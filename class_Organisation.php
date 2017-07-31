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
     * Ausgabe einer einzelnen Organisation
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

    /*
     * Ausgabe eines Organisation per Custom-Shortcode
     */

    public function customOrganisation($content = '') {
        $ws = new CRIS_organisations();
        try {
            $orgaArray = $ws->by_id($this->id);
        } catch (Exception $ex) {
            return;
        }

        if (!count($orgaArray)) {
            $output = '<p>' . __('Es wurden leider keine Projekte gefunden.', 'fau-cris') . '</p>';
            return $output;
        }
        $output = $this->make_custom_single($orgaArray, $content);
        return $output;
    }

    public function researchContacts($seed=false) {
        $ws = new CRIS_organisations();
        if($seed)
            $ws->disable_cache();
        try {
            $orgaArray = $ws->by_id($this->id);
        } catch (Exception $ex) {
            return;
        }
        if (!count($orgaArray)) {
            $output = '<p>' . __('Es wurden leider keine Informationen gefunden.', 'fau-cris') . '</p>';
            return $output;
        }
        $research_contacts = "";
        foreach ($orgaArray as $organisation) {
            $organisation = (array) $organisation;
            foreach ($organisation['attributes'] as $attribut => $v) {
                $organisation[$attribut] = $v;
            }
            unset($organisation['attributes']);
            $contacts = explode('|', $organisation['research_contact_names']);
            foreach ($contacts as $_c) {
                $nameparts = explode(':', $_c);
                $lastname = $nameparts[0];
                $firstname = array_key_exists(1, $nameparts) ? $nameparts[1] : '';
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
            $research_imgs = self::get_research_images($organisation['ID']);

            if (count($research_imgs)) {
                $output .= "<div class=\"cris-image\">";
                foreach($research_imgs as $img) {
                    if (isset($img->attributes['png180']) && mb_strlen($img->attributes['png180']) > 30) {
                       $output .= "<p><img alt=\"". $img->attributes['_short description'] ."\" src=\"data:image/PNG;base64," . $img->attributes['png180'] . "\" width=\"180\" height=\"180\"><br />"
                        . "<span class=\"wp-caption-text\">" . (($img->attributes['description'] !='') ? $img->attributes['description'] : "") . "</span></p>";
                    }
                }
                $output .= "</div>";
            }

            if (!empty($organisation['research_desc']) || !empty($organisation['research_desc_en'])) {
                $research = ($lang == 'en' && !empty($organisation['research_desc_en'])) ? $organisation['research_desc_en'] : $organisation['research_desc'];
                $output .= "<p class=\"cris-research\">" . $research . "</p>";
            }
        }

        $output .= "</div>";
        return $output;
    }

    private function make_custom_single($organisations, $custom_text) {
        $lang = strpos(get_locale(), 'de') === 0 ? 'de' : 'en';
        $output = '';
        $output .= "<div class=\"cris-organisation\">";

        foreach ($organisations as $organisation) {
            $organisation = (array) $organisation;
            foreach ($organisation['attributes'] as $attribut => $v) {
                $organisation[$attribut] = $v;
            }
            unset($organisation['attributes']);
            $details['#image1#'] = '';
            $research_imgs = self::get_research_images($organisation['ID']);
            if (count($research_imgs)) {
                $i = 1;
                $image = "<div class=\"cris-image\">";
                foreach($research_imgs as $img) {
                    if (isset($img->attributes['png180']) && mb_strlen($img->attributes['png180']) > 30) {
                       $image .= "<p><img alt=\"". $img->attributes['_short description'] ."\" src=\"data:image/PNG;base64," . $img->attributes['png180'] . "\" width=\"180\" height=\"180\"><br />"
                        . "<span class=\"wp-caption-text\">" . (($img->attributes['description'] !='') ? $img->attributes['description'] : "") . "</span></p>";
                    }
                }
                $image .= "</div>";
                $details["#image.$i.#"] .= $image;
                $i++;
            }
            $details['#description#'] = '';
            if (!empty($organisation['research_desc']) || !empty($organisation['research_desc_en'])) {
                $research = ($lang == 'en' && !empty($organisation['research_desc_en'])) ? $organisation['research_desc_en'] : $organisation['research_desc'];
                $details['#description#'] .= "<p class=\"cris-research\">" . $research . "</p>";
            }
            $output .= strtr($custom_text, $details);
        }
        $output .= "</div>";
        return $output;
    }

    private function get_research_images($orga) {
        $images = array();
        $imgString = CRIS_Dicts::$base_uri . "getrelated/Organisation/" . $orga . "/ORGA_has_research_PICT";
        $imgXml = Tools::XML2obj($imgString);

        if ($imgXml['size'] != 0) {
            foreach ($imgXml as $img) {
                $_i = new CRIS_research_image($img);
                $images[$_i->ID] = $_i;
            }
        }
        return $images;
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
                //echo $e->getMessage();
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

class CRIS_research_image extends CRIS_Entity {
    /*
     * object for single publication
     */

    public function __construct($data) {
        parent::__construct($data);

        foreach ($data->relation as $_r) {
            if ($_r['type'] != "ORGA_has_research_PICT")
                continue;
            foreach($_r->attribute as $_a) {
                if ($_a['name'] == 'description') {
                    $this->attributes["description"] = (string) $_a->data;
                }
            }
        }
    }
}