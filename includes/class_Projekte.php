<?php
namespace RRZE\Cris;
require_once( "class_Tools.php" );
require_once( "class_Webservice.php" );
require_once( "class_Filter.php" );
require_once( "class_Formatter.php" );

class Projekte
{

    private array $options;
    public $output;

    public function __construct($einheit = '', $id = '', $page_lang = 'de')
    {
        if (strpos($_SERVER['PHP_SELF'], "vkdaten/tools/")) {
            $this->cms = 'wbk';
            $this->options = CRIS::ladeConf();
            $this->pathPersonenseiteUnivis = $this->options['Pfad_Personenseite_Univis'] . '/';
        } else {
            $this->cms = 'wp';
            $this->options = (array) FAU_CRIS::get_options();
            $this->pathPersonenseiteUnivis = '/person/';
        }

        $this->id = $id ?: $this->options['cris_org_nr'];
        $this->suchstring = '';
        $this->univis = null;

        $this->order = $this->options['cris_project_order'];
        $this->cris_project_link = $this->options['cris_project_link'] ?? 'none';
        if ($this->cms == 'wbk' && $this->cris_project_link == 'person') {
            $this->univis = Tools::get_univis();
        }

        $this->page_lang = $page_lang;
     
        if (in_array($einheit, array("person", "orga", "award", "awardnameid", "project", "field"))) {
            $this->einheit = $einheit;
        } else {
            $this->einheit = "orga";
        }

        if (!$this->id) {
            $this->error = new \WP_Error(
                'cris-orgid-error',
                __('Bitte geben Sie die CRIS-ID der Organisation, Person oder des Projektes an.', 'fau-cris')
            );
        }
    }

    /**
     * Name : projListe
     *
     * Use: get all project list by organization id or person id
     *
     * Returns: Project list in html format
     *
     * Start::projListe
     */
    public function projListe($param = []): string
    {
        $year = $param['year'] ?: '';
        $start = $param['start'] ?: '';
        $end = $param['end'] ?: '';
        $type = $param['type'] ?: '';
        $limit = $param['limit'] ?: '';
        $hide = $param['hide'] ?: [];
        $role = $param['role'] ?: 'all';
        $status = $param['status'] ?: '';

        $projArray = $this->fetch_projects($year, $start, $end, $type, $role, $status);
        if (empty($projArray)) {
            return '<p>' . __('Es wurden leider keine Projekte gefunden.', 'fau-cris') . '</p>';
        }
        
        // sortiere nach Erscheinungsdatum
        $order = "cfstartdate";
        $formatter = new CRIS_formatter(null, null, $order, SORT_DESC);
        $res = $formatter->execute($projArray);
        if ($limit != '') {
            $projList = array_slice($res[$order], 0, $limit);
        } else {
            $projList = $res[$order];
        }

        $output = '';

        $output .= $this->make_list($projList, $hide);

        return $output;
    }
    //  END::projListe


    /**
     * Name : projNachRolle
     *
     * Use: get all project list by role, leader or collaborator
     *
     * Returns: Project list in html format
     *
     * Start::projNachRolle
     */
    public function projNachRolle($param = array(), $content = ''): string
    {
        $year = (isset($param['year']) && $param['year'] != '') ? $param['year'] : '';
        $start = (isset($param['start']) && $param['start'] != '') ? $param['start'] : '';
        $end = (isset($param['end']) && $param['end'] != '') ? $param['end'] : '';
        $type = (isset($param['type']) && $param['type'] != '') ? $param['type'] : '';
        $hide = (isset($param['hide']) && !empty($param['hide'])) ? $param['hide'] : array();
        $role = (isset($param['role']) && $param['role'] != '') ? $param['role'] : 'all';
        $status = (isset($param['status']) && $param['status'] != '') ? $param['status'] : '';

        $projArray = $this->fetch_projects($year, $start, $end, $type, $role, $status);

        if (!count($projArray)) {
            $output = '<p>' . __('Es wurden leider keine Projekte gefunden.', 'fau-cris') . '</p>';
            return $output;
        }
        foreach ($projArray as $id) {
            if (strpos($id->attributes['relpersidlead'], $this->id) !== false) {
                $id->attributes['role'] = 'leader';
            } elseif (strpos($id->attributes['relpersidcoll'], $this->id) !== false) {
                $id->attributes['role'] = 'member';
            }
        }
        // sortiere nach Rolle, innerhalb der Rolle nach Startdatum absteigend
        $formatter = new CRIS_formatter("role", SORT_ASC, "cfstartdate", SORT_DESC);
        $projList = $formatter->execute($projArray);

        $output = '';
        foreach ($projList as $array_role => $projects) {
            $title = Tools::getTitle('projectroles', $array_role, $this->page_lang);
            $output .= '<h3>' . $title . '</h3>';
            if ($content == '') {
                $output .= $this->make_list($projects, $hide);
            } else {
                $output .= $this->make_custom_list($projects, $content);
            }
        }
        return $output;
    }
    //  End::projNachRolle


    /**
     * Name : projNachJahr
     *
     * Use: get all project list by year
     *
     * Returns: Project list in html format
     *
     * Start::projNachJahr
     */

    public function projNachJahr($param = array(), $content = ''): string
    {
        $year = (isset($param['year']) && $param['year'] != '') ? $param['year'] : '';
        $start = (isset($param['start']) && $param['start'] != '') ? $param['start'] : '';
        $end = (isset($param['end']) && $param['end'] != '') ? $param['end'] : '';
        $type = (isset($param['type']) && $param['type'] != '') ? $param['type'] : '';
        $hide = (isset($param['hide']) && !empty($param['hide'])) ? $param['hide'] : array();
        $role = (isset($param['role']) && $param['role'] != '') ? $param['role'] : 'all';
        $format = (isset($param['format']) && $param['format'] != '') ? $param['format'] : '';
        $status = (isset($param['status']) && $param['status'] != '') ? $param['status'] : '';

        $projArray = $this->fetch_projects($year, $start, $end, $type, $role, $status);

        if (!count($projArray)) {
            $output = '<p>' . __('Es wurden leider keine Projekte gefunden.', 'fau-cris') . '</p>';
            return $output;
        }
        
        // sortiere nach Erscheinungsjahr, innerhalb des Jahres nach Erstautor
        $formatter = new CRIS_formatter("startyear", SORT_DESC, "cftitle", SORT_ASC);
        $projList = $formatter->execute($projArray);

        $output = '';
        if (shortcode_exists('collapsibles') && $format == 'accordion') {
            $shortcode_data = '';
            if (empty($year) || strpos($year, ',') !== false) {
                $openfirst = ' load="open"';
                $expandall = ' expand-all-link="true"';
            } else {
                $openfirst = '';
                $expandall = '';
            }
            foreach ($projList as $array_year => $projects) {
                $shortcode_data .= do_shortcode('[collapse title="' . $array_year . '"' . $openfirst . ']' . $this->make_list($projects, $hide) . '[/collapse]');
                $openfirst = '';
            }
            $output .= do_shortcode('[collapsibles ' . $expandall . ']' . $shortcode_data . '[/collapsibles]');
        } else {
            foreach ($projList as $array_year => $projects) {
                if (empty($year)) {
                    $output .= '<h3>' . $array_year . '</h3>';
                }
                if ($content == '') {
                    $output .= $this->make_list($projects, $hide);
                } else {
                    $output .= $this->make_custom_list($projects, $content);
                }
            }
        }
        return $output;
    }
    //  End::projNachJahr

    /**
     * Name : projNachTyp
     *
     * Use: get all project list by type
     *
     * Returns: Project list in html format
     *
     * Start::projNachTyp
     */
    public function projNachTyp($param = array(), $content = ''): string
    {
        $year = (isset($param['year']) && $param['year'] != '') ? $param['year'] : '';
        $start = (isset($param['start']) && $param['start'] != '') ? $param['start'] : '';
        $end = (isset($param['end']) && $param['end'] != '') ? $param['end'] : '';
        $type = (isset($param['type']) && $param['type'] != '') ? $param['type'] : '';
        $hide = (isset($param['hide']) && !empty($param['hide'])) ? $param['hide'] : array();
        $role = (isset($param['role']) && $param['role'] != '') ? $param['role'] : 'all';
        $format = (isset($param['format']) && $param['format'] != '') ? $param['format'] : '';
        $status = (isset($param['status']) && $param['status'] != '') ? $param['status'] : '';

        $projArray = $this->fetch_projects($year, $start, $end, $type, $role, $status);

        if (!count($projArray)) {
            $output = '<p>' . __('Es wurden leider keine Projekte gefunden.', 'fau-cris') . '</p>';
            return $output;
        }
        
        // Projekttypen sortieren
        $order = $this->order;
        if ($order[0] != '' && array_search($order[0], array_column(CRIS_Dicts::$typeinfos['projects'], 'short'))) {
            foreach ($order as $key => $value) {
                $order[$key] = Tools::getType('projects', $value);
            }
        } else {
            $order = Tools::getOrder('projects');
        }

        // sortiere nach Typenliste, innerhalb des Jahres nach Jahr abwärts sortieren
        $formatter = new CRIS_formatter("project type", array_values($order), "cfstartdate", SORT_DESC);
        $projList = $formatter->execute($projArray);

        $output = '';
        if (shortcode_exists('collapsibles') && $format == 'accordion') {
            $shortcode_data = '';
            if (!empty($type) && strpos($type, ',') !== false) {
                $openfirst = ' load="open"';
                $expandall = ' expand-all-link="true"';
            } else {
                $openfirst = '';
                $expandall = '';
            }
            foreach ($projList as $array_type => $projects) {
                $title = Tools::getTitle('projects', $array_type, $this->page_lang);
                $shortcode_data .= do_shortcode('[collapse title="' . $title . '"' . $openfirst . ']' . $this->make_list($projects, $hide) . '[/collapse]');
                $openfirst = '';
            }
            $output .= do_shortcode('[collapsibles ' . $expandall . ']' . $shortcode_data . '[/collapsibles]');
        } else {
            foreach ($projList as $array_type => $projects) {
                // Zwischenüberschrift (= Projecttyp), außer wenn nur ein Typ gefiltert wurde
                if (empty($type)) {
                    $title = Tools::getTitle('projects', $array_type, $this->page_lang);
                    $output .= "<h3>";
                    $output .= $title;
                    $output .= "</h3>";
                }
                if ($content == '') {
                    $output .= $this->make_list($projects, $hide, 0);
                } else {
                    $output .= $this->make_custom_list($projects, $content);
                }
            }
        }
        return $output;
    }
    //  End::projNachTyp


    /**
     * Name : singleProj
     *
     * Use: get single project by id
     *
     * Returns: single Project array in html format
     *
     * Start::singleProj
     */

    public function singleProj($param = array())
    {
        $ws = new CRIS_projects();
        try {
            $projArray = $ws->by_id($this->id);
        } catch (Exception $ex) {
            return;
        }

        if (!count($projArray)) {
            $output = '<p>' . __('Es wurden leider keine Projekte gefunden.', 'fau-cris') . '</p>';
            return $output;
        }

        if (is_array($this->id)) {
            $output = $this->make_list($projArray, $param['hide']);
        } else {
            $output = $this->make_single($projArray, $param);
        }

        return $output;
    }
    //  End::singleProj


    /**
     * Name : customProj
     *
     * Use: format the customize Project attributes in html
     *
     * Returns: custom Project array in html format
     *
     * Start::customProj
     */

    public function customProj($content = '', $param = array())
    {
        $ws = new CRIS_projects();
        try {
            $projArray = $ws->by_id($this->id);
        } catch (Exception $ex) {
            return;
        }

        if (!count($projArray)) {
            $output = '<p>' . __('Es wurden leider keine Projekte gefunden.', 'fau-cris') . '</p>';
            return $output;
        }

        $output = $this->make_custom_single($projArray, $content, $param);
        return $output;
    }
    //End::customProj

    /**
     * Name : pubProj
     *
     * Use: get projects of a single publication by publication id
     *
     * Returns: Project array in html format
     *
     * Start::pubProj
     */
    public function pubProj($pub, $seed = false)
    {
        $ws = new CRIS_projects();
        if ($seed) {
            $ws->disable_cache();
        }
        try {
            $projArray = $ws->by_pub($pub);
        } catch (Exception $ex) {
            return;
        }
        if (!count($projArray)) {
            return;
        }

        $firstItem = reset($projArray);
        if ($firstItem && isset($firstItem->attributes['relation right seq'])) {
            //if (array_key_exists('relation right seq', reset($projArray)->attributes)) {
            $sortby = 'relation right seq';
            $orderby = $sortby;
        } else {
            $sortby = null;
            $orderby = __('O.A.', 'fau-cris');
        }

        // sortiere nach Erscheinungsdatum
        $firstItem = reset($projArray);
        if ($firstItem && isset($firstItem->attributes['relation right seq'])) {
            //if (array_key_exists('relation right seq', reset($projArray)->attributes)) {
            $sortby = 'relation right seq';
            $orderby = $sortby;
        } else {
            $sortby = null;
            $orderby = __('O.A.', 'fau-cris');
        }
        $formatter = new CRIS_formatter(null, null, $sortby, SORT_ASC);
        $res = $formatter->execute($projArray);
        $projList = $res[$orderby] ?? [];

        $hide = array();
        $output = $this->make_list($projList, $hide, 0, 1);

        return $output;
    }

    //  End:pubProj

    /* =========================================================================
     * Private Functions
      ======================================================================== */

    /*
     * Holt Daten vom Webservice je nach definierter Einheit.
     */


    /**
     * Name : fetch_projects
     *
     * Use: get all project by_orga_id or by_pers_id
     *
     * Returns: project array
     *
     * Start::fetch_projects
     */
    private function fetch_projects($year = '', $start = '', $end = '', $type = '', $role = 'all', $status = ''): array
    {
        $awardArray = [];

        $filter = Tools::project_filter($year, $start, $end, $type, $status);
        if (!is_wp_error($filter)) {
            $ws = new CRIS_projects();
            if ($this->einheit == "orga") {
                $awardArray = $ws->by_orga_id($this->id, $filter);
            } elseif ($this->einheit == "person") {
                $awardArray = $ws->by_pers_id($this->id, $filter, $role);
            }
        }

        return $awardArray;
    }
    //End::fetch_projects


    /**
     * Name : make_custom_single
     *
     * Use: format the single customize Project attributes in html
     *
     * Returns: project array in html format
     *
     * Start::make_custom_single
     */

    private function make_custom_single($projects, $custom_text, $param = array()): string
    {
        $proj_details = array();
        $projlist = "<div class=\"cris-projects\">";

        foreach ($projects as $project) {
            $project = (array) $project;
            $leaders = array();
            $members = array();
            foreach ($project['attributes'] as $attribut => $v) {
                $project[$attribut] = $v;
            }
            unset($project['attributes']);

            $id = $project['ID'];
            $imgs = self::get_project_images($project['ID']);
            $proj_details = array();

            switch ($this->page_lang) {
                case 'en':
                    $title = ($project['cftitle_en'] != '') ? $project['cftitle_en'] : $project['cftitle'];
                    $description = ($project['cfabstr_en'] != '') ? $project['cfabstr_en'] : $project['cfabstr'];
                    break;
                case 'de':
                default:
                    $title = ($project['cftitle'] != '') ? $project['cftitle'] : $project['cftitle_en'];
                    $description = ($project['cfabstr'] != '') ? $project['cfabstr'] : $project['cfabstr_en'];
                    break;
            }
            $proj_details['#title#'] = htmlentities($title, ENT_QUOTES);
            $proj_details['#description#'] = strip_tags($description, '<br><a><sup><sub><ul><ol><li><b><p><i><strong><em>');

            $proj_details['#type#'] = Tools::getName('projects', $project['project type'], $this->page_lang);
            $proj_details['#parentprojecttitle#'] = ($this->page_lang == 'en' && !empty($project['parentprojecttitle_en'])) ? $project['parentprojecttitle_en'] : $project['parentprojecttitle'];
            $leaderIDs = explode(",", $project['relpersidlead']);
            $collIDs = explode(",", $project['relpersidcoll']);
            $persons = $this->get_project_persons($id, $leaderIDs, $collIDs);
            $proj_details['#leaders#'] = array();
            foreach ($persons['leaders'] as $l_id => $l_names) {
                $leaders[] = Tools::get_person_link($l_id, $l_names['firstname'], $l_names['lastname'], $this->cris_project_link, $this->cms, $this->pathPersonenseiteUnivis, $this->univis);
            }
            $proj_details['#leaders#'] = implode(', ', $leaders);
            $proj_details['#members#'] = array();
            foreach ($persons['members'] as $m_id => $m_names) {
                $members[] = Tools::get_person_link($m_id, $m_names['firstname'], $m_names['lastname'], $this->cris_project_link, $this->cms, $this->pathPersonenseiteUnivis, $this->univis);
            }
            $proj_details['#members#'] = implode(', ', $members);
            $start = $project['cfstartdate'];
            $proj_details['#start#'] = date_i18n(get_option('date_format'), strtotime($start));
            $end = $project['cfenddate'];
            $proj_details['#end#'] = (!empty($end) ? date_i18n(get_option('date_format'), strtotime($end)) : '');
            $proj_details['#extend#'] = (!empty($project['extension date'])) ? date_i18n(get_option('date_format'), strtotime($project['extension date'])) : '';
            $funding = $this->get_project_funding($id);
            $proj_details['#funding#'] = implode(', ', $funding);
            $proj_details['#url#'] = $project['cfuri'];
            $proj_details['#acronym#'] = $project['cfacro'];
            $description = ($this->page_lang == 'en' && !empty($project['cfabstr_en'])) ? $project['cfabstr_en'] : $project['cfabstr'];
            $proj_details['#publications#'] = $this->get_project_publications($id, $param);
            $proj_details['#image1#'] = '';
            if (count($imgs)) {
                $i = 1;
                foreach ($imgs as $img) {
                    if (isset($img->attributes['png180']) && mb_strlen($img->attributes['png180']) > 30) {
                        $proj_details["#image$i#"] = "<div class=\"cris-image\">";
                        $proj_details["#image$i#"] .= "<p><img alt=\"" . $img->attributes['description'] . "\" src=\"data:image/PNG;base64," . $img->attributes['png180'] . "\" width=\"180\" height=\"180\"><br />"
                                . "<span class=\"wp-caption-text\">" . (($img->attributes['description'] != '') ? $img->attributes['description'] : "") . "</span></p>";
                        $proj_details["#image$i#"] .= "</div>";
                    }
                    $i++;
                }
            }
            $projlist .= strtr($custom_text, $proj_details);
        }
        $projlist .= "</div>";
        return $projlist;
    }
    //  End::make_custom_single

    /**
     * Name : make_custom_list
     *
     * Use: format the customize Project attributes in html
     *
     * Returns: project array in html format
     *
     * Start::make_custom_list
     */
    private function make_custom_list($projects, $custom_text, $param = array()): string
    {
        $projlist = "<ul class=\"cris-projects\">";

        foreach ($projects as $project) {
            $project = (array) $project;
            foreach ($project['attributes'] as $attribut => $v) {
                $project[$attribut] = $v;
            }
            unset($project['attributes']);

            $id = $project['ID'];
            $imgs = self::get_project_images($project['ID']);

            $proj_details = array();
            switch ($this->page_lang) {
                case 'en':
                    $title = ($project['cftitle_en'] != '') ? $project['cftitle_en'] : $project['cftitle'];
                    $description = ($project['cfabstr_en'] != '') ? $project['cfabstr_en'] : $project['cfabstr'];
                    break;
                case 'de':
                default:
                    $title = ($project['cftitle'] != '') ? $project['cftitle'] : $project['cftitle_en'];
                    $description = ($project['cfabstr'] != '') ? $project['cfabstr'] : $project['cfabstr_en'];
                    break;
            }
            $proj_details['#title#'] = htmlentities($title, ENT_QUOTES);
            $proj_details['#description#'] = strip_tags($description, '<br><br/><a><sup><sub><ul><ol><li>');
            $proj_details['#type#'] = Tools::getName('projects', $project['project type'], $this->page_lang);
            $proj_details['#parentprojecttitle#'] = ($this->page_lang == 'en' && !empty($project['parentprojecttitle_en'])) ? $project['parentprojecttitle_en'] : $project['parentprojecttitle'];
            $start = $project['cfstartdate'];
            $proj_details['#start#'] = date_i18n(get_option('date_format'), strtotime($start));
            $end = $project['cfenddate'];
            $proj_details['#end#'] = (!empty($end) ? date_i18n(get_option('date_format'), strtotime($end)) : '');
            $proj_details['#extend#'] = (!empty($project['extension date'])) ? date_i18n(get_option('date_format'), strtotime($project['extension date'])) : '';
            $funding = $this->get_project_funding($id);
            $proj_details['#funding#'] = implode(', ', $funding);
            $proj_details['#url#'] = $project['cfuri'];
            $proj_details['#acronym#'] = $project['cfacro'];
            $description = ($this->page_lang == 'en' && !empty($project['cfabstr_en'])) ? $project['cfabstr_en'] : $project['cfabstr'];
            $proj_details['#image1#'] = '';
            if (count($imgs)) {
                $i = 1;
                foreach ($imgs as $img) {
                    $proj_details["#image$i#"] = "<div class=\"cris-image\">";
                    if (isset($img->attributes['png180']) && mb_strlen($img->attributes['png180']) > 30) {
                        $proj_details["#image$i#"] .= "<p><img alt=\"" . $img->attributes['description'] . "\" src=\"data:image/PNG;base64," . $img->attributes['png180'] . "\" width=\"180\" height=\"180\"><br />"
                                . "<span class=\"wp-caption-text\">" . (($img->attributes['description'] != '') ? $img->attributes['description'] : "") . "</span></p>";
                        $proj_details["#image$i#"] .= "</div>";
                    }
                    $i++;
                }
            }

            $projlist .= "<li>";
            $projlist .= strtr($custom_text, $proj_details);
            $projlist .= "</li>";
        }
        $projlist .= "</ul>";
        return $projlist;
    }

    //  End::make_custom_list

    /**
     * Name : make_single
     *
     * Use: format the single Project attributes in html
     *
     * Returns: project array in html format
     *
     * Start::make_single
     */
    private function make_single($projects, $param = array()): string
    {

        $projlist = "<div class=\"cris-projects\">";

        foreach ($projects as $project) {
            $project = (array) $project;
            foreach ($project['attributes'] as $attribut => $v) {
                $project[$attribut] = $v;
            }
            unset($project['attributes']);

            $id = $project['ID'];
            switch ($this->page_lang) {
                case 'en':
                    $title = ($project['cftitle_en'] != '') ? $project['cftitle_en'] : $project['cftitle'];
                    $description = ($project['cfabstr_en'] != '') ? $project['cfabstr_en'] : $project['cfabstr'];
                    break;
                case 'de':
                default:
                    $title = ($project['cftitle'] != '') ? $project['cftitle'] : $project['cftitle_en'];
                    $description = ($project['cfabstr'] != '') ? $project['cfabstr'] : $project['cfabstr_en'];
                    break;
            }
            $title = htmlentities($title, ENT_QUOTES);
            $description = str_replace(["\n", "\t", "\r"], '', $description);
            $description = strip_tags($description, '<br><a><sup><sub><ul><ol><li><b><p><i><strong><em>');
            $type = Tools::getName('projects', $project['project type'], $this->page_lang);
            $imgs = self::get_project_images($project['ID']);

            if (count($imgs)) {
                $projlist .= "<div class=\"cris-image\">";
                foreach ($imgs as $img) {
                    if (isset($img->attributes['png180']) && mb_strlen($img->attributes['png180']) > 30) {
                        $projlist .= "<p><img alt=\"" . $img->attributes['description'] . "\" src=\"data:image/PNG;base64," . $img->attributes['png180'] . "\" width=\"180\" height=\"180\"><br />"
                                . "<span class=\"wp-caption-text\">" . (($img->attributes['description'] != '') ? $img->attributes['description'] : "") . "</span></p>";
                    }
                }
                $projlist .= "</div>";
            }

            if (!in_array('title', $param['hide'])) {
                $projlist .= "<h3>" . $title . "</h3>";
            }

            if (!empty($type)) {
                $projlist .= "<p class=\"project-type\">(" . $type . ")</p>";
            }

            if (!in_array('details', $param['hide'])) {
                $parentprojecttitle = ($this->page_lang == 'en' && !empty($project['parentprojecttitle_en'])) ? $project['parentprojecttitle_en'] : $project['parentprojecttitle'];
                $leaderIDs = explode(",", $project['relpersidlead']);
                $collIDs = explode(",", $project['relpersidcoll']);
                $persons = $this->get_project_persons($id, $leaderIDs, $collIDs);
                $leaders = array();
                foreach ($persons['leaders'] as $l_id => $l_names) {
                    $leaders[] = Tools::get_person_link($l_id, $l_names['firstname'], $l_names['lastname'], $this->cris_project_link, $this->cms, $this->pathPersonenseiteUnivis, $this->univis);
                }
                $members = array();
                foreach ($persons['members'] as $m_id => $m_names) {
                    $members[] = Tools::get_person_link($m_id, $m_names['firstname'], $m_names['lastname'], $this->cris_project_link, $this->cms, $this->pathPersonenseiteUnivis, $this->univis);
                }
                $start = $project['cfstartdate'];
                $start = date_i18n(get_option('date_format'), strtotime($start));
                if (!in_array('end', $param['hide'])) {
                    $end = (!empty($project['cfenddate'])) ? date_i18n(get_option('date_format'), strtotime($project['cfenddate'])) : '';
                    $extend = (!empty($project['extension date'])) ? date_i18n(get_option('date_format'), strtotime($project['extension date'])) : '';
                }
                $funding = $this->get_project_funding($id);
                $url = $project['cfuri'];
                $acronym = $project['cfacro'];

                $projlist .= "<p class=\"project-details\">";
                if (!empty($parentprojecttitle)) {
                    $projlist .= "<strong>" . __('Titel des Gesamtprojektes', 'fau-cris') . ': </strong>' . $parentprojecttitle;
                }
                if (!empty($leaders)) {
                    $projlist .= "<br /><strong>" . __('Projektleitung', 'fau-cris') . ': </strong>';
                    $projlist .= implode(', ', $leaders);
                }
                if (!empty($members)) {
                    $projlist .= "<br /><strong>" . __('Projektbeteiligte', 'fau-cris') . ': </strong>';
                    $projlist .= implode(', ', $members);
                }
                if (!empty($start)) {
                    $projlist .= "<br /><strong>" . __('Projektstart', 'fau-cris') . ': </strong>' . $start;
                }
                if (!empty($end)) {
                    $projlist .= "<br /><strong>" . __('Projektende', 'fau-cris') . ': </strong>' . $end;
                }
                if (!empty($extend)) {
                    $projlist .= "<br /><strong>" . __('Laufzeitverlängerung bis', 'fau-cris') . ': </strong>' . $extend;
                }
                if (!empty($acronym)) {
                    $projlist .= "<br /><strong>" . __('Akronym', 'fau-cris') . ": </strong>" . $acronym;
                }
                if (!empty($funding)) {
                    $projlist .= "<br /><strong>" . __('Mittelgeber', 'fau-cris') . ': </strong>';
                    $projlist .= implode(', ', $funding);
                }
                if (!empty($url)) {
                    $projlist .= "<br /><strong>" . __('URL', 'fau-cris') . ": </strong><a href=\"" . $url . "\">" . $url . "</a>";
                }
                $projlist .= "</p>";
            }

            if (!in_array('abstract', $param['hide'])) {
                if ($description) {
                    $projlist .= "<h4>" . __('Abstract', 'fau-cris') . ": </h4>" . "<p class=\"project-description\">" . $description . '</p>';
                }
            }
            if (!in_array('publications', $param['hide'])) {
                $publications = $this->get_project_publications($id, $param);
                if ($publications) {
                    $projlist .= "<h4>" . __('Publikationen', 'fau-cris') . ": </h4>" . $publications;
                }
            }
        }
        $projlist .= "</div>";
        return $projlist;
    }
    //  End::make_single

    /**
     * Name : make_list
     *
     * Use: format all Projects attributes in html
     *
     * Returns: projects array in html format
     *
     * Start::make_list
     */
    private function make_list($projects, $hide = array(), $showtype = 1, $pubProj = 0): array|string
    {

        global $post;
        $projlist = "<ul class=\"cris-projects\">";

        foreach ($projects as $project) {
            $project = (array) $project;
            foreach ($project['attributes'] as $attribut => $v) {
                $project[$attribut] = $v;
            }
            unset($project['attributes']);

            $id = $project['ID'];
            switch ($this->page_lang) {
                case 'en':
                    $title = ($project['cftitle_en'] != '') ? $project['cftitle_en'] : $project['cftitle'];
                    $description = ($project['cfabstr_en'] != '') ? $project['cfabstr_en'] : $project['cfabstr'];
                    break;
                case 'de':
                default:
                    $title = ($project['cftitle'] != '') ? $project['cftitle'] : $project['cftitle_en'];
                    $description = ($project['cfabstr'] != '') ? $project['cfabstr'] : $project['cfabstr_en'];
                    break;
            }
            $title = htmlentities($title, ENT_QUOTES);
            $description = str_replace(["\n", "\t", "\r"], '', $description);
            $description = strip_tags($description, '<br><a><sup><sub><ul><ol><li><b><p><i><strong><em>');
            $type = Tools::getName('projects', $project['project type'], $this->page_lang);

            $projlist .= "<li>";
            $projlist .= "<h3 class=\"project-title\">" . $title . "</h3>";

            if (!empty($type) && $showtype == 1) {
                $projlist .= "<br />(" . $type . ")";
            }

            if (!in_array('details', $hide)) {
                $parentprojecttitle = ($this->page_lang == 'en' && !empty($project['parentprojecttitle_en'])) ? $project['parentprojecttitle_en'] : $project['parentprojecttitle'];
                $acronym = $project['cfacro'];
                $start = $project['cfstartdate'];
                if (!in_array('end', $hide)) {
                    $end = (!empty($project['extension date'])) ? $project['extension date'] : ((!empty($project['cfenddate'])) ? $project['cfenddate'] : '');
                } else {
                    $end = '';
                }
                $date = Tools::make_date($start, $end);
                $funding = $this->get_project_funding($id);
                $url = $project['cfuri'];
                /*
                 * Erst umsetzen wenn Datendrehscheibe läuft
                 *
                  $leaderIDs = explode(",", $project['relpersidlead']);
                  $leaderArray = $this->get_project_leaders($id, $leaderIDs);
                  $leaders = array();
                  foreach ($leaderArray as $l_id => $l_names) {
                  $leaders[] = Tools::get_person_link($l_id, $l_names['firstname'], $l_names['lastname'], $this->cris_project_link, $this->cms, $this->pathPersonenseiteUnivis, $this->univis);
                  }
                 */

                $projlist .= "<div class=\"project-details\">";
                if (!empty($parentprojecttitle)) {
                    $projlist .= "<strong>" . __('Titel des Gesamtprojektes', 'fau-cris') . ': </strong>' . $parentprojecttitle . '<br />';
                }
                if (isset($leaders) && !empty($leaders)) {
                    $projlist .= "<strong>" . __('Projektleitung', 'fau-cris') . ': </strong>';
                    $projlist .= implode(', ', $leaders) . '<br />';
                }
                if (!empty($date)) {
                    $projlist .= "<strong>" . __('Laufzeit', 'fau-cris') . ': </strong>' . $date . '<br />';
                }
                if (!empty($funding)) {
                    $projlist .= "<strong>" . __('Mittelgeber', 'fau-cris') . ': </strong>';
                    $projlist .= implode(', ', $funding) . '<br />';
                }
                if (!empty($url)) {
                    $projlist .= "<strong>" . __('URL', 'fau-cris') . ": </strong><a href=\"" . $url . "\">" . $url . "</a>";
                }
                $projlist .= "</div>";
            }

            if (!in_array('abstract', $hide) && !empty($description)) {
                $projlist .= "<div>"
                        . "<div class=\"abstract-title\"><a title=\"" . __('Abstract anzeigen', 'fau-cris') . "\">" . __('Abstract', 'fau-cris') . "</a> </div>"
                        . "<div class=\"abstract\">" . $description . '</div>'
                        . '</div>';
            }
            if (!in_array('link', $hide) && !empty($id)) {
                $link = Tools::get_item_url("project", $title, $id, $post->ID, $this->page_lang);
                $projlist .= "<div>" . " &#8594;<a href=\"" . $link . "\">" . __('Mehr Informationen', 'fau-cris') . "</a> </div>";
            }
            $projlist .= "</li>";
            if ($pubProj == 1) {
                $titlesArray[] = $title;
                $linksArray[] = "<a href=\"" . $link . "\">" . $title . "</a>";
            }
        }
        $projlist .= "</ul>";

        if ($pubProj == 1) {
            $output['title'] = implode('<br />', $titlesArray);
            $output['link'] = implode('<br />', $linksArray);
            return $output;
        }

        return $projlist;
    }

    //  End::make_list

    private function make_accordion($projects, $hide = array(), $showtype = 1)
    {
        global $post;

        $lang_key = ($this->page_lang == 'en') ? '_en' : '';
        $projlist = '[collapsibles]';

        foreach ($projects as $project) {
            $project = (array) $project;
            foreach ($project['attributes'] as $attribut => $v) {
                $project[$attribut] = $v;
            }
            unset($project['attributes']);

            $id = $project['ID'];
            $acronym = $project['cfacro'];
            switch ($this->page_lang) {
                case 'en':
                    $title = ($project['cftitle_en'] != '') ? $project['cftitle_en'] : $project['cftitle'];
                    $description = ($project['cfabstr_en'] != '') ? $project['cfabstr_en'] : $project['cfabstr'];
                    break;
                case 'de':
                default:
                    $title = ($project['cftitle'] != '') ? $project['cftitle'] : $project['cftitle_en'];
                    $description = ($project['cfabstr'] != '') ? $project['cfabstr'] : $project['cfabstr_en'];
                    break;
            }
            $title = htmlentities($title, ENT_QUOTES);
            $title = str_replace(['[', ']'], ['&#91;', '&#93;'], $title);
            $description = str_replace(["\n", "\t", "\r"], '', $description);
            $description = strip_tags($description, '<br><a><sup><sub><ul><ol><li><b><p><i><strong><em>');
            if (mb_strlen($description) > 500) {
                $pos = strpos($description, ' ', 500);
                $description = mb_substr($description, 0, $pos) . '&hellip;';
            }
            if (!empty($project['kurzbeschreibung' . $lang_key])) {
                $description = $project['kurzbeschreibung' . $lang_key];
            }
            $description = str_replace(['[', ']'], ['&#91;', '&#93;'], $description);

            $type = Tools::getName('projects', $project['project type'], $this->page_lang);


            $projlist .= "[collapse title=\"" . ((!empty($acronym)) ? $acronym . ": " : "") . $title . "\"]";
            if (!in_array('abstract', $hide) && !empty($description)) {
                $projlist .= "<p class=\"abstract\">" . $description . '</p>';
            }
            if (!in_array('link', $hide) && !empty($id)) {
                $link = Tools::get_item_url("project", $title, $id, $post->ID, $this->page_lang);
            }
            $projlist .= "<p>" . "&#8594; <a href=\"" . $link . "\">" . __('Mehr Informationen', 'fau-cris') . "</a> </p>";
            $projlist .= "[/collapse]";
        }
        $projlist .= "[/collapsibles]";

        return do_shortcode($projlist);
    }

    public function fieldProj($field, $return = 'list', $seed = false)
    {
        $ws = new CRIS_projects();
        if ($seed) {
            $ws->disable_cache();
        }
        try {
            $projArray = $ws->by_field($field);
        } catch (Exception $ex) {
            return;
        }
        if (!count($projArray)) {
            return;
        }

        if ($return == 'array') {
            return $projArray;
        }

        // sortiere nach Erscheinungsdatum
        $firstItem = reset($projArray);
        if ($firstItem && isset($firstItem->attributes['relation right seq'])) {
            //if (array_key_exists('relation right seq', reset($projArray)->attributes)) {
            $sortby = 'relation right seq';
            $orderby = $sortby;
        } else {
            $sortby = null;
            $orderby = __('O.A.', 'fau-cris');
        }
        $formatter = new CRIS_formatter(null, null, $sortby, SORT_ASC);
        $res = $formatter->execute($projArray);
        $projList = $res[$orderby] ?? [];

        if ($this->cms == 'wp' && shortcode_exists('collapsibles')) {
            $output = $this->make_accordion($projList);
        } else {
            $output = $this->make_list($projList);
        }
        return $output;
    }

    public function fieldPersons($field)
    {
        $ws = new CRIS_projects();
        try {
            $projArray = $ws->by_field($field);
        } catch (Exception $ex) {
            return;
        }
        if (!count($projArray)) {
            return;
        }
        $persList = array();
        foreach ($projArray as $project) {
            $project = (array) $project;
            foreach ($project['attributes'] as $attribut => $v) {
                $project[$attribut] = $v;
            }
            unset($project['attributes']);
            $leaderIDs = explode(",", $project['relpersidlead']);
            $collIDs = explode(",", $project['relpersidcoll']);
            $persons[$project['ID']] = $this->get_project_persons($project['ID'], $leaderIDs, $collIDs);
            foreach ($persons as $project) {
                foreach ($project as $type => $person) {
                    foreach ($person as $id => $details) {
                        $persList[$id] = $details;
                    }
                }
            }
        }
        return $persList;
    }

    public function get_project_leaders($project, $leadIDs): array
    {
        $leaders = array();
        $leadersString = CRIS_Dicts::$base_uri . "getrelated/Project/" . $project . "/proj_has_card";
        $leadersXml = Tools::XML2obj($leadersString);
        if (!is_wp_error($leadersXml) && !empty($leadersXml->infoObject)) {
            $i = 0;
            foreach ($leadersXml->infoObject as $person) {
                foreach ($person->attribute as $persAttribut) {
                    if ($persAttribut['name'] == 'lastName') {
                        $leaders[$i]['lastname'] = (string) $persAttribut->data;
                    }
                    if ($persAttribut['name'] == 'firstName') {
                        $leaders[$i]['firstname'] = (string) $persAttribut->data;
                    }
                }
                foreach ($person->relation as $persRel) {
                    foreach ($persRel->attribute as $persRelAttribute) {
                        if ($persRelAttribute['name'] == 'Right seq') {
                            $leaders[$i]['order'] = (string) $persRelAttribute->data;
                        }
                    }
                }
                $i++;
            }
        }
        usort($leaders, function ($a, $b) {
            return $a['order'] <=> $b['order'];
        });
        if (count($leadIDs) == count($leaders)) {
            $leaders = array_combine($leadIDs, $leaders);
        } else {
            $leaders = $leaders;
        }
        return $leaders;
    }

    public function get_project_members($project, $collIDs): array
    {
        $members = array();
        $membersString = CRIS_Dicts::$base_uri . "getrelated/Project/" . $project . "/proj_has_col_card";
        $membersXml = Tools::XML2obj($membersString);
        if (!is_wp_error($membersXml) && !empty($membersXml->infoObject)) {
            $i = 0;
            foreach ($membersXml->infoObject as $person) {
                foreach ($person->attribute as $persAttribut) {
                    if ($persAttribut['name'] == 'lastName') {
                        $members[$i]['lastname'] = (string) $persAttribut->data;
                    }
                    if ($persAttribut['name'] == 'firstName') {
                        $members[$i]['firstname'] = (string) $persAttribut->data;
                    }
                }
                foreach ($person->relation as $persRel) {
                    foreach ($persRel->attribute as $persRelAttribute) {
                        if ($persRelAttribute['name'] == 'Right seq') {
                            $members[$i]['order'] = (string) $persRelAttribute->data;
                        }
                    }
                }
                $i++;
            }
            usort($members, function ($a, $b) {
                return $a['order'] <=> $b['order'];
            });
        }
        if (count($collIDs) == count($members)) {
            $members = array_combine($collIDs, $members);
        }
        return $members;
    }

    public function get_project_persons($project, $leadIDs, $collIDs): array
    {
        $persons = array();

        $persons['leaders'] = $this->get_project_leaders($project, $leadIDs);
        $persons['members'] = $this->get_project_members($project, $collIDs);

        return $persons;
    }

    private function get_project_funding($project): array
    {
        $funding = array();
        $fundingString = CRIS_Dicts::$base_uri . "getrelated/Project/" . $project . "/proj_has_fund";
        $fundingXml = Tools::XML2obj($fundingString);
        if (!is_wp_error($fundingXml) && !empty($fundingXml->infoObject)) {
            foreach ($fundingXml->infoObject as $fund) {
                $_v = (string) $fund['id'];
                foreach ($fund->attribute as $fundAttribut) {
                    if ($fundAttribut['name'] == 'Name') {
                        $funding[$_v] = (string) $fundAttribut->data;
                    }
                }
            }
        }
        return $funding;
    }

    private function get_project_publications($project = null, $param = array()): string
    {
        require_once( 'class_Publikationen.php' );
        $liste = new Publikationen('project', $project);
        $args = array();
        foreach ($param as $_k => $_v) {
            if (substr($_k, 0, 13) == 'publications_') {
                $args[substr($_k, 13)] = $_v;
            }
        }
        //      $args['sc_type'] = 'default';
        //      $args['quotation'] = $param['quotation'];
        //      $args['display_language'] = $param['display_language'];
        //        $args['showimage'] = $param['showimage'];
        //        $args['image_align'] = $param['image_align'];
        //        $args['image_position'] = $param['image_position'];
        $param['format'] = $param['publications_format'];
        $param['sc_type'] = 'default';
        if ($param['publications_orderby'] == 'year') {
            return $liste->pubNachJahr($param, $param['project'], '', false, $param['project']);
        }
        if ($param['publications_orderby'] == 'type') {
            return $liste->pubNachTyp($param, $param['project'], '', false, $param['project']);
        }
        return $liste->projectPub($param);
    }

    private function get_project_images($project): array
    {
        $images = array();
        $imgString = CRIS_Dicts::$base_uri . "getrelated/project/" . $project . "/PROJ_has_PICT";
        $imgXml = Tools::XML2obj($imgString);

        if (!is_wp_error($imgXml) && isset($imgXml['size']) && $imgXml['size'] != 0) {
            foreach ($imgXml as $img) {
                $_i = new CRIS_project_image($img);
                $images[$_i->ID] = $_i;
            }
        }
        return $images;
    }
}

class CRIS_projects extends CRIS_webservice
{
    /*
     * projects requests
     */

    public function by_orga_id($orgaID = null, &$filter = null): array
    {
        if ($orgaID === null || $orgaID === "0") {
            return new \WP_Error(
                'cris-orgid-error',
                __('Bitte geben Sie die CRIS-ID der Organisation, Person oder des Projektes an.', 'fau-cris')
            );
        }

        if (!is_array($orgaID)) {
            $orgaID = array($orgaID);
        }

        $requests = array();
        foreach ($orgaID as $_o) {
            $requests[] = sprintf("getautorelated/Organisation/%d/ORGA_2_PROJ_1", $_o);
            $requests[] = sprintf("getrelated/Organisation/%d/PROJ_has_int_ORGA", $_o);
        }
        return $this->retrieve($requests, $filter);
    }

    public function by_pers_id($persID = null, &$filter = null, $role = 'all'): array
    {
        if ($persID === null || $persID === "0") {
            return new \WP_Error(
                'cris-orgid-error',
                __('Bitte geben Sie die CRIS-ID der Organisation, Person oder des Projektes an.', 'fau-cris')
            );
        }

        if (!is_array($persID)) {
            $persID = array($persID);
        }

        $requests = array();
        foreach ($persID as $_p) {
            if ($role == 'leader') {
                $requests[] = sprintf('getautorelated/Person/%s/PERS_2_PROJ_1', $_p);
            } elseif ($role == 'member') {
                $requests[] = sprintf('getautorelated/Person/%s/PERS_2_PROJ_2', $_p);
            } else {
                $requests[] = sprintf('getautorelated/Person/%s/PERS_2_PROJ_1', $_p);
                $requests[] = sprintf('getautorelated/Person/%s/PERS_2_PROJ_2', $_p);
            }
        }
        return $this->retrieve($requests, $filter);
    }

    public function by_id($projID = null): array
    {
        if ($projID === null || $projID === "0") {
            return new \WP_Error(
                'cris-orgid-error',
                __('Bitte geben Sie die CRIS-ID der Organisation, Person oder des Projektes an.', 'fau-cris')
            );
        }

        if (!is_array($projID)) {
            $projID = array($projID);
        }

        $requests = array();
        foreach ($projID as $_p) {
            $requests[] = sprintf('get/Project/%d', $_p);
        }
        return $this->retrieve($requests);
    }

    public function by_field($fieldID = null): array
    {
        if ($fieldID === null || $fieldID === "0") {
            return new \WP_Error(
                'cris-orgid-error',
                __('Bitte geben Sie die CRIS-ID der Organisation, Person oder des Projektes an.', 'fau-cris')
            );
        }

        if (!is_array($fieldID)) {
            $fieldID = array($fieldID);
        }

        $requests = array();
        foreach ($fieldID as $_f) {
            $requests[] = sprintf('getrelated/Forschungsbereich/%d/fobe_has_proj', $_f);
            $requests[] = sprintf('getrelated/Forschungsbereich/%d/fobe_fac_has_proj', $_f);
        }
        return $this->retrieve($requests);
    }

    public function by_pub($pubID = null): array
    {
        if ($pubID === null || $pubID === "0") {
            return new \WP_Error(
                'cris-orgid-error',
                __('Bitte geben Sie die CRIS-ID der Organisation, Person oder des Projektes an.', 'fau-cris')
            );
        }

        if (!is_array($pubID)) {
            $pubID = array($pubID);
        }

        $requests = array();
        foreach ($pubID as $_f) {
            $requests[] = sprintf('getrelated/Publication/%d/PROJ_has_PUBL', $_f);
        }
        return $this->retrieve($requests);
    }

    private function retrieve($reqs, &$filter = null): array
    {
        if ($filter !== null && !$filter instanceof CRIS_filter) {
            $filter = new CRIS_filter($filter);
        }

        $data = array();
        foreach ($reqs as $_i) {
            $_data = $this->get($_i, $filter);
            if (!is_wp_error($_data)) {
                $data[] = $_data;
            }
        }

        $projects = array();

        foreach ($data as $_d) {
            foreach ($_d as $project) {
                $a = new CRIS_project($project);
                if ($a->ID) {
                    $a->attributes['startyear'] = mb_substr($a->attributes['cfstartdate'], 0, 4);
                    $a->attributes['endyear'] = mb_substr($a->attributes['virtualenddate'], 0, 4);
                    //$a->attributes['endyear'] = $a->attributes['cfenddate'] != '' ? mb_substr($a->attributes['cfenddate'], 0, 4) : mb_substr($a->attributes['virtualenddate'], 0, 4);
                }
                if ($a->ID && ($filter === null || $filter->evaluate($a))) {
                    $projects[$a->ID] = $a;
                }
            }
        }

        return $projects;
    }
}

class CRIS_project extends CRIS_Entity
{
    /*
     * object for single award
     */

    public function __construct($data)
    {
        parent::__construct($data);
    }
}

class CRIS_project_image extends CRIS_Entity
{
    /*
     * object for single project image
     */

    public function __construct($data)
    {
        parent::__construct($data);

        foreach ($data->relation as $_r) {
            if ($_r['type'] != "PROJ_has_PICT") {
                continue;
            }
            foreach ($_r->attribute as $_a) {
                if ($_a['name'] == 'description') {
                    $this->attributes["description"] = (string) $_a->data;
                }
            }
        }
    }
}
