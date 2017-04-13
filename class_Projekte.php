<?php

require_once("class_Tools.php");
require_once("class_Webservice.php");
require_once("class_Filter.php");
require_once("class_Formatter.php");

class Projekte {

    private $options;
    public $output;

    public function __construct($einheit = '', $id = '') {

        if (strpos($_SERVER['PHP_SELF'], "vkdaten/tools/")) {
            $this->cms = 'wbk';
            $this->options = CRIS::ladeConf();
            $this->pathPersonenseiteUnivis = $this->options['Pfad_Personenseite_Univis'] . '/';
        } else {
            $this->cms = 'wp';
            $this->options = (array) get_option('_fau_cris');
            $this->pathPersonenseiteUnivis = '/person/';
        }
        $this->orgNr = $this->options['cris_org_nr'];
        $this->suchstring = '';
        $this->univis = NULL;

        $this->order = $this->options['cris_project_order'];
        $this->cris_project_link = isset($this->options['cris_project_link']) ? $this->options['cris_project_link'] : 'none';
        if ($this->cms == 'wbk' && $this->cris_project_link == 'person') {
            $this->univis = Tools::get_univis();
        }

        if ((!$this->orgNr || $this->orgNr == 0) && $id == '') {
            print '<p><strong>' . __('Bitte geben Sie die CRIS-ID der Organisation, Person oder Publikation an.', 'fau-cris') . '</strong></p>';
            return;
        }
        if (in_array($einheit, array("person", "orga", "award", "awardnameid", "project"))) {
            $this->id = $id;
            $this->einheit = $einheit;
        } else {
            // keine Einheit angegeben -> OrgNr aus Einstellungen verwenden
            $this->id = $this->orgNr;
            $this->einheit = "orga";
        }

    }

    /*
     * Ausgabe aller Projekte ohne Gliederung
     */

    public function projListe($year = '', $start = '', $type = '', $items = '', $hide = '', $role = 'leader') {
        $projArray = $this->fetch_projects($year, $start, $type, $role);

        if (!count($projArray)) {
            $output = '<p>' . __('Es wurden leider keine Projekte gefunden.', 'fau-cris') . '</p>';
            return $output;
        }
        $hide = explode(',', $hide);

        // sortiere nach Erscheinungsdatum
        $order = "cfstartdate";
        $formatter = new CRIS_formatter(NULL, NULL, $order, SORT_DESC);
        $res = $formatter->execute($projArray);
        if ($items != '')
            $projList = array_slice($res[$order], 0, $items);
        else
            $projList = $res[$order];

        $output = '';

        $output .= $this->make_list($projList, $hide);

        return $output;
    }

    /*
     * Ausgabe aller Projekte nach Jahren gegliedert
     */

    public function projNachJahr($year = '', $start = '', $type = '', $hide = '', $role = '', $content = '') {
        $projArray = $this->fetch_projects($year, $start, $type, $role);

        if (!count($projArray)) {
            $output = '<p>' . __('Es wurden leider keine Projekte gefunden.', 'fau-cris') . '</p>';
            return $output;
        }
        $hide = explode(',', $hide);

        // sortiere nach Erscheinungsjahr, innerhalb des Jahres nach Erstautor
        $formatter = new CRIS_formatter("startyear", SORT_DESC, "cftitle", SORT_ASC);
        $projList = $formatter->execute($projArray);

        $output = '';
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
        return $output;
    }

    /*
     * Ausgabe aller Publikationen nach Publikationstypen gegliedert
     */

    public function projNachTyp($year = '', $start = '', $type = '', $hide = '', $role = '', $content = '') {
        $projArray = $this->fetch_projects($year, $start, $type, $role);

        if (!count($projArray)) {
            $output = '<p>' . __('Es wurden leider keine Projekte gefunden.', 'fau-cris') . '</p>';
            return $output;
        }
        $hide = explode(',', $hide);

        // Publikationstypen sortieren
        $order = $this->order;
        if ($order[0] != '' && array_search($order[0], array_column(CRIS_Dicts::$projects, 'short'))) {
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
        foreach ($projList as $array_type => $projects) {
            // Zwischenüberschrift (= Projecttyp), außer wenn nur ein Typ gefiltert wurde
            if (empty($type)) {
                $title = Tools::getTitle('projects', $array_type, get_locale());
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
        return $output;
    }

    /*
     * Ausgabe eines einzelnen Projektes
     */

    public function singleProj($hide = '', $quotation = '') {
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

        $hide = explode(',', $hide);
        if (is_array($this->id)) {
            $output = $this->make_list($projArray, $hide);
        } else {
            $output = $this->make_single($projArray, $hide, $quotation);
        }

        return $output;
    }

    /*
     * Ausgabe eines Projektes per Custom-Shortcode
     */

    public function customProj($content = '', $quotation = '') {
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

        $output = $this->make_custom_single($projArray, $content, $quotation);
        return $output;
    }

    /* =========================================================================
     * Private Functions
      ======================================================================== */

    /*
     * Holt Daten vom Webservice je nach definierter Einheit.
     */

    private function fetch_projects($year = '', $start = '', $type = '', $role = 'leader') {
        $filter = Tools::project_filter($year, $start, $type);

        $ws = new CRIS_projects();
        $awardArray = array();

        try {
            if ($this->einheit === "orga") {
                $awardArray = $ws->by_orga_id($this->id, $filter);
            }
            if ($this->einheit === "person") {
                $awardArray = $ws->by_pers_id($this->id, $filter, $role);
            }
        } catch (Exception $ex) {
            $awardArray = array();
        }
        return $awardArray;
    }

    /*
     * Ausgabe der Projekte
     */

    private function make_custom_single($projects, $custom_text, $quotation = '') {
        $lang = strpos(get_locale(), 'de') === 0 ? 'de' : 'en';
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

            $proj_details['#title#'] = ($lang == 'en' && !empty($project['cftitle_en'])) ? $project['cftitle_en'] : $project['cftitle'];
            $proj_details['#type#'] = Tools::getName('projects', $project['project type'], get_locale());
            $proj_details['#parentprojecttitle#'] = ($lang == 'en' && !empty($project['parentprojecttitle_en'])) ? $project['parentprojecttitle_en'] : $project['parentprojecttitle'];
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
            setlocale(LC_TIME, get_locale());
            $start = $project['cfstartdate'];
            $proj_details['#start#']= strftime('%x', strtotime($start));
            $end = $project['virtualenddate'];
            $proj_details['#end#'] = strftime('%x', strtotime($end));
            $funding = $this->get_project_funding($id);
            $proj_details['#funding#'] = implode(', ', $funding);
            $proj_details['#url#'] = $project['cfuri'];
            $proj_details['#acronym#'] = $project['cfacro'];
            $description = ($lang == 'en' && !empty($project['cfabstr_en'])) ? $project['cfabstr_en'] : $project['cfabstr'];
            $proj_details['#description#'] = strip_tags($description, '<br><br/><a>');
            $proj_details['#publications#'] = $this->get_project_publications($id, $quotation);
            $proj_details['#image1#'] = '';
            if (count($imgs)) {
                $i = 1;
                foreach($imgs as $img) {
                    if (isset($img->attributes['png180']) && strlen($img->attributes['png180']) > 30) {
                        $proj_details["#image$i#"] = "<div class=\"cris-image\">";
                        $proj_details["#image$i#"] .= "<p><img alt=\"". $img->attributes['_short description'] ."\" src=\"data:image/PNG;base64," . $img->attributes['png180'] . "\" width=\"180\" height=\"180\"><br />"
                        . "<span class=\"wp-caption-text\">" . (($img->attributes['description'] !='') ? $img->attributes['description'] : "") . "</span></p>";
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

    private function make_custom_list($projects, $custom_text) {
        $lang = strpos(get_locale(), 'de') === 0 ? 'de' : 'en';
        $projlist = '';
        $projlist .= "<ul class=\"cris-projects\">";

        foreach ($projects as $project) {
            $project = (array) $project;
            foreach ($project['attributes'] as $attribut => $v) {
                $project[$attribut] = $v;
            }
            unset($project['attributes']);

            $id = $project['ID'];
            $imgs = self::get_project_images($project['ID']);

            $proj_details = array();
            $proj_details['#title#'] = ($lang == 'en' && !empty($project['cftitle_en'])) ? $project['cftitle_en'] : $project['cftitle'];
            $proj_details['#type#'] = Tools::getName('projects', $project['project type'], get_locale());
            $proj_details['#parentprojecttitle#'] = ($lang == 'en' && !empty($project['parentprojecttitle_en'])) ? $project['parentprojecttitle_en'] : $project['parentprojecttitle'];
            setlocale(LC_TIME, get_locale());
            $start = $project['cfstartdate'];
            $proj_details['#start#'] = strftime('%x', strtotime($start));
            $end = $project['virtualenddate'];
            $proj_details['#end#'] = strftime('%x', strtotime($end));
            $funding = $this->get_project_funding($id);
            $proj_details['#funding#'] = implode(', ', $funding);
            $proj_details['#url#'] = $project['cfuri'];
            $proj_details['#acronym#'] = $project['cfacro'];
            $description = ($lang == 'en' && !empty($project['cfabstr_en'])) ? $project['cfabstr_en'] : $project['cfabstr'];
            $proj_details['#description#'] = strip_tags($description, '<br><br/><a>');
            $proj_details['#image1#'] = '';
            if (count($imgs)) {
                $i = 1;
                foreach($imgs as $img) {
                    $proj_details["#image$i#"] = "<div class=\"cris-image\">";
                    if (isset($img->attributes['png180']) && strlen($img->attributes['png180']) > 30) {
                       $proj_details["#image$i#"] .= "<p><img alt=\"". $img->attributes['_short description'] ."\" src=\"data:image/PNG;base64," . $img->attributes['png180'] . "\" width=\"180\" height=\"180\"><br />"
                        . "<span class=\"wp-caption-text\">" . (($img->attributes['description'] !='') ? $img->attributes['description'] : "") . "</span></p>";
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

    private function make_single($projects, $hide = array(), $quotation = '') {

        $lang = strpos(get_locale(), 'de') === 0 ? 'de' : 'en';
        $projlist = '';
        $projlist .= "<div class=\"cris-projects\">";

        foreach ($projects as $project) {
            $project = (array) $project;
            foreach ($project['attributes'] as $attribut => $v) {
                $project[$attribut] = $v;
            }
            unset($project['attributes']);

            $id = $project['ID'];
            $title = ($lang == 'en' && !empty($project['cftitle_en'])) ? $project['cftitle_en'] : $project['cftitle'];
            $type = Tools::getName('projects', $project['project type'], get_locale());
            $imgs = self::get_project_images($project['ID']);

            if (count($imgs)) {
                $projlist .= "<div class=\"cris-image\">";
                foreach($imgs as $img) {
                    if (isset($img->attributes['png180']) && strlen($img->attributes['png180']) > 30) {
                       $projlist .= "<p><img alt=\"". $img->attributes['_short description'] ."\" src=\"data:image/PNG;base64," . $img->attributes['png180'] . "\" width=\"180\" height=\"180\"><br />"
                        . "<span class=\"wp-caption-text\">" . (($img->attributes['description'] !='') ? $img->attributes['description'] : "") . "</span></p>";
                    }
                }
                $projlist .= "</div>";
            }

            if (!in_array('title', $hide)) {
                $projlist .= "<h3>" . $title . "</h3>";
            }

            if (!empty($type))
                $projlist .= "<p class=\"project-type\">(" . $type . ")</p>";

            if (!in_array('details', $hide)) {
                $parentprojecttitle = ($lang == 'en' && !empty($project['parentprojecttitle_en'])) ? $project['parentprojecttitle_en'] : $project['parentprojecttitle'];
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
                setlocale(LC_TIME, get_locale());
                $start = $project['cfstartdate'];
                $start = strftime('%x', strtotime($start));
                $end = $project['virtualenddate'];
                $end = strftime('%x', strtotime($end));
                $funding = $this->get_project_funding($id);
                $url = $project['cfuri'];
                $acronym = $project['cfacro'];

                $projlist .= "<p class=\"project-details\">";
                if (!empty($parentprojecttitle))
                    $projlist .= "<b>" . __('Titel des Gesamtprojektes', 'fau-cris') . ': </b>' . $parentprojecttitle;
                if (!empty($leaders)) {
                    $projlist .= "<br /><b>" . __('Projektleitung', 'fau-cris') . ': </b>';
                    $projlist .= implode(', ', $leaders);
                }
                if (!empty($members)) {
                    $projlist .= "<br /><b>" . __('Projektbeteiligte', 'fau-cris') . ': </b>';
                    $projlist .= implode(', ', $members);
                }
                if (!empty($start))
                    $projlist .= "<br /><b>" . __('Projektstart', 'fau-cris') . ': </b>' . $start;
                if (!empty($end))
                    $projlist .= "<br /><b>" . __('Projektende', 'fau-cris') . ': </b>' . $end;
                if (!empty($acronym))
                    $projlist .= "<br /><b>" . __('Akronym', 'fau-cris') . ": </b>" . $acronym;
                if (!empty($funding)) {
                    $projlist .= "<br /><b>" . __('Mittelgeber', 'fau-cris') . ': </b>';
                    $projlist .= implode(', ', $funding);
                }
                if (!empty($url))
                    $projlist .= "<br /><b>" . __('URL', 'fau-cris') . ": </b><a href=\"" . $url . "\">" . $url . "</a>";
                $projlist .= "</p>";
            }

            if (!in_array('abstract', $hide)) {
                $description = ($lang == 'en' && !empty($project['cfabstr_en'])) ? $project['cfabstr_en'] : $project['cfabstr'];
                $description = strip_tags($description, '<br><br/><a><sup><sub>');
                if ($description)
                    $projlist .= "<h4>" . __('Abstract', 'fau-cris') . ": </h4>" . "<p class=\"project-description\">" . $description . '</p>';
            }
            if (!in_array('publications', $hide)) {
                $publications = $this->get_project_publications($id, $quotation);
                if($publications)
                    $projlist .= "<h4>" . __('Publikationen', 'fau-cris') . ": </h4>" . $publications;
            }
        }
        $projlist .= "</div>";
        return $projlist;
    }

    private function make_list($projects, $hide = array(), $showtype = 1) {

        global $post;
        $lang = strpos(get_locale(), 'de') === 0 ? 'de' : 'en';
        $projlist = '';
        $projlist .= "<ul class=\"cris-projects\">";

        foreach ($projects as $project) {
            $project = (array) $project;
            foreach ($project['attributes'] as $attribut => $v) {
                $project[$attribut] = $v;
            }
            unset($project['attributes']);

            $id = $project['ID'];
            $title = ($lang == 'en' && !empty($project['cftitle_en'])) ? $project['cftitle_en'] : $project['cftitle'];
            $type = Tools::getName('projects', $project['project type'], get_locale());

            $projlist .= "<li>";
            $projlist .= "<span class=\"project-title\">" . $title . "</span>";
            if (!empty($type) && $showtype == 1)
                $projlist .= "<br />(" . $type . ")";

            if (!in_array('details', $hide)) {
                $parentprojecttitle = ($lang == 'en' && !empty($project['parentprojecttitle_en'])) ? $project['parentprojecttitle_en'] : $project['parentprojecttitle'];
                $acronym = $project['cfacro'];
                $start = $project['cfstartdate'];
                $end = $project['virtualenddate'];
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
                if (!empty($parentprojecttitle))
                    $projlist .= "<b>" . __('Titel des Gesamtprojektes', 'fau-cris') . ': </b>' . $parentprojecttitle;
                if (isset($leaders) && !empty($leaders)) {
                    $projlist .= "<br /><b>" . __('Projektleitung', 'fau-cris') . ': </b>';
                    $projlist .= implode(', ', $leaders);
                }
                if (!empty($date))
                    $projlist .= "<br /><b>" . __('Laufzeit', 'fau-cris') . ': </b>' . $date;
                if (!empty($funding)) {
                    $projlist .= "<br /><b>" . __('Mittelgeber', 'fau-cris') . ': </b>';
                    $projlist .= implode(', ', $funding);
                }
                if (!empty($url))
                    $projlist .= "<br /><b>" . __('URL', 'fau-cris') . ": </b><a href=\"" . $url . "\">" . $url . "</a>";
                $projlist .= "</div>";
            }

            if (!in_array('abstract', $hide) && !empty($description)) {
                $description = ($lang == 'en' && !empty($project['cfabstr_en'])) ? $project['cfabstr_en'] : $project['cfabstr'];
                $description = strip_tags($description, '<br><br/><a>');

                $projlist .= "<div>"
                        . "<div class=\"abstract-title\"><a title=\"" . __('Abstract anzeigen', 'fau-cris') . "\">" . __('Abstract', 'fau-cris') . "</a> </div>"
                        . "<div class=\"abstract\">" . $description . '</div>'
                        . '</div>';
            }
            if (!in_array('link', $hide) && !empty($id)) {
                $link = "https://cris.fau.de/converis/publicweb/Project/" . $id . ($lang == 'de' ? '?lang=2' : '?lang=1');
                if ($this->cms == 'wp') {
                    $proj_pages = get_pages(array('child_of' => $post->ID, 'post_status' => 'publish'));
                    $page_proj = array();
                    foreach ($proj_pages as $proj_page) {
                        if ($proj_page->post_title == $title && !empty($proj_page->guid)) {
                            $page_proj[] = $proj_page;
                        }
                    }
                    if (count($page_proj)) {
                        $link = $page_proj[0]->guid;
                    } else {
                        $page = get_page_by_title($title);
                        if ($page && !empty($page->guid)) {
                            $link = $page->guid;
                        }
                    }
                }
                $projlist .= "<div>" . "<a href=\"" . $link . "\">" . __('Mehr Informationen', 'fau-cris') . "</a> &#8594; </div>";
            }
            $projlist .= "</li>";
        }
        $projlist .= "</ul>";

        return $projlist;
    }

    private function make_accordion($projects, $hide = array(), $showtype = 1) {
        global $post;

        $lang = strpos(get_locale(), 'de') === 0 ? 'de' : 'en';
        $lang_key = ($lang == 'en') ? '_en' : '';
        $projlist = '';
        $projlist .=  '[collapsibles]';

        foreach ($projects as $project) {
            $project = (array) $project;
            foreach ($project['attributes'] as $attribut => $v) {
                $project[$attribut] = $v;
            }
            unset($project['attributes']);

            $id = $project['ID'];
            $acronym = $project['cfacro'];
            $title = ($lang == 'en' && !empty($project['cftitle_en'])) ? $project['cftitle_en'] : $project['cftitle'];
            $type = Tools::getName('projects', $project['project type'], get_locale());
            $description = $project['cfabstr'.$lang_key];
            $description = strip_tags($description, '<br><br/><a>');
            $pos = strpos($description, ' ', 500);
            $description = substr($description, 0, $pos) . '&hellip;';
            if (!empty($project['kurzbeschreibung'.$lang_key])) {
                $description = $project['kurzbeschreibung'.$lang_key];
            }


            $projlist .= "[collapse title=\"" . ((!empty($acronym)) ? $acronym . ": " : "") . $title . "\"]";
            if (!in_array('abstract', $hide) && !empty($description)) {
                $projlist .= "<p class=\"abstract\">" . $description . '</p>';
            }
            if (!in_array('link', $hide) && !empty($id))
                $link = "https://cris.fau.de/converis/publicweb/Project/" . $id . ($lang == 'de' ? '?lang=2' : '?lang=1');
                if ($this->cms == 'wp') {
                    $proj_pages = get_pages(array('child_of' => $post->ID, 'post_status' => 'publish'));
                    $page_proj = array();
                    foreach ($proj_pages as $proj_page) {
                        if ($proj_page->post_title == $title && !empty($proj_page->guid)) {
                            $page_proj[] = $proj_page;
                        }
                    }
                    if (count($page_proj)) {
                        $link = $page_proj[0]->guid;
                    } else {
                        $page = get_page_by_title($title);
                        if ($page && !empty($page->guid)) {
                            $link = $page->guid;
                        }
                    }
                }
                $projlist .= "<p>" . "&#8594; <a href=\"" . $link . "\">" . __('Mehr Informationen', 'fau-cris') . "</a> </p>";
            $projlist .= "[/collapse]";
        }
        $projlist .= "[/collapsibles]";

        return do_shortcode($projlist);
    }

    public function fieldProj($field, $return = 'list', $seed=false) {
        $ws = new CRIS_projects();
        if($seed)
            $ws->disable_cache();
        try {
            $projArray = $ws->by_field($field);
        } catch (Exception $ex) {
            return;
        }
        if (!count($projArray))
            return;

        if ($return == 'array')
            return $projArray;

        if ( $this->cms == 'wp' && shortcode_exists( 'collapsibles' ) ) {
            $output = $this->make_accordion($projArray);
        } else {
            $output = $this->make_list($projArray);
        }
        return $output;
    }

    public function fieldPersons($field) {
        $ws = new CRIS_projects();
        try {
            $projArray = $ws->by_field($field);
        } catch (Exception $ex) {
            return;
        }
        if (!count($projArray))
            return;
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
                        $persList[$type][$id] = $details;
                    }
                }
            }
        }
        return $persList;
    }

    public function get_project_leaders($project, $leadIDs) {
        $leaders = array();
        $leadersString = CRIS_Dicts::$base_uri . "getrelated/Project/" . $project . "/proj_has_card";
        $leadersXml = Tools::XML2obj($leadersString);
        if ($leadersXml['size'] != 0) {
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
                $i++;
            }
        }
        if (count($leadIDs) == count($leaders)) {
            $leaders = array_combine($leadIDs, $leaders);
        } else {
            $leaders = $leaders;
        }
        return $leaders;
    }

    public function get_project_members($project, $collIDs) {
        $members = array();
        $membersString = CRIS_Dicts::$base_uri . "getrelated/Project/" . $project . "/proj_has_col_card";
        $membersXml = Tools::XML2obj($membersString);
        if ($membersXml['size'] != 0) {
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
                $i++;
            }
        }
        if (count($collIDs) == count($members)) {
            $members = array_combine($collIDs, $members);
        }
        return $members;
    }

    public function get_project_persons($project, $leadIDs, $collIDs) {
        $persons = array();

        $persons['leaders'] = $this->get_project_leaders($project, $leadIDs);
        $persons['members'] = $this->get_project_members($project, $collIDs);

        return $persons;
    }

    private function get_project_funding($project) {
        $funding = array();
        $fundingString = CRIS_Dicts::$base_uri . "getrelated/Project/" . $project . "/proj_has_fund";
        $fundingXml = Tools::XML2obj($fundingString);
        if ($fundingXml['size'] != 0) {
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

    private function get_project_publications($project = NULL, $quotation = '') {
        require_once('class_Publikationen.php');
        $liste = new Publikationen();
        return $liste->projectPub($project, $quotation);
    }

    private function get_project_images($project) {
        $images = array();
        $imgString = CRIS_Dicts::$base_uri . "getrelated/project/" . $project . "/PROJ_has_PICT";
        $imgXml = Tools::XML2obj($imgString);

        if ($imgXml['size'] != 0) {
            foreach ($imgXml as $img) {
                $_i = new CRIS_project_image($img);
                $images[$_i->ID] = $_i;
            }
        }
        return $images;
    }

}

class CRIS_projects extends CRIS_webservice {
    /*
     * projects requests
     */

    public function by_orga_id($orgaID = null, &$filter = null) {
        if ($orgaID === null || $orgaID === "0")
            throw new Exception('Please supply valid organisation ID');

        if (!is_array($orgaID))
            $orgaID = array($orgaID);

        $requests = array();
        foreach ($orgaID as $_o) {
            $requests[] = sprintf("getautorelated/Organisation/%d/ORGA_2_PROJ_1", $_o);
        }
        return $this->retrieve($requests, $filter);
    }

    public function by_pers_id($persID = null, &$filter = null, $role = 'leader') {
        if ($persID === null || $persID === "0")
            throw new Exception('Please supply valid person ID');

        if (!is_array($persID))
            $persID = array($persID);

        $requests = array();
        foreach ($persID as $_p) {
            if ($role == 'leader') {
                $requests[] = sprintf('getautorelated/Person/%d/PERS_2_PROJ_1', $_p);
            } else {
                $requests[] = sprintf('getautorelated/Person/%d/PERS_2_PROJ_2', $_p);
            }
        }
        return $this->retrieve($requests, $filter);
    }

    public function by_id($projID = null) {
        if ($projID === null || $projID === "0")
            throw new Exception('Please supply valid project ID');

        if (!is_array($projID))
            $projID = array($projID);

        $requests = array();
        foreach ($projID as $_p) {
            $requests[] = sprintf('get/Project/%d', $_p);
        }
        return $this->retrieve($requests);
    }

    public function by_field($fieldID = null) {
        if ($fieldID === null || $fieldID === "0")
            throw new Exception('Please supply valid field of research ID');

        if (!is_array($fieldID))
            $fieldID = array($fieldID);

        $requests = array();
        foreach ($fieldID as $_f) {
            $requests[] = sprintf('getrelated/Forschungsbereich/%d/fobe_has_proj', $_f);
        }
        return $this->retrieve($requests);
    }

    private function retrieve($reqs, &$filter = null) {
        if ($filter !== null && !$filter instanceof CRIS_filter)
            $filter = new CRIS_filter($filter);

        $data = array();
        foreach ($reqs as $_i) {
            try {
                $data[] = $this->get($_i, $filter);
            } catch (Exception $e) {
                // TODO: logging?
                //echo $e->getMessage();
                continue;
            }
        }

        $projects = array();

        foreach ($data as $_d) {
            foreach ($_d as $project) {
                $a = new CRIS_project($project);
                if ($a->ID) {
                    $a->attributes['startyear'] = substr($a->attributes['cfstartdate'], 0, 4);
                    $a->attributes['endyear'] = substr($a->attributes['virtualenddate'], 0, 4);
                    //$a->attributes['endyear'] = $a->attributes['cfenddate'] != '' ? substr($a->attributes['cfenddate'], 0, 4) : substr($a->attributes['virtualenddate'], 0, 4);
                }
                if ($a->ID && ($filter === null || $filter->evaluate($a)))
                    $projects[$a->ID] = $a;
            }
        }

        return $projects;
    }
}

class CRIS_project extends CRIS_Entity {
    /*
     * object for single award
     */

    function __construct($data) {
        parent::__construct($data);
    }
}

class CRIS_project_image extends CRIS_Entity {
    /*
     * object for single project image
     */

    public function __construct($data) {
        parent::__construct($data);

        foreach ($data->relation as $_r) {
            if ($_r['type'] != "PROJ_has_PICT")
                continue;
            foreach($_r->attribute as $_a) {
                if ($_a['name'] == 'description') {
                    $this->attributes["description"] = (string) $_a->data;
                }
            }
        }
    }
}
