<?php

require_once("class_Tools.php");
require_once("class_Webservice.php");
require_once("class_Filter.php");
require_once("class_Formatter.php");

class Publikationen {

    private $options;
    public $output;

    public function __construct($einheit = '', $id = '') {

        $this->cms = 'wp';
        $this->options = (array) get_option('_fau_cris');
        $this->orgNr = $this->options['cris_org_nr'];
        $this->order = $this->options['cris_pub_order'];
        $this->univisLink = isset($this->options['cris_univis']) ? $this->options['cris_univis'] : 'none';
        $this->pathPersonenseiteUnivis = '/person/';
        $this->bibtex = $this->options['cris_bibtex'];
        $this->suchstring = '';

        if ((!$this->orgNr || $this->orgNr == 0) && $id == '') {
            print '<p><strong>' . __('Bitte geben Sie die CRIS-ID der Organisation, Person oder Publikation an.', 'fau-cris') . '</strong></p>';
            return;
        }
        if (in_array($einheit, array("person", "orga", "publication"))) {
            $this->id = $id;
            $this->einheit = $einheit;
        } else {
            // keine Einheit angegeben -> OrgNr aus Einstellungen verwenden
            $this->id = $this->orgNr;
            $this->einheit = "orga";
        }

        $univis = NULL;
        if ($this->cms == 'wbk' && $this->cris_award_link == 'person') {
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
     * Ausgabe aller Publikationen ohne Gliederung
     */

    public function pubListe($year = '', $start = '', $type = '', $quotation = '', $items = '', $sortby = 'virtualdate') {
        $pubArray = $this->fetch_publications($year, $start, $type);

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
            $output .= $this->make_list($pubList);
        }

        return $output;
    }

    /*
     * Ausgabe aller Publikationen nach Jahren gegliedert
     */

    public function pubNachJahr($year = '', $start = '', $type = '', $quotation = '', $order2 = 'author') {
        $pubArray = $this->fetch_publications($year, $start, $type);

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
        foreach ($pubList as $array_year => $publications) {
            if (empty($year)) {
                $output .= '<h3>' . $array_year . '</h3>';
            }
            if ($quotation == 'apa' || $quotation == 'mla') {
                $output .= $this->make_quotation_list($publications, $quotation);
            } else {
                $output .= $this->make_list($publications);
            }
        }
        return $output;
    }

    /*
     * Ausgabe aller Publikationen nach Publikationstypen gegliedert
     */

    public function pubNachTyp($year = '', $start = '', $type = '', $quotation = '', $order2 = 'date') {
        $pubArray = $this->fetch_publications($year, $start, $type);

        if (!count($pubArray)) {
            $output = '<p>' . __('Es wurden leider keine Publikationen gefunden.', 'fau-cris') . '</p>';
            return $output;
        }

        // Publikationstypen sortieren
        $order = $this->order;
        if ($order[0] != '' && array_search($order[0], array_column(CRIS_Dicts::$publications, 'short'))) {
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

            if ($quotation == 'apa' || $quotation == 'mla') {
                $output .= $this->make_quotation_list($publications, $quotation);
            } else {
                $output .= $this->make_list($publications);
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

    /* =========================================================================
     * Private Functions
      ======================================================================== */

    /*
     * Holt Daten vom Webservice je nach definierter Einheit.
     */

    private function fetch_publications($year = '', $start = '', $type = '') {
        $filter = Tools::publication_filter($year, $start, $type);
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
            $publist .= "<li>";
            $publist .= $publication->attributes['quotation' . $quotation];
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

    private function make_list($publications) {

        $publist = "<ul>";

        foreach ($publications as $publicationObject) {

            $publication = $publicationObject->attributes;
            // id
            $id = $publicationObject->ID;
            // authors
            $authors = explode(", ", $publication['relauthors']);
            $authorIDs = explode(",", $publication['relpersid']);
            $authorsArray = array();
            foreach ($authorIDs as $i => $key) {
                $authorsArray[] = array('id' => $key, 'name' => $authors[$i]);
            }
            $authorList = array();
            foreach ($authorsArray as $author) {
                $author_elements = explode(" ", $author['name']);
                $author_firstname = array_pop($author_elements);
                $author_lastname = implode(" ", $author_elements);
                $authorList[] = Tools::get_person_link($author['id'], $author_firstname, $author_lastname, $this->univisLink, $this->cms, $this->pathPersonenseiteUnivis, $this->univis, 1);
            }
            $authors_html = implode(", ", $authorList);
            // title
            $title = (array_key_exists('cftitle', $publication) ? strip_tags($publication['cftitle']) : __('O.T.', 'fau-cris'));
            $title_html = "<br />"
                    . "<a href=\"https://cris.fau.de/converis/publicweb/Publication/" . $id
                    . "\" target=\"blank\" title=\"Detailansicht in neuem Fenster &ouml;ffnen\"><span class=\"title\" itemprop=\"name\" style=\"font-weight: bold;\">"
                    . $title
                    . "</span></a>";
            // make array
            setlocale(LC_TIME, get_locale());
            $pubDetails = array(
                'id' => $id,
                'authors' => $authors_html,
                'title' => $title_html,
                'city' => (array_key_exists('cfcitytown', $publication) ? strip_tags($publication['cfcitytown']) : __('O.O.', 'fau-cris')),
                'publisher' => (array_key_exists('publisher', $publication) ? strip_tags($publication['publisher']) : __('O.A.', 'fau-cris')),
                'year' => (array_key_exists('publyear', $publication) ? strip_tags($publication['publyear']) : __('O.J.', 'fau-cris')),
                'pubType' => (array_key_exists('publication type', $publication) ? strip_tags($publication['publication type']) : __('O.A.', 'fau-cris')),
                'pagesTotal' => (array_key_exists('cftotalpages', $publication) ? strip_tags($publication['cftotalpages']) : ''),
                'pagesRange' => (array_key_exists('pagesrange', $publication) ? strip_tags($publication['pagesrange']) : ''),
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
                'bibtex_link' => (array_key_exists('bibtex_link', $publication) ? $publication['bibtex_link'] : __('Nicht verfügbar', 'fau-cris'))
            );

            switch ($pubDetails['pubType']) {

                case "Other": // Falling through
                case "Book": // OK
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
                    if (!empty($pubDetails['series'])) {
                        $publist .= $pubDetails['series'] != '' ? "<br />(" . $pubDetails['series'] : '';
                        $publist .= $pubDetails['seriesNumber'] != '' ? ", " . _x('Bd.', 'Abkürzung für "Band" bei Publikationen', 'fau-cris') . $pubDetails['seriesNumber'] : '';
                        $publist .= ")";
                    }
                    $publist .= $pubDetails['pagesTotal'] != '' ? "<br /><span itemprop=\"numberOfPages\">" . $pubDetails['pagesTotal'] . "</span> " . __('Seiten', 'fau-cris') : '';
                    $publist .= $pubDetails['ISBN'] != '' ? "<br /><span itemprop=\"isbn\">ISBN: " . $pubDetails['ISBN'] . "</span>" : '';
                    $publist .= $pubDetails['DOI'] != '' ? "<br />DOI: <a href='http://dx.doi.org/" . $pubDetails['DOI'] . "' target='blank' itemprop=\"sameAs\">" . $pubDetails['DOI'] . "</a>" : '';
                    $publist .= $pubDetails['URI'] != '' ? "<br />URL: <a href='" . $pubDetails['URI'] . "' target='blank' itemprop=\"url\">" . $pubDetails['URI'] . "</a>" : '';
                    $publist .= "<br />";
                    break;

                case "Article in Edited Volumes":
                    $publist .= "<li itemscope itemtype=\"http://schema.org/ScholarlyArticle\">";
                    $publist .= $pubDetails['authors'] . ':';
                    $publist .= $pubDetails['title'];
                    if ($pubDetails['booktitle'] != '') {
                        $publist .= "<div itemscope itemtype=\"http://schema.org/Book\">In: ";
                        $publist .= $pubDetails['editiors'] != '' ? "<span itemprop=\"author\">" . $pubDetails['editiors'] . " (" . __('Hrsg.', 'fau-cris') . "): </span>" : '';
                        $publist .= "<span itemprop=\"name\" style=\"font-weight:bold;\">" . $pubDetails['booktitle'] . "</span>";
                        $publist .= ($pubDetails['city'] != '' || $pubDetails['publisher'] != '') ? ", <span itemprop=\"publisher\" itemscope itemtype=\"http://schema.org/Organization\">" : '';
                        $publist .= $pubDetails['city'] != '' ? "<span class=\"city\" itemprop=\"address\" itemscope itemtype=\"http://schema.org/PostalAddress\">"
                                . "<span itemprop=\"addressLocality\">" . $pubDetails['city'] . "</span></span>: " : '';
                        $publist .= $pubDetails['publisher'] != '' ? "<span itemprop=\"name\">" . $pubDetails['publisher'] . "</span>" : '';
                        $publist .= ($pubDetails['city'] != '' || $pubDetails['publisher'] != '') ? "</span>" : '';
                        $publist .= $pubDetails['year'] != '' ? ", <span itemprop=\"datePublished\">" . $pubDetails['year'] . "</span>" : '';
                        if (!empty($pubDetails['series'])) {
                            $publist .= $pubDetails['series'] != '' ? " (" . $pubDetails['series'] : '';
                            $publist .= $pubDetails['seriesNumber'] != '' ? ", " . _x('Bd.', 'Abkürzung für "Band" bei Publikationen', 'fau-cris') . $pubDetails['seriesNumber'] : '';
                            $publist .= ")";
                        }
                        $publist .= $pubDetails['ISBN'] != '' ? "<br /><span itemprop=\"isbn\">ISBN: " . $pubDetails['ISBN'] . "</span>" : '';
                        $publist .= "</div>";
                    }
                    $publist .= $pubDetails['pagesTotal'] != '' ? "<span itemprop=\"numberOfPages\">" . $pubDetails['pagesTotal'] . "</span> " . __('Seiten', 'fau-cris')  . "<br />": '';
                    $publist .= $pubDetails['DOI'] != '' ? "DOI: <a href='http://dx.doi.org/" . $pubDetails['DOI'] . "' target='blank' itemprop=\"sameAs\">" . $pubDetails['DOI'] . "</a></span><br />" : '</span>';
                    $publist .= $pubDetails['URI'] != '' ? "URL: <a href='" . $pubDetails['URI'] . "' target='blank' itemprop=\"url\">" . $pubDetails['URI'] . "</a><br />" : '';
                    break;

                case "Journal article":
                    $publist .= "<li itemscope itemtype=\"http://schema.org/ScholarlyArticle\">";
                    $publist .= $pubDetails['authors'] . ":";
                    $publist .= $pubDetails['title'];
                    $publist .= (($pubDetails['journaltitle'] != '') || ($pubDetails['volume'] != '') || ($pubDetails['year'] != '') || ($pubDetails['pagesRange'] != '')) ? "<br />" : '';
                    $publist .= $pubDetails['journaltitle'] != '' ? "In: <span itemscope itemtype=\"http://schema.org/Periodical\" itemid=\"#periodical_" . $pubDetails['id'] . "\"><span itemprop=\"name\" style=\"font-weight: bold;\">" . $pubDetails['journaltitle'] . "</span></span>" : '';
                    $publist .= $pubDetails['seriesNumber'] != '' ? " <span itemprop=\"isPartOf\" itemscope itemtype=\"http://schema.org/PublicationVolume\"><link itemprop=\"isPartOf\" href=\"#periodical_" . $pubDetails['id'] . "\" /><span itemprop=\"volumeNumber\">" . $pubDetails['seriesNumber'] . "</span></span> " : '';
                    $publist .= $pubDetails['year'] != '' ? " (<span itemprop=\"datePublished\">" . $pubDetails['year'] . "</span>)" : '';
                    $publist .= $pubDetails['pagesRange'] != '' ? ", " . _x('S.', 'Abkürzung für "Seite" bei Publikationen', 'fau-cris') . " <span itemprop=\"pagination\">" . $pubDetails['pagesRange'] . "</span>" : '';
                    $publist .= $pubDetails['ISSN'] != '' ? "<br><span itemscope itemtype=\"http://schema.org/Periodical\" itemid=\"#periodical_" . $pubDetails['id'] . "\"><span itemprop=\"issn\">ISSN: " . $pubDetails['ISSN'] . "</span></span></span>" : "</span>";
                    $publist .= $pubDetails['DOI'] != '' ? "<br />DOI: <a href='http://dx.doi.org/" . $pubDetails['DOI'] . "' target='blank' itemprop=\"sameAs\">" . $pubDetails['DOI'] . "</a>" : '';
                    $publist .= $pubDetails['URI'] != '' ? "<br />URL: <a href='" . $pubDetails['URI'] . "' target='blank' itemprop=\"url\">" . $pubDetails['URI'] . "</a>" : '';
                    $publist .= "<br />";
                    break;

                case "Conference contribution": // OK
                    $publist .= "<li itemscope itemtype=\"http://schema.org/ScholarlyArticle\">";
                    $publist .= $pubDetails['authors'] . ':';
                    $publist .= $pubDetails['title'];
                    if ($pubDetails['eventtitle'] != '') {
                        $publist .= "<div itemscope itemtype=\"http://schema.org/Event\" style=\"font-style:italic;\">";
                        $publist .= "<span itemprop=\"name\">" . $pubDetails['eventtitle'] . "</span>";
                        $publist .= ($pubDetails['eventlocation'] != '' || $pubDetails['eventstart'] != '' || $pubDetails['eventend'] != '') ? " (" : '';
                        $publist .= $pubDetails['eventlocation'] != '' ? "<span itemprop =\"location\" itemscope itemtype=\"http://schema.org/PostalAddress\">"
                                . "<span itemprop=\"name\">" . $pubDetails['eventlocation'] . "</span></span>" : '';
                        $publist .= $pubDetails['eventstart'] != '' ? ", <span itemprop=\"startDate\" content=\"" . $pubDetails['eventstart_raw'] . "\">" . $pubDetails['eventstart'] . "</span>" : "<span itemprop=\"startDate\" content=\"" . $pubDetails['eventstart_raw'] . "\"></span>";
                        $publist .= $pubDetails['eventend'] != '' ? " - <span itemprop=\"endDate\" content=\"" . $pubDetails['eventend_raw'] . "\">" . $pubDetails['eventend'] . "</span>" : '';
                        $publist .= ($pubDetails['eventlocation'] != '' || $pubDetails['eventstart'] != '' || $pubDetails['eventend'] != '') ? ")" : '';
                        $publist .= "</div>";
                    }
                    if ($pubDetails['booktitle'] != '') {
                        $publist .= "<div itemscope itemtype=\"http://schema.org/Book\">In: ";
                        $publist .= $pubDetails['editiors'] != '' ? "<span itemprop=\"author\">" . $pubDetails['editiors'] . " (" . __('Hrsg.', 'fau-cris') . "): </span>" : '';
                        $publist .= "<span itemprop=\"name\" style=\"font-weight:bold;\">" . $pubDetails['booktitle'] . "</span>";
                        $publist .= ($pubDetails['city'] != '' || $pubDetails['publisher'] != '') ? ", <span itemprop=\"publisher\" itemscope itemtype=\"http://schema.org/Organization\">" : '';
                        $publist .= $pubDetails['city'] != '' ? "<span class=\"city\" itemprop=\"address\" itemscope itemtype=\"http://schema.org/PostalAddress\">"
                                . "<span itemprop=\"addressLocality\">" . $pubDetails['city'] . "</span></span>: " : '';
                        $publist .= $pubDetails['publisher'] != '' ? "<span itemprop=\"name\">" . $pubDetails['publisher'] . "</span>" : '';
                        $publist .= ($pubDetails['city'] != '' || $pubDetails['publisher'] != '') ? "</span>" : '';
                        $publist .= $pubDetails['year'] != '' ? ", <span itemprop=\"datePublished\">" . $pubDetails['year'] . "</span>" : '';
                        $publist .= "</div>";
                    }
                    $publist .= $pubDetails['DOI'] != '' ? "DOI: <a href='http://dx.doi.org/" . $pubDetails['DOI'] . "' target='blank' itemprop=\"sameAs\">" . $pubDetails['DOI'] . "</a><br />" : '';
                    $publist .= $pubDetails['URI'] != '' ? "URL: <a href='" . $pubDetails['URI'] . "' target='blank' itemprop=\"url\">" . $pubDetails['URI'] . "</a><br />" : '';

                    break;
                case "Editorial":
                    $publist .= "<li itemscope itemtype=\"http://schema.org/Book\">";
                    $publist .= $pubDetails['authors'] . ' (' . __('Hrsg.', 'fau-cris') . '):';
                    $publist .= $pubDetails['title'] . "<br />";
                    $publist .= $pubDetails['volume'] != '' ? "<span itemprop=\"volumeNumber\">" . $pubDetails['volume'] . "</span>. <br />" : '';
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
                        $publist .= $pubDetails['seriesNumber'] != '' ? ", " . _x('Bd.', 'Abkürzung für "Band" bei Publikationen', 'fau-cris') . " " . $pubDetails['seriesNumber'] : '';
                        $publist .= ")";
                    }
                    $publist .= $pubDetails['pagesTotal'] != '' ? "<br /><span itemprop=\"numberOfPages\">" . $pubDetails['pagesTotal'] . "</span> " . __('Seiten', 'fau-cris') : '';
                    $publist .= $pubDetails['ISBN'] != '' ? "<br /><span itemprop=\"isbn\">ISBN: " . $pubDetails['ISBN'] . "</span>" : '';
                    $publist .= $pubDetails['DOI'] != '' ? "<br />DOI: <a href='http://dx.doi.org/" . $pubDetails['DOI'] . "' target='blank' itemprop=\"sameAs\">" . $pubDetails['DOI'] . "</a>" : '';
                    $publist .= $pubDetails['URI'] != '' ? "<br />URL: <a href='" . $pubDetails['URI'] . "' target='blank' itemprop=\"url\">" . $pubDetails['URI'] . "</a>" : '';
                    $publist .= "<br />";
                    break;
                case "Thesis":
                    $publist .= "<li itemscope itemtype=\"http://schema.org/Thesis\">";
                    $publist .= $pubDetails['authors'] . ':';
                    $publist .= $pubDetails['title'];
                    $publist .= "<br />" . __('Abschlussarbeit', '') . " <span itemprop=\"datePublished\">" . $pubDetails['year'] . "</span>";
                    $publist .= $pubDetails['DOI'] != '' ? "<br />DOI: <a href='http://dx.doi.org/" . $pubDetails['DOI'] . "' target='blank' itemprop=\"sameAs\">" . $pubDetails['DOI'] . "</a>" : '';
                    $publist .= $pubDetails['URI'] != '' ? "<br />URL: <a href='" . $pubDetails['URI'] . "' target='blank' itemprop=\"url\">" . $pubDetails['URI'] . "</a>" : '';
                    $publist .= "<br />";
                    break;
                case "Translation":
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
                    $publist .= "<br />";
                    break;
            }
            if ($this->bibtex == 1) {
                $publist .= "BibTeX: " . $pubDetails['bibtex_link'];
            }
            $publist .= "</li>\r";
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
        $title = preg_quote($this->attributes["cftitle"], "/");

        $cristmpl = '<a href="https://cris.fau.de/converis/publicweb/publication/%d" target="_blank">%s</a>';

        $apa = $this->attributes["quotationapa"];
        $mla = $this->attributes["quotationmla"];

        $matches = array();
        $splitapa = preg_match("/^(.+)(" . $title . ")(.+)(" . $doilink . ".+)?$/Uu", $apa, $matches);

        if ($splitapa === 1) {
            $apalink = $matches[1] . \
                    sprintf($cristmpl, $this->ID, $matches[2]) . $matches[3];
            if (isset($matches[4]))
                $apalink .= sprintf('<a href="%s" target="_blank">%s</a>', $matches[4], $matches[4]);
        } else {
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
    $f = new CRIS_Filter(array("publyear__le" => 2016, "publyear__gt" => 2014, "peerreviewed__eq" => "Yes"));
    $publs = $p->by_orga_id("142285", $f);
    $order = "virtualdate";
    $formatter = new CRIS_formatter(NULL, NULL, $order, SORT_DESC);
    $res = $formatter->execute($publs);
    foreach ($res[$order] as $key => $value) {
        echo sprintf("%s: %s\n", $key, $value->attributes[$order]);
    }
}
