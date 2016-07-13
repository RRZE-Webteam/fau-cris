<?php

require_once("class_Tools.php");
require_once("class_Webservice.php");
require_once("class_Filter.php");
require_once("class_Formatter.php");

class Auszeichnungen {

    private $options;
    public $output;

    public function __construct($einheit = '', $id = '') {

        $this->cms = 'wp';
        $this->options = (array) get_option('_fau_cris');
        $this->orgNr = $this->options['cris_org_nr'];
        $this->order = $this->options['cris_award_order'];
        $this->cris_award_link = isset($this->options['cris_award_link']) ? $this->options['cris_award_link'] : 0;
        $this->pathPersonenseiteUnivis = '/person/';
        $this->suchstring = '';

        if ((!$this->orgNr || $this->orgNr == 0) && $id == '') {
            print '<p><strong>' . __('Bitte geben Sie die CRIS-ID der Organisation, Person oder Publikation an.', 'fau-cris') . '</strong></p>';
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

        $univis = NULL;
        if ($this->cms == 'wbk' && $this->cris_award_link == 1) {
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
                $univis[] = array ('firstname' => (string) $person->firstname,
                                   'lastname' => (string) $person->lastname);
            }
        }
        $this->univis = $univis;
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

    public function awardsNachJahr($year = '', $start = '', $type = '', $awardnameid = '', $showname = 1, $showyear = 0, $showawardname = 1, $display = 'list') {
        $awardArray = $this->fetch_awards($year, $start, $type, $awardnameid);

        if (!count($awardArray)) {
            $output = '<p>' . __('Es wurden leider keine Auszeichnungen gefunden.', 'fau-cris') . '</p>';
            return $output;
        }

        $formatter = new CRIS_formatter("year award", SORT_DESC, "award_preistraeger", SORT_ASC);
        $awardList = $formatter->execute($awardArray);

        $output = '';

        foreach ($awardList as $array_year => $awards) {
            if (empty($year)) {
                $output .= '<h3 class="clearfix clear">';
                $output .=!empty($array_year) ? $array_year : __('Ohne Jahr', 'fau-cris');
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

    public function awardsNachTyp($year = '', $start = '', $type = '', $awardnameid = '', $showname = 1, $showyear = 0, $showawardname = 1, $display = '') {
        $awardArray = $this->fetch_awards($year, $start, $type, $awardnameid);

        if (!count($awardArray)) {
            $output = '<p>' . __('Es wurden leider keine Auszeichnungen gefunden.', 'fau-cris') . '</p>';
            return $output;
        }

        // Auszeichnungstypen sortieren
        $order = $this->order;
        if ($order[0] != '' && array_key_exists($order[0], CRIS_Dicts::$awardNames)) {
            foreach ($order as $key => $value) {
                $order[$key] = Tools::getAwardName($value, "de");
            }
        } else {
            $order = array();
            foreach (CRIS_Dicts::$awardOrder as $value) {
                $order[] = Tools::getAwardName($value, "de");
            }
        }

        // sortiere nach Typenliste, innerhalb des Typs nach Name aufwärts sortieren
        $formatter = new CRIS_formatter("type of award", array_values($order), "award_preistraeger", SORT_ASC);
        $awardList = $formatter->execute($awardArray);

        $output = '';

        foreach ($awardList as $array_type => $awards) {
            if (empty($type)) {
                $title = Tools::getawardTitle($array_type, get_locale());
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

            $award_preistraeger = $award['award_preistraeger'];
            $preistraeger_firstname = explode(" ", $award['award_preistraeger'])[0];
            $preistraeger_lastname = array_pop((array_slice(explode(" ", $award['award_preistraeger']), -1)));
            if ($this->cris_award_link == 1
                    && Tools::person_exists($this->cms, $preistraeger_firstname, $preistraeger_lastname, $this->univis)) {
                $link_pre = "<a href=\"" . $this->pathPersonenseiteUnivis . Tools::person_slug($this->cms, $preistraeger_firstname, $preistraeger_lastname) . "\">";
                $link_post = "</a>";
                $award_preistraeger = $link_pre . $award_preistraeger . $link_post;
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

            $awardlist .= "<li>";
            if ($year == 1 && $name == 1) {
                $awardlist .= (!empty($award_preistraeger) ? $award_preistraeger : "")
                        . ($awardname == 1 ? ": <strong>" . $award_name . "</strong> "
                                . ((isset($organisation) && $award['type of award'] != 'Akademie-Mitgliedschaft') ? " (" . $organisation . ")" : "") : "" )
                        . (!empty($award_year) ? " &ndash; " . $award_year : "");
            } elseif ($year == 1 && $name == 0) {
                $awardlist .= (!empty($award_year) ? $award_year . ": " : "")
                        . "<strong>" . $award_name . "</strong>"
                        . ((isset($organisation) && $award['type of award'] != 'Akademie-Mitgliedschaft') ? " (" . $organisation . ")" : "");
            } elseif ($year == 0 && $name == 1) {
                $awardlist .= (!empty($award_preistraeger) ? $award_preistraeger . ": " : "")
                        . "<strong>" . $award_name . "</strong>"
                        . ((isset($organisation) && $award['type of award'] != 'Akademie-Mitgliedschaft') ? " (" . $organisation . ")" : "");
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
            if ($this->cris_award_link == 1
                    && Tools::person_exists($this->cms, $preistraeger_firstname, $preistraeger_lastname, $this->univis)) {
                $link_pre = "<a href=\"" . $this->pathPersonenseiteUnivis . Tools::person_slug($this->cms, $preistraeger_firstname, $preistraeger_lastname) . "\">";
                $link_post = "</a>";
                $award_preistraeger = $link_pre . $award_preistraeger . $link_post;
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
            $awardlist .= !empty($award_pic) ? "<img alt=\"Portrait " . $award['award_preistraeger'] . "\" src=\"" . $award_pic['png'] . "\"  />" : "<div class=\"noimage\">&nbsp</div>";
            $awardlist .= $name == 1 ? $award_preistraeger : '';
            $awardlist .= $awardname == 1 ? "<br /><strong>" . $award_name . "</strong> " : '';
            $awardlist .= (isset($organisation) && $award['type of award'] != 'Akademie-Mitgliedschaft') ? " (" . $organisation . ")" : "";
            $awardlist .= ($year == 1 && !empty($award_year)) ? "<br />" . $award_year : '';
            $awardlist .= isset($award_pic['desc']) ? "<br /><span class=\"imgsrc\">(" . _x('Bild:','Wird bei Galerien vor die Bildquelle geschrieben.' , 'fau-cris') . " ". $award_pic['desc'] . ")</span>" : "";
            $awardlist .= "</li>";
        }

        $awardlist .= "</ul><div style='clear:left;height:0;width:0;visibility:hidden;'></div>";

        return $awardlist;
    }

    private function get_pic($award) {
        $pic = '';

        $picString = "https://cris.fau.de/ws-cached/1.0/public/infoobject/getrelated/Award/" . $award . "/awar_has_pict";
        $picXml = Tools::XML2obj($picString);

        if ($picXml['size'] != 0) {
            foreach ($picXml->infoObject->attribute as $picAttribut) {
                if ($picAttribut['name'] == 'png180') {
                    $pic['png'] = 'data:image/PNG;base64,' . $picAttribut->data;
                }
            }
            foreach ($picXml->infoObject->relation->attribute as $picRelAttribut) {
                if ($picRelAttribut['name'] == 'description') {
                    $pic['desc'] = (string) $picRelAttribut->data;
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
    public function by_orga_id($orgaID=null, &$filter=null) {
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

    public function by_pers_id($persID=null, &$filter=null) {
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

    public function by_id($awarID=null) {
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

    public function by_awardtype_id($awatID=null) {
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

    private function retrieve($reqs, &$filter=null) {
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