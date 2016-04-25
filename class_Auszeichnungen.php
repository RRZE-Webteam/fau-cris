<?php

require_once("class_Tools.php");

class Auszeichnungen {
	private $options;
	public $output;

	public function __construct($einheit='', $id='', $display='list') {
		$this->options = (array) get_option('_fau_cris');
		$orgNr = $this->options['cris_org_nr'];
		$this->crisURL = "https://cris.fau.de/ws-cached/1.0/public/infoobject/";
		$this->suchstring = '';

		if((!$orgNr||$orgNr==0) && $id=='') {
            print '<p><strong>' . __('Bitte geben Sie die CRIS-ID der Organisation, Person oder Auszeichnung an.','fau-cris') . '</strong></p>';
			return;
        }

		if ($id !='' && $einheit == "person") {
			// Awards für einzelne Person
			$this->suchstring = $this->crisURL . "getrelated/Person/" . $id . "/awar_has_pers";
		} elseif ($id !='' && $einheit == "orga") {
			// Awards für Organisationseinheit (überschreibt Orgeinheit aus Einstellungen!!!)
			$this->suchstring = $this->crisURL . "getautorelated/Organisation/" . $id . "/ORGA_3_AWAR_1"; //142534
		} elseif ($id !='' && $einheit == "award") {
			$this->suchstring = $this->crisURL . 'get/Award/' . $id;
		} elseif ($id !='' && $einheit == "awardnameid") {
			$this->suchstring = $this->crisURL . "getrelated/Award%20Type/" . $id . "/awar_has_awat"; //222
		} else {
			// keine Einheit angegeben -> OrgNr aus Einstellungen verwenden
			$this->suchstring = $this->crisURL . "getautorelated/Organisation/" . $orgNr . "/ORGA_3_AWAR_1"; //142534
		}

		$xml = Tools::XML2obj($this->suchstring);

		if (!$xml) {
			return;
		}

		$this->awardObject = $xml; // nötig für SingleAward

		// XML -> Array
		$this->awardArray = array();

		foreach ($xml as $award) {
			$this->awardID = (string) $award['id'];

			foreach ($award as $attribut) {
				if ($attribut['language'] == 1) {
					$awardAttribut = (string) $attribut['name'] . "_en";
				} else {
					$awardAttribut = (string) $attribut['name'];
				}
				if ((string) $attribut['disposition'] == 'choicegroup') {
					$awardDetail = (string) $attribut->additionalInfo;
				} else {
					$awardDetail = (string) $attribut->data;
				}
				if ($awardAttribut != '') {		// verhindert seltsame Einträge mit leerem Key und Value
					$this->awardArray[$this->awardID][$awardAttribut] = $awardDetail;
				}

			}
			//print $this->awardID . '<br />';
		}
	}

	public function awardsListe($year = '', $start = '', $type = '', $showname = 1, $showyear = 1, $display = 'list', $awardnameid='') {
		if (!isset($this->awardArray) || !is_array($this->awardArray)) return;

		$output = '';

		// Awards filtern
		if ($year !='' || $start !='' || $type != '') {
			if ($type != '') {
				$type = CRIS_Dicts::$awardNames[$type]['de'];
			}
			$awards = Tools::filter_awards($this->awardArray, $year, $start, $type);
		} else {
			$awards = $this->awardArray;
		}

		if (empty($awards)) {
			$output .= '<p>' . __('Es wurden leider keine Auszeichnungen gefunden.','fau-cris') . '</p>';
			return $output;
		}

		// Awards sortieren
		$awardsSorted = Tools::array_msort($awards, array('Year award'=>SORT_DESC));

		// Ausgabe
		$showawardname = 1;
		if ($awardnameid != '') {
			$title = reset($awardsSorted)['award_name'];
			$output .= '<h3 class="clearfix clear">'. $title . '</h3>';
			$showawardname = 0;
		}
		if ($display == 'gallery') {
			$output .= $this->make_gallery($awardsSorted, $showname, $showyear, $showawardname);
		} else {
			$output .= $this->make_list($awardsSorted, $showname, $showyear, $showawardname);
		}

		return $output;

	}

	public function awardsNachJahr($year = '', $start = '', $type = '', $showname = 1, $showyear = 0, $display = 'list') {
		if (!isset($this->awardArray) || !is_array($this->awardArray)) return;

		$awardsByYear = array();
		$output = '';

		// Awards filtern
		if ($year !='' || $start !='' || $type != '') {
			if ($type != '') {
				$type = CRIS_Dicts::$awardNames[$type]['de'];
			}
			$awards = Tools::filter_awards($this->awardArray, $year, $start, $type);
		} else {
			$awards = $this->awardArray;
		}

		if (empty($awards)) {
			$output .= '<p>' . __('Es wurden leider keine Auszeichnungen gefunden.','fau-cris') . '</p>';
			return $output;
		}

		// Awards gliedern
		foreach ($awards as $i => $element) {
			foreach ($element as $j => $sub_element) {
				if (($j == 'Year award')) {
					$awardsByYear[$sub_element][$i] = $element;
				}
			}
		}

		// Awards sortieren
		$keys = array_keys($awardsByYear);
		rsort($keys);
		$awardsByYear = Tools::sort_key($awardsByYear, $keys);

		// Ausgabe
		foreach ($awardsByYear as $array_year => $awards) {
			if (empty($year)) {
				$output .= '<h3 class="clearfix clear">' . $array_year . '</h3>';
			}
			if ($display == 'gallery') {
				$output .= $this->make_gallery($awards, $showname, $showyear);
			} else {
				$output .= $this->make_list($awards, $showname, $showyear);
			}
		}

		return $output;

	}

	public function awardsNachTyp($year = '', $start = '', $type = '', $showname = 1, $showyear = 0, $display = 'list') {
		if (!isset($this->awardArray) || !is_array($this->awardArray)) return;

		$awardsByType = array();
		$output = '';

		// Awards filtern
		if ($year !='' || $start !='' || $type != '') {
			if ($type != '') {
				$type = CRIS_Dicts::$awardNames[$type]['de'];
			}
			$awards = Tools::filter_awards($this->awardArray, $year, $start, $type);
		} else {
			$awards = $this->awardArray;
		}

		if (empty($awards)) {
			$output .= '<p>' . __('Es wurden leider keine Auszeichnungen gefunden.','fau-cris') . '</p>';
			return $output;
		}

		// Awards gliedern
		foreach ($awards as $i => $element) {
			foreach ($element as $j => $sub_element) {
				if (($j == 'Type of award')) {
					$awardsByType[$sub_element][$i] = $element;
				}
			}
		}

		// Awards sortieren
		$order = $this->options['cris_award_order'];
		if ($order[0] != ''  && array_key_exists($order[0],CRIS_Dicts::$awardNames)) {
			foreach ($order as $awardType) {
				$order[] = CRIS_Dicts::$awardNames[$awardType]['de'];
			}
			$awardsByType = Tools::sort_key($awardsByType, $order);
		} else {
			$awardsByType = Tools::sort_key($awardsByType, CRIS_Dicts::$awardOrder);
		}

		// Ausgabe
		foreach ($awardsByType as $array_type => $awards) {
			if (empty($year)) {
				$title = Tools::getawardTitle($array_type, get_locale());
				$output .= '<h3 class="clearfix clear">' . $title . '</h3>';
			}
			// innerhalb des Awardtyps nach Jahr abwärts sortieren
			$awards = Tools::array_msort($awards, array('Year award' => SORT_DESC));

			if ($display == 'gallery') {
				$output .= $this->make_gallery($awards, $showname, $showyear);
			} else {
				$output .= $this->make_list($awards, $showname, $showyear);
			}
		}

		return $output;
	}

	public function singleAward($showname = 1, $showyear = 0, $display = 'list') {
		$award = $this->awardObject;
		$awardArray = array();
		foreach ($award as $attribut) {
			$awardID = (string) $award['id'];

			if ($attribut['language'] == 1) {
				$awardAttribut = (string) $attribut['name'] . "_en";
			} else {
				$awardAttribut = (string) $attribut['name'];
			}
			if ((string) $attribut['disposition'] == 'choicegroup') {
				$awardDetail = (string) $attribut->additionalInfo;
			} else {
				$awardDetail = (string) $attribut->data;
			}
			$awardArray[$awardID][$awardAttribut] = $awardDetail;
		}

		if (!isset($awardArray) || !is_array($awardArray)) return;

		if ($display == 'gallery') {
			$output = $this->make_gallery($awardArray, $showname, $showyear);
		} else {
			$output = $this->make_list($awardArray, $showname, $showyear);
		}

		return $output;

	}


	/* =========================================================================
	 * Private Functions
	  ======================================================================== */

	/*
	 * Ausgabe der Awards
	 */

	private function make_list($awards, $name=1, $year=1, $awardname=1) {
		$awardlist = "<ul class=\"cris-awards\">";

		foreach ($awards as $award) {

			$award_preistraeger = $award['award_preistraeger'];
			if(!empty($award['award_name'])) {
				$award_name = $award['award_name'];
			} elseif (!empty($award['award_name_manual'])) {
				$award_name = $award['award_name_manual'];
			}
			if(!empty($award['award_organisation'])) {
				$organisation = $award['award_organisation'];
			} elseif (!empty($award['award_organisation_manual'])) {
				$organisation = $award['award_organisation_manual'];
			}
			$award_year = $award['Year award'];

			$awardlist .= "<li>";
			if ($year == 1 && $name == 1) {
				$awardlist .= (!empty($award_preistraeger) ? $award_preistraeger : "")
					. ($awardname == 1 ? ": <strong>" . $award_name . "</strong> "
						. ((isset($organisation) && $award['Type of award'] != 'Akademie-Mitgliedschaft') ? " (". $organisation . ")" : "")  : "" )
					. (!empty($award_year) ? " &ndash; " . $award_year : "");
			} elseif ($year == 1 && $name == 0) {
				$awardlist .= (!empty($award_year) ? $award_year . ": " : "")
					. "<strong>" . $award_name . "</strong>"
					. ((isset($organisation) && $award['Type of award'] != 'Akademie-Mitgliedschaft') ? " (". $organisation . ")" : "");
			} elseif ($year == 0 && $name == 1) {
				$awardlist .= (!empty($award_preistraeger) ? $award_preistraeger . ": " : "")
					. "<strong>" . $award_name . "</strong>"
					. ((isset($organisation) && $award['Type of award'] != 'Akademie-Mitgliedschaft') ? " (". $organisation . ")" : "");
			} else {
				$awardlist .= "<strong>" . $award_name . "</strong>"
					. ((isset($organisation) && $award['Type of award'] != 'Akademie-Mitgliedschaft') ? " (". $organisation . ")" : "");
			}
			$awardlist .= "</li>";
		}

		$awardlist .= "</ul>";
		return $awardlist;
	}

	private function make_gallery($awards, $name=1, $year=1, $awardname=1) {
		$awardlist = "<ul class=\"cris-awards cris-gallery clear\">";

		foreach ($awards as $award) {
			$award_preistraeger = $award['award_preistraeger'];
			if(!empty($award['award_name'])) {
				$award_name = $award['award_name'];
			} elseif (!empty($award['award_name_manual'])) {
				$award_name = $award['award_name_manual'];
			}
			if(!empty($award['award_organisation'])) {
				$organisation = $award['award_organisation'];
			} elseif (!empty($award['award_organisation_manual'])) {
				$organisation = $award['award_organisation_manual'];
			}
			$award_year = $award['Year award'];
			$award_pic = self::get_pic($award['ID_AWAR']);

			$awardlist .= "<li>";
			$awardlist .= strlen($award_pic) > 50 ? "<img src=\"" . $award_pic . "\" alt=\"Portrait " . $award_preistraeger . "\" />" : "<div class=\"noimage\">&nbsp</div>";
			$awardlist .= $name == 1 ? $award_preistraeger . "<br />" : '';
			$awardlist .= $awardname == 1 ? "<strong>" . $award_name . "</strong> "
				. ((isset($organisation) && $award['Type of award'] != 'Akademie-Mitgliedschaft') ? " (". $organisation . ")" : "") . "<br />" :'';
			$awardlist .= ($year == 1 && !empty($award_year)) ? $award_year : '';

			$awardlist .= "</li>";
		}

		$awardlist .= "</ul>";
		return $awardlist;
	}

	private function get_pic($award) {
		$pic = '';

		$picString = "https://cris.fau.de/ws-cached/1.0/public/infoobject/getrelated/Award/" . $award . "/awar_has_pict";
		$picXml = Tools::XML2obj($picString);

		if ($picXml['size'] != 0) {
			foreach ($picXml->infoObject->attribute as $picAttribut) {
				if ($picAttribut['name'] == 'png180') {
					$pic = 'data:image/PNG;base64,' . $picAttribut->data;
				}
			}
		}
		return $pic;
	}

}