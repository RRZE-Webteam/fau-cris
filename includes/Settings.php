<?php

namespace FAU\CRIS;

defined('ABSPATH') || exit;

require_once 'config/config.php';

use FAU\CRIS\Tools;
use function FAU\CRIS\getOptionName;
use function FAU\CRIS\getMenuSettings;
use function FAU\CRIS\getHelpTab;
use function FAU\CRIS\getSections;
use function FAU\CRIS\getFields;

/**
 * Settings-Klasse
 */
class Settings
{
    /**
     * Der vollständige Pfad- und Dateiname der Plugin-Datei.
     * @var string
     */
    protected $pluginFile;

    /**
     * Optionsname
     * @var string
     */
    protected $optionName;

    /**
     * Einstellungsoptionen
     * @var array
     */
    protected $options;

    /**
     * Settings-Menü
     * @var array
     */
    protected $settingsMenu;

    /**
     * Settings-Bereiche
     * @var array
     */
    protected $settingsSections;

    /**
     * Settings-Felder
     * @var array
     */
    protected $settingsFields;

    /**
     * Alle Registerkarte
     * @var array
     */
    protected $allTabs = [];

    /**
     * Standard-Registerkarte
     * @var string
     */
    protected $defaultTab = '';

    /**
     * Aktuelle Registerkarte
     * @var string
     */
    protected $currentTab = '';

    /**
     * Variablen Werte zuweisen.
     * @param string $pluginFile [description]
     */
    public function __construct($pluginFile)
    {
        $this->pluginFile = $pluginFile;
    }

    /**
     * Er wird ausgeführt, sobald die Klasse instanziiert wird.
     * @return void
     */
    public function onLoaded()
    {
        $this->setMenu();
        $this->setSections();
        $this->setFields();
        $this->setTabs();

        $this->optionName = $this->optionName = getOptionName();
        $this->options = $this->getOptions();

        add_action('admin_init', [$this, 'adminInit']);
        add_action('admin_menu', [$this, 'adminMenu']);
        add_action('admin_enqueue_scripts', [$this, 'adminEnqueueScripts']);
    }

    protected function setMenu()
    {
        $this->settingsMenu = getmenuSettings();
    }

    /**
     * Einstellungsbereiche einstellen.
     */
    protected function setSections()
    {
        $this->settingsSections = getSections();
    }

    /**
     * Einen einzelnen Einstellungsbereich hinzufügen.
     * @param array   $section
     */
    protected function addSection($section)
    {
        $this->settingsSections[] = $section;
    }

    /**
     * Einstellungsfelder einstellen.
     */
    protected function setFields()
    {
        $this->settingsFields = getFields();
    }

    /**
     * Ein einzelnes Einstellungsfeld hinzufügen.
     * @param [type] $section [description]
     * @param [type] $field   [description]
     */
    protected function addField($section, $field)
    {
        $defaults = array(
            'name'  => '',
            'label' => '',
            'desc'  => '',
            'type'  => 'text'
        );

        $arg = wp_parse_args($field, $defaults);
        $this->settingsFields[$section][] = $arg;
    }

    /**
     * Gibt die Standardeinstellungen zurück.
     * @return array
     */
    protected function defaultOptions()
    {
        $options = [];

	    foreach ($this->settingsFields as $section => $field) {
            foreach ($field as $option) {
                $name = $option['name'];
                $default = isset($option['default']) ? $option['default'] : '';
                $options = array_merge($options, [$section . '_' . $name => $default]);
            }
        }

        return $options;
    }

    /**
     * Gibt die Einstellungen zurück.
     * @return array
     */
    public function getOptions()
    {
        $defaults = self::defaultOptions();

        $options = (array) get_option($this->optionName);
        $options = wp_parse_args($options, $defaults);
        $options = array_intersect_key($options, $defaults);

        return $options;
    }

    /**
     * Gibt den Wert eines Einstellungsfelds zurück.
     * @param string  $name  settings field name
     * @param string  $section the section name this field belongs to
     * @param string  $default default text if it's not found
     * @return string
     */
    public function getOption($section, $name, $default = '')
    {
        $option = $section . '_' . $name;

        if (isset($this->options[$option])) {
            return $this->options[$option];
        }

        return $default;
    }

    /**
     * Sanitize-Callback für die Optionen.
     * @return mixed
     */
    public function sanitizeOptions($options)
    {
        if (!$options) {
            return $options;
        }

        foreach ($options as $key => $value) {
            $this->options[$key] = $value;
            $sanitizeCallback = $this->getSanitizeCallback($key);
            if ($sanitizeCallback) {
                $this->options[$key] = call_user_func($sanitizeCallback, $value);
            }
        }

        return $this->options;
    }

    /**
     * Gibt die Sanitize-Callback-Funktion für die angegebene Option-Key.
     * @param string $key Option-Key
     * @return mixed string oder (bool) false
     */
    protected function getSanitizeCallback($key = '')
    {
        if (empty($key)) {
            return false;
        }

        foreach ($this->settingsFields as $section => $options) {
            foreach ($options as $option) {
                if ($section . '_' . $option['name'] != $key) {
                    continue;
                }

                return isset($option['sanitize_callback']) && is_callable($option['sanitize_callback']) ? $option['sanitize_callback'] : false;
            }
        }

        return false;
    }

    /**
     * Einstellungsbereiche als Registerkarte anzeigen.
     * Zeigt alle Beschriftungen der Einstellungsbereiche als Registerkarte an.
     */
    public function showTabs()
    {
        $html = '<h1>' . $this->settingsMenu['title'] . '</h1>' . PHP_EOL;

        if (count($this->settingsSections) < 2) {
            return;
        }

        $html .= '<h2 class="nav-tab-wrapper wp-clearfix">';

        foreach ($this->settingsSections as $section) {
            $class = $section['id'] == $this->currentTab ? 'nav-tab-active' : $this->defaultTab;
            $html .= sprintf(
                '<a href="?page=%4$s&current-tab=%1$s" class="nav-tab %3$s" id="%1$s-tab">%2$s</a>',
                esc_attr($section['id']),
                $section['title'],
                esc_attr($class),
                $this->settingsMenu['menu_slug']
            );
        }

        $html .= '</h2>' . PHP_EOL;

        echo $html;
    }

    /**
     * Anzeigen der Einstellungsbereiche.
     * Zeigt für jeden Einstellungsbereich das entsprechende Formular an.
     */
    public function showSections()
    {
        foreach ($this->settingsSections as $section) {
            if ($section['id'] != $this->currentTab) {
                continue;
            } ?>
            <div id="<?php echo $section['id']; ?>">
                <form method="post" action="options.php">
                    <?php settings_fields($section['id']); ?>
                    <?php do_settings_sections($section['id']); ?>
                    <?php submit_button(); ?>
                </form>
            </div>
        <?php
        }
    }

    /**
     * Optionen Seitenausgabe
     */
    public function pageOutput()
    {
        echo '<div class="wrap">', PHP_EOL;
        $this->showTabs();
        $this->showSections();
        echo '</div>', PHP_EOL;
    }

    /**
     * Erstellt die Kontexthilfe der Einstellungsseite.
     */
    public function adminHelpTab()
    {
        $screen = get_current_screen();

        if (!method_exists($screen, 'add_help_tab') || $screen->id != $this->optionsPage) {
            return;
        }

        $helpTab = getHelpTab();

        if (empty($helpTab)) {
            return;
        }

        foreach ($helpTab as $help) {
            $screen->add_help_tab(
                [
                    'id' => $help['id'],
                    'title' => $help['title'],
                    'content' => implode(PHP_EOL, $help['content'])
                ]
            );
            $screen->set_help_sidebar($help['sidebar']);
        }
    }

    /**
     * Initialisierung und Registrierung der Bereiche und Felder.
     */
    public function adminInit()
    {
        // Hinzufügen von Einstellungsbereichen
        foreach ($this->settingsSections as $section) {
            if (isset($section['desc']) && !empty($section['desc'])) {
                $section['desc'] = '<div class="inside">' . $section['desc'] . '</div>';
                $callback = function () use ($section) {
                    echo str_replace('"', '\"', $section['desc']);
                };
            } elseif (isset($section['callback'])) {
                $callback = $section['callback'];
            } else {
                $callback = null;
            }

            add_settings_section($section['id'], $section['title'], $callback, $section['id']);
        }

        // Hinzufügen von Einstellungsfelder
        foreach ($this->settingsFields as $section => $field) {
            foreach ($field as $option) {
                $name     = $option['name'];
                $type     = isset( $option['type'] ) ? $option['type'] : 'text';
                $label    = isset( $option['label'] ) ? $option['label'] : '';
                $callback = isset( $option['callback'] ) ? $option['callback'] : [
                    $this,
                    'callback' . ucfirst( $type )
                ];

                $args = [
                    'id'                => $name,
                    'class'             => isset( $option['class'] ) ? $option['class'] : $name,
                    'label_for'         => "{$section}[{$name}]",
                    'desc'              => isset( $option['desc'] ) ? $option['desc'] : '',
                    'name'              => $label,
                    'section'           => $section,
                    'size'              => isset( $option['size'] ) ? $option['size'] : null,
                    'options'           => isset( $option['options'] ) ? $option['options'] : '',
                    'default'           => isset( $option['default'] ) ? $option['default'] : '',
                    'sanitize_callback' => isset( $option['sanitize_callback'] ) ? $option['sanitize_callback'] : '',
                    'type'              => $type,
                    'placeholder'       => isset( $option['placeholder'] ) ? $option['placeholder'] : '',
                    'min'               => isset( $option['min'] ) ? $option['min'] : '',
                    'max'               => isset( $option['max'] ) ? $option['max'] : '',
                    'step'              => isset( $option['step'] ) ? $option['step'] : '',
                ];

                add_settings_field( "{$section}[{$name}]", $label, $callback, $section, $section, $args );

                if ( in_array( $type, [ 'color', 'file' ] ) ) {
                    add_action( 'admin_enqueue_scripts', [ $this, $type . 'EnqueueScripts' ] );
                }
            }
        }

        // Registrieren der Einstellungen
        foreach ($this->settingsSections as $section) {
            register_setting($section['id'], $this->optionName, [$this, 'sanitizeOptions']);
        }
    }

    /**
     * Hinzufügen der Optionen-Seite
     * @return void
     */
    public function adminMenu()
    {
        $this->optionsPage = add_options_page(
            $this->settingsMenu['page_title'],
            $this->settingsMenu['menu_title'],
            $this->settingsMenu['capability'],
            $this->settingsMenu['menu_slug'],
            [$this, 'pageOutput']
        );

        add_action('load-' . $this->optionsPage, [$this, 'adminHelpTab']);
    }

    /**
     * Registerkarten einstellen
     */
    protected function setTabs()
    {
        foreach ($this->settingsSections as $key => $val) {
            if ($key == 0) {
                $this->defaultTab = $val['id'];
            }
            $this->allTabs[] = $val['id'];
        }

        $this->currentTab = array_key_exists('current-tab', $_GET) && in_array($_GET['current-tab'], $this->allTabs) ? $_GET['current-tab'] : $this->defaultTab;
    }

    /**
     * Enqueue Skripte und Style
     * @return void
     */
    public function adminEnqueueScripts()
    {
        wp_register_script('wp-color-picker-settings', plugins_url('assets/js/settings/wp-color-picker.min.js', plugin_basename($this->pluginFile)));
        wp_register_script('wp-media-settings', plugins_url('assets/js/settings/wp-media.min.js', plugin_basename($this->pluginFile)));
    }

    /**
     * Enqueue WP-Color-Picker-Skripte.
     * @return [type] [description]
     */
    public function colorEnqueueScripts()
    {
        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script('wp-color-picker');
        wp_enqueue_script('wp-color-picker-settings');
        wp_enqueue_script('jquery');
    }

    /**
     * Enqueue WP-Media-Skripte.
     * @return [type] [description]
     */
    public function fileEnqueueScripts()
    {
        wp_enqueue_media();
        wp_enqueue_script('wp-media-settings');
        wp_enqueue_script('jquery');
    }

    /**
     * Gibt die Feldbeschreibung des Einstellungsfelds zurück.
     * @param array   $args Argumente des Einstellungsfelds
     */
    public function getFieldDescription($args)
    {
        if (! empty($args['desc'])) {
            $desc = sprintf('<p class="description">%s</p>', $args['desc']);
        } else {
            $desc = '';
        }

        return $desc;
    }

    /**
     * Zeigt ein Textfeld für ein Einstellungsfeld an.
     * @param array   $args Argumente des Einstellungsfelds
     */
    public function callbackText($args)
    {
        $value = esc_attr($this->getOption($args['section'], $args['id'], $args['default']));
        $size = isset($args['size']) && !is_null($args['size']) ? $args['size'] : 'regular';
        $type = isset($args['type']) ? $args['type'] : 'text';
        $placeholder = empty($args['placeholder']) ? '' : ' placeholder="' . $args['placeholder'] . '"';

        $html = sprintf(
            '<input type="%1$s" class="%2$s-text" id="%4$s-%5$s" name="%3$s[%4$s_%5$s]" value="%6$s"%7$s>',
            $type,
            $size,
            $this->optionName,
            $args['section'],
            $args['id'],
            $value,
            $placeholder
        );
        $html .= $this->getFieldDescription($args);

        echo $html;
    }

    /**
     * Zeigt ein Zahlenfeld für ein Einstellungsfeld an.
     * @param array   $args Argumente des Einstellungsfelds
     */
    public function callbackNumber($args)
    {
        $value = esc_attr($this->getOption($args['section'], $args['id'], $args['default']));
        $size = isset($args['size']) && !is_null($args['size']) ? $args['size'] : 'regular';
        $type = isset($args['type']) ? $args['type'] : 'number';
        $placeholder = empty($args['placeholder']) ? '' : ' placeholder="' . $args['placeholder'] . '"';
        $min = ($args['min'] == '') ? '' : ' min="' . $args['min'] . '"';
        $max = ($args['max'] == '') ? '' : ' max="' . $args['max'] . '"';
        $step = ($args['step'] == '') ? '' : ' step="' . $args['step'] . '"';

        $html = sprintf(
            '<input type="%1$s" class="%2$s-number" id="%4$s-%5$s" name="%3$s[%4$s_%5$s]" value="%6$s"%7$s%8$s%9$s%10$s>',
            $type,
            $size,
            $this->optionName,
            $args['section'],
            $args['id'],
            $value,
            $placeholder,
            $min,
            $max,
            $step
        );
        $html .= $this->getFieldDescription($args);

        echo $html;
    }

    /**
     * Zeigt ein Kontrollkästchen (Checkbox) für ein Einstellungsfeld an.
     * @param array   $args Argumente des Einstellungsfelds
     */
    public function callbackCheckbox($args)
    {
        $value = esc_attr($this->getOption($args['section'], $args['id'], $args['default']));

        $html = '<fieldset>';
        $html .= sprintf(
            '<label for="%1$s-%2$s">',
            $args['section'],
            $args['id']
        );
        $html .= sprintf(
            '<input type="hidden" name="%1$s[%2$s_%3$s]" value="off">',
            $this->optionName,
            $args['section'],
            $args['id']
        );
        $html .= sprintf(
            '<input type="checkbox" class="checkbox" id="%2$s-%3$s" name="%1$s[%2$s_%3$s]" value="on" %4$s>',
            $this->optionName,
            $args['section'],
            $args['id'],
            checked($value, 'on', false)
        );
        $html .= sprintf(
            '%1$s</label>',
            $args['desc']
        );
        $html .= '</fieldset>';

        echo $html;
    }

    /**
     * Zeigt ein Multicheckbox für ein Einstellungsfeld an.
     * @param array   $args Argumente des Einstellungsfelds
     */
    public function callbackMulticheck($args)
    {
        $value = $this->getOption($args['section'], $args['id'], $args['default']);
        $html = '<fieldset>';
        $html .= sprintf(
            '<input type="hidden" name="%1$s[%2$s_%3$s]" value="">',
            $this->optionName,
            $args['section'],
            $args['id']
        );
        foreach ($args['options'] as $key => $label) {
            $checked = isset($value[$key]) ? $value[$key] : '0';
            $html .= sprintf(
                '<label for="%1$s-%2$s-%3$s">',
                $args['section'],
                $args['id'],
                $key
            );
            $html .= sprintf(
                '<input type="checkbox" class="checkbox" id="%2$s-%3$s-%4$s" name="%1$s[%2$s_%3$s][%4$s]" value="%4$s" %5$s>',
                $this->optionName,
                $args['section'],
                $args['id'],
                $key,
                checked($checked, $key, false)
            );
            $html .= sprintf('%1$s</label><br>', $label);
        }

        $html .= $this->getFieldDescription($args);
        $html .= '</fieldset>';

        echo $html;
    }

    /**
     * Zeigt einen Auswahlknopf (Radio-Button) für ein Einstellungsfeld an.
     * @param array   $args Argumente des Einstellungsfelds
     */
    public function callbackRadio($args)
    {
        $value = $this->getOption($args['section'], $args['id'], $args['default']);
        $html  = '<fieldset>';

        foreach ($args['options'] as $key => $label) {
            $html .= sprintf(
                '<label for="%1$s-%2$s-%3$s">',
                $args['section'],
                $args['id'],
                $key
            );
            $html .= sprintf(
                '<input type="radio" class="radio" id="%2$s-%3$s-%4$s" name="%1$s[%2$s_%3$s]" value="%4$s" %5$s>',
                $this->optionName,
                $args['section'],
                $args['id'],
                $key,
                checked($value, $key, false)
            );
            $html .= sprintf(
                '%1$s</label><br>',
                $label
            );
        }

        $html .= $this->getFieldDescription($args);
        $html .= '</fieldset>';

        echo $html;
    }

    /**
     * Zeigt eine Auswahlliste (Selectbox) für ein Einstellungsfeld an.
     * @param array   $args Argumente des Einstellungsfelds
     */
    public function callbackSelect($args)
    {
        $value = esc_attr($this->getOption($args['section'], $args['id'], $args['default']));
        $size  = isset($args['size']) && !is_null($args['size']) ? $args['size'] : 'regular';
        $html  = sprintf(
            '<select class="%1$s" id="%3$s-%4$s" name="%2$s[%3$s_%4$s]">',
            $size,
            $this->optionName,
            $args['section'],
            $args['id']
        );

        foreach ($args['options'] as $key => $label) {
            $html .= sprintf(
                '<option value="%s"%s>%s</option>',
                $key,
                selected($value, $key, false),
                $label
            );
        }

        $html .= sprintf('</select>');
        $html .= $this->getFieldDescription($args);

        echo $html;
    }

    /**
     * Zeigt ein Textfeld für ein Einstellungsfeld an.
     * @param array   $args Argumente des Einstellungsfelds
     */
    public function callbackTextarea($args)
    {
        $value = esc_textarea($this->getOption($args['section'], $args['id'], $args['default']));
        $size = isset($args['size']) && !is_null($args['size']) ? $args['size'] : 'regular';
        $placeholder = empty($args['placeholder']) ? '' : ' placeholder="' . $args['placeholder'] . '"';

        $html = sprintf(
            '<textarea rows="5" cols="55" class="%1$s-text" id="%3$s-%4$s" name="%2$s[%3$s_%4$s]"%5$s>%6$s</textarea>',
            $size,
            $this->optionName,
            $args['section'],
            $args['id'],
            $placeholder,
            $value
        );
        $html .= $this->getFieldDescription($args);

        echo $html;
    }

    /**
     * Zeigt ein Rich-Text-Textfeld (WP-Editor) für ein Einstellungsfeld an.
     * @param array   $args Argumente des Einstellungsfelds
     */
    public function callbackWysiwyg($args)
    {
        $value = $this->getOption($args['section'], $args['id'], $args['default']);
        $size = isset($args['size']) && !is_null($args['size']) ? $args['size'] : '500px';

        echo '<div style="max-width: ' . $size . ';">';

        $editor_settings = [
            'teeny' => true,
            'textarea_name' => sprintf('%1$s[%2$s_%3$s]', $this->optionName, $args['section'], $args['id']),
            'textarea_rows' => 10
        ];

        if (isset($args['options']) && is_array($args['options'])) {
            $editor_settings = array_merge($editor_settings, $args['options']);
        }

        wp_editor($value, $args['section'] . '-' . $args['id'], $editor_settings);

        echo '</div>';

        echo $this->getFieldDescription($args);
    }

    /**
     * Zeigt ein Datei-Upload-Feld für ein Einstellungsfeld an.
     * @param array   $args Argumente des Einstellungsfelds
     */
    public function callbackFile($args)
    {
        $value = esc_attr($this->getOption($args['section'], $args['id'], $args['default']));
        $size = isset($args['size']) && !is_null($args['size']) ? $args['size'] : 'regular';
        $id = $args['section']  . '[' . $args['id'] . ']';
        $label = isset($args['options']['button_label']) ? $args['options']['button_label'] : __('Choose File');

        $html = sprintf(
            '<input type="text" class="%1$s-text settings-media-url" id="%3$s-%4$s" name="%2$s[%3$s_%4$s]" value="%5$s"/>',
            $size,
            $this->optionName,
            $args['section'],
            $args['id'],
            $value
        );
        $html .= '<input type="button" class="button settings-media-browse" value="' . $label . '">';
        $html .= $this->getFieldDescription($args);

        echo $html;
    }

    /**
     * Zeigt ein Passwortfeld für ein Einstellungsfeld an.
     * @param array   $args Argumente des Einstellungsfelds
     */
    public function callbackPassword($args)
    {
        $value = esc_attr($this->getOption($args['section'], $args['id'], $args['default']));
        $size  = isset($args['size']) && !is_null($args['size']) ? $args['size'] : 'regular';

        $html = sprintf(
            '<input type="password" class="%1$s-text" id="%3$s-%4$s" name="%2$s[%3$s_%4$s]" value="%5$s">',
            $size,
            $this->optionName,
            $args['section'],
            $args['id'],
            $value
        );
        $html .= $this->getFieldDescription($args);

        echo $html;
    }

    /**
     * Zeigt ein Farbauswahlfeld (WP-Color-Picker) für ein Einstellungsfeld an.
     * @param array   $args Argumente des Einstellungsfelds
     */
    public function callbackColor($args)
    {
        $value = esc_attr($this->getOption($args['section'], $args['id'], $args['default']));
        $size = isset($args['size']) && !is_null($args['size']) ? $args['size'] : 'regular';

        $html = sprintf(
            '<input type="text" class="%1$s-text wp-color-picker-field" id="%3$s-%4$s" name="%2$s[%3$s_%4$s]" value="%5$s" data-default-color="%6$s">',
            $size,
            $this->optionName,
            $args['section'],
            $args['id'],
            $value,
            $args['default']
        );
        $html .= $this->getFieldDescription($args);

        echo $html;
    }
}
