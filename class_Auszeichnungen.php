<?php

require_once("class_Tools.php");
require_once("class_Webservice.php");
require_once("class_Filter.php");
require_once("class_Formatter.php");

class Auszeichnungen {

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


        $this->order = $this->options['cris_award_order'];
        $this->cris_award_link = isset($this->options['cris_award_link']) ? $this->options['cris_award_link'] : 'none';
        if ($this->cms == 'wbk' && $this->cris_award_link == 'person') {
            $this->univis = Tools::get_univis();
        }

        if ((!$this->orgNr || $this->orgNr == 0) && $id == '') {
            print '<p><strong>' . __('Bitte geben Sie die CRIS-ID der Organisation, Person oder Auszeichnung an.', 'fau-cris') . '</strong></p>';
            return;
        }
        if (in_array($einheit, array("person", "orga", "award", "awardnameid"))) {
            $this->id = $id;
            $this->einheit = $einheit;
        } else {
            // keine Einheit angegeben -> OrgNr aus Einstellungen verwenden
            $this->id = $this->orgNr;
            $this->einheit = "orga";
        }
    }

    /*
     * Ausgabe aller Auszeichnungen ohne Gliederung
     */

    public function awardsListe($year = '', $start = '', $type = '', $awardnameid = '', $showname = 1, $showyear = 1, $showawardname = 1, $display = 'list') {
        $awardArray = $this->fetch_awards($year, $start, $type, $awardnameid);

        if (!count($awardArray)) {
            $output = '<p>' . __('Es wurden leider keine Auszeichnungen gefunden.', 'fau-cris') . '</p>';
            return $output;
        }

        $order = "year award";
        $formatter = new CRIS_formatter(NULL, NULL, $order, SORT_DESC);
        $res = $formatter->execute($awardArray);
        $awardList = $res[$order];

        $output = '';

        if ($display == 'gallery') {
            $output .= $this->make_gallery($awardList, $showname, $showyear, $showawardname);
        } else {
            $output .= $this->make_list($awardList, $showname, $showyear, $showawardname);
        }

        return $output;
    }

    /*
     * Ausgabe aller Auszeichnungen nach Jahren gegliedert
     */

    public function awardsNachJahr($year = '', $start = '', $type = '', $awardnameid = '', $showname = 1, $showyear = 0, $showawardname = 1, $display = 'list', $order2 = 'year') {
        $awardArray = $this->fetch_awards($year, $start, $type, $awardnameid);

        if (!count($awardArray)) {
            $output = '<p>' . __('Es wurden leider keine Auszeichnungen gefunden.', 'fau-cris') . '</p>';
            return $output;
        }

        if ($order2 == 'name') {
            $formatter = new CRIS_formatter("year award", SORT_DESC, "exportnames", SORT_ASC);
        } else {
            $formatter = new CRIS_formatter("year award", SORT_DESC, "year award", SORT_ASC);
        }
        $awardList = $formatter->execute($awardArray);

        $output = '';

        foreach ($awardList as $array_year => $awards) {
            if (count($awards) < 1)
                return $output;
            if (empty($year)) {
                $output .= '<h3 class="clearfix clear">';
                $output .= $array_year;
                $output .= '</h3>';
            }
            if ($display == 'gallery') {
                $output .= $this->make_gallery($awards, $showname, $showyear, $showawardname);
            } else {
                $output .= $this->make_list($awards, $showname, $showyear, $showawardname);
            }
        }

        return $output;
    }

    /*
     * Ausgabe aller Auszeichnungen nach Auszeichnungstypen gegliedert
     */

    public function awardsNachTyp($year = '', $start = '', $type = '', $awardnameid = '', $showname = 1, $showyear = 0, $showawardname = 1, $display = '', $order2 = 'year') {
        $awardArray = $this->fetch_awards($year, $start, $type, $awardnameid);

        if (!count($awardArray)) {
            $output = '<p>' . __('Es wurden leider keine Auszeichnungen gefunden.', 'fau-cris') . '</p>';
            return $output;
        }

        // Auszeichnungstypen sortieren
        $order = $this->order;
        if ($order[0] != '' && array_search($order[0], array_column(CRIS_Dicts::$awards, 'short'))) {
            foreach ($order as $key => $value) {
                $order[$key] = Tools::getType('awards', $value);
            }
        } else {
            $order = Tools::getOrder('awards');
        }

        // sortiere nach Typenliste, innerhalb des Typs nach Name aufwärts sortieren
        if ($order2 == 'name') {
            $formatter = new CRIS_formatter("type of award", SORT_DESC, "award_preistraeger", SORT_ASC);
        } else {
            $formatter = new CRIS_formatter("type of award", SORT_DESC, "year award", SORT_DESC);
        }
        $awardList = $formatter->execute($awardArray);
        $output = '';

        foreach ($awardList as $array_type => $awards) {
            if (empty($type)) {
                $title = Tools::getTitle('awards', $array_type, get_locale());
                $output .= '<h3 class="clearfix clear">';
                $output .= $title;
                $output .= "</h3>";
            }
            if ($display == 'gallery') {
                $output .= $this->make_gallery($awards, $showname, $showyear, $showawardname);
            } else {
                $output .= $this->make_list($awards, $showname, $showyear, $showawardname);
            }
        }

        return $output;
    }

    /*
     * Ausgabe einer einzelnen Auszeichnung
     */

    public function singleAward($showname = 1, $showyear = 0, $showawardname = 1, $display = 'list') {
        $ws = new CRIS_awards();

        try {
            $awardArray = $ws->by_id($this->id);
        } catch (Exception $ex) {
            return;
        }

        if (!count($awardArray)) {
            $output = '<p>' . __('Es wurden leider keine Auszeichnungen gefunden.', 'fau-cris') . '</p>';
            return $output;
        }

        if ($display == 'gallery') {
            $output = $this->make_gallery($awardArray, $showname, $showyear, $showawardname);
        } else {
            $output = $this->make_list($awardArray, $showname, $showyear, $showawardname);
        }

        return $output;
    }

    /* =========================================================================
     * Private Functions
      ======================================================================== */

    /*
     * Holt Daten vom Webservice je nach definierter Einheit.
     */

    private function fetch_awards($year = '', $start = '', $type = '', $awardnameid = '') {
        $filter = Tools::award_filter($year, $start, $type);

        $ws = new CRIS_awards();
        $awardArray = array();

        try {
            if ($this->einheit === "orga") {
                $awardArray = $ws->by_orga_id($this->id, $filter);
            }
            if ($this->einheit === "person") {
                $awardArray = $ws->by_pers_id($this->id, $filter);
            }
            if ($this->einheit === "awardnameid") {
                $awardArray = $ws->by_awardtype_id($this->id, $filter);
            }
        } catch (Exception $ex) {
            $awardArray = array();
        }
        return $awardArray;
    }

    /*
     * Ausgabe der Awards
     */

    private function make_list($awards, $name = 1, $year = 1, $awardname = 1) {
        $awardlist = "<ul class=\"cris-awards\">";

        foreach ($awards as $award) {
            $award = (array) $award;
            foreach ($award['attributes'] as $attribut => $v) {
                $award[$attribut] = $v;
            }
            unset($award['attributes']);

            $preistraeger = explode("|", $award['exportnames']);
            $preistraegerIDs = explode(",", $award['relpersid']);
            $preistraegerArray = array();
            foreach ($preistraegerIDs as $i => $key) {
                $nameparts = explode(":", $preistraeger[$i]);
                $preistraegerArray[] = array(
                    'id' => $key,
                    'lastname' => $nameparts[0],
                    'firstname' => $nameparts[1]);
            }
            $preistraegerList = array();
            foreach ($preistraegerArray as $pt) {
                $preistraegerList[] = Tools::get_person_link($pt['id'], $pt['firstname'], $pt['lastname'], $this->cris_award_link, $this->cms, $this->pathPersonenseiteUnivis, $this->univis, 0);
            }
            $preistraeger_html = implode(", ", $preistraegerList);

            if (!empty($award['award_name'])) {
                $award_name = $award['award_name'];
            } elseif (!empty($award['award_name_manual'])) {
                $award_name = $award['award_name_manual'];
            }
            if (!empty($award['award_organisation'])) {
                $organisation = $award['award_organisation'];
            } elseif (!empty($award['award_organisation_manual'])) {
                $organisation = $award['award_organisation_manual'];
            }
            $award_year = $award['year award'];

            $awardlist .= "<li>";
            if ($year == 1 && $name == 1) {
                $awardlist .= (!empty($preistraeger_html) ? $preistraeger_html : "")
                        . (($awardname == 1) ? ": <strong>" . $award_name . "</strong> "
                        . ((isset($organisation) && $award['type of award'] != 'Akademie-Mitgliedschaft') ? " (" . $organisation . ")" : "") : "" )
                        . (!empty($award_year) ? " &ndash; " . $award_year : "");
            } elseif ($year == 1 && $name == 0) {
                $awardlist .= (!empty($award_year) ? $award_year . ": " : "")
                        . (($awardname == 1) ? "<strong>" . $award_name . "</strong> "
                        . ((isset($organisation) && $award['type of award'] != 'Akademie-Mitgliedschaft') ? " (" . $organisation . ")" : "") : "" );
            } elseif ($year == 0 && $name == 1) {
                $awardlist .= (!empty($preistraeger_html) ? $preistraeger_html . ": " : "")
                        . (($awardname == 1) ? "<strong>" . $award_name . "</strong> "
                        . ((isset($organisation) && $award['type of award'] != 'Akademie-Mitgliedschaft') ? " (" . $organisation . ")" : "") : "" );
            } else {
                $awardlist .= "<strong>" . $award_name . "</strong>"
                        . ((isset($organisation) && $award['type of award'] != 'Akademie-Mitgliedschaft') ? " (" . $organisation . ")" : "");
            }
            $awardlist .= "</li>";
        }

        $awardlist .= "</ul>";
        return $awardlist;
    }

    private function make_gallery($awards, $name = 1, $year = 1, $awardname = 1) {
        $awardlist = "<ul class=\"cris-awards cris-gallery clear clearfix\">";

        foreach ($awards as $award) {
            $award = (array) $award;
            foreach ($award['attributes'] as $attribut => $v) {
                $award[$attribut] = $v;
            }
            unset($award['attributes']);

            $award_preistraeger = $award['award_preistraeger'];
            $preistraeger_firstname = explode(" ", $award['award_preistraeger'])[0];
            $preistraeger_lastname = array_pop((array_slice(explode(" ", $award['award_preistraeger']), -1)));
            $preistraeger_id = $award['relpersid'];
            switch ($this->cris_award_link) {
                case 'cris' :
                    if (is_numeric($preistraeger_id)) {
                        $link_pre = "<a href=\"https://cris.fau.de/converis/publicweb/Person/" . $preistraeger_id . "\" class=\"extern\">";
                        $link_post = "</a>";
                        $award_preistraeger = $link_pre . $award_preistraeger . $link_post;
                    }
                    break;
                case 'person':
                    if (Tools::person_exists($this->cms, $preistraeger_firstname, $preistraeger_lastname, $this->univis)) {
                        $link_pre = "<a href=\"" . $this->pathPersonenseiteUnivis . Tools::person_slug($this->cms, $preistraeger_firstname, $preistraeger_lastname) . "\">";
                        $link_post = "</a>";
                        $award_preistraeger = $link_pre . $award_preistraeger . $link_post;
                    }
                    break;
                default:
            }

            if (!empty($award['award_name'])) {
                $award_name = $award['award_name'];
            } elseif (!empty($award['award_name_manual'])) {
                $award_name = $award['award_name_manual'];
            }
            if (!empty($award['award_organisation'])) {
                $organisation = $award['award_organisation'];
            } elseif (!empty($award['award_organisation_manual'])) {
                $organisation = $award['award_organisation_manual'];
            }
            $award_year = $award['year award'];
            $award_pic = self::get_pic($award['ID']);

            $awardlist .= "<li>";
            $awardlist .= (isset($award_pic['png']) && strlen($award_pic['png']) > 30) ? "<img alt=\"Portrait " . $award['award_preistraeger'] . "\" src=\"" . $award_pic['png'] . "\"  />" : "<div class=\"noimage\">&nbsp</div>";
            $awardlist .= $name == 1 ? $award_preistraeger : '';
            $awardlist .= (($awardname == 1) ? " <strong>" . $award_name . "</strong> "
                    . ((isset($organisation) && $award['type of award'] != 'Akademie-Mitgliedschaft') ? " (" . $organisation . ")" : "") : "" );
            $awardlist .= ($year == 1 && !empty($award_year)) ? "<br />" . $award_year : '';
            $awardlist .= (isset($award_pic['desc']) && strlen($award_pic['desc']) > 0) ? "<br /><span class=\"imgsrc\">(" . _x('Bild:', 'Wird bei Galerien vor die Bildquelle geschrieben.', 'fau-cris') . " " . $award_pic['desc'] . ")</span>" : "";
            $awardlist .= "</li>";
        }

        $awardlist .= "</ul><div style='clear:left;height:0;width:0;visibility:hidden;'></div>";

        return $awardlist;
    }

    private function get_pic($award) {

        $pic = array();
        $picString = CRIS_Dicts::$base_uri . "getrelated/Award/" . $award . "/awar_has_pict";
        $picXml = Tools::XML2obj($picString);

        if ($picXml['size'] != 0) {
            foreach ($picXml->infoObject->attribute as $picAttribut) {
                if ($picAttribut['name'] == 'png180') {
                    if (!empty($picAttribut->data)) {
                        $pic['png'] = 'data:image/PNG;base64,' . $picAttribut->data;
                    } else {
                        // Wenn es kein Bild gibt, braucht er auch die Quelle nicht auslesen
                        return $pic;
                    }
                }
            }
            foreach ($picXml->infoObject->relation->attribute as $picRelAttribut) {
                if ($picRelAttribut['name'] == 'description') {
                    if (!empty($picRelAttribut->data)) {
                        $pic['desc'] = (string) $picRelAttribut->data;
                    }
                }
            }
        }
        return $pic;
    }

}

class CRIS_awards extends CRIS_webservice {
    /*
     * awards/grants requests
     */

    public function by_orga_id($orgaID = null, &$filter = null) {
        if ($orgaID === null || $orgaID === "0")
            throw new Exception('Please supply valid organisation ID');

        if (!is_array($orgaID))
            $orgaID = array($orgaID);

        $requests = array();
        foreach ($orgaID as $_o) {
            $requests[] = sprintf("getautorelated/Organisation/%d/ORGA_3_AWAR_1", $_o);
        }
        return $this->retrieve($requests, $filter);
    }

    public function by_pers_id($persID = null, &$filter = null) {
        if ($persID === null || $persID === "0")
            throw new Exception('Please supply valid person ID');

        if (!is_array($persID))
            $persID = array($persID);

        $requests = array();
        foreach ($persID as $_p) {
            $requests[] = sprintf('getrelated/Person/%d/awar_has_pers', $_p);
        }
        return $this->retrieve($requests, $filter);
    }

    public function by_id($awarID = null) {
        if ($awarID === null || $awarID === "0")
            throw new Exception('Please supply valid award ID');

        if (!is_array($awarID))
            $awarID = array($awarID);

        $requests = array();
        foreach ($awarID as $_p) {
            $requests[] = sprintf('get/Award/%d', $_p);
        }
        return $this->retrieve($requests);
    }

    public function by_awardtype_id($awatID = null) {
        if ($awatID === null || $awatID === "0")
            throw new Exception('Please supply valid award ID');

        if (!is_array($awatID))
            $awatID = array($awatID);

        $requests = array();
        foreach ($awatID as $_p) {
            $requests[] = sprintf("getrelated/Award%%20Type/%d/awar_has_awat", $_p);
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
//                $e->getMessage();
                continue;
            }
        }

        $awards = array();

        foreach ($data as $_d) {
            foreach ($_d as $award) {
                $a = new CRIS_award($award);
                if ($a->ID && ($filter === null || $filter->evaluate($a)))
                    $awards[$a->ID] = $a;
            }
        }

        return $awards;
    }

}

class CRIS_award extends CRIS_Entity {
    /*
     * object for single award
     */

    function __construct($data) {
        parent::__construct($data);
    }

}
