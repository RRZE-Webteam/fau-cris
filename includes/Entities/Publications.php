<?php

namespace FAU\CRIS\Entities;

include_once (plugin_dir_path(__DIR__) . "/tools.php");

class Publications {

	public function __construct($parameter) {
		$this->parameter = $parameter;
	}

	public function publicationsByYear() {
		//var_dump($this->parameter);
		$pubArray = $this->fetch_publications();
		if (!count($pubArray)) {
			$output = '<p>' . __('Es wurden leider keine Publikationen gefunden.', 'fau-cris') . '</p>';
			return $output;
		}
	}


	/* =========================================================================
     * Private Functions
     * ========================================================================= */

	/*
     * Holt Daten vom Webservice je nach definierter Einheit.
     */

	private function fetch_publications() {
		$filter = NULL;
		$filter = \FAU\CRIS\publication_filter($this->parameter);
		$ws = new CRIS_publications();
		try {
			if ($this->einheit === "orga") {
				$pubArray = $ws->by_orga_id($this->id, $filter);
			}
			if ($this->einheit === "person") {
				$pubArray = $ws->by_pers_id($this->id, $filter, $notable);
			}
			if ($this->einheit === "project") {
				$pubArray = $ws->by_project($this->id, $filter, $notable);
			}
			if ($this->einheit === "field" || $this->einheit === "field_proj") {
				$pubArray = $ws->by_field($field, $filter, $fsp, $this->einheit);
			}
			if ($this->einheit === "publication") {
				$pubArray = $ws->by_id($this->id);
			}
		} catch (Exception $ex) {
			$pubArray = array();
		}
		return $pubArray;
	}

}