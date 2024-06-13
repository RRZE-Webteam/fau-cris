<?php
namespace RRZE\Cris;
defined('ABSPATH') || exit;

use RRZE\Cris\Tools;
use RRZE\Cris\Webservice;
use RRZE\Cris\Filter;
use RRZE\Cris\Formatter;

class Aktivitaeten
{

    private array $options;
    public $output;
    public $cms;
    public $pathPersonenseiteUnivis;
    public $orgNr;
    public $suchstring;
    public $univis;
    public $order;
    public $cris_activities_link;
    public $id;
    public $einheit;
    public $page_lang;
    public $langdiv_open;
    public $sc_lang;
    public $langdiv_close;


    public function __construct($einheit = '', $id = '', $page_lang = 'de', $sc_lang = 'de')
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
        $this->orgNr = $this->options['cris_org_nr'];
        $this->suchstring = '';
        $this->univis = null;

        $this->order = $this->options['cris_activities_order'];
        $this->cris_activities_link = $this->options['cris_activities_link'] ?? 'none';
        if ($this->cms == 'wbk' && $this->univisLink == 'person') {
            $this->univis = Tools::get_univis();
        }

        if ((!$this->orgNr || $this->orgNr == 0) && $id == '') {
            $this->error = new \WP_Error(
                'cris-orgid-error',
                __('Bitte geben Sie die CRIS-ID der Organisation, Person oder Forschungsaktivität an.', 'fau-cris')
            );
        }
        if (in_array($einheit, array("person", "orga", "activity"))) {
            $this->id = $id;
            $this->einheit = $einheit;
        } else {
            // keine Einheit angegeben -> OrgNr aus Einstellungen verwenden
            $this->id = $this->orgNr;
            $this->einheit = "orga";
        }
        $this->page_lang = $page_lang;
        $this->sc_lang = $sc_lang;
        $this->langdiv_open = '<div class="cris">';
        $this->langdiv_close = '</div>';
        if ($sc_lang != $this->page_lang) {
            $this->langdiv_open = '<div class="cris" lang="' . $sc_lang . '">';
        }
    }

    /*
     * Ausgabe aller Aktivitäten ohne Gliederung
     */

    public function actiListe($param = array()): string
    {
        $year = (isset($param['year']) && $param['year'] != '') ? $param['year'] : '';
        $start = (isset($param['start']) && $param['start'] != '') ? $param['start'] : '';
        $end = (isset($param['end']) && $param['end'] != '') ? $param['end'] : '';
        $type = (isset($param['type']) && $param['type'] != '') ? $param['type'] : '';
        $limit = (isset($param['limit']) && $param['limit'] != '') ? $param['limit'] : '';
        $hide = (isset($param['hide']) && !empty($param['hide'])) ? $param['hide'] : array();
        $showname = $this->einheit == 'person' ? 0 : 1;
        $showyear = 1;
        $showactivityname = 1;
        $showtype = in_array('type', $hide) ? 0 : 1;

        $activityArray = $this->fetch_activities($year, $start, $end, $type);

        if (!count($activityArray)) {
            $output = '<p>' . __('Es wurden leider keine Aktivitäten gefunden.', 'fau-cris') . '</p>';
            return $output;
        }
        $order = "sortdate";
        $formatter = new Formatter(null, null, $order, SORT_DESC);
        $res = $formatter->execute($activityArray);
        if ($limit != '') {
            $activityList = array_slice($res[$order], 0, $limit);
        } else {
            $activityList = $res[$order];
        }

        $output = $this->langdiv_open . $this->make_list($activityList, $showname, $showyear, $showactivityname, $showtype) . $this->langdiv_close;

        return $this->langdiv_open . $output . $this->langdiv_close;
    }

    /*
     * Ausgabe aller Aktivitäten nach Jahren gegliedert
     */

    public function actiNachJahr($param = array()): string
    {
        $year = (isset($param['year']) && $param['year'] != '') ? $param['year'] : '';
        $start = (isset($param['start']) && $param['start'] != '') ? $param['start'] : '';
        $end = (isset($param['end']) && $param['end'] != '') ? $param['end'] : '';
        $type = (isset($param['type']) && $param['type'] != '') ? $param['type'] : '';
        $hide = (isset($param['hide']) && !empty($param['hide'])) ? $param['hide'] : array();
        $showname = $this->einheit == 'person' ? 0 : 1;
        $showyear = 0;
        $showactivityname = 1;
        $order2 = 'year';
        $format = (isset($param['format']) && $param['format'] != '') ? $param['format'] : '';
        $showtype = in_array('type', $hide) ? 0 : 1;

        $activityArray = $this->fetch_activities($year, $start, $end, $type);

        if (!count($activityArray)) {
            $output = '<p>' . __('Es wurden leider keine Aktivitäten gefunden.', 'fau-cris') . '</p>';
            return $output;
        }

        if ($order2 == 'author') {
            $formatter = new Formatter("year", SORT_DESC, "exportnames", SORT_ASC);
        } else {
            $formatter = new Formatter("year", SORT_DESC, "sortdate", SORT_ASC);
        }
        $activityList = $formatter->execute($activityArray);

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
            foreach ($activityList as $array_year => $activities) {
                $shortcode_data .= do_shortcode('[collapse title="' . $array_year . '"' . $openfirst . ']' . $this->make_list($activities, $showname, $showyear, $showactivityname) . '[/collapse]');
                $openfirst = '';
            }
            $output .= do_shortcode('[collapsibles ' . $expandall . ']' . $shortcode_data . '[/collapsibles]');
        } else {
            foreach ($activityList as $array_year => $activities) {
                if (empty($year)) {
                    $output .= '<h3 class="clearfix clear">';
                    $output .= !empty($array_year) ? $array_year : __('Ohne Jahr', 'fau-cris');
                    $output .= '</h3>';
                }
                $output .= $this->make_list($activities, $showname, $showyear, $showactivityname, $showtype);
            }
        }
        return $this->langdiv_open . $output . $this->langdiv_close;
    }

    /*
     * Ausgabe aller Aktivitäten nach Patenttypen gegliedert
     */

    public function actiNachTyp($param = array()): string
    {
        $year = (isset($param['year']) && $param['year'] != '') ? $param['year'] : '';
        $start = (isset($param['start']) && $param['start'] != '') ? $param['start'] : '';
        $end = (isset($param['end']) && $param['end'] != '') ? $param['end'] : '';
        $type = (isset($param['type']) && $param['type'] != '') ? $param['type'] : '';
        $hide = (isset($param['hide']) && !empty($param['hide'])) ? $param['hide'] : array();
        $showname = $this->einheit == 'person' ? 0 : 1;
        $showyear = 0;
        $showactivityname = 1;
        $order2 = 'year';
        $format = (isset($param['format']) && $param['format'] != '') ? $param['format'] : '';

        $activityArray = $this->fetch_activities($year, $start, $end, $type);

        if (!count($activityArray)) {
            $output = '<p>' . __('Es wurden leider keine Aktivitäten gefunden.', 'fau-cris') . '</p>';
            return $output;
        }

        // Patenttypen sortieren
        $order = $this->order;
        if ($order[0] != '' && array_search($order[0], array_column(Dicts::$typeinfos['activities'], 'short'))) {
            foreach ($order as $key => $value) {
                $order[$key] = Tools::getType('activities', $value);
            }
        } else {
            $order = Tools::getOrder('activities');
        }

        // sortiere nach Typenliste, innerhalb des Typs nach Name aufwärts sortieren
        if ($order2 == 'name') {
            $formatter = new Formatter("type of activity", $order, "exportnames", SORT_ASC);
        } else {
            $formatter = new Formatter("type of activity", $order, "sortdate", SORT_DESC);
        }
        $activityList = $formatter->execute($activityArray);
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
            foreach ($activityList as $array_type => $activities) {
                $title = Tools::getTitle('activities', $array_type, $param['display_language']);
                $shortcode_data .= do_shortcode('[collapse title="' . $title . '"' . $openfirst . ']' . $this->make_list($activities, $showname, $showyear, $showactivityname, 0) . '[/collapse]');
                $openfirst = '';
            }
            $output .= do_shortcode('[collapsibles ' . $expandall . ']' . $shortcode_data . '[/collapsibles]');
        } else {
            foreach ($activityList as $array_type => $activities) {
                if (empty($type)) {
                    $title = Tools::getTitle('activities', $array_type, $this->sc_lang);
                    $output .= '<h3 class="clearfix clear">';
                    $output .= $title;
                    $output .= "</h3>";
                }
                $output .= $this->make_list($activities, $showname, $showyear, $showactivityname, 0);
            }
        }
        return $this->langdiv_open . $output . $this->langdiv_close;
    }

    /*
     * Ausgabe eines einzelnen Patents
     */

    /** @noinspection PhpInconsistentReturnPointsInspection */
    public function singleActivity($hide)
    {
        $showname = 1;
        $showyear = 0;
        $showactivityname = 1;
        $ws = new CRIS_activities();

        try {
            $activityArray = $ws->by_id($this->id);
        } catch (Exception $ex) {
            return;
        }

        if (!count($activityArray)) {
            $output = '<p>' . __('Es wurden leider keine Aktivitäten gefunden.', 'fau-cris') . '</p>';
            return $output;
        }

        $output = $this->langdiv_open . $this->make_list($activityArray, $showname, $showyear, $showactivityname) . $this->langdiv_close;

        return $this->langdiv_open . $output . $this->langdiv_close;
    }

    /* =========================================================================
     * Private Functions
      ======================================================================== */

    /*
     * Holt Daten vom Webservice je nach definierter Einheit.
     */

    private function fetch_activities($year = '', $start = '', $end = '', $type = ''): array
    {
        $filter = Tools::activity_filter($year, $start, $end, $type);

        $ws = new CRIS_activities();
        $activityArray = array();

        try {
            if ($this->einheit === "orga") {
                $activityArray = $ws->by_orga_id($this->id, $filter);
            }
            if ($this->einheit === "person") {
                $activityArray = $ws->by_pers_id($this->id, $filter);
            }
        } catch (Exception $ex) {
            $activityArray = array();
        }
        return $activityArray;
    }

    /*
     * Ausgabe der Patents
     */

    private function make_list($activities, $name = 1, $year = 1, $activityname = 1, $showtype = 1): string
    {
        if ($this->einheit == "activity") {
            $activitylist = "<div class=\"cris-activities\">";
        } else {
            $activitylist = "<ul class=\"cris-activities\">";
        }

        foreach ($activities as $activity) {
            $activity = (array) $activity;
            $namesArray = array();
            foreach ($activity['attributes'] as $attribut => $v) {
                $activity[$attribut] = $v;
            }
            unset($activity['attributes']);
            $names = explode("|", $activity['exportnames']);
            $nameIDs = explode(",", $activity['relpersid']);
            foreach ($nameIDs as $i => $key) {
                $namesArray[] = array('id' => $key, 'name' => $names[$i]);
            }
            $namesList = array();
            foreach ($namesArray as $persname) {
                $name_elements = explode(":", $persname['name']);
                $firstname = $name_elements[1];
                $lastname = $name_elements[0];
                $namesList[] = Tools::get_person_link($persname['id'], $firstname, $lastname, $this->cris_activities_link, $this->cms, $this->pathPersonenseiteUnivis, $this->univis, 0);
            }
            $names_html = implode(", ", $namesList);

            $activity_id = $activity['ID'];
            $activity_type = Tools::getName('activities', $activity['type of activity'], $this->sc_lang);
            setlocale(LC_TIME, get_locale());

            switch (strtolower($activity['type of activity'])) {
                case "fau-interne gremienmitgliedschaften / funktionen":
                    $activity_name = Tools::getName('activities', $activity['type of activity'], $this->sc_lang, $subtype = $activity['typeofinternalmembership']);
                    $activity_detail = '';
                    $activity_nameofshow = '';
                    $activity_eventname = '';
                    $activity_startdate = $activity['mandate start'];
                    $activity_enddate = $activity['mandate end'];
                    $activity_date = Tools::make_date($activity_startdate, $activity_enddate);
                    $activity_url = $activity['url'];
                    $activity_location = $activity['mirror_orga'];
                    break;
                case "organisation einer tagung / konferenz":
                    $activity_name = $activity['nameconference'];
                    $activity_detail = '';
                    $activity_nameofshow = '';
                    $activity_eventname = '';
                    $activity_startdate = $activity['start date'];
                    $activity_enddate = $activity['end date'];
                    $activity_date = Tools::make_date($activity_startdate, $activity_enddate);
                    $activity_url = $activity['url'];
                    $activity_location = $activity['city'];
                    break;
                case "herausgeberschaft":
                    $activity_name = $activity['namejournal'];
                    $activity_detail = $activity['role of editorship'];
                    $activity_nameofshow = '';
                    $activity_eventname = '';
                    $activity_startdate = $activity['start date'];
                    $activity_enddate = $activity['end date'];
                    $activity_date = Tools::make_date($activity_startdate, $activity_enddate);
                    $activity_url = $activity['url'];
                    $activity_location = '';
                    break;
                case "gutachtertätigkeit für wissenschaftliche zeitschrift":
                    $activity_name = $activity['namejournal'];
                    $activity_detail = '';
                    $activity_nameofshow = '';
                    $activity_eventname = '';
                    $activity_startdate = $activity['start date'];
                    $activity_enddate = $activity['end date'];
                    $activity_date = Tools::make_date($activity_startdate, $activity_enddate);
                    $activity_url = $activity['url'];
                    $activity_location = '';
                    break;
                case "gutachtertätigkeit für förderorganisation":
                    $activity_name = $activity['mirror_fund'];
                    $activity_detail = $activity['type of expert activity'];
                    $activity_nameofshow = '';
                    $activity_eventname = '';
                    $activity_startdate = $activity['start date'];
                    $activity_enddate = $activity['end date'];
                    $activity_date = Tools::make_date($activity_startdate, $activity_enddate);
                    $activity_url = $activity['url'];
                    $activity_location = '';
                    break;
                case "sonstige fau-externe gutachtertätigkeit":
                    $activity_name = $activity['type of expert activity'];
                    $activity_detail = '';
                    $activity_nameofshow = '';
                    $activity_eventname = '';
                    $activity_startdate = $activity['start date'];
                    $activity_enddate = $activity['end date'];
                    $activity_date = Tools::make_date($activity_startdate, $activity_enddate);
                    $activity_url = $activity['url'];
                    $activity_location = $activity['mirror_eorg'];
                    break;
                case "dfg-fachkollegiat/in":
                    $activity_name = $activity['mirror_dfgfach'];
                    $activity_detail = '';
                    $activity_nameofshow = '';
                    $activity_eventname = '';
                    $activity_startdate = $activity['mandate start'];
                    $activity_enddate = $activity['mandate end'];
                    $activity_date = Tools::make_date($activity_startdate, $activity_enddate);
                    $activity_url = $activity['url'];
                    $activity_location = '';
                    break;
                case "gremiumsmitglied wissenschaftsrat":
                    $activity_name = $activity['description function'];
                    $activity_detail = $activity['memberscicouncil'];
                    $activity_nameofshow = '';
                    $activity_eventname = '';
                    $activity_startdate = $activity['mandate start'];
                    $activity_enddate = $activity['mandate end'];
                    $activity_date = $activity_startdate . " - " . $activity_enddate;
                    $activity_url = $activity['url'];
                    $activity_location = $activity['mirror_orga'];
                    break;
                case "vortrag":
                    $activity_name = $activity['name'];
                    $activity_detail = '';
                    $activity_nameofshow = '';
                    $activity_eventname = $activity['event name'];
                    $activity_date = $activity['date'];
                    if ($activity_date != '') {
                        $activity_date = date_i18n(get_option('date_format'), strtotime($activity_date));
                    }
                    $activity_url = $activity['url'];
                    $activity_location = $activity['mirror_eorg'];
                    break;
                case "radio- / fernsehbeitrag / podcast":
                    $activity_name = $activity['name of contribution'];
                    $activity_detail = '';
                    $activity_nameofshow = $activity['showname'];
                    $activity_eventname = '';
                    $activity_date = $activity['date'];
                    if ($activity_date != '') {
                        $activity_date = date_i18n(get_option('date_format'), strtotime($activity_date));
                    }
                    $activity_url = $activity['url'];
                    $activity_location = '';
                    break;
                case "sonstige fau-externe aktivitäten":
                    $activity_name = $activity['type of extern expert activity'];
                    $activity_detail = '';
                    $activity_nameofshow = '';
                    $activity_eventname = '';
                    $activity_startdate = $activity['start date'];
                    $activity_enddate = $activity['end date'];
                    $activity_date = Tools::make_date($activity_startdate, $activity_enddate);
                    $activity_url = $activity['url'];
                    $activity_location = $activity['mirror_eorg'];
                    break;
            }

            if ($this->einheit != "activity") {
                $activitylist .= "<li>";
            }

            if ($name == 1 && !empty($names_html)) {
                $activitylist .= $names_html . ": ";
            }
            if (!empty($activity_name)) {
                global $post;
                $activitylist .= " <strong><a href=\"" . Tools::get_item_url("activity", $activity_name, $activity_id, $post->ID, $this->page_lang) . "\" target=\"blank\" title=\"" . __('Detailansicht auf cris.fau.de in neuem Fenster &ouml;ffnen', 'fau-cris') . "\">" . $activity_name . "</a></strong>";
            }
            if (!empty($activity_detail)) {
                $activitylist .= " (" . $activity_detail . ")";
            }
            if (!empty($activity_type) && $showtype != 0) {
                $activitylist .= '<br />(' . $activity_type . ') ';
            }
            if (!empty($activity_date)) {
                $activitylist .= '<br />' . $activity_date;
            }
            if (!empty($activity_eventname)) {
                $activitylist .= ", " . __('Veranstaltung', 'fau-cris') . ": " . $activity_eventname;
            }
            if (!empty($activity_nameofshow)) {
                $activitylist .= ", " . __('In', 'fau-cris') . ": \"" . $activity_nameofshow . "\"";
            }
            if (!empty($activity_location)) {
                $activitylist .= ", " . $activity_location;
            }
            if (!empty($activity_url)) {
                $activitylist .= ", URL: <a href=\"" . $activity_url . "\" target=\"blank\" title=\"" . __('Link in neuem Fenster &ouml;ffnen', 'fau-cris') . "\">" . $activity_url . "</a>";
            }

            if ($this->einheit != "activity") {
                $activitylist .= "</li>";
            }
        }

        if ($this->einheit == "activity") {
            $activitylist .= "</div>";
        } else {
            $activitylist .= "</ul>";
        }

        return $activitylist;
    }
}

class CRIS_activities extends Webservice
{
    /*
     * actients/grants requests
     */

    public function by_orga_id($orgaID = null, &$filter = null)
    {
        if ($orgaID === null || $orgaID === "0") {
            return  new \WP_Error(
                'cris-orgid-error',
                __('Bitte geben Sie die CRIS-ID der Organisation, Person oder des Projektes an.', 'fau-cris')
            );
        }

        if (!is_array($orgaID)) {
            $orgaID = array($orgaID);
        }

        $requests = array();
        foreach ($orgaID as $_o) {
            $requests[] = sprintf("getrelated/Organisation/%d/acti_has_orga", $_o);
        }
        return $this->retrieve($requests, $filter);
    }

    public function by_pers_id($persID = null, &$filter = null): array
    {
        if ($persID === null || $persID === "0") {
            return  new \WP_Error(
                'cris-orgid-error',
                __('Bitte geben Sie die CRIS-ID der Organisation, Person oder des Projektes an.', 'fau-cris')
            );
        }

        if (!is_array($persID)) {
            $persID = array($persID);
        }

        $requests = array();
        foreach ($persID as $_p) {
            $requests[] = sprintf('getrelated/Person/%s/acti_has_pers', $_p);
        }
        return $this->retrieve($requests, $filter);
    }

    public function by_id($awarID = null): array
    {
        if ($awarID === null || $awarID === "0") {
            return  new \WP_Error(
                'cris-orgid-error',
                __('Bitte geben Sie die CRIS-ID der Organisation, Person oder des Projektes an.', 'fau-cris')
            );
        }

        if (!is_array($awarID)) {
            $awarID = array($awarID);
        }

        $requests = array();
        foreach ($awarID as $_p) {
            $requests[] = sprintf('get/Activity/%d', $_p);
        }
        return $this->retrieve($requests);
    }

    private function retrieve($reqs, &$filter = null): array
    {
        if ($filter !== null && !$filter instanceof Filter) {
            $filter = new Filter($filter);
        }

        $data = array();
        foreach ($reqs as $_i) {
            $_data = $this->get($_i, $filter);
            if (!is_wp_error($_data)) {
                $data[] = $_data;
            }
        }

        $activities = array();

        foreach ($data as $_d) {
            foreach ($_d as $activity) {
                $a = new CRIS_activity($activity);
                if ($a->ID) {
                    if (!empty($a->attributes['date'])) {
                        $a->attributes['year'] = mb_substr($a->attributes['date'], 0, 4);
                        $a->attributes['sortdate'] = $a->attributes['date'];
                    } elseif (!empty($a->attributes['start date'])) {
                        $a->attributes['year'] = mb_substr($a->attributes['start date'], 0, 4);
                        $a->attributes['sortdate'] = $a->attributes['start date'];
                    } elseif (!empty($a->attributes['mandate start'])) {
                        $a->attributes['year'] = mb_substr($a->attributes['mandate start'], 0, 4);
                        $a->attributes['sortdate'] = $a->attributes['mandate start'];
                    }
                    if (!empty($a->attributes['sortdate'])) {
                        $a->attributes['year'] = mb_substr($a->attributes['sortdate'], 0, 4);
                    } else {
                        $a->attributes['year'] = '';
                    }
                }
                if ($a->ID && ($filter === null || $filter->evaluate($a))) {
                    $activities[$a->ID] = $a;
                }
            }
        }

        return $activities;
    }
}

class CRIS_activity extends CRIS_Entity
{
    /*
     * object for single activity
     */

    public function __construct($data)
    {
        parent::__construct($data);
    }
}
