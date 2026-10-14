<?php
namespace RRZE\Cris;

defined('ABSPATH') || exit;

use RRZE\Cris\Tools;
use RRZE\Cris\Webservice;
use RRZE\Cris\Filter;

/*
 * Single person view with their projects.
 *
 * Shortcode usage: [cris show=person person=315785366 filter=sdg]
 *
 * Below the person's name comes their language-specific UNSDGDescription
 * statement, shortened to a teaser with a read-more disclosure, then the FAU
 * Person plugin card (with picture) when a local person page exists or a link
 * into CRIS when it does not, then their projects as an accordion. With
 * filter=sdg each project additionally carries the codes of the UN SDGs it is
 * related to.
 *
 * hide accepts title, card, sdgdescription and projects.
 */
class Personen
{

    /*
     * Characters of the person's UNSDGDescription shown before the read-more
     * disclosure takes over.
     */
    public const SDG_DESCRIPTION_TEASER_LENGTH = 300;

    private array $options;
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
                'cris-persid-error',
                __('Bitte geben Sie die CRIS-ID der Person an.', 'fau-cris')
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
     * Output of a single person including their projects.
     */
    public function singlePerson($param = array())
    {
        $ws = new CRIS_persons();
        try {
            $personArray = $ws->by_id($this->id);
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
                    'method' => 'Personen::singlePerson',
                    'person' => $this->id
                ]
            );
            $this->fetchError = new \WP_Error(
                'cris-person-fetch-failed',
                __('CRIS-Anfrage fehlgeschlagen.', 'fau-cris')
            );
            $personArray = array();
        }

        // Propagate fetch error for renderer message branching.
        if (is_wp_error($personArray)) {
            $this->fetchError = $personArray;
            $personArray = array();
        } elseif ($ws->lastError instanceof \WP_Error) {
            $this->fetchError = $ws->lastError;
        }

        if (!count($personArray)) {
            $output = Tools::no_data_message($this->fetchError, __('Es wurden leider keine Informationen gefunden.', 'fau-cris'));
            return $this->langdiv_open . $output . $this->langdiv_close;
        }

        $output = $this->make_single($personArray, $param);

        return $this->langdiv_open . $output . $this->langdiv_close;
    }

    /* =========================================================================
     * Private Functions
      ======================================================================== */

    private function make_single($persons, $param = array()): string
    {
        $hide = $param['hide'] ?? '';
        $hidden = is_array($hide) ? array_map('trim', $hide) : array_map('trim', explode(',', (string) $hide));
        // Heading levels follow the page structure: hstart sets the main
        // heading, sub-headings sit one level below it.
        $hTitle = min(max(absint($param['hstart'] ?? 2) ?: 2, 1), 6);
        $hSub = min($hTitle + 1, 6);

        $output = "<div class=\"cris-person\">";

        foreach ($persons as $person) {
            $person = (array) $person;
            foreach ($person['attributes'] as $attribut => $v) {
                $person[$attribut] = $v;
            }
            unset($person['attributes']);

            $firstname = $person['cffirstnames'] ?? '';
            $lastname = $person['cffamilynames'] ?? '';

            if (!in_array('title', $hidden, true) && ($firstname !== '' || $lastname !== '')) {
                $output .= "<h{$hTitle} class=\"cris-person-title\">" . esc_html(trim($firstname . ' ' . $lastname)) . "</h{$hTitle}>";
            }

            if (!in_array('sdgdescription', $hidden, true)) {
                $output .= $this->make_sdg_description($person);
            }

            if (!in_array('card', $hidden, true)) {
                $output .= $this->make_card($person['ID'], $firstname, $lastname);
            }

            if (!in_array('projects', $hidden, true)) {
                $output .= $this->make_projects($param, $hidden, $hSub);
            }
        }

        $output .= "</div>";
        return $output;
    }

    /*
     * Person representation: the local person card with picture when a page
     * exists in FAUdir or FAU Person, a CRIS link otherwise.
     */
    private function make_card($persID, $firstname, $lastname): string
    {
        $output = "<div class=\"cris-person-card\">";
        $output .= Tools::get_person_card($persID, $firstname, $lastname, $this->cms, $this->name_order_plugin);
        $output .= "</div>";

        return $output;
    }

    /*
     * The person's own statement on their contribution to the UN SDGs. CRIS
     * carries it as UNSDGDescription in both languages, so the English page
     * falls back to the German text when the English one has not been filled
     * in, as with every other language-specific attribute here.
     *
     * The value is plain text with line breaks, so each line becomes its own
     * paragraph rather than one block with collapsed newlines. Only the first
     * SDG_DESCRIPTION_TEASER_LENGTH characters are shown; expanding replaces
     * that teaser with the full statement instead of appending a second block,
     * so the text grows in place rather than reading as another field. The
     * disclosure is the native element, which needs no JavaScript and stays
     * keyboard accessible; the lang attribute carries the language the text was
     * picked in, which drives hyphenation of the justified paragraphs and tells
     * screen readers which voice to use.
     */
    private function make_sdg_description($person): string
    {
        $lang = 'de';
        $text = $person['unsdgdescription'] ?? '';
        if ($this->page_lang == 'en' && !empty($person['unsdgdescription_en'])) {
            $lang = 'en';
            $text = $person['unsdgdescription_en'];
        }

        $text = str_replace(array("\r\n", "\r"), "\n", (string) $text);
        $lines = array_values(array_filter(array_map('trim', explode("\n", $text)), static function ($line) {
            return $line !== '';
        }));
        if (!count($lines)) {
            return '';
        }

        list($teaser, $rest) = $this->split_sdg_description($lines);
        $output = "<div class=\"cris-person-sdgdescription\" lang=\"" . esc_attr($lang) . "\">";

        if (!count($rest)) {
            return $output . $this->description_paragraphs($lines) . "</div>";
        }

        // The disclosure comes first in the markup so that the teaser can be
        // hidden with a sibling selector once it is open; the stylesheet puts
        // the two back in reading order.
        $output .= "<details class=\"cris-person-sdgdescription-more\">";
        $output .= "<summary>"
            . "<span class=\"cris-person-sdgdescription-more-label\">" . esc_html__('Weiterlesen', 'fau-cris') . "</span>"
            . "<span class=\"cris-person-sdgdescription-less-label\">" . esc_html__('Weniger anzeigen', 'fau-cris') . "</span>"
            . "</summary>";
        $output .= "<div class=\"cris-person-sdgdescription-full\">" . $this->description_paragraphs($lines) . "</div>";
        $output .= "</details>";
        $output .= "<div class=\"cris-person-sdgdescription-teaser\">"
            . $this->description_paragraphs($teaser, ' &hellip;')
            . "</div>";
        $output .= "</div>";

        return $output;
    }

    /*
     * Split the statement into the visible teaser and the part behind the
     * disclosure, cutting at the last word boundary that still fits so that no
     * word is broken in half. A line without any space stays whole rather than
     * being cut mid-word.
     */
    private function split_sdg_description($lines): array
    {
        $teaser = array();
        $rest = array();
        $budget = self::SDG_DESCRIPTION_TEASER_LENGTH;

        foreach ($lines as $line) {
            if ($budget <= 0) {
                $rest[] = $line;
                continue;
            }
            if (mb_strlen($line) <= $budget) {
                $teaser[] = $line;
                $budget -= mb_strlen($line);
                continue;
            }

            // The space is ASCII, so looking for it byte-wise cannot land in
            // the middle of a multibyte character.
            $keep = mb_substr($line, 0, $budget);
            $space = strrpos($keep, ' ');
            $keep = ($space !== false) ? substr($keep, 0, $space) : $line;
            $teaser[] = $keep;

            $remainder = trim(mb_substr($line, mb_strlen($keep)));
            if ($remainder !== '') {
                $rest[] = $remainder;
            }
            $budget = 0;
        }

        return array($teaser, $rest);
    }

    private function description_paragraphs($lines, $suffix = ''): string
    {
        $output = '';
        $last = count($lines) - 1;
        foreach (array_values($lines) as $i => $line) {
            $output .= "<p>" . esc_html($line) . ($i === $last ? $suffix : '') . "</p>";
        }
        return $output;
    }

    /*
     * The person's projects as an accordion, rendered by the projects component
     * so the markup stays identical to show=projects.
     */
    private function make_projects($param, $hidden, $hSub): string
    {
        $projekte = new Projekte('person', $this->id, $this->page_lang);
        $ws = new CRIS_projects();

        $filter = null;
        try {
            $projects = $ws->by_pers_id($this->id, $filter, 'all');
        } catch (\Throwable $ex) {
            do_action(
                'rrze.log.error',
                'Plugin: {plugin} Exception: {exception}',
                [
                    'plugin' => 'fau-cris',
                    'exception' => $ex->getMessage(),
                    'method' => 'Personen::make_projects',
                    'person' => $this->id
                ]
            );
            return '';
        }

        if (is_wp_error($projects) || !count($projects)) {
            return '';
        }

        // Always fetch SDG tags so that projects show their SDG links
        // regardless of whether filter=sdg is set. Each per-project CRIS
        // request is cached individually by RemoteGet.
        $sdgTags = $this->get_project_sdgs($projects);

        // filter=sdg restricts the list to SDG-related projects only;
        // without the filter all projects are shown, with tags where they exist.
        if (($param['filter'] ?? '') === 'sdg') {
            $projects = $this->only_tagged($projects, $sdgTags);
            if (!count($projects)) {
                return '';
            }
        }

        $projectHide = $param['projects_hide'] ?? ($param['hide'] ?? '');
        $projectHide = is_array($projectHide) ? $projectHide : array_map('trim', explode(',', (string) $projectHide));

        $output = "<div class=\"cris-person-projects\">";
        $output .= "<h{$hSub} class=\"cris-person-projects-title\">" . esc_html__('Projekte', 'fau-cris') . "</h{$hSub}>";
        $output .= $projekte->projectAccordion($projects, $projectHide, $sdgTags);
        $output .= "</div>";

        return $output;
    }

    /*
     * Reduce the project list to those that carry at least one SDG tag.
     */
    private function only_tagged($projects, $sdgTags): array
    {
        $filtered = array();
        foreach ($projects as $key => $project) {
            $projectID = $this->project_id($project);
            if ($projectID && !empty($sdgTags[$projectID])) {
                $filtered[$key] = $project;
            }
        }
        return $filtered;
    }

    private function project_id($project)
    {
        return is_object($project) ? $project->ID : ($project['ID'] ?? '');
    }

    /*
     * SDGs related to each project, keyed by project ID. One webservice request
     * per project as specified by the CRIS webservice; every response is cached
     * individually by RemoteGet, so only a cold cache pays the full cost.
     */
    private function get_project_sdgs($projects): array
    {
        $tags = array();

        foreach ($projects as $project) {
            $projectID = $this->project_id($project);
            if (!$projectID) {
                continue;
            }

            $sdgString = Dicts::$base_uri . "getrelated/Project/" . $projectID . "/proj_has_usdg";
            $sdgXml = Tools::XML2obj($sdgString);

            if (is_wp_error($sdgXml) || !isset($sdgXml['size']) || $sdgXml['size'] == 0) {
                continue;
            }

            foreach ($sdgXml as $_s) {
                $sdg = new CRIS_project_sdg($_s);
                if (!$sdg->ID) {
                    continue;
                }
                $code = $sdg->attributes['code'] ?? '';
                if ($code === '') {
                    continue;
                }
                $name = ($this->page_lang == 'en' && !empty($sdg->attributes['name_en']))
                    ? $sdg->attributes['name_en']
                    : ($sdg->attributes['name'] ?? '');

                $tags[$projectID][] = array(
                    'code' => $code,
                    'name' => $name,
                    'url' => Tools::get_sdg_url($code, $this->page_lang)
                );
            }
        }

        return $tags;
    }
}

class CRIS_persons extends Webservice
{
    /*
     * Person requests.
     */
    public function by_id($persID = null): array|\WP_Error
    {
        if ($persID === null || $persID === "0" || $persID === '') {
            return new \WP_Error(
                'cris-persid-error',
                __('Bitte geben Sie die CRIS-ID der Person an.', 'fau-cris')
            );
        }

        if (!is_array($persID)) {
            $persID = array($persID);
        }

        $requests = array();
        foreach ($persID as $_p) {
            $requests[] = sprintf('get/Person/%d', $_p);
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

        $persons = array();

        foreach ($data as $_d) {
            foreach ($_d as $person) {
                $p = new CRIS_person($person);
                if ($p->ID && ($filter === null || $filter->evaluate($p))) {
                    $persons[$p->ID] = $p;
                }
            }
        }

        return $persons;
    }
}

class CRIS_person extends CRIS_Entity
{
    /*
     * object for a single person
     */
    public function __construct($data)
    {
        parent::__construct($data);
    }
}

class CRIS_project_sdg extends CRIS_Entity
{
    /*
     * object for an SDG related to a project. Declared here rather than reused
     * from the sustainability component, because the autoloader resolves class
     * names to files and would not load that file for this class name.
     */
    public function __construct($data)
    {
        parent::__construct($data);
    }
}
