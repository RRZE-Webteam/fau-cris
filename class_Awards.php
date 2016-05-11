<?php

require_once("class_Webservice.php");
require_once("class_Filter.php");
require_once("class_Formatter.php");

class CRIS_awards extends CRIS_webservice {
    /*
     * awards/grants requests
     */
    public function by_orga_id($orgaID=null, &$filter=null) {
        if ($orgaID === null || $orgaID === "0")
            throw new Exception('Please supply valid organisation ID');

        if (!is_array($orgaID))
            $orgaID = array($orgaID);

        $requests = array();
        foreach ($orgaID as $_o) {
            $requests[] = sprintf("getautorelated/Organisation/%d/ORGA_3_AWAR_1", $_o);
        }
        return $this->retrieve($requests, $filter);
    }

    public function by_pers_id($persID=null, &$filter=null) {
        if ($persID === null || $persID === "0")
            throw new Exception('Please supply valid person ID');

        if (!is_array($persID))
            $persID = array($persID);

        $requests = array();
        foreach ($persID as $_p) {
            $requests[] = sprintf('getrelated/Person/%d/awar_has_pers', $_p);
        }
        return $this->retrieve($requests, $filter);
    }

    public function by_id($awarID=null) {
        if ($awarID === null || $awarID === "0")
            throw new Exception('Please supply valid award ID');

        if (!is_array($awarID))
            $awarID = array($awarID);

        $requests = array();
        foreach ($awarID as $_p) {
            $requests[] = sprintf('get/Award/%d', $_p);
        }
        return $this->retrieve($requests);
    }

    public function by_awardtype_id($awatID=null) {
        if ($awatID === null || $awatID === "0")
            throw new Exception('Please supply valid award ID');

        if (!is_array($awatID))
            $awatID = array($awatID);
        
        $requests = array();
        foreach ($awatID as $_p) {
            $requests[] = sprintf("getrelated/Award%%20Type/%d/awar_has_awat", $_p);
        }
        return $this->retrieve($requests);
    }

    private function retrieve($reqs, &$filter=null) {
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

        if ($filter !== null && !$filter instanceof CRIS_filter)
            $filter = new CRIS_filter($filter);

        $awards = array();

        foreach ($data as $_d) {
            foreach ($_d as $award) {
                $a = new CRIS_award($award);
                if ($a->ID && ($filter === null || $filter->evaluate($a)))
                    $awards[$a->ID] = $a;
            }
        }

        return $awards;
    }
}

class CRIS_award extends CRIS_Entity {
    /*
     * object for single award
     */
    function __construct($data) {
        parent::__construct($data);
    }    
}