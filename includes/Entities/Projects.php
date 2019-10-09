<?php
namespace FAU\CRIS\Entities;

use FAU\CRIS\Entity;
use FAU\CRIS\Formatter;
use FAU\CRIS\Webservice;
use FAU\CRIS\Tools;


class Projects {

	public function __construct($parameter, $content, $tag, $settings) {
		$this->parameter = $parameter;
		$this->content = $content;
		$this->tag = $tag;
		$this->settings = $settings;
		$this->langdiv_open = '<div class="cris">';
		$this->langdiv_close = '</div>';
		if ($parameter['display_language'] != $parameter['page_language']) {
			$this->langdiv_open = '<div class="cris" lang="' . $parameter['display_language'] . '">';
		}
	}





	/*
	 * Projekt zu einer Publikation
	 */
	public function pubProjects($pub, $seed = false) {
		$ws = new CRIS_projects();
		if ($seed)
			$ws->disable_cache();
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
		$output = $this->make_list($projList, $hide, 0, 1);
		return $output;
	}
}

class ProjectsRequest extends CRIS_webservice {
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
				$a = new CRIS_project($project);
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
class Project extends CRIS_Entity {
	/*
	 * object for single award
	 */
	function __construct($data) {
		parent::__construct($data);
	}
}
class ProjectImage extends CRIS_Entity {
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