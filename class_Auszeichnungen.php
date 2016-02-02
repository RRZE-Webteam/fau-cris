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

			// Bild suchen
			if ($einheit != "award" && $display == 'gallery') {
				$picString = "https://cris.fau.de/ws-cached/1.0/public/infoobject/getrelated/Award/" . $this->awardID . "/awar_has_pict";
				$picXml = Tools::XML2obj($picString);
				//var_dump($picXml['size']);
				if($picXml['size']== 0) {
					$pic = '';
				} else {
					foreach ($picXml->infoObject->attribute as $picAttribut) {
						if ($picAttribut['name'] == 'Content') {
							$pic = 'data:image/JPEG;base64,' . $picAttribut->data;
						}
					}
				}
				$this->awardArray[$this->awardID]['pic'] = $pic;
			}

		}
	}

	public function awardsListe($year = '', $start = '', $type = '', $showname = 1, $showyear = 1, $display = 'list') {
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

		// Awards sortieren
		$awardsSorted = Tools::array_msort($awards, array('Year award'=>SORT_DESC));

		// Ausgabe
		if ($display == 'gallery') {
			$output = $this->make_gallery($awardsSorted, $showname, $showyear);
		} else {
			$output = $this->make_list($awardsSorted, $showname, $showyear);
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
				$output .= '<h3>' . $array_year . '</h3>';
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
		$orderRaw = $this->options['cris_award_order'];
		foreach ($orderRaw as $awardType) {
			$order[] = CRIS_Dicts::$awardNames[$awardType]['de'];
		}

		if ($order[0] != '') {
			$awardsByType = Tools::sort_key($awardsByType, $order);
		} else {
			$awardsByType = Tools::sort_key($awardsByType, CRIS_Dicts::$awardOrder);
		}

		// Ausgabe
		foreach ($awardsByType as $array_type => $awards) {
			if (empty($year)) {
				$output .= '<h3>' . $array_type . '</h3>';
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

			if ($display == 'gallery') {
				$picString = "https://cris.fau.de/ws-cached/1.0/public/infoobject/getrelated/Award/" . $awardID . "/awar_has_pict";
				$picXml = Tools::XML2obj($picString);
				foreach ($picXml->infoObject->attribute as $picAttribut) {
					if ($picAttribut['name'] == 'Content') {
						$pic = $picAttribut->data;
					}
				}
				//print '<img src="data:image/JPEG;base64,' . $pic . '">';
				$awardArray[$awardID]['pic'] = 'data:image/JPEG;base64,' . $pic;
			}

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

	private function make_list($awards, $name=1, $year=1) {
		$awardlist = "<ul class=\"cris-awards\">";

		foreach ($awards as $award) {
			$awardlist .= "<li>";
			if ($year == 1 && $name == 1) {
				$awardlist .= $award['award_preistraeger'] . ": <strong>" . $award['award_name'] . "</strong> (". $award['award_organisation'] . ") &ndash; " . $award['Year award'];
			} elseif ($year == 1 && $name == 0) {
				$awardlist .= $award['Year award'] . ": <strong>" . $award['award_name'] . "</strong> (". $award['award_organisation'] . ")";
			} elseif ($year == 0 && $name == 1) {
				$awardlist .= $award['award_preistraeger'] . ": <strong>" . $award['award_name'] . "</strong> (". $award['award_organisation'] . ")";
			} else {
				$awardlist .= "<strong>" . $award['award_name'] . "</strong>";
			}
			$awardlist .= "</li>";
		}

		$awardlist .= "</ul>";
		return $awardlist;
	}

	private function make_gallery($awards, $name=1, $year=1, $awardname=1) {
		$awardlist = "<ul class=\"cris-awards cris-gallery clear\">";

		foreach ($awards as $award) {
			$awardlist .= "<li>";
			$awardlist .= $award['pic'] != '' ? "<img src=\"" . $award['pic'] . "\" alt=\"Portrait " . $award['award_preistraeger'] . "\" />" : "<div class=\"noimage\">&nbsp</div>";
			$awardlist .= $name == 1 ? $award['award_preistraeger'] . "<br />" : '';
			$awardlist .= $awardname == 1 ? "<strong>" . $award['award_name'] . "</strong><br />"
				. $award['award_organisation'] . "<br />" :'';
			$awardlist .= $year == 1 ? $award['Year award'] : '';

			$awardlist .= "</li>";
		}

		$awardlist .= "</ul>";
		return $awardlist;
	}

}