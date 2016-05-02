<?php

require_once("class_Tools.php");
require_once("class_Publications.php");

class Publikationen_neu {

	private $options;
	public $output;

	public function __construct($einheit='', $id='') {
            $this->options = (array) get_option('_fau_cris');
            $orgNr = $this->options['cris_org_nr'];
            $this->suchstring = '';

            if((!$orgNr||$orgNr==0) && $id=='') {
                print '<p><strong>' . __('Bitte geben Sie die CRIS-ID der Organisation, Person oder Publikation an.','fau-cris') . '</strong></p>';
                return;
            }

            if (in_array($einheit, array("person", "orga", "publication"))) {
                $this->id = $id;
                $this->einheit = $einheit;
            } else {
                // keine Einheit angegeben -> OrgNr aus Einstellungen verwenden
                $this->id = $orgNr;
                $this->einheit = "orga";
            }
	}

	/*
	 * Ausgabe aller Publikationen nach Jahren gegliedert
	 */

	public function pubNachJahr($year = '', $start = '', $type = '', $quotation = '', $items = '') {
            $pubArray = $this->fetch_publications($year, $start, $type);

			if (!count($pubArray)) {
                $output = '<p>' . __('Es wurden leider keine Publikationen gefunden.','fau-cris') . '</p>';
                return $output;
            }

            // sortiere nach Erscheinungsjahr, innerhalb des Jahres nach Erstautor
            $formatter = new CRIS_formatter("publyear", SORT_DESC, "relauthors", SORT_ASC);
            $pubList = $formatter->execute($pubArray, $items);

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

	public function pubNachTyp($year = '', $start = '', $type = '', $quotation = '', $items = '') {
            $pubArray = $this->fetch_publications($year, $start, $type);

            if (!count($pubArray)) {
                $output = '<p>' . __('Es wurden leider keine Publikationen gefunden.','fau-cris') . '</p>';
                return $output;
            }

            // Publikationstypen sortieren
            $order = $this->options['cris_pub_order'];
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
//            print_r($order);

            // sortiere nach Typenliste, innerhalb des Jahres nach Jahr abwärts sortieren
            $formatter = new CRIS_formatter("publication type", array_values($order), "publyear", SORT_DESC);
            $pubList = $formatter->execute($pubArray, $items);

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
	} // Ende pubNachTyp()

	public function singlePub($quotation = '') {
            $ws = new CRIS_publications();

            try {
                $pubArray = $ws->by_id($this->id);
            } catch (Exception $ex) {
                return;
            }

            if (!count($pubArray)) return;

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
			$authorIDs = explode(",", $publication['relauthorsid']);
			$authorsArray = array();
			foreach ($authorIDs as $i => $key) {
				$authorsArray[] = array('id' => $key, 'name' => $authors[$i]);
			}

			$pubDetails = array(
				'id' => $id,
				'authorsArray' => $authorsArray,
				'title' => (array_key_exists('cftitle', $publication) ? strip_tags($publication['cftitle']) : 'O.T.'),
				'city' => (array_key_exists('cfcitytown', $publication) ? strip_tags($publication['cfcitytown']) : 'O.O.'),
				'publisher' => (array_key_exists('publisher', $publication) ? strip_tags($publication['publisher']) : 'O.A.'),
				'year' => (array_key_exists('publyear', $publication) ? strip_tags($publication['publyear']) : 'O.J.'),
				'pubType' => (array_key_exists('publication type', $publication) ? strip_tags($publication['publication type']) : 'O.A.'),
				'pagesTotal' => (array_key_exists('cftotalpages', $publication) ? strip_tags($publication['cftotalpages']) : ''),
				'pagesRange' => (array_key_exists('pagesrange', $publication) ? strip_tags($publication['pagesrange']) : ''),
				'volume' => (array_key_exists('cfvol', $publication) ? strip_tags($publication['cfvol']) : 'O.A.'),
				'series' => (array_key_exists('cfseries', $publication) ? strip_tags($publication['cfseries']) : 'O.A.'),
				'seriesNumber' => (array_key_exists('cfnum', $publication) ? strip_tags($publication['cfnum']) : 'O.A.'),
				'ISBN' => (array_key_exists('cfisbn', $publication) ? strip_tags($publication['cfisbn']) : 'O.A.'),
				'ISSN' => (array_key_exists('cfissn', $publication) ? strip_tags($publication['cfissn']) : 'O.A.'),
				'DOI' => (array_key_exists('doi', $publication) ? strip_tags($publication['doi']) : 'O.A.'),
				'URI' => (array_key_exists('cfuri', $publication) ? strip_tags($publication['cfuri']) : 'O.A.'),
				'editiors' => (array_key_exists('editor', $publication) ? strip_tags($publication['editor']) : 'O.A.'),
				'booktitle' => (array_key_exists('edited volumes', $publication) ? strip_tags($publication['edited volumes']) : 'O.A.'), // Titel des Sammelbands
				'journaltitle' => (array_key_exists('journalname', $publication) ? strip_tags($publication['journalname']) : 'O.A.'),
				'conference' => (array_key_exists('conference', $publication) ? strip_tags($publication['conference']) : 'O.A.'),
				'origTitle' => (array_key_exists('originaltitel', $publication) ? strip_tags($publication['originaltitel']) : 'O.A.'),
				'origLanguage' => (array_key_exists('language', $publication) ? strip_tags($publication['language']) : 'O.A.')
			);

			$publist .= "<li>";

			$authorList = array();
			foreach ($pubDetails['authorsArray'] as $author) {
				$span_pre = "<span class=\"author\">";
				$span_post = "</span>";
				$authordata = $span_pre . $author['name'] . $span_post;
				$author_firstname = explode(" ", $author['name'])[1];
				$author_lastname = explode(" ", $author['name'])[0];
				if ($author['id']
						&& !in_array($author['id'], array('invisible', 'external'))
						&& isset($this->options['cris_univis'])
						&& $this->options['cris_univis'] == 1
						&& Tools::person_slug($author_firstname, $author_lastname) != "") {
					$link_pre = "<a href=\"/person/" . Tools::person_slug($author_firstname, $author_lastname) . "\">";
					$link_post = "</a>";
					$authordata = $link_pre . $authordata . $link_post;
				}
				$authorList[] = $authordata;
			}
			$publist .= implode(", ", $authorList);
			$publist .= ($pubDetails['pubType'] == 'Editorial' ? '(' . __('Hrsg.','fau-cris') . '):' : ':');

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
					$publist .= ($pubDetails['pagesTotal'] != '' ? "<br />" . $pubDetails['pagesTotal'] . " " . __('Seiten','fau-cris') : '');
					$publist .= ($pubDetails['ISBN'] != '' ? "<br />ISBN: " . $pubDetails['ISBN'] : '');
					$publist .= ($pubDetails['ISSN'] != '' ? "<br />ISSN: " . $pubDetails['ISSN'] : '');
					$publist .= ($pubDetails['DOI'] != '' ? "<br />DOI: <a href='http://dx.doi.org/" . $pubDetails['DOI'] . "' target='blank'>" . $pubDetails['DOI'] . "</a>" : '');
					$publist .= ($pubDetails['URI'] != '' ? "<br />URL: <a href='" . $pubDetails['URI'] . "' target='blank'>" . $pubDetails['URI'] . "</a>" : '');
					break;

				case "Article in Edited Volumes":
					$publist .= ((($pubDetails['editiors'] != '') || ($pubDetails['booktitle'] != '')) ? "<br />" : '');
					$publist .= ($pubDetails['editiors'] != '' ? "In: <strong>" . $pubDetails['editiors'] . ' ('.__('Hrsg.','fau-cris').'): </strong>' : '');
					$publist .= ($pubDetails['booktitle'] != '' ? " <strong><em>" . $pubDetails['booktitle'] . '</em></strong>' : '');
					$publist .= ((($pubDetails['city'] != '') || ($pubDetails['publisher'] != '') || ($pubDetails['year'] != '')) ? "<br />" : '');
					$publist .= ($pubDetails['volume'] != '' ? $pubDetails['volume'] . ". " : '');
					$publist .= ($pubDetails['city'] != '' ? "<span class=\"city\">" . $pubDetails['city'] . "</span>: " : '');
					$publist .= ($pubDetails['publisher'] != '' ? $pubDetails['publisher'] . ", " : '');
					$publist .= ($pubDetails['year'] != '' ? $pubDetails['year'] : '');
					$publist .= ($pubDetails['series'] != '' ? "<br />" . $pubDetails['series'] : '');
					$publist .= ($pubDetails['seriesNumber'] != '' ? "Bd. " . $pubDetails['seriesNumber'] : '');
					$publist .= ($pubDetails['pagesTotal'] != '' ? "<br />" . $pubDetails['pagesTotal'] . " " . __('Seiten','fau-cris') : '');
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
					$publist .= ($pubDetails['pagesTotal'] != '' ? "<br />" . $pubDetails['pagesTotal'] . " " . __('Seiten','fau-cris') : '');
					$publist .= ($pubDetails['ISBN'] != '' ? "<br />ISBN: " . $pubDetails['ISBN'] : '');
					$publist .= ($pubDetails['ISSN'] != '' ? "<br />ISSN: " . $pubDetails['ISSN'] : '');
					$publist .= ($pubDetails['DOI'] != '' ? "<br />DOI: <a href='http://dx.doi.org/" . $pubDetails['DOI'] . "' target='blank'>" . $pubDetails['DOI'] . "</a>" : '');
					$publist .= ($pubDetails['URI'] != '' ? "<br />URL: <a href='" . $pubDetails['URI'] . "' target='blank'>" . $pubDetails['URI'] . "</a>" : '');
					break;
				case "Thesis":
					$publist .= "<br />Abschlussarbeit";
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
					$publist .= ($pubDetails['pagesTotal'] != '' ? "<br />" . $pubDetails['pagesTotal'] . " " . __('Seiten','fau-cris') : '');
					$publist .= ($pubDetails['ISBN'] != '' ? "<br />ISBN: " . $pubDetails['ISBN'] : '');
					$publist .= ($pubDetails['ISSN'] != '' ? "<br />ISSN: " . $pubDetails['ISSN'] : '');
					$publist .= ($pubDetails['DOI'] != '' ? "<br />DOI: <a href='http://dx.doi.org/" . $pubDetails['DOI'] . "' target='blank'>" . $pubDetails['DOI'] . "</a>" : '');
					$publist .= ($pubDetails['URI'] != '' ? "<br />URL: <a href='" . $pubDetails['URI'] . "' target='blank'>" . $pubDetails['URI'] . "</a>" : '');
					$publist .= ($pubDetails['origTitle'] != '' ? "<br />Originaltitel: " . $pubDetails['origTitle'] : '');
					$publist .= ($pubDetails['origLanguage'] != '' ? "<br />Originalsprache: " . $pubDetails['origLanguage'] : '');
					break;
			}
			$publist .= "</li>";
		}
		$publist .= "</ul>";

		return $publist;
	}

}
