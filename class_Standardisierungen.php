<?php

require_once("class_Tools.php");
require_once("class_Webservice.php");
require_once("class_Filter.php");
require_once("class_Formatter.php");

class Standardisierungen {
    private $options;
    public $output;

    public function __construct($einheit = '', $id = '', $page_lang = 'de', $sc_lang = 'de') {
	    $this->options = (array) FAU_CRIS::get_options();
	    $this->cms = 'wp';
        $this->pathPersonenseiteUnivis = '/person/';
        $this->orgNr = $this->options['cris_org_nr'];
        $this->suchstring = '';
        $this->univis = NULL;

        //$this->order = $this->options['cris_standardization_order'];
        $this->cris_standardization_link = isset($this->options['cris_standardization_link']) ? $this->options['cris_standardization_link'] : 'none';
        if ((!$this->orgNr || $this->orgNr == 0) && $id == '') {
            print '<p><strong>' . __('Bitte geben Sie die CRIS-ID der Organisation, Person oder Forschungsaktivität an.', 'fau-cris') . '</strong></p>';
        }
        if (in_array($einheit, array("person", "orga", "standardization"))) {
            $this->id = $id;
            $this->einheit = $einheit;
        } else {
            // keine Einheit angegeben -> OrgNr aus Einstellungen verwenden
            $this->id = $this->orgNr;
            $this->einheit = "orga";
        }
        $this->page_lang = $page_lang;
	    $this->sc_lang = $sc_lang;
	    $this->langdiv_open = '<div class="cris">';
	    $this->langdiv_close = '</div>';
	    if ($sc_lang != $this->page_lang) {
		    $this->langdiv_open = '<div class="cris" lang="' . $sc_lang . '">';
	    }
    }

    /*
     * Ausgabe einer einzelnen Standardisierung
     */

    public function singleStandardization($hide = array(), $quotation = '') {
        $ws = new CRIS_standardizations();

        try {
            $standardizationArray = $ws->by_id($this->id);
        } catch (Exception $ex) {
            return;
        }

        if (!count($standardizationArray)) {
            $output = '<p>' . __('Es wurde leider kein Eintrag gefunden.', 'fau-cris') . '</p>';
            return $output;
        }

        $output = $this->make_single($standardizationArray, $hide, $quotation);
        
        return $this->langdiv_open . $output . $this->langdiv_close;
    }

	public function customStandardization($content = '', $param = array()) {
    	if ($param['entity'] == 'standardization') {
			$ws = new CRIS_standardizations();
			//var_dump($ws);
			try {
				$standardizationArray = $ws->by_id($this->id);
			} catch (Exception $ex) {
				return;
			}
		} else {
			$constructionYearStart = ( isset( $param['constructionyearstart'] ) && $param['constructionyearstart'] != '' ) ? $param['constructionyearstart'] : '';
			$constructionYearEnd   = ( isset( $param['constructionyearend'] ) && $param['constructionyearend'] != '' ) ? $param['constructionyearend'] : '';
			$constructionYear      = ( isset( $param['constructionyear'] ) && $param['constructionyear'] != '' ) ? $param['constructionyear'] : '';
			//$limit = (isset($param['limit']) && $param['limit'] != '') ? $param['limit'] : '';
			$manufacturer = ( isset( $param['manufacturer'] ) && $param['manufacturer'] != '' ) ? $param['manufacturer'] : '';
			$location     = ( isset( $param['location'] ) && $param['location'] != '' ) ? $param['location'] : '';
			$hide         = ( isset( $param['hide'] ) && ! empty( $param['hide'] ) ) ? $param['hide'] : array();

			$standardizationArray = $this->fetch_standardizations( $manufacturer, $location, $constructionYear, $constructionYearStart, $constructionYearEnd );
		}

		if (!count($standardizationArray)) {
			$output = '<p>' . __('Es wurden leider kein Eintrag gefunden.', 'fau-cris') . '</p>';
			return $output;
		}

		// sortiere nach Erscheinungsdatum
		$order = "cfname";
		$formatter = new CRIS_formatter(NULL, NULL, $order, SORT_ASC);
		$res = $formatter->execute($standardizationArray);
		$standardizationList = $res[$order];

		$output =  $this->make_custom($standardizationList, $content, $param);

		return $this->langdiv_open . $output . $this->langdiv_close;
	}

	public function standardizationListe($param = array()) {
		$constructionYearStart = (isset($param['constructionyearstart']) && $param['constructionyearstart'] != '') ? $param['constructionyearstart'] : '';
        $constructionYearEnd = (isset($param['constructionyearend']) && $param['constructionyearend'] != '') ? $param['constructionyearend'] : '';
        $constructionYear = (isset($param['constructionyear']) && $param['constructionyear'] != '') ? $param['constructionyear'] : '';
        //$limit = (isset($param['limit']) && $param['limit'] != '') ? $param['limit'] : '';
        $manufacturer = (isset($param['manufacturer']) && $param['manufacturer'] != '') ? $param['manufacturer'] : '';
        $location = (isset($param['location']) && $param['location'] != '') ? $param['location'] : '';
        $hide = (isset($param['hide']) && !empty($param['hide'])) ? $param['hide'] : array();

        $standardizationArray = $this->fetch_standardizations($manufacturer, $location, $constructionYear, $constructionYearStart, $constructionYearEnd);

        if (!count($standardizationArray)) {
            $output = '<p>' . __('Es wurden leider kein Eintrag gefunden.', 'fau-cris') . '</p>';
            return $output;
        }

        // sortiere nach Erscheinungsdatum
        $order = "cfname";
        $formatter = new CRIS_formatter(NULL, NULL, $order, SORT_ASC);
        $res = $formatter->execute($standardizationArray);
        $standardizationList = $res[$order];

        $output =  $this->make_list($standardizationList, $hide);

		return $this->langdiv_open . $output . $this->langdiv_close;
    }

    public function standardizationNachTyp($parameter) {}

    public function standardizationNachJahr($parameter) {}

    private function make_list($standardizations, $hide = array()) {
    	$standardizationList = '';
        $standardizationList .= "<ul class=\"cris-standardization\">";
        foreach($standardizations as $standardization) {
            $standardization = (array) $standardization;
            foreach ($standardization['attributes'] as $attribut => $v) {
                $standardization[$attribut] = $v;
            }
            unset($standardization['attributes']);

            switch ($this->sc_lang) {
                case 'en':
                    $name = ($standardization['cfname_en'] != '') ? $standardization['cfname_en'] : $standardization['cfname'];
                    break;
                case 'de':
                default:
                    $name = ($standardization['cfname'] != '') ? $standardization['cfname'] : $standardization['cfname_en'];
                    break;
            }
            $name = htmlentities($name, ENT_QUOTES);

            $manufacturer = null;
            $model = null;
            $constructionYear = null;
            $location = null;
            if ($standardization['hersteller'] != '' && !in_array('manufacturer', $hide)) {
                $manufacturer = $standardization['hersteller'];
            }
            if ($standardization['modell'] != '' && !in_array('model', $hide)) {
                $model = $standardization['modell'];
            }
            if ($standardization['baujahr'] != '' && !in_array('constructionYear', $hide)) {
                $constructionYear = $standardization['baujahr'];
            }
            if ($standardization['standort'] !='' && !in_array('location', $hide)) {
                $location = $standardization['standort'];
            }
            //var_dump($manufacturer);
            $standardizationList .= "<li>";
            $standardizationList .= "<span class=\"standardization-name\">" . $name . "</span>";
            if ($manufacturer) {
                $standardizationList .= '<br />' . $manufacturer;
            }
            if ($model) {
                if ($manufacturer) {
                    $standardizationList .= ': ';
                } else {
                    $standardizationList .= '<br />';
                }
                $standardizationList .= $model;
            }
            if ($constructionYear) {
                $standardizationList .= ' (' . __('Bj.', 'fau-cris') . ' ' . $constructionYear . ')';
            }
            if ($location) {
                $standardizationList .= '<br />' . __('Standort', 'fau-cris') . ': '. $location;
            }
            $standardizationList .= "</li>";
        }
        $standardizationList .= "</ul>";

        return $standardizationList;
    }

    private function make_single($standardizations, $hide = array(), $quotation = '', $image_align = 'alignright') {
        $standardizationList = '';
        $standardizationList .= "<div class=\"cris-standardization\">";
        foreach($standardizations as $standardization) {
            $standardization = (array) $standardization;
            foreach ($standardization['attributes'] as $attribut => $v) {
                $standardization[$attribut] = $v;
            }
            unset($standardization['attributes']);
/*print "<pre>";
var_dump($standardization);
print "</pre>";*/
            $id = $standardization['ID'];
            $name = htmlentities($standardization['title'], ENT_QUOTES);
	        $description = str_replace(["\n", "\t", "\r"], '', $standardization['abstract']);
	        $description = strip_tags($description, '<br><a><sup><sub><ul><ol><li><b><p><i><strong><em>');
            $documentNumber = $standardization['document_number'];
            $url = $standardization['uri'];
            $contributionTo = $standardization['document_contribution'];
            $acceptedIn = $standardization['document_final'];
            $committeeOrganization = $standardization['ws_organisation'];
            $committeeGroup = $standardization['group'];
            $meetingStart = $standardization['venue_start'];
            $meetingEnd = $standardization['venue_end'];
            $meetingVenue = $standardization['ws_location'];
            $meetingHost = $standardization['host'];
            $type = Tools::getName('standardizations', $standardization['subtype'], $this->sc_lang);
            $author = explode("|", $standardization['exportnames']);
            $authorIDs = explode(",", $standardization['persid']);
            $authorArray = array();
            foreach ($authorIDs as $i => $key) {
                $nameparts = explode(":", $author[$i]);
                $authorArray[] = array(
                    'id' => $key,
                    'lastname' => $nameparts[0],
                    'firstname' => array_key_exists(1, $nameparts) ? $nameparts[1] : '');
            }
            $authorList = array();
            foreach ($authorArray as $v) {
                $authorList[] = Tools::get_person_link($v['id'], $v['firstname'], $v['lastname'], $this->cris_standardization_link, $this->cms, $this->pathPersonenseiteUnivis, $this->univis, 0);
            }
            $authorHtml = implode(", ", $authorList);

	        if (!in_array('name', (array)$hide)) {
		        $standardizationList .= "<h3>" . $name . "</h3>";
	        }

            if ($type && !in_array('type', (array)$hide)) {
                $standardizationList .= "<p>" . $type . '</p>';
            }

            if ($description && !in_array('description', (array)$hide)) {
                $standardizationList .= "<div class=\"standardization-description\">" . $description . '</div>';
            }
            if (!in_array('details', $hide)) {
                if (!in_array('documentNumber', (array)$hide) && !empty($documentNumber)) {
                    $standardizationList .= "<strong>" . __('Nummer des Dokuments', 'fau-cris') . ': </strong>' . $documentNumber;
                }
                if (!in_array('url', (array)$hide) && !empty($url)) {
                    $standardizationList .= "<br /><strong>" . __('URL', 'fau-cris') . ': </strong>' . $url;
                }
                if (!in_array('contributionTo', (array)$hide) && !empty($contributionTo)) {
                    $standardizationList .= "<br /><strong>" . __('Beitrag zum Dokument (Nummer)', 'fau-cris') . ': </strong>' . $contributionTo;
                }
                if (!in_array('acceptedIn', (array)$hide) && !empty($acceptedIn)) {
                    $standardizationList .= "<br /><strong>" . __('Übernahme in öffentliches Dokument (Nummer)', 'fau-cris') . ': </strong>' . $acceptedIn;
                }
                if (!in_array('authors', (array)$hide) && !empty($authorHtml)) {
                    $standardizationList .= "<br /><strong>" . __('Autor/-innen', 'fau-cris') . ': </strong>' . $authorHtml;
                }
            }
            if (!in_array('committee', $hide)) {
                $standardizationList .= '<h4>' . __('Gremium') . '</h4>';
                if (!in_array('organization', (array)$hide) && !empty($committeeOrganization)) {
                    $standardizationList .= "<strong>" . __('Organisation', 'fau-cris') . ': </strong>' . $committeeOrganization;
                }
                if (!in_array('group', (array)$hide) && !empty($url)) {
                    $standardizationList .= "<br /><strong>" . __('Arbeitsgruppe', 'fau-cris') . ': </strong>' . $committeeGroup;
                }
            }
            if (!in_array('meeting', $hide)) {
                $standardizationList .= '<h4>' . __('Meeting') . '</h4>';
                if (!in_array('date', (array)$hide) && (!empty($meetingStart) || !empty($meetingEnd))) {
                    $startDate = date_i18n(get_option('date_format') , strtotime($meetingStart));
                    $endDate = date_i18n(get_option('date_format') , strtotime($meetingEnd));
                    $standardizationList .= "<br /><strong>" . __('Datum', 'fau-cris') . ': </strong>';
                    if (!empty($meetingStart) && !empty($meetingStart)) {
                        $standardizationList .= $startDate . ' &mdash; ' . $endDate;
                    } elseif (empty($meetingStart) xor empty($meetingStart)) {
                        $standardizationList .= $startDate . $endDate;
                    } elseif ($meetingStart == $meetingStart) {
                        $standardizationList .= $startDate;
                    }
                }
                if (!in_array('host', (array)$hide) && !empty($meetingHost)) {
                    $standardizationList .= "<br /><strong>" . __('Veranstalter', 'fau-cris') . ': </strong>' . $meetingHost;
                }
                if (!in_array('venue', (array)$hide) && !empty($meetingVenue)) {
                    $standardizationList .= "<br /><strong>" . __('Ort', 'fau-cris') . ': </strong>' . $meetingVenue;
                }
            }
            
            $standardizationList .= "</div>";
        }

        $standardizationList .= "</div>";
        return $standardizationList;

    }

	private function make_custom($standardizations, $custom_text = '', $param = array()) {

		switch ($param['display']) {
			case 'accordion':
				$tag_open = '[collapsibles expand-all-link="true"]';
				$tag_close= '[/collapsibles]';
				$item_open = '[collapse title="%1s" color="%2s" name="%3s"]';
				$item_close = '[/collapse]';
				break;
			case 'no-list':
				$tag_open = '<div class="cris-standardizations">';
				$tag_close= '</div>';
				$item_open = '<div class="cris-standardization">';
				$item_close = '</div>';
				break;
			case 'list':
			default:
				$tag_open = '<ul class="cris-standardizations">';
				$tag_close= '</ul>';
				$item_open = '<li>';
				$item_close = '</li>';
		}

		$standardizationList = $tag_open;

		foreach ($standardizations as $standardization) {

			$standardization = (array) $standardization;
			foreach ($standardization['attributes'] as $attribut => $v) {
				$standardization[$attribut] = $v;
			}
			unset($standardization['attributes']);

			$id = $standardization['ID'];
			switch ($this->sc_lang) {
				case 'en':
					$name = ($standardization['cfname_en'] != '') ? $standardization['cfname_en'] : $standardization['cfname'];
					$description = ($standardization['description_en'] != '') ? $standardization['description_en'] : $standardization['description'];
					break;
				case 'de':
				default:
					$name = ($standardization['cfname'] != '') ? $standardization['cfname'] : $standardization['cfname_en'];
					$description = ($standardization['description'] != '') ? $standardization['description'] : $standardization['description_en'];
					break;
			}
			$description = str_replace(["\n", "\t", "\r"], '', $description);
			$standardization_details['#name#'] =  htmlentities($name, ENT_QUOTES);
			$standardization_details['#description#'] = "<div class=\"standardization-description\">" . strip_tags($description, '<br><a><sup><sub><ul><ol><li><b><p><i><strong><em>') . "</div>";
			$standardization_details['#manufacturer#']  = $standardization['hersteller'];
			$standardization_details['#model#'] = $standardization['modell'];
			$standardization_details['#constructionYear#'] = $standardization['baujahr'];
			$standardization_details['#location#'] = $standardization['standort'];
			$standardization_details['#url#'] = $standardization['url'];
			$standardization_details['#year#'] = $standardization['year'];

			if (strpos($custom_text, '#image') !== false) {
				$imgs = self::get_standardization_images($id);

				$standardization_details['#image1#'] = '';
				if (count($imgs)) {
					$i = 1;
					foreach($imgs as $img) {
						if (isset($img['png180']) && mb_strlen($img['png180']) > 30) {
							$img_description = ($img['desc'] != '' ? "<p class=\"wp-caption-text\">" . $img['desc'] . "</p>" : '');
							$standardization_details['#image'.$i.'#'] = "<div class='cris-image wp-caption " . $param['image_align'] . "'><img alt=\"". $standardization_details['#name#'] ."\" src=\"" . $img['png180'] . "\"><p class=\"wp-caption-text\">" . $img_description . "</p>";
							$standardization_details['#image'.$i.'#'] .= "</div>";
						}
						$i++;
					}
				}
				$standardization_details['#image#'] = $standardization_details['#image1#'];
			}

			if (strpos($custom_text, '#funding#') !== false) {
				$standardization_details['#funding#'] = '-/-';
				$funding = $this->get_standardization_funding($id);
				if ($funding)
					$standardization_details['#funding#'] = implode(', ', $funding);
			}

			if (strpos($custom_text, '#fields#') !== false) {
				$standardization_details['#fields#'] = '-/-';
				$fields = $this->get_standardization_fields($id);
				if ($fields) {
					$standardization_details['#fields#'] = "<ul>";
					foreach ($fields as $_k => $field) {
						switch ($this->sc_lang) {
							case 'en':
								if (!empty($field['cfname_en'])) {
									$standardization_details['#fields#'] .= sprintf('<li><a href="https://cris.fau.de/converis/portal/Forschungsbereich/%s?lang=en_GB">%s</a></li>', $_k, $field['cfname_en']);
								} else {
									$standardization_details['#fields#'] .= sprintf('<li><a href="https://cris.fau.de/converis/portal/Forschungsbereich/%s?lang=en_GB">%s</a></li>', $_k, $field['cfname']);
								}
								break;
							case 'de':
							default:
								if (!empty($field['cfname'])) {
									$standardization_details['#fields#'] .= sprintf('<li><a href="https://cris.fau.de/converis/portal/Forschungsbereich/%s">%s</a></li>', $_k, $field['cfname']);
								} else {
									$standardization_details['#fields#'] .= sprintf('<li><a href="https://cris.fau.de/converis/portal/Forschungsbereich/%s">%s</a></li>', $_k, $field['cfname_en']);
								}
								break;
						}
					}
					$standardization_details['#fields#'] .= "</ul>";
				}
			}

			if (strpos($custom_text, '#projects#') !== false) {
				$standardization_details['#projects#'] = '-/-';
				$projects = $this->get_standardization_projects($id);
				if ($projects) {
					$standardization_details['#projects#'] = "<ul>";
					foreach ($projects as $_k => $project) {
						switch ($this->sc_lang) {
							case 'en':
								if (!empty($project['cfTitle_en'])) {
									$standardization_details['#projects#'] .= sprintf('<li><a href="https://cris.fau.de/converis/portal/Project/%s?lang=en_GB">%s</a></li>', $_k, $project['cfTitle_en']);
								} else {
									$standardization_details['#projects#'] .= sprintf('<li><a href="https://cris.fau.de/converis/portal/Project/%s?lang=en_GB">%s</a></li>', $_k, $project['cfTitle']);
								}
								break;
							case 'de':
							default:
								if (!empty($project['cfTitle'])) {
									$standardization_details['#projects#'] .= sprintf('<li><a href="https://cris.fau.de/converis/portal/Project/%s">%s</a></li>', $_k, $project['cfTitle']);
								} else {
									$standardization_details['#projects#'] .= sprintf('<li><a href="https://cris.fau.de/converis/portal/Project/%s">%s</a></li>', $_k, $project['cfTitle_en']);
								}
								break;
						}
					}
					$standardization_details['#projects#'] .= "</ul>";
				}
			}

			if (strpos($custom_text, '#publications#') !== false) {
				$standardization_details['#publications#'] = '-/-';
				$publications = $this->get_standardization_publications($id, $param['quotation']);
				if ($publications)
					$standardization_details['#publications#'] = $publications;
			}

			if ($param['display'] == 'accordion') {
				$item_open = sprintf($item_open, $param['accordion_title'],$param['accordion_color'], sanitize_title($standardization_details['#name#']));
			}

			$standardizationList .= strtr($item_open . $custom_text . $item_close, $standardization_details);
		}

		$standardizationList .= $tag_close;

		return do_shortcode($standardizationList);
	}

    private function get_standardization_publications($standardization = NULL, $quotation = '') {
        return false;
        require_once('class_Publikationen.php');
        $liste = new Publikationen('standardization', $standardization);
        return $liste->standardizationPub($standardization, $quotation);
    }

    private function get_standardization_funding($standardization = NULL) {
        $funding = array();
        $fundingString = CRIS_Dicts::$base_uri . "getrelated/standardization/" . $standardization . "/EQUI_has_FUND";
        $fundingXml = Tools::XML2obj($fundingString);
        if ($fundingXml['size'] != 0) {
            foreach ($fundingXml->infoObject as $fund) {
                $_v = (string) $fund['id'];
                foreach ($fund->attribute as $fundAttribut) {
                    if ($fundAttribut['name'] == 'Name') {
                        $funding[$_v] = (string) $fundAttribut->data;
                    }
                }
            }
        }
        return $funding;
    }

    private function get_standardization_fields($standardization) {
        $fields = array();
        $fieldsString = CRIS_Dicts::$base_uri . "getrelated/standardization/" . $standardization . "/FOBE_has_EQUI";
        $fieldsXml = Tools::XML2obj($fieldsString);
        if ($fieldsXml['size'] != 0) {
            foreach ($fieldsXml->infoObject as $field) {
                $_v = (string) $field['id'];
                foreach ($field->attribute as $fieldAttribut) {
                    if ($fieldAttribut['language'] == '1') {
                        $fields[$_v][(string) $fieldAttribut['name'].'_en'] = (string) $fieldAttribut->data;
                    } else {
                        $fields[$_v][(string) $fieldAttribut['name']] = (string) $fieldAttribut->data;
                    }
                }
            }
        }
        return $fields;
    }

    private function get_standardization_projects($standardization) {
        $projects = array();
        $projectsString = CRIS_Dicts::$base_uri . "getrelated/standardization/" . $standardization . "/EQUI_has_PROJ";
        $projectsXml = Tools::XML2obj($projectsString);
        if ($projectsXml['size'] != 0) {
            foreach ($projectsXml->infoObject as $project) {
                $_v = (string) $project['id'];
                foreach ($project->attribute as $projectAttribut) {
                    if ($projectAttribut['language'] == '1') {
                        $projects[$_v][(string) $projectAttribut['name'].'_en'] = (string) $projectAttribut->data;
                    } else {
                        $projects[$_v][(string) $projectAttribut['name']] = (string) $projectAttribut->data;
                    }
                }
            }
        }
        return $projects;
    }

    /*
     * Holt Daten vom Webservice je nach definierter Einheit.
     */

    private function fetch_standardizations($manufacturer = '', $location = '', $constructionYear = '', $constructionYearStart = '', $constructionYearEnd = '') {

        $filter = Tools::standardization_filter($manufacturer, $location, $constructionYear, $constructionYearStart, $constructionYearEnd);

        $ws = new CRIS_standardizations();
        $standardizationArray = array();

        try {
            if ($this->einheit === "orga") {
                $standardizationArray = $ws->by_orga_id($this->id, $filter);
            }
        } catch (Exception $ex) {
            $standardizationArray = array();
        }
        return $standardizationArray;
    }
}

class CRIS_standardizations extends CRIS_webservice {
    /*
     * actients/grants requests
     */

    public function by_orga_id($orgaID = null, &$filter = null) {
        if ($orgaID === null || $orgaID === "0")
            throw new Exception('Please supply valid organisation ID');

        if (!is_array($orgaID))
            $orgaID = array($orgaID);

        $requests = array();
        foreach ($orgaID as $_o) {
            $requests[] = sprintf("getrelated/Organisation/%d/standardization_has_orga", $_o);
        }
        return $this->retrieve($requests, $filter);
    }

    public function by_id($awarID = null) {
        if ($awarID === null || $awarID === "0")
            throw new Exception('Please supply valid standardization ID');

        if (!is_array($awarID))
            $awarID = array($awarID);

        $requests = array();
        foreach ($awarID as $_p) {
            $requests[] = sprintf('get/standardization/%d', $_p);
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

        $standardizations = array();

        foreach ($data as $_d) {
            foreach ($_d as $standardization) {
                $a = new CRIS_standardization($standardization);
                if ($a->ID) {
                    if (!empty($a->attributes['date'])) {
                        $a->attributes['year'] = mb_substr($a->attributes['date'], 0, 4);
                        $a->attributes['sortdate'] = $a->attributes['date'];
                    } elseif (!empty($a->attributes['start date'])) {
                        $a->attributes['year'] = mb_substr($a->attributes['start date'], 0, 4);
                        $a->attributes['sortdate'] = $a->attributes['start date'];
                    } elseif (!empty($a->attributes['mandate start'])) {
                        $a->attributes['year'] = mb_substr($a->attributes['mandate start'], 0, 4);
                        $a->attributes['sortdate'] = $a->attributes['mandate start'];
                    }
                    if (!empty($a->attributes['sortdate'])) {
                        $a->attributes['year'] = mb_substr($a->attributes['sortdate'], 0, 4);
                    } else {
                        $a->attributes['year'] = '';
                    }
                }

                if ($a->ID && ($filter === null || $filter->evaluate($a)))
                    $standardizations[$a->ID] = $a;
            }
        }

        return $standardizations;
    }

}

class CRIS_standardization extends CRIS_Entity {
    /*
     * object for single standardization
     */

    function __construct($data) {
        parent::__construct($data);
    }
}

class CRIS_standardization_image extends CRIS_Entity {
    /*
     * object for single standardization image
     */

    public function __construct($data) {
        parent::__construct($data);

        foreach ($data->relation as $_r) {
            if ($_r['type'] != "EQUI_has_PICT")
                continue;
            foreach ($_r->attribute as $_a) {
                if ($_a['name'] == 'description') {
                    $this->attributes["description"] = (string) $_a->data;
                }
            }
        }
    }

}

