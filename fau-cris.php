<?php
/**
 * Plugin Name: FAU CRIS
 * Description: Anzeige von Daten aus dem FAU-Forschungsportal CRIS in WP-Seiten
 * Version: 3.8.1
 * Author: RRZE-Webteam
 * Author URI: http://blogs.fau.de/webworking/
 * Text Domain: fau-cris
 * Domain Path: /languages
 * Requires at least: 3.9.2
 * Tested up to: 4.9.5
 * License: GPLv2 or later
 * GitHub Plugin URI: https://github.com/RRZE-Webteam/fau-cris
 * GitHub Branch: master
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
    const version = '3.8.1';
    const option_name = '_fau_cris';
    const version_option_name = '_fau_cris_version';
    const textdomain = 'fau-cris';
    const php_version = '5.3'; // Minimal erforderliche PHP-Version
    const wp_version = '3.9.2'; // Minimal erforderliche WordPress-Version
    const cris_publicweb = 'https://cris.fau.de/converis/portal/';
    const doi = 'https://dx.doi.org/';

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

        add_action('update_option_' . self::option_name, array(__CLASS__, 'cris_cron'), 10, 2 );
        add_action('cris_auto_update', array(__CLASS__, 'cris_auto_sync'));

        add_action('wp_head', array(__CLASS__, 'cris_customize_css'));

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
            'cris_pub_subtypes_order' => Tools::getOptionsOrder('publications', 'Other'),
            'cris_univis' => 'none',
            'cris_bibtex' => 0,
            'cris_url' => 0,
            'cris_doi' => 0,
            'cris_name_order_plugin' => 'firstname-lastname',
            'cris_award_order' => Tools::getOptionsOrder('awards'),
            'cris_award_link' => 'none',
            'cris_project_order' => Tools::getOptionsOrder('projects'),
            'cris_project_link' => 'none',
            'cris_patent_order' => Tools::getOptionsOrder('patents'),
            'cris_patent_link' => 'none',
            'cris_activities_order' =>  Tools::getOptionsOrder('activities'),
            'cris_activities_link' => 'none',
            'cris_sync_check' => 0,
            'cris_sync_research_custom' => 0,
            'cris_sync_field_custom' => 0,
            'cris_sync_shortcode_format' => array(
                'research' => 0,
                'fields' => 0,
                'projects' => 0,
            ),
            'cris_fields_num_pub' => 5
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
                <?php foreach( $tabs as $tab => $name ){
                    $class = ( $tab == $current ) ? ' nav-tab-active' : '';
                    echo "<a class='nav-tab$class' href='?page=options-fau-cris&tab=$tab'>$name</a>";
                } ?>
            </h2>
            <?php if (isset($result)) {
                print $result;
            } ?>
            <form method="post" action="options.php">
                <?php
                settings_fields('fau_cris_options');
                do_settings_sections('fau_cris_options');
                if (isset($current) && $current == 'sync'
                        && (isset($options['cris_sync_check']) && $options['cris_sync_check'] == 1)) {
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
        switch($tab) {
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
                        'cris_doi', __('DOI-Link', 'fau-cris'), array(__CLASS__, 'cris_check_callback'), 'fau_cris_options', 'cris_publications_section', array(
                    'name' => 'cris_doi',
                    'description' => __('Soll auch im APA- und MLA-Zitierstil (wenn vorhanden) für jede Publikation ein DOI-Link angezeigt werden?', 'fau-cris')
                        )
                );
                add_settings_field(
                        'cris_url', __('URL', 'fau-cris'), array(__CLASS__, 'cris_check_callback'), 'fau_cris_options', 'cris_publications_section', array(
                    'name' => 'cris_url',
                    'description' => __('Soll auch im APA- und MLA-Zitierstil (wenn vorhanden) ein Link zu einer Website angezeigt werden?', 'fau-cris')
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
                        'cris' => __('Autoren mit ihrer Profilseite auf cris.fau.de verlinken','fau-cris'),
                        'none' => __('keinen Link setzen', 'fau-cris'))
                    )
                );
                add_settings_field (
                    'cris_name_order_plugin',
                    __('Namen im FAU-Person-Plugin', 'fau-cris'),
                    array(__CLASS__, 'cris_select_callback'),
                    'fau_cris_options',
                    'cris_publications_section',
                    array(
                        'name' => 'cris_name_order_plugin',
                        'description' => __('In welcher Reihenfolge sind die Namen im FAU-Person-Plugin angelegt?', 'fau-cris'),
                        'options' => array(
                            'firstname-lastname' => __('Vorname Nachname', 'fau-cris'),
                            'lastname-firstname' => __('Nachname, Vorname','fau-cris'))
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
                        'cris' => __('Preisträger mit ihrer Profilseite auf cris.fau.de verlinken','fau-cris'),
                        'none' => __('keinen Link setzen', 'fau-cris'))
                    )
                );
                add_settings_section(
                        'cris_fields_section', // ID
                        __('Forschungsbereiche', 'fau-cris'), // Title
                        '__return_false', // Callback
                        'fau_cris_options' // Page
                );
                add_settings_field(
                        'cris_fields_num_pub', // ID
                        __('Anzahl Publikationen', 'fau-cris'), // Title
                        array(__CLASS__, 'cris_textbox_callback'), // Callback
                        'fau_cris_options', // Page
                        'cris_fields_section', // Section
                        array(
                            'name' => 'cris_fields_num_pub',
                            'description' => __('Maximale Anzahl der Publikationen, die in der Detailansicht eines Forschungsbereichs angezeigt werden.', 'fau-cris')
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
                        'cris' => __('Projektleiter und -beteiligte mit ihrer Profilseite auf cris.fau.de verlinken','fau-cris'),
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
                        'cris' => __('Patentinhaber mit ihrer Profilseite auf cris.fau.de verlinken','fau-cris'),
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
                        'cris' => __('Personen mit ihrer Profilseite auf cris.fau.de verlinken','fau-cris'),
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
                        'cris_sync_check',
                        __('Automatische Synchronisierung', 'fau-cris'),
                        array(__CLASS__, 'cris_check_callback'),
                        'fau_cris_options',
                        'cris_sync_section',
                        array(
                            'name' => 'cris_sync_check',
                            'description' => __('Sollen für neue Projekte und Forschungsbereiche automatisch Seiten und Menüeinträge generiert werden?', 'fau-cris')
                        )
                    );
                add_settings_field(
                        'cris_sync_shortcode_format',
                        __('Shortcode-Format', 'fau-cris'),
                        array(__CLASS__, 'cris_check_callback'),
                        'fau_cris_options',
                        'cris_sync_section',
                        array(
                            'name' => 'cris_sync_shortcode_format',
                            'description' => __('Soll für die Shortcodes auf den automatisch erstellten Seiten das konfigurierbare Format "[cris-custom]" verwendet werden?', 'fau-cris'),
                    'options' => array(
                        'research' => __('Custom-Shortcode für Seite Forschung', 'fau-cris'),
                        'fields' => __('Custom-Shortcode für Forschungsbereiche','fau-cris'),
                        'projects' => __('Custom-Shortcode für Forschungsprojekte', 'fau-cris'))
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
                $new_input['cris_url'] = isset($_POST[self::option_name]['cris_url']) ? 1 : 0;
                $new_input['cris_doi'] = isset($_POST[self::option_name]['cris_doi']) ? 1 : 0;
                $new_input['cris_name_order_plugin'] = (isset($_POST[self::option_name]['cris_name_order_plugin'])
                        && $_POST[self::option_name]['cris_name_order_plugin'] == 'lastname-firstname') ? 'lastname-firstname' : 'firstname-lastname';
                $new_input['cris_award_order'] = isset($_POST[self::option_name]['cris_award_order']) ? explode("\n", str_replace("\r", "", $_POST[self::option_name]['cris_award_order'])) : $default_options['cris_award_order'];
                $new_input['cris_award_link'] = in_array($_POST[self::option_name]['cris_award_link'], array('person', 'cris', 'none')) ? $_POST[self::option_name]['cris_award_link'] : $default_options['cris_award_link'];
                $new_input['cris_fields_num_pub'] = isset($_POST[self::option_name]['cris_fields_num_pub']) ? sanitize_text_field($_POST[self::option_name]['cris_fields_num_pub']) : 0;
                $new_input['cris_project_order'] = isset($_POST[self::option_name]['cris_project_order']) ? explode("\n", str_replace("\r", "", $_POST[self::option_name]['cris_project_order'])) : $default_options['cris_project_order'];
                $new_input['cris_project_link'] = in_array($_POST[self::option_name]['cris_project_link'], array('person', 'cris', 'none')) ? $_POST[self::option_name]['cris_project_link'] : $default_options['cris_project_link'];
                $new_input['cris_patent_order'] = isset($_POST[self::option_name]['cris_patent_order']) ? explode("\n", str_replace("\r", "", $_POST[self::option_name]['cris_patent_order'])) : $default_options['cris_patent_order'];
                $new_input['cris_patent_link'] = in_array($_POST[self::option_name]['cris_patent_link'], array('person', 'cris', 'none')) ? $_POST[self::option_name]['cris_patent_link'] : $default_options['cris_patent_link'];
                $new_input['cris_activities_order'] = isset($_POST[self::option_name]['cris_activities_order']) ? explode("\n", str_replace("\r", "", $_POST[self::option_name]['cris_activities_order'])) : $default_options['cris_activities_order'];
                $new_input['cris_activities_link'] = in_array($_POST[self::option_name]['cris_activities_link'], array('person', 'cris', 'none')) ? $_POST[self::option_name]['cris_activities_link'] : $default_options['cris_activities_link'];
                break;
            case 'sync':
                $new_input['cris_sync_check'] = isset($_POST[self::option_name]['cris_sync_check']) ? 1 : 0;
                if(is_array($_POST[self::option_name]['cris_sync_shortcode_format'])) {
                    /*foreach ($_POST[self::option_name]['cris_sync_shortcode_format'] as $_check){
                        foreach ($_check as $_k => $_v) {
                            $new_input['cris_sync_shortcode_format'][$_k] = $_v;
                        }
                    }*/
                    $new_input['cris_sync_shortcode_format'] = $_POST[self::option_name]['cris_sync_shortcode_format'];
                }
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
        if (array_key_exists('options', $args)) {
            $checks = $args['options'];
            foreach ($checks as $_k => $_v) { ?>
                <label>
                    <input name="<?php printf('%s[' . $name . '][' . $_k . ']', self::option_name); ?>"
                        type='checkbox'
                        value='1'
                        <?php
                        if (array_key_exists($name, $options)) {
                            print checked($options[$name][ $_k], 1, false);
                        }
                        ?>
                    >
                    <?php print $_v; ?>
                </label><br />
            <?php }
        } else { ?>
            <label><input name="<?php printf('%s[' . $name . ']', self::option_name); ?>" type='checkbox' value='1'         <?php
            if (array_key_exists($name, $options)) {
                print checked($options[$name], 1, false);
            }
            ?> >
            <?php if (isset($description)) { ?>
                <span class="description"><?php echo $description; ?></span></label>
            <?php }
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
        foreach ($radios as $_k => $_v) { ?>
            <label>
                <input name="<?php printf('%s[' . $name . ']', self::option_name); ?>"
                   type='radio'
                   value='<?php print $_k; ?>'
                   <?php
                    if (array_key_exists($name, $options)) {
                       checked($options[$name], $_k);
                    } ?>
                >
                <?php print $_v; ?>
            </label><br />
        <?php }

        if (isset($description)) { ?>
            <p class="description"><?php echo $description; ?></p>
        <?php }
    }

    //Select
    public static function cris_select_callback($args){
        $options = self::get_options();
        if (array_key_exists('name', $args))
            $name = esc_attr($args['name']);
        if (array_key_exists('description', $args))
            $description = esc_attr($args['description']);
        if (array_key_exists('options', $args))
            $limit = $args['options']; ?>
        <select name="<?php printf('%s[' . $name . ']', self::option_name); ?>">
        <?php foreach ($limit as $_k => $_v) { ?>
            <option value='<?php print $_k; ?>'
                <?php if (array_key_exists($name, $options)) { selected($options[$name], $_k); } ?>>
                    <?php print $_v; ?>
            </option>
        <?php } ?>
        </select>
        <?php
        if (isset($description)) { ?>
            <p class="description"><?php echo $description; ?></p>
        <?php }
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
                if (is_array($options[$name]) && count($options[$name])>0 && $options[$name][0] !='') {
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
    public static function cris_shortcode($atts, $content = null, $tag) {
        $parameter = self::cris_shortcode_parameter($atts, $content = null, $tag);

        if (isset($parameter['show']) && $parameter['show'] == 'organisation') {
            // Forschung
            require_once('class_Organisation.php');
            $liste = new Organisation($parameter['entity'], $parameter['entity_id']);
            return $liste->singleOrganisation($parameter['hide']);
        } elseif (isset($parameter['show']) && $parameter['show'] == 'fields') {
            // Forschungsbereiche
            require_once('class_Forschungsbereiche.php');
            $liste = new Forschungsbereiche($parameter['entity'], $parameter['entity_id']);

            if ($parameter['field'] != '') {
                return $liste->singleField($parameter);
            }
            if (!empty($parameter['limit'])) {
                return $liste->fieldListe($parameter);
            }
            return $liste->fieldListe($parameter);
        } elseif (isset($parameter['show']) && $parameter['show'] == 'activities') {
            // Aktivitäten
            require_once('class_Aktivitaeten.php');
            $liste = new Aktivitaeten($parameter['entity'], $parameter['entity_id']);

            if ($parameter['activity'] != '') {
                return $liste->singleActivity($parameter['hide']);
            }
            if ($parameter['limit'] != '' ) {
                return $liste->actiListe($parameter);
            }
            if (strpos($parameter['order1'], 'type') !== false) {
                return $liste->actiNachTyp($parameter);
            }
            if (strpos($parameter['order1'], 'year') !== false) {
                return $liste->actiNachJahr($parameter);
            }
            return $liste->actiListe($parameter);
        } elseif (isset($parameter['show']) && $parameter['show'] == 'patents') {
            // Patente
            require_once('class_Patente.php');
            $liste = new Patente($parameter['entity'], $parameter['entity_id']);

            if ($parameter['patent'] != '') {
                return $liste->singlePatent($parameter['hide']);
            }
            if (!empty($parameter['limit'])) {
                return $liste->patListe($parameter);
            }
            if (strpos($parameter['order1'], 'type') !== false) {
                return $liste->patNachTyp($parameter);
            }
            if (strpos($parameter['order1'], 'year') !== false) {
                return $liste->patNachJahr($parameter);
            }
            return $liste->patListe($parameter);
        } elseif (isset($parameter['show']) && $parameter['show'] == 'projects') {
            // Projekte
            require_once('class_Projekte.php');
            $liste = new Projekte($parameter['entity'], $parameter['entity_id']);

            if ($parameter['project'] != '') {
                return $liste->singleProj($parameter['hide'], $parameter['quotation']);
            }
            if (!empty($parameter['limit'])) {
                return $liste->projListe($parameter);
            }
            if (strpos($parameter['order1'], 'type') !== false) {
                return $liste->projNachTyp($parameter, '');
            }
            if (strpos($parameter['order1'], 'year') !== false) {
                return $liste->projNachJahr($parameter, '');
            }
            if (strpos($parameter['order1'], 'role') !== false) {
                return $liste->projNachRolle($parameter, '');
            }
            return $liste->projListe($parameter);
        } elseif (isset($parameter['show']) && $parameter['show'] == 'awards') {
            // Awards
            require_once('class_Auszeichnungen.php');
            $liste = new Auszeichnungen($parameter['entity'], $parameter['entity_id'], $parameter['display']);

            if ($parameter['award'] != '') {
                return $liste->singleAward($parameter['showname'], $parameter['showyear'], $parameter['showawardname'], $parameter['display']);
            }
            if (strpos($parameter['order1'], 'type') !== false) {
                return $liste->awardsNachTyp($parameter);
            }
            if (strpos($parameter['order1'], 'year') !== false) {
                return $liste->awardsNachJahr($parameter);
            }
            return $liste->awardsListe($parameter);
        } else {
            // Publications
            require_once('class_Publikationen.php');
            $liste = new Publikationen($parameter['entity'], $parameter['entity_id'], $parameter['name_order_plugin']);

            if ($parameter['publication'] != '' && $parameter['order1'] == '') {
                return $liste->singlePub($parameter['quotation']);
            }
            if ($parameter['order1'] == '' && ($parameter['limit'] != '' || $parameter['sortby'] != '' || $parameter['notable'] != '')) {
                return $liste->pubListe($parameter);
            }
            if (strpos($parameter['order1'], 'type') !== false) {
                return $liste->pubNachTyp($parameter, $field = '');
            }
            return $liste->pubNachJahr($parameter, $field = '');
        }

        // nothing
        return '';
    }

    public static function cris_custom_shortcode($atts, $content = null, $tag) {
        $parameter = self::cris_shortcode_parameter($atts, $content, $tag);

        if ($parameter['show'] == 'organisation') {
        // Forschung
            require_once('class_Organisation.php');
            $liste = new Organisation($parameter['entity'], $parameter['entity_id']);
            return $liste->customOrganisation($content);
        } elseif ($parameter['show'] == 'fields') {
        // Forschungsbereiche
            require_once('class_Forschungsbereiche.php');
            $liste = new Forschungsbereiche($parameter['entity'], $parameter['entity_id']);
            if ($parameter['field'] != '') {
                return $liste->customField($content, $parameter);
            }
        } elseif (isset($parameter['show']) && $parameter['show'] == 'projects') {
        // Projekte
            require_once('class_Projekte.php');
            $liste = new Projekte($parameter['entity'], $parameter['entity_id']);
            if ($parameter['project'] != '') {
                return $liste->customProj($content, $parameter['quotation']);
            }
            /*if (!empty($parameter['limit'])) {
                return $liste->projListe($parameter['year'], $parameter['start'], $parameter['type'], $parameter['limit'], $parameter['hide'], $parameter['role']);
            }
            if (strpos($parameter['order1'], 'type') !== false) {
                return $liste->projNachTyp($parameter['year'], $parameter['start'], $parameter['type'], $parameter['hide'] = array(), $parameter['role'], $content);
            }
            if (strpos($parameter['order1'], 'year') !== false) {
                return $liste->projNachJahr($parameter['year'], $parameter['start'], $parameter['type'], $parameter['hide'] = array(), $parameter['role'], $content);
            }
            return $liste->projListe($parameter['year'], $parameter['start'], $parameter['type'], $parameter['limit'], $parameter['hide'], $parameter['role']);*/
        } elseif ($parameter['show'] == 'publications') {
        // Publikationen
            require_once('class_Publikationen.php');
            $liste = new Publikationen($parameter['entity'], $parameter['entity_id']);
            if ($parameter['publication'] != '' && $parameter['order1'] == '') {
                return $liste->singlePub($parameter['quotation'], $content, $parameter['sc_type']);
            }
            if ($parameter['order1'] == '' && ($parameter['limit'] != '' || $parameter['sortby'] != '' || $parameter['notable'] != '')) {
                return $liste->pubListe($parameter, $content);
            }
            if (strpos($parameter['order1'], 'type') !== false) {
                return $liste->pubNachTyp($parameter, $field = '', $content);
            }
            return $liste->pubNachJahr($parameter, $field = '', $content);
        }
    }


    private static function cris_shortcode_parameter($atts, $content = '', $tag) {
        $options = self::get_options();

        // Attributes
        extract(shortcode_atts(
                        array(
            'show' => 'publications',
            'orderby' => '',
            'year' => '',
            'start' => '',
            'end' => '',
            'orgid' => isset($options['cris_org_nr']) ? $options['cris_org_nr'] : '',
            'persid' => '',
            'publication' => '',
            'pubtype' => '',
            'quotation' => '',
            'items' => '',
            'limit' => '',
            'sortby' => '',
            'format' => '',
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
            'role' => 'all',
            'patent' => '',
            'activity' => '',
            'field' => '',
            'fau' => '',
            'peerreviewed' => '',
            'current' => '',
            'publications_limit' => (isset($options['cris_fields_num_pub']) && !empty($options['cris_fields_num_pub'])) ? $options['cris_fields_num_pub'] : '5',
            'name_order_plugin' => (isset($options['cris_name_order_plugin']) && !empty($options['cris_name_order_plugin'])) ? $options['cris_name_order_plugin'] : 'firstname-lastname',
            'notable' => '',
            'publications_year' => '',
            'publications_start' => '',
            'publications_type' => '',
            'publications_subtype' => '',
            'publications_fau' => '',
            'publications_peerreviewed' => '',
            'publications_orderby' => '',
            'publications_notable' => ''
                        ), $atts));

        $sc_param['orderby'] = sanitize_text_field($orderby);
        $sc_param['orgid'] = sanitize_text_field($orgid);
        $sc_param['persid'] = sanitize_text_field($persid);
        $sc_param['publication'] = sanitize_text_field($publication);
        $sc_param['award'] = sanitize_text_field($award);
        $sc_param['awardnameid'] = sanitize_text_field($awardnameid);
        $sc_param['project'] = sanitize_text_field($project);
        $sc_param['patent'] = sanitize_text_field($patent);
        $sc_param['activity'] = sanitize_text_field($activity);
        $sc_param['field'] = sanitize_text_field($field);
        $sc_param['show'] = sanitize_text_field($show);
        if ($type == 'weitere')
            $type = 'andere';
        $sc_param['type'] = (!empty($pubtype)) ? sanitize_text_field($pubtype) : sanitize_text_field($type); //Abwärtskompatibilität
        $sc_param['subtype'] = sanitize_text_field($subtype);
        $sc_param['year'] = sanitize_text_field($year);
        $sc_param['start'] = sanitize_text_field($start);
        $sc_param['end'] = sanitize_text_field($end);
        $sc_param['quotation'] = sanitize_text_field($quotation);
        $limit = ($limit != '' ? $limit : $items);
        $sc_param['limit'] = sanitize_text_field($limit);
        $sc_param['sortby'] = (in_array($sortby, array('created', 'updated'))) ? sanitize_text_field($sortby) : '';
        $sc_param['format'] = sanitize_text_field($format);
        $sc_param['showname'] = sanitize_text_field($showname);
        $sc_param['showyear'] = sanitize_text_field($showyear);
        $sc_param['showawardname'] = sanitize_text_field($showawardname);
        $sc_param['display'] = sanitize_text_field($display);
        $sc_param['role'] = sanitize_text_field($role);
        $sc_param['hide'] = sanitize_text_field($hide);
        $sc_param['fau'] = sanitize_text_field($fau);
        $sc_param['peerreviewed'] = sanitize_text_field($peerreviewed);
        $sc_param['current'] = sanitize_text_field($current);
        $sc_param['name_order_plugin'] = sanitize_text_field($name_order_plugin);
        $sc_param['notable'] = $notable == 1 ? 1 : 0;
        $sc_param['publications_limit'] = sanitize_text_field($publications_limit);
        $sc_param['publications_year'] = sanitize_text_field($publications_year);
        $sc_param['publications_start'] = sanitize_text_field($publications_start);
        $sc_param['publications_type'] = sanitize_text_field($publications_type);
        $sc_param['publications_subtype'] = sanitize_text_field($publications_subtype);
        $sc_param['publications_fau'] = sanitize_text_field($publications_fau);
        $sc_param['publications_peerreviewed'] = sanitize_text_field($publications_peerreviewed);
        $sc_param['publications_orderby'] = sanitize_text_field($publications_orderby);
        $sc_param['publications_notable'] = $publications_notable == 1 ? 1 : 0;

        if ($sc_param['publication'] != '') {
            $sc_param['entity'] = 'publication';
            if (strpos($sc_param['publication'], ',')) {
                $sc_param['publication'] = str_replace(' ', '', $sc_param['publication']);
                $sc_param['publication'] = explode(',', $sc_param['publication']);
            }
            $sc_param['entity_id'] = $sc_param['publication'];
        } elseif ($sc_param['field'] != '') {
            $sc_param['entity'] = 'field';
            $sc_param['entity_id'] = $sc_param['field'];
        } elseif (isset($sc_param['activity']) && $sc_param['activity'] != '') {
            $sc_param['entity'] = 'activity';
            $sc_param['entity_id'] = $sc_param['activity'];
        } elseif (isset($sc_param['patent']) && $sc_param['patent'] != '') {
            $sc_param['entity'] = 'patent';
            $sc_param['entity_id'] = $sc_param['patent'];
        } elseif (isset($sc_param['award']) && $sc_param['award'] != '') {
            $sc_param['entity'] = 'award';
            $sc_param['entity_id'] = $sc_param['award'];
        } elseif (isset($sc_param['project']) && $sc_param['project'] != '') {
            $sc_param['entity'] = 'project';
            if (strpos($sc_param['project'], ',') !== false) {
                $sc_param['project'] = str_replace(' ', '', $sc_param['project']);
                $sc_param['project'] = explode(',', $sc_param['project']);
            }
            $sc_param['entity_id'] = $sc_param['project'];
        } elseif (isset($sc_param['awardnameid']) && $sc_param['awardnameid'] != '') {
            $sc_param['entity'] = 'awardnameid';
            $sc_param['entity_id'] = $sc_param['awardnameid'];
        } elseif (isset($sc_param['persid']) && $sc_param['persid'] != '') {
            $sc_param['entity'] = 'person';
            if (strpos($sc_param['persid'], ',') !== false) {
                $sc_param['persid'] = str_replace(' ', '', $sc_param['persid']);
                $sc_param['persid'] = explode(',', $sc_param['persid']);
            }
            $sc_param['entity_id'] = $sc_param['persid'];
        } elseif (isset($sc_param['orgid']) && $sc_param['orgid'] != '') {
            $sc_param['entity'] = 'orga';
            if (strpos($sc_param['orgid'], ',') !== false) {
                $sc_param['orgid'] = str_replace(' ', '', $sc_param['orgid']);
                $sc_param['orgid'] = explode(',', $sc_param['orgid']);
            }
            $sc_param['entity_id'] = $sc_param['orgid'];
        } else {
            $sc_param['entity'] = '';
            $sc_param['entity_id'] = '';
        }

        $sc_param['order1'] = '';
        $sc_param['order2'] = '';

        if (!empty($orderby)) {
            if (strpos($orderby, ',') !== false) {
                $orderby = str_replace(' ', '', $orderby);
                $sc_param['order1'] = explode(',', $orderby)[0];
                $sc_param['order2'] = explode(',', $orderby)[1];
            } else {
                $sc_param['order1'] = $orderby;
                $sc_param['order2'] = '';
            }
        }
        if ($tag == 'cris-custom') {
            $sc_param['sc_type'] = 'custom';
        } else {
            $sc_param['sc_type'] = 'default';
        }

        return $sc_param;
    }

    public static function cris_enqueue_styles() {
        global $post;
        $plugin_url = plugin_dir_url(__FILE__);
        if ($post && has_shortcode($post->post_content, 'cris')
                || $post && has_shortcode($post->post_content, 'cris-custom')) {
            wp_enqueue_style('cris', plugins_url('css/cris.css', __FILE__), array(), self::version);
            wp_enqueue_script('cris', plugins_url('js/cris.js', __FILE__), array('jquery'), self::version);
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
        if (isset($options['cris_sync_check'])
                && $options['cris_sync_check'] != 1) {
            if (wp_next_scheduled( 'cris_auto_update' ))
                wp_clear_scheduled_hook('cris_auto_update');
            return;
        }
        if (!isset($options['cris_org_nr'])
                || $options['cris_org_nr'] == 0
                || !isset($options['cris_sync_check'])
                || $options['cris_sync_check'] != 1) {
            return;
        }
        //Use wp_next_scheduled to check if the event is already scheduled*/
        if( !wp_next_scheduled( 'cris_auto_update' )) {
            //Schedule the event for right now, then to repeat daily using the hook 'cris_create_cron'
            wp_schedule_event( strtotime('today 21:00'), 'daily', 'cris_auto_update' );
            $timestamp = wp_next_scheduled( 'cris_auto_update' );
            if ($timestamp) {
                $message = __('Einstellungen gespeichert', 'fau-cris')
                        . '<br />'
                        . __('Nächste automatische Synchronisierung:', 'fau-cris') . ' '
                        //. date ('d.m.Y - h:i', $timestamp)
                        . get_date_from_gmt( date( 'Y-m-d H:i:s', $timestamp ), 'd.m.Y - H:i' );
                add_settings_error('AutoSyncComplete', 'autosynccomplete', $message , 'updated' );
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
            . '<p>' . __('<b>CRIS-OrgNr</b>:<br>Die Nummer der der Organisationseinheit, für die die Publikationen und Personendaten ausgegeben werden. Diese erfahren Sie, wenn Sie in CRIS eingeloggt sind, oder wenn Sie ich Ihre Organisationseinheit auf http://cris.fau.de anzeigen lassen, in der URL: z.B. ' . FAU_CRIS::cris_publicweb . 'Organisation/<strong><em>141517</em></strong>.', 'fau-cris') . '</p>'
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

    public static function cris_customize_css() {

        $css_content = 'Forschungsbereiche';
        if (strpos(get_locale(), 'de') === 0) {
            $css_content = 'Forschungsbereiche';
        } else {
            $css_content = 'Research Areas';
        }
        ?>
        <style type="text/css">
            #nav .level2 li.cris-last {
                border-bottom: 1px solid #aaa;
                padding-bottom: 10px;
                margin-bottom: 10px;
            }

            #nav .level2 li.cris-first {
                border-top: 1px solid #aaa;
                padding-top: 10px;
            }

            #nav .level2 li.cris-first:before {
                content: '<?php echo $css_content; ?>';
                display:block;
                color: #fff;
                font-weight: bold;
                margin-bottom: 5px;
            }

            #nav .level2 li.cris-last a {
                border-bottom-color: transparent;
            }
        </style>
        <?php
    }

}
