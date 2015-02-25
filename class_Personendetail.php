<?php

require_once("class_Publikationsliste.php");

class Personendetail {

	public function __construct($id) {
		libxml_use_internal_errors(true);
		$this->ID = $id;
		$suchPers = "https://cris.fau.de/ws-cached/public/infoobject/getrelated/Card/" . $this->ID . "/PERS_has_CARD";
		$this->xmlPers = simplexml_load_file($suchPers);
		$suchCard = "https://cris.fau.de/ws-cached/public/infoobject/get/Card/". $this->ID;

		$this->xmlCard = @simplexml_load_file($suchCard, 'SimpleXmlElement', LIBXML_NOERROR+LIBXML_NOWARNING);
		if (false === $this->xmlCard) {
			print "<p>" . __('Keine Daten gefunden.', 'fau-cris') . "</p>"
					."<p><a href=\"" . get_permalink() . "\">&rarr; " . __('Zur Mitarbeiterliste','fau-cris') . "</a></p>";
			return;
		}

		/* Erstmal keine Awards in Webservices
		$persID = $this->xmlPers->infoObject['id'];
		$this->suchAwards = "https://cris.fau.de/ws-cached/public/infoobject/getrelated/Person/". $persID . "/awar_has_pers";
		$this->xmlAwards = simplexml_load_file($this->suchAwards);*/

		$person = $this->xmlPers->infoObject->attribute;
		foreach ($person as $attribut) {
			if ($attribut['language'] == 1) {
				$persAttribut = (string)$attribut['name'] . "_en";
			} else {
				$persAttribut = (string)$attribut['name'];
			}
			if ((string)$attribut['disposition'] == 'choicegroup' ) {
				$persDetail = (string)$attribut->additionalInfo;
			} else {
				$persDetail = (string)$attribut->data;
			}
			$this->persArray[$persAttribut] = $persDetail;
		}

		$card = $this->xmlCard->attribute;
		foreach ($card as $attribut) {
			if ($attribut['language'] == 1) {
				$cardAttribut = (string)$attribut['name'] . "_en";
			} else {
				$cardAttribut = (string)$attribut['name'];
			}
			if ((string)$attribut['disposition'] == 'choicegroup' ) {
				$cardDetail = (string)$attribut->additionalInfo;
			} else {
				$cardDetail = (string)$attribut->data;
			}
			$this->cardArray[$cardAttribut] = $cardDetail;
		}
	}

	/*
	 *  Persönliche Informationen, Kontakt etc.
	 */
	public function detail() {
		if (false === $this->xmlCard) {
			return;
		}
		$options = (array) get_option('_fau_cris');

		$vorname = strip_tags($this->cardArray['firstName']);
		$nachname = strip_tags($this->cardArray['lastName']);
		$academicTitle = strip_tags($this->persArray['Academic title']);
		$email = strip_tags($this->persArray['email']);
		$email2 = strip_tags($this->cardArray['email']);
		$phone = strip_tags($this->cardArray['phone']);
		$fax = strip_tags($this->cardArray['fax']);
		$website = strip_tags($this->cardArray['cfURI']);
		//$jobTitle = $this->cardArray['jobTitle'];
		$jobTitle = explode('&#32;-&#32;',substr(strip_tags($this->cardArray['allFunctions']), 0, -11));
		$jobTitle = $jobTitle[count($jobTitle)-1];

		echo "<h2>"
			. ($academicTitle ? '<acronym title="' . Tools::getAcronym($academicTitle) . '">' . $academicTitle . "</acronym> " : '')
			. $vorname . " " . $nachname
			. "</h2>";
		echo ($jobTitle !='' ? "<p><strong>" . $jobTitle . "</strong><p>" : '');
		echo "<p>";
		echo ($email !='' ? "E-Mail: <a href=\"mailto:" . $email ."\">" . $email . '</a>' : '');
		//echo ($email2 !='' ? "<br />E-Mail (Card): " . $email2 : '');
		echo ($phone !='' ? "<br />Telefon: " . $phone : '');
		echo ($fax !='' ? "<br />Fax: " . $fax : '');
		echo ($website !='' ? "<br />Website: " . $website : '');
		echo "</p>";

/*		if ($options['cris_awards'] == '1') {
			$this->auszeichnungen();
		}
*/
		if (isset($options['cris_publications']) && $options['cris_publications'] == '1') {
			$liste = new Publikationsliste("person", $this->ID);
			$liste->pubNachJahr('klein');
		}

		echo "<br /><p><a href=\"" . get_permalink() . "\">&larr; " . __('Zur&uuml;ck zur Mitarbeiterliste','fau-cris') . "</a></p>";
	}

	/*
	 *  Auszeichnungen
	 */
	/* Erstmal keine Awards in Webservices
	 private function auszeichnungen() {

		print_r($this->suchAwards);

		$awards = $this->xmlAwards->infoObject;
		$awardArray = array();

		if(!empty($awards)) {
			echo "<h3>Auszeichnungen</h3>";
			echo "<ul>";

			foreach ($awards as $award) {
				$awardID = (string)$award['id'];

				foreach ($award as $attribut){
					if ($attribut['language'] == 1) {
						$awardAttribut = (string)$attribut['name'] . "_en";
					} else {
						$awardAttribut = (string)$attribut['name'];
					}
					if ((string)$attribut['disposition'] == 'choicegroup' ) {
						$awardDetail = (string)$attribut->additionalInfo;
					} else {
						$awardDetail = (string)$attribut->data;
					}
					$awardArray[$awardID][$awardAttribut] = $awardDetail;
				}

				$year = strip_tags($awardArray[$awardID]['Year award']);
				$awardType = strip_tags($awardArray[$awardID]['Type of award']);
				$awardName = strip_tags($awardArray[$awardID]['award_name']);
				$awardOrga = strip_tags($awardArray[$awardID]['award_organisation']);

				echo "<li>";
				echo $awardName . " (" . $year . ")";
				echo "<br />" . $awardOrga;
				echo "<br />(" . $awardType . ")";
				echo "</li>";
			}
			echo "</ul>";

		}

	}*/

}