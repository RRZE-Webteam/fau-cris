<?php

require_once("class_Tools.php");

class Publikationsliste {

	private $options;
	public $output;

	public function __construct($einheit='', $id='') {
		$this->options = (array) get_option('_fau_cris');
		$orgNr = $this->options['cris_org_nr'];

		if ($einheit == "person") {
			$this->ID = $id;
			//Publikationsliste nach Card (für Personendetailseite)
			$this->suchstring = 'https://cris.fau.de/ws-cached/1.0/public/infoobject/getrelated/Card/' . $this->ID . '/Publ_has_CARD';
		} else {
			// keine Einheit angegeben -> OrgNr verwenden
			$this->suchstring = "https://cris.fau.de/ws-cached/1.0/public/infoobject/getautorelated/Organisation/" . $orgNr . "/ORGA_2_PUBL_1"; //141440
		}

		$xml = Tools::XML2obj($this->suchstring);
		$this->publications = $xml->infoObject;

		// XML -> Array

		$this->pubArray = array();

		foreach ($this->publications as $publication) {
			$this->pubID = (string) $publication['id'];

			foreach ($publication as $attribut) {
				if ($attribut['language'] == 1) {
					$pubAttribut = (string) $attribut['name'] . "_en";
				} else {
					$pubAttribut = (string) $attribut['name'];
				}
				if ((string) $attribut['disposition'] == 'choicegroup') {
					$pubDetail = (string) $attribut->additionalInfo;
				} else {
					$pubDetail = (string) $attribut->data;
				}
				$this->pubArray[$this->pubID][$pubAttribut] = $pubDetail;
			}
		}
		$this->pubArray = Tools::record_sortByYear($this->pubArray);

	}

	/*
	 * Ausgabe aller Publikationen nach Jahren gegliedert
	 */

	public function pubNachJahr($display='') {

		$pubByYear = array();
		$output = '';

		foreach ($this->pubArray as $i => $element) {
			foreach ($element as $j => $sub_element) {
				if (($j == 'publYear')) {
					$pubByYear[$sub_element][$i] = $element;
				}
			}
		}
		if($display != 'klein') {
			if (!empty($pubByYear)) {
				foreach ($pubByYear as $year => $publications) {
					$output .= '<h3>' . $year . '</h3>';
					$output .= $this->make_list($publications);
				}
			} else {
				$output .= '<p>' . __('Es wurden leider keine Publikationen gefunden.','fau-cris') . '</p>';
			}
		} else {
			if (!empty($pubByYear)) {
				foreach ($pubByYear as $year => $publications) {
					$output .= '<h3>' . $year . '</h3>';
					$output .= $this->make_list($publications);
				}
			}
		}
		return $output;
	}

	/*
	 * Ausgabe aller Publikationen nach Publikationstypen gegliedert
	 */

	public function pubNachTyp() {
		$output = '';
		$pubByType = array();

		if (empty($this->publications)) {
			$output .= "<p>Es wurden leider keine Publikationen gefunden.</p>";
		}

		foreach ($this->pubArray as $i => $element) {
			foreach ($element as $j => $sub_element) {
				if (($j == 'Publication type')) {
					$pubByType[$sub_element][$i] = $element;
				}
			}
		}

		// Publikationstypen sortieren
		$order = $this->options['cris_pub_order'];
		if ($order[0] != ''  && in_array($order[0],CRIS_Dicts::$pubNames)) {
			foreach ($order as $key => $value) {
				$order[$key] = Tools::getPubName($value, "en");
			}
			$pubByType = Tools::sort_key($pubByType, $order);

		} else {
			$pubByType = Tools::sort_key($pubByType, CRIS_Dicts::$pubOrder);
		}
		foreach ($pubByType as $type => $publications) {
			$title = Tools::getPubTranslation($type);
			$output .= "<h3>";
			$output .= $title;
			$output .= "</h3>";
			$output .= $this->make_list($publications);
		}
		return $output;
	} // Ende pubNachTyp()

	/*
	 * Ausgabe einzelner Publikationstypen
	 */

	public function publikationstypen($typ) {

		$output = '';
		$publications = array();
		$pubTyp = Tools::getPubName($typ, "en");
		$pubTyp_de = Tools::getPubName($typ, "de");
		if (!isset($pubTyp) && !isset($pubTyp_de)) {
			$output .= "<p>Falscher Parameter</p>";
			return;
		}

		foreach($this->pubArray as $id => $book) {
			if($book['Publication type'] == $pubTyp){
				$publications[$id] = $book;
			}
		}

		if (!empty($publications)) {
			$output .= $this->make_list($publications);
		} else {
			$output .= '<p>' . sprintf(__('Es wurden leider keine Publikationen des Typs &quot;%s&quot; gefunden.','fau-cris'), $pubTyp_de) . '</p>';
		}
		return $output;
	}

	/*
	 * Ausgabe Publikationen einzelner Jahre
	 */

	public function publikationsjahre($year) {

		$output = '';
		$publications = array();

		foreach($this->pubArray as $id => $book) {
			if($book['publYear'] == $year){
				$publications[$id] = $book;
			}
		}

		if (!empty($publications)) {
			$output .= $this->make_list($publications);
		} else {
			$output .= '<p>' . sprintf(__('Es wurden leider keine Publikationen aus dem Jahr %d gefunden.','fau-cris'), $year) . '</p>';
		}
		return $output;
	}

	/*
	 * Ausgabe Publikationen ab einem bestimmten Jahr
	 */

	public function publikationsjahrestart($year) {

		$pubByYear = array();
		$output = '';

		foreach($this->pubArray as $i=>$element) {
			if($element['publYear'] >= $year){
				$publications[$i] = $element;
				foreach($element as $j=>$sub_element) {
					if (($j == 'publYear') ) {
						$pubByYear[$sub_element][$i]= $element;
					}
				}
			}
		}
		if (!empty($pubByYear)) {
			foreach ($pubByYear as $year => $publications) {
				$output .= '<h4>' . $year . '</h4>';
				$output .= $this->make_list($publications);
			}
		} else {
			$output .= '<p>' . sprintf(__('Es wurden leider keine Publikationen nach %d gefunden.','fau-cris'), $year) . '</p>';
		}

		return $output;
	}

	/*
	 * Liste aller Publikationen in CRIS-Reihenfolge
	 */

	public function liste($titel) {

		$output = '';

		if ($titel) {
			$output .= $this->titeltext;
		}

		if (!empty($this->pubArray)) {
			$output .= $this->make_list($this->pubArray);
		} else {
			$output .= '<p>' . __('Es wurden keine Publikationen gefunden.','fau-cris') . '</p>';
		}
		return $output;
	}

	/* =========================================================================
	 * Private Functions
	  ======================================================================== */

	/*
	 * Ausgabe der Publikationsdetails, unterschiedlich nach Publikationstyp
	 */

	private function make_list($publications) {

		$publist = "<ul>";

		foreach ($publications as $id => $publication) {

			$authors = explode(", ", $publication['relAuthors']);
			$authorIDs = explode(",", $publication['relAuthorsId']);
			$authorsArray = array();
			foreach ($authorIDs as $i => $key) {
				$authorsArray[] = array('id' => $key, 'name' => $authors[$i]);
			}

			$pubDetails = array(
				'id' => $id,
				'authorsArray' => $authorsArray,
				'title' => (array_key_exists('cfTitle', $publication) ? strip_tags($publication['cfTitle']) : 'O.T.'),
				'city' => (array_key_exists('cfCityTown', $publication) ? strip_tags($publication['cfCityTown']) : 'O.O.'),
				'publisher' => (array_key_exists('publisher', $publication) ? strip_tags($publication['publisher']) : 'O.A.'),
				'year' => (array_key_exists('publYear', $publication) ? strip_tags($publication['publYear']) : 'O.J.'),
				'pubType' => (array_key_exists('Publication type', $publication) ? strip_tags($publication['Publication type']) : 'O.A.'),
				'pagesTotal' => (array_key_exists('cfTotalPages', $publication) ? strip_tags($publication['cfTotalPages']) : ''),
				'pagesRange' => (array_key_exists('pagesRange', $publication) ? strip_tags($publication['pagesRange']) : ''),
				'volume' => (array_key_exists('cfVol', $publication) ? strip_tags($publication['cfVol']) : 'O.A.'),
				'series' => (array_key_exists('cfSeries', $publication) ? strip_tags($publication['cfSeries']) : 'O.A.'),
				'seriesNumber' => (array_key_exists('cfNum', $publication) ? strip_tags($publication['cfNum']) : 'O.A.'),
				'ISBN' => (array_key_exists('cfISBN', $publication) ? strip_tags($publication['cfISBN']) : 'O.A.'),
				'ISSN' => (array_key_exists('cfISSN', $publication) ? strip_tags($publication['cfISSN']) : 'O.A.'),
				'DOI' => (array_key_exists('DOI', $publication) ? strip_tags($publication['DOI']) : 'O.A.'),
				'URI' => (array_key_exists('cfURI', $publication) ? strip_tags($publication['cfURI']) : 'O.A.'),
				'editiors' => (array_key_exists('Editor', $publication) ? strip_tags($publication['Editor']) : 'O.A.'),
				'booktitle' => (array_key_exists('Edited Volumes', $publication) ? strip_tags($publication['Edited Volumes']) : 'O.A.'), // Titel des Sammelbands
				'journaltitle' => (array_key_exists('journalName', $publication) ? strip_tags($publication['journalName']) : 'O.A.'),
				'conference' => (array_key_exists('Conference', $publication) ? strip_tags($publication['Conference']) : 'O.A.'),
				'origTitle' => (array_key_exists('Originaltitel', $publication) ? strip_tags($publication['Originaltitel']) : 'O.A.'),
				'origLanguage' => (array_key_exists('Language', $publication) ? strip_tags($publication['Language']) : 'O.A.')
			);

			$publist .= "<li style='margin-bottom: 15px; line-height: 150%;'>";

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
			$publist .= ($pubDetails['pubType'] == 'Editorial' ? ' (Hrsg.):' : ':');

			$publist .= "<br /><span class=\"title\"><b>"
			. "<a href=\"http://cris.fau.de/converis/publicweb/Publication/" . $id
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
					$publist .= ($pubDetails['editiors'] != '' ? "In: <strong>" . $pubDetails['editiors'] . '</strong> (Hrsg.):' : '');
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
