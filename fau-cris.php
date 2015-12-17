<?php
/**
 * Plugin Name: FAU CRIS
 * Description: Anzeige von Daten aus dem FAU-Forschungsportal CRIS in WP-Seiten
 * Version: 1.6.3
 * Author: RRZE-Webteam
 * Author URI: http://blogs.fau.de/webworking/
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
	const version = '1.6.3';
	const option_name = '_fau_cris';
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
		load_plugin_textdomain('fau-cris', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

		add_action('admin_init', array(__CLASS__, 'admin_init'));
		add_action('admin_menu', array(__CLASS__, 'add_options_page'));
		add_filter('plugin_action_links_' . plugin_basename(__FILE__), array(__CLASS__, 'add_action_links') );

		add_shortcode('cris', array(__CLASS__, 'cris_shortcode'));

	}

	/**
	 * Check PHP and WP Version and if Contact Form 7 is active
	 */
	public static function activate() {
		self::version_compare();
		update_option(self::version_option_name, self::version);
	}

	private static function version_compare() {
		$error = '';

		if (version_compare(PHP_VERSION, self::php_version, '<')) {
			$error = sprintf(__('Ihre PHP-Version %s ist veraltet. Bitte aktualisieren Sie mindestens auf die PHP-Version %s.', self::textdomain), PHP_VERSION, self::php_version);
		}

		if (version_compare($GLOBALS['wp_version'], self::wp_version, '<')) {
			$error = sprintf(__('Ihre Wordpress-Version %s ist veraltet. Bitte aktualisieren Sie mindestens auf die Wordpress-Version %s.', self::textdomain), $GLOBALS['wp_version'], self::wp_version);
		}

		if (!empty($error)) {
			deactivate_plugins(plugin_basename(__FILE__), false, true);
			wp_die(
				$error,
				__('Fehler bei der Aktivierung des Plugins', self::textdomain),
				array(
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
	public static function add_action_links ( $links ) {
		$mylinks = array(
			'<a href="' . admin_url( 'options-general.php?page=options-fau-cris' ) . '">' . __('Einstellungen', self::textdomain) . '</a>',
		);
		return array_merge( $links, $mylinks );
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
		$options = array(
			'cris_org_nr'		=>	'',
			'cris_pub_order'	=>	array(
										'zeitschriftenartikel',
										'sammelbandbeitraege',
										'uebersetzungen',
										'buecher',
										'herausgeberschaften',
										'konferenzbeitraege',
										'abschlussarbeiten',
										'andere'
									),
			'cris_cache'		=>	'18000',
			'cris_univis'		=> 0,
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

	/**
	 * Options page callback
	 */
	public static function options_fau_cris() {
		?>
		<div class="wrap">
		<?php screen_icon(); ?>
			<h2><?php echo __('Einstellungen', self::textdomain) . ' &rsaquo; CRIS'; ?></h2>
			<form method="post" action="options.php">
				<?php
				settings_fields('fau_cris_options');
				do_settings_sections('fau_cris_options');
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
				array(__CLASS__, 'sanitize') // Sanitize
		);
		// Form Settings 1
		add_settings_section(
				'cris_section', // ID
				__('Einstellungen', self::textdomain), // Title
				'__return_false', // Callback
				'fau_cris_options' // Page
		);
		add_settings_field(
				'cris_org_nr', // ID
				__('CRIS-OrgNr.', self::textdomain), // Title
				array(__CLASS__, 'cris_textbox_callback'), // Callback
				'fau_cris_options', // Page
				'cris_section', // Section
				array (
					'name' => 'cris_org_nr',
				)
		);
		add_settings_field(
				'cris_pub_order',
				__('Reihenfolge der Publikationen', self::textdomain),
				array(__CLASS__, 'cris_textarea_callback'),
				'fau_cris_options',
				'cris_section',
				array (
					'name' => 'cris_pub_order',
					'description' => __('Wenn Sie die Publikationsliste nach Publikationstypen geordnet ausgeben, können Sie hier angeben, in welcher Reihenfolge die Typen aufgelistet werden. Eine Liste aller Typen finden Sie im Hilfemenü unter "Shortcode Publikationen". Ein Eintrag pro Zeile. ', self::textdomain)
				)
		);
		add_settings_field(
				'cris_univis',
				__('Autoren verlinken', self::textdomain),
				array(__CLASS__, 'cris_check_callback'),
				'fau_cris_options',
				'cris_section',
				array (
					'name' => 'cris_univis',
					'description' => __('Sollen die Autoren mit ihrer Personen-Detailansicht im FAU-Person-Plugin verlinkt werden?', self::textdomain)
				)
		);
	}

	/**
	 * Sanitize each setting field as needed
	 */
	public static function sanitize($input) {
		$new_input = array();
        $default_options = self::default_options();
        $new_input['cris_org_nr'] = isset($input['cris_org_nr']) ? absint($input['cris_org_nr']) : 0;
        $new_input['cris_pub_order'] = isset($input['cris_pub_order']) && is_array($input['cris_pub_order']) ? explode("\n", str_replace("\r", "",$input['cris_pub_order'])) : $default_options['cris_pub_order'];
        $new_input['cris_univis'] = isset($input['cris_univis']) ? 1 : 0;
		return $new_input;
	}

	/**
	 * Get the settings option array and print its values
	 */
	// Checkbox
	public static function cris_check_callback($args) {
		$options = self::get_options();
		if (array_key_exists('name', $args)) $name = esc_attr( $args['name'] );
		if (array_key_exists('description', $args)) $description = esc_attr($args['description']);
		?>
		<input name="<?php printf('%s['.$name.']' , self::option_name); ?>" type='checkbox' value='1' <?php if (array_key_exists($name, $options)) { print checked($options[$name], 1, false); } ?> >
		<?php if (isset($description)) { ?>
			<span class="description"><?php echo $description; ?></span>
		<?php }
	}

	// Textbox
	public static function cris_textbox_callback($args) {
		$options = self::get_options();
		if (array_key_exists('name', $args)) $name = esc_attr( $args['name'] );
		if (array_key_exists('description', $args)) $description = esc_attr($args['description']);
		?>
		<input name="<?php printf('%s['.$name.']', self::option_name); ?>" type='text' value="<?php if (array_key_exists($name, $options)) { echo $options[$name]; } ?>" ><br />
		<?php if (isset($description)) { ?>
			<span class="description"><?php echo $description; ?></span>
		<?php }
	}

	// Textarea
	public static function cris_textarea_callback($args) {
		$options = self::get_options();
		if (array_key_exists('name', $args)) $name = esc_attr( $args['name'] );
		if (array_key_exists('description', $args)) $description = esc_attr($args['description']);
		?>
			<textarea name="<?php printf('%s['.$name.']', self::option_name); ?>" cols="30" rows="8"><?php if (array_key_exists($name, $options)) { echo implode ("\n", $options[$name]); } ?></textarea><br />
		<?php if (isset($description)) { ?>
			<span class="description"><?php echo $description; ?></span>
		<?php }
	}

	/**
	 * Add Shortcode
	 */
	public static function cris_shortcode($atts, $content = null) {

		// Attributes
		extract(shortcode_atts(
			array(
				'show' => 'publications',
				'orderby' => '',
				'pubtype' => '',
				'year' => '',
				'start' => '',
				'orgid' => '',
				'persid' => '',
				'publication' => '',
			),
			$atts));

		//var_dump($atts);
		$show = sanitize_text_field($show);
		$orderby = sanitize_text_field($orderby);
		$pubtype = sanitize_text_field($pubtype);
		$year = sanitize_text_field($year);
		$start = sanitize_text_field($start);
		$orgid = sanitize_text_field($orgid);
		$persid = sanitize_text_field($persid);
		$publication =  sanitize_text_field($publication);

		if (isset($publication) && $publication !='') {
			$param1 = 'publication';
			$param2 = $publication;
		} elseif (isset($persid) && $persid !='') {
			$param1 = 'person';
			$param2 = $persid;
		} elseif (isset($orgid) && $orgid !='') {
			$param1 = 'orga';
			$param2 = $orgid;
		} else {
			$param1 = '';
			$param2 = '';
		}

		require_once('class_Publikationsliste.php');
		$liste = new Publikationsliste($param1, $param2);

		if (isset($orderby) && ($orderby == 'type' || $orderby == 'pubtype') && !isset($publication)) {
			$output = $liste->pubNachTyp($year, $start, $pubtype);
		} elseif (isset($orderby) && $orderby == 'year' && !isset($publication)) {
			$output = $liste->pubNachJahr($year, $start, $pubtype);
		} elseif (isset($publication) && $publication != '') {
			$output = $liste->singlePub();
		} else {
			$output = $liste->pubNachJahr($year, $start, $pubtype);
		}
		
		return $output;
	}

	/*
	 * Hilfe-Panel über der Theme-Options-Seite
	 */
	public static function cris_help_menu() {

		$content_cris = array(
			'<p>' . __('Binden Sie Daten aus aus dem FAU-Forschungsportal <strong>CRIS (Currrent Research Information System)</strong> in Ihren Webauftritt ein. Das Plugin ermöglicht außerdem die Integration mit dem FAU-Person-Plugin.', self::textdomain) . '</p>',
			'<p>' . __('Für die Publikationslisten lassen sich über den Shortcode verschiedene Ausgabeformen einstellen. Die Titel sind jeweils mit der Detailansicht der Publikation auf http://cris.fau.de verlinkt.', self::textdomain) . '</p>','<p>' . __('<b>CRIS-OrgNr</b>:<br>Die Nummer der der Organisationseinheit, für die die Publikationen und Personendaten ausgegeben werden. Diese erfahren Sie, wenn Sie in CRIS eingeloggt sind, oder wenn Sie ich Ihre Organisationseinheit auf http://cris.fau.de anzeigen lassen, in der URL: z.B. http://cris.fau.de/converis/publicweb/Organisation/<strong><em>141517</em></strong>.', self::textdomain) . '</p>'
			);

		$content_shortcode_publikationen = array(
			'<h2>[cris]</h2>'
			. '<p>' . __('Bindet eine Liste aller Publikationen Ihrer Organisationseinheit ein. Mögliche Zusatzoptionen:', self::textdomain) . '</p>'
			. '<h3>' . __('Gliederung', self::textdomain) . '</h3>'
			. '<ul><li><b>orderby="year"</b>: '
			. __('Liste nach Jahren absteigend gegliedert (Voreinstellung)', self::textdomain) . '</li>'
			. '<li><b>orderby="pubtype"</b>: '
			. __('Liste nach Publikationstypen gegliedert', self::textdomain) . '</li>'
			. '</ul>'
			. '<h3>' . __('Filter', self::textdomain) . '</h3>'
			. '<ul>'
			. '<li><b>year="2015"</b>: '
			. __('Nur Publikationen aus einem bestimmten Jahr', self::textdomain) . '</li>'
			. '<li><b>start="2000"</b>: '
			. __('Nur Publikationen ab einem bestimmten Jahr', self::textdomain) . '</li>'
			. '<li><b>pubtype="buecher"</b>: '
			. __('Es werden nur Publikationen eines bestimmten Typs angezeigt:', self::textdomain)
			. '<ul style="list-style-type: circle;">'
				. '<li style="margin-bottom: 0;">buecher</li>'
				. '<li style="margin-bottom: 0;">zeitschriftenartikel</li>'
				. '<li style="margin-bottom: 0;">sammelbandbeitraege</li>'
				. '<li style="margin-bottom: 0;">herausgeberschaften</li>'
				. '<li style="margin-bottom: 0;">konferenzbeitraege</li>'
				. '<li style="margin-bottom: 0;">uebersetzungen</li>'
				. '<li style="margin-bottom: 0;">abschlussarbeiten</li>'
				. '<li style="margin-bottom: 0;">andere</li>'
				. '</ul>'
			. '</li>'
			. '<li><b>publication="12345678"</b>: '
			. __('Nur eine einzelne Publikation (hier die CRIS-ID der Publikation angeben)', self::textdomain)
			. '</ul>'
			. '<h3>' . __('ID überschreiben', self::textdomain) . '</h3>'
			. '<p>Die in den Einstellungen festgelegte CRIS-ID kann überschrieben werden, entweder durch die ID einer anderen Organisationseinheit, oder durch die ID einer einzelnen Person:</p>'
			. '<ul>'
			. '<li><b>orgID="123456"</b> '
			. __('für eine von den Einstellungen abweichende Organisations-ID', self::textdomain) . '</li>'
			. '<li><b>persID="123456"</b> '
			. __('für die Publikationsliste einer konkreten Person', self::textdomain) . '</li>'
			. '</ul>'
			. '<h3>' .__('Beispiele', self::textdomain) . '</h3>'
			. '<ul>'
			. '<li><code>[cris pubtype="buecher"]</code> => '. __('Alle Bücher', self::textdomain) . '</li>'
			. '<li><code>[cris year="2015"]</code> => '. __('Alle Publikationen aus dem Jahr 2015', self::textdomain) . '</li>'
			. '<li><code>[cris persID="123456" year="2000" orderby="pubtype"]</code> => '. __('Alle Publikationen der Person mit der CRIS-ID 123456 aus dem Jahr 2000, nach Publikationstypen gegliedert', self::textdomain) . '</li>'

		);

		$content_fauperson = array(
			'<p>' . __('Wenn Sie das <strong>FAU-Person</strong>-Plugin verwenden, können Autoren mit ihrer FAU-Person-Kontaktseite verlinkt werden.', self::textdomain) . '</p>',
			'<p>' . __('Wenn diese Option in den Einstellungen des CRIS-Plugins aktiviert ist, überprüft das Plugin selbstständig, welche Personen vorhanden sind und setzt die entsprechenden Links.', self::textdomain) . '</p>',
			'<p>' . __('', self::textdomain) . '</p>'
			);

		$helptexts = array(
			array(
				'id' => 'uebersicht',
				'title' => __('Übersicht', self::textdomain),
				'content' => implode(PHP_EOL, $content_cris),
			),
			array(
				'id' => 'publikationen',
				'title' => __('Shortcodes', self::textdomain),
				'content' => implode(PHP_EOL, $content_shortcode_publikationen),
			),
			array(
				'id' => 'person',
				'title' => __('Integration "FAU Person"', self::textdomain),
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
