<?php

namespace FAU\CRIS;

defined('ABSPATH') || exit;

include_once ("tools.php");

/**
 * Shortcode
 */
class Shortcode
{
    /**
     * Der vollständige Pfad- und Dateiname der Plugin-Datei.
     * @var string
     */
    protected $pluginFile;

	/**
	 * Settings-Objekt
	 * @var object
	 */
	protected $settings;

	/**
     * Variablen Werte zuweisen.
     * @param string $pluginFile Pfad- und Dateiname der Plugin-Datei
     */
    public function __construct($pluginFile, $settings)
    {
        $this->pluginFile = $pluginFile;
	    $this->settings = $settings;
	    $this->options = $this->settings->getOptions();
    }

    /**
     * Wird ausgeführt, sobald WP, alle Plugins und das Theme
     * vollständig geladen und instanziiert sind.
     * @return void
     */
    public function onLoaded()
    {
        add_action('wp_enqueue_scripts', [$this, 'enqueueScripts']);
        add_shortcode('cris', [$this, 'crisShortcode'], 10, 2);
    }

    /**
     * Enqueue der Skripte.
     */
    public function enqueueScripts()
    {
        wp_register_style('basis-shortcode', plugins_url('assets/css/shortcodes/basis-shortcode.min.css', plugin_basename($this->pluginFile)));
        wp_register_script('basis-shortcode', plugins_url('assets/js/shortcodes/basis-shortcode.min.js', plugin_basename($this->pluginFile)));
    }

    /**
     * Generieren Sie die Shortcode-Ausgabe
     * @param  array   $atts Shortcode-Attribute
     * @param  string  $content Beiliegender Inhalt
     * @return string Gib den Inhalt zurück
     */
    public function crisShortcode($atts, $content = '', $tag)
    {
	    wp_enqueue_style('basis-shortcode');
	    wp_enqueue_script('basis-shortcode');
	    $parameter = self::crisShortcodeParameter($atts, $content = null, $tag);

	    $output = '';
	    if (isset($parameter['show']) && $parameter['show'] == 'publications') {
		    $output = new Entities\Publications($parameter);
		    /*if ($parameter['publication'] != '' && $parameter['order1'] == '') {
			    return $output->singlePub($parameter['quotation']);
		    }
		    if ($parameter['order1'] == '' && ($parameter['limit'] != '' || $parameter['sortby'] != '' || $parameter['notable'] != '')) {
			    return $output->pubListe($parameter);
		    }
		    if (strpos($parameter['order1'], 'type') !== false) {
			    return $output->pubNachTyp($parameter, $field = '');
		    }*/
		    return $output->publicationsByYear();
	    }




    }

	private function crisShortcodeParameter($atts, $content = '', $tag) {
		global $post;

		$shortcode_atts = shortcode_atts([
			'show' => 'publications',
			'orderby' => '',
			'year' => '',
			'start' => '',
			'end' => '',
			'organisation' => $this->settings->getOption('cris_general','cris_org_nr', ''),
			'orgid' => $this->settings->getOption('cris_general','cris_org_nr', ''),
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
			'display' => 'list',
			'project' => '',
			'hide' => '',
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
			'publications_limit' => $this->settings->getOption('cris_layout','cris_fields_num_pub', '5'),
			'name_order_plugin' => $this->settings->getOption('cris_layout','cris_name_order_plugin', 'firstname-lastname'),
			'notable' => '',
			'publications_year' => '',
			'publications_start' => '',
			'publications_type' => '',
			'publications_subtype' => '',
			'publications_fau' => '',
			'publications_peerreviewed' => '',
			'publications_orderby' => '',
			'publications_notable' => '',
			'image_align' => 'left',
			'accordion_title' => '#name# (#year#)',
			'accordion_color' => '',
			'display_language' => getPageLanguage($post->ID),
		], $atts, $tag);
		$shortcode_atts = array_map('sanitize_text_field', $shortcode_atts);

		$shortcode_atts['sc_type'] = $tag;

		return $shortcode_atts;
    }
}
