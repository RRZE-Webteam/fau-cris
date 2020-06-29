<?php
namespace FAU\CRIS\Entities;

use FAU\CRIS\Entity;
use FAU\CRIS\Entities\Publications;
use FAU\CRIS\Formatter;
use FAU\CRIS\Webservice;
use FAU\CRIS\Tools;


class Projects {

	public function __construct($parameter, $content, $tag, $options) {
		$this->parameter = $parameter;
		$this->content = $content;
		$this->tag = $tag;
		$this->options = $options;
		$this->langdiv_open = '<div class="cris">';
		$this->langdiv_close = '</div>';
		$this->cris_project_link = isset($this->options['cris_project_link']) ? $this->options['cris_project_link'] : 'none';
		if ($parameter['display_language'] != $parameter['page_language']) {
			$this->langdiv_open = '<div class="cris" lang="' . $parameter['display_language'] . '">';
		}
		include (plugin_dir_path(__DIR__)."dictionary.php");
		$this->base_uri = $base_uri;
		$this->tools = new Tools();
	}

	/*
	 * Ausgabe eines einzelnen Projektes
	 */

	public function singleProject() {
		$ws = new ProjectsRequest();
		try {
			$projArray = $ws->by_id($this->parameter['project']);
		} catch (Exception $ex) {
			return;
		}

		if (!count($projArray)) {
			$output = '<p>' . __('Es wurden leider keine Projekte gefunden.', 'fau-cris') . '</p>';
			return $output;
		}

		if (is_array($this->parameter['project'])) {
			$output = $this->makeList($projArray, $this->parameter['hide']);
		} else {
			$output = $this->make_single($projArray);
		}
		return $output;
	}


	/* =========================================================================
     * Private Functions
     * ========================================================================= */

	private function make_single($projects) {
		$projlist = '';
		$projlist .= "<div class=\"fau-cris cris-projects\">";

		foreach ($projects as $project) {
			$project = (array) $project;
			foreach ($project['attributes'] as $attribut => $v) {
				$project[$attribut] = $v;
			}
			unset($project['attributes']);

			$id = $project['ID'];
			switch ($this->parameter['page_language']) {
				case 'en':
					$title = ($project['cftitle_en'] != '') ? $project['cftitle_en'] : $project['cftitle'];
					$description = ($project['cfabstr_en'] != '') ? $project['cfabstr_en'] : $project['cfabstr'];
					break;
				case 'de':
				default:
					$title = ($project['cftitle'] != '') ? $project['cftitle'] : $project['cftitle_en'];
					$description = ($project['cfabstr'] != '') ? $project['cfabstr'] : $project['cfabstr_en'];
					break;
			}
			$title = htmlentities($title, ENT_QUOTES);
			$description = strip_tags($description, '<br><a><sup><sub><ul><ol><li><b><p><i><strong><em>');
			$type = $this->tools->getName('projects', $project['project type'], $this->parameter['page_language']);
			$imgs = self::get_project_images($project['ID']);

			if (count($imgs)) {
				$projlist .= "<div class=\"cris-image\">";
				foreach ($imgs as $img) {
					if (isset($img->attributes['png180']) && mb_strlen($img->attributes['png180']) > 30) {
						$projlist .= "<p><img alt=\"" . $img->attributes['description'] . "\" src=\"data:image/PNG;base64," . $img->attributes['png180'] . "\" width=\"180\" height=\"180\"><br />"
						             . "<span class=\"wp-caption-text\">" . (($img->attributes['description'] != '') ? $img->attributes['description'] : "") . "</span></p>";
					}
				}
				$projlist .= "</div>";
			}

			if (!in_array('title', $this->parameter['hide'])) {
				$projlist .= "<h3>" . $title . "</h3>";
			}

			if (!empty($type))
				$projlist .= "<p class=\"project-type\">(" . $type . ")</p>";

			if (!in_array('details', $this->parameter['hide'])) {
				$parentprojecttitle = ($this->parameter['page_language'] == 'en' && !empty($project['parentprojecttitle_en'])) ? $project['parentprojecttitle_en'] : $project['parentprojecttitle'];
				$leaderIDs = explode(",", $project['relpersidlead']);
				$collIDs = explode(",", $project['relpersidcoll']);
				$persons = $this->get_project_persons($id, $leaderIDs, $collIDs);
				$leaders = array();
				foreach ($persons['leaders'] as $l_id => $l_names) {
					$leaders[] = $this->tools->getPersonLink($l_id, $l_names['firstname'], $l_names['lastname'], $this->options['cris_layout_cris_project_link'], 1, 1);
				}
				$members = array();
				foreach ($persons['members'] as $m_id => $m_names) {
					$members[] = $this->tools->getPersonLink($m_id, $m_names['firstname'], $m_names['lastname'], $this->cris_project_link, $this->cms, $this->pathPersonenseiteUnivis, $this->univis);
				}
				$start = $project['cfstartdate'];
				$start = date_i18n( get_option( 'date_format' ), strtotime($start));
				if (!in_array('end', $this->parameter['hide'])) {
					$end = $project['virtualenddate'];
					$end = date_i18n( get_option( 'date_format' ), strtotime($end));
				} else {
					$end = '';
				}
				$funding = $this->get_project_funding($id);
				$url = $project['cfuri'];
				$acronym = $project['cfacro'];

				$projlist .= "<p class=\"project-details\">";
				if (!empty($parentprojecttitle))
					$projlist .= "<b>" . __('Titel des Gesamtprojektes', 'fau-cris') . ': </b>' . $parentprojecttitle;
				if (!empty($leaders)) {
					$projlist .= "<br /><b>" . __('Projektleitung', 'fau-cris') . ': </b>';
					$projlist .= implode(', ', $leaders);
				}
				if (!empty($members)) {
					$projlist .= "<br /><b>" . __('Projektbeteiligte', 'fau-cris') . ': </b>';
					$projlist .= implode(', ', $members);
				}
				if (!empty($start))
					$projlist .= "<br /><b>" . __('Projektstart', 'fau-cris') . ': </b>' . $start;
				if (!empty($end))
					$projlist .= "<br /><b>" . __('Projektende', 'fau-cris') . ': </b>' . $end;
				if (!empty($acronym))
					$projlist .= "<br /><b>" . __('Akronym', 'fau-cris') . ": </b>" . $acronym;
				if (!empty($funding)) {
					$projlist .= "<br /><b>" . __('Mittelgeber', 'fau-cris') . ': </b>';
					$projlist .= implode(', ', $funding);
				}
				if (!empty($url))
					$projlist .= "<br /><b>" . __('URL', 'fau-cris') . ": </b><a href=\"" . $url . "\">" . $url . "</a>";
				$projlist .= "</p>";
			}

			if (!in_array('abstract', $this->parameter['hide'])) {
				if ($description)
					$projlist .= "<h4>" . __('Abstract', 'fau-cris') . ": </h4>" . "<p class=\"project-description\">" . $description . '</p>';
			}
			if (!in_array('publications', $this->parameter['hide'])) {
				$publications = $this->get_project_publications($id, $this->parameter);
				if ($publications)
					$projlist .= "<h4>" . __('Publikationen', 'fau-cris') . ": </h4>" . $publications;
			}
		}
		$projlist .= "</div>";
		return $projlist;
	}

	private function get_project_images($project) {
		$images = array();
		$imgString = $this->base_uri . "getrelated/project/" . $project . "/PROJ_has_PICT";
		$imgXml = $this->tools->XML2obj($imgString);

		if ($imgXml['size'] != 0) {
			foreach ($imgXml as $img) {
				$_i = new CRIS_project_image($img);
				$images[$_i->ID] = $_i;
			}
		}
		return $images;
	}

	private function get_project_leaders($project, $leadIDs) {
		$leaders = array();
		$leadersString = $this->base_uri . "getrelated/Project/" . $project . "/proj_has_card";
		$leadersXml = $this->tools->XML2obj($leadersString);
		if ($leadersXml['size'] != 0) {
			$i = 0;
			foreach ($leadersXml->infoObject as $person) {
				foreach ($person->attribute as $persAttribut) {
					if ($persAttribut['name'] == 'lastName') {
						$leaders[$i]['lastname'] = (string) $persAttribut->data;
					}
					if ($persAttribut['name'] == 'firstName') {
						$leaders[$i]['firstname'] = (string) $persAttribut->data;
					}
				}
				foreach ($person->relation as $persRel) {
					foreach ($persRel->attribute as $persRelAttribute) {
						if ($persRelAttribute['name'] == 'Right seq') {
							$leaders[$i]['order'] = (string) $persRelAttribute->data;
						}
					}
				}
				$i++;
			}
		}
		usort($leaders, function($a, $b) {
			return $a['order'] <=> $b['order'];
		});
		if (count($leadIDs) == count($leaders)) {
			$leaders = array_combine($leadIDs, $leaders);
		} else {
			$leaders = $leaders;
		}
		return $leaders;
	}

	public function get_project_members($project, $collIDs) {
		$members = array();
		$membersString = $this->base_uri . "getrelated/Project/" . $project . "/proj_has_col_card";
		$membersXml = $this->tools->XML2obj($membersString);
		if ($membersXml['size'] != 0) {
			$i = 0;
			foreach ($membersXml->infoObject as $person) {
				foreach ($person->attribute as $persAttribut) {
					if ($persAttribut['name'] == 'lastName') {
						$members[$i]['lastname'] = (string) $persAttribut->data;
					}
					if ($persAttribut['name'] == 'firstName') {
						$members[$i]['firstname'] = (string) $persAttribut->data;
					}
				}
				foreach ($person->relation as $persRel) {
					foreach ($persRel->attribute as $persRelAttribute) {
						if ($persRelAttribute['name'] == 'Right seq') {
							$members[$i]['order'] = (string) $persRelAttribute->data;
						}
					}
				}
				$i++;
			}
			usort($members, function($a, $b) {
				return $a['order'] <=> $b['order'];
			});
		}
		if (count($collIDs) == count($members)) {
			$members = array_combine($collIDs, $members);
		}
		return $members;
	}

	private function get_project_persons($project, $leadIDs, $collIDs) {
		$persons = array();

		$persons['leaders'] = $this->get_project_leaders($project, $leadIDs);
		$persons['members'] = $this->get_project_members($project, $collIDs);

		return $persons;
	}

	private function get_project_funding($project) {
		$funding = array();
		$fundingString = $this->base_uri . "getrelated/Project/" . $project . "/proj_has_fund";
		$fundingXml = $this->tools->XML2obj($fundingString);
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

	private function get_project_publications($project = NULL, $param = array()) {
		$liste = new Publications('project', $project, $this->tag, $this->options);
		$args = array();
		foreach ($param as $_k => $_v) {
			if (substr($_k, 0, 13) == 'publications_') {
				$args[substr($_k,13)] = $_v;
			}
		}
		$args['sc_type'] = 'default';
		$args['quotation'] = $param['quotation'];
		$args['display_language'] = $param['display_language'];
		if ($param['publications_orderby'] == 'year')
			return $liste->pubNachJahr ($args, $param['project'], '', false,$param['project']);
		if ($param['publications_orderby'] == 'type')
			return $liste->pubNachTyp ($args, $param['project'], '', false,$param['project']);
		return $liste->projectPub($param['project'], $param['quotation'], false, $param['publications_limit'], $param['display_language']);
	}




	/*
	 * Projekt zu einer Publikation
	 */
	public function pubProjects($pub, $seed = false) {
		$ws = new CRIS_projects();
		if ($seed)
			$ws->disableCache();
		try {
			$projArray = $ws->by_pub($pub);
		} catch (Exception $ex) {
			return;
		}
		if (!count($projArray))
			return;
		if (array_key_exists('relation right seq', reset($projArray)->attributes)) {
			$sortby = 'relation right seq';
			$orderby = $sortby;
		} else {
			$sortby = NULL;
			$orderby = __('O.A.', 'fau-cris');
		}
		// sortiere nach Erscheinungsdatum
		if (array_key_exists('relation right seq', reset($projArray)->attributes)) {
			$sortby = 'relation right seq';
			$orderby = $sortby;
		} else {
			$sortby = NULL;
			$orderby = __('O.A.', 'fau-cris');
		}
		$formatter = new CRIS_formatter(NULL, NULL, $sortby, SORT_ASC);
		$res = $formatter->execute($projArray);
		$projList = $res[$orderby];
		$hide = array();
		$output = $this->makeList($projList, $hide, 0, 1);
		return $output;
	}
}

class ProjectsRequest extends Webservice {
	/*
	 * projects requests
	 */
	public function by_orga_id($orgaID = null, &$filter = null) {
		if ($orgaID === null || $orgaID === "0")
			throw new Exception('Please supply valid organisation ID');
		if (!is_array($orgaID))
			$orgaID = array($orgaID);
		$requests = array();
		foreach ($orgaID as $_o) {
			$requests[] = sprintf("getautorelated/Organisation/%d/ORGA_2_PROJ_1", $_o);
			$requests[] = sprintf("getrelated/Organisation/%d/PROJ_has_int_ORGA", $_o);
		}
		return $this->retrieve($requests, $filter);
	}
	public function by_pers_id($persID = null, &$filter = null, $role = 'all') {
		if ($persID === null || $persID === "0")
			throw new Exception('Please supply valid person ID');
		if (!is_array($persID))
			$persID = array($persID);
		$requests = array();
		foreach ($persID as $_p) {
			if ($role == 'leader') {
				$requests[] = sprintf('getautorelated/Person/%s/PERS_2_PROJ_1', $_p);
			} elseif ($role == 'member') {
				$requests[] = sprintf('getautorelated/Person/%s/PERS_2_PROJ_2', $_p);
			} else {
				$requests[] = sprintf('getautorelated/Person/%s/PERS_2_PROJ_1', $_p);
				$requests[] = sprintf('getautorelated/Person/%s/PERS_2_PROJ_2', $_p);
			}
		}
		return $this->retrieve($requests, $filter);
	}
	public function by_id($projID = null) {
		if ($projID === null || $projID === "0")
			throw new Exception('Please supply valid project ID');
		if (!is_array($projID))
			$projID = array($projID);
		$requests = array();
		foreach ($projID as $_p) {
			$requests[] = sprintf('get/Project/%d', $_p);
		}
		return $this->retrieve($requests);
	}
	public function by_field($fieldID = null) {
		if ($fieldID === null || $fieldID === "0")
			throw new Exception('Please supply valid field of research ID');
		if (!is_array($fieldID))
			$fieldID = array($fieldID);
		$requests = array();
		foreach ($fieldID as $_f) {
			$requests[] = sprintf('getrelated/Forschungsbereich/%d/fobe_has_proj', $_f);
			$requests[] = sprintf('getrelated/Forschungsbereich/%d/fobe_fac_has_proj', $_f);
		}
		return $this->retrieve($requests);
	}
	public function by_pub($pubID = null) {
		if ($pubID === null || $pubID === "0")
			throw new Exception('Please supply valid publication ID');
		if (!is_array($pubID))
			$pubID = array($pubID);
		$requests = array();
		foreach ($pubID as $_f) {
			$requests[] = sprintf('getrelated/Publication/%d/PROJ_has_PUBL', $_f);
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
				//echo $e->getMessage();
				continue;
			}
		}
		$projects = array();
		foreach ($data as $_d) {
			foreach ($_d as $project) {
				$a = new Project($project);
				if ($a->ID) {
					$a->attributes['startyear'] = mb_substr($a->attributes['cfstartdate'], 0, 4);
					$a->attributes['endyear'] = mb_substr($a->attributes['virtualenddate'], 0, 4);
					//$a->attributes['endyear'] = $a->attributes['cfenddate'] != '' ? mb_substr($a->attributes['cfenddate'], 0, 4) : mb_substr($a->attributes['virtualenddate'], 0, 4);
				}
				if ($a->ID && ($filter === null || $filter->evaluate($a)))
					$projects[$a->ID] = $a;
			}
		}
		return $projects;
	}
}
class Project extends Entity {
	/*
	 * object for single award
	 */
	function __construct($data) {
		parent::__construct($data);
	}
}
class ProjectImage extends Entity {
	/*
	 * object for single project image
	 */
	public function __construct($data) {
		parent::__construct($data);
		foreach ($data->relation as $_r) {
			if ($_r['type'] != "PROJ_has_PICT")
				continue;
			foreach ($_r->attribute as $_a) {
				if ($_a['name'] == 'description') {
					$this->attributes["description"] = (string) $_a->data;
				}
			}
		}
	}
}