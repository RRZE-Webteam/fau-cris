<?php

require_once("class_Tools.php");
require_once("class_Webservice.php");
require_once("class_Filter.php");
require_once("class_Formatter.php");

class Publikationen {

    private $options;
    public $output;

    public function __construct($einheit = '', $id = '', $nameorder = '', $page_lang = 'de') {
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

        $this->order = $this->options['cris_pub_order'];
        $this->subtypeorder = $this->options['cris_pub_subtypes_order'];
        $this->univisLink = isset($this->options['cris_univis']) ? $this->options['cris_univis'] : 'none';
        $this->bibtex = $this->options['cris_bibtex'];
        $this->bibtexlink = "https://cris.fau.de/bibtex/publication/%s.bib";
        if ($this->cms == 'wbk' && $this->univisLink == 'person') {
            $this->univis = Tools::get_univis();
        }

        if ((!$this->orgNr || $this->orgNr == 0) && $id == '') {
            print '<p><strong>' . __('Bitte geben Sie die CRIS-ID der Organisation, Person oder Publikation an.', 'fau-cris') . '</strong></p>';
        }
        if (in_array($einheit, array("person", "orga", "publication", "project", "field", "field_proj"))) {
            $this->id = $id;
            $this->einheit = $einheit;
        } else {
            // keine Einheit angegeben -> OrgNr aus Einstellungen verwenden
            $this->id = $this->orgNr;
            $this->einheit = "orga";
        }
        if (strlen(trim($nameorder))) {
            $this->nameorder = $nameorder;
        } else {
            $this->nameorder = $this->options['cris_name_order_plugin'];
        }
        $this->page_lang = $page_lang;
    }

    /*
     * Ausgabe aller Publikationen ohne Gliederung
     */

    public function pubListe($param = array(), $content = '') {
        $year = (isset($param['year']) && $param['year'] != '') ? $param['year'] : '';
        $start = (isset($param['start']) && $param['start'] != '') ? $param['start'] : '';
        $end = (isset($param['end']) && $param['end'] != '') ? $param['end'] : '';
        $type = (isset($param['type']) && $param['type'] != '') ? $param['type'] : '';
        $subtype = (isset($param['subtype']) && $param['subtype'] != '') ? $param['subtype'] : '';
        $quotation = (isset($param['quotation']) && $param['quotation'] != '') ? $param['quotation'] : '';
        $limit = (isset($param['limit']) && $param['limit'] != '') ? $param['limit'] : '';
        $sortby = (isset($param['sortby']) && $param['sortby'] != '') ? $param['sortby'] : 'virtualdate';
        $fau = (isset($param['fau']) && $param['fau'] != '') ? $param['fau'] : '';
        $peerreviewed = (isset($param['peerreviewed']) && $param['peerreviewed'] != '') ? $param['peerreviewed'] : '';
        $notable = (isset($param['notable']) && $param['notable'] != '') ? $param['notable'] : 0;
        $language = (isset($param['language']) && $param['language'] != '') ? $param['language'] : '';

        $pubArray = $this->fetch_publications($year, $start, $end, $type, $subtype, $fau, $peerreviewed, $notable, $language);

        if (!count($pubArray)) {
            $output = '<p>' . __('Es wurden leider keine Publikationen gefunden.', 'fau-cris') . '</p>';
            return $output;
        }

        switch ($sortby) {
            case 'created':
                $order = 'createdon';
                break;
            case 'updated':
                $order = 'updatedon';
                break;
            default:
                $order = 'virtualdate';
        }
        $formatter = new CRIS_formatter(NULL, NULL, $order, SORT_DESC);
        $res = $formatter->execute($pubArray);
        if ($limit != '')
            $pubList = array_slice($res[$order], 0, $limit);
        else
            $pubList = $res[$order];

        $output = '';

        if ($quotation == 'apa' || $quotation == 'mla') {
            $output .= $this->make_quotation_list($pubList, $quotation);
        } else {
            if ($param['sc_type'] == 'custom') {
                $output .= $this->make_custom_list($pubList, $content, '', $this->page_lang);
            } else {
                $output .= $this->make_list($pubList, 1, $this->nameorder, $this->page_lang);
            }
        }

        return $output;
    }

    /*
     * Ausgabe aller Publikationen nach Jahren gegliedert
     */

    public function pubNachJahr($param = array(), $field = '', $content = '', $fsp = false) {
        $year = (isset($param['year']) && $param['year'] != '') ? $param['year'] : '';
        $start = (isset($param['start']) && $param['start'] != '') ? $param['start'] : '';
        $end = (isset($param['end']) && $param['end'] != '') ? $param['end'] : '';
        $type = (isset($param['type']) && $param['type'] != '') ? $param['type'] : '';
        $subtype = (isset($param['subtype']) && $param['subtype'] != '') ? $param['subtype'] : '';
        $quotation = (isset($param['quotation']) && $param['quotation'] != '') ? $param['quotation'] : '';
        $order2 = (isset($param['order2']) && $param['order2'] != '') ? $param['order2'] : 'author';
        $fau = (isset($param['fau']) && $param['fau'] != '') ? $param['fau'] : '';
        $peerreviewed = (isset($param['peerreviewed']) && $param['peerreviewed'] != '') ? $param['peerreviewed'] : '';
        $notable = (isset($param['notable']) && $param['notable'] != '') ? $param['notable'] : 0;
        $format = (isset($param['format']) && $param['format'] != '') ? $param['format'] : '';
        $language = (isset($param['language']) && $param['language'] != '') ? $param['language'] : '';

        $pubArray = $this->fetch_publications($year, $start, $end, $type, $subtype, $fau, $peerreviewed, $notable, $field, $language, $fsp);

        if (!count($pubArray)) {
            $output = '<p>' . __('Es wurden leider keine Publikationen gefunden.', 'fau-cris') . '</p>';
            return $output;
        }

        // Sortierreihenfolge
        $typeorder = $this->order;
        if ($typeorder[0] != '' && ((array_search($typeorder[0], array_column(CRIS_Dicts::$typeinfos['publications'], 'short')) !== false) || array_search($typeorder[0], array_column(CRIS_Dicts::$typeinfos['publications'], 'short_alt')) !== false)) {
            foreach ($typeorder as $key => $value) {
                $typeorder[$key] = Tools::getType('publications', $value);
            }
        } else {
            $typeorder = Tools::getOrder('publications');
        }
        switch ($order2) {
            case 'author':
                $formatter = new CRIS_formatter("publyear", SORT_DESC, "relauthors", SORT_ASC);
                $subformatter = new CRIS_formatter(NULL, NULL, "relauthors", SORT_ASC);
                break;
            case 'type':
                $formatter = new CRIS_formatter("publyear", SORT_DESC, "virtualdate", SORT_DESC);
                $subformatter = new CRIS_formatter("publication type", array_values($typeorder), "virtualdate", SORT_DESC);
                break;
            default:
                $formatter = new CRIS_formatter("publyear", SORT_DESC, "virtualdate", SORT_DESC);
                $subformatter = new CRIS_formatter(NULL, NULL, "virtualdate", SORT_DESC);
                break;
        }
        $pubList = $formatter->execute($pubArray);

        $output = '';
        $showsubtype = ($subtype == '') ? 1 : 0;

        if (empty($year) && shortcode_exists('collapsibles') && $format == 'accordion') {
            $shortcode_data = '';
            $openfirst = ' load="open"';
            foreach ($pubList as $array_year => $publications) {
                $shortcode_data_inner = '';
                $pubSubList = $subformatter->execute($publications);
                foreach ($pubSubList as $array_subtype => $publications_sub) {
                    if ($order2 == 'type') {
                        $shortcode_data_inner .= "<h4>";
                        $shortcode_data_inner .= Tools::getTitle('publications', $array_subtype, $this->page_lang);;
                        $shortcode_data_inner .= "</h4>";
                    }
                    if ($quotation == 'apa' || $quotation == 'mla') {
                        $shortcode_data_inner .= $this->make_quotation_list($publications_sub, $quotation);
                    } else {
                        if ($param['sc_type'] == 'custom') {
                            $shortcode_data_inner .= $this->make_custom_list($publications_sub, $content, '', $this->page_lang);
                        } else {
                            $shortcode_data_inner .= $this->make_list($publications_sub, $showsubtype, $this->nameorder, $this->page_lang);
                        }
                    }
                }
                $shortcode_data .= do_shortcode('[collapse title="' . $array_year . '"]' . $shortcode_data_inner . '[/collapse]');
            }
            $openfirst = '';
            $output .= do_shortcode('[collapsibles expand-all-link="true"]' . $shortcode_data . '[/collapsibles]');
        } else {
            foreach ($pubList as $array_year => $publications) {
                if (empty($year)) {
                    $output .= '<h3>' . $array_year . '</h3>';
                }
                $pubSubList = $subformatter->execute($publications);
                foreach ($pubSubList as $array_subtype => $publications_sub) {
                    // Zwischenüberschrift
                    if ($order2 == 'type') {
                        $output .= "<h4>";
                        $output .= Tools::getTitle('publications', $array_subtype, $this->page_lang);;
                        $output .= "</h4>";
                    }
                    if ($quotation == 'apa' || $quotation == 'mla') {
                        $output .= $this->make_quotation_list($publications_sub, $quotation);
                    } else {
                        if ($param['sc_type'] == 'custom') {
                            $output .= $this->make_custom_list($publications_sub, $content, '', $this->page_lang);
                        } else {
                            $output .= $this->make_list($publications_sub, $showsubtype, $this->nameorder, $this->page_lang);
                        }
                    }
                }
            }
        }
        return $output;
    }

    /*
     * Ausgabe aller Publikationen nach Publikationstypen gegliedert
     */

    public function pubNachTyp($param = array(), $field = '', $content = '', $fsp = false) {
        $year = (isset($param['year']) && $param['year'] != '') ? $param['year'] : '';
        $start = (isset($param['start']) && $param['start'] != '') ? $param['start'] : '';
        $end = (isset($param['end']) && $param['end'] != '') ? $param['end'] : '';
        $type = (isset($param['type']) && $param['type'] != '') ? $param['type'] : '';
        $subtype = (isset($param['subtype']) && $param['subtype'] != '') ? $param['subtype'] : '';
        $quotation = (isset($param['quotation']) && $param['quotation'] != '') ? $param['quotation'] : '';
        $order2 = (isset($param['order2']) && $param['order2'] != '') ? $param['order2'] : 'date';
        $fau = (isset($param['fau']) && $param['fau'] != '') ? $param['fau'] : '';
        $peerreviewed = (isset($param['peerreviewed']) && $param['peerreviewed'] != '') ? $param['peerreviewed'] : '';
        $notable = (isset($param['notable']) && $param['notable'] != '') ? $param['notable'] : 0;
        $format = (isset($param['format']) && $param['format'] != '') ? $param['format'] : '';
        $language = (isset($param['language']) && $param['language'] != '') ? $param['language'] : '';

        $pubArray = $this->fetch_publications($year, $start, $end, $type, $subtype, $fau, $peerreviewed, $notable, $field, $language, $fsp);

        if (!count($pubArray)) {
            $output = '<p>' . __('Es wurden leider keine Publikationen gefunden.', 'fau-cris') . '</p>';
            return $output;
        }

        // Publikationstypen sortieren
        $order = $this->order;
        if ($order[0] != '' && ((array_search($order[0], array_column(CRIS_Dicts::$typeinfos['publications'], 'short')) !== false) || array_search($order[0], array_column(CRIS_Dicts::$typeinfos['publications'], 'short_alt')) !== false)) {
            foreach ($order as $key => $value) {
                $order[$key] = Tools::getType('publications', $value);
            }
        } else {
            $order = Tools::getOrder('publications');
        }

        // sortiere nach Typenliste, innerhalb des Typs nach $order2 sortieren
        if ($order2 == 'author') {
            $formatter = new CRIS_formatter("publication type", array_values($order), "relauthors", SORT_ASC);
        } else {
            $formatter = new CRIS_formatter("publication type", array_values($order), "virtualdate", SORT_DESC);
        }
        $pubList = $formatter->execute($pubArray);

        $output = '';

        if (empty($type) && shortcode_exists('collapsibles') && $format == 'accordion') {
            $shortcode_data = '';
            $openfirst = ' load="open"';
            foreach ($pubList as $array_type => $publications) {
                // Zwischenüberschrift (= Publikationstyp), außer wenn nur ein Typ gefiltert wurde
                $title = Tools::getTitle('publications', $array_type, $this->page_lang);
                //if ($array_type == 'Other') {
                $shortcode_data_other = '';
                // Weitrere Untergliederung für Subtypen
                $subtypeorder = $this->subtypeorder;
                if ($array_type == 'Other' && $subtypeorder[0] != '' && array_search($subtypeorder[0], array_column(CRIS_Dicts::$typeinfos['publications'][$array_type]['subtypes'], 'short'))) {
                    foreach ($subtypeorder as $key => $value) {
                        $subtypeorder[$key] = Tools::getType('publications', $value, $array_type);
                    }
                } else {
                    $subtypeorder = Tools::getOrder('publications', $array_type);
                }
                switch ($order2) {
                    case 'author':
                        $subformatter = new CRIS_formatter(NULL, NULL, "relauthors", SORT_ASC);
                        break;
                    case 'subtype':
                        $subformatter = new CRIS_formatter("subtype", array_values($subtypeorder), "virtualdate", SORT_DESC);
                        break;
                    case 'year':
                        $subformatter = new CRIS_formatter("publyear", SORT_DESC, "virtualdate", SORT_DESC);
                        break;
                    default:
                        $subformatter = new CRIS_formatter(NULL, NULL, "virtualdate", SORT_DESC);
                        break;
                }
                $pubOtherList = $subformatter->execute($publications);

                foreach ($pubOtherList as $array_subtype => $publications_sub) {
                    // Zwischenüberschrift (= Publikationstyp), außer wenn nur ein Typ gefiltert wurde
                    if ($order2 == 'subtype') {
                        $title_sub = Tools::getTitle('publications', $array_subtype, $this->page_lang, $array_type);
                        $shortcode_data_other .= "<h4>";
                        $shortcode_data_other .= $title_sub;
                        $shortcode_data_other .= "</h4>";
                    }
                    if ($order2 == 'year') {
                        $shortcode_data_other .= "<h4>";
                        $shortcode_data_other .= $array_subtype;
                        $shortcode_data_other .= "</h4>";
                    }
                    if ($quotation == 'apa' || $quotation == 'mla') {
                        $shortcode_data_other .= $this->make_quotation_list($publications_sub, $quotation);
                    } else {
                        $shortcode_data_other .= $this->make_list($publications_sub, 0, $this->nameorder, $this->page_lang);
                    }
                }
                $shortcode_data .= do_shortcode('[collapse title="' . $title . '"]' . $shortcode_data_other . '[/collapse]');
                $openfirst = '';
            }
            $output .= do_shortcode('[collapsibles expand-all-link="true"]' . $shortcode_data . '[/collapsibles]');
        } else {
            foreach ($pubList as $array_type => $publications) {
                // Zwischenüberschrift (= Publikationstyp), außer wenn nur ein Typ gefiltert wurde
                if (empty($type)) {
                    $title = Tools::getTitle('publications', $array_type, $this->page_lang);
                    if (!shortcode_exists('collapsibles') || $format != 'accordion') {
                        $output .= "<h3>";
                        $output .= $title;
                        $output .= "</h3>";
                    }
                }
                //var_dump($array_type);
                // Weitrere Untergliederung (Subtypen)
                $subtypeorder = $this->subtypeorder;
                if ($array_type == 'Other' && $subtypeorder[0] != '' && array_search($subtypeorder[0], array_column(CRIS_Dicts::$typeinfos['publications'][$array_type]['subtypes'], 'short'))) {
                    foreach ($subtypeorder as $key => $value) {
                        $subtypeorder[$key] = Tools::getType('publications', $value, $array_type);
                    }
                } else {
                    $subtypeorder = Tools::getOrder('publications', $array_type);
                }
                switch ($order2) {
                    case 'author':
                        $subformatter = new CRIS_formatter(NULL, NULL, "relauthors", SORT_ASC);
                        break;
                    case 'subtype':
                        $subformatter = new CRIS_formatter("subtype", array_values($subtypeorder), "virtualdate", SORT_DESC);
                        break;
                    case 'year':
                        $subformatter = new CRIS_formatter("publyear", SORT_DESC, "virtualdate", SORT_DESC);
                        break;
                    default:
                        $subformatter = new CRIS_formatter(NULL, NULL, "virtualdate", SORT_DESC);
                        break;
                }
                $pubOtherList = $subformatter->execute($publications);

                foreach ($pubOtherList as $array_subtype => $publications_sub) {
                    // Zwischenüberschrift (= Publikationstyp), außer wenn nur ein Typ gefiltert wurde
                    if ($order2 == 'subtype') {
                        $title_sub = Tools::getTitle('publications', $array_subtype, $this->page_lang, $array_type);
                        $output .= "<h4>";
                        $output .= $title_sub;
                        $output .= "</h4>";
                    }
                    if ($order2 == 'year') {
                        $output .= "<h4>";
                        $output .= $array_subtype;
                        $output .= "</h4>";
                    }
                    if ($quotation == 'apa' || $quotation == 'mla') {
                        $output .= $this->make_quotation_list($publications_sub, $quotation);
                    } else {
                        if ($param['sc_type'] == 'custom') {
                            $output .= $this->make_custom_list($publications_sub, $content, '', $this->page_lang);
                        } else {
                            $output .= $this->make_list($publications_sub, 0, $this->nameorder, $this->page_lang);
                        }
                    }
                }
            }
        }
        return $output;
    }

// Ende pubNachTyp()

    public function singlePub($quotation = '', $content = '', $sc_type = 'default') {
        $ws = new CRIS_publications();

        try {
            $pubArray = $ws->by_id($this->id);
        } catch (Exception $ex) {
            return;
        }

        if (!count($pubArray))
            return;

        if ($quotation == 'apa' || $quotation == 'mla') {
            $output = $this->make_quotation_list($pubArray, $quotation);
        } else {
            if ($sc_type == 'custom') {
                $output = $this->make_custom_list($pubArray, $content, '', $this->page_lang);
            } else {
                $output = $this->make_list($pubArray, 0, $this->nameorder, $this->page_lang);
            }
        }

        return $output;
    }

    public function projectPub($project, $quotation = '') {
        $ws = new CRIS_publications();

        try {
            $pubArray = $ws->by_project($project);
        } catch (Exception $ex) {
            return;
        }

        if (!count($pubArray))
            return;
        
        if (array_key_exists('relation right seq', reset($pubArray)->attributes)) {
            $sortby = 'relation right seq';
            $orderby = $sortby;
        } else {
            $sortby = NULL;
            $orderby = __('O.A.', 'fau-cris');
        }
        $formatter = new CRIS_formatter(NULL, NULL, $sortby, SORT_ASC);
        $res = $formatter->execute($pubArray);
        $pubList = $res[$orderby];

        if ($quotation == 'apa' || $quotation == 'mla') {
            $output = $this->make_quotation_list($pubList, $quotation);
        } else {
            $output = $this->make_list($pubList, 0, $this->nameorder, $this->page_lang);
        }

        return $output;
    }

    public function fieldPub($field, $quotation = '', $seed = false, $publications_limit = '', $fsp = false) {
        $ws = new CRIS_publications();
        if ($seed)
            $ws->disable_cache();
        try {
            $filter = null;
            $pubArray = $ws->by_field($field, $filter, $fsp);
        } catch (Exception $ex) {
            return;
        }

        if (!count($pubArray))
            return;
        
        if (array_key_exists('relation right seq', reset($pubArray)->attributes)) {
            $sortby = 'relation right seq';
            $orderby = $sortby;
        } else {
            $sortby = NULL;
            $orderby = __('O.A.', 'fau-cris');
        }
        $formatter = new CRIS_formatter(NULL, NULL, $sortby, SORT_ASC);
        $res = $formatter->execute($pubArray);
        $pubList = $res[$orderby];

        if ($publications_limit != '') {
            $pubList = array_slice($pubList, 0, $publications_limit, true);
        }

        $output = '';
        if ($quotation == 'apa' || $quotation == 'mla') {
            $output = $this->make_quotation_list($pubList, $quotation);
        } else {
            $output = $this->make_list($pubList, 0, $this->nameorder, $this->page_lang);
        }

        return $output;
    }

    public function equiPub($equipment, $quotation = '', $seed = false, $publications_limit = '') {
        $ws = new CRIS_publications();
        if ($seed)
            $ws->disable_cache();
        try {
            $pubArray = $ws->by_equipment($equipment);
        } catch (Exception $ex) {
            return;
        }

        if (!count($pubArray))
            return;

        if (array_key_exists('relation right seq', reset($pubArray)->attributes)) {
            $sortby = 'relation right seq';
            $orderby = $sortby;
        } else {
            $sortby = NULL;
            $orderby = __('O.A.', 'fau-cris');
        }
        $formatter = new CRIS_formatter(NULL, NULL, $sortby, SORT_ASC);
        $res = $formatter->execute($pubArray);
        $pubList = $res[$orderby];

        if ($publications_limit != '') {
            $pubList = array_slice($pubList, 0, $publications_limit, true);
        }

        $output = '';
        if ($quotation == 'apa' || $quotation == 'mla') {
            $output = $this->make_quotation_list($pubList, $quotation);
        } else {
            $output = $this->make_list($pubList, 0, $this->nameorder, $this->page_lang);
        }

        return $output;
    }

    /* =========================================================================
     * Private Functions
      ======================================================================== */

    /*
     * Holt Daten vom Webservice je nach definierter Einheit.
     */

    private function fetch_publications($year = '', $start = '', $end = '', $type = '', $subtype = '', $fau = '', $peerreviewed = '', $notable = 0, $field = '', $language = '', $fsp = false) {
        $filter = NULL;

        $filter = Tools::publication_filter($year, $start, $end, $type, $subtype, $fau, $peerreviewed, $language);
        $ws = new CRIS_publications();

        try {
            if ($this->einheit === "orga") {
                $pubArray = $ws->by_orga_id($this->id, $filter);
            }
            if ($this->einheit === "person") {
                $pubArray = $ws->by_pers_id($this->id, $filter, $notable);
            }
            if ($this->einheit === "field" || $this->einheit === "field_proj") {
                $pubArray = $ws->by_field($field, $filter, $fsp, $this->einheit);
            }
            if ($this->einheit === "publication") {
                $pubArray = $ws->by_id($this->id);
            }
        } catch (Exception $ex) {
            $pubArray = array();
        }

        return $pubArray;
    }

    /*
     * Ausgabe der Publikationsdetails in Zitierweise (MLA/APA)
     */

    private function make_quotation_list($publications, $quotation) {

        $quotation = strtolower($quotation);
        $publist = "<ul class=\"cris-publications\">";

        foreach ($publications as $publication) {
            $publication->insert_quotation_links();
            $publist .= "<li>";
            $publist .= $publication->attributes['quotation' . $quotation . 'link'];
            if ($publication->attributes['openaccess'] == "Ja" && isset($this->options['cris_oa']) && $this->options['cris_oa'] == 1) {
                $publist .= "<span aria-hidden class=\"oa-icon\" title=\"Open-Access-Publikation\"></span>";
            }
            if (isset($this->options['cris_doi']) && $this->options['cris_doi'] == 1 && !empty($publication->attributes['doi'])) {
                $publist .= "<br />DOI: <a href='" . FAU_CRIS::doi . $publication->attributes['doi'] . "' target='blank' itemprop=\"url\">" . $publication->attributes['doi'] . "</a>";
            }
            if (isset($this->options['cris_url']) && $this->options['cris_url'] == 1 && !empty($publication->attributes['cfuri'])) {
                $publist .= "<br />URL: <a href='" . $publication->attributes['cfuri'] . "' target='blank' itemprop=\"url\">" . $publication->attributes['cfuri'] . "</a>";
            }
            if (isset($this->options['cris_bibtex']) && $this->options['cris_bibtex'] == 1) {
                $publist .= '<br />BibTeX: <a href="' . sprintf($this->bibtexlink, $publication->attributes['id_publ']) . '">Download</a>';
            }
            $publist .= "</li>";
        }

        $publist .= "</ul>";

        return $publist;
    }

    /*
     * Ausgabe der Publikationsdetails, unterschiedlich nach Publikationstyp
     */

    private function make_list($publications, $showsubtype = 0, $nameorder = '') {

        $publist = "<ul class=\"cris-publications\">";

        foreach ($publications as $publicationObject) {

            $publication = $publicationObject->attributes;
            // id
            $id = $publicationObject->ID;
            // authors
            if (strtolower($publication['complete author relations']) == 'yes') {
                $authors = explode("|", $publication['exportauthors']);
                $authorIDs = explode(",", $publication['relpersid']);
                $authorsArray = array();
                foreach ($authorIDs as $i => $key) {
                    $nameparts = explode(":", $authors[$i]);
                    $authorsArray[] = array(
                        'id' => $key,
                        'lastname' => $nameparts[0],
                        'firstname' => $nameparts[1]);
                }
                $authorList = array();
                foreach ($authorsArray as $author) {
                    $authorList[] = Tools::get_person_link($author['id'], $author['firstname'], $author['lastname'], $this->univisLink, $this->cms, $this->pathPersonenseiteUnivis, $this->univis, 1, 1, $nameorder);
                }
                $authors_html = implode(", ", $authorList);
            } else {
                if ($publication['publication type'] == "Editorial") {
                    $authors_html = $publication['srceditors'];
                } else {
                    $authors_html = $publication['srcauthors'];
                }
            }
            // title (bei Rezensionen mit Original-Autor davor)
            $title = '';
            if (($publication['publication type'] == 'Translation' || $publication['subtype'] == 'Rezension') && $publication['originalauthors'] != '') {
                $title = strip_tags($publication['originalauthors']) . ': ';
            }
            $title .= (array_key_exists('cftitle', $publication) ? strip_tags($publication['cftitle']) : __('O.T.', 'fau-cris'));
            global $post;
            $title_html = "<span class=\"title\" itemprop=\"name\"><strong>"
                    . "<a href=\"" . Tools::get_item_url("publication", $title, $id, $post->ID) . "\" title=\"Detailansicht in neuem Fenster &ouml;ffnen\">"
                    . $title
                    . "</a></strong></span>";
            if ($publication['openaccess'] == "Ja") {
                $title_html .= "<span aria-hidden class=\"oa-icon\" title=\"Open-Access-Publikation\"></span>";
            }
            // make array
            $pubDetails = array(
                'id' => $id,
                'authors' => $authors_html,
                'title' => $title_html,
                'city' => (array_key_exists('cfcitytown', $publication) ? strip_tags($publication['cfcitytown']) : __('O.O.', 'fau-cris')),
                'publisher' => (array_key_exists('publisher', $publication) ? strip_tags($publication['publisher']) : __('O.A.', 'fau-cris')),
                'year' => (array_key_exists('publyear', $publication) ? strip_tags($publication['publyear']) : __('O.J.', 'fau-cris')),
                'pubType' => (array_key_exists('futurepublicationtype', $publication) && $publication['futurepublicationtype'] != '') ? strip_tags($publication['futurepublicationtype']) : (array_key_exists('publication type', $publication) ? strip_tags($publication['publication type']) : __('O.A.', 'fau-cris')),
                'pubStatus' => (array_key_exists('publstatus', $publication) ? strip_tags($publication['publstatus']) : ''),
                'pagesTotal' => (array_key_exists('cftotalpages', $publication) ? strip_tags($publication['cftotalpages']) : ''),
                'pagesRange' => (array_key_exists('pagesrange', $publication) ? strip_tags($publication['pagesrange']) : ''),
                'lexiconColumn' => (array_key_exists('lexiconcolumn', $publication) ? strip_tags($publication['lexiconcolumn']) : ''),
                'volume' => (array_key_exists('cfvol', $publication) ? strip_tags($publication['cfvol']) : __('O.A.', 'fau-cris')),
                'series' => (array_key_exists('cfseries', $publication) ? strip_tags($publication['cfseries']) : __('O.A.', 'fau-cris')),
                'seriesNumber' => !empty($publication['book volume']) ? strip_tags($publication['book volume']) : '',
                'ISBN' => (array_key_exists('cfisbn', $publication) ? strip_tags($publication['cfisbn']) : __('O.A.', 'fau-cris')),
                'ISSN' => (array_key_exists('cfissn', $publication) ? strip_tags($publication['cfissn']) : __('O.A.', 'fau-cris')),
                'DOI' => (array_key_exists('doi', $publication) ? strip_tags($publication['doi']) : __('O.A.', 'fau-cris')),
                'OA' => (array_key_exists('openaccess', $publication) ? strip_tags($publication['openaccess']) : false),
                'OAlink' => (array_key_exists('openaccesslink', $publication) ? strip_tags($publication['openaccesslink']) : ''),
                'URI' => (array_key_exists('cfuri', $publication) ? strip_tags($publication['cfuri']) : __('O.A.', 'fau-cris')),
                'editiors' => (array_key_exists('editor', $publication) ? strip_tags($publication['editor']) : __('O.A.', 'fau-cris')),
                'booktitle' => (array_key_exists('edited volumes', $publication) ? strip_tags($publication['edited volumes']) : __('O.A.', 'fau-cris')), // Titel des Sammelbands
                'journaltitle' => (array_key_exists('journalname', $publication) ? strip_tags($publication['journalname']) : __('O.A.', 'fau-cris')),
                'eventtitle' => (array_key_exists('event title', $publication) ? strip_tags($publication['event title']) : ''),
                'eventlocation' => (array_key_exists('event location', $publication) ? strip_tags($publication['event location']) : ''),
                'eventstart_raw' => !empty($publication['event start date']) ? $publication['event start date'] : (!empty($publication['publyear']) ? $publication['publyear'] : '-----'),
                'eventend_raw' => (!empty($publication['event end date']) ? $publication['event end date'] : ''),
                'eventstart' => !empty($publication['event start date']) ? date_i18n( get_option( 'date_format' ), strtotime(strip_tags($publication['event start date']))) : '',
                'eventend' => (!empty($publication['event end date']) ? date_i18n( get_option( 'date_format' ), strtotime(strip_tags($publication['event end date']))) : ''),
                'origTitle' => (array_key_exists('originaltitel', $publication) ? strip_tags($publication['originaltitel']) : __('O.A.', 'fau-cris')),
                'language' => (array_key_exists('language', $publication) ? strip_tags($publication['language']) : __('O.A.', 'fau-cris')),
                'bibtex_link' => '<a href="' . sprintf($this->bibtexlink, $id) . '">Download</a>',
                'otherSubtype' => (array_key_exists('type other subtype', $publication) ? $publication['type other subtype'] : ''),
                'thesisSubtype' => (array_key_exists('publication thesis subtype', $publication) ? $publication['publication thesis subtype'] : ''),
                'articleNumber' => (array_key_exists('article number', $publication) ? $publication['article number'] : '')
            );

            switch (strtolower($pubDetails['pubType'])) {

                case "book": // OK
                    $publist .= "<li itemscope itemtype=\"http://schema.org/Book\">";
                    $publist .= $pubDetails['authors'] . ':';
                    $publist .= "<br />" . $pubDetails['title'];
                    $publist .= $publication['publication type'] == 'Unpublished' ? ' (' . Tools::getName('publications', $publication['publication type'], $this->page_lang, $pubDetails['pubType']) . ')' : '';
                    $publist .= (($pubDetails['city'] != '') || ($pubDetails['publisher'] != '') || ($pubDetails['year'] != '')) ? "<br />" : '';
                    $publist .= $pubDetails['volume'] != '' ? $pubDetails['volume'] . ". " : '';
                    if (!empty($pubDetails['publisher'])) {
                        $publist .= "<span itemprop=\"publisher\" itemscope itemtype=\"http://schema.org/Organization\">";
                        $publist .= $pubDetails['city'] != '' ? "<span class=\"city\" itemprop=\"address\" itemscope itemtype=\"http://schema.org/PostalAddress\">"
                                . "<span itemprop=\"addressLocality\">" . $pubDetails['city'] . "</span></span>: " : '';
                        $publist .= "<span itemprop=\"name\">" . $pubDetails['publisher'] . "</span></span>, ";
                    } else {
                        $publist .= $pubDetails['city'] != '' ? $pubDetails['city'] . ", " : '';
                    }
                    $publist .= $pubDetails['year'] != '' ? "<span itemprop=\"datePublished\">" . $pubDetails['year'] . "</span>" : '';
                    if (!empty($pubDetails['series'])) {
                        $publist .= $pubDetails['series'] != '' ? "<br />(" . $pubDetails['series'] : '';
                        $publist .= $pubDetails['seriesNumber'] != '' ? ", " . _x('Bd.', 'Abkürzung für "Band" bei Publikationen', 'fau-cris') . $pubDetails['seriesNumber'] : '';
                        $publist .= ")";
                    }
                    $publist .= $pubDetails['pagesTotal'] != '' ? "<br /><span itemprop=\"numberOfPages\">" . $pubDetails['pagesTotal'] . "</span> " . __('Seiten', 'fau-cris') : '';
                    $publist .= $pubDetails['ISBN'] != '' ? "<br /><span itemprop=\"isbn\">ISBN: " . $pubDetails['ISBN'] . "</span>" : '';
                    $publist .= $pubDetails['DOI'] != '' ? "<br />DOI: <a href='" . FAU_CRIS::doi . $pubDetails['DOI'] . "' target='blank' itemprop=\"sameAs\">" . $pubDetails['DOI'] . "</a>" : '';
                    $publist .= ($pubDetails['DOI'] == '' && $pubDetails['OA'] == 'Ja' && $pubDetails['OAlink'] != '') ? "<br />Open Access: <a href='" . $pubDetails['OAlink'] . "' target='blank' itemprop=\"sameAs\">" . $pubDetails['OAlink'] . "</a>" : '';
                    $publist .= $pubDetails['URI'] != '' ? "<br />URL: <a href='" . $pubDetails['URI'] . "' target='blank' itemprop=\"url\">" . $pubDetails['URI'] . "</a>" : '';
                    break;

                case "other":
                case "article in edited volumes":
                    if (($pubDetails['pubType'] == 'Other' && $pubDetails['booktitle'] != '') || $pubDetails['pubType'] == 'Article in Edited Volumes') {
                        $publist .= "<li itemscope itemtype=\"http://schema.org/ScholarlyArticle\">";
                        $publist .= $pubDetails['authors'] . ':';
                        $publist .= "<br />" . $pubDetails['title'];
                        if ($pubDetails['booktitle'] != '') {
                            $publist .= "<br /><span itemscope itemtype=\"http://schema.org/Book\">In: ";
                            $publist .= $pubDetails['editiors'] != '' ? "<span itemprop=\"author\">" . $pubDetails['editiors'] . " (" . __('Hrsg.', 'fau-cris') . "): </span>" : '';
                            $publist .= "<span itemprop=\"name\"><strong>" . $pubDetails['booktitle'] . "</strong></span>";
                            $publist .= ($pubDetails['city'] != '' || $pubDetails['publisher'] != '') ? ", <span itemprop=\"publisher\" itemscope itemtype=\"http://schema.org/Organization\">" : '';
                            $publist .= $pubDetails['city'] != '' ? "<span class=\"city\" itemprop=\"address\" itemscope itemtype=\"http://schema.org/PostalAddress\">"
                                    . "<span itemprop=\"addressLocality\">" . $pubDetails['city'] . "</span></span>: " : '';
                            $publist .= $pubDetails['publisher'] != '' ? "<span itemprop=\"name\">" . $pubDetails['publisher'] . "</span>" : '';
                            $publist .= ($pubDetails['city'] != '' || $pubDetails['publisher'] != '') ? "</span>" : '';
                            $publist .= $pubDetails['year'] != '' ? ", <span itemprop=\"datePublished\">" . $pubDetails['year'] . "</span>" : '';
                            if ($pubDetails['pagesRange'] != '') {
                                $publist .= ", " . _x('S.', 'Abkürzung für "Seite" bei Publikationen', 'fau-cris') . " <span itemprop=\"pagination\">" . $pubDetails['pagesRange'] . "</span>";
                            } elseif ($pubDetails['articleNumber'] != '') {
                                $publist .= ", " . _x('Art.Nr.', 'Abkürzung für "Artikelnummer" bei Publikationen', 'fau-cris') . ": <span itemprop=\"pagination\">" . $pubDetails['articleNumber'] . "</span>";
                            }
                            $publist .= $pubDetails['lexiconColumn'] != '' ? ", " . _x('Sp.', 'Abkürzung für "Spalte" bei Lexikonartikeln', 'fau-cris') . " <span itemprop=\"pagination\">" . $pubDetails['lexiconColumn'] . "</span>" : '';
                            if (!empty($pubDetails['series'])) {
                                $publist .= $pubDetails['series'] != '' ? " (" . $pubDetails['series'] : '';
                                $publist .= $pubDetails['seriesNumber'] != '' ? ", " . _x('Bd.', 'Abkürzung für "Band" bei Publikationen', 'fau-cris') . $pubDetails['seriesNumber'] : '';
                                $publist .= ")";
                            }
                            $publist .= $pubDetails['ISBN'] != '' ? "<br /><span itemprop=\"isbn\">ISBN: " . $pubDetails['ISBN'] . "</span>" : '';
                            $publist .= "</span>";
                        }
                        $publist .= $pubDetails['DOI'] != '' ? "<br />DOI: <a href='" . FAU_CRIS::doi . $pubDetails['DOI'] . "' target='blank' itemprop=\"sameAs\">" . $pubDetails['DOI'] . "</a></span>" : '';
                         $publist .= ($pubDetails['DOI'] == '' && $pubDetails['OA'] == 'Ja' && $pubDetails['OAlink'] != '') ? "<br />Open Access: <a href='" . $pubDetails['OAlink'] . "' target='blank' itemprop=\"sameAs\">" . $pubDetails['OAlink'] . "</a>" : '';
                        $publist .= $pubDetails['URI'] != '' ? "<br />URL: <a href='" . $pubDetails['URI'] . "' target='blank' itemprop=\"url\">" . $pubDetails['URI'] . "</a>" : '';
                        break;
                    }
                case "journal article":
                    $publist .= "<li itemscope itemtype=\"http://schema.org/ScholarlyArticle\">";
                    $publist .= $pubDetails['authors'] . ":";
                    $publist .= "<br />" . $pubDetails['title'];
                    $publist .= (($pubDetails['journaltitle'] != '') || ($pubDetails['volume'] != '') || ($pubDetails['year'] != '') || ($pubDetails['pagesRange'] != '')) ? "<br />" : '';
                    $publist .= $pubDetails['journaltitle'] != '' ? "In: <span itemscope itemtype=\"http://schema.org/Periodical\" itemid=\"#periodical_" . $pubDetails['id'] . "\"><span itemprop=\"name\"><strong>" . $pubDetails['journaltitle'] . "</strong></span></span>" : '';
                    $publist .= $pubDetails['seriesNumber'] != '' ? " <span itemprop=\"isPartOf\" itemscope itemtype=\"http://schema.org/PublicationVolume\"><link itemprop=\"isPartOf\" href=\"#periodical_" . $pubDetails['id'] . "\" /><span itemprop=\"volumeNumber\">" . $pubDetails['seriesNumber'] . "</span></span> " : '';
                    $publist .= $pubDetails['year'] != '' ? " (<span itemprop=\"datePublished\">" . $pubDetails['year'] . "</span>)" : '';
                    if ($pubDetails['pagesRange'] != '') {
                        $publist .= ", " . _x('S.', 'Abkürzung für "Seite" bei Publikationen', 'fau-cris') . " <span itemprop=\"pagination\">" . $pubDetails['pagesRange'] . "</span>";
                    } elseif ($pubDetails['articleNumber'] != '') {
                        $publist .= ", " . _x('Art.Nr.', 'Abkürzung für "Artikelnummer" bei Publikationen', 'fau-cris') . ": <span itemprop=\"pagination\">" . $pubDetails['articleNumber'] . "</span>";
                    }
                    $publist .= $pubDetails['lexiconColumn'] != '' ? ", " . _x('Sp.', 'Abkürzung für "Spalte" bei Lexikonartikeln', 'fau-cris') . " <span itemprop=\"pagination\">" . $pubDetails['lexiconColumn'] . "</span>" : '';
                    $publist .= $pubDetails['ISSN'] != '' ? "<br><span itemscope itemtype=\"http://schema.org/Periodical\" itemid=\"#periodical_" . $pubDetails['id'] . "\"><span itemprop=\"issn\">ISSN: " . $pubDetails['ISSN'] . "</span></span></span>" : "</span>";
                    $publist .= $pubDetails['DOI'] != '' ? "<br />DOI: <a href='" . FAU_CRIS::doi . $pubDetails['DOI'] . "' target='blank' itemprop=\"sameAs\">" . $pubDetails['DOI'] . "</a>" : '';
                    $publist .= ($pubDetails['DOI'] == '' && $pubDetails['OA'] == 'Ja' && $pubDetails['OAlink'] != '') ? "<br />Open Access: <a href='" . $pubDetails['OAlink'] . "' target='blank' itemprop=\"sameAs\">" . $pubDetails['OAlink'] . "</a>" : '';
                    $publist .= $pubDetails['URI'] != '' ? "<br />URL: <a href='" . $pubDetails['URI'] . "' target='blank' itemprop=\"url\">" . $pubDetails['URI'] . "</a>" : '';
                    break;

                case "conference contribution": // OK
                    $publist .= "<li itemscope itemtype=\"http://schema.org/ScholarlyArticle\">";
                    $publist .= $pubDetails['authors'] . ':';
                    $publist .= "<br />" . $pubDetails['title'];
                    $publist .= $publication['publication type'] == 'Unpublished' ? ' (' . Tools::getName('publications', $publication['publication type'], $this->page_lang, $pubDetails['pubType']) . (!empty($pubDetails['pubStatus']) ? ', ' . strtolower($pubDetails['pubStatus']) : '') . ')' : '';
                    if ($pubDetails['eventtitle'] != '') {
                        $publist .= "<br /><span itemscope itemtype=\"http://schema.org/Event\" style=\"font-style:italic;\">";
                        $publist .= "<span itemprop=\"name\">" . $pubDetails['eventtitle'] . "</span>";
                        $publist .= ($pubDetails['eventlocation'] != '' || $pubDetails['eventstart'] != '' || $pubDetails['eventend'] != '') ? " (" : '';
                        $publist .= $pubDetails['eventlocation'] != '' ? "<span itemprop =\"location\" itemscope itemtype=\"http://schema.org/PostalAddress\">"
                                . "<span itemprop=\"name\">" . $pubDetails['eventlocation'] . "</span></span>" : '';
                        $publist .= $pubDetails['eventstart'] != '' ? ", <span itemprop=\"startDate\" content=\"" . $pubDetails['eventstart_raw'] . "\">" . $pubDetails['eventstart'] . "</span>" : "<span itemprop=\"startDate\" content=\"" . $pubDetails['eventstart_raw'] . "\"></span>";
                        $publist .= $pubDetails['eventend'] != '' ? " - <span itemprop=\"endDate\" content=\"" . $pubDetails['eventend_raw'] . "\">" . $pubDetails['eventend'] . "</span>" : '';
                        $publist .= ($pubDetails['eventlocation'] != '' || $pubDetails['eventstart'] != '' || $pubDetails['eventend'] != '') ? ")" : '';
                        $publist .= "</span>";
                    }
                    if ($pubDetails['booktitle'] != '') {
                        $publist .= "<br /><span itemscope itemtype=\"http://schema.org/Book\">In: ";
                        $publist .= $pubDetails['editiors'] != '' ? "<span itemprop=\"author\">" . $pubDetails['editiors'] . " (" . __('Hrsg.', 'fau-cris') . "): </span>" : '';
                        $publist .= "<span itemprop=\"name\" style=\"font-weight:bold;\">" . $pubDetails['booktitle'] . "</span>";
                        $publist .= ($pubDetails['city'] != '' || $pubDetails['publisher'] != '') ? ", <span itemprop=\"publisher\" itemscope itemtype=\"http://schema.org/Organization\">" : '';
                        $publist .= $pubDetails['city'] != '' ? "<span class=\"city\" itemprop=\"address\" itemscope itemtype=\"http://schema.org/PostalAddress\">"
                                . "<span itemprop=\"addressLocality\">" . $pubDetails['city'] . "</span></span>: " : '';
                        $publist .= $pubDetails['publisher'] != '' ? "<span itemprop=\"name\">" . $pubDetails['publisher'] . "</span>" : '';
                        $publist .= ($pubDetails['city'] != '' || $pubDetails['publisher'] != '') ? "</span>" : '';
                        $publist .= $pubDetails['year'] != '' ? ", <span itemprop=\"datePublished\">" . $pubDetails['year'] . "</span>" : '';
                        $publist .= "</span>";
                    }
                    $publist .= $pubDetails['DOI'] != '' ? "<br />DOI: <a href='" . FAU_CRIS::doi . $pubDetails['DOI'] . "' target='blank' itemprop=\"sameAs\">" . $pubDetails['DOI'] . "</a>" : '';
                    $publist .= ($pubDetails['DOI'] == '' && $pubDetails['OA'] == 'Ja' && $pubDetails['OAlink'] != '') ? "<br />Open Access: <a href='" . $pubDetails['OAlink'] . "' target='blank' itemprop=\"sameAs\">" . $pubDetails['OAlink'] . "</a>" : '';
                    $publist .= $pubDetails['URI'] != '' ? "<br />URL: <a href='" . $pubDetails['URI'] . "' target='blank' itemprop=\"url\">" . $pubDetails['URI'] . "</a>" : '';
                    break;

                case "editorial":
                case "edited volumes":
                    $publist .= "<li itemscope itemtype=\"http://schema.org/Book\">";
                    $publist .= $pubDetails['authors'] . ' (' . __('Hrsg.', 'fau-cris') . '):';
                    $publist .= "<br />" . $pubDetails['title'];
                    $publist .= $pubDetails['volume'] != '' ? "<br /><span itemprop=\"volumeNumber\">" . $pubDetails['volume'] . "</span>. " : '';
                    if (!empty($pubDetails['publisher'])) {
                        $publist .= "<br /><span itemprop=\"publisher\" itemscope itemtype=\"http://schema.org/Organization\">";
                    }
                    if (!empty( $pubDetails['city'])) {
                        if (empty($pubDetails['publisher'])) {
                            $publist .= "<br />";
                        }
                        $publist .= "<span class=\"city\" itemprop=\"address\" itemscope itemtype=\"http://schema.org/PostalAddress\">" . "<span itemprop=\"addressLocality\">" . $pubDetails['city'] . "</span></span>: ";
                    }
                    if($pubDetails['year'] != '') {
                        if (empty($pubDetails['publisher']) && empty($pubDetails['city'])) {
                            $publist .= "<br />";
                        }
                        $publist .= "<span itemprop=\"datePublished\">" . $pubDetails['year'] . "</span>";
                    }
                    if (!empty($pubDetails['series'])) {
                        $publist .= $pubDetails['series'] != '' ? "<br />(" . $pubDetails['series'] : '';
                        $publist .= $pubDetails['seriesNumber'] != '' ? ", " . _x('Bd.', 'Abkürzung für "Band" bei Publikationen', 'fau-cris') . " " . $pubDetails['seriesNumber'] : '';
                        $publist .= ")";
                    }
                    $publist .= $pubDetails['pagesTotal'] != '' ? "<br /><span itemprop=\"numberOfPages\">" . $pubDetails['pagesTotal'] . "</span> " . __('Seiten', 'fau-cris') : '';
                    $publist .= $pubDetails['ISBN'] != '' ? "<br /><span itemprop=\"isbn\">ISBN: " . $pubDetails['ISBN'] . "</span>" : '';
                    $publist .= $pubDetails['DOI'] != '' ? "<br />DOI: <a href='" . FAU_CRIS::doi . $pubDetails['DOI'] . "' target='blank' itemprop=\"sameAs\">" . $pubDetails['DOI'] . "</a>" : '';
                    $publist .= ($pubDetails['DOI'] == '' && $pubDetails['OA'] == 'Ja' && $pubDetails['OAlink'] != '') ? "<br />Open Access: <a href='" . $pubDetails['OAlink'] . "' target='blank' itemprop=\"sameAs\">" . $pubDetails['OAlink'] . "</a>" : '';
                    $publist .= $pubDetails['URI'] != '' ? "<br />URL: <a href='" . $pubDetails['URI'] . "' target='blank' itemprop=\"url\">" . $pubDetails['URI'] . "</a>" : '';
                    break;

                case "thesis":
                    $publist .= "<li itemscope itemtype=\"http://schema.org/Thesis\">";
                    $publist .= $pubDetails['authors'] . ':';
                    $publist .= "<br />" . $pubDetails['title'];
                    $publist .= " (" . ($pubDetails['thesisSubtype'] != '' ? Tools::getName('publications', 'Thesis', $this->page_lang, $pubDetails['thesisSubtype']) : __('Abschlussarbeit', 'fau-cris')) . ", <span itemprop=\"datePublished\">" . $pubDetails['year'] . "</span>)";
                    $publist .= $pubDetails['DOI'] != '' ? "<br />DOI: <a href='" . FAU_CRIS::doi . $pubDetails['DOI'] . "' target='blank' itemprop=\"sameAs\">" . $pubDetails['DOI'] . "</a>" : '';
                    $publist .= $pubDetails['URI'] != '' ? "<br />URL: <a href='" . $pubDetails['URI'] . "' target='blank' itemprop=\"url\">" . $pubDetails['URI'] . "</a>" : '';
                    break;

                case "translation":
                    $publist .= "<li itemscope itemtype=\"http://schema.org/Book\">";
                    $publist .= $pubDetails['authors'] . ':';
                    $publist .= $pubDetails['title'];
                    $publist .= (($pubDetails['city'] != '') || ($pubDetails['publisher'] != '') || ($pubDetails['year'] != '')) ? "<br />" : '';
                    $publist .= $pubDetails['volume'] != '' ? $pubDetails['volume'] . ". " : '';
                    if (!empty($pubDetails['publisher'])) {
                        $publist .= "<span itemprop=\"publisher\" itemscope itemtype=\"http://schema.org/Organization\">";
                        $publist .= $pubDetails['city'] != '' ? "<span class=\"city\" itemprop=\"address\" itemscope itemtype=\"http://schema.org/PostalAddress\">"
                                . "<span itemprop=\"addressLocality\">" . $pubDetails['city'] . "</span></span>: " : '';
                        $publist .= "<span itemprop=\"name\">" . $pubDetails['publisher'] . "</span></span>, ";
                    } else {
                        $publist .= $pubDetails['city'] != '' ? $pubDetails['city'] . ", " : '';
                    }
                    $publist .= $pubDetails['year'] != '' ? "<span itemprop=\"datePublished\">" . $pubDetails['year'] . "</span>" : '';
                    $publist .= $pubDetails['series'] != '' ? "<br />" . $pubDetails['series'] : '';
                    $publist .= $pubDetails['seriesNumber'] != '' ? ", " . _x('Bd.', 'Abkürzung für "Band" bei Publikationen', 'fau-cris') . $pubDetails['seriesNumber'] : '';
                    $publist .= $pubDetails['pagesTotal'] != '' ? "<br /><span itemprop=\"numberOfPages\">" . $pubDetails['pagesTotal'] . "</span> " . __('Seiten', 'fau-cris') : '';
                    $publist .= $pubDetails['ISBN'] != '' ? "<br /><span itemprop=\"isbn\">ISBN: " . $pubDetails['ISBN'] . "</span>" : '';
                    $publist .= $pubDetails['ISSN'] != '' ? "<br /><span itemprop=\"issn\">ISSN: " . $pubDetails['ISSN'] . "</span>" : '';
                    $publist .= $pubDetails['DOI'] != '' ? "<br />DOI: <a href='" . FAU_CRIS::doi . $pubDetails['DOI'] . "' target='blank' itemprop=\"sameAs\">" . $pubDetails['DOI'] . "</a>" : '';
                    $publist .= ($pubDetails['DOI'] == '' && $pubDetails['OA'] == 'Ja' && $pubDetails['OAlink'] != '') ? "<br />Open Access: <a href='" . $pubDetails['OAlink'] . "' target='blank' itemprop=\"sameAs\">" . $pubDetails['OAlink'] . "</a>" : '';
                    $publist .= $pubDetails['URI'] != '' ? "<br />URL: <a href='" . $pubDetails['URI'] . "' target='blank' itemprop=\"url\">" . $pubDetails['URI'] . "</a>" : '';
                    $publist .= $pubDetails['origTitle'] != '' ? "<br />Originaltitel: " . $pubDetails['origTitle'] : '';
                    $publist .= $pubDetails['language'] != '' ? "<br />Sprache: <span itemprop=\"inLanguage\">" . $pubDetails['language'] . "</span>" : '';
                    break;
            }
            if ($this->bibtex == 1) {
                $publist .= "<br />BibTeX: " . $pubDetails['bibtex_link'];
            }
            if ($showsubtype == 1 && $pubDetails['otherSubtype'] != '') {
                $publist .= "<br />(" . $pubDetails['otherSubtype'] . ")";
            }
            $publist .= "</li>";
        }
        $publist .= "</ul>";

        return $publist;
    }

    private function make_custom_list($publications, $custom_text, $nameorder = '', $lang = 'de') {
        $publist = '';
        $list = (count($publications) > 1) ? true : false;
        if ($list) {
            $publist .= "<ul class=\"cris-publications\">";
        } else {
            $publist .= "<div class=\"cris-publications\">";
        }
        foreach ($publications as $publObject) {
            $publication = (array) $publObject;
            foreach ($publication['attributes'] as $attribut => $v) {
                $publication[$attribut] = $v;
            }
            unset($publication['attributes']);
            $id = $publication['ID'];
            // authors
            if (strtolower($publication['complete author relations']) == 'yes') {
                $authors = explode("|", $publication['exportauthors']);
                $authorIDs = explode(",", $publication['relpersid']);
                $authorsArray = array();
                foreach ($authorIDs as $i => $key) {
                    $nameparts = explode(":", $authors[$i]);
                    $authorsArray[] = array(
                        'id' => $key,
                        'lastname' => $nameparts[0],
                        'firstname' => $nameparts[1]);
                }
                $authorList = array();
                foreach ($authorsArray as $author) {
                    $authorList[] = Tools::get_person_link($author['id'], $author['firstname'], $author['lastname'], $this->univisLink, $this->cms, $this->pathPersonenseiteUnivis, $this->univis, 1, 1, $nameorder);
                }
                $authors_html = implode(", ", $authorList);
            } else {
                if ($publication['publication type'] == "Editorial") {
                    $authors_html = $publication['srceditors'];
                } else {
                    $authors_html = $publication['srcauthors'];
                }
            }
            // title (bei Rezensionen mit Original-Autor davor)
            $title = '';
            if (($publication['publication type'] == 'Translation' || $publication['subtype'] == 'Rezension') && $publication['originalauthors'] != '') {
                $title = strip_tags($publication['originalauthors']) . ': ';
            }
            $title .= (array_key_exists('cftitle', $publication) ? strip_tags($publication['cftitle']) : __('O.T.', 'fau-cris'));
            global $post;
            $title_html = "<span class=\"title\" itemprop=\"name\"><strong>"
                    . "<a href=\"" . Tools::get_item_url("publication", $title, $id, $post->ID) . "\" title=\"Detailansicht in neuem Fenster &ouml;ffnen\">"
                    . $title
                    . "</a></strong></span>";
            //pubType
            $pubTypeRaw = (array_key_exists('futurepublicationtype', $publication) && $publication['futurepublicationtype'] != '') ? strip_tags($publication['futurepublicationtype']) : (array_key_exists('publication type', $publication) ? strip_tags($publication['publication type']) : __('O.A.', 'fau-cris'));

            $pubType = Tools::getName('publications', $pubTypeRaw, $lang);
            // make array
            setlocale(LC_TIME, get_locale());
            $pubDetails = array(
                '#id#' => $id,
                '#author#' => $authors_html,
                '#title#' => $title,
                '#url#' => Tools::get_item_url("publication", $title, $id, $post->ID),
                '#city#' => (array_key_exists('cfcitytown', $publication) ? strip_tags($publication['cfcitytown']) : __('O.O.', 'fau-cris')),
                '#publisher#' => (array_key_exists('publisher', $publication) ? strip_tags($publication['publisher']) : __('O.A.', 'fau-cris')),
                '#year#' => (array_key_exists('publyear', $publication) ? strip_tags($publication['publyear']) : __('O.J.', 'fau-cris')),
                '#pubType#' => $pubType,
                '#pubStatus#' => (array_key_exists('publstatus', $publication) ? strip_tags($publication['publstatus']) : ''),
                '#pagesTotal#' => (array_key_exists('cftotalpages', $publication) ? strip_tags($publication['cftotalpages']) : ''),
                '#pagesRange#' => (array_key_exists('pagesrange', $publication) ? strip_tags($publication['pagesrange']) : ''),
                '#lexiconColumn#' => (array_key_exists('lexiconcolumn', $publication) ? strip_tags($publication['lexiconcolumn']) : ''),
                '#volume#' => (array_key_exists('cfvol', $publication) ? strip_tags($publication['cfvol']) : __('O.A.', 'fau-cris')),
                '#series#' => (array_key_exists('cfseries', $publication) ? strip_tags($publication['cfseries']) : __('O.A.', 'fau-cris')),
                '#seriesNumber#' => !empty($publication['book volume']) ? strip_tags($publication['book volume']) : '',
                '#ISBN#' => (array_key_exists('cfisbn', $publication) ? strip_tags($publication['cfisbn']) : __('O.A.', 'fau-cris')),
                '#ISSN#' => (array_key_exists('cfissn', $publication) ? strip_tags($publication['cfissn']) : __('O.A.', 'fau-cris')),
                '#DOI#' => (array_key_exists('doi', $publication) ? strip_tags($publication['doi']) : __('O.A.', 'fau-cris')),
                '#URI#' => (array_key_exists('cfuri', $publication) ? strip_tags($publication['cfuri']) : __('O.A.', 'fau-cris')),
                '#editors#' => (array_key_exists('editor', $publication) ? strip_tags($publication['editor']) : __('O.A.', 'fau-cris')),
                '#bookTitle#' => (array_key_exists('edited volumes', $publication) ? strip_tags($publication['edited volumes']) : __('O.A.', 'fau-cris')), // Titel des Sammelbands
                '#journalTitle#' => (array_key_exists('journalname', $publication) ? strip_tags($publication['journalname']) : __('O.A.', 'fau-cris')),
                '#eventTitle#' => (array_key_exists('event title', $publication) ? strip_tags($publication['event title']) : ''),
                '#eventLocation#' => (array_key_exists('event location', $publication) ? strip_tags($publication['event location']) : ''),
                '#eventstart_raw#' => !empty($publication['event start date']) ? $publication['event start date'] : (!empty($publication['publyear']) ? $publication['publyear'] : '-----'),
                '#eventend_raw#' => (!empty($publication['event end date']) ? $publication['event end date'] : ''),
                '#eventStart#' => !empty($publication['event start date']) ? date_i18n( get_option( 'date_format' ), strtotime(strip_tags($publication['event start date']))) : '',
                '#eventEnd#' => (!empty($publication['event end date']) ? date_i18n( get_option( 'date_format' ), strtotime(strip_tags($publication['event end date']))) : ''),
                '#originalTitle#' => (array_key_exists('originaltitel', $publication) ? strip_tags($publication['originaltitel']) : __('O.A.', 'fau-cris')),
                '#language#' => (array_key_exists('language', $publication) ? strip_tags($publication['language']) : __('O.A.', 'fau-cris')),
                '#bibtexLink#' => '<a href="' . sprintf($this->bibtexlink, $id) . '">Download</a>',
                '#subtype#' => (array_key_exists('subtype', $publication) ? $publication['subtype'] : ''),
                '#articleNumber#' => (array_key_exists('article number', $publication) ? $publication['article number'] : ''),
                '#projectTitle#' => '',
                '#projectLink#' => '',
                '#oaIcon#' => ($publication['openaccess'] == "Ja") ? "<span aria-hidden class=\"oa-icon\" title=\"Open-Access-Publikation\"></span>" : '',
            );
            if (strpos($custom_text, '#projectTitle#' ) !== false) {
                $pubDetails['#projectTitle#'] = $this->get_pub_projects($id, 'title');
            }
            if (strpos($custom_text, '#projectLink#' ) !== false) {
                $pubDetails['#projectLink#'] = $this->get_pub_projects($id, 'link');
            }

            if ($list) {
                $publist .= "<li>"; }
            $publist .= strtr($custom_text, $pubDetails);
            if ($list) {
                $publist .= "</li>"; }
        }
        if ($list) {
            $publist .= "</ul>";
        } else {
            $publist .= "</div>";
        }       
        return $publist;
    }

    private function get_pub_projects($pub = NULL, $item = 'title') {
        require_once('class_Projekte.php');
        $liste = new Projekte();
        $projects = $liste->pubProj($pub);
        return $projects[$item];
    }
}

class CRIS_publications extends CRIS_webservice {
    /*
     * publication requests, supports multiple organisation ids given as array.
     */

    public function by_orga_id($orgaID = null, &$filter = null) {
        if ($orgaID === null || $orgaID === "0" || $orgaID === "")
            throw new Exception('Please supply valid organisation ID');

        if (!is_array($orgaID))
            $orgaID = array($orgaID);

        $requests = array();
        foreach ($orgaID as $_o) {
            $requests = array_merge($requests, array(
                sprintf("getautorelated/Organisation/%d/ORGA_2_PUBL_1", $_o),
                sprintf("getrelated/Organisation/%d/Publ_has_ORGA", $_o),
            ));
        }
        return $this->retrieve($requests, $filter);
    }

    public function by_pers_id($persID = null, &$filter = null, $notable = 0) {
        if ($persID === null || $persID === "0")
            throw new Exception('Please supply valid person ID');

        if (!is_array($persID))
            $persID = array($persID);

        $requests = array();
        if ($notable == 1) {
            foreach ($persID as $_p) {
                $requests[] = sprintf('getrelated/Person/%s/PUBL_has_PERS', $_p);
            }
        } else {
            foreach ($persID as $_p) {
                $requests[] = sprintf('getautorelated/Person/%s/PERS_2_PUBL_1', $_p);
            }
        }
        return $this->retrieve($requests, $filter);
    }

    public function by_id($publID = null) {
        if ($publID === null || $publID === "0")
            throw new Exception('Please supply valid publication ID');

        if (!is_array($publID))
            $publID = array($publID);

        $requests = array();
        foreach ($publID as $_p) {
            $requests[] = sprintf('get/Publication/%d', $_p);
        }
        return $this->retrieve($requests);
    }

    public function by_project($projID = null) {
        if ($projID === null || $projID === "0")
            throw new Exception('Please supply valid publication ID');

        if (!is_array($projID))
            $projID = array($projID);

        $requests = array();
        foreach ($projID as $_p) {
            $requests[] = sprintf('getrelated/Project/%d/proj_has_publ', $_p);
        }
        return $this->retrieve($requests);
    }

    public function by_field($fieldID = null, &$filter = null, $fsp = false, $entity = 'field') {
        if ($fieldID === null || $fieldID === "0")
            throw new Exception('Please supply valid research field ID');

        if (!is_array($fieldID))
            $fieldID = array($fieldID);

        $requests = array();
        switch ($entity) {
	        case 'field_proj':
		        $relation = $fsp ? 'fsp_proj_publ' : 'fobe_proj_publ';
		        break;
	        case 'field':
	        default:
	            $relation = $fsp ? 'FOBE_FSP_has_PUBL' : 'fobe_has_top_publ';
        }

	    foreach ($fieldID as $_p) {
            $requests[] = sprintf('getrelated/Forschungsbereich/%d/', $_p) . $relation;
        }
        return $this->retrieve($requests, $filter);
    }

    public function by_equipment($equiID = null, &$filter = null) {
        if ($equiID === null || $equiID === "0")
            throw new Exception('Please supply valid equipment ID');

        if (!is_array($equiID))
            $equiID = array($equiID);

        $requests = array();
        foreach ($equiID as $_p) {
            $requests[] = sprintf('getrelated/equipment/%d/PUBL_has_EQUI', $_p);
        }
        return $this->retrieve($requests, $filter);
    }

    
    private function retrieve($reqs, &$filter = null) {
        if ($filter !== null && !$filter instanceof CRIS_filter) {
            $filter = new CRIS_filter($filter);
        }
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

        $publs = array();

        foreach ($data as $_d) {
            foreach ($_d as $publ) {
                $p = new CRIS_publication($publ);
                if ($p->ID && ($filter === null || $filter->evaluate($p)))
                    $publs[$p->ID] = $p;
            }
        }
        return $publs;
    }

}

class CRIS_publication extends CRIS_Entity {
    /*
     * object for single publication
     */

    public function __construct($data) {
        parent::__construct($data);
    }

    public function insert_quotation_links() {
        /*
         * Enrich APA/MLA quotation by links to publication details (CRIS
         * website) and DOI (if present, applies only to APA).
         */

        $doilink = preg_quote("https://dx.doi.org/", "/");
        $title = preg_quote(Tools::numeric_xml_encode($this->attributes["cftitle"]), "/");

        $cristmpl = '<a href="' . FAU_CRIS::cris_publicweb . 'publication/%d" target="_blank">%s</a>';

        $apa = $this->attributes["quotationapa"];
        $mla = $this->attributes["quotationmla"];

        $matches = array();
        $splitapa = preg_match("/^(.+)(" . $title . ")(.+)(" . $doilink . ".+)?$/Uu", $apa, $matches);

        if ($splitapa === 1 && isset($matches[2])) {
            $apalink = $matches[1] . \
                    sprintf($cristmpl, $this->ID, $matches[2]) . $matches[3];
            if (isset($matches[4]))
                $apalink .= sprintf('<a href="%s" target="_blank">%s</a>', $matches[4], $matches[4]);
        } else {
            // try to identify DOI at least
            $splitapa = preg_match("/^(.+)(" . $doilink . ".+)?$/Uu", $apa, $matches);
            if ($splitapa === 1 && isset($matches[2])) {
                $apalink = $matches[1] . \
                        sprintf('<a href="%s" target="_blank">%s</a>', $matches[2], $matches[2]);
            } else
                $apalink = $apa;
        }

        $this->attributes["quotationapalink"] = $apalink;

        $matches = array();
        $splitmla = preg_match("/^(.+)(" . $title . ")(.+)$/", $mla, $matches);

        if ($splitmla === 1) {
            $mlalink = $matches[1] . \
                    sprintf($cristmpl, $this->ID, $matches[2]) . $matches[3];
        } else {
            $mlalink = $mla;
        }

        $this->attributes["quotationmlalink"] = $mlalink;
    }
}

# tests possible if called on command-line
if (!debug_backtrace()) {
    $p = new CRIS_Publications();
    // default uses the cache automatically
    // $p->disable_cache();
    $f = new CRIS_Filter(array("publyear__le" => 2016, "publyear__gt" => 2014, "peerreviewed__eq" => "Yes"));
    $publs = $p->by_orga_id("142285", $f);
    $order = "virtualdate";
    $formatter = new CRIS_formatter(NULL, NULL, $order, SORT_DESC);
    $res = $formatter->execute($publs);
    foreach ($res[$order] as $key => $value) {
        echo sprintf("%s: %s\n", $key, $value->attributes[$order]);
    }
}
