<?php

require_once("class_Tools.php");

class Publikationsliste {

	private $pathPersonenseite;
	private $options;

	public function __construct($einheit='', $id='') {
		$this->options = (array) get_option('_fau_cris');
		$orgNr = $this->options['cris_org_nr'];
		if (isset($this->options['cris_staff_page'])) {
			$this->pathPersonenseite = "/" . $this->options['cris_staff_page'] . '/?id=';
		}

		if ($einheit == "person") {
			$this->ID = $id;
			//Publikationsliste nach Card (für Personendetailseite)
			$this->suchstring = 'https://cris.fau.de/ws-cached/public/infoobject/getrelated/Card/' . $this->ID . '/Publ_has_CARD';
		} else {
			// keine Einheit angegeben -> OrgNr verwenden
			$this->suchstring = "https://cris.fau.de/ws-cached/public/infoobject/getautorelated/Organisation/" . $orgNr . "/ORGA_2_PUBL_1"; //141440
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
					echo '<h3>' . $year . '</h3>';
					$this->make_list($publications);
				}
			} else {
				echo '<p>' . __('Es wurden leider keine Publikationen gefunden.','fau-cris') . '</p>';
			}
		} else {
			if (!empty($pubByYear)) {
				echo '<h3>' . __('Publikationen','fau-cris') . '</h3>';
				foreach ($pubByYear as $year => $publications) {
					echo '<h4>' . $year . '</h4>';
					$this->make_list($publications);
				}
			}
		}
	}

	/*
	 * Ausgabe aller Publikationen nach Publikationstypen gegliedert
	 */

	public function pubNachTyp() {

		if (empty($this->publications)) {
			echo "<p>Es wurden leider keine Publikationen gefunden.</p>";
		}

		$pubByType = array();

		foreach ($this->pubArray as $i => $element) {
			foreach ($element as $j => $sub_element) {
				if (($j == 'Publication type')) {
					$pubByType[$sub_element][$i] = $element;
				}
			}
		}

		// Publikationstypen sortieren
		$order = $this->options['cris_pub_order'];
		if ($order[0] != '') {
			$pubByType = Tools::sort_key($pubByType, $order);
		} else {
			$pubByType = Tools::sort_key($pubByType, Dicts::$jobOrder);
		}
		foreach ($pubByType as $type => $publications) {
			$title = Tools::getPubTranslation($type);
			echo "<h3>";
			echo $title;
			echo "</h3>";
			$this->make_list($publications);
		}
	} // Ende pubNachTyp()

	/*
	 * Ausgabe einzelner Publikationstypen
	 */

	public function publikationstypen($typ) {

		$publications = array();
		$pubTyp = Tools::getPubName($typ, "en");
		$pubTyp_de = Tools::getPubName($typ, "de");
		if (!isset($pubTyp) && !isset($pubTyp_de)) {
			echo "<p>Falscher Parameter</p>";
			return;
		}

		foreach($this->pubArray as $id => $book) {
			if($book['Publication type'] == $pubTyp){
				$publications[$id] = $book;
			}
		}

		if (!empty($publications)) {
			$this->make_list($publications);
		} else {
			echo '<p>' . sprintf(__('Es wurden leider keine Publikationen des Typs &quot;%s&quot; gefunden.','fau-cris'), $pubTyp_de) . '</p>';
		}
	}

	/*
	 * Ausgabe Publikationen einzelner Jahre
	 */

	public function publikationsjahre($year) {

		$publications = array();

		foreach($this->pubArray as $id => $book) {
			if($book['publYear'] == $year){
				$publications[$id] = $book;
			}
		}

		if (!empty($publications)) {
			$this->make_list($publications);
		} else {
			echo '<p>' . sprintf(__('Es wurden leider keine Publikationen aus dem Jahr %d gefunden.','fau-cris'), $year) . '</p>';
		}
	}

	/*
	 * Liste aller Publikationen in CRIS-Reihenfolge
	 */

	public function liste($titel) {

		if ($titel) {
			echo $this->titeltext;
		}

		if (!empty($this->pubArray)) {
			$this->make_list($this->pubArray);
		} else {
			echo '<p>' . __('Es wurden keine Publikationen gefunden.','fau-cris') . '</p>';
		}
	}

	/* =========================================================================
	 * Private Functions
	  ======================================================================== */

	/*
	 * Ausgabe der Publikationsdetails, unterschiedlich nach Publikationstyp
	 */

	private function make_list($publications) {

		echo "<ul>";

		foreach ($publications as $id => $publication) {

			$authors = explode(", ", $publication['relAuthors']);
			$authorIDs = explode(",", $publication['relAuthorsId']);
			$authorsArray = array();
			foreach ($authorIDs as $i => $key) {
				$authorsArray[] = array($key => $authors[$i]);
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

			echo "<li style='margin-bottom: 15px; line-height: 150%;'>";

			foreach ($pubDetails['authorsArray'] as $author) {
				foreach ($author as $authorID => $authorName) {
					$authorList = array();
					$link_pre = "<a href=\"" . $this->pathPersonenseite . "/" . $authorID . "\">";
					$link_post = "</a>";
					$span_pre = "<span class=\"author\">";
					$span_post = "</span>";
					$authorList[] = ($authorID && $authorID != 'invisible' ? $link_pre : '') . $span_pre . $authorName . $span_post . ($authorID && $authorID != 'invisible' ? $link_post : '');
				}
			}
			print implode(", ", $authorList);
			echo ($pubDetails['pubType'] == 'Editorial' ? ' (Hrsg.):' : ':');

			echo "<br /><span class=\"title\"><b>"
			. "<a href=\"http://cris.fau.de/converis/publicweb/Publication/" . $id
			. "\" target=\"blank\" title=\"Detailansicht in neuem Fenster &ouml;ffnen\">"
			. $pubDetails['title']
			. "</a>"
			. "</b></span>";


			switch ($pubDetails['pubType']) {

				case "Other": // Falling through
				case "Book":
					echo ((($pubDetails['city'] != '') || ($pubDetails['publisher'] != '') || ($pubDetails['year'] != '')) ? "<br />" : '');
					echo ($pubDetails['volume'] != '' ? $pubDetails['volume'] . ". " : '');
					echo ($pubDetails['city'] != '' ? "<span class=\"city\">" . $pubDetails['city'] . "</span>: " : '');
					echo ($pubDetails['publisher'] != '' ? $pubDetails['publisher'] . ", " : '');
					echo ($pubDetails['year'] != '' ? $pubDetails['year'] : '');
					echo ($pubDetails['series'] != '' ? "<br />" . $pubDetails['series'] : '');
					echo ($pubDetails['seriesNumber'] != '' ? "Bd. " . $pubDetails['seriesNumber'] : '');
					echo ($pubDetails['pagesTotal'] != '' ? "<br />" . $pubDetails['pagesTotal'] . " " . __('Seiten','fau-cris') : '');
					echo ($pubDetails['ISBN'] != '' ? "<br />ISBN: " . $pubDetails['ISBN'] : '');
					echo ($pubDetails['ISSN'] != '' ? "<br />ISSN: " . $pubDetails['ISSN'] : '');
					echo ($pubDetails['DOI'] != '' ? "<br />DOI: <a href='http://dx.doi.org/" . $pubDetails['DOI'] . "' target='blank'>" . $pubDetails['DOI'] . "</a>" : '');
					echo ($pubDetails['URI'] != '' ? "<br />URL: <a href='" . $pubDetails['URI'] . "' target='blank'>" . $pubDetails['URI'] . "</a>" : '');
					break;

				case "Article in Edited Volumes":
					echo ((($pubDetails['editiors'] != '') || ($pubDetails['booktitle'] != '')) ? "<br />" : '');
					echo ($pubDetails['editiors'] != '' ? "In: <strong>" . $pubDetails['editiors'] . '</strong> (Hrsg.):' : '');
					echo ($pubDetails['booktitle'] != '' ? " <strong><em>" . $pubDetails['booktitle'] . '</em></strong>' : '');
					echo ((($pubDetails['city'] != '') || ($pubDetails['publisher'] != '') || ($pubDetails['year'] != '')) ? "<br />" : '');
					echo ($pubDetails['volume'] != '' ? $pubDetails['volume'] . ". " : '');
					echo ($pubDetails['city'] != '' ? "<span class=\"city\">" . $pubDetails['city'] . "</span>: " : '');
					echo ($pubDetails['publisher'] != '' ? $pubDetails['publisher'] . ", " : '');
					echo ($pubDetails['year'] != '' ? $pubDetails['year'] : '');
					echo ($pubDetails['series'] != '' ? "<br />" . $pubDetails['series'] : '');
					echo ($pubDetails['seriesNumber'] != '' ? "Bd. " . $pubDetails['seriesNumber'] : '');
					echo ($pubDetails['pagesTotal'] != '' ? "<br />" . $pubDetails['pagesTotal'] . " " . __('Seiten','fau-cris') : '');
					echo ($pubDetails['ISBN'] != '' ? "<br />ISBN: " . $pubDetails['ISBN'] : '');
					echo ($pubDetails['ISSN'] != '' ? "<br />ISSN: " . $pubDetails['ISSN'] : '');
					echo ($pubDetails['DOI'] != '' ? "<br />DOI: <a href='http://dx.doi.org/" . $pubDetails['DOI'] . "' target='blank'>" . $pubDetails['DOI'] . "</a>" : '');
					echo ($pubDetails['URI'] != '' ? "<br />URL: <a href='" . $pubDetails['URI'] . "' target='blank'>" . $pubDetails['URI'] . "</a>" : '');
					break;

				case "Journal article":
					echo ((($pubDetails['journaltitle'] != '') || ($pubDetails['volume'] != '') || ($pubDetails['year'] != '') || ($pubDetails['pagesRange'] != '')) ? "<br />" : '');
					echo ($pubDetails['journaltitle'] != '' ? "In: <strong>" . $pubDetails['journaltitle'] . '</strong> ' : '');
					echo ($pubDetails['volume'] != '' ? $pubDetails['volume'] . ". " : '');
					echo ($pubDetails['year'] != '' ? " (" . $pubDetails['year'] . ")" : '');
					echo ($pubDetails['pagesRange'] != '' ? ", " . __('S.', 'Abkürzung für "Seite" bei Publikationen', 'fau-cris') . " " . $pubDetails['pagesRange'] : '');
					echo ($pubDetails['DOI'] != '' ? "<br />DOI: <a href='http://dx.doi.org/" . $pubDetails['DOI'] . "' target='blank'>" . $pubDetails['DOI'] . "</a>" : '');
					echo ($pubDetails['URI'] != '' ? "<br />URL: <a href='" . $pubDetails['URI'] . "' target='blank'>" . $pubDetails['URI'] . "</a>" : '');
					break;

				case "Conference contribution":
					echo ((($pubDetails['conference'] != '') || ($pubDetails['publisher'] != '')) ? "<br />" : '');
					echo ($pubDetails['conference'] != '' ? $pubDetails['conference'] : '');
					echo ((($pubDetails['conference'] != '') && ($pubDetails['publisher'] != '')) ? ", " : '');
					echo ($pubDetails['publisher'] != '' ? $pubDetails['publisher'] : '');
					echo ((($pubDetails['city'] != '') || ($pubDetails['year'] != '')) ? "<br />" : '');
					echo ($pubDetails['city'] != '' ? "<span class=\"city\">" . $pubDetails['city'] . "</span>" : '');
					echo ($pubDetails['year'] != '' ? " (" . $pubDetails['year'] . ")" : '');
					echo ($pubDetails['DOI'] != '' ? "<br />DOI: <a href='http://dx.doi.org/" . $pubDetails['DOI'] . "' target='blank'>" . $pubDetails['DOI'] . "</a>" : '');
					echo ($pubDetails['URI'] != '' ? "<br />URL: <a href='" . $pubDetails['URI'] . "' target='blank'>" . $pubDetails['URI'] . "</a>" : '');
					break;
				case "Editorial":
					echo ((($pubDetails['city'] != '') || ($pubDetails['publisher'] != '') || ($pubDetails['year'] != '')) ? "<br />" : '');
					echo ($pubDetails['volume'] != '' ? $pubDetails['volume'] . ". " : '');
					echo ($pubDetails['city'] != '' ? "<span class=\"city\">" . $pubDetails['city'] . "</span>: " : '');
					echo ($pubDetails['publisher'] != '' ? $pubDetails['publisher'] . ", " : '');
					echo ($pubDetails['year'] != '' ? $pubDetails['year'] : '');
					echo ($pubDetails['series'] != '' ? "<br />" . $pubDetails['series'] : '');
					echo ($pubDetails['seriesNumber'] != '' ? "Bd. " . $pubDetails['seriesNumber'] : '');
					echo ($pubDetails['pagesTotal'] != '' ? "<br />" . $pubDetails['pagesTotal'] . " " . __('Seiten','fau-cris') : '');
					echo ($pubDetails['ISBN'] != '' ? "<br />ISBN: " . $pubDetails['ISBN'] : '');
					echo ($pubDetails['ISSN'] != '' ? "<br />ISSN: " . $pubDetails['ISSN'] : '');
					echo ($pubDetails['DOI'] != '' ? "<br />DOI: <a href='http://dx.doi.org/" . $pubDetails['DOI'] . "' target='blank'>" . $pubDetails['DOI'] . "</a>" : '');
					echo ($pubDetails['URI'] != '' ? "<br />URL: <a href='" . $pubDetails['URI'] . "' target='blank'>" . $pubDetails['URI'] . "</a>" : '');
					break;
				case "Thesis":
					echo "<br />Abschlussarbeit";
					echo ($pubDetails['DOI'] != '' ? "<br />DOI: <a href='http://dx.doi.org/" . $pubDetails['DOI'] . "' target='blank'>" . $pubDetails['DOI'] . "</a>" : '');
					echo ($pubDetails['URI'] != '' ? "<br />URL: <a href='" . $pubDetails['URI'] . "' target='blank'>" . $pubDetails['URI'] . "</a>" : '');
					break;
				case "Translation":
					echo ((($pubDetails['city'] != '') || ($pubDetails['publisher'] != '') || ($pubDetails['year'] != '')) ? "<br />" : '');
					echo ($pubDetails['volume'] != '' ? $pubDetails['volume'] . ". " : '');
					echo ($pubDetails['city'] != '' ? "<span class=\"city\">" . $pubDetails['city'] . "</span>: " : '');
					echo ($pubDetails['publisher'] != '' ? $pubDetails['publisher'] . ", " : '');
					echo ($pubDetails['series'] != '' ? "<br />" . $pubDetails['series'] : '');
					echo ($pubDetails['seriesNumber'] != '' ? "Bd. " . $pubDetails['seriesNumber'] : '');
					echo ($pubDetails['pagesTotal'] != '' ? "<br />" . $pubDetails['pagesTotal'] . " " . __('Seiten','fau-cris') : '');
					echo ($pubDetails['ISBN'] != '' ? "<br />ISBN: " . $pubDetails['ISBN'] : '');
					echo ($pubDetails['ISSN'] != '' ? "<br />ISSN: " . $pubDetails['ISSN'] : '');
					echo ($pubDetails['DOI'] != '' ? "<br />DOI: <a href='http://dx.doi.org/" . $pubDetails['DOI'] . "' target='blank'>" . $pubDetails['DOI'] . "</a>" : '');
					echo ($pubDetails['URI'] != '' ? "<br />URL: <a href='" . $pubDetails['URI'] . "' target='blank'>" . $pubDetails['URI'] . "</a>" : '');
					echo ($pubDetails['origTitle'] != '' ? "<br />Originaltitel: " . $pubDetails['origTitle'] : '');
					echo ($pubDetails['origLanguage'] != '' ? "<br />Originalsprache: " . $pubDetails['origLanguage'] : '');
					break;
			}
			echo "</li>";
		}
		echo "</ul>";
	}

}
