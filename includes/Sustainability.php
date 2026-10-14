<?php
namespace RRZE\Cris;
defined('ABSPATH') || exit;

use RRZE\Cris\Tools;
use RRZE\Cris\Webservice;
use RRZE\Cris\Filter;

/*
 * Nachhaltigkeit / UN Sustainable Development Goals (UNSDG).
 *
 * Shortcode usage: [cris show=sdg sdg=361590914]
 *
 * Renders a single UN SDG (code, language-specific long name and description)
 * plus the GreenOffice-approved persons related to it.
 */
class Sustainability
{

    private array $options;
    public $output;
    public $cms;
    public $id;
    public $page_lang;
    public $sc_lang;
    public $langdiv_open;
    public $langdiv_close;
    public $name_order_plugin;
    public \WP_Error|null $error = null;
    public ?\WP_Error $fetchError = null;

    public function __construct($id = '', $page_lang = 'de', $sc_lang = 'de')
    {
        if (isset($_SERVER['PHP_SELF']) && strpos(sanitize_text_field(wp_unslash($_SERVER['PHP_SELF'])), "vkdaten/tools/")) {
            $this->cms = 'wbk';
            $this->options = CRIS::ladeConf();
        } else {
            $this->cms = 'wp';
            $this->options = (array) FAU_CRIS::get_options();
        }

        if ($id == '') {
            $this->error = new \WP_Error(
                'cris-sdgid-error',
                __('Bitte geben Sie die CRIS-ID des Nachhaltigkeitsziels an.', 'fau-cris')
            );
        }

        $this->id = $id;
        $this->page_lang = $page_lang;
        $this->sc_lang = $sc_lang;
        $this->name_order_plugin = $this->options['cris_name_order_plugin'] ?? 'firstname-lastname';
        // The plugin-slug class "fau-cris" is the CSS namespace; "cris" is kept
        // alongside it because existing page and theme styles target that class.
        $this->langdiv_open = '<div class="fau-cris cris">';
        $this->langdiv_close = '</div>';
        if ($sc_lang != $this->page_lang) {
            $this->langdiv_open = '<div class="fau-cris cris" lang="' . esc_attr($sc_lang) . '">';
        }
    }

    /*
     * Ausgabe eines einzelnen Nachhaltigkeitsziels.
     */
    public function singleSDG($hide = '', $hstart = 2)
    {
        $ws = new CRIS_sdgs();
        try {
            $sdgArray = $ws->by_id($this->id);
        } catch (\Throwable $ex) {
            // The exception must not silently produce empty output: log it and
            // fall through to the regular error message below, so that editors
            // can see what happened.
            do_action(
                'rrze.log.error',
                'Plugin: {plugin} Exception: {exception}',
                [
                    'plugin' => 'fau-cris',
                    'exception' => $ex->getMessage(),
                    'method' => 'Sustainability::singleSDG',
                    'sdg' => $this->id
                ]
            );
            $this->fetchError = new \WP_Error(
                'cris-sdg-fetch-failed',
                __('CRIS-Anfrage fehlgeschlagen.', 'fau-cris')
            );
            $sdgArray = array();
        }

        // Propagate fetch error for renderer message branching.
        if (is_wp_error($sdgArray)) {
            $this->fetchError = $sdgArray;
            $sdgArray = array();
        } elseif ($ws->lastError instanceof \WP_Error) {
            $this->fetchError = $ws->lastError;
        }

        if (!count($sdgArray)) {
            $output = Tools::no_data_message($this->fetchError, __('Es wurden leider keine Informationen gefunden.', 'fau-cris'));
            return $this->langdiv_open . $output . $this->langdiv_close;
        }

        $output = $this->make_single($sdgArray, $hide, $hstart);

        return $this->langdiv_open . $output . $this->langdiv_close;
    }

    /* =========================================================================
     * Private Functions
      ======================================================================== */

    private function make_single($sdgs, $hide = '', $hstart = 2): string
    {
        $hidden = is_array($hide) ? array_map('trim', $hide) : array_map('trim', explode(',', (string) $hide));
        // Heading levels follow the page structure: hstart sets the main
        // heading, sub-headings sit one level below it.
        $hTitle = min(max(absint($hstart) ?: 2, 1), 6);
        $hSub = min($hTitle + 1, 6);
        $output = "<div class=\"cris-sdg\">";

        foreach ($sdgs as $sdg) {
            $sdg = (array) $sdg;
            foreach ($sdg['attributes'] as $attribut => $v) {
                $sdg[$attribut] = $v;
            }
            unset($sdg['attributes']);

            $code = isset($sdg['code']) ? trim($sdg['code']) : '';
            $name = ($this->page_lang == 'en' && !empty($sdg['name_en'])) ? $sdg['name_en'] : ($sdg['name'] ?? '');
            $namelong = ($this->page_lang == 'en' && !empty($sdg['namelong_en'])) ? $sdg['namelong_en'] : ($sdg['namelong'] ?? '');
            $description = ($this->page_lang == 'en' && !empty($sdg['description_en'])) ? $sdg['description_en'] : ($sdg['description'] ?? '');

            // Code und Kurzname (zweisprachig) in einer Überschrift,
            // z. B. "SDG 3 – Gesundheit und Wohlergehen".
            if (!in_array('title', $hidden, true) && ($code !== '' || $name !== '')) {
                $heading = esc_html($code);
                if ($name !== '') {
                    $heading .= ($code !== '' ? ' &ndash; ' : '') . esc_html($name);
                }
                $output .= "<h{$hTitle} class=\"cris-sdg-title\">" . $heading . "</h{$hTitle}>";
            }

            // Langname als (eingeklapptes) Akkordeon, Beschreibung als Inhalt.
            if (!in_array('description', $hidden, true) && ($namelong !== '' || $description !== '')) {
                $body = $description !== '' ? $this->render_description($description) : '';
                $title = $namelong !== '' ? $namelong : $code;

                if (shortcode_exists('collapsibles') && $title !== '') {
                    $collapse = do_shortcode('[collapse title="' . esc_attr($title) . '"]' . $body . '[/collapse]');
                    $output .= do_shortcode('[collapsibles]' . $collapse . '[/collapsibles]');
                } else {
                    if ($namelong !== '') {
                        $output .= "<h{$hSub} class=\"cris-sdg-namelong\">" . esc_html($namelong) . "</h{$hSub}>";
                    }
                    $output .= $body;
                }
            }

            if (!in_array('persons', $hidden, true)) {
                $output .= $this->make_persons($sdg['ID'], $hSub);
            }
        }

        $output .= "</div>";
        return $output;
    }

    /*
     * Formatiert die (lange) CRIS-Beschreibung: Einleitungszeile als Absatz,
     * die einzelnen Unterziele ("3.1 ...") als saubere Listenpunkte statt eines
     * Textblocks mit doppelten Zeilenumbrüchen.
     */
    private function render_description($text): string
    {
        $text = str_replace(array("\r\n", "\r"), "\n", (string) $text);
        $lines = array_values(array_filter(array_map('trim', explode("\n", $text)), static function ($line) {
            return $line !== '';
        }));
        if (!count($lines)) {
            return '';
        }

        $intro = array();
        $items = array();
        foreach ($lines as $line) {
            // Unterziele beginnen mit einer Nummer wie "3.1", "3.a" oder "12.b".
            if (preg_match('/^\d+\.[0-9a-z]+(\s|$)/i', $line)) {
                $items[] = $line;
            } elseif (count($items)) {
                // Fortsetzung des vorigen Unterziels an dieses anhängen.
                $items[count($items) - 1] .= ' ' . $line;
            } else {
                $intro[] = $line;
            }
        }

        $output = "<div class=\"cris-sdg-description\">";
        foreach ($intro as $paragraph) {
            $output .= "<p class=\"cris-sdg-description-intro\">" . esc_html($paragraph) . "</p>";
        }
        if (count($items)) {
            $output .= "<ul class=\"cris-sdg-targets\">";
            foreach ($items as $item) {
                $output .= "<li>" . esc_html($item) . "</li>";
            }
            $output .= "</ul>";
        }
        $output .= "</div>";
        return $output;
    }

    /*
     * GreenOffice-freigegebene Personen zum Nachhaltigkeitsziel.
     */
    private function make_persons($sdgID, $hSub = 3): string
    {
        $persons = $this->get_sdg_persons($sdgID);
        if (!count($persons)) {
            return '';
        }

        $output = "<div class=\"cris-sdg-persons\">";
        $output .= "<h{$hSub} class=\"cris-sdg-persons-title\">" . esc_html__('Beitragende Wissenschaftler/-innen', 'fau-cris') . "</h{$hSub}>";
        $output .= "<div class=\"cris-sdg-persons-cards\">";

        foreach ($persons as $person) {
            $firstname = $person->attributes['cffirstnames'] ?? '';
            $lastname = $person->attributes['cffamilynames'] ?? '';

            $output .= "<div class=\"cris-sdg-person-card\">";
            // Card from a local person page when one exists, CRIS link otherwise.
            // The SDG list names contributors, it is not a contact directory,
            // so the card drops the contact details and the job title.
            $output .= Tools::get_person_card($person->ID, $firstname, $lastname, $this->cms, $this->name_order_plugin, 'card', 'email,phone,jobTitle');
            $output .= "</div>";
        }

        $output .= "</div>";
        $output .= "</div>";
        return $output;
    }

    private function get_sdg_persons($sdgID): array
    {
        $persons = array();
        $personsString = Dicts::$base_uri . "getrelated/UNSDG/" . $sdgID . "/usdg_has_pers";
        $personsXml = Tools::XML2obj($personsString);

        if (!is_wp_error($personsXml) && isset($personsXml['size']) && $personsXml['size'] != 0) {
            foreach ($personsXml as $_p) {
                $person = new CRIS_sdg_person($_p);
                // Nur GreenOffice-freigegebene Personen anzeigen.
                if (($person->attributes['greenofficecheck'] ?? '') !== 'GOApproved') {
                    continue;
                }
                if ($person->ID) {
                    $persons[$person->ID] = $person;
                }
            }
        }
        return $persons;
    }
}

class CRIS_sdgs extends Webservice
{
    /*
     * UNSDG requests.
     */
    public function by_id($sdgID = null): array|\WP_Error
    {
        if ($sdgID === null || $sdgID === "0" || $sdgID === '') {
            return new \WP_Error(
                'cris-sdgid-error',
                __('Bitte geben Sie die CRIS-ID des Nachhaltigkeitsziels an.', 'fau-cris')
            );
        }

        if (!is_array($sdgID)) {
            $sdgID = array($sdgID);
        }

        $requests = array();
        foreach ($sdgID as $_s) {
            $requests[] = sprintf('get/UNSDG/%d', $_s);
        }
        return $this->retrieve($requests);
    }

    private function retrieve($reqs, &$filter = null): array
    {
        if ($filter !== null && !$filter instanceof Filter) {
            $filter = new Filter($filter);
        }

        $data = array();
        $hadFailure = false;
        foreach ($reqs as $_i) {
            $_data = $this->get($_i, $filter);
            if (is_wp_error($_data)) {
                $hadFailure = true;
                continue;
            }
            $data[] = $_data;
        }

        if (empty($data) && $hadFailure) {
            $this->lastError = $this->lastError ?: new \WP_Error(
                'cris-fetch-failed',
                __('Data is currently unavailable.', 'fau-cris')
            );
        } else {
            $this->lastError = null;
        }

        $sdgs = array();

        foreach ($data as $_d) {
            foreach ($_d as $sdg) {
                $s = new CRIS_sdg($sdg);
                if ($s->ID && ($filter === null || $filter->evaluate($s))) {
                    $sdgs[$s->ID] = $s;
                }
            }
        }

        return $sdgs;
    }
}

class CRIS_sdg extends CRIS_Entity
{
    /*
     * object for a single UN SDG
     */
    public function __construct($data)
    {
        parent::__construct($data);
    }
}

class CRIS_sdg_person extends CRIS_Entity
{
    /*
     * object for a person related to a UN SDG. Captures the GreenOfficeCheck
     * value carried on the USDG_has_PERS relation.
     */
    public function __construct($data)
    {
        parent::__construct($data);

        foreach ($data->relation as $_r) {
            if ($_r['type'] != "USDG_has_PERS") {
                continue;
            }
            foreach ($_r->attribute as $_a) {
                if ($_a['name'] == 'GreenOfficeCheck') {
                    $this->attributes["greenofficecheck"] = (string) $_a->additionalInfo;
                }
            }
        }
    }
}
