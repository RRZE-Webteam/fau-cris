<?php

require_once("class_Tools.php");
require_once("class_Webservice.php");
require_once("class_Filter.php");
require_once("class_Formatter.php");

class Organisation {

    private $options;
    public $output;

    public function __construct($einheit = '', $id = '') {

        if (strpos($_SERVER['PHP_SELF'], "vkdaten/tools/")) {
            $this->cms = 'wbk';
            $this->options = CRIS::ladeConf();
        } else {
            $this->cms = 'wp';
            $this->options = (array) get_option('_fau_cris');
        }
        $this->orgNr = $this->options['cris_org_nr'];

        if ((!$this->orgNr || $this->orgNr == 0) && $id == '') {
            print '<p><strong>' . __('Bitte geben Sie die CRIS-ID der Organisation an.', 'fau-cris') . '</strong></p>';
            return;
        }
        $this->id = $this->orgNr;
        $this->einheit = "orga";
    }

    /*
     * Ausgabe eines einzelnen Projektes
     */

    public function singleOrganisation($hide = '') {
        $ws = new CRIS_organisations();
        try {
            $orgaArray = $ws->by_id($this->id);
        } catch (Exception $ex) {
            return;
        }

        if (!count($orgaArray)) {
            $output = '<p>' . __('Es wurden leider keine Informationen gefunden.', 'fau-cris') . '</p>';
            return $output;
        }

        $output = $this->make_single($orgaArray, $hide);

        return $output;
    }

    public function researchContacts() {
        $ws = new CRIS_organisations();
        try {
            $orgaArray = $ws->by_id($this->id);
        } catch (Exception $ex) {
            return;
        }
        if (!count($orgaArray)) {
            $output = '<p>' . __('Es wurden leider keine Informationen gefunden.', 'fau-cris') . '</p>';
            return $output;
        }
        $research_contacts = false;
        foreach ($orgaArray as $organisation) {
            $organisation = (array) $organisation;
            foreach ($organisation['attributes'] as $attribut => $v) {
                $organisation[$attribut] = $v;
            }
            unset($organisation['attributes']);
            $contacts = explode(',', $organisation['research_contact']);
            foreach ($contacts as $_c) {
                $nameparts = explode(':', $_c);
                $firstname = $nameparts[0];
                $lastname = array_key_exists(1, $nameparts) ? $nameparts[1] : '';
                $cid = Tools::person_exists($this->cms, $firstname, $lastname);
                if ($cid) {
                    $research_contacts[] = $cid;
                }
            }
        }
        return $research_contacts;
    }

    /* =========================================================================
     * Private Functions
      ======================================================================== */

    /*
     * Holt Daten vom Webservice je nach definierter Einheit.
     */

    private function fetch_organisation() {

        $ws = new CRIS_organisations();
        $orgaArray = array();

        try {
            $orgaArray = $ws->by_id($this->id);
        } catch (Exception $ex) {
            $awardArray = array();
        }
        return $awardArray;
    }

    /*
     * Ausgabe der Organisation
     */

    private function make_single($organisations) {

        $lang = strpos(get_locale(), 'de') === 0 ? 'de' : 'en';
        $output = '';
        $output .= "<div class=\"cris-organisation\">";

        foreach ($organisations as $organisation) {
            $organisation = (array) $organisation;
            foreach ($organisation['attributes'] as $attribut => $v) {
                $organisation[$attribut] = $v;
            }
            unset($organisation['attributes']);

            if (!empty($organisation['research_desc']) || !empty($organisation['research_desc_en'])) {
                $research = ($lang == 'en' && !empty($organisation['research_desc_en'])) ? $organisation['research_desc_en'] : $organisation['research_desc'];
                $output .= "<p class=\"cris-research\">(" . $research . ")</p>";
            }
        }

        $output .= "</div>";
        return $output;
    }

}

class CRIS_organisations extends CRIS_webservice {
    /*
     * projects requests
     */

    public function by_id($orgaID = null) {
        if ($orgaID === null || $orgaID === "0")
            throw new Exception('Please supply valid organisation ID');

        if (!is_array($orgaID))
            $orgaID = array($orgaID);

        $requests = array();
        foreach ($orgaID as $_o) {
            $requests[] = sprintf('get/Organisation/%d', $_o);
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
                echo $e->getMessage();
                continue;
            }
        }

        $organisations = array();

        foreach ($data as $_d) {
            foreach ($_d as $organisation) {
                $a = new CRIS_organisation($organisation);
                if ($a->ID && ($filter === null || $filter->evaluate($a)))
                    $organisations[$a->ID] = $a;
            }
        }

        return $organisations;
    }
}

class CRIS_organisation extends CRIS_Entity {
    /*
     * object for single award
     */

    function __construct($data) {
        parent::__construct($data);
    }

}
