<?php

require_once("class_Tools.php");
require_once("class_Webservice.php");
require_once("class_Filter.php");
require_once("class_Formatter.php");

class Equipment {
    private $options;
    public $output;

    public function __construct($einheit = '', $id = '', $page_lang = 'de') {
        $this->options = (array) get_option('_fau_cris');
        $this->pathPersonenseiteUnivis = '/person/';
        $this->orgNr = $this->options['cris_org_nr'];
        $this->suchstring = '';
        $this->univis = NULL;

        //$this->order = $this->options['cris_equipment_order'];
        $this->cris_equipment_link = isset($this->options['cris_equipment_link']) ? $this->options['cris_equipment_link'] : 'none';
        if ((!$this->orgNr || $this->orgNr == 0) && $id == '') {
            print '<p><strong>' . __('Bitte geben Sie die CRIS-ID der Organisation, Person oder Forschungsaktivität an.', 'fau-cris') . '</strong></p>';
        }
        if (in_array($einheit, array("person", "orga", "equipment"))) {
            $this->id = $id;
            $this->einheit = $einheit;
        } else {
            // keine Einheit angegeben -> OrgNr aus Einstellungen verwenden
            $this->id = $this->orgNr;
            $this->einheit = "orga";
        }
        $this->page_lang = $page_lang;
    }

    /*
     * Ausgabe eines einzelnen Equipments
     */

    public function singleEquipment($hide = array(), $quotation = '') {
        $ws = new CRIS_equipments();

        try {
            $equiArray = $ws->by_id($this->id);
        } catch (Exception $ex) {
            return;
        }

        if (!count($equiArray)) {
            $output = '<p>' . __('Es wurde leider kein Equipment gefunden.', 'fau-cris') . '</p>';
            return $output;
        }

        $output = $this->make_single($equiArray, $hide, $quotation);
        
        return $output;
    }

	public function customEquipment($content = '', $param = array()) {
    	//var_dump($param['entity_id']);
		if ($param['entity'] == 'equipment') {
			$ws = new CRIS_equipments();
			//var_dump($ws);
			try {
				$equiArray = $ws->by_id($this->id);
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

			$equiArray = $this->fetch_equipments( $manufacturer, $location, $constructionYear, $constructionYearStart, $constructionYearEnd );
		}

		if (!count($equiArray)) {
			$output = '<p>' . __('Es wurden leider kein Equipment gefunden.', 'fau-cris') . '</p>';
			return $output;
		}

		// sortiere nach Erscheinungsdatum
		$order = "cfname";
		$formatter = new CRIS_formatter(NULL, NULL, $order, SORT_ASC);
		$res = $formatter->execute($equiArray);
		$equiList = $res[$order];

		$output =  $this->make_custom($equiList, $content, $param);

		return $output;
	}

	public function equiListe($param = array()) {
        $constructionYearStart = (isset($param['constructionyearstart']) && $param['constructionyearstart'] != '') ? $param['constructionyearstart'] : '';
        $constructionYearEnd = (isset($param['constructionyearend']) && $param['constructionyearend'] != '') ? $param['constructionyearend'] : '';
        $constructionYear = (isset($param['constructionyear']) && $param['constructionyear'] != '') ? $param['constructionyear'] : '';
        //$limit = (isset($param['limit']) && $param['limit'] != '') ? $param['limit'] : '';
        $manufacturer = (isset($param['manufacturer']) && $param['manufacturer'] != '') ? $param['manufacturer'] : '';
        $location = (isset($param['location']) && $param['location'] != '') ? $param['location'] : '';
        $hide = (isset($param['hide']) && !empty($param['hide'])) ? $param['hide'] : array();

        $equiArray = $this->fetch_equipments($manufacturer, $location, $constructionYear, $constructionYearStart, $constructionYearEnd);

        if (!count($equiArray)) {
            $output = '<p>' . __('Es wurden leider kein Equipment gefunden.', 'fau-cris') . '</p>';
            return $output;
        }

        // sortiere nach Erscheinungsdatum
        $order = "cfname";
        $formatter = new CRIS_formatter(NULL, NULL, $order, SORT_ASC);
        $res = $formatter->execute($equiArray);
        $equiList = $res[$order];

        $output =  $this->make_list($equiList, $hide);

        return $output;
    }

    public function equiNachTyp($parameter) {}

    public function equiNachJahr($parameter) {}

    private function make_list($equipments, $hide = array()) {
        $equilist = '';
        $equilist .= "<ul class=\"cris-equipment\">";
        foreach($equipments as $equipment) {
            $equipment = (array) $equipment;
            foreach ($equipment['attributes'] as $attribut => $v) {
                $equipment[$attribut] = $v;
            }
            unset($equipment['attributes']);

            switch ($this->page_lang) {
                case 'en':
                    $name = ($equipment['cfname_en'] != '') ? $equipment['cfname_en'] : $equipment['cfname'];
                    break;
                case 'de':
                default:
                    $name = ($equipment['cfname'] != '') ? $equipment['cfname'] : $equipment['cfname_en'];
                    break;
            }
            $name = htmlentities($name, ENT_QUOTES);

            $manufacturer = null;
            $model = null;
            $constructionYear = null;
            $location = null;
            if ($equipment['hersteller'] != '' && !in_array('manufacturer', $hide)) {
                $manufacturer = $equipment['hersteller'];
            }
            if ($equipment['modell'] != '' && !in_array('model', $hide)) {
                $model = $equipment['modell'];
            }
            if ($equipment['baujahr'] != '' && !in_array('constructionYear', $hide)) {
                $constructionYear = $equipment['baujahr'];
            }
            if ($equipment['standort'] !='' && !in_array('location', $hide)) {
                $location = $equipment['standort'];
            }
            //var_dump($manufacturer);
            $equilist .= "<li>";
            $equilist .= "<span class=\"equipment-name\">" . $name . "</span>";
            if ($manufacturer) {
                $equilist .= '<br />' . $manufacturer;
            }
            if ($model) {
                if ($manufacturer) {
                    $equilist .= ': ';
                } else {
                    $equilist .= '<br />';
                }
                $equilist .= $model;
            }
            if ($constructionYear) {
                $equilist .= ' (' . __('Bj.', 'fau-cris') . ' ' . $constructionYear . ')';
            }
            if ($location) {
                $equilist .= '<br />' . __('Standort', 'fau-cris') . ': '. $location;
            }
            $equilist .= "</li>";
        }
        $equilist .= "</ul>";

        return $equilist;
    }

    private function make_single($equipments, $hide = array(), $quotation = '') {
        $equilist = '';
        $equilist .= "<div class=\"cris-equipment\">";
        foreach($equipments as $equipment) {
            $equipment = (array) $equipment;
            foreach ($equipment['attributes'] as $attribut => $v) {
                $equipment[$attribut] = $v;
            }
            unset($equipment['attributes']);

            $id = $equipment['ID'];
            switch ($this->page_lang) {
                case 'en':
                    $name = ($equipment['cfname_en'] != '') ? $equipment['cfname_en'] : $equipment['cfname'];
                    $description = ($equipment['description_en'] != '') ? $equipment['description_en'] : $equipment['description'];
                    break;
                case 'de':
                default:
                    $name = ($equipment['cfname'] != '') ? $equipment['cfname'] : $equipment['cfname_en'];
                    $description = ($equipment['description'] != '') ? $equipment['description'] : $equipment['description_en'];
                    break;
            }
            $name = htmlentities($name, ENT_QUOTES);
            $description = strip_tags($description, '<br><a><sup><sub><ul><ol><li><b><p><i><strong><em>');
            $manufacturer  = $equipment['hersteller'];
            $model = $equipment['modell'];
            $constructionYear = $equipment['baujahr'];
            $location = $equipment['standort'];
            $url = $equipment['url'];
            $year = $equipment['year'];

	        if (!in_array('name', (array)$hide)) {
		        $equilist .= "<h3>" . $name . "</h3>";
	        }

	        if (!in_array('image', (array)$hide)) {
	            $imgs = self::get_equipment_images($id);
		        if (count($imgs)) {
		            $equilist .= "<div class=\"cris-image\">";
		            foreach ( $imgs as $img ) {
			            if ( isset( $img['png180'] ) && mb_strlen( $img['png180'] ) > 30 ) {
				            $equilist .= "<p><img alt=\"" . $img['desc'] . "\" src=\"" . $img['png180'] . "\" width=\"180\" height=\"180\"><br />"
				                         . "<span class=\"wp-caption-text\">" . ( ( $img['desc'] != '' ) ? $img['desc'] : "" ) . "</span></p>";
			            }
		            }
		            $equilist .= "</div>";
	            }
            }

            if ($description && !in_array('description', (array)$hide)) {
                $equilist .= "<p class=\"equipment-description\">" . $description . '</p>';
            }
            if (!in_array('details', $hide)) {
                $equilist .= "<p class=\"equipment-details\">";
                if (!in_array('manufacturer', (array)$hide) && !empty($manufacturer))
                    $equilist .= "<b>" . __('Hersteller', 'fau-cris') . ': </b>' . $manufacturer;
                if (!in_array('model', (array)$hide) && !empty($model))
                    $equilist .= "<br /><b>" . __('Modell', 'fau-cris') . ': </b>' . $model;
                if (!in_array('constructionYear', (array)$hide) && !empty($constructionYear))
                    $equilist .= "<br /><b>" . __('Baujahr', 'fau-cris') . ': </b>' . $constructionYear;
                if (!in_array('location', (array)$hide) && !empty($location))
                    $equilist .= "<br /><b>" . __('Standort', 'fau-cris') . ': </b>' . $location;
                if (!in_array('url', (array)$hide) && !empty($url))
                    $equilist .= "<br /><b>" . __('URL', 'fau-cris') . ': </b>' . $url;
                if (!in_array('year', (array)$hide) && !empty($year))
                    $equilist .= "<br /><b>" . __('Jahr', 'fau-cris') . ': </b>' . $year;
            }
            if (!in_array('funding', $hide)) {
                $funding = $this->get_equipment_funding($id);
                if ($funding)
                    $equilist .=  "<br /><b>" . __('Mittelgeber', 'fau-cris') . ": </b>" . implode(', ', $funding);
            }
            if (!in_array('fields', $hide)) {
                $fields = $this->get_equipment_fields($id);
                if ($fields) {
                    $equilist .= "<h4>" . __('Einsatz in Forschungsbereichen', 'fau-cris') . ': </h4>';
                    $equilist .= "<ul>";
                    foreach ($fields as $_k => $field) {
                        switch ($this->page_lang) {
                            case 'en':
                                if (!empty($field['cfname_en'])) {
                                    $equilist .= sprintf('<li><a href="https://cris.fau.de/converis/portal/Forschungsbereich/%s?lang=en_GB">%s</a></li>', $_k, $field['cfname_en']);
                                } else {
                                    $equilist .= sprintf('<li><a href="https://cris.fau.de/converis/portal/Forschungsbereich/%s?lang=en_GB">%s</a></li>', $_k, $field['cfname']);
                                }
                                break;
                            case 'de':
                            default:
                                if (!empty($field['cfname'])) {
                                    $equilist .= sprintf('<li><a href="https://cris.fau.de/converis/portal/Forschungsbereich/%s">%s</a></li>', $_k, $field['cfname']);
                                } else {
                                    $equilist .= sprintf('<li><a href="https://cris.fau.de/converis/portal/Forschungsbereich/%s">%s</a></li>', $_k, $field['cfname_en']);
                                }
                                break;
                        }
                    }
                    $equilist .= "</ul>";
                }
            }

            if (!in_array('projects', $hide)) {
                $projects = $this->get_equipment_projects($id);
                if ($projects) {
                    $equilist .= "<h4>" . __('Einsatz in Forschungsprojekten', 'fau-cris') . ': </h4>';
                    $equilist .= "<ul>";
                    foreach ($projects as $_k => $project) {
                        switch ($this->page_lang) {
                            case 'en':
                                if (!empty($project['cfTitle_en'])) {
                                    $equilist .= sprintf('<li><a href="https://cris.fau.de/converis/portal/Project/%s?lang=en_GB">%s</a></li>', $_k, $project['cfTitle_en']);
                                } else {
                                    $equilist .= sprintf('<li><a href="https://cris.fau.de/converis/portal/Project/%s?lang=en_GB">%s</a></li>', $_k, $project['cfTitle']);
                                }
                                break;
                            case 'de':
                            default:
                                if (!empty($project['cfTitle'])) {
                                    $equilist .= sprintf('<li><a href="https://cris.fau.de/converis/portal/Project/%s">%s</a></li>', $_k, $project['cfTitle']);
                                } else {
                                    $equilist .= sprintf('<li><a href="https://cris.fau.de/converis/portal/Project/%s">%s</a></li>', $_k, $project['cfTitle_en']);
                                }
                                break;
                        }
                    }
                    $equilist .= "</ul>";
                }
            }

            if (!in_array('publications', $hide)) {
                $publications = $this->get_equipment_publications($id, $quotation);
                if ($publications)
                    $equilist .= "<h4>" . __('Zugehörige Publikationen', 'fau-cris') . ': </h4>' . $publications;
            }
        }

        $equilist .= "</div>";
        return $equilist;

    }

	private function make_custom($equipments, $custom_text = '', $param = array()) {

		switch ($param['display']) {
			case 'accordion':
				$tag_open = '[collapsibles expand-all-link="true"]';
				$tag_close= '[/collapsibles]';
				$item_open = '[collapse title="%1s" color="%2s" name="%3s"]';
				$item_close = '[/collapse]';
				break;
			case 'no-list':
				$tag_open = '<div class="cris-equipments">';
				$tag_close= '</div>';
				$item_open = '<div class="cris-equipment">';
				$item_close = '</div>';
				break;
			case 'list':
			default:
				$tag_open = '<ul class="cris-equipments">';
				$tag_close= '</ul>';
				$item_open = '<li>';
				$item_close = '</li>';
		}

		$equipmentlist = $tag_open;

		foreach ($equipments as $equipment) {

			$equipment = (array) $equipment;
			foreach ($equipment['attributes'] as $attribut => $v) {
				$equipment[$attribut] = $v;
			}
			unset($equipment['attributes']);

			$id = $equipment['ID'];
			switch ($this->page_lang) {
				case 'en':
					$name = ($equipment['cfname_en'] != '') ? $equipment['cfname_en'] : $equipment['cfname'];
					$description = ($equipment['description_en'] != '') ? $equipment['description_en'] : $equipment['description'];
					break;
				case 'de':
				default:
					$name = ($equipment['cfname'] != '') ? $equipment['cfname'] : $equipment['cfname_en'];
					$description = ($equipment['description'] != '') ? $equipment['description'] : $equipment['description_en'];
					break;
			}
			$equipment_details['#name#'] =  htmlentities($name, ENT_QUOTES);
			$equipment_details['#description#'] = strip_tags($description, '<br><a><sup><sub><ul><ol><li><b><p><i><strong><em>');
			$equipment_details['#manufacturer#']  = $equipment['hersteller'];
			$equipment_details['#model#'] = $equipment['modell'];
			$equipment_details['#constructionYear#'] = $equipment['baujahr'];
			$equipment_details['#location#'] = $equipment['standort'];
			$equipment_details['#url#'] = $equipment['url'];
			$equipment_details['#year#'] = $equipment['year'];

			if (strpos($custom_text, '#image') !== false) {
				$imgs = self::get_equipment_images($id);

				$equipment_details['#image1#'] = '';
				if (count($imgs)) {
					$i = 1;
					foreach($imgs as $img) {
						if (isset($img['png180']) && mb_strlen($img['png180']) > 30) {
							$equipment_details['#image'.$i.'#'] = "<div class='wp-caption align" . $param['image_align'] . "'><img alt=\"". $equipment_details['#name#'] ."\" src=\"" . $img['png180'] . "\"><br />"
							                                  . "<span class=\"wp-caption-text\">" . (($img['desc'] !='') ? $img['desc'] : "") . "</span>";
							$equipment_details['#image'.$i.'#'] .= "</div>";
						}
						$i++;
					}
				}
				$equipment_details['#image#'] = $equipment_details['#image1#'];
			}

			if (strpos($custom_text, '#funding#') !== false) {
				$equipment_details['#funding#'] = '-/-';
				$funding = $this->get_equipment_funding($id);
				if ($funding)
					$equipment_details['#funding#'] = implode(', ', $funding);
			}

			if (strpos($custom_text, '#fields#') !== false) {
				$equipment_details['#fields#'] = '-/-';
				$fields = $this->get_equipment_fields($id);
				if ($fields) {
					$equipment_details['#fields#'] = "<ul>";
					foreach ($fields as $_k => $field) {
						switch ($this->page_lang) {
							case 'en':
								if (!empty($field['cfname_en'])) {
									$equipment_details['#fields#'] .= sprintf('<li><a href="https://cris.fau.de/converis/portal/Forschungsbereich/%s?lang=en_GB">%s</a></li>', $_k, $field['cfname_en']);
								} else {
									$equipment_details['#fields#'] .= sprintf('<li><a href="https://cris.fau.de/converis/portal/Forschungsbereich/%s?lang=en_GB">%s</a></li>', $_k, $field['cfname']);
								}
								break;
							case 'de':
							default:
								if (!empty($field['cfname'])) {
									$equipment_details['#fields#'] .= sprintf('<li><a href="https://cris.fau.de/converis/portal/Forschungsbereich/%s">%s</a></li>', $_k, $field['cfname']);
								} else {
									$equipment_details['#fields#'] .= sprintf('<li><a href="https://cris.fau.de/converis/portal/Forschungsbereich/%s">%s</a></li>', $_k, $field['cfname_en']);
								}
								break;
						}
					}
					$equipment_details['#fields#'] .= "</ul>";
				}
			}

			if (strpos($custom_text, '#projects#') !== false) {
				$equipment_details['#projects#'] = '-/-';
				$projects = $this->get_equipment_projects($id);
				if ($projects) {
					$equipment_details['#projects#'] = "<ul>";
					foreach ($projects as $_k => $project) {
						switch ($this->page_lang) {
							case 'en':
								if (!empty($project['cfTitle_en'])) {
									$equipment_details['#projects#'] .= sprintf('<li><a href="https://cris.fau.de/converis/portal/Project/%s?lang=en_GB">%s</a></li>', $_k, $project['cfTitle_en']);
								} else {
									$equipment_details['#projects#'] .= sprintf('<li><a href="https://cris.fau.de/converis/portal/Project/%s?lang=en_GB">%s</a></li>', $_k, $project['cfTitle']);
								}
								break;
							case 'de':
							default:
								if (!empty($project['cfTitle'])) {
									$equipment_details['#projects#'] .= sprintf('<li><a href="https://cris.fau.de/converis/portal/Project/%s">%s</a></li>', $_k, $project['cfTitle']);
								} else {
									$equipment_details['#projects#'] .= sprintf('<li><a href="https://cris.fau.de/converis/portal/Project/%s">%s</a></li>', $_k, $project['cfTitle_en']);
								}
								break;
						}
					}
					$equipment_details['#projects#'] .= "</ul>";
				}
			}

			if (strpos($custom_text, '#publications#') !== false) {
				$equipment_details['#publications#'] = '-/-';
				$publications = $this->get_equipment_publications($id, $param['quotation']);
				if ($publications)
					$equipment_details['#publications#'] = $publications;
			}

			if ($param['display'] == 'accordion') {
				$item_open = sprintf($item_open, $param['accordion_title'],$param['accordion_color'], sanitize_title($equipment_details['#name#']));
			}

			$equipmentlist .= strtr($item_open . $custom_text . $item_close, $equipment_details);
		}

		$equipmentlist .= $tag_close;

		return do_shortcode($equipmentlist);
	}

    private function get_equipment_images($equipment) {
        $images = array();
        $imgString = CRIS_Dicts::$base_uri . "getrelated/equipment/" . $equipment . "/equi_has_pict";
        $imgXml = Tools::XML2obj($imgString);
	    $i = 1;
	    if ($imgXml['size'] != 0) {
		    foreach ($imgXml->infoObject as $img) {
			    foreach ($img->attribute as $imgAttribut) {
				    if ($imgAttribut['name'] == 'png180') {
					    $images[$i]['png180'] = (!empty($imgAttribut->data)) ? 'data:image/PNG;base64,' . (string) $imgAttribut->data : '';
				    }
			    }
			    foreach ($img->relation->attribute as $imgRelAttribut) {
				    if ($imgRelAttribut['name'] == 'description') {
					    $images[$i]['desc'] = (!empty($imgRelAttribut->data)) ? (string) $imgRelAttribut->data : '';
				    }
			    }
			    $i ++;
		    }
	    }
        return $images;
    }

    private function get_equipment_publications($equipment = NULL, $quotation = '') {
        require_once('class_Publikationen.php');
        $liste = new Publikationen('equipment', $equipment);
        return $liste->equiPub($equipment, $quotation);
    }

    private function get_equipment_funding($equipment = NULL) {
        $funding = array();
        $fundingString = CRIS_Dicts::$base_uri . "getrelated/equipment/" . $equipment . "/EQUI_has_FUND";
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

    private function get_equipment_fields($equipment) {
        $fields = array();
        $fieldsString = CRIS_Dicts::$base_uri . "getrelated/equipment/" . $equipment . "/FOBE_has_EQUI";
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

    private function get_equipment_projects($equipment) {
        $projects = array();
        $projectsString = CRIS_Dicts::$base_uri . "getrelated/equipment/" . $equipment . "/EQUI_has_PROJ";
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

    private function fetch_equipments($manufacturer = '', $location = '', $constructionYear = '', $constructionYearStart = '', $constructionYearEnd = '') {

        $filter = Tools::equipment_filter($manufacturer, $location, $constructionYear, $constructionYearStart, $constructionYearEnd);

        $ws = new CRIS_equipments();
        $equiArray = array();

        try {
            if ($this->einheit === "orga") {
                $equiArray = $ws->by_orga_id($this->id, $filter);
            }
        } catch (Exception $ex) {
            $equiArray = array();
        }
        return $equiArray;
    }
}

class CRIS_equipments extends CRIS_webservice {
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
            $requests[] = sprintf("getrelated/Organisation/%d/equi_has_orga", $_o);
        }
        return $this->retrieve($requests, $filter);
    }

    public function by_id($awarID = null) {
        if ($awarID === null || $awarID === "0")
            throw new Exception('Please supply valid equipment ID');

        if (!is_array($awarID))
            $awarID = array($awarID);

        $requests = array();
        foreach ($awarID as $_p) {
            $requests[] = sprintf('get/equipment/%d', $_p);
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

        $equipments = array();

        foreach ($data as $_d) {
            foreach ($_d as $equipment) {
                $a = new CRIS_equipment($equipment);
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
                    $equipments[$a->ID] = $a;
            }
        }

        return $equipments;
    }

}

class CRIS_equipment extends CRIS_Entity {
    /*
     * object for single equipment
     */

    function __construct($data) {
        parent::__construct($data);
    }
}

class CRIS_equipment_image extends CRIS_Entity {
    /*
     * object for single equipment image
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

