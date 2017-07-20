<?php

require_once("class_Tools.php");
require_once("class_Webservice.php");
require_once("class_Filter.php");
require_once("class_Formatter.php");

class Publikationen {

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

        $this->order = $this->options['cris_pub_order'];
        $this->subtypeorder = $this->options['cris_pub_subtypes_order'];
        $this->univisLink = isset($this->options['cris_univis']) ? $this->options['cris_univis'] : 'none';
        $this->bibtex = $this->options['cris_bibtex'];
        if ($this->cms == 'wbk' && $this->univisLink == 'person') {
            $this->univis = Tools::get_univis();
        }

        if ((!$this->orgNr || $this->orgNr == 0) && $id == '') {
            print '<p><strong>' . __('Bitte geben Sie die CRIS-ID der Organisation, Person oder Publikation an.', 'fau-cris') . '</strong></p>';
        }
        if (in_array($einheit, array("person", "orga", "publication", "project"))) {
            $this->id = $id;
            $this->einheit = $einheit;
        } else {
            // keine Einheit angegeben -> OrgNr aus Einstellungen verwenden
            $this->id = $this->orgNr;
            $this->einheit = "orga";
        }

    }

    /*
     * Ausgabe aller Publikationen ohne Gliederung
     */

    public function pubListe($year = '', $start = '', $type = '', $subtype = '', $quotation = '', $items = '', $sortby = 'virtualdate', $fau = '', $peerreviewed = '') {
        $pubArray = $this->fetch_publications($year, $start, $type, $subtype, $fau, $peerreviewed);

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
        if ($items != '')
            $pubList = array_slice($res[$order], 0, $items);
        else
            $pubList = $res[$order];

        $output = '';

        if ($quotation == 'apa' || $quotation == 'mla') {
            $output .= $this->make_quotation_list($pubList, $quotation);
        } else {
            $output .= $this->make_list($pubList, 1);
        }

        return $output;
    }

    /*
     * Ausgabe aller Publikationen nach Jahren gegliedert
     */

    public function pubNachJahr($year = '', $start = '', $type = '', $subtype = '', $quotation = '', $order2 = 'author', $fau = '', $peerreviewed = '') {
        $pubArray = $this->fetch_publications($year, $start, $type, $subtype, $fau, $peerreviewed);
        if (!count($pubArray)) {
            $output = '<p>' . __('Es wurden leider keine Publikationen gefunden.', 'fau-cris') . '</p>';
            return $output;
        }

        // sortiere nach Erscheinungsjahr, innerhalb des Jahres nach Erstautor
        if ($order2 == 'author') {
            $formatter = new CRIS_formatter("publyear", SORT_DESC, "relauthors", SORT_ASC);
        } else {
            $formatter = new CRIS_formatter("publyear", SORT_DESC, "virtualdate", SORT_DESC);
        }
        $pubList = $formatter->execute($pubArray);

        $output = '';
        $showsubtype = ($subtype == '') ? 1 : 0;
        foreach ($pubList as $array_year => $publications) {
            if (empty($year)) {
                $output .= '<h3>' . $array_year . '</h3>';
            }
            if ($quotation == 'apa' || $quotation == 'mla') {
                $output .= $this->make_quotation_list($publications, $quotation);
            } else {
                $output .= $this->make_list($publications, $showsubtype);
            }
        }
        return $output;
    }

    /*
     * Ausgabe aller Publikationen nach Publikationstypen gegliedert
     */

    public function pubNachTyp($year = '', $start = '', $type = '', $subtype = '', $quotation = '', $order2 = 'date', $fau = '', $peerreviewed = '') {
        $pubArray = $this->fetch_publications($year, $start, $type, $subtype, $fau, $peerreviewed);

        if (!count($pubArray)) {
            $output = '<p>' . __('Es wurden leider keine Publikationen gefunden.', 'fau-cris') . '</p>';
            return $output;
        }

        // Publikationstypen sortieren
        $order = $this->order;
        if ($order[0] != '' && array_search($order[0], array_column(CRIS_Dicts::$publications, 'short')) !== false) {
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
        foreach ($pubList as $array_type => $publications) {
            // Zwischenüberschrift (= Publikationstyp), außer wenn nur ein Typ gefiltert wurde
            if (empty($type)) {
                $title = Tools::getTitle('publications', $array_type, get_locale());
                $output .= "<h3>";
                $output .= $title;
                $output .= "</h3>";
            }
            if ($array_type == 'Other') {
            // Weitrere Untergliederung für Publikationstyp "Other"
                $subtypeorder = $this->subtypeorder;
                if ($subtypeorder[0] != '' && array_search($subtypeorder[0], array_column(CRIS_Dicts::$pubOtherSubtypes, 'short'))) {
                    foreach ($subtypeorder as $key => $value) {
                        $subtypeorder[$key] = Tools::getType('pubothersubtypes', $value);
                    }
                } else {
                    $subtypeorder = Tools::getOrder('pubothersubtypes');
                }
                if ($order2 == 'author') {
                    $subformatter = new CRIS_formatter("type other subtype", array_values($subtypeorder), "relauthors", SORT_ASC);
                } else {
                    $subformatter = new CRIS_formatter("type other subtype", array_values($subtypeorder), "virtualdate", SORT_DESC);
                }
                $pubOtherList = $subformatter->execute($publications);

                foreach ($pubOtherList as $array_subtype => $publications_sub) {
                    // Zwischenüberschrift (= Publikationstyp), außer wenn nur ein Typ gefiltert wurde
                    if (empty($subtype)) {
                        $title_sub = Tools::getTitle('pubothersubtypes', $array_subtype, get_locale());
                        $output .= "<h4>";
                        $output .= $title_sub;
                        $output .= "</h4>";
                    }
                    if ($quotation == 'apa' || $quotation == 'mla') {
                    $output .= $this->make_quotation_list($publications_sub, $quotation);
                    } else {
                        $output .= $this->make_list($publications_sub);
                    }
                }
            } else {
                if ($quotation == 'apa' || $quotation == 'mla') {
                    $output .= $this->make_quotation_list($publications, $quotation);
                } else {
                    $output .= $this->make_list($publications);
                }
            }
        }
        return $output;
    }

// Ende pubNachTyp()

    public function singlePub($quotation = '') {
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
            $output = $this->make_list($pubArray);
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

        if ($quotation == 'apa' || $quotation == 'mla') {
            $output = $this->make_quotation_list($pubArray, $quotation);
        } else {
            $output = $this->make_list($pubArray);
        }

        return $output;
    }

    public function fieldPub($field, $quotation = '', $seed=false) {
        $ws = new CRIS_publications();
        if($seed)
            $ws->disable_cache();
        try {
            $pubArray = $ws->by_field($field);
        } catch (Exception $ex) {
            return;
        }

        if (!count($pubArray))
            return;

        if ($quotation == 'apa' || $quotation == 'mla') {
            $output = $this->make_quotation_list($pubArray, $quotation);
        } else {
            $output = $this->make_list($pubArray);
        }

        return $output;
    }

    /* =========================================================================
     * Private Functions
      ======================================================================== */

    /*
     * Holt Daten vom Webservice je nach definierter Einheit.
     */

    private function fetch_publications($year = '', $start = '', $type = '', $subtype = '', $fau = '', $peerreviewed = '') {
        $filter = Tools::publication_filter($year, $start, $type, $subtype, $fau, $peerreviewed);
        $ws = new CRIS_publications();

        try {
            if ($this->einheit === "orga") {
                $pubArray = $ws->by_orga_id($this->id, $filter);
            }
            if ($this->einheit === "person") {
                $pubArray = $ws->by_pers_id($this->id, $filter);
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
            if (isset($this->options['cris_doi'])
                    && $this->options['cris_doi'] == 1
                    && !empty($publication->attributes['doi'])) {
                $publist .= "<br />DOI: <a href='" . $publication->attributes['doi'] . "' target='blank' itemprop=\"url\">" . $publication->attributes['doi'] . "</a>";
            }
            if (isset($this->options['cris_url'])
                    && $this->options['cris_url'] == 1
                    && !empty($publication->attributes['cfuri'])) {
                $publist .= "<br />URL: <a href='" . $publication->attributes['cfuri'] . "' target='blank' itemprop=\"url\">" . $publication->attributes['cfuri'] . "</a>";
            }
            if (isset($this->options['cris_bibtex']) && $this->options['cris_bibtex'] == 1) {
                $publist .= "<br />BibTeX: " . $publication->attributes['bibtex_link'];
            }
            $publist .= "</li>";
        }

        $publist .= "</ul>";

        return $publist;
    }

    /*
     * Ausgabe der Publikationsdetails, unterschiedlich nach Publikationstyp
     */

    private function make_list($publications, $showsubtype = 0) {

        $publist = "<ul class=\"cris-publications\">";

        foreach ($publications as $publicationObject) {

            $publication = $publicationObject->attributes;
            // id
            $id = $publicationObject->ID;
            // authors
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
                $authorList[] = Tools::get_person_link($author['id'], $author['firstname'], $author['lastname'], $this->univisLink, $this->cms, $this->pathPersonenseiteUnivis, $this->univis, 1, 1);
            }
            $authors_html = implode(", ", $authorList);
            // title (bei Rezensionen mit Original-Autor davor)
            $title = '';
            if (($publication['publication type'] == 'Translation' || $publication['type other subtype'] == 'Rezension') && $publication['originalauthors'] != '') {
                $title = strip_tags($publication['originalauthors']) . ': ';
                }
            $title .= (array_key_exists('cftitle', $publication) ? strip_tags($publication['cftitle']) : __('O.T.', 'fau-cris'));
            $title_html = "<span class=\"title\" itemprop=\"name\" style=\"font-weight: bold;\">"
                    . "<a href=\"https://cris.fau.de/converis/publicweb/Publication/" . $id . "\" target=\"blank\" title=\"Detailansicht in neuem Fenster &ouml;ffnen\">"
                    . $title
                    . "</a></span>";
            // make array
            setlocale(LC_TIME, get_locale());
            $pubDetails = array(
                'id' => $id,
                'authors' => $authors_html,
                'title' => $title_html,
                'city' => (array_key_exists('cfcitytown', $publication) ? strip_tags($publication['cfcitytown']) : __('O.O.', 'fau-cris')),
                'publisher' => (array_key_exists('publisher', $publication) ? strip_tags($publication['publisher']) : __('O.A.', 'fau-cris')),
                'year' => (array_key_exists('publyear', $publication) ? strip_tags($publication['publyear']) : __('O.J.', 'fau-cris')),
                'pubType' => (array_key_exists('futurepublicationtype', $publication) && $publication['futurepublicationtype'] !='') ? strip_tags($publication['futurepublicationtype']) : (array_key_exists('publication type', $publication) ? strip_tags($publication['publication type']) : __('O.A.', 'fau-cris')),
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
                'URI' => (array_key_exists('cfuri', $publication) ? strip_tags($publication['cfuri']) : __('O.A.', 'fau-cris')),
                'editiors' => (array_key_exists('editor', $publication) ? strip_tags($publication['editor']) : __('O.A.', 'fau-cris')),
                'booktitle' => (array_key_exists('edited volumes', $publication) ? strip_tags($publication['edited volumes']) : __('O.A.', 'fau-cris')), // Titel des Sammelbands
                'journaltitle' => (array_key_exists('journalname', $publication) ? strip_tags($publication['journalname']) : __('O.A.', 'fau-cris')),
                'eventtitle' => (array_key_exists('event title', $publication) ? strip_tags($publication['event title']) : ''),
                'eventlocation' => (array_key_exists('event location', $publication) ? strip_tags($publication['event location']) : ''),
                'eventstart_raw' => !empty($publication['event start date']) ? $publication['event start date'] : (!empty($publication['publyear']) ? $publication['publyear'] : '-----'),
                'eventend_raw' => (!empty($publication['event end date']) ? $publication['event end date'] : ''),
                'eventstart' => !empty($publication['event start date']) ? strftime('%x', strtotime(strip_tags($publication['event start date']))) : '',
                'eventend' => (!empty($publication['event end date']) ? strftime('%x', strtotime(strip_tags($publication['event end date']))) : ''),
                'origTitle' => (array_key_exists('originaltitel', $publication) ? strip_tags($publication['originaltitel']) : __('O.A.', 'fau-cris')),
                'language' => (array_key_exists('language', $publication) ? strip_tags($publication['language']) : __('O.A.', 'fau-cris')),
                'bibtex_link' => (array_key_exists('bibtex_link', $publication) ? $publication['bibtex_link'] : __('Nicht verfügbar', 'fau-cris')),
                'otherSubtype' => (array_key_exists('type other subtype', $publication) ? $publication['type other subtype'] : ''),
                'thesisSubtype' =>(array_key_exists('publication thesis subtype', $publication) ? $publication['publication thesis subtype'] : ''),
                'articleNumber' =>(array_key_exists('article number', $publication) ? $publication['article number'] : '')
            );
            
            switch (strtolower($pubDetails['pubType'])) {

                case "book": // OK
                    $publist .= "<li itemscope itemtype=\"http://schema.org/Book\">";
                    $publist .= $pubDetails['authors'] . ':';
                    $publist .= "<br />" . $pubDetails['title'];
                    $publist .= $publication['publication type'] == 'Unpublished' ? ' (' . Tools::getName('publications', $pubDetails['pubType'], get_locale()) . ')' : '';
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
                    $publist .= $pubDetails['DOI'] != '' ? "<br />DOI: <a href='http://dx.doi.org/" . $pubDetails['DOI'] . "' target='blank' itemprop=\"sameAs\">" . $pubDetails['DOI'] . "</a>" : '';
                    $publist .= $pubDetails['URI'] != '' ? "<br />URL: <a href='" . $pubDetails['URI'] . "' target='blank' itemprop=\"url\">" . $pubDetails['URI'] . "</a>" : '';
                    break;

                case "other":
                case "article in edited volumes":
                    if(($pubDetails['pubType'] == 'Other' && $pubDetails['booktitle']!='') || $pubDetails['pubType'] == 'Article in Edited Volumes') {
                        $publist .= "<li itemscope itemtype=\"http://schema.org/ScholarlyArticle\">";
                        $publist .= $pubDetails['authors'] . ':';
                        $publist .= "<br />" . $pubDetails['title'];
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
                        $publist .= $pubDetails['DOI'] != '' ? "<br />DOI: <a href='http://dx.doi.org/" . $pubDetails['DOI'] . "' target='blank' itemprop=\"sameAs\">" . $pubDetails['DOI'] . "</a></span>" : '';
                        $publist .= $pubDetails['URI'] != '' ? "<br />URL: <a href='" . $pubDetails['URI'] . "' target='blank' itemprop=\"url\">" . $pubDetails['URI'] . "</a>" : '';
                        break;
                    }
                case "journal article":
                    $publist .= "<li itemscope itemtype=\"http://schema.org/ScholarlyArticle\">";
                    $publist .= $pubDetails['authors'] . ":";
                    $publist .= "<br />" . $pubDetails['title'];
                    $publist .= (($pubDetails['journaltitle'] != '') || ($pubDetails['volume'] != '') || ($pubDetails['year'] != '') || ($pubDetails['pagesRange'] != '')) ? "<br />" : '';
                    $publist .= $pubDetails['journaltitle'] != '' ? "In: <span itemscope itemtype=\"http://schema.org/Periodical\" itemid=\"#periodical_" . $pubDetails['id'] . "\"><span itemprop=\"name\" style=\"font-weight: bold;\">" . $pubDetails['journaltitle'] . "</span></span>" : '';
                    $publist .= $pubDetails['seriesNumber'] != '' ? " <span itemprop=\"isPartOf\" itemscope itemtype=\"http://schema.org/PublicationVolume\"><link itemprop=\"isPartOf\" href=\"#periodical_" . $pubDetails['id'] . "\" /><span itemprop=\"volumeNumber\">" . $pubDetails['seriesNumber'] . "</span></span> " : '';
                    $publist .= $pubDetails['year'] != '' ? " (<span itemprop=\"datePublished\">" . $pubDetails['year'] . "</span>)" : '';
                    if ($pubDetails['pagesRange'] != '') {
                        $publist .= ", " . _x('S.', 'Abkürzung für "Seite" bei Publikationen', 'fau-cris') . " <span itemprop=\"pagination\">" . $pubDetails['pagesRange'] . "</span>";
                    } elseif ($pubDetails['articleNumber'] != '') {
                        $publist .= ", " . _x('Art.Nr.', 'Abkürzung für "Artikelnummer" bei Publikationen', 'fau-cris') . ": <span itemprop=\"pagination\">" . $pubDetails['articleNumber'] . "</span>";
                    }
                    $publist .= $pubDetails['lexiconColumn'] != '' ? ", " . _x('Sp.', 'Abkürzung für "Spalte" bei Lexikonartikeln', 'fau-cris') . " <span itemprop=\"pagination\">" . $pubDetails['lexiconColumn'] . "</span>" : '';
                    $publist .= $pubDetails['ISSN'] != '' ? "<br><span itemscope itemtype=\"http://schema.org/Periodical\" itemid=\"#periodical_" . $pubDetails['id'] . "\"><span itemprop=\"issn\">ISSN: " . $pubDetails['ISSN'] . "</span></span></span>" : "</span>";
                    $publist .= $pubDetails['DOI'] != '' ? "<br />DOI: <a href='http://dx.doi.org/" . $pubDetails['DOI'] . "' target='blank' itemprop=\"sameAs\">" . $pubDetails['DOI'] . "</a>" : '';
                    $publist .= $pubDetails['URI'] != '' ? "<br />URL: <a href='" . $pubDetails['URI'] . "' target='blank' itemprop=\"url\">" . $pubDetails['URI'] . "</a>" : '';
                    break;

                case "conference contribution": // OK
                    $publist .= "<li itemscope itemtype=\"http://schema.org/ScholarlyArticle\">";
                    $publist .= $pubDetails['authors'] . ':';
                    $publist .= "<br />" . $pubDetails['title'];
                    $publist .= $publication['publication type'] == 'Unpublished' ? ' (' . Tools::getName('publications', $pubDetails['pubType'], get_locale()) . (!empty($pubDetails['pubStatus']) ? ', '  . strtolower($pubDetails['pubStatus']) : '') . ')': '';
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
                    $publist .= $pubDetails['DOI'] != '' ? "<br />DOI: <a href='http://dx.doi.org/" . $pubDetails['DOI'] . "' target='blank' itemprop=\"sameAs\">" . $pubDetails['DOI'] . "</a>" : '';
                    $publist .= $pubDetails['URI'] != '' ? "<br />URL: <a href='" . $pubDetails['URI'] . "' target='blank' itemprop=\"url\">" . $pubDetails['URI'] . "</a>" : '';
                    break;

                case "editorial":
                case "edited volumes":
                    $publist .= "<li itemscope itemtype=\"http://schema.org/Book\">";
                    $publist .= $pubDetails['authors'] . ' (' . __('Hrsg.', 'fau-cris') . '):';
                    $publist .= "<br />" . $pubDetails['title'];
                    $publist .= $pubDetails['volume'] != '' ? "<br /><span itemprop=\"volumeNumber\">" . $pubDetails['volume'] . "</span>. <br />" : '';
                     if (!empty($pubDetails['publisher'])) {
                        $publist .= "<br /><span itemprop=\"publisher\" itemscope itemtype=\"http://schema.org/Organization\">";
                        $publist .= $pubDetails['city'] != '' ? "<span class=\"city\" itemprop=\"address\" itemscope itemtype=\"http://schema.org/PostalAddress\">"
                                . "<span itemprop=\"addressLocality\">" . $pubDetails['city'] . "</span></span>: " : '';
                        $publist .= "<span itemprop=\"name\">" . $pubDetails['publisher'] . "</span></span>, ";
                    } else {
                        $publist .= $pubDetails['city'] != '' ? $pubDetails['city'] . ", " : '';
                    }
                    $publist .= $pubDetails['year'] != '' ? "<span itemprop=\"datePublished\">" . $pubDetails['year'] . "</span>" : '';
                    if (!empty($pubDetails['series'])) {
                        $publist .= $pubDetails['series'] != '' ? "<br />(" . $pubDetails['series'] : '';
                        $publist .= $pubDetails['seriesNumber'] != '' ? ", " . _x('Bd.', 'Abkürzung für "Band" bei Publikationen', 'fau-cris') . " " . $pubDetails['seriesNumber'] : '';
                        $publist .= ")";
                    }
                    $publist .= $pubDetails['pagesTotal'] != '' ? "<br /><span itemprop=\"numberOfPages\">" . $pubDetails['pagesTotal'] . "</span> " . __('Seiten', 'fau-cris') : '';
                    $publist .= $pubDetails['ISBN'] != '' ? "<br /><span itemprop=\"isbn\">ISBN: " . $pubDetails['ISBN'] . "</span>" : '';
                    $publist .= $pubDetails['DOI'] != '' ? "<br />DOI: <a href='http://dx.doi.org/" . $pubDetails['DOI'] . "' target='blank' itemprop=\"sameAs\">" . $pubDetails['DOI'] . "</a>" : '';
                    $publist .= $pubDetails['URI'] != '' ? "<br />URL: <a href='" . $pubDetails['URI'] . "' target='blank' itemprop=\"url\">" . $pubDetails['URI'] . "</a>" : '';
                    break;

                case "thesis":
                    $publist .= "<li itemscope itemtype=\"http://schema.org/Thesis\">";
                    $publist .= $pubDetails['authors'] . ':';
                    $publist .= "<br />" . $pubDetails['title'];
                    $publist .= " (" . ($pubDetails['thesisSubtype'] != '' ? $pubDetails['thesisSubtype'] : __('Abschlussarbeit', 'fau-cris')) . ", <span itemprop=\"datePublished\">" . $pubDetails['year'] . "</span>)";        
                    $publist .= $pubDetails['DOI'] != '' ? "<br />DOI: <a href='http://dx.doi.org/" . $pubDetails['DOI'] . "' target='blank' itemprop=\"sameAs\">" . $pubDetails['DOI'] . "</a>" : '';
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
                    $publist .= $pubDetails['DOI'] != '' ? "<br />DOI: <a href='http://dx.doi.org/" . $pubDetails['DOI'] . "' target='blank' itemprop=\"sameAs\">" . $pubDetails['DOI'] . "</a>" : '';
                    $publist .= $pubDetails['URI'] != '' ? "<br />URL: <a href='" . $pubDetails['URI'] . "' target='blank' itemprop=\"url\">" . $pubDetails['URI'] . "</a>" : '';
                    $publist .= $pubDetails['origTitle'] != '' ? "<br />Originaltitel: " . $pubDetails['origTitle'] : '';
                    $publist .= $pubDetails['language'] != '' ? "<br />Sprache: <span itemprop=\"inLanguage\">" . $pubDetails['language'] . "</span>" : '';
                    break;
            }
            if ($this->bibtex == 1) {
                $publist .= "<br />BibTeX: " . $pubDetails['bibtex_link'];
            }
            if ($showsubtype ==1 && $pubDetails['otherSubtype'] !='') {
                $publist .= "<br />(" . $pubDetails['otherSubtype'] . ")";
            }
            $publist .= "</li>";
        }
        $publist .= "</ul>";

        return $publist;
    }

}

class CRIS_publications extends CRIS_webservice {
    /*
     * publication requests, supports multiple organisation ids given as array.
     */

    public function by_orga_id($orgaID = null, &$filter = null) {
        if ($orgaID === null || $orgaID === "0")
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

    public function by_pers_id($persID = null, &$filter = null) {
        if ($persID === null || $persID === "0")
            throw new Exception('Please supply valid person ID');

        if (!is_array($persID))
            $persID = array($persID);

        $requests = array();
        foreach ($persID as $_p) {
            $requests[] = sprintf('getautorelated/Person/%d/PERS_2_PUBL_1', $_p);
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

    public function by_field($fieldID = null) {
        if ($fieldID === null || $fieldID === "0")
            throw new Exception('Please supply valid publication ID');

        if (!is_array($fieldID))
            $fieldID = array($fieldID);

        $requests = array();
        foreach ($fieldID as $_p) {
            $requests[] = sprintf('getrelated/Forschungsbereich/%d/fobe_has_top_publ', $_p);
        }
        return $this->retrieve($requests);
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

        $cristmpl = '<a href="https://cris.fau.de/converis/publicweb/publication/%d" target="_blank">%s</a>';

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
