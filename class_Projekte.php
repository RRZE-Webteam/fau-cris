<?php

require_once("class_Tools.php");
require_once("class_Webservice.php");
require_once("class_Filter.php");
require_once("class_Formatter.php");

class Projekte {

    private $options;
    public $output;

    public function __construct($einheit = '', $id = '') {

        $this->cms = 'wp';
        $this->options = (array) get_option('_fau_cris');
        $this->orgNr = $this->options['cris_org_nr'];
        $this->order = isset($this->options['cris_project_order']) ? $this->options['cris_project_order'] : CRIS_Dicts::$projOrder;
        $this->cris_project_link = isset($this->options['cris_project_link']) ? $this->options['cris_project_link'] : 0;
        $this->pathPersonenseiteUnivis = '/person/';
        $this->suchstring = '';

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

        $univis = NULL;
        if ($this->cms == 'wbk' && $this->cris_award_link == 1) {
            //if ($this->cms == 'wbk' && $this->cris_award_link == 'person') {
            $this->univisID = Tools::get_univis_id();
            // Ich liebe UnivIS: Welche Abfrage liefert mehr Ergebnisse (hängt davon ab, wie die
            // Mitarbeiter der Institution zugeordnet wurden...)?
            $url1 = "http://univis.uni-erlangen.de/prg?search=departments&number=" . $this->univisID . "&show=xml";
            $daten1 = Tools::XML2obj($url1);
            $num1 = count($daten1->Person);
            $url2 = "http://univis.uni-erlangen.de/prg?search=persons&department=" . $this->univisID . "&show=xml";
            $daten2 = Tools::XML2obj($url2);
            $num2 = count($daten2->Person);
            $daten = $num1 > $num2 ? $daten1 : $daten2;

            foreach ($daten->Person as $person) {
                $univis[] = array('firstname' => (string) $person->firstname,
                    'lastname' => (string) $person->lastname);
            }
        }
        $this->univis = $univis;
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

    public function projNachJahr($year = '', $start = '', $type = '', $hide = '') {
        $projArray = $this->fetch_projects($year, $start, $type);

        if (!count($projArray)) {
            $output = '<p>' . __('Es wurden leider keine Projekte gefunden.', 'fau-cris') . '</p>';
            return $output;
        }

        // sortiere nach Erscheinungsjahr, innerhalb des Jahres nach Erstautor
        $formatter = new CRIS_formatter("startyear", SORT_DESC, "cftitle", SORT_ASC);
        $projList = $formatter->execute($projArray);

        $output = '';
        foreach ($projList as $array_year => $projects) {
            if (empty($year)) {
                $output .= '<h3>' . $array_year . '</h3>';
                //$output .= '<h3>' . substr($array_year, 0, 4) . '</h3>';
            }
            $output .= $this->make_list($projects, $hide);
        }
        return $output;
    }

    /*
     * Ausgabe aller Publikationen nach Publikationstypen gegliedert
     */

    public function projNachTyp($year = '', $start = '', $type = '', $hide = '') {
        $projArray = $this->fetch_projects($year, $start, $type);

        if (!count($projArray)) {
            $output = '<p>' . __('Es wurden leider keine Projekte gefunden.', 'fau-cris') . '</p>';
            return $output;
        }

        // Publikationstypen sortieren
        $order = $this->order;
        if ($order[0] != '' && array_key_exists($order[0], CRIS_Dicts::$projNames)) {
            foreach ($order as $key => $value) {
                $order[$key] = Tools::getProjName($value, "en");
            }
        } else {
            $order = array();
            foreach (CRIS_Dicts::$projOrder as $value) {
                $order[] = Tools::getProjName($value, "en");
            }
        }

        // sortiere nach Typenliste, innerhalb des Jahres nach Jahr abwärts sortieren
        $formatter = new CRIS_formatter("project type", array_values($order), "cfstartdate", SORT_DESC);
        $projList = $formatter->execute($projArray);

        $output = '';
        foreach ($projList as $array_type => $projects) {
            // Zwischenüberschrift (= Projecttyp), außer wenn nur ein Typ gefiltert wurde
            if (empty($type)) {
                $title = Tools::getProjTitle($array_type, get_locale());
                $output .= "<h3>";
                $output .= $title;
                $output .= "</h3>";
            }
            $output .= $this->make_list($projects, $hide, 0);
        }
        return $output;
    }

    /*
     * Ausgabe eines einzelnen Projektes
     */

    public function singleProj($hide = '') {
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

        $output = $this->make_single($projArray, $hide);

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
     * Ausgabe der Awards
     */

    private function make_single($projects, $hide = '') {

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
            $type = Tools::getprojTranslation($project['project type'], get_locale());
            $projlist .= "<h3>" . $title . "</h3>";

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
                $end = !empty($project['cfenddate']) ? $project['cfenddate'] : $project['virtualenddate'];
                $end = strftime('%x', strtotime($end));
                $funding = $this->get_project_funding($id);
                $url = $project['cfuri'];
                //$acronym = $project['cfacro'];

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
                $description = strip_tags($description, '<br><br/><a>');
                $projlist .= "<h4>" . __('Abstract', 'fau-cris') . ": </h4>" . "<p class=\"project-description\">" . $description . '</p>';
            }
            if (!in_array('publications', $hide)) {
                $publications = $this->get_project_publications($id, $quotation = '');
                $projlist .= "<h4>" . __('Publikationen', 'fau-cris') . ": </h4>" . $publications;
            }
        }
        $projlist .= "</div>";
        return $projlist;
    }

    private function make_list($projects, $hide, $showtype = 1) {

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
            $type = Tools::getprojTranslation($project['project type'], get_locale());

            $projlist .= "<li>";
            $projlist .= "<span class=\"project-title\">" . $title . "</span>";
            if (!empty($type) && $showtype == 1)
                $projlist .= "<br />(" . $type . ")";

            if (!in_array('details', $hide)) {
                $parentprojecttitle = ($lang == 'en' && !empty($project['parentprojecttitle_en'])) ? $project['parentprojecttitle_en'] : $project['parentprojecttitle'];
                //$acronym = $project['cfacro'];
                setlocale(LC_TIME, get_locale());
                $start = $project['cfstartdate'];
                $start = strftime('%x', strtotime($start));
                $end = !empty($project['cfenddate']) ? $project['cfenddate'] : $project['virtualenddate'];
                $end = strftime('%x', strtotime($end));
                $funding = $this->get_project_funding($id);
                $url = $project['cfuri'];

                $projlist .= "<div class=\"project-details\">";
                if (!empty($parentprojecttitle))
                    $projlist .= "<b>" . __('Titel des Gesamtprojektes', 'fau-cris') . ': </b>' . $parentprojecttitle . '<br />';
                if (!empty($start) || !empty($end))
                    $projlist .= "<b>" . __('Laufzeit', 'fau-cris') . ': </b>' . $start;
                if (!empty($end))
                    $projlist .= " &ndash; " . $end;
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
            if (!in_array('link', $hide) && !empty($id))
                $projlist .= "<div>" . "<a href=\"https://cris.fau.de/converis/publicweb/Project/" . $id . ($lang == 'de' ? '?lang=2' : '?lang=1') . "\" title=\"" . __('Zur Projektseite auf cris.fau.de wechseln', 'fau-cris') . "\">" . __('Mehr Informationen', 'fau-cris') . "</a> &#8594; </div>";
            $projlist .= "</li>";
        }
        $projlist .= "</ul>";

        return $projlist;
    }

    private function get_project_persons($project, $leadIDs, $collIDs) {
        $persons = array();
        $leaders = array();
        $members = array();

        $leadersString = "https://cris.fau.de/ws-cached/1.0/public/infoobject/getrelated/Project/" . $project . "/proj_has_card";
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
            $persons['leaders'] = array_combine($leadIDs, $leaders);
        } else {
            $persons['leaders'] = $leaders;
        }

        $membersString = "https://cris.fau.de/ws-cached/1.0/public/infoobject/getrelated/Project/" . $project . "/proj_has_col_card";
        $membersXml = Tools::XML2obj($membersString);
        if ($membersXml['size'] != 0) {
            $j = 0;
            foreach ($membersXml->infoObject as $person) {
                foreach ($person->attribute as $persAttribut) {
                    if ($persAttribut['name'] == 'lastName') {
                        $members[$j]['lastname'] = (string) $persAttribut->data;
                    }
                    if ($persAttribut['name'] == 'firstName') {
                        $members[$j]['firstname'] = (string) $persAttribut->data;
                    }
                }
                $j++;
            }
        }
        if (count($collIDs) == count($members)) {
            $persons['members'] = array_combine($collIDs, $members);
        } else {
            $persons['members'] = $members;
        }
        return $persons;
    }

    private function get_project_funding($project) {
        $funding = array();
        $fundingString = "https://cris.fau.de/ws-cached/1.0/public/infoobject/getrelated/Project/" . $project . "/proj_has_fund";
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

    private function retrieve($reqs, &$filter = null) {
        if ($filter !== null && !$filter instanceof CRIS_filter)
            $filter = new CRIS_filter($filter);

        $data = array();
        foreach ($reqs as $_i) {
            try {
                $data[] = $this->get($_i, $filter);
            } catch (Exception $e) {
                // TODO: logging?
                echo $e->getMessage();
                continue;
            }
        }

        $projects = array();

        foreach ($data as $_d) {
            foreach ($_d as $project) {
                $a = new CRIS_project($project);
                if ($a->ID) {
                    $a->attributes['startyear'] = substr($a->attributes['cfstartdate'], 0, 4);
                    $a->attributes['endyear'] = $a->attributes['cfenddate'] != '' ? substr($a->attributes['cfenddate'], 0, 4) : substr($a->attributes['virtualenddate'], 0, 4);
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
