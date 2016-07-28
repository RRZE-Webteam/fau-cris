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

    public function pubNachJahr($year = '', $start = '', $type = '', $quotation = '') {
        $pubArray = $this->fetch_publications($year, $start, $type);

        if (!count($pubArray)) {
            $output = '<p>' . __('Es wurden leider keine Publikationen gefunden.', 'fau-cris') . '</p>';
            return $output;
        }

        // sortiere nach Erscheinungsjahr, innerhalb des Jahres nach Erstautor
        $formatter = new CRIS_formatter("publyear", SORT_DESC, "relauthors", SORT_ASC);
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

    public function pubNachTyp($year = '', $start = '', $type = '', $quotation = '') {
        $pubArray = $this->fetch_publications($year, $start, $type);

        if (!count($pubArray)) {
            $output = '<p>' . __('Es wurden leider keine Publikationen gefunden.', 'fau-cris') . '</p>';
            return $output;
        }

        // Publikationstypen sortieren
        $order = $this->order;
        if ($order[0] != '' && array_key_exists($order[0], CRIS_Dicts::$pubNames)) {
            foreach ($order as $key => $value) {
                $order[$key] = Tools::getPubName($value, "en");
            }
        } else {
            $order = array();
            foreach (CRIS_Dicts::$pubOrder as $value) {
                $order[] = Tools::getPubName($value, "en");
            }
        }

        // sortiere nach Typenliste, innerhalb des Jahres nach Jahr abwärts sortieren
        $formatter = new CRIS_formatter("publication type", array_values($order), "publyear", SORT_DESC);
        $pubList = $formatter->execute($pubArray);

        $output = '';
        foreach ($pubList as $array_type => $publications) {
            // Zwischenüberschrift (= Publikationstyp), außer wenn nur ein Typ gefiltert wurde
            if (empty($type)) {
                $title = Tools::getpubTitle($array_type, get_locale());
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
            $id = $publicationObject->ID;

            $authors = explode(", ", $publication['relauthors']);
            $authorIDs = explode(",", $publication['relpersid']);
            $authorsArray = array();
            foreach ($authorIDs as $i => $key) {
                $authorsArray[] = array('id' => $key, 'name' => $authors[$i]);
            }

            $pubDetails = array(
                'id' => $id,
                'authorsArray' => $authorsArray,
                'title' => (array_key_exists('cftitle', $publication) ? strip_tags($publication['cftitle']) : __('O.T.', 'fau-cris')),
                'city' => (array_key_exists('cfcitytown', $publication) ? strip_tags($publication['cfcitytown']) : __('O.O.', 'fau-cris')),
                'publisher' => (array_key_exists('publisher', $publication) ? strip_tags($publication['publisher']) : __('O.A.', 'fau-cris')),
                'year' => (array_key_exists('publyear', $publication) ? strip_tags($publication['publyear']) : __('O.J.', 'fau-cris')),
                'pubType' => (array_key_exists('publication type', $publication) ? strip_tags($publication['publication type']) : __('O.A.', 'fau-cris')),
                'pagesTotal' => (array_key_exists('cftotalpages', $publication) ? strip_tags($publication['cftotalpages']) : ''),
                'pagesRange' => (array_key_exists('pagesrange', $publication) ? strip_tags($publication['pagesrange']) : ''),
                'volume' => (array_key_exists('cfvol', $publication) ? strip_tags($publication['cfvol']) : __('O.A.', 'fau-cris')),
                'series' => (array_key_exists('cfseries', $publication) ? strip_tags($publication['cfseries']) : __('O.A.', 'fau-cris')),
                'seriesNumber' => (array_key_exists('cfnum', $publication) ? strip_tags($publication['cfnum']) : __('O.A.', 'fau-cris')),
                'ISBN' => (array_key_exists('cfisbn', $publication) ? strip_tags($publication['cfisbn']) : __('O.A.', 'fau-cris')),
                'ISSN' => (array_key_exists('cfissn', $publication) ? strip_tags($publication['cfissn']) : __('O.A.', 'fau-cris')),
                'DOI' => (array_key_exists('doi', $publication) ? strip_tags($publication['doi']) : __('O.A.', 'fau-cris')),
                'URI' => (array_key_exists('cfuri', $publication) ? strip_tags($publication['cfuri']) : __('O.A.', 'fau-cris')),
                'editiors' => (array_key_exists('editor', $publication) ? strip_tags($publication['editor']) : __('O.A.', 'fau-cris')),
                'booktitle' => (array_key_exists('edited volumes', $publication) ? strip_tags($publication['edited volumes']) : __('O.A.', 'fau-cris')), // Titel des Sammelbands
                'journaltitle' => (array_key_exists('journalname', $publication) ? strip_tags($publication['journalname']) : __('O.A.', 'fau-cris')),
                'conference' => (array_key_exists('conference', $publication) ? strip_tags($publication['conference']) : 'O.A.'),
                'origTitle' => (array_key_exists('originaltitel', $publication) ? strip_tags($publication['originaltitel']) : __('O.A.', 'fau-cris')),
                'origLanguage' => (array_key_exists('language', $publication) ? strip_tags($publication['language']) : __('O.A.', 'fau-cris')),
                'bibtex_link' => (array_key_exists('bibtex_link', $publication) ? $publication['bibtex_link'] : __('Nicht verfügbar', 'fau-cris'))
            );

            $publist .= "<li>";

            $authorList = array();
            foreach ($pubDetails['authorsArray'] as $author) {
                $span_pre = "<span class=\"author\">";
                $span_post = "</span>";
                $authordata = $span_pre . $author['name'] . $span_post;
                $author_firstname = explode(" ", $author['name'])[1];
                $author_lastname = explode(" ", $author['name'])[0];
                $authorList[] = Tools::get_person_link($author['id'], $author_firstname, $author_lastname, $this->univisLink, $this->cms, $this->pathPersonenseiteUnivis, $this->univis, 1);
            }
            $publist .= implode(", ", $authorList);
            $publist .= ($pubDetails['pubType'] == 'Editorial' ? ' (' . __('Hrsg.', 'fau-cris') . '):' : ':');

            $publist .= "<br /><span class=\"title\"><b>"
                    . "<a href=\"https://cris.fau.de/converis/publicweb/Publication/" . $id
                    . "\" target=\"blank\" title=\"Detailansicht in neuem Fenster &ouml;ffnen\">"
                    . $pubDetails['title']
                    . "</a>"
                    . "</b></span>";


            switch ($pubDetails['pubType']) {

                case "Other": // Falling through
                case "Book":
                    $publist .= ((($pubDetails['city'] != '') || ($pubDetails['publisher'] != '') || ($pubDetails['year'] != '')) ? "<br />" : '');
                    $publist .= ($pubDetails['volume'] != '' ? $pubDetails['volume'] . ". " : '');
                    $publist .= ($pubDetails['city'] != '' ? "<span class=\"city\">" . $pubDetails['city'] . "</span>: " : '');
                    $publist .= ($pubDetails['publisher'] != '' ? $pubDetails['publisher'] . ", " : '');
                    $publist .= ($pubDetails['year'] != '' ? $pubDetails['year'] : '');
                    $publist .= ($pubDetails['series'] != '' ? "<br />" . $pubDetails['series'] : '');
                    $publist .= ($pubDetails['seriesNumber'] != '' ? "Bd. " . $pubDetails['seriesNumber'] : '');
                    $publist .= ($pubDetails['pagesTotal'] != '' ? "<br />" . $pubDetails['pagesTotal'] . " " . __('Seiten', 'fau-cris') : '');
                    $publist .= ($pubDetails['ISBN'] != '' ? "<br />ISBN: " . $pubDetails['ISBN'] : '');
                    $publist .= ($pubDetails['ISSN'] != '' ? "<br />ISSN: " . $pubDetails['ISSN'] : '');
                    $publist .= ($pubDetails['DOI'] != '' ? "<br />DOI: <a href='http://dx.doi.org/" . $pubDetails['DOI'] . "' target='blank'>" . $pubDetails['DOI'] . "</a>" : '');
                    $publist .= ($pubDetails['URI'] != '' ? "<br />URL: <a href='" . $pubDetails['URI'] . "' target='blank'>" . $pubDetails['URI'] . "</a>" : '');
                    break;

                case "Article in Edited Volumes":
                    $publist .= ((($pubDetails['editiors'] != '') || ($pubDetails['booktitle'] != '')) ? "<br />" : '');
                    $publist .= ($pubDetails['editiors'] != '' ? "In: <strong>" . $pubDetails['editiors'] . ' (' . __('Hrsg.', 'fau-cris') . '): </strong>' : '');
                    $publist .= ($pubDetails['booktitle'] != '' ? " <strong><em>" . $pubDetails['booktitle'] . '</em></strong>' : '');
                    $publist .= ((($pubDetails['city'] != '') || ($pubDetails['publisher'] != '') || ($pubDetails['year'] != '')) ? "<br />" : '');
                    $publist .= ($pubDetails['volume'] != '' ? $pubDetails['volume'] . ". " : '');
                    $publist .= ($pubDetails['city'] != '' ? "<span class=\"city\">" . $pubDetails['city'] . "</span>: " : '');
                    $publist .= ($pubDetails['publisher'] != '' ? $pubDetails['publisher'] . ", " : '');
                    $publist .= ($pubDetails['year'] != '' ? $pubDetails['year'] : '');
                    $publist .= ($pubDetails['series'] != '' ? "<br />" . $pubDetails['series'] : '');
                    $publist .= ($pubDetails['seriesNumber'] != '' ? "Bd. " . $pubDetails['seriesNumber'] : '');
                    $publist .= ($pubDetails['pagesTotal'] != '' ? "<br />" . $pubDetails['pagesTotal'] . " " . __('Seiten', 'fau-cris') : '');
                    $publist .= ($pubDetails['ISBN'] != '' ? "<br />ISBN: " . $pubDetails['ISBN'] : '');
                    $publist .= ($pubDetails['ISSN'] != '' ? "<br />ISSN: " . $pubDetails['ISSN'] : '');
                    $publist .= ($pubDetails['DOI'] != '' ? "<br />DOI: <a href='http://dx.doi.org/" . $pubDetails['DOI'] . "' target='blank'>" . $pubDetails['DOI'] . "</a>" : '');
                    $publist .= ($pubDetails['URI'] != '' ? "<br />URL: <a href='" . $pubDetails['URI'] . "' target='blank'>" . $pubDetails['URI'] . "</a>" : '');
                    break;

                case "Journal article":
                    $publist .= ((($pubDetails['journaltitle'] != '') || ($pubDetails['volume'] != '') || ($pubDetails['year'] != '') || ($pubDetails['pagesRange'] != '')) ? "<br />" : '');
                    $publist .= ($pubDetails['journaltitle'] != '' ? "In: <strong>" . $pubDetails['journaltitle'] . '</strong> ' : '');
                    $publist .= ($pubDetails['volume'] != '' ? $pubDetails['volume'] . ". " : '');
                    $publist .= ($pubDetails['year'] != '' ? " (" . $pubDetails['year'] . ")" : '');
                    $publist .= ($pubDetails['pagesRange'] != '' ? ", " . __('S.', 'Abkürzung für "Seite" bei Publikationen', 'fau-cris') . " " . $pubDetails['pagesRange'] : '');
                    $publist .= ($pubDetails['DOI'] != '' ? "<br />DOI: <a href='http://dx.doi.org/" . $pubDetails['DOI'] . "' target='blank'>" . $pubDetails['DOI'] . "</a>" : '');
                    $publist .= ($pubDetails['URI'] != '' ? "<br />URL: <a href='" . $pubDetails['URI'] . "' target='blank'>" . $pubDetails['URI'] . "</a>" : '');
                    break;

                case "Conference contribution":
                    $publist .= ((($pubDetails['conference'] != '') || ($pubDetails['publisher'] != '')) ? "<br />" : '');
                    $publist .= ($pubDetails['conference'] != '' ? $pubDetails['conference'] : '');
                    $publist .= ((($pubDetails['conference'] != '') && ($pubDetails['publisher'] != '')) ? ", " : '');
                    $publist .= ($pubDetails['publisher'] != '' ? $pubDetails['publisher'] : '');
                    $publist .= ((($pubDetails['city'] != '') || ($pubDetails['year'] != '')) ? "<br />" : '');
                    $publist .= ($pubDetails['city'] != '' ? "<span class=\"city\">" . $pubDetails['city'] . "</span>" : '');
                    $publist .= ($pubDetails['year'] != '' ? " (" . $pubDetails['year'] . ")" : '');
                    $publist .= ($pubDetails['DOI'] != '' ? "<br />DOI: <a href='http://dx.doi.org/" . $pubDetails['DOI'] . "' target='blank'>" . $pubDetails['DOI'] . "</a>" : '');
                    $publist .= ($pubDetails['URI'] != '' ? "<br />URL: <a href='" . $pubDetails['URI'] . "' target='blank'>" . $pubDetails['URI'] . "</a>" : '');
                    break;
                case "Editorial":
                    $publist .= ((($pubDetails['city'] != '') || ($pubDetails['publisher'] != '') || ($pubDetails['year'] != '')) ? "<br />" : '');
                    $publist .= ($pubDetails['volume'] != '' ? $pubDetails['volume'] . ". " : '');
                    $publist .= ($pubDetails['city'] != '' ? "<span class=\"city\">" . $pubDetails['city'] . "</span>: " : '');
                    $publist .= ($pubDetails['publisher'] != '' ? $pubDetails['publisher'] . ", " : '');
                    $publist .= ($pubDetails['year'] != '' ? $pubDetails['year'] : '');
                    $publist .= ($pubDetails['series'] != '' ? "<br />" . $pubDetails['series'] : '');
                    $publist .= ($pubDetails['seriesNumber'] != '' ? "Bd. " . $pubDetails['seriesNumber'] : '');
                    $publist .= ($pubDetails['pagesTotal'] != '' ? "<br />" . $pubDetails['pagesTotal'] . " " . __('Seiten', 'fau-cris') : '');
                    $publist .= ($pubDetails['ISBN'] != '' ? "<br />ISBN: " . $pubDetails['ISBN'] : '');
                    $publist .= ($pubDetails['ISSN'] != '' ? "<br />ISSN: " . $pubDetails['ISSN'] : '');
                    $publist .= ($pubDetails['DOI'] != '' ? "<br />DOI: <a href='http://dx.doi.org/" . $pubDetails['DOI'] . "' target='blank'>" . $pubDetails['DOI'] . "</a>" : '');
                    $publist .= ($pubDetails['URI'] != '' ? "<br />URL: <a href='" . $pubDetails['URI'] . "' target='blank'>" . $pubDetails['URI'] . "</a>" : '');
                    break;
                case "Thesis":
                    $publist .= "<br />Abschlussarbeit " . $pubDetails['year'];
                    $publist .= ($pubDetails['DOI'] != '' ? "<br />DOI: <a href='http://dx.doi.org/" . $pubDetails['DOI'] . "' target='blank'>" . $pubDetails['DOI'] . "</a>" : '');
                    $publist .= ($pubDetails['URI'] != '' ? "<br />URL: <a href='" . $pubDetails['URI'] . "' target='blank'>" . $pubDetails['URI'] . "</a>" : '');
                    break;
                case "Translation":
                    $publist .= ((($pubDetails['city'] != '') || ($pubDetails['publisher'] != '') || ($pubDetails['year'] != '')) ? "<br />" : '');
                    $publist .= ($pubDetails['volume'] != '' ? $pubDetails['volume'] . ". " : '');
                    $publist .= ($pubDetails['city'] != '' ? "<span class=\"city\">" . $pubDetails['city'] . "</span>: " : '');
                    $publist .= ($pubDetails['publisher'] != '' ? $pubDetails['publisher'] . ", " : '');
                    $publist .= ($pubDetails['series'] != '' ? "<br />" . $pubDetails['series'] : '');
                    $publist .= ($pubDetails['seriesNumber'] != '' ? "Bd. " . $pubDetails['seriesNumber'] : '');
                    $publist .= ($pubDetails['pagesTotal'] != '' ? "<br />" . $pubDetails['pagesTotal'] . " " . __('Seiten', 'fau-cris') : '');
                    $publist .= ($pubDetails['ISBN'] != '' ? "<br />ISBN: " . $pubDetails['ISBN'] : '');
                    $publist .= ($pubDetails['ISSN'] != '' ? "<br />ISSN: " . $pubDetails['ISSN'] : '');
                    $publist .= ($pubDetails['DOI'] != '' ? "<br />DOI: <a href='http://dx.doi.org/" . $pubDetails['DOI'] . "' target='blank'>" . $pubDetails['DOI'] . "</a>" : '');
                    $publist .= ($pubDetails['URI'] != '' ? "<br />URL: <a href='" . $pubDetails['URI'] . "' target='blank'>" . $pubDetails['URI'] . "</a>" : '');
                    $publist .= ($pubDetails['origTitle'] != '' ? "<br />Originaltitel: " . $pubDetails['origTitle'] : '');
                    $publist .= ($pubDetails['origLanguage'] != '' ? "<br />Originalsprache: " . $pubDetails['origLanguage'] : '');
                    break;
            }
            if ($this->bibtex == 1) {
                $publist .= "<br />BibTeX: " . $pubDetails['bibtex_link'];
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
