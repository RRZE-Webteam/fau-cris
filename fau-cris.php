<?php
/**
 * Plugin Name: FAU CRIS
 * Description: Anzeige von Daten aus dem FAU-Forschungsportal CRIS in WP-Seiten
 * Version: 3.3
 * Author: RRZE-Webteam
 * Author URI: http://blogs.fau.de/webworking/
 * Text Domain: fau-cris
 * Domain Path: /languages
 * License: GPLv2 or later
 */
/*
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 */

add_action('plugins_loaded', array('FAU_CRIS', 'instance'));

register_activation_hook(__FILE__, array('FAU_CRIS', 'activate'));
register_deactivation_hook(__FILE__, array('FAU_CRIS', 'deactivate'));

class FAU_CRIS {

    /**
     * Get Started
     */
    const version = '3.3';
    const option_name = '_fau_cris';
    const site_option_name = '_fau_cris_site';
    const version_option_name = '_fau_cris_version';
    const textdomain = 'fau-cris';
    const php_version = '5.3'; // Minimal erforderliche PHP-Version
    const wp_version = '3.9.2'; // Minimal erforderliche WordPress-Version

    protected static $instance = null;
    private static $cris_option_page = null;

    public static function instance() {

        if (null == self::$instance) {
            self::$instance = new self;
        }

        return self::$instance;
    }

    private function __construct() {
        load_plugin_textdomain('fau-cris', false, dirname(plugin_basename(__FILE__)) . '/languages');

        add_action('admin_init', array(__CLASS__, 'admin_init'));
        add_action('admin_menu', array(__CLASS__, 'add_options_page'));
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), array(__CLASS__, 'add_action_links'));

        add_action('wp_enqueue_scripts', array(__CLASS__, 'cris_enqueue_styles'));

        add_shortcode('cris', array(__CLASS__, 'cris_shortcode'));
        add_shortcode('cris-custom', array(__CLASS__, 'cris_custom_shortcode'));

        add_action('update_option_' . self::option_name, array(__CLASS__, 'cris_cron'), 10, 2);
        add_action('cris_auto_update', array(__CLASS__, 'cris_auto_sync'));

        if (is_network_admin()) {
            add_action('network_admin_menu', array(__CLASS__, 'cris_add_network_settings_page'));
            add_action('network_admin_edit_cris_update_network_settings',  array( __CLASS__, 'cris_update_network_settings'), 10, 0);
        }
    }

    /**
     * Check PHP and WP Version
     */
    public static function activate() {
        self::version_compare();
        update_option(self::version_option_name, self::version);
    }

    public static function deactivate() {
        wp_clear_scheduled_hook('cris_auto_update');
    }

    private static function version_compare() {
        $error = '';

        if (version_compare(PHP_VERSION, self::php_version, '<')) {
            $error = sprintf(__('Ihre PHP-Version %s ist veraltet. Bitte aktualisieren Sie mindestens auf die PHP-Version %s.', 'fau-cris'), PHP_VERSION, self::php_version);
        }

        if (version_compare($GLOBALS['wp_version'], self::wp_version, '<')) {
            $error = sprintf(__('Ihre Wordpress-Version %s ist veraltet. Bitte aktualisieren Sie mindestens auf die Wordpress-Version %s.', 'fau-cris'), $GLOBALS['wp_version'], self::wp_version);
        }

        if (!empty($error)) {
            deactivate_plugins(plugin_basename(__FILE__), false, true);
            wp_die(
                    $error, __('Fehler bei der Aktivierung des Plugins', 'fau-cris'), array(
                'response' => 500,
                'back_link' => TRUE
                    )
            );
        }
    }

    public static function update_version() {
        if (get_option(self::version_option_name, null) != self::version)
            update_option(self::version_option_name, self::version);
    }

    /**
     * Display settings link on the plugins page (beside the activate/deactivate links)
     */
    public static function add_action_links($links) {
        $mylinks = array(
            '<a href="' . admin_url('options-general.php?page=options-fau-cris') . '">' . __('Einstellungen', 'fau-cris') . '</a>',
        );
        return array_merge($links, $mylinks);
    }

    /**
     * Get Options
     */
    private static function get_options() {
        $defaults = self::default_options();
        $options = (array) get_option(self::option_name);
        $options = wp_parse_args($options, $defaults);
        $options = array_intersect_key($options, $defaults);
        return $options;
    }

    /**
     * Set default options
     */
    private static function default_options() {
        require_once("class_Tools.php");
        $options = array(
            'cris_org_nr' => '',
            'cris_cache' => '18000',
            'cris_pub_order' => Tools::getOptionsOrder('publications'),
            'cris_pub_subtypes_order' => Tools::getOptionsOrder('pubothersubtypes'),
            'cris_univis' => 'none',
            'cris_bibtex' => 0,
            'cris_award_order' => Tools::getOptionsOrder('awards'),
            'cris_award_link' => 'none',
            'cris_project_order' => Tools::getOptionsOrder('projects'),
            'cris_project_link' => 'none',
            'cris_patent_order' => Tools::getOptionsOrder('patents'),
            'cris_patent_link' => 'none',
            'cris_activities_order' => Tools::getOptionsOrder('activities'),
            'cris_activities_link' => 'none',
            'cris_sync_check' => 0
        );
        return $options;
    }

    /**
     * Add options page
     */
    public static function add_options_page() {
        self::$cris_option_page = add_options_page(
                'CRIS: Einstellungen', 'CRIS', 'manage_options', 'options-fau-cris', array(__CLASS__, 'options_fau_cris')
        );
        add_action('load-' . self::$cris_option_page, array(__CLASS__, 'cris_help_menu'));
    }

    /*
     * Add Network Options Page
     */

    public static function cris_add_network_settings_page() {
        add_submenu_page('settings.php', 'CRIS', 'CRIS', 'manage_network_options', 'fau-cris', array(__CLASS__, 'cris_show_network_settings'));
    }

    public static function cris_get_network_settings() {
        $settings = array(
            array('id'   => 'dbhost',
                'name' => __( 'Hostname', 'fau-cris' ),
                'desc' => '',
                'type' => 'text',
                'size' => 'regular'),
            array('id'   => 'dbname',
                'name' => __( 'Datenbankname', 'fau-cris' ),
                'desc' => '',
                'type' => 'text',
                'size' => 'regular'),
            array('id'   => 'dbuser',
                'name' => __( 'Benutzer', 'fau-cris' ),
                'desc' => '',
                'type' => 'text',
                'size' => 'regular'),
            array('id'   => 'dbpw',
                'name' => __( 'Passwort', 'fau-cris' ),
                'desc' => '',
                'type' => 'text',
                'size' => 'regular'),
        );
        return apply_filters( 'plugin_settings', $settings );
    }

    public static function cris_show_network_settings() {
        $settings = self::cris_get_network_settings();
        ?>
        <div class="wrap">
            <h2><?php _e('CRIS', 'fau-cris');?></h2>
            <form action="edit.php?action=cris_update_network_settings" method="POST">
                <?php wp_nonce_field('cris_update_network_settings', 'cris_update_network_nonce'); ?>
                <table id="menu" class="form-table">
                    <?php foreach ($settings as $setting) { ?>
                    <tr valign="top">
                        <th scope="row"><?php echo $setting['name']; ?></th>
                        <td>
                            <input type="<?php echo $setting['type']; ?>"
                                   name="<?php echo self::site_option_name . '[' . $setting['id'] . ']'; ?>"
                                   id="<?php echo self::site_option_name . '[' . $setting['id'] . ']'; ?>"
                                   value="<?php echo esc_attr( get_site_option( self::site_option_name)[$setting['id']]); ?>"
                                   class="<?php echo $setting['size'] . '-' . $setting['type']; ?>"/>
                            <?php if (isset($setting['desc']) & $setting['desc'] != '') {
                                echo '<p class="description">' . $setting['desc'] . '</p>';} ?>
                        </td>
                    </tr>
                    <?php } ?>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
    <?php }

    public static function cris_update_network_settings(){
        check_admin_referer('cris_update_network_settings', 'cris_update_network_nonce');
        if(!current_user_can('manage_network_options'))
            return;
        $posted_settings = array_map( 'sanitize_text_field', $_POST[self::site_option_name] );
        update_site_option( self::site_option_name,$posted_settings);
        wp_redirect(add_query_arg(array('page' => 'fau-cris', 'updated' => 'true'), network_admin_url('settings.php')));
        exit;
    }

    /*
     * Options page tabs
     */

    private static function options_page_tabs() {
        $tabs = array(
            'general' => __('Allgemein', 'fau-cris'),
            'layout' => __('Darstellung', 'fau-cris'),
            'sync' => __('Synchronisierung', 'fau-cris')
        );
        return $tabs;
    }

    private static function current_tab($tab) {
        $tabs = self::options_page_tabs();
        if (isset($tab['tab'])) {
            $current = $tab['tab'];
        } else {
            reset($tabs);
            $current = key($tabs);
        }
        return $current;
    }

    /**
     * Options page callback
     */
    public static function options_fau_cris() {
        $tabs = self::options_page_tabs();
        $current = self::current_tab($_GET);
        if (isset($_GET['action']) && $_GET['action'] == 'cris_sync') {
            include 'class_Sync.php';
            $sync = new Sync();
            $result = $sync->do_sync();
        }
        $options = self::get_options();
        ?>

        <div class="wrap">
            <h2><?php _e('Einstellungen', 'fau-cris'); ?> &rsaquo; CRIS</h2>
            <h2 class="nav-tab-wrapper">
            <?php
            foreach ($tabs as $tab => $name) {
                $class = ( $tab == $current ) ? ' nav-tab-active' : '';
                echo "<a class='nav-tab$class' href='?page=options-fau-cris&tab=$tab'>$name</a>";
            }
            ?>
            </h2>
                <?php
                if (isset($result)) {
                    print $result;
                }
                ?>
            <form method="post" action="options.php">
        <?php
        settings_fields('fau_cris_options');
        do_settings_sections('fau_cris_options');
        if (isset($current) && $current == 'sync' && (isset($options['cris_sync_check']) && $options['cris_sync_check'] == 1)) {
            echo '<a href="?page=options-fau-cris&tab=sync&action=cris_sync" name="sync-now" id="sync-now" class="button button-secondary" style="margin-bottom: 10px;" ><span class="dashicons dashicons-image-rotate" style="margin: 3px 5px 0 0;"></span>' . __('Jetzt synchronisieren', 'fau-cris') . '</a>';
        }
        submit_button();
        ?>
            </form>
        </div>
        <?php
    }

    /**
     * Register and add settings
     */
    public static function admin_init() {

        register_setting(
                'fau_cris_options', // Option group
                self::option_name, // Option name
                array(__CLASS__, 'sanitize') // Sanitize Callback
        );

        if (isset($_GET))
            $tab = self::current_tab($_GET);
        switch ($tab) {
            case 'general' :
            default:
                // Form Settings 1
                add_settings_section(
                        'cris_section', // ID
                        '', // Title
                        '__return_false', // Callback
                        'fau_cris_options' // Page
                );
                add_settings_field(
                        'cris_org_nr', // ID
                        __('CRIS-OrgNr.', 'fau-cris'), // Title
                        array(__CLASS__, 'cris_textbox_callback'), // Callback
                        'fau_cris_options', // Page
                        'cris_section', // Section
                        array(
                    'name' => 'cris_org_nr',
                    'description' => __('Sie können auch mehrere Organisationsnummern &ndash; durch Komma getrennt &ndash; eingeben.', 'fau-cris')
                        )
                );
                break;
            case 'layout' :
                add_settings_section(
                        'cris_publications_section', // ID
                        __('Publikationen', 'fau-cris'), // Title
                        '__return_false', // Callback
                        'fau_cris_options' // Page
                );
                add_settings_field(
                        'cris_pub_order', __('Reihenfolge der Publikationen', 'fau-cris'), array(__CLASS__, 'cris_textarea_callback'), 'fau_cris_options', 'cris_publications_section', array(
                    'name' => 'cris_pub_order',
                    'description' => __('Wenn Sie die Publikationsliste nach Publikationstypen geordnet ausgeben, können Sie hier angeben, in welcher Reihenfolge die Typen aufgelistet werden. Eine Liste aller Typen finden Sie im Hilfemenü unter "Shortcode Publikationen". Ein Eintrag pro Zeile. ', 'fau-cris')
                        )
                );
                add_settings_field(
                        'cris_pub_subtypes_order', __('Reihenfolge der Publikationen-Subtypen unter "Andere"', 'fau-cris'), array(__CLASS__, 'cris_textarea_callback'), 'fau_cris_options', 'cris_publications_section', array(
                    'name' => 'cris_pub_subtypes_order',
                        //'description' => __('Wenn Sie die Publikationsliste nach Publikationstypen geordnet ausgeben, können Sie hier angeben, in welcher Reihenfolge die Typen aufgelistet werden. Eine Liste aller Typen finden Sie im Hilfemenü unter "Shortcode Publikationen". Ein Eintrag pro Zeile. ', 'fau-cris')
                        )
                );
                add_settings_field(
                        'cris_bibtex', __('BibTeX-Link', 'fau-cris'), array(__CLASS__, 'cris_check_callback'), 'fau_cris_options', 'cris_publications_section', array(
                    'name' => 'cris_bibtex',
                    'description' => __('Soll für jede Publikation ein Link zum BibTeX-Export angezeigt werden?', 'fau-cris')
                        )
                );
                add_settings_field(
                        'cris_univis', __('Autoren verlinken', 'fau-cris'), array(__CLASS__, 'cris_radio_callback'), 'fau_cris_options', 'cris_publications_section', array(
                    'name' => 'cris_univis',
                    'options' => array(
                        'person' => __('Autoren mit ihrer Personen-Detailansicht im FAU-Person-Plugin verlinken', 'fau-cris'),
                        'cris' => __('Autoren mit ihrer Profilseite auf cris.fau.de verlinken', 'fau-cris'),
                        'none' => __('keinen Link setzen', 'fau-cris'))
                        )
                );
                add_settings_section(
                        'cris_awards_section', // ID
                        __('Auszeichnungen', 'fau-cris'), // Title
                        '__return_false', // Callback
                        'fau_cris_options' // Page
                );
                add_settings_field(
                        'cris_award_order', __('Reihenfolge der Auszeichnungen', 'fau-cris'), array(__CLASS__, 'cris_textarea_callback'), 'fau_cris_options', 'cris_awards_section', array(
                    'name' => 'cris_award_order',
                    'description' => __('Siehe Reihenfolge der Publikationen. Nur eben für die Auszeichnungen.', 'fau-cris')
                        )
                );
                add_settings_field(
                        'cris_award_link', __('Preisträger verlinken', 'fau-cris'), array(__CLASS__, 'cris_radio_callback'), 'fau_cris_options', 'cris_awards_section', array(
                    'name' => 'cris_award_link',
                    'options' => array(
                        'person' => __('Preisträger mit ihrer Personen-Detailansicht im FAU-Person-Plugin verlinken', 'fau-cris'),
                        'cris' => __('Preisträger mit ihrer Profilseite auf cris.fau.de verlinken', 'fau-cris'),
                        'none' => __('keinen Link setzen', 'fau-cris'))
                        )
                );
                add_settings_section(
                        'cris_projects_section', // ID
                        __('Forschungsprojekte', 'fau-cris'), // Title
                        '__return_false', // Callback
                        'fau_cris_options' // Page
                );
                add_settings_field(
                        'cris_project_order', __('Reihenfolge der Forschungsprojekte', 'fau-cris'), array(__CLASS__, 'cris_textarea_callback'), 'fau_cris_options', 'cris_projects_section', array(
                    'name' => 'cris_project_order',
                    'description' => __('Siehe Reihenfolge der Publikationen. Nur eben für die Forschungsprojekte.', 'fau-cris')
                        )
                );
                add_settings_field(
                        'cris_project_link', __('Projektbeteiligte verlinken', 'fau-cris'), array(__CLASS__, 'cris_radio_callback'), 'fau_cris_options', 'cris_projects_section', array(
                    'name' => 'cris_project_link',
                    'options' => array(
                        'person' => __('Projektleiter und -beteiligte mit ihrer Personen-Detailansicht im FAU-Person-Plugin verlinken', 'fau-cris'),
                        'cris' => __('Projektleiter und -beteiligte mit ihrer Profilseite auf cris.fau.de verlinken', 'fau-cris'),
                        'none' => __('keinen Link setzen', 'fau-cris'))
                        )
                );
                add_settings_section(
                        'cris_patents_section', // ID
                        __('Patente', 'fau-cris'), // Title
                        '__return_false', // Callback
                        'fau_cris_options' // Page
                );
                add_settings_field(
                        'cris_patent_order', __('Reihenfolge der Patente', 'fau-cris'), array(__CLASS__, 'cris_textarea_callback'), 'fau_cris_options', 'cris_patents_section', array(
                    'name' => 'cris_patent_order',
                    'description' => __('Siehe Reihenfolge der Publikationen. Nur eben für die Patente.', 'fau-cris')
                        )
                );
                add_settings_field(
                        'cris_patent_link', __('Patentinhaber verlinken', 'fau-cris'), array(__CLASS__, 'cris_radio_callback'), 'fau_cris_options', 'cris_patents_section', array(
                    'name' => 'cris_patent_link',
                    'options' => array(
                        'person' => __('Patentinhaber mit ihrer Personen-Detailansicht im FAU-Person-Plugin verlinken', 'fau-cris'),
                        'cris' => __('Patentinhaber mit ihrer Profilseite auf cris.fau.de verlinken', 'fau-cris'),
                        'none' => __('keinen Link setzen', 'fau-cris'))
                        )
                );
                add_settings_section(
                        'cris_activities_section', // ID
                        __('Aktivitäten', 'fau-cris'), // Title
                        '__return_false', // Callback
                        'fau_cris_options' // Page
                );
                add_settings_field(
                        'cris_activities_order', __('Reihenfolge der Aktivitäten', 'fau-cris'), array(__CLASS__, 'cris_textarea_callback'), 'fau_cris_options', 'cris_activities_section', array(
                    'name' => 'cris_activities_order',
                    'description' => __('Siehe Reihenfolge der Publikationen. Nur eben für die Aktivitäten.', 'fau-cris')
                        )
                );
                add_settings_field(
                        'cris_activities_link', __('Personen verlinken', 'fau-cris'), array(__CLASS__, 'cris_radio_callback'), 'fau_cris_options', 'cris_activities_section', array(
                    'name' => 'cris_activities_link',
                    'options' => array(
                        'person' => __('Personen mit ihrer Personen-Detailansicht im FAU-Person-Plugin verlinken', 'fau-cris'),
                        'cris' => __('Personen mit ihrer Profilseite auf cris.fau.de verlinken', 'fau-cris'),
                        'none' => __('keinen Link setzen', 'fau-cris'))
                        )
                );
                break;
            case 'sync':
                add_settings_section(
                        'cris_sync_section', // ID
                        '', // Title
                        '__return_false', // Callback
                        'fau_cris_options' // Page
                );
                add_settings_field(
                        'cris_sync_check', __('Automatische Synchronisation', 'fau-cris'), array(__CLASS__, 'cris_check_callback'), 'fau_cris_options', 'cris_sync_section', array(
                    'name' => 'cris_sync_check',
                    'description' => __('Sollen für neue Projekte und Forschungsbereiche automatisch Seiten und Menüeinträge generiert werden?', 'fau-cris')
                        )
                );
                break;
        }
    }

    /**
     * Sanitize each setting field as needed
     */
    public static function sanitize() {

        $new_input = self::get_options();
        $default_options = self::default_options();
        $parts = parse_url($_POST['_wp_http_referer']);
        parse_str($parts['query'], $query);
        $tab = $query['tab'];

        switch ($tab) {
            case 'general':
            default:
                $new_input['cris_org_nr'] = isset($_POST[self::option_name]['cris_org_nr']) ? sanitize_text_field($_POST[self::option_name]['cris_org_nr']) : 0;
                break;

            case 'layout':
                $new_input['cris_pub_order'] = isset($_POST[self::option_name]['cris_pub_order']) ? explode("\n", str_replace("\r", "", $_POST[self::option_name]['cris_pub_order'])) : $default_options['cris_pub_order'];
                $new_input['cris_pub_subtypes_order'] = isset($_POST[self::option_name]['cris_pub_subtypes_order']) ? explode("\n", str_replace("\r", "", $_POST[self::option_name]['cris_pub_subtypes_order'])) : $default_options['cris_pub_subtypes_order'];
                $new_input['cris_univis'] = in_array($_POST[self::option_name]['cris_univis'], array('person', 'cris', 'none')) ? $_POST[self::option_name]['cris_univis'] : $default_options['cris_univis'];
                $new_input['cris_bibtex'] = isset($_POST[self::option_name]['cris_bibtex']) ? 1 : 0;
                $new_input['cris_award_order'] = isset($_POST[self::option_name]['cris_award_order']) ? explode("\n", str_replace("\r", "", $_POST[self::option_name]['cris_award_order'])) : $default_options['cris_award_order'];
                $new_input['cris_award_link'] = in_array($_POST[self::option_name]['cris_award_link'], array('person', 'cris', 'none')) ? $_POST[self::option_name]['cris_award_link'] : $default_options['cris_award_link'];
                $new_input['cris_project_order'] = isset($_POST[self::option_name]['cris_project_order']) ? explode("\n", str_replace("\r", "", $_POST[self::option_name]['cris_project_order'])) : $default_options['cris_project_order'];
                $new_input['cris_project_link'] = in_array($_POST[self::option_name]['cris_project_link'], array('person', 'cris', 'none')) ? $_POST[self::option_name]['cris_project_link'] : $default_options['cris_project_link'];
                $new_input['cris_patent_order'] = isset($_POST[self::option_name]['cris_patent_order']) ? explode("\n", str_replace("\r", "", $_POST[self::option_name]['cris_patent_order'])) : $default_options['cris_patent_order'];
                $new_input['cris_patent_link'] = in_array($_POST[self::option_name]['cris_patent_link'], array('person', 'cris', 'none')) ? $_POST[self::option_name]['cris_patent_link'] : $default_options['cris_patent_link'];
                $new_input['cris_activities_order'] = isset($_POST[self::option_name]['cris_activities_order']) ? explode("\n", str_replace("\r", "", $_POST[self::option_name]['cris_activities_order'])) : $default_options['cris_activities_order'];
                $new_input['cris_activities_link'] = in_array($_POST[self::option_name]['cris_activities_link'], array('person', 'cris', 'none')) ? $_POST[self::option_name]['cris_activities_link'] : $default_options['cris_activities_link'];
                break;
            case 'sync':
                $new_input['cris_sync_check'] = isset($_POST[self::option_name]['cris_sync_check']) ? 1 : 0;
                break;
        }
        return $new_input;
    }

    /**
     * Get the settings option array and print its values
     */
    // Checkbox
    public static function cris_check_callback($args) {
        $options = self::get_options();
        if (array_key_exists('name', $args))
            $name = esc_attr($args['name']);
        if (array_key_exists('description', $args))
            $description = esc_attr($args['description']);
        if ($name == 'cris_sync_check') {
            print "<p>";
            printf(__('%1s Wichtig! %2s Lesen Sie vor der Aktivierung unbedingt die Hinweise in unserem %3s Benutzerhandbuch! %3s', 'fau-cris'), '<strong>', '</strong>', '<a href="https://www.wordpress.rrze.fau.de/plugins/fau-cris/erweiterte-optionen/">', '</a>');
            print "<p>";
        }
        ?>
        <label><input name="<?php printf('%s[' . $name . ']', self::option_name); ?>" type='checkbox' value='1'         <?php
            if (array_key_exists($name, $options)) {
                print checked($options[$name], 1, false);
            }
            ?> >
        <?php if (isset($description)) { ?>
                <span class="description"><?php echo $description; ?></span></label>
        <?php
        }
    }

    // Radio Button
    public static function cris_radio_callback($args) {
        $options = self::get_options();
        if (array_key_exists('name', $args))
            $name = esc_attr($args['name']);
        if (array_key_exists('description', $args))
            $description = esc_attr($args['description']);
        if (array_key_exists('options', $args))
            $radios = $args['options'];
        foreach ($radios as $_k => $_v) {
            ?>
            <label>
                <input name="<?php printf('%s[' . $name . ']', self::option_name); ?>"
                       type='radio'
                       value='<?php print $_k; ?>'
                       <?php
                       if (array_key_exists($name, $options)) {
                           checked($options[$name], $_k);
                       }
                       ?>
                       >
            <?php print $_v; ?>
            </label><br />
        <?php }

        if (isset($description)) {
            ?>
            <p class="description"><?php echo $description; ?></p>
        <?php
        }
    }

    // Textbox
    public static function cris_textbox_callback($args) {
        $options = self::get_options();
        if (array_key_exists('name', $args))
            $name = esc_attr($args['name']);
        if (array_key_exists('description', $args))
            $description = esc_attr($args['description']);
        ?>
        <input name="<?php printf('%s[' . $name . ']', self::option_name); ?>" type='text' value="<?php
        if (array_key_exists($name, $options)) {
            echo $options[$name];
        }
        ?>" ><br />
        <?php if (isset($description)) { ?>
            <span class="description"><?php echo $description; ?></span>
            <?php
        }
    }

    // Textarea
    public static function cris_textarea_callback($args) {
        $options = self::get_options();
        $default_options = self::default_options();
        if (array_key_exists('name', $args))
            $name = esc_attr($args['name']);
        if (array_key_exists('description', $args))
            $description = esc_attr($args['description']);
        ?>
        <textarea name="<?php printf('%s[' . $name . ']', self::option_name); ?>" cols="30" rows="8"><?php
            if (array_key_exists($name, $options)) {
                if (is_array($options[$name]) && count($options[$name]) > 0 && $options[$name][0] != '') {
                    echo implode("\n", $options[$name]);
                } else {
                    echo implode("\n", $default_options[$name]);
                }
            }
            ?></textarea><br />
        <?php if (isset($description)) { ?>
            <span class="description"><?php echo $description; ?></span>
            <?php
        }
    }

    /**
     * Add Shortcodes
     */
    public static function cris_shortcode($atts) {
        $options = self::get_options();

        // Attributes
        extract(shortcode_atts(
                        array(
            'show' => 'publications',
            'orderby' => '',
            'year' => '',
            'start' => '',
            'orgid' => isset($options['cris_org_nr']) ? $options['cris_org_nr'] : '',
            'persid' => '',
            'publication' => '',
            'pubtype' => '',
            'quotation' => '',
            'items' => '',
            'sortby' => '',
            'award' => '',
            'awardnameid' => '',
            'type' => '',
            'subtype' => '',
            'showname' => 1,
            'showyear' => 1,
            'showawardname' => 1,
            'display' => 'list',
            'project' => '',
            'hide' => '',
            'role' => 'leader',
            'patent' => '',
            'activity' => '',
            'field' => ''
                        ), $atts));

        $show = sanitize_text_field($show);
        $orderby = sanitize_text_field($orderby);
        $type = (!empty($pubtype)) ? sanitize_text_field($pubtype) : sanitize_text_field($type); //Abwärtskompatibilität
        $subtype = sanitize_text_field($subtype);
        $year = sanitize_text_field($year);
        $start = sanitize_text_field($start);
        $orgid = sanitize_text_field($orgid);
        $persid = sanitize_text_field($persid);
        $publication = sanitize_text_field($publication);
        $quotation = sanitize_text_field($quotation);
        $items = sanitize_text_field($items);
        if (in_array($sortby, array('created', 'updated')))
            $sortby = sanitize_text_field($sortby);
        $award = sanitize_text_field($award);
        $awardnameid = sanitize_text_field($awardnameid);
        $showname = sanitize_text_field($showname);
        $showyear = sanitize_text_field($showyear);
        $showawardname = sanitize_text_field($showawardname);
        $display = sanitize_text_field($display);
        $project = sanitize_text_field($project);
        $hide = sanitize_text_field($hide);
        $hide = str_replace(" ", "", $hide);
        $hide = explode(",", $hide);
        $role = sanitize_text_field($role);
        $patent = sanitize_text_field($patent);
        $activity = sanitize_text_field($activity);
        $field = sanitize_text_field($field);

        if (isset($publication) && $publication != '') {
            $param1 = 'publication';
            $publication = str_replace(' ', '', $publication);
            $publications = explode(',', $publication);
            $param2 = $publications;
        } elseif (isset($field) && $field != '') {
            $param1 = 'field';
            $field = str_replace(' ', '', $field);
            $fields = explode(',', $field);
            $param2 = $fields;
        } elseif (isset($activity) && $activity != '') {
            $param1 = 'activity';
            $activity = str_replace(' ', '', $activity);
            $activitys = explode(',', $activity);
            $param2 = $activitys;
        } elseif (isset($patent) && $patent != '') {
            $param1 = 'patent';
            $patent = str_replace(' ', '', $patent);
            $patents = explode(',', $patent);
            $param2 = $patents;
        } elseif (isset($award) && $award != '') {
            $param1 = 'award';
            $param2 = $award;
        } elseif (isset($project) && $project != '') {
            $param1 = 'project';
            $project = str_replace(' ', '', $project);
            $projects = explode(',', $project);
            $param2 = $projects;
        } elseif (isset($awardnameid) && $awardnameid != '') {
            $param1 = 'awardnameid';
            $awardnameid = str_replace(' ', '', $awardnameid);
            $awardnameids = explode(',', $awardnameid);
            $param2 = $awardnameids;
        } elseif (isset($persid) && $persid != '') {
            $param1 = 'person';
            $persid = str_replace(' ', '', $persid);
            $persids = explode(',', $persid);
            $param2 = $persids;
        } elseif (isset($orgid) && $orgid != '') {
            $param1 = 'orga';
            $orgid = str_replace(' ', '', $orgid);
            $orgids = explode(',', $orgid);
            $param2 = $orgids;
        } else {
            $param1 = '';
            $param2 = '';
        }

        // IDs mit zu groüen Abfragemengen ausschließen
        $excluded = array(
            '143134', // FAU
            '141815', // MedFak
            '142105', // NatFak
            '141354', // PhilFak
            '141678', // ReWi
            '142351'  // Techfak
        );

        if ((!$orgid || $orgid == 0) && $persid == '' && $publication == '' && $award == '' && $awardnameid == '' && $field == '') {
            // Fehlende ID oder ID=0 abfangen
            return __('Bitte geben Sie eine CRIS-ID an.', 'fau-cris') . '</strong></p>';
        } /* elseif (in_array($orgid, $excluded)
          &&  $persid == ''
          && (($show == 'awards' && $award == '') || ($show == 'publications' && $publication == ''))
          && ($year == '' && $type == '')
          ) {
          // IDs mit zu vielen Ergebnissen ausschließen
          return __('Abfragemenge zu groß. Bitte filtern Sie nach Jahr oder Typ.','fau-cris');
          } */ else {
            $order1 = 'year';
            $order2 = '';

            if (!empty($orderby)) {
                if (strpos($orderby, ',') !== false) {
                    $orderby = str_replace(' ', '', $orderby);
                    $order1 = explode(',', $orderby)[0];
                    $order2 = explode(',', $orderby)[1];
                } else {
                    $order1 = $orderby;
                    $order2 = '';
                }
            }

            if (isset($show) && $show == 'organisation') {
                // Forschungsbereiche
                require_once('class_Organisation.php');
                $liste = new Organisation($param1, $param2);
                return $liste->singleOrganisation($hide);
            } elseif (isset($show) && $show == 'fields') {
                // Forschungsbereiche
                require_once('class_Forschungsbereiche.php');
                $liste = new Forschungsbereiche($param1, $param2);

                if ($field != '') {
                    return $liste->singleField($hide);
                }
                if (!empty($items)) {
                    return $liste->fieldListe();
                }
                return $liste->fieldListe();
            } elseif (isset($show) && $show == 'activities') {
                // Projekte
                require_once('class_Aktivitaeten.php');
                $liste = new Aktivitaeten($param1, $param2);

                if ($activity != '') {
                    return $liste->singleActivity($hide);
                }
                if (!empty($items)) {
                    return $liste->actiListe($year, $start, $type, $items, $hide);
                }
                if (strpos($order1, 'type') !== false) {
                    return $liste->actiNachTyp($year, $start, $type, $hide);
                }
                if (strpos($order1, 'year') !== false) {
                    return $liste->actiNachJahr($year, $start, $type, $hide);
                }
                return $liste->actiListe($year, $start, $type, $items, $hide);
            } elseif (isset($show) && $show == 'patents') {
                // Projekte
                require_once('class_Patente.php');
                $liste = new Patente($param1, $param2);

                if ($patent != '') {
                    return $liste->singlePatent($hide);
                }
                if (!empty($items)) {
                    return $liste->patListe($year, $start, $type, $items, $hide);
                }
                if (strpos($order1, 'type') !== false) {
                    return $liste->patNachTyp($year, $start, $type, $hide);
                }
                if (strpos($order1, 'year') !== false) {
                    return $liste->patNachJahr($year, $start, $type, $hide);
                }
                return $liste->patListe($year, $start, $type, $items, $hide);
            } elseif (isset($show) && $show == 'projects') {
                // Projekte
                require_once('class_Projekte.php');
                $liste = new Projekte($param1, $param2);

                if ($project != '') {
                    return $liste->singleProj($hide);
                }
                if (!empty($items)) {
                    return $liste->projListe($year, $start, $type, $items, $hide, $role);
                }
                if (strpos($order1, 'type') !== false) {
                    return $liste->projNachTyp($year, $start, $type, $hide, $role);
                }
                if (strpos($order1, 'year') !== false) {
                    return $liste->projNachJahr($year, $start, $type, $hide, $role);
                }
                return $liste->projListe($year, $start, $type, $items, $hide, $role);
            } elseif (isset($show) && $show == 'awards') {
                // Awards
                require_once('class_Auszeichnungen.php');
                $liste = new Auszeichnungen($param1, $param2, $display);

                if ($award != '') {
                    return $liste->singleAward($showname, $showyear, $showawardname, $display);
                }
                if (strpos($order1, 'type') !== false) {
                    return $liste->awardsNachTyp($year, $start, $type, $awardnameid, $showname, $showyear, $showawardname, $display, $order2);
                }
                if (strpos($order1, 'year') !== false) {
                    return $liste->awardsNachJahr($year, $start, $type, $awardnameid, $showname, 0, $showawardname, $display, $order2);
                }
                return $liste->awardsListe($year, $start, $type, $awardnameid, $showname, $showyear, $showawardname, $display);
            } else {
                // Publications
                require_once('class_Publikationen.php');
                $liste = new Publikationen($param1, $param2);

                if ($publication != '') {
                    return $liste->singlePub($quotation);
                }
                if (!empty($items) || !empty($sortby)) {
                    return $liste->pubListe($year, $start, $type, $subtype, $quotation, $items, $sortby);
                }
                if (strpos($order1, 'type') !== false) {
                    return $liste->pubNachTyp($year, $start, $type, $subtype, $quotation, $order2);
                }
                return $liste->pubNachJahr($year, $start, $type, $subtype, $quotation, $order2);
            }
        }
        // nothing
        return '';
    }

    public static function cris_custom_shortcode($atts, $content = null) {
        $options = self::get_options();

        // Attributes
        extract(shortcode_atts(
                        array(
            'show' => 'publications',
            'orderby' => '',
            'year' => '',
            'start' => '',
            'orgid' => isset($options['cris_org_nr']) ? $options['cris_org_nr'] : '',
            'persid' => '',
            'publication' => '',
            'pubtype' => '',
            'quotation' => '',
            'items' => '',
            'sortby' => '',
            'award' => '',
            'awardnameid' => '',
            'type' => '',
            'subtype' => '',
            'showname' => 1,
            'showyear' => 1,
            'showawardname' => 1,
            'display' => 'list',
            'project' => '',
            'hide' => '',
            'role' => 'leader',
            'patent' => '',
            'activity' => '',
            'field' => ''
                        ), $atts));

        $show = sanitize_text_field($show);
        $orderby = sanitize_text_field($orderby);
        $type = (!empty($pubtype)) ? sanitize_text_field($pubtype) : sanitize_text_field($type); //Abwärtskompatibilität
        $subtype = sanitize_text_field($subtype);
        $year = sanitize_text_field($year);
        $start = sanitize_text_field($start);
        $orgid = sanitize_text_field($orgid);
        $persid = sanitize_text_field($persid);
        $publication = sanitize_text_field($publication);
        $quotation = sanitize_text_field($quotation);
        $items = sanitize_text_field($items);
        if (in_array($sortby, array('created', 'updated')))
            $sortby = sanitize_text_field($sortby);
        $award = sanitize_text_field($award);
        $awardnameid = sanitize_text_field($awardnameid);
        $showname = sanitize_text_field($showname);
        $showyear = sanitize_text_field($showyear);
        $showawardname = sanitize_text_field($showawardname);
        $display = sanitize_text_field($display);
        $project = sanitize_text_field($project);
        $role = sanitize_text_field($role);
        $patent = sanitize_text_field($patent);
        $activity = sanitize_text_field($activity);
        $field = sanitize_text_field($field);

        if (isset($publication) && $publication != '') {
            $param1 = 'publication';
            if (strpos($publication, ',')) {
                $publication = str_replace(' ', '', $publication);
                $publication = explode(',', $publication);
            }
            $param2 = $publication;
        } elseif (isset($field) && $field != '') {
            $param1 = 'field';
            $param2 = $field;
        } elseif (isset($activity) && $activity != '') {
            $param1 = 'activity';
            $param2 = $activity;
        } elseif (isset($patent) && $patent != '') {
            $param1 = 'patent';
            $param2 = $patent;
        } elseif (isset($award) && $award != '') {
            $param1 = 'award';
            $param2 = $award;
        } elseif (isset($project) && $project != '') {
            $param1 = 'project';
            if (strpos($project, ',') !== false) {
                $project = str_replace(' ', '', $project);
                $project = explode(',', $project);
            }
            $param2 = $project;
        } elseif (isset($awardnameid) && $awardnameid != '') {
            $param1 = 'awardnameid';
            $param2 = $awardnameid;
        } elseif (isset($persid) && $persid != '') {
            $param1 = 'person';
            if (strpos($persid, ',') !== false) {
                $persid = str_replace(' ', '', $persid);
                $persid = explode(',', $persid);
            }
            $param2 = $persid;
        } elseif (isset($orgid) && $orgid != '') {
            $param1 = 'orga';
            if (strpos($orgid, ',') !== false) {
                $orgid = str_replace(' ', '', $orgid);
                $orgid = explode(',', $orgid);
            }
            $param2 = $orgid;
        } else {
            $param1 = '';
            $param2 = '';
        }

        $order1 = 'year';
        $order2 = '';

        if (!empty($orderby)) {
            if (strpos($orderby, ',') !== false) {
                $orderby = str_replace(' ', '', $orderby);
                $order1 = explode(',', $orderby)[0];
                $order2 = explode(',', $orderby)[1];
            } else {
                $order1 = $orderby;
                $order2 = '';
            }
        }

        if (isset($show) && $show == 'projects') {
            // Projekte
            require_once('class_Projekte.php');
            $liste = new Projekte($param1, $param2);

            if ($project != '') {
                return $liste->customProj($content);
            }
            if (!empty($items)) {
                //    return $liste->projListe($year, $start, $type, $items, $hide, $role);
            }
            if (strpos($order1, 'type') !== false) {
                return $liste->projNachTyp($year, $start, $type, $hide = array(), $role, $content);
            }
            if (strpos($order1, 'year') !== false) {
                return $liste->projNachJahr($year, $start, $type, $hide = array(), $role, $content);
            }
            //return $liste->projListe($year, $start, $type, $items, $hide, $role);
        }
    }

    public static function cris_enqueue_styles() {
        global $post;
        $plugin_url = plugin_dir_url(__FILE__);
        wp_enqueue_style('cris', $plugin_url . 'css/cris.css');
        if ($post && has_shortcode($post->post_content, 'cris')) {
            //wp_enqueue_style('cris', $plugin_url . 'css/cris.css');
            wp_enqueue_script('cris', $plugin_url . 'js/cris.js', array('jquery'));
        }
    }

    /*
     * WP-Cron
     */

    public static function cris_auto_sync() {
        include 'class_Sync.php';
        $sync = new Sync();
        $sync->do_sync();
    }

    public static function cris_cron() {
        $options = get_option('_fau_cris');
        if (isset($options['cris_sync_check']) && $options['cris_sync_check'] != 1) {
            if (wp_next_scheduled('cris_auto_update'))
                wp_clear_scheduled_hook('cris_auto_update');
            return;
        }
        if (!isset($options['cris_org_nr']) || $options['cris_org_nr'] == 0 || !isset($options['cris_sync_check']) || $options['cris_sync_check'] != 1) {
            return;
        }
        //Use wp_next_scheduled to check if the event is already scheduled*/
        if (!wp_next_scheduled('cris_auto_update')) {
            //Schedule the event for right now, then to repeat daily using the hook 'cris_create_cron'
            wp_schedule_event(strtotime('today 21:00'), 'daily', 'cris_auto_update');
            $timestamp = wp_next_scheduled('cris_auto_update');
            if ($timestamp) {
                $message = __('Einstellungen gespeichert', 'fau-cris')
                        . '<br />'
                        . __('Nächste automatische Synchronisierung:', 'fau-cris') . ' '
                        //. date ('d.m.Y - h:i', $timestamp)
                        . get_date_from_gmt(date('Y-m-d H:i:s', $timestamp), 'd.m.Y - H:i');
                add_settings_error('AutoSyncComplete', 'autosynccomplete', $message, 'updated');
                settings_errors();
            }
        }
    }

    /*
     * Hilfe-Panel über der Theme-Options-Seite
     */

    public static function cris_help_menu() {

        $content_cris = array(
            '<p>' . __('Binden Sie Daten aus aus dem FAU-Forschungsportal <strong>CRIS (Currrent Research Information System)</strong> in Ihren Webauftritt ein. Das Plugin ermöglicht außerdem die Integration mit dem FAU-Person-Plugin.', 'fau-cris') . '</p>',
            '<p>' . __('Aktuell werden folgende in CRIS erfasste Forschungsleistungen unterstützt:', 'fau-cris') . '</p>'
            . '<ul>'
            . '<li>' . __('Publikationen', 'fau-cris') . '</li>'
            . '<li>' . __('Auszeichnungen', 'fau-cris') . '</li>'
            . '</ul>'
            . '<p>' . __('Über den Shortcode lassen sich jeweils verschiedene Ausgabeformate einstellen.', 'fau-cris') . '</p>'
            . '<p>' . __('<b>CRIS-OrgNr</b>:<br>Die Nummer der der Organisationseinheit, für die die Publikationen und Personendaten ausgegeben werden. Diese erfahren Sie, wenn Sie in CRIS eingeloggt sind, oder wenn Sie ich Ihre Organisationseinheit auf http://cris.fau.de anzeigen lassen, in der URL: z.B. http://cris.fau.de/converis/publicweb/Organisation/<strong><em>141517</em></strong>.', 'fau-cris') . '</p>'
        );

        $content_shortcode_publikationen = array(
            '<h1>Shortcodes</h1>'
            . '<ul>'
            . '<li><code>[cris show="publications"]</code>: ' . __('Publikationsliste (automatisch nach Jahren gegliedert)') . '</li>'
            . '<li><code>[cris show="awards"]</code>: ' . __('Auszeichnungen (automatisch nach Jahren sortiert)') . '</li>'
            . '</ul>'
            . '<h2>' . __('Mögliche Zusatzoptionen', 'fau-cris') . '</h2>'
            . '<p>' . __('Ausgabe lässt sich beliebig anpassen. Eine Übersicht der verschiedenen Shortcode-Parameter zum Filtern, Sortieren und Ändern der Darstellung finden Sie unter: ') . '<a href="https://www.wordpress.rrze.fau.de/plugins/fau-cris/ target="_blank">https://www.wordpress.rrze.fau.de/plugins/fau-cris/</a>'
        );

        $content_fauperson = array(
            '<p>' . __('Wenn Sie das <strong>FAU-Person</strong>-Plugin verwenden, können Autoren mit ihrer FAU-Person-Kontaktseite verlinkt werden.', 'fau-cris') . '</p>',
            '<p>' . __('Wenn diese Option in den Einstellungen des CRIS-Plugins aktiviert ist, überprüft das Plugin selbstständig, welche Personen vorhanden sind und setzt die entsprechenden Links.', 'fau-cris') . '</p>',
            '<p>' . __('', 'fau-cris') . '</p>'
        );

        $helptexts = array(
            array(
                'id' => 'uebersicht',
                'title' => __('Übersicht', 'fau-cris'),
                'content' => implode(PHP_EOL, $content_cris),
            ),
            array(
                'id' => 'publikationen',
                'title' => __('Shortcodes', 'fau-cris'),
                'content' => implode(PHP_EOL, $content_shortcode_publikationen),
            ),
            array(
                'id' => 'person',
                'title' => __('Integration "FAU Person"', 'fau-cris'),
                'content' => implode(PHP_EOL, $content_fauperson),
            )
        );

        $screen = get_current_screen();
        if ($screen->id != self::$cris_option_page) {
            return;
        }
        foreach ($helptexts as $helptext) {
            $screen->add_help_tab($helptext);
        }
        //$screen->set_help_sidebar($help_sidebar);
    }

}
