<?php
namespace RRZE\Cris;
defined('ABSPATH') || exit;

use RRZE\Cris\Tools;
use RRZE\Cris\Standardisierungen;
use RRZE\Cris\Organisation;
use RRZE\Cris\Equipment;
use RRZE\Cris\Forschungsbereiche;
use RRZE\Cris\Projekte;
use RRZE\Cris\Auszeichnungen;
use RRZE\Cris\Publikationen;
use RRZE\Cris\Aktivitaeten;
use RRZE\Cris\Patente;
use RRZE\Cris\Sync;



/**
 * Plugin Name: FAU CRIS
 * Description: Anzeige von Daten aus dem FAU-Forschungsportal CRIS in WP-Seiten
 * Version: 3.26.7
 * Author: RRZE-Webteam
 * Author URI: http://blogs.fau.de/webworking/
 * Text Domain: fau-cris
 * Domain Path: /languages
 * Requires at least: 6.2
 * Tested up to: 6.3
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

/**
 * SPL Autoloader (PSR-4).
 * @param string $class The fully-qualified class name.
 * @return void
 */
spl_autoload_register(function ($class) {
    $prefix = 'RRZE\Cris';
    $baseDir = __DIR__ . '/includes/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});



add_action('plugins_loaded', array('RRZE\Cris\FAU_CRIS', 'instance'));

register_activation_hook(__FILE__, array('RRZE\Cris\FAU_CRIS', 'activate'));
register_deactivation_hook(__FILE__, array('RRZE\Cris\FAU_CRIS', 'deactivate'));

class FAU_CRIS
{
    /**
     * Get Started
     */
    const version = '3.26.7';
    const option_name = '_fau_cris';
    const version_option_name = '_fau_cris_version';
    const textdomain = 'fau-cris';
    const php_version = '7.1'; // Minimal erforderliche PHP-Version
    const wp_version = '3.9.2'; // Minimal erforderliche WordPress-Version
    const cris_publicweb = 'https://cris.fau.de/';
    const doi = 'https://doi.org/';

    protected static $instance = null;
    private static $cris_option_page = null;

    public static function instance(): ?FAU_CRIS
    {

        if (null == self::$instance) {
            self::$instance = new self;
        }

        return self::$instance;
    }

    private function __construct()
    {
        load_plugin_textdomain('fau-cris', false, dirname(plugin_basename(__FILE__)) . '/languages');

        add_action('admin_init', array(__CLASS__, 'admin_init'));
        add_action('admin_menu', array(__CLASS__, 'add_options_page'));
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), array(__CLASS__, 'add_action_links'));

        add_action('wp_enqueue_scripts', array(__CLASS__, 'cris_enqueue_styles'));

        add_shortcode('cris', array(__CLASS__, 'cris_shortcode'));
        add_shortcode('cris-custom', array(__CLASS__, 'cris_custom_shortcode'));

        add_action('update_option_' . self::option_name, array(__CLASS__, 'cris_cron'), 10, 2);
        add_action('cris_auto_update', array(__CLASS__, 'cris_auto_sync'));
    }

    /**
     * Check PHP and WP Version
     */
    public static function activate(): void
    {
        self::version_compare();
        update_option(self::version_option_name, self::version);
    }

    public static function deactivate(): void
    {
        wp_clear_scheduled_hook('cris_auto_update');
    }


    private static function version_compare(): void
    {
        $error = '';       
            if (version_compare(PHP_VERSION, self::php_version, '<')) {

                $error = sprintf(
                    /* translators: 1: current PHP version, 2: required PHP version */
                    __('Ihre PHP-Version %1$s ist veraltet. Bitte aktualisieren Sie mindestens auf die PHP-Version %2$s.', 'fau-cris'),
                    PHP_VERSION,
                    self::php_version
                );
            }



        if (version_compare($GLOBALS['wp_version'], self::wp_version, '<')) {
            $error = sprintf(
                     /* translators: 1: current WordPress version, 2: required WordPress version */
                __('Ihre Wordpress-Version %1$s ist veraltet. Bitte aktualisieren Sie mindestens auf die Wordpress-Version %2$s.', 'fau-cris'), $GLOBALS['wp_version'], self::wp_version);
        }

        if (!empty($error)) {
            deactivate_plugins(plugin_basename(__FILE__), false, true);
            wp_die(
                esc_html($error),
                esc_html(__('Fehler bei der Aktivierung des Plugins', 'fau-cris')),
                array(
                'response' => 500,
                'back_link' => true
                    )
            );
        }
    }

    public static function update_version(): void
    {
        if (get_option(self::version_option_name, null) != self::version) {
            update_option(self::version_option_name, self::version);
        }
    }

    /**
     * Display settings link on the plugins page (beside the activate/deactivate links)
     */
    public static function add_action_links($links): array
    {
        $mylinks = array(
            '<a href="' . admin_url('options-general.php?page=options-fau-cris') . '">' . __('Einstellungen', 'fau-cris') . '</a>',
        );
        return array_merge($links, $mylinks);
    }

    /**
     * Get Options
     */
    public static function get_options(): array
    {
        $defaults = self::default_options();
        $options = (array) get_option(self::option_name);
        $options = wp_parse_args($options, $defaults);
        $options = array_intersect_key($options, $defaults);
        return $options;
    }

    /**
     * Set default options
     */
    private static function default_options(): array
    {
        $options = array(
            'cris_org_nr' => '',
            'cris_cache' => '18000',
            'cris_pub_order' => Tools::getOptionsOrder('publications'),
            'cris_pub_subtypes_order' => Tools::getOptionsOrder('publications', 'Other'),
            'cris_univis' => 'none',
            'cris_bibtex' => 0,
            'cris_url' => 0,
            'cris_doi' => 0,
            'cris_oa' => 1,
            'cris_name_order_plugin' => 'firstname-lastname',
            'cris_award_order' => Tools::getOptionsOrder('awards'),
            'cris_award_link' => 'none',
            'cris_project_order' => Tools::getOptionsOrder('projects'),
            'cris_project_link' => 'none',
            'cris_patent_order' => Tools::getOptionsOrder('patents'),
            'cris_patent_link' => 'none',
            'cris_activities_order' =>  Tools::getOptionsOrder('activities'),
            'cris_activities_link' => 'none',
            'cris_standardizations_order' =>  Tools::getOptionsOrder('standardizations'),
            'cris_standardizations_link' => 'none',
            'cris_sync_check' => 0,
            'cris_sync_research_custom' => 0,
            'cris_sync_field_custom' => 0,
            'cris_sync_shortcode_format' => array(
                'research' => 0,
                'fields' => 0,
                'projects' => 0,
            ),
            'cris_fields_num_pub' => 5,
            'cris_project_num_pub'=>5,
            'cris_field_link' => 'none',
            'cris_pub_title_link_order'=>Dicts::$publicationTitleLinksOptions
        );
        return $options;
    }

    /**
     * Add options page
     */
    public static function add_options_page(): void
    {
        self::$cris_option_page = add_options_page(
            'CRIS: Einstellungen',
            'CRIS',
            'manage_options',
            'options-fau-cris',
            array(__CLASS__, 'options_fau_cris')
        );
        add_action('load-' . self::$cris_option_page, array(__CLASS__, 'cris_help_menu'));
    }

    /*
     * Options page tabs
     */
    private static function options_page_tabs(): array
    {
        $tabs = array(
            'general' => __('Allgemein', 'fau-cris'),
            'layout' => __('Darstellung', 'fau-cris'),
            'sync' => __('Synchronisierung', 'fau-cris')
        );
        return $tabs;
    }
    private static function current_tab($tab)
    {
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
public static function options_fau_cris(): void
    {
        $tabs = self::options_page_tabs();
    $current = 'general'; // Default tab

    // Get current tab from URL with proper nonce verification
    if (isset($_GET['tab']) && in_array($_GET['tab'], array_keys($tabs))) {
        // Verify nonce for tab switching
        if (isset($_GET['_wpnonce_tab']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce_tab'])), 'fau_cris_tab_nonce')) {
            $current = sanitize_text_field(wp_unslash($_GET['tab']));
        }
    }

    $result = null;
    
    // Verify nonce and process action if valid
    if (isset($_GET['action']) && $_GET['action'] === 'cris_sync') {
        $nonce = isset($_GET['_wpnonce_tab']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce_tab'])) : '';
        
        if (wp_verify_nonce($nonce, 'fau_cris_tab_nonce')) {
            // Process sync action
            global $post;
            $page_lang = substr(get_locale(), 0, 2);
            $sync = new Sync($page_lang);
            $result = $sync->do_sync(true);
        } else {
            wp_die(__('Security check failed for sync action!', 'fau-cris'));
        }
    }

    $options = self::get_options();
    ?>

        <div class="wrap">
            <h2><?php esc_html_e('Einstellungen', 'fau-cris'); ?> &rsaquo; CRIS</h2>
            
            <?php 
            // Display errors if any
        if (!empty($error->errors)) {
            foreach ($error->get_error_messages() as $message) {
                echo '<div class="notice notice-error"><p>' . esc_html($message) . '</p></div>';
            }
        }
                if (isset($result)) {
                    echo wp_kses_post($result);} ?>

            <h2 class="nav-tab-wrapper">
            <?php foreach ($tabs as $tab => $name) {
                $class = ($tab == $current) ? ' nav-tab-active' : '';
                $url = add_query_arg([
                    'page' => 'options-fau-cris',
                    'tab' => $tab,
                    '_wpnonce_tab' => wp_create_nonce('fau_cris_tab_nonce')
                ], admin_url('options-general.php'));
                echo '<a class="nav-tab' . esc_attr($class) . '" href="' . esc_url($url) . '">' . esc_html($name) . '</a>';
            } ?>
        </h2>

            <form method="post" action="options.php">
                <?php
                // Add the nonce field
                wp_nonce_field('fau_cris_form_nonce', '_wpnonce_form');
                settings_fields('fau_cris_options');
                do_settings_sections('fau_cris_options');
                if (isset($current) && $current == 'sync' && (isset($options['cris_sync_check']) && $options['cris_sync_check'] == 1)) {
    $sync_url = add_query_arg([
        'page' => 'options-fau-cris',
        'tab' => 'sync',
        'action' => 'cris_sync',
        '_wpnonce_tab' => wp_create_nonce('fau_cris_tab_nonce')
    ], admin_url('options-general.php'));
    
    echo '<a href="' . esc_url($sync_url) . '" name="sync-now" id="sync-now" class="button button-secondary" style="margin-bottom: 10px;">
        <span class="dashicons dashicons-image-rotate" style="margin: 3px 5px 0 0;"></span>' .
        esc_html(__('Jetzt synchronisieren', 'fau-cris')) . '</a>';
}
                    // echo '<a href="' . esc_url( '?page=options-fau-cris&tab=sync&action=cris_sync' ) . '" name="sync-now" id="sync-now" class="button button-secondary" style="margin-bottom: 10px;"> <span class="dashicons dashicons-image-rotate" style="margin: 3px 5px 0 0;"></span>' . esc_html( __('Jetzt synchronisieren', 'fau-cris') ) . '</a>'; }
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Register and add settings
     */
    public static function admin_init(): void
    {

        register_setting(
            'fau_cris_options', // Option group
            self::option_name, // Option name
            array(__CLASS__, 'sanitize') // Sanitize Callback
        );
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if (isset($_GET)) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $tab = self::current_tab($_GET);
        }
        switch ($tab) {
            case 'general':
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
            case 'layout':
                add_settings_section(
                    'cris_publications_section', // ID
                    __('Publikationen', 'fau-cris'), // Title
                    '__return_false', // Callback
                    'fau_cris_options' // Page
                );
                add_settings_field(
                    'cris_pub_order',
                    __('Reihenfolge der Publikationen', 'fau-cris'),
                    array(__CLASS__, 'cris_textarea_callback'),
                    'fau_cris_options',
                    'cris_publications_section',
                    array(
                    'name' => 'cris_pub_order',
                    'description' => __('Wenn Sie die Publikationsliste nach Publikationstypen geordnet ausgeben, können Sie hier angeben, in welcher Reihenfolge die Typen aufgelistet werden. Eine Liste aller Typen finden Sie im Hilfemenü unter "Shortcode Publikationen". Ein Eintrag pro Zeile. ', 'fau-cris')
                        )
                );
                add_settings_field(
                    'cris_pub_subtypes_order',
                    __('Reihenfolge der Publikationen-Subtypen unter "Andere"', 'fau-cris'),
                    array(__CLASS__, 'cris_textarea_callback'),
                    'fau_cris_options',
                    'cris_publications_section',
                    array(
                    'name' => 'cris_pub_subtypes_order',
                    //'description' => __('Wenn Sie die Publikationsliste nach Publikationstypen geordnet ausgeben, können Sie hier angeben, in welcher Reihenfolge die Typen aufgelistet werden. Eine Liste aller Typen finden Sie im Hilfemenü unter "Shortcode Publikationen". Ein Eintrag pro Zeile. ', 'fau-cris')
                        )
                );

                add_settings_field(
                    'cris_pub_title_link_order',
                    __('Priorisierung des Titel-Links bei Publikationen', 'fau-cris'),
                    array(__CLASS__, 'cris_textarea_callback'),
                    'fau_cris_options',
                    'cris_publications_section',
                    array(
                    'name' => 'cris_pub_title_link_order'
                        )
                );


                add_settings_field(
                    'cris_doi',
                    __('DOI-Link', 'fau-cris'),
                    array(__CLASS__, 'cris_check_callback'),
                    'fau_cris_options',
                    'cris_publications_section',
                    array(
                    'name' => 'cris_doi',
                    'description' => __('Soll auch im APA- und MLA-Zitierstil (wenn vorhanden) für jede Publikation ein DOI-Link angezeigt werden?', 'fau-cris')
                        )
                );
                add_settings_field(
                    'cris_url',
                    __('URL', 'fau-cris'),
                    array(__CLASS__, 'cris_check_callback'),
                    'fau_cris_options',
                    'cris_publications_section',
                    array(
                    'name' => 'cris_url',
                    'description' => __('Soll auch im APA- und MLA-Zitierstil (wenn vorhanden) ein Link zu einer Website angezeigt werden?', 'fau-cris')
                        )
                );
                add_settings_field(
                    'cris_oa',
                    __('OA-Icon', 'fau-cris'),
                    array(__CLASS__, 'cris_check_callback'),
                    'fau_cris_options',
                    'cris_publications_section',
                    array(
                    'name' => 'cris_oa',
                    'description' => __('Sollen Publikationen auch im APA- und MLA-Zitierstil als Open Access gekennzeichnet werden?', 'fau-cris')
                        )
                );
                add_settings_field(
                    'cris_bibtex',
                    __('BibTeX-Link', 'fau-cris'),
                    array(__CLASS__, 'cris_check_callback'),
                    'fau_cris_options',
                    'cris_publications_section',
                    array(
                    'name' => 'cris_bibtex',
                    'description' => __('Soll für jede Publikation ein Link zum BibTeX-Export angezeigt werden?', 'fau-cris')
                        )
                );

                add_settings_field(
                    'cris_univis',
                    __('Autoren verlinken', 'fau-cris'),
                    array(__CLASS__, 'cris_radio_callback'),
                    'fau_cris_options',
                    'cris_publications_section',
                    array(
                    'name' => 'cris_univis',
                    'options' => array(
                        'person' => __('Autoren mit ihrer Personen-Detailansicht im FAU-Person-Plugin verlinken', 'fau-cris'),
                        'cris' => __('Autoren mit ihrer Profilseite auf cris.fau.de verlinken', 'fau-cris'),
                        'none' => __('keinen Link setzen', 'fau-cris'))
                    )
                );

                add_settings_field(
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
                            'lastname-firstname' => __('Nachname, Vorname', 'fau-cris'))
                        )
                );
                add_settings_section(
                    'cris_awards_section', // ID
                    __('Auszeichnungen', 'fau-cris'), // Title
                    '__return_false', // Callback
                    'fau_cris_options' // Page
                );
                add_settings_field(
                    'cris_award_order',
                    __('Reihenfolge der Auszeichnungen', 'fau-cris'),
                    array(__CLASS__, 'cris_textarea_callback'),
                    'fau_cris_options',
                    'cris_awards_section',
                    array(
                    'name' => 'cris_award_order',
                    'description' => __('Siehe Reihenfolge der Publikationen. Nur eben für die Auszeichnungen.', 'fau-cris')
                        )
                );
                add_settings_field(
                    'cris_award_link',
                    __('Preisträger verlinken', 'fau-cris'),
                    array(__CLASS__, 'cris_radio_callback'),
                    'fau_cris_options',
                    'cris_awards_section',
                    array(
                    'name' => 'cris_award_link',
                    'options' => array(
                        'person' => __('Preisträger mit ihrer Personen-Detailansicht im FAU-Person-Plugin verlinken', 'fau-cris'),
                        'cris' => __('Preisträger mit ihrer Profilseite auf cris.fau.de verlinken', 'fau-cris'),
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
                add_settings_field(
                    'cris_field_link',
                    __('Kontaktpersonen verlinken', 'fau-cris'),
                    array(__CLASS__, 'cris_radio_callback'),
                    'fau_cris_options',
                    'cris_fields_section',
                    array(
                        'name' => 'cris_field_link',
                        'options' => array(
                            'person' => __('Kontaktpersonen mit ihrer Personen-Detailansicht im FAU-Person-Plugin verlinken', 'fau-cris'),
                            'cris' => __('Kontaktpersonen mit ihrer Profilseite auf cris.fau.de verlinken', 'fau-cris'),
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
                    'cris_project_order',
                    __('Reihenfolge der Forschungsprojekte', 'fau-cris'),
                    array(__CLASS__, 'cris_textarea_callback'),
                    'fau_cris_options',
                    'cris_projects_section',
                    array(
                    'name' => 'cris_project_order',
                    'description' => __('Siehe Reihenfolge der Publikationen. Nur eben für die Forschungsprojekte.', 'fau-cris')
                        )
                );
                add_settings_field(
                    'cris_project_link',
                    __('Projektbeteiligte verlinken', 'fau-cris'),
                    array(__CLASS__, 'cris_radio_callback'),
                    'fau_cris_options',
                    'cris_projects_section',
                    array(
                    'name' => 'cris_project_link',
                    'options' => array(
                        'person' => __('Projektleiter und -beteiligte mit ihrer Personen-Detailansicht im FAU-Person-Plugin verlinken', 'fau-cris'),
                        'cris' => __('Projektleiter und -beteiligte mit ihrer Profilseite auf cris.fau.de verlinken', 'fau-cris'),
                        'none' => __('keinen Link setzen', 'fau-cris'))
                    )
                );
                add_settings_field(
                    'cris_project_num_pub', // ID
                    __('Anzahl Publikationen', 'fau-cris'), // Title
                    array(__CLASS__, 'cris_textbox_callback'), // Callback
                    'fau_cris_options', // Page
                    'cris_projects_section', // Section
                    array(
                        'name' => 'cris_project_num_pub',
                        'description' => __('Maximale Anzahl der Publikationen, die in der Detailansicht eines Projekte angezeigt werden.', 'fau-cris')
                    )
                );
                
                add_settings_section(
                    'cris_patents_section', // ID
                    __('Patente', 'fau-cris'), // Title
                    '__return_false', // Callback
                    'fau_cris_options' // Page
                );
                add_settings_field(
                    'cris_patent_order',
                    __('Reihenfolge der Patente', 'fau-cris'),
                    array(__CLASS__, 'cris_textarea_callback'),
                    'fau_cris_options',
                    'cris_patents_section',
                    array(
                    'name' => 'cris_patent_order',
                    'description' => __('Siehe Reihenfolge der Publikationen. Nur eben für die Patente.', 'fau-cris')
                        )
                );
                add_settings_field(
                    'cris_patent_link',
                    __('Patentinhaber verlinken', 'fau-cris'),
                    array(__CLASS__, 'cris_radio_callback'),
                    'fau_cris_options',
                    'cris_patents_section',
                    array(
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
                    'cris_activities_order',
                    __('Reihenfolge der Aktivitäten', 'fau-cris'),
                    array(__CLASS__, 'cris_textarea_callback'),
                    'fau_cris_options',
                    'cris_activities_section',
                    array(
                    'name' => 'cris_activities_order',
                    'description' => __('Siehe Reihenfolge der Publikationen. Nur eben für die Aktivitäten.', 'fau-cris')
                        )
                );
                add_settings_field(
                    'cris_activities_link',
                    __('Personen verlinken', 'fau-cris'),
                    array(__CLASS__, 'cris_radio_callback'),
                    'fau_cris_options',
                    'cris_activities_section',
                    array(
                    'name' => 'cris_activities_link',
                    'options' => array(
                        'person' => __('Personen mit ihrer Personen-Detailansicht im FAU-Person-Plugin verlinken', 'fau-cris'),
                        'cris' => __('Personen mit ihrer Profilseite auf cris.fau.de verlinken', 'fau-cris'),
                        'none' => __('keinen Link setzen', 'fau-cris'))
                    )
                );
                add_settings_section(
                    'cris_standardizations_section', // ID
                    __('Standardisierungen', 'fau-cris'), // Title
                    '__return_false', // Callback
                    'fau_cris_options' // Page
                );
                add_settings_field(
                    'cris_standardizations_order',
                    __('Reihenfolge der Standardisierungen', 'fau-cris'),
                    array(__CLASS__, 'cris_textarea_callback'),
                    'fau_cris_options',
                    'cris_standardizations_section',
                    array(
                        'name' => 'cris_standardizations_order',
                        'description' => __('Siehe Reihenfolge der Publikationen. Nur eben für die Standardisierungen.', 'fau-cris')
                    )
                );
                add_settings_field(
                    'cris_standardizations_link',
                    __('Personen verlinken', 'fau-cris'),
                    array(__CLASS__, 'cris_radio_callback'),
                    'fau_cris_options',
                    'cris_standardizations_section',
                    array(
                        'name' => 'cris_standardizations_link',
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
                        'fields' => __('Custom-Shortcode für Forschungsbereiche', 'fau-cris'),
                        'projects' => __('Custom-Shortcode für Forschungsprojekte', 'fau-cris'))
                            )
                );
                break;
        }
    }

    /**
     * Sanitize each setting field as needed
     */
    public static function sanitize()
    {
        $error = new \WP_Error();
          // Check nonce first and return early if invalid
    if (!isset($_POST['_wpnonce_form']) || 
        !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce_form'])), 'fau_cris_form_nonce')) {
        return self::get_options(); // Return current options without changes
    }

    // Check user capabilities
    if (!current_user_can('manage_options')) {
        return self::get_options();
    }

    // Then proceed with your sanitization logic
    $new_input = self::get_options();
    $default_options = self::default_options();

         if (!isset($_POST['_wp_http_referer'])) {
            return $new_input; // Return early if the referer is missing
        }
        $parts = wp_parse_url(sanitize_text_field(wp_unslash($_POST['_wp_http_referer'])));
        parse_str($parts['query'], $query);
        $tab = (array_key_exists('tab', $query)) ? $query['tab'] : 'general';



        switch ($tab) {
            case 'general':
            default:
                $new_input['cris_org_nr'] = isset($_POST[self::option_name]['cris_org_nr']) ? sanitize_text_field(wp_unslash($_POST[self::option_name]['cris_org_nr'])) : 0;
                break;

            case 'layout':
                $new_input['cris_pub_order'] = isset($_POST[self::option_name]['cris_pub_order']) ? explode("\n", str_replace("\r", "", sanitize_textarea_field(wp_unslash($_POST[self::option_name]['cris_pub_order'])))) : $default_options['cris_pub_order'];
                $new_input['cris_pub_subtypes_order'] = isset($_POST[self::option_name]['cris_pub_subtypes_order']) ? explode("\n", str_replace("\r", "", sanitize_textarea_field(wp_unslash($_POST[self::option_name]['cris_pub_subtypes_order'])))) : $default_options['cris_pub_subtypes_order'];
                $new_input['cris_univis'] = isset($_POST[self::option_name]['cris_univis']) && in_array(sanitize_text_field(wp_unslash($_POST[self::option_name]['cris_univis'])), array('person', 'cris', 'none')) ? sanitize_text_field(wp_unslash($_POST[self::option_name]['cris_univis'])) : $default_options['cris_univis'];
                $new_input['cris_bibtex'] = isset($_POST[self::option_name]['cris_bibtex']) ? 1 : 0;
                $new_input['cris_url'] = isset($_POST[self::option_name]['cris_url']) ? 1 : 0;
                $new_input['cris_doi'] = isset($_POST[self::option_name]['cris_doi']) ? 1 : 0;
                $new_input['cris_oa'] = isset($_POST[self::option_name]['cris_oa']) ? 1 : 0;
                $new_input['cris_name_order_plugin'] = (isset($_POST[self::option_name]['cris_name_order_plugin'])
                        && $_POST[self::option_name]['cris_name_order_plugin'] == 'lastname-firstname') ? 'lastname-firstname' : 'firstname-lastname';
                $new_input['cris_award_order'] = isset($_POST[self::option_name]['cris_award_order']) ? explode("\n", str_replace("\r", "", sanitize_textarea_field(wp_unslash($_POST[self::option_name]['cris_award_order'])))) : $default_options['cris_award_order'];
                $new_input['cris_award_link'] = isset($_POST[self::option_name]['cris_award_link']) && in_array(sanitize_text_field(wp_unslash($_POST[self::option_name]['cris_award_link'])), array('person', 'cris', 'none')) ? sanitize_text_field(wp_unslash($_POST[self::option_name]['cris_award_link'])) : $default_options['cris_award_link'];
                $new_input['cris_fields_num_pub'] = isset($_POST[self::option_name]['cris_fields_num_pub']) ? sanitize_textarea_field(wp_unslash($_POST[self::option_name]['cris_fields_num_pub'])) : 0;
                $new_input['cris_project_num_pub'] = isset($_POST[self::option_name]['cris_project_num_pub']) ? sanitize_textarea_field(wp_unslash($_POST[self::option_name]['cris_project_num_pub'])) : 0;
                $new_input['cris_field_link'] = isset($_POST[self::option_name]['cris_field_link']) && in_array(sanitize_text_field(wp_unslash($_POST[self::option_name]['cris_field_link'])), array('person', 'cris', 'none')) ? sanitize_text_field(wp_unslash($_POST[self::option_name]['cris_field_link'])) : $default_options['cris_field_link'];
                $new_input['cris_project_order'] = isset($_POST[self::option_name]['cris_project_order']) ? explode("\n", str_replace("\r", "", sanitize_text_field(wp_unslash($_POST[self::option_name]['cris_project_order'])))) : $default_options['cris_project_order'];
                $new_input['cris_project_link'] = isset($_POST[self::option_name]['cris_project_link']) && in_array(sanitize_text_field(wp_unslash($_POST[self::option_name]['cris_project_link'])), array('person', 'cris', 'none')) ? sanitize_text_field(wp_unslash($_POST[self::option_name]['cris_project_link'])) : $default_options['cris_project_link'];
                $new_input['cris_patent_order'] = isset($_POST[self::option_name]['cris_patent_order']) ? explode("\n", str_replace("\r", "", sanitize_textarea_field(wp_unslash($_POST[self::option_name]['cris_patent_order'])))) : $default_options['cris_patent_order'];
                $new_input['cris_patent_link'] = isset($_POST[self::option_name]['cris_patent_link']) && in_array(sanitize_text_field(wp_unslash($_POST[self::option_name]['cris_patent_link'])), array('person', 'cris', 'none')) ? sanitize_text_field(wp_unslash($_POST[self::option_name]['cris_patent_link'])) : $default_options['cris_patent_link'];
                $new_input['cris_activities_order'] = isset($_POST[self::option_name]['cris_activities_order']) ? explode("\n", str_replace("\r", "", sanitize_textarea_field(wp_unslash($_POST[self::option_name]['cris_activities_order'])))) : $default_options['cris_activities_order'];
                $new_input['cris_activities_link'] = isset($_POST[self::option_name]['cris_activities_link']) && in_array(sanitize_text_field(wp_unslash($_POST[self::option_name]['cris_activities_link'])), array('person', 'cris', 'none')) ? sanitize_text_field(wp_unslash($_POST[self::option_name]['cris_activities_link'])) : $default_options['cris_activities_link'];
                $new_input['cris_standardizations_order'] = isset($_POST[self::option_name]['cris_standardizations_order']) ? explode("\n", str_replace("\r", "", sanitize_textarea_field(wp_unslash($_POST[self::option_name]['cris_standardizations_order'])))) : $default_options['cris_standardizations_order'];
                $new_input['cris_standardizations_link'] = isset($_POST[self::option_name]['cris_standardizations_link']) && in_array(sanitize_text_field(wp_unslash($_POST[self::option_name]['cris_standardizations_link'])), array('person', 'cris', 'none')) ? sanitize_text_field(wp_unslash($_POST[self::option_name]['cris_standardizations_link'])) : $default_options['cris_standardizations_link'];
                $new_input['cris_pub_title_link_order'] = isset($_POST[self::option_name]['cris_pub_title_link_order']) ? explode("\n", str_replace("\r", "", sanitize_textarea_field(wp_unslash($_POST[self::option_name]['cris_pub_title_link_order'])))) : $default_options['cris_pub_title_link_order'];
                break;
            case 'sync':
                $new_input['cris_sync_check'] = isset($_POST[self::option_name]['cris_sync_check']) ? 1 : 0;
                if (isset($_POST[self::option_name]['cris_sync_shortcode_format']) && is_array($_POST[self::option_name]['cris_sync_shortcode_format'])) {
                    /*foreach ($_POST[self::option_name]['cris_sync_shortcode_format'] as $_check){
                        foreach ($_check as $_k => $_v) {
                            $new_input['cris_sync_shortcode_format'][$_k] = $_v;
                        }
                    }*/
                    $new_input['cris_sync_shortcode_format'] = sanitize_text_field(wp_unslash($_POST[self::option_name]['cris_sync_shortcode_format']));
                }
                break;
        }
        return $new_input;
    }

    /**
     * Get the settings option array and print its values
     */
    // Checkbox
    public static function cris_check_callback($args): void
    {
        $options = self::get_options();
        if (array_key_exists('name', $args)) {
            $name = esc_attr($args['name']);
        }
        if (array_key_exists('description', $args)) {
            $description = esc_attr($args['description']);
        }
        if ($name == 'cris_sync_check') {
            print "<p>";
            printf(
                /* translators: 1: strong tag, 2: strong tag, 3: link to user manual, 4: closing link tag */
            esc_html__( '%1$s Wichtig! %2$s Lesen Sie vor der Aktivierung unbedingt die Hinweise in unserem %3$s Benutzerhandbuch! %4$s', 'fau-cris' ),
            '<strong>',
            '</strong>',
            '<a href="' . esc_url( 'https://www.wordpress.rrze.fau.de/plugins/fau-cris/erweiterte-optionen/' ) . '">',
            '</a>'
                );
            print "<p>";
        }
        if (array_key_exists('options', $args)) {
            $checks = $args['options'];
            foreach ($checks as $_k => $_v) { ?>
                <label>
                    <input 
                        name="<?php echo esc_attr( sprintf('%s[%s][%s]', self::option_name, $name, $_k ) ); ?>" 
                        type="checkbox" 
                        value="1"
                        <?php 
                        if (isset($options[$name][$_k])) { 
                            echo checked($options[$name][$_k], 1, false); 
                        } 
                        ?>
                    >
                    <?php echo esc_html( $_v ); ?>
                </label><br />
            <?php }
        } else { ?>
            <label>
                <input 
                    name="<?php echo esc_attr( sprintf('%s[%s]', self::option_name, $name ) ); ?>" 
                    type="checkbox" 
                    value="1" 
                    <?php
                    if (isset($options[$name])) {
                        echo checked($options[$name], 1, false);
                    }
                    ?> >
                <?php if (isset($description)) { ?>
                    <span class="description"><?php echo esc_html( $description ); ?></span></label>
                <?php }
            
        }
    }

    // Radio Button
    public static function cris_radio_callback($args): void
    {
        $options = self::get_options();
        if (array_key_exists('name', $args)) {
            $name = esc_attr($args['name']);
        }
        if (array_key_exists('description', $args)) {
            $description = esc_attr($args['description']);
        }
        if (array_key_exists('options', $args)) {
            $radios = $args['options'];
        }
        foreach ($radios as $_k => $_v) { ?>
            <label>
                <input 
                    name="<?php echo esc_attr( sprintf('%s[%s]', self::option_name, $name ) ); ?>" 
                    type="radio" 
                    value="<?php echo esc_attr( $_k ); ?>" 
                    <?php 
                    if (isset($options[$name])) { 
                        checked($options[$name], $_k); 
                    } 
                    ?> >
                <?php echo esc_html( $_v ); ?>
            </label><br />
        <?php }

        if (isset($description)) { ?>
            <p class="description"><?php echo esc_html($description); ?></p>
        <?php }
    }

    //Select
    public static function cris_select_callback($args): void
    {
        $options = self::get_options();
        if (array_key_exists('name', $args)) {
            $name = esc_attr($args['name']);
        }
        if (array_key_exists('description', $args)) {
            $description = esc_attr($args['description']);
        }
        if (array_key_exists('options', $args)) {
            $limit = $args['options'];
        } ?>
        <select name="<?php echo esc_attr( sprintf('%s[%s]', self::option_name, $name ) ); ?>">
            <?php foreach ($limit as $_k => $_v) { ?>
                <option value="<?php echo esc_attr( $_k ); ?>" 
                    <?php if (isset($options[$name])) { 
                        selected($options[$name], $_k); 
                    } ?>>
                    <?php echo esc_html( $_v ); ?>
                </option>
            <?php } ?>
        </select>
        <?php
        if (isset($description)) { ?>
            <p class="description"><?php echo esc_html($description); ?></p>
        <?php }
    }

    // Textbox
    public static function cris_textbox_callback($args): void
    {
        $options = self::get_options();
        if (array_key_exists('name', $args)) {
            $name = esc_attr($args['name']);
        }
        if (array_key_exists('description', $args)) {
            $description = esc_attr($args['description']);
        }
        ?>
        <input name="<?php echo esc_attr( sprintf('%s[%s]', self::option_name, $name )); ?>" type='text' value="<?php
        if (array_key_exists($name, $options)) {
            echo esc_attr($options[$name]);
        }
        ?>" ><br />
               <?php if (isset($description)) { ?>
            <span class="description"><?php echo esc_attr($description); ?></span>
                    <?php
               }
    }

    // Textarea
    public static function cris_textarea_callback($args): void
{
    $options = self::get_options();
    $default_options = self::default_options();
    
    if (array_key_exists('name', $args)) {
        $name = esc_attr($args['name']);
    }
    if (array_key_exists('description', $args)) {
        $description = esc_attr($args['description']);
    }
    ?>
    <textarea name="<?php echo esc_attr(sprintf('%s[%s]', self::option_name, $name)); ?>" cols="30" rows="8"><?php
    if (array_key_exists($name, $options)) {
        if (is_array($options[$name]) && count($options[$name]) > 0 && $options[$name][0] != '') {
            echo esc_textarea(implode("\n", $options[$name]));
        } else {
            echo esc_textarea(implode("\n", $default_options[$name]));
        }
    }
    ?></textarea><br />
    <?php if (isset($description)) { ?>
        <span class="description"><?php echo esc_html($description); ?></span>
    <?php
    }
}

    /**
     * Add Shortcodes
     */
    public static function cris_shortcode($atts, $content = null, $tag = null)
    {
        $parameter = self::cris_shortcode_parameter($atts, $content = null, $tag);
        global $post;
        $page_lang = Tools::getPageLanguage($post->ID);
        if (isset($parameter['show']) && $parameter['show'] == 'standardizations') {
            // Standardisierung
            $liste = new Standardisierungen($parameter['entity'], $parameter['entity_id'], $page_lang, $parameter['display_language']);
            if (isset($liste->error) && is_wp_error($liste->error)) {
                return $liste->error->get_error_message();
            }
            if ($parameter['standardization'] != '') {
                return $liste->singleStandardization($parameter['hide']);
            }
            return $liste->standardizationListe($parameter);
        } elseif (isset($parameter['show']) && $parameter['show'] == 'equipment') {
            // Equipment
            $liste = new Equipment($parameter['entity'], $parameter['entity_id'], $page_lang, $parameter['display_language']);
            if (isset($liste->error) && is_wp_error($liste->error)) {
                return $liste->error->get_error_message();
            }
            if ($parameter['equipment'] != '') {
                return $liste->singleEquipment($parameter['hide'], $parameter['quotation']);
            }
            if ($parameter['limit'] != '') {
                return $liste->equiListe($parameter);
            }
            if (strpos($parameter['order1'], 'type') !== false) {
                return $liste->equiNachTyp($parameter);
            }
            if (strpos($parameter['order1'], 'year') !== false) {
                return $liste->equiNachJahr($parameter);
            }
            return $liste->equiListe($parameter);
        } elseif (isset($parameter['show']) && $parameter['show'] == 'organisation') {
            // Forschung
            $liste = new Organisation($parameter['entity'], $parameter['entity_id'], $page_lang, $parameter['display_language']);
            if (is_wp_error(isset($liste->error) && is_wp_error($liste->error))) {
                return $liste->error->get_error_message();
            }
            return $liste->singleOrganisation($parameter['hide'], $parameter['image_align']);
        } elseif (isset($parameter['show']) && $parameter['show'] == 'fields') {
            // Forschungsbereiche
            $liste = new Forschungsbereiche($parameter['entity'], $parameter['entity_id'], $page_lang, $parameter['display_language']);
            if (isset($liste->error) && is_wp_error($liste->error)) {
                return $liste->error->get_error_message();
            }

            if ($parameter['field'] != '') {
                return $liste->singleField($parameter);
            }
            if (!empty($parameter['limit'])) {
                return $liste->fieldListe($parameter);
            }
            return $liste->fieldListe($parameter);
        } elseif (isset($parameter['show']) && $parameter['show'] == 'activities') {
            // Aktivitäten
            $liste = new Aktivitaeten($parameter['entity'], $parameter['entity_id'], $page_lang, $parameter['display_language']);
            if (isset($liste->error) && is_wp_error($liste->error)) {
                return $liste->error->get_error_message();
            }

            if ($parameter['activity'] != '') {
                return $liste->singleActivity($parameter['hide']);
            }
            if ($parameter['limit'] != '') {
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
            $liste = new Patente($parameter['entity'], $parameter['entity_id'], $page_lang, $parameter['display_language']);
            if (isset($liste->error) && is_wp_error($liste->error)) {
                return $liste->error->get_error_message();
            }
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
            $liste = new Projekte($parameter['entity'], $parameter['entity_id'], $page_lang, $parameter['display_language']);

            if (isset($liste->error) && is_wp_error($liste->error)) {
                return $liste->error->get_error_message();
            }
            if ($parameter['project'] != '') {
                return $liste->singleProj($parameter);
            }
            // if (!empty($parameter['limit'])) {
            //     return $liste->projListe($parameter);
            // }
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
            $liste = new Auszeichnungen($parameter['entity'], $parameter['entity_id'], $parameter['display'], $page_lang, $parameter['display_language']);
            if (isset($liste->error) && is_wp_error($liste->error)) {
                return $liste->error->get_error_message();
            }

            if ($parameter['award'] != '') {
                return $liste->singleAward($parameter['showname'], $parameter['showyear'], $parameter['showawardname'], $parameter['display']);
            }
            if (strpos($parameter['order1'], 'type') !== false) {
                return $liste->awardsNachTyp($parameter);
            }
            if (strpos($parameter['order1'], 'year') !== false) {
                return $liste->awardsNachJahr($parameter);
            }
            return $liste->awardsListe($parameter, '');
        } else {
            // Publications
            $liste = new Publikationen($parameter['entity'], $parameter['entity_id'], $parameter['name_order_plugin'], $page_lang, $parameter['display_language']);

            if (isset($liste->error) && is_wp_error($liste->error)) {
                return $liste->error->get_error_message();
            }

            if ($parameter['publication'] != '' && $parameter['order1'] == '') {
                return $liste->singlePub($parameter['quotation'], '', 'default', $parameter['showimage'], $parameter['image_align'], $parameter['image_position'], $parameter['display'],$parameter['listtype']);
            }
            if ($parameter['order1'] == '' && ($parameter['limit'] != '' || $parameter['notable'] != 0)) {
                return $liste->pubListe($parameter);
            }
            if (strpos($parameter['order1'], 'type') !== false) {
                return $liste->pubNachTyp($parameter);
            }
            return $liste->pubNachJahr($parameter);
        }

        // nothing
        return '';
    }

    public static function cris_custom_shortcode($atts, $content = null, $tag = null)
    {
        $parameter = self::cris_shortcode_parameter($atts, $content, $tag);
        global $post;
        $page_lang = Tools::getPageLanguage($post->ID);

        // Standardisierung
        if (isset($parameter['show']) && $parameter['show'] == 'standardizations') {
            $liste = new Standardisierungen($parameter['entity'], $parameter['entity_id'], $page_lang, $parameter['display_language']);
            if (isset($liste->error) && is_wp_error($liste->error)) {
                return $liste->error->get_error_message();
            }
            return $liste->standardizationListe($parameter, $content);
        } elseif ($parameter['show'] == 'organisation') {
            // Forschung
            $liste = new Organisation($parameter['entity'], $parameter['entity_id'], $page_lang, $parameter['display_language']);
            if (isset($liste->error) && is_wp_error($liste->error)) {
                return $liste->error->get_error_message();
            }
            return $liste->customOrganisation($content, $parameter['image_align']);
        } elseif ($parameter['show'] == 'equipment') {
            // Forschungsinfrastruktur
            $liste = new Equipment($parameter['entity'], $parameter['entity_id'], $page_lang, $parameter['display_language']);
            if (isset($liste->error) && is_wp_error($liste->error)) {
                return $liste->error->get_error_message();
            }
            return $liste->customEquipment($content, $parameter);
        } elseif ($parameter['show'] == 'fields') {
            // Forschungsbereiche
            $liste = new Forschungsbereiche($parameter['entity'], $parameter['entity_id'], $page_lang, $parameter['display_language']);
            if (isset($liste->error) && is_wp_error($liste->error)) {
                return $liste->error->get_error_message();
            }
            if ($parameter['field'] != '') {
                return $liste->customField($content, $parameter);
            }
        } elseif (isset($parameter['show']) && $parameter['show'] == 'projects') {
            // Projekte
            $liste = new Projekte($parameter['entity'], $parameter['entity_id'], $page_lang, $parameter['display_language']);
            if (isset($liste->error) && is_wp_error($liste->error)) {
                return $liste->error->get_error_message();
            }
            if ($parameter['project'] != '') {
                return $liste->customProj($content, $parameter);
            }
        } elseif ($parameter['show'] == 'awards') {
            // Auszeichnungen
            $liste = new Auszeichnungen($parameter['entity'], $parameter['entity_id'], $page_lang, $parameter['display_language']);
            if (isset($liste->error) && is_wp_error($liste->error)) {
                return $liste->error->get_error_message();
            }
            if ($parameter['award'] != '') {
                return $liste->customAward($content, $parameter);
            }
            return $liste->awardsListe($parameter, $content);
        } elseif ($parameter['show'] == 'publications') {
            // Publikationen
            $liste = new Publikationen($parameter['entity'], $parameter['entity_id'], '', $page_lang);
            if (isset($liste->error) && is_wp_error($liste->error)) {
                return $liste->error->get_error_message();

            }
            if ($parameter['publication'] != '' && $parameter['order1'] == '') {

                return $liste->singlePub($parameter['quotation'], $content, $parameter['sc_type'], 1, $parameter['image_align'], $parameter['image_position'],$parameter['display'],$parameter['listtype']);

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


    private static function cris_shortcode_parameter($atts, $content = '', $tag = ''): array
    {
        global $post;
        $options = self::get_options();

        // Default attributes
        $defaultAtts = [
            'show' => 'publications',
            'orderby' => '',
            'year' => '',
            'start' => '',
            'end' => '',
            'orgid' => $options['cris_org_nr'],
            'persid' => '',
            'publication' => '',
            'pubtype' => '',
            'quotation' => '',
            'language' => '',
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
            'showimage' => 0,
            'display' => 'list',
            'project' => '',
            'hide' => '',
            'projects_hide'=>'',
            'role' => 'all',
            'status' => '',
            'patent' => '',
            'activity' => '',
            'field' => '',
            'fau' => '',
            'equipment' => '',
            'manufacturer' => '',
            'constructionyear' => '',
            'constructionyearstart' => '',
            'constructionyearend' => '',
            'location' => '',
            'peerreviewed' => '',
            'current' => '',
            'publications_limit' => $options['cris_fields_num_pub'] ?: '5',
            'project_publications_limit'=>$options['cris_project_num_pub'] ?: '5',
            'name_order_plugin' => $options['cris_name_order_plugin'] ?: 'firstname-lastname',
            'notable' => '',
            'publications_year' => '',
            'publications_start' => '',
            'publications_end' => '',
            'publications_type' => '',
            'publications_subtype' => '',
            'publications_fau' => '',
            'publications_peerreviewed' => '',
            'publications_orderby' => '',
            'publications_notable' => '',
            'publications_language' => '',
            'publications_display' => 'list',
            'publications_format' => 'list',
            'image_align' => 'right',
            'accordion_title' => '#name# (#year#)',
            'accordion_color' => '',
            'display_language' => Tools::getPageLanguage($post->ID),
            'organisation' => $options['cris_org_nr'],
            'standardization' => '',
            'projects_status'=>'',
            'projects_start'=>'',
            'author_position'=>'',
            'publicationsum'=>'',
            'useprojpubls'=>'false',
            'listtype'=>'ul'
        ];

        // Attributes
        extract(shortcode_atts($defaultAtts, $atts));

        $orgid = str_replace(' ', '', sanitize_text_field($orgid));
        $organisation = str_replace(' ', '', sanitize_text_field($organisation));
        $orgid = $orgid ?: $organisation;

        $sc_param['orderby'] = sanitize_text_field($orderby);
        $sc_param['orgid'] = $orgid;
        $sc_param['persid'] = sanitize_text_field($persid);
        $sc_param['publication'] = sanitize_text_field($publication);
        $sc_param['award'] = sanitize_text_field($award);
        $sc_param['awardnameid'] = sanitize_text_field($awardnameid);
        $sc_param['project'] = sanitize_text_field($project);
        $sc_param['patent'] = sanitize_text_field($patent);
        $sc_param['activity'] = sanitize_text_field($activity);
        $sc_param['field'] = sanitize_text_field($field);
        $sc_param['show'] = sanitize_text_field($show);
        if ($type == 'weitere') {
            $type = 'andere';
        }
        $sc_param['type'] = (!empty($pubtype)) ? sanitize_text_field($pubtype) : sanitize_text_field($type); //Abwärtskompatibilität
        $sc_param['subtype'] = sanitize_text_field($subtype);
        $sc_param['year'] = sanitize_text_field($year);
        $sc_param['start'] = sanitize_text_field($start);
        $sc_param['end'] = sanitize_text_field($end);
        $sc_param['quotation'] = sanitize_text_field($quotation);
        $language = sanitize_text_field($language);
        $sc_param['language'] = in_array($language, Dicts::$pubLanguages) ? $language : '';
        $limit = ($limit != '' ? $limit : $items);
        $sc_param['limit'] = sanitize_text_field($limit);
        $sc_param['format'] = sanitize_text_field($format);
        $sc_param['showname'] = sanitize_text_field($showname);
        $sc_param['showyear'] = sanitize_text_field($showyear);
        $sc_param['showawardname'] = sanitize_text_field($showawardname);
        $sc_param['showimage'] = $showimage == 1 ? 1 : 0;
        $sc_param['display'] = sanitize_text_field($display);
        $sc_param['role'] = sanitize_text_field($role);
        $sc_param['fau'] = sanitize_text_field($fau);
        $sc_param['equipment'] = sanitize_text_field($equipment);
        $sc_param['manufacturer'] = sanitize_text_field($manufacturer);
        $sc_param['constructionyear'] = sanitize_text_field($constructionyear);
        $sc_param['constructionyearstart'] = sanitize_text_field($constructionyearstart);
        $sc_param['constructionyearend'] = sanitize_text_field($constructionyearend);
        $sc_param['location'] = sanitize_text_field($location);
        $sc_param['peerreviewed'] = sanitize_text_field($peerreviewed);
        $sc_param['name_order_plugin'] = sanitize_text_field($name_order_plugin);
        $sc_param['notable'] = $notable == 1 ? 1 : 0;
        $sc_param['publications_language'] = sanitize_text_field($publications_language);
        $sc_param['publications_limit'] = sanitize_text_field($publications_limit);
        $sc_param['project_publications_limit'] = sanitize_text_field($project_publications_limit);
        $sc_param['publications_display'] = sanitize_text_field($publications_display);
        $sc_param['publications_format'] = ($publications_format != 'list' ? sanitize_text_field($publications_format) : $sc_param['publications_display']);
        $sc_param['publications_year'] = sanitize_text_field($publications_year);
        $sc_param['publications_start'] = sanitize_text_field($publications_start);
        $sc_param['publications_end'] = sanitize_text_field($publications_end);
        $sc_param['publications_type'] = sanitize_text_field($publications_type);
        $sc_param['publications_subtype'] = sanitize_text_field($publications_subtype);
        $sc_param['publications_fau'] = sanitize_text_field($publications_fau);
        $sc_param['publications_peerreviewed'] = sanitize_text_field($publications_peerreviewed);
        $sc_param['publications_orderby'] = sanitize_text_field($publications_orderby);
        $sc_param['publications_notable'] = $publications_notable == 1 ? 1 : 0;
        $sc_param['standardization'] = sanitize_text_field($standardization);
        $sc_param['projects_status'] = sanitize_text_field($projects_status);
        $sc_param['projects_start'] = sanitize_text_field($projects_start);
        $sc_param['author_position'] = sanitize_text_field($author_position);
        $sc_param['publicationsum'] = sanitize_text_field($publicationsum);
        $sc_param['useprojpubls'] = strtolower(sanitize_text_field($useprojpubls));
        $sc_param['listtype'] = strtolower(sanitize_text_field($listtype));
        switch ($sortby) {
            case 'created':
                $sc_param['sortby'] = 'updatedon';
                $sc_param['sortorder'] = SORT_DESC;
                break;
            case 'updated':
                $sc_param['sortby'] = 'createdon';
                $sc_param['sortorder'] = SORT_DESC;
                break;
            case 'author':
                $sc_param['sortby'] = 'relauthors';
                $sc_param['sortorder'] = SORT_ASC;
                break;
            case 'date':
            default:
                $sc_param['sortby'] = 'virtualdate';
                $sc_param['sortorder'] = SORT_DESC;
                break;
        }
        if (in_array($image_align, ['left', 'right', 'none'])) {
            $sc_param['image_align'] = 'align' . sanitize_text_field($image_align);
            $sc_param['image_position'] = 'top';
        } elseif (in_array($image_align, ['bottom', 'top'])) {
            $sc_param['image_align'] = 'alignnone';
            $sc_param['image_position'] = sanitize_text_field($image_align);
        } else {
            $sc_param['image_align'] = 'alignright';
            $sc_param['image_position'] = 'top';
        }
        $sc_param['accordion_title'] = sanitize_text_field($accordion_title);
        $sc_param['accordion_title'] = str_replace(['[', ']'], ['&#91;', '&#93;'], $accordion_title);
        $sc_param['accordion_color'] = sanitize_text_field($accordion_color);
        if (sanitize_text_field($current) == "1" && sanitize_text_field($status) == '') { // Abwärtskompatibilität
            $sc_param['status'] = 'current';
        } else {
            $sc_param['status'] = sanitize_text_field($status);
        }
        $sc_param['display_language'] = ($display_language == 'en') ? 'en' : 'de';
        $hide = str_replace(' ', '', sanitize_text_field($hide));
        $sc_param['hide'] = explode(',', $hide);
        $projects_hide = str_replace(' ', '', sanitize_text_field($projects_hide));
        $sc_param['projects_hide'] = explode(',', $projects_hide);
        if ($sc_param['publication'] != '') {
            $sc_param['entity'] = 'publication';
            if (strpos($sc_param['publication'], ',')) {
                $sc_param['publication'] = str_replace(' ', '', $sc_param['publication']);
                $sc_param['publication'] = explode(',', $sc_param['publication']);
            }
            $sc_param['entity_id'] = $sc_param['publication'];
        } elseif ($sc_param['standardization'] != '') {
            $sc_param['entity'] = 'standardization';
            if (strpos($sc_param['standardization'], ',') !== false) {
                $sc_param['standardization'] = str_replace(' ', '', $sc_param['standardization']);
                $sc_param['standardization'] = explode(',', $sc_param['standardization']);
            }
            $sc_param['entity_id'] = $sc_param['standardization'];
        } elseif ($sc_param['equipment'] != '') {
            $sc_param['entity'] = 'equipment';
            if (strpos($sc_param['equipment'], ',') !== false) {
                $sc_param['equipment'] = str_replace(' ', '', $sc_param['equipment']);
                $sc_param['equipment'] = explode(',', $sc_param['equipment']);
            }
            $sc_param['entity_id'] = $sc_param['equipment'];
        } elseif ($sc_param['field'] != '' && $sc_param['useprojpubls'] == 'false') {
            $sc_param['entity'] = 'field';
            if (strpos($sc_param['field'], ',') !== false) {
                $sc_param['field'] = str_replace(' ', '', $sc_param['field']);
                $sc_param['field'] = explode(',', $sc_param['field']);
            }
            $sc_param['entity_id'] = $sc_param['field']; } 
            elseif ($sc_param['field'] != '' && $sc_param['useprojpubls'] == 'true') {  
            $sc_param['entity'] = 'field_incl_proj';
            if (strpos($sc_param['field'], ',') !== false) {
                $sc_param['field'] = str_replace(' ', '', $sc_param['field']);
                $sc_param['field'] = explode(',', $sc_param['field']);
            }
            $sc_param['entity_id'] = $sc_param['field'];    
        }
        elseif (isset($sc_param['activity']) && $sc_param['activity'] != '') {
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
            if (isset($sc_param['author_position']) && $sc_param['author_position'] != '') {
                if (strpos($sc_param['author_position'], ',') !== false) {
                    $sc_param['author_position'] = str_replace(' ', '', $sc_param['author_position']);
                    $sc_param['author_position'] = explode(',', $sc_param['author_position']);
                }
                
            }
            if (strpos($sc_param['persid'], ',') !== false) {
                $sc_param['persid'] = str_replace(' ', '', $sc_param['persid']);
                $sc_param['persid'] = explode(',', $sc_param['persid']);
            }
            $sc_param['entity_id'] = $sc_param['persid'];
        } elseif ($sc_param['orgid'] != '') {
            $sc_param['entity'] = '';
            $sc_param['entity_id'] = '';
            if (strpos($sc_param['orgid'], ',') !== false) {
                $sc_param['orgid'] = explode(',', $sc_param['orgid']);
                $sc_param['orgid'] = array_filter($sc_param['orgid'], fn ($a) => (absint($a) !== 0));
            } else {
                $sc_param['orgid'] = absint($sc_param['orgid']);
                $sc_param['orgid'] = $sc_param['orgid'] ?: '';
            }
            if (!empty($sc_param['orgid'])) {
                $sc_param['entity'] = 'orga';
                $sc_param['entity_id'] = $sc_param['orgid'];
            }
        } else {
            $sc_param['entity'] = '';
            $sc_param['entity_id'] = '';
        }

        $sc_param['order1'] = '';
        $sc_param['order2'] = '';

        if (isset($sc_param['publicationsum']) && $sc_param['publicationsum'] != '') {
            if (strpos($sc_param['publicationsum'], ',') !== false) {
                $sc_param['publicationsum'] = str_replace(' ', '', $sc_param['publicationsum']);
                $sc_param['publicationsum'] = explode(',', $sc_param['publicationsum']);
            }
            
        }

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

    public static function cris_enqueue_styles(): void
    {
        global $post;
        $plugin_url = plugin_dir_url(__FILE__);
        if ($post && has_shortcode($post->post_content, 'cris')
                || $post && has_shortcode($post->post_content, 'cris-custom')) {
            wp_enqueue_style('cris', plugins_url('css/cris.css', __FILE__), array(), self::version);
            wp_enqueue_script('cris', plugins_url('js/cris.js', __FILE__), array('jquery'), self::version,false);
        }
    }

    /*
     * WP-Cron
     */

    public static function cris_auto_sync(): void
    {
        global $post;
        $page_lang = Tools::getPageLanguage($post->ID);
        $sync = new Sync($page_lang);
        $sync->do_sync(false);
    }

    public static function cris_cron(): void
    {
        $options = get_option('_fau_cris');
        if (isset($options['cris_sync_check'])
                && $options['cris_sync_check'] != 1) {
            if (wp_next_scheduled('cris_auto_update')) {
                wp_clear_scheduled_hook('cris_auto_update');
            }
            return;
        }
        if (!isset($options['cris_org_nr'])
                || $options['cris_org_nr'] == 0
                || !isset($options['cris_sync_check'])
                || $options['cris_sync_check'] != 1) {
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
                        . get_date_from_gmt(gmdate('Y-m-d H:i:s', $timestamp), 'd.m.Y - H:i');
                add_settings_error('AutoSyncComplete', 'autosynccomplete', $message, 'updated');
                settings_errors();
            }
        }
    }

    /*
     * Hilfe-Panel über der Theme-Options-Seite
     */

    public static function cris_help_menu(): void
    {

        $content_cris = array(
            '<p>' . __('Binden Sie Daten aus aus dem FAU-Forschungsportal <strong>CRIS (Currrent Research Information System)</strong> in Ihren Webauftritt ein. Das Plugin ermöglicht außerdem die Integration mit dem FAU-Person-Plugin.', 'fau-cris') . '</p>',
            '<p>' . __('Aktuell werden folgende in CRIS erfasste Forschungsleistungen unterstützt:', 'fau-cris') . '</p>'
            . '<ul>'
            . '<li>' . __('Publikationen', 'fau-cris') . '</li>'
            . '<li>' . __('Auszeichnungen', 'fau-cris') . '</li>'
            . '</ul>'
            . '<p>' . __('Über den Shortcode lassen sich jeweils verschiedene Ausgabeformate einstellen.', 'fau-cris') . '</p>'
            . '<p>' . __('<strong>CRIS-OrgNr</strong>:<br>Die Nummer der der Organisationseinheit, für die die Publikationen und Personendaten ausgegeben werden. Diese erfahren Sie, wenn Sie in CRIS eingeloggt sind, oder wenn Sie ich Ihre Organisationseinheit auf http://cris.fau.de anzeigen lassen, in der URL: z.B. ', 'fau-cris') . FAU_CRIS::cris_publicweb . 'organisations/<strong><em>141517</em></strong>.' . '</p>'
        );

        $content_shortcode_publikationen = array(
            '<h1>Shortcodes</h1>'
            . '<ul>'
            . '<li><code>[cris show="publications"]</code>: ' . __('Publikationsliste (automatisch nach Jahren gegliedert)','fau-cris') . '</li>'
            . '<li><code>[cris show="awards"]</code>: ' . __('Auszeichnungen (automatisch nach Jahren sortiert)','fau-cris') . '</li>'
            . '</ul>'
            . '<h2>' . __('Mögliche Zusatzoptionen', 'fau-cris') . '</h2>'
            . '<p>' . __('Ausgabe lässt sich beliebig anpassen. Eine Übersicht der verschiedenen Shortcode-Parameter zum Filtern, Sortieren und Ändern der Darstellung finden Sie unter: ','fau-cris') . '<a href="https://www.wordpress.rrze.fau.de/plugins/fau-cris/" target="_blank">https://www.wordpress.rrze.fau.de/plugins/fau-cris/</a>'

        );

        $content_fauperson = array(
            '<p>' . __('Wenn Sie das <strong>FAU-Person</strong>-Plugin verwenden, können Autoren mit ihrer FAU-Person-Kontaktseite verlinkt werden.', 'fau-cris') . '</p>',
            '<p>' . __('Wenn diese Option in den Einstellungen des CRIS-Plugins aktiviert ist, überprüft das Plugin selbstständig, welche Personen vorhanden sind und setzt die entsprechenden Links.', 'fau-cris') . '</p>'
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
