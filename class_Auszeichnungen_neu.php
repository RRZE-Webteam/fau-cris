<?php

require_once("class_Tools.php");
require_once("class_Awards.php");

class Auszeichnungen_neu {

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
            if (in_array($einheit, array("person", "orga", "award", "awardnameid"))) {
                $this->id = $id;
                $this->einheit = $einheit;
            } else {
                // keine Einheit angegeben -> OrgNr aus Einstellungen verwenden
                $this->id = $orgNr;
                $this->einheit = "orga";
            }
	}

	/*
	 * Ausgabe aller Auszeichnungen ohne Gliederung
	 */

	public function awardsListe($year = '', $start = '', $type = '', $awardnameid = '', $showname = 1, $showyear = 1, $display = 'list') {
		$awardArray = $this->fetch_awards($year, $start, $type, $awardnameid);

		if (!count($awardArray)) {
			$output = '<p>' . __('Es wurden leider keine Publikationen gefunden.','fau-cris') . '</p>';
			return $output;
		}

		$order = "year award";
                $formatter = new CRIS_formatter(NULL, NULL, $order, SORT_DESC);
		$res = $formatter->execute($awardArray);
                $awardList = $res[$order];

		$output = '';

		if ($display == 'gallery') {
			$output .= $this->make_gallery($awardList, $showname, $showyear, $showawardname = 1);
		} else {
			$output .= $this->make_list($awardList, $showname, $showyear, $showawardname = 1);
		}

/*		foreach ($awardList as $array_year => $awards) {
			if ($display == 'gallery') {
				$output .= $this->make_gallery($awards, $showname, $showyear, $showawardname = 1);
			} else {
				$output .= $this->make_list($awards, $showname, $showyear, $showawardname = 1);
			}
		}
*/		return $output;

	}

	/*
	 * Ausgabe aller Auszeichnungen nach Jahren gegliedert
	 */

	public function awardsNachJahr($year = '', $start = '', $type = '', $awardnameid = '', $showname = 1, $showyear = 0, $display = 'list') {
		$awardArray = $this->fetch_awards($year, $start, $type, $awardnameid);

		if (!count($awardArray)) {
			$output = '<p>' . __('Es wurden leider keine Publikationen gefunden.','fau-cris') . '</p>';
			return $output;
		}

		$formatter = new CRIS_formatter("year award", SORT_DESC, "award_preistraeger", SORT_ASC);
		$awardList = $formatter->execute($awardArray);

		$output = '';

		foreach ($awardList as $array_year => $awards) {
			if (empty($year)) {
				$output .= '<h3 class="clearfix clear">';
				$output .=  !empty($array_year) ? $array_year : __('Ohne Jahr','fau-cris');
				$output .= '</h3>';
			}
			if ($display == 'gallery') {
				$output .= $this->make_gallery($awards, $showname, $showyear, $showawardname = 1);
			} else {
				$output .= $this->make_list($awards, $showname, $showyear, $showawardname = 1);
			}
		}

		return $output;
	}

	/*
	 * Ausgabe aller Auszeichnungen nach Auszeichnungstypen gegliedert
	 */

	public function awardsNachTyp($year = '', $start = '', $type = '', $awardnameid = '', $showname = 1, $showyear = 0, $display = '') {
		$awardArray = $this->fetch_awards($year, $start, $type, $awardnameid);

		if (!count($awardArray)) {
			$output = '<p>' . __('Es wurden leider keine Publikationen gefunden.','fau-cris') . '</p>';
			return $output;
		}

		$formatter = new CRIS_formatter("type of award", SORT_DESC, "award_preistraeger", SORT_ASC);
		$awardList = $formatter->execute($awardArray);

		$output = '';

		foreach ($awardList as $array_type => $awards) {
			if (empty($type)) {
				$title = Tools::getawardTitle($array_type, get_locale());
				$output .= '<h3 class="clearfix clear">';
				$output .= $title;
				$output .= "</h3>";
			}
			if ($display == 'gallery') {
				$output .= $this->make_gallery($awards, $showname, $showyear, $showawardname = 1);
			} else {
				$output .= $this->make_list($awards, $showname, $showyear, $showawardname = 1);
			}
		}

		return $output;
	}

	/*
	 * Ausgabe einer einzelnen Auszeichnung
	 */

	public function singleAward($showname = 1, $showyear = 0, $display = 'list') {
		$ws = new CRIS_awards();

		try {
			$awardArray = $ws->by_id($this->id);
		} catch (Exception $ex) {
			return;
		}

		if (!count($awardArray)) {
			$output = '<p>' . __('Es wurden leider keine Publikationen gefunden.','fau-cris') . '</p>';
			return $output;
		}

		if ($display == 'gallery') {
			$output = $this->make_gallery($awardArray, $showname, $showyear, $showawardname = 1);
		} else {
			$output = $this->make_list($awardArray, $showname, $showyear, $showawardname = 1);
		}

		return $output;
	}

	/* =========================================================================
	 * Private Functions
	  ======================================================================== */

	/*
	* Holt Daten vom Webservice je nach definierter Einheit.
	*/

	private function fetch_awards($year = '', $start = '', $type = '', $awardnameid = '') {
		$filter = Tools::award_filter($year, $start, $type, $awardnameid);

		$ws = new CRIS_awards();
		$awardArray = array();

		try {
			if ($this->einheit === "orga") {
				$awardArray = $ws->by_orga_id($this->id, $filter);
			}
			if ($this->einheit === "person") {
				$awardArray = $ws->by_pers_id($this->id, $filter);
			}
			if ($this->einheit === "awardnameid") {
				$awardArray = $ws->by_awardtype_id($this->id, $filter);
			}
		} catch (Exception $ex) {
			$awardArray = array();
		}

		return $awardArray;
	}

	/*
	 * Ausgabe der Awards
	 */

	private function make_list($awards, $name=1, $year=1, $awardname=1) {
		$awardlist = "<ul class=\"cris-awards\">";

		foreach ($awards as $award) {
			$award = (array)$award;
			foreach ($award['attributes'] as $attribut => $v) {
					$award[$attribut] = $v;
				}
				unset($award['attributes']);


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
			$award_year = $award['year award'];

			$awardlist .= "<li>";
			if ($year == 1 && $name == 1) {
				$awardlist .= (!empty($award_preistraeger) ? $award_preistraeger : "")
					. ($awardname == 1 ? ": <strong>" . $award_name . "</strong> "
						. ((isset($organisation) && $award['type of award'] != 'Akademie-Mitgliedschaft') ? " (". $organisation . ")" : "")  : "" )
					. (!empty($award_year) ? " &ndash; " . $award_year : "");
			} elseif ($year == 1 && $name == 0) {
				$awardlist .= (!empty($award_year) ? $award_year . ": " : "")
					. "<strong>" . $award_name . "</strong>"
					. ((isset($organisation) && $award['type of award'] != 'Akademie-Mitgliedschaft') ? " (". $organisation . ")" : "");
			} elseif ($year == 0 && $name == 1) {
				$awardlist .= (!empty($award_preistraeger) ? $award_preistraeger . ": " : "")
					. "<strong>" . $award_name . "</strong>"
					. ((isset($organisation) && $award['type of award'] != 'Akademie-Mitgliedschaft') ? " (". $organisation . ")" : "");
			} else {
				$awardlist .= "<strong>" . $award_name . "</strong>"
					. ((isset($organisation) && $award['type of award'] != 'Akademie-Mitgliedschaft') ? " (". $organisation . ")" : "");
			}
			$awardlist .= "</li>";
		}

		$awardlist .= "</ul>";
		return $awardlist;
	}

	private function make_gallery($awards, $name=1, $year=1, $awardname=1) {
		$awardlist = "<ul class=\"cris-awards cris-gallery clear\">";

		foreach ($awards as $award) {
			$award = (array)$award;
			foreach ($award['attributes'] as $attribut => $v) {
				$award[$attribut] = $v;
			}
			unset($award['attributes']);

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
			$award_year = $award['year award'];
			$award_pic = self::get_pic($award['id_awar']);

			$awardlist .= "<li>";
			$awardlist .= strlen($award_pic) > 50 ? "<img src=\"" . $award_pic . "\" alt=\"Portrait " . $award_preistraeger . "\" />" : "<div class=\"noimage\">&nbsp</div>";
			$awardlist .= $name == 1 ? $award_preistraeger . "<br />" : '';
			$awardlist .= $awardname == 1 ? "<strong>" . $award_name . "</strong> "
				. ((isset($organisation) && $award['type of award'] != 'Akademie-Mitgliedschaft') ? " (". $organisation . ")" : "") . "<br />" :'';
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