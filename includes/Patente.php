<?php
namespace RRZE\Cris;
use RRZE\Cris\Tools;
use RRZE\Cris\Webservice;
use RRZE\Cris\Filter;
use RRZE\Cris\Formatter;

class Patente
{

    private array $options;
    public $output;

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

        $this->order = $this->options['cris_patent_order'];
        $this->cris_patent_link = $this->options['cris_patent_link'] ?? 'none';
        if ($this->cms == 'wbk' && $this->cris_patent_link == 'person') {
            $this->univis = Tools::get_univis();
        }

        if ((!$this->orgNr || $this->orgNr == 0) && $id == '') {
            $this->error = new \WP_Error(
                'cris-orgid-error',
                __('Bitte geben Sie die CRIS-ID der Organisation, Person oder des Patents an.', 'fau-cris')
            );
        }
        if (in_array($einheit, array("person", "orga", "patent"))) {
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

	/**
	 * Name : patListe
	 *
	 * Use: get patent list
	 *
	 * Returns: patent list
	 *
	 */

    public function patListe($param = array()): string {
        $year = (isset($param['year']) && $param['year'] != '') ? $param['year'] : '';
        $start = (isset($param['start']) && $param['start'] != '') ? $param['start'] : '';
        $end = (isset($param['end']) && $param['end'] != '') ? $param['end'] : '';
        $type = (isset($param['type']) && $param['type'] != '') ? $param['type'] : '';
        $limit = (isset($param['limit']) && $param['limit'] != '') ? $param['limit'] : '';
        $hide = (isset($param['hide']) && $param['hide'] != '') ? $param['hide'] : '';
        $showname = (isset($param['showname']) && $param['showname'] != '') ? $param['showname'] : 1;
        $showyear = (isset($param['showyear']) && $param['showyear'] != '') ? $param['showyear'] : 1;
        $showpatentname = (isset($param['showpatentname']) && $param['showpatentname'] != '') ? $param['showpatentname'] : 1;

        $patentArray = $this->fetch_patents($year, $start, $end, $type);

        if (!count($patentArray)) {
            $output = '<p>' . __('Es wurden leider keine Patente gefunden.', 'fau-cris') . '</p>';
            return $output;
        }

        $order = "registryear";
        $formatter = new Formatter(null, null, $order, SORT_DESC);
        $res = $formatter->execute($patentArray);
        if ($limit != '') {
            $patentList = array_slice($res[$order], 0, $limit);
        } else {
            $patentList = $res[$order];
        }

        $output = $this->make_list($patentList, $showname, $showyear, $showpatentname);

        return $this->langdiv_open . $output . $this->langdiv_close;
    }


	/**
	 * Name : patNachJahr
	 *
	 * Use: get patent list according to registryear
	 *
	 * Returns: patent list by registryear
	 *
	 */

    public function patNachJahr($param = array()): string {
        $year = (isset($param['year']) && $param['year'] != '') ? $param['year'] : '';
        $start = (isset($param['start']) && $param['start'] != '') ? $param['start'] : '';
        $end = (isset($param['end']) && $param['end'] != '') ? $param['end'] : '';
        $type = (isset($param['type']) && $param['type'] != '') ? $param['type'] : '';
        $hide = (isset($param['hide']) && $param['hide'] != '') ? $param['hide'] : '';
        $showname = (isset($param['showname']) && $param['showname'] != '') ? $param['showname'] : 1;
        $showyear = (isset($param['showyear']) && $param['showyear'] != '') ? $param['showyear'] : 0;
        $showpatentname = (isset($param['showpatentname']) && $param['showpatentname'] != '') ? $param['showpatentname'] : 1;
        $order2 = (isset($param['order2']) && $param['order2'] != '') ? $param['order2'] : 'year';
        $format = (isset($param['format']) && $param['format'] != '') ? $param['format'] : '';

        $patentArray = $this->fetch_patents($year, $start, $end, $type);

        if (!count($patentArray)) {
            $output = '<p>' . __('Es wurden leider keine Patente gefunden.', 'fau-cris') . '</p>';
            return $output;
        }

        if ($order2 == 'author') {
            $formatter = new Formatter("registryear", SORT_DESC, "exportinventors", SORT_ASC);
        } else {
            $formatter = new Formatter("registryear", SORT_DESC, "cfregistrdate", SORT_ASC);
        }
        $patentList = $formatter->execute($patentArray);

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
            foreach ($patentList as $array_year => $patents) {
                $shortcode_data .= do_shortcode('[collapse title="' . $array_year . '"' . $openfirst . ']' . $this->make_list($patents, $showname, $showyear, $showpatentname) . '[/collapse]');
                $openfirst = '';
            }
            $output .= do_shortcode('[collapsibles ' . $expandall . ']' . $shortcode_data . '[/collapsibles]');
        } else {
            foreach ($patentList as $array_year => $patents) {
                if (empty($year)) {
                    $output .= '<h3 class="clearfix clear">';
                    $output .= !empty($array_year) ? $array_year : __('Ohne Jahr', 'fau-cris');
                    $output .= '</h3>';
                }
                $output .= $this->make_list($patents, $showname, $showyear, $showpatentname);
            }
        }
        return $this->langdiv_open . $output . $this->langdiv_close;
    }

	/**
	 * Name : patNachTyp
	 *
	 * Use: get patent list according to patenttype
	 *
	 * Returns: patent list by patenttype
	 *
	 */

    public function patNachTyp($param = array()): string {
        $year = (isset($param['year']) && $param['year'] != '') ? $param['year'] : '';
        $start = (isset($param['start']) && $param['start'] != '') ? $param['start'] : '';
        $end = (isset($param['end']) && $param['end'] != '') ? $param['end'] : '';
        $type = (isset($param['type']) && $param['type'] != '') ? $param['type'] : '';
        $hide = (isset($param['hide']) && $param['hide'] != '') ? $param['hide'] : '';
        $showname = (isset($param['showname']) && $param['showname'] != '') ? $param['showname'] : 1;
        $showyear = (isset($param['showyear']) && $param['showyear'] != '') ? $param['showyear'] : 1;
        $showpatentname = (isset($param['showpatentname']) && $param['showpatentname'] != '') ? $param['showpatentname'] : 1;
        $order2 = (isset($param['order2']) && $param['order2'] != '') ? $param['order2'] : 'year';
        $format = (isset($param['format']) && $param['format'] != '') ? $param['format'] : '';

        $patentArray = $this->fetch_patents($year, $start, $end, $type);

        if (!count($patentArray)) {
            $output = '<p>' . __('Es wurden leider keine Patente gefunden.', 'fau-cris') . '</p>';
            return $output;
        }

        // Patenttypen sortieren
        $order = $this->order;
        if ($order[0] != '' && array_search($order[0], array_column(Dicts::$typeinfos['publications'], 'short'))) {
            foreach ($order as $key => $value) {
                $order[$key] = Tools::getType('patents', $value);
            }
        } else {
            $order = Tools::getOrder('patents');
        }

        // sortiere nach Typenliste, innerhalb des Typs nach Name aufwärts sortieren
        if ($order2 == 'name') {
            $formatter = new Formatter("patenttype", SORT_DESC, "exportinventors", SORT_ASC);
        } else {
            $formatter = new Formatter("patenttype", SORT_DESC, "cfregistrdate", SORT_DESC);
        }
        $patentList = $formatter->execute($patentArray);
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
            foreach ($patentList as $array_type => $patents) {
                $title = Tools::getTitle('patents', $array_type, $this->sc_lang);
                $shortcode_data .= do_shortcode('[collapse title="' . $title . '" ' . $openfirst . ']' . $this->make_list($patents, $showname, $showyear, $showpatentname, 0) . '[/collapse]');
                $openfirst = '';
            }
            $output .= do_shortcode('[collapsibles ' . $expandall . ']' . $shortcode_data . '[/collapsibles]');
        } else {
            foreach ($patentList as $array_type => $patents) {
                if (empty($type)) {
                    $title = Tools::getTitle('patents', $array_type, $this->sc_lang);
                    $output .= '<h3 class="clearfix clear">';
                    $output .= $title;
                    $output .= "</h3>";
                }
                $output .= $this->make_list($patents, $showname, $showyear, $showpatentname, 0);
            }
        }
        return $this->langdiv_open . $output . $this->langdiv_close;
    }

	/**
	 * Name : singlePatent
	 *
	 * Use: get single patent array
	 *
	 * Returns: single patent array
	 *
	 */
    public function singlePatent($hide = '', $showname = 1, $showyear = 0, $showpatentname = 1)
    {
        $ws = new CRIS_patents();

        try {
            $patentArray = $ws->by_id($this->id);
        } catch (Exception $ex) {
            return;
        }

        if (!count($patentArray)) {
            $output = '<p>' . __('Es wurden leider keine Patente gefunden.', 'fau-cris') . '</p>';
            return $output;
        }

        $output = $this->make_list($patentArray, $showname, $showyear, $showpatentname);

        return $this->langdiv_open . $output . $this->langdiv_close;
    }

	/**
	 * Name : fetch_patents
	 *
	 * Use: get all patents by orga and person
	 *
	 * Returns: patent list
	 *
	 */

    private function fetch_patents($year = '', $start = '', $end = '', $type = ''): array {
        $filter = Tools::patent_filter($year, $start, $end, $type);

        $ws = new CRIS_patents();
        $patentArray = array();

        try {
            if ($this->einheit === "orga") {
                $patentArray = $ws->by_orga_id($this->id, $filter);
            }
            if ($this->einheit === "person") {
                $patentArray = $ws->by_pers_id($this->id, $filter);
            }
        } catch (Exception $ex) {
            $patentArray = array();
        }
        return $patentArray;
    }

	/**
	 * Name : make_list
	 *
	 * Use: format the patent attributes in html
	 *
	 * Returns: html formatted list
	 *
	 */

    private function make_list($patents, $name = 1, $year = 1, $patentname = 1, $showtype = 1): string {
        global $post;
        $patentlist = "<ul class=\"cris-patents\">";

        foreach ($patents as $patent) {
            $patent = (array) $patent;
            foreach ($patent['attributes'] as $attribut => $v) {
                $patent[$attribut] = $v;
            }
            unset($patent['attributes']);

            $inventors = explode("|", $patent['exportinventors']);
            $inventorIDs = explode(",", $patent['relinventorsid']);
            $inventorsArray = array();
            foreach ($inventorIDs as $i => $key) {
                $inventorsArray[] = array('id' => $key, 'name' => $inventors[$i]);
            }
            $inventorsList = array();
            foreach ($inventorsArray as $inventor) {
                $inventor_elements = explode(":", $inventor['name']);
                $inventor_firstname = $inventor_elements[1];
                $inventor_lastname = $inventor_elements[0];
                $inventorsList[] = Tools::get_person_link($inventor['id'], $inventor_firstname, $inventor_lastname, $this->cris_patent_link, $this->cms, $this->pathPersonenseiteUnivis, $this->univis, 0);
            }
            $inventors_html = implode(", ", $inventorsList);

            $patent_id = $patent['ID'];
            $patent_name = ($this->sc_lang == 'de') ? $patent['cftitle'] : $patent['cftitle_en'];
            $patent_type = Tools::getName('patents', $patent['patenttype'], $this->sc_lang);
            $patent_abstract = $patent['cfabstr'];
            $patent_number = $patent['cfpatentnum'];
            $patent_link = $patent['patnrlink'];
            $patent_registered = date_i18n(get_option('date_format'), strtotime($patent['cfregistrdate']));
            $patent_appproved = date_i18n(get_option('date_format'), strtotime($patent['cfapprovdate']));
            $patent_expiry = date_i18n(get_option('date_format'), strtotime($patent['patexpirydate']));

            $patentlist .= "<li>";

            if (!empty($patent_name)) {
                $patentlist .= "<strong><a href=\"" . Tools::get_item_url("cfrespat", $patent_name, $patent_id, $post->ID, $this->page_lang) . "\" title=\"" . __('Detailansicht auf cris.fau.de in neuem Fenster &ouml;ffnen', 'fau-cris') . "\">" . $patent_name . "</a></strong>";
            }
            if (!empty($patent_type) || !empty($patent_number)) {
                $patentlist .= " (";
            }
            if (!empty($patent_type) & $showtype != 0) {
                $patentlist .= $patent_type . ": ";
            }
            if (!empty($patent_number)) {
                if (!empty($patent_link)) {
                    $patentlist .= '<a href="' . $patent_link . '" target="blank" title="' . __('Eintrag auf DEPATISnet in neuem Fenster &ouml;ffnen', 'fau-cris') . '">';
                }
                $patentlist .= $patent_number;
                if (!empty($patent_link)) {
                    $patentlist .= "</a>";
                }
            }
            if (!empty($patent_type) && !empty($patent_number)) {
                $patentlist .= ")";
            }
            if (!empty($inventors)) {
                $patentlist .= "<br />" . __('Erfinder', 'fau-cris') . ": " . $inventors_html;
            }
            $patentlist .= "</li>";
        }

        $patentlist .= "</ul>";
        return $patentlist;
    }
}

class CRIS_patents extends Webservice
{
    /*
     * patents/grants requests
     */

    public function by_orga_id($orgaID = null, &$filter = null): array {
        if ($orgaID === null || $orgaID === "0") {
	        return new \WP_Error(
		        'cris-orgid-error',
		        __('Bitte geben Sie die CRIS-ID der Organisation, Person oder des Patents an.', 'fau-cris')
	        );
        }

        if (!is_array($orgaID)) {
            $orgaID = array($orgaID);
        }

        $requests = array();
        foreach ($orgaID as $_o) {
            $requests[] = sprintf("getautorelated/Organisation/%d/ORGA_2_PATE_1", $_o);
        }
        return $this->retrieve($requests, $filter);
    }

    public function by_pers_id($persID = null, &$filter = null): array {
        if ($persID === null || $persID === "0") {
	        return new \WP_Error(
		        'cris-orgid-error',
		        __('Bitte geben Sie die CRIS-ID der Organisation, Person oder des Patents an.', 'fau-cris')
	        );
        }

        if (!is_array($persID)) {
            $persID = array($persID);
        }

        $requests = array();
        foreach ($persID as $_p) {
            $requests[] = sprintf('getautorelated/Person/%s/PERS_2_PATE_1', $_p);
        }
        return $this->retrieve($requests, $filter);
    }

    public function by_id($awarID = null): array {
        if ($awarID === null || $awarID === "0") {
	        return new \WP_Error(
		        'cris-orgid-error',
		        __('Bitte geben Sie die CRIS-ID der Organisation, Person oder des Patents an.', 'fau-cris')
	        );
        }

        if (!is_array($awarID)) {
            $awarID = array($awarID);
        }

        $requests = array();
        foreach ($awarID as $_p) {
            $requests[] = sprintf('get/cfrespat/%d', $_p);
        }
        return $this->retrieve($requests);
    }

    private function retrieve($reqs, &$filter = null): array {
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

        $patents = array();

        foreach ($data as $_d) {
            foreach ($_d as $patent) {
                $a = new CRIS_patent($patent);
                if ($a->ID) {
                    $a->attributes['registryear'] = mb_substr($a->attributes['cfregistrdate'], 0, 4);
                    $a->attributes['approvyear'] = $a->attributes['cfapprovdate'] != '' ? mb_substr($a->attributes['cfapprovdate'], 0, 4) : '';
                    $a->attributes['expiryyear'] = $a->attributes['patexpirydate'] != '' ? mb_substr($a->attributes['patexpirydate'], 0, 4) : '';
                }

                if ($a->ID && ($filter === null || $filter->evaluate($a))) {
                    $patents[$a->ID] = $a;
                }
            }
        }

        return $patents;
    }
}

class CRIS_patent extends CRIS_Entity
{
    /*
     * object for single patent
     */

    public function __construct($data)
    {
        parent::__construct($data);
    }
}
