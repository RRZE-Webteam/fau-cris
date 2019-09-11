<?php

namespace FAU\CRIS;

use FAU\CRIS\Settings;
use FAU\CRIS\Shortcode;

defined('ABSPATH') || exit;

/**
 * Hauptklasse (Main)
 */
class Main
{
    /**
     * Der vollständige Pfad- und Dateiname der Plugin-Datei.
     * @var string
     */
    protected $pluginFile;

    /**
     * Variablen Werte zuweisen.
     * @param string $pluginFile Pfad- und Dateiname der Plugin-Datei
     */
    public function __construct($pluginFile)
    {
        $this->pluginFile = $pluginFile;
    }

    /**
     * Wird ausgeführt, sobald WP, alle Plugins und das Theme
     * vollständig geladen und instanziiert sind.
     */
    public function onLoaded()
    {
        add_action('wp_enqueue_scripts', [$this, 'enqueueScripts']);

        // Settings-Klasse wird instanziiert.
        $settings = new Settings($this->pluginFile);
        $settings->onLoaded();

        // Shortcode-Klasse wird instanziiert.
        $shortcode = new Shortcode($this->pluginFile, $settings);
        $shortcode->onLoaded();
    }

    /**
     * Enqueue der globale Skripte.
     */
    public function enqueueScripts()
    {
        wp_register_style('fau-cris', plugins_url('assets/css/fau-cris.min.css', plugin_basename($this->pluginFile)));
    }
}
