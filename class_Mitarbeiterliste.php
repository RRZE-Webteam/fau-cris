<?php

require_once("class_Dicts.php");
require_once("class_Tools.php");

class Mitarbeiterliste {

	public function __construct() {
		$options = (array) get_option('_fau_cris');
		$this->orgNr = $options['cris_org_nr'];
		$this->ignore = $options['cris_ignore'];
		$this->suchstring = 'https://cris.fau.de/ws-cached/public/infoobject/getrelated/Organisation/' . $this->orgNr . '/CARD_has_ORGA';
		$this->mitarbeiter = simplexml_load_file($this->suchstring);

		// XML -> Array
		$this->maArray = array();
		foreach ($this->mitarbeiter as $mitarbeiter) {
			$this->maID = (string)$mitarbeiter['id'];

			foreach ($mitarbeiter as $attribut){
				if ($attribut['language'] == 1) {
					$maAttribut = (string)$attribut['name'] . "_en";
				} else {
					$maAttribut = (string)$attribut['name'];
				}
				if ((string)$attribut['disposition'] == 'choicegroup' ) {
					$maDetail = (string)$attribut->additionalInfo;
				} else {
					$maDetail = (string)$attribut->data;
				}
				$this->maArray[$this->maID][$maAttribut] = $maDetail;
			}
		}
		// Array alphabetisch sortieren
		$this->maArray = Tools::record_sortByName($this->maArray);
	}


	/*
	 * Alphabetisch sortierte Mitarbeiterliste
	 */
	public function liste() {
		echo "<ul>";
		foreach ($this->maArray as $maID=>$mitarbeiter) {
			echo "<li>";
			echo "<a href='" . get_permalink() . "?id=" . $maID . "'>";
			echo strip_tags($mitarbeiter['firstName']) . " " . strip_tags($mitarbeiter['lastName']) . "</a>";
			$jobs2 = explode('&#32;-&#32;',strip_tags(substr($mitarbeiter['allFunctions'], 0, -11)));
			$strJobs = $jobs2[count($jobs2)-1];
			if ($strJobs != '') {
				echo " (";
				echo $strJobs;
				echo ")";
			}
			echo "</li>";
		}
		echo "</ul>";
	}


	/*
	 * Nach Funktionen/jobTitle gegliederte Mitarbeiterliste
	 */

	public function organigramm() {
		// Mitarbeiter-Array umstrukturieren: Funktion -> ID -> Attribute -> Wert
		$organigramm = array();
		foreach($this->maArray as $i=>$element) {
			foreach($element as $j=>$sub_element) {
				if (($j == 'allFunctions') && $sub_element != '') {
					$jobs = explode('&#32;-&#32;',substr(strip_tags($sub_element), 0, -11));
					$job = $jobs[count($jobs)-1];
					$organigramm[$job][$i] = $element;
				} elseif (($j == 'allFunctions') && !$sub_element) {
					$organigramm['Andere'][$i]= $element;
				}
			}
		}

		// Mitarbeiter-Array nach Hierarchie sortieren
		$organigramm = Tools::sort_key($organigramm, Dicts::$jobOrder);

		// Organigramm ausgeben
		foreach ($organigramm as $i=>$funktion) {
			if (!in_array($i, $this->ignore)) {
				echo "<h3>" . $i . "</h3>";
				echo "<ul>";
				foreach ($funktion as $maID=>$mitarbeiter) {
					echo "<li>";
					echo "<a href='" . get_permalink() . "?id=" . $maID . "'>";
					echo strip_tags($mitarbeiter['firstName']) . " " . strip_tags($mitarbeiter['lastName']);
					echo "</a>";
					echo "</li>";
				}
				echo "</ul>";
			}
		}
	} // Ende organigramm()

} // Ende class_Mitarbeiterliste