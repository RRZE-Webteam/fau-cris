<?php

require_once("class_Tools.php");
require_once("class_Webservice.php");
require_once("class_Filter.php");
require_once("class_Formatter.php");

class Forschungsbereiche {

    private $options;
    public $output;

    public function __construct($einheit = '', $id = '') {
        if (strpos($_SERVER['PHP_SELF'], "vkdaten/tools/")) {
            $this->cms = 'wbk';
            $this->options = CRIS::ladeConf();
            $this->pathPersonenseiteUnivis = $this->options['Pfad_Personenseite_Univis'] . '/';
        } else {
            $this->cms = 'wp';
            $this->options = (array) get_option('_fau_cris');
            $this->pathPersonenseiteUnivis = '/person/';
        }
        $this->orgNr = $this->options['cris_org_nr'];
        $this->suchstring = '';
        $this->univis = NULL;
        $this->lang = strpos(get_locale(), 'de') === 0 ? 'de' : 'en';

        $this->cris_project_link = isset($this->options['cris_project_link']) ? $this->options['cris_project_link'] : 'none';
        if ($this->cms == 'wbk' && $this->cris_project_link == 'person') {
            $this->univis = Tools::get_univis();
        }

        if ((!$this->orgNr || $this->orgNr == 0) && $id == '') {
            print '<p><strong>' . __('Bitte geben Sie die CRIS-ID der Organisation oder des Forschungsbereichs an.', 'fau-cris') . '</strong></p>';
            return;
        }
        if (in_array($einheit, array("orga", "field"))) {
            $this->id = $id;
            $this->einheit = $einheit;
        } else {
            // keine Einheit angegeben -> OrgNr aus Einstellungen verwenden
            $this->id = $this->orgNr;
            $this->einheit = "orga";
        }

    }

    /*
     * Ausgabe aller Forschungsbereiche
     */

    public function fieldListe($param = array()) {

        $fieldsArray = $this->fetch_fields();

        if (!count($fieldsArray)) {
            $output = '<p>' . __('Es wurden leider keine Forschungsbereiche gefunden.', 'fau-cris') . '</p>';
            return $output;
        }
        if (array_key_exists('relation left seq', reset($fieldsArray)->attributes)) {
            $sortby = 'relation left seq';
            $orderby = $sortby;
        } else {
            $sortby = NULL;
            $orderby = __('O.A.','fau-cris');
        }
        $hide = explode(',', $param['hide']);
        $formatter = new CRIS_formatter(NULL, NULL, $sortby, SORT_ASC);
        $res = $formatter->execute($fieldsArray);
        if ($param['limit'] != '')
            $fieldList = array_slice($res[$orderby], 0, $param['limit']);
        else
            $fieldList = $res[$orderby];
        $output = '';
        $output .= $this->make_list($fieldList);

        return $output;
    }

    /*
     * Ausgabe eines einzelnen Forschungsbereichs
     */

    public function singleField($param = array()) {
        $ws = new CRIS_fields();
        try {
            $fieldsArray = $ws->by_id($this->id);
        } catch (Exception $ex) {
            return;
        }
        if (!count($fieldsArray))
            return;

        $output = $this->make_single($fieldsArray, $param);

        return $output;
    }

    /*
     * Ausgabe eines einzelnen Forschungsbereichs per Custom-Shortcode
     */

    public function customField($content = '', $param = array()) {
        $ws = new CRIS_fields();

        try {
            $fieldsArray = $ws->by_id($this->id);
        } catch (Exception $ex) {
            return;
        }

        if (!count($fieldsArray))
            return;

        $output = $this->make_custom_single($fieldsArray, $content, $param);

        return $output;
    }

    /*
     * Array aller Forschungsbereiche für die Synchonisierung
     */

    public function fieldsArray($seed=false, $sortby=NULL) {
        $fieldsArray = $this->fetch_fields($seed);

        if (!count($fieldsArray)) {
            return false;
        }
        if ($sortby != NULL) {
            if ($this->lang == 'en')
                $sortby = $sortby . '_en';
                $orderby = $sortby;
        } else {
            if (array_key_exists('relation left seq', reset($fieldsArray)->attributes)) {
                $sortby = 'relation left seq';
                $orderby = $sortby;
            } else {
                $sortby = NULL;
                $orderby = __('O.A.','fau-cris');
            }
        }

        $formatter = new CRIS_formatter(NULL, NULL, $sortby, SORT_ASC);
        $res = $formatter->execute($fieldsArray);
        $fieldList = $res[$orderby];

        return $fieldList;
    }


    /* =========================================================================
     * Private Functions
      ======================================================================== */

    /*
     * Holt Daten vom Webservice je nach definierter Einheit.
     */

    private function fetch_fields($seed=false) {
        $filter = Tools::field_filter();
        $ws = new CRIS_fields();
        if ($seed)
            $ws->disable_cache();
        try {
            if ($this->einheit === "orga") {
                $pubArray = $ws->by_orga_id($this->id, $filter);
            }
            /*if ($this->einheit === "person") {
                $pubArray = $ws->by_pers_id($this->id, $filter);
            }*/
        } catch (Exception $ex) {
            $pubArray = array();
        }

        return $pubArray;
    }

    /*
     * Ausgabe der Forschungsbereiche
     */

    private function make_single($fields, $param) {
        $hide = $hide = explode(',', $param['hide']);

        $lang = strpos(get_locale(), 'de') === 0 ? 'de' : 'en';
        $singlefield = '';
        $singlefield .= "<div class=\"cris-fields\">";

        foreach ($fields as $field) {
            $field = (array) $field;
            foreach ($field['attributes'] as $attribut => $v) {
                $field[$attribut] = $v;
            }
            unset($field['attributes']);
            $imgs = self::get_field_images($field['ID']);
            $id = $field['ID'];
            switch ($lang) {
                case 'en':
                    $title = (!empty($field['cfname_en'])) ? $field['cfname_en'] : $field['cfname'];
                    $description = (!empty($field['description_en'])) ? $field['description_en'] : $field['description'];
                    break;
                case 'de':
                default:
                    $title = (!empty($field['cfname'])) ? $field['cfname'] : $field['cfname_en'];
                    $description = (!empty($field['description'])) ? $field['description'] : $field['description_en'];
                    break;
            }
            $title = htmlentities($title, ENT_QUOTES);
            $description = strip_tags($description, '<br><br/><a><sup><sub><ul><ol><li>');

            if (!in_array('title', $hide))
                $singlefield .= "<h2>" . $title . "</h2>";

            if (count($imgs)) {
                $singlefield .= "<div class=\"cris-image\">";
                foreach($imgs as $img) {
                    if (isset($img->attributes['png180']) && mb_strlen($img->attributes['png180']) > 30) {
                       $singlefield .= "<p><img alt=\"". $img->attributes['description'] ."\" src=\"data:image/PNG;base64," . $img->attributes['png180'] . "\" width=\"180\" height=\"180\"><br />"
                        . "<span class=\"wp-caption-text\">" . (($img->attributes['description'] !='') ? $img->attributes['description'] : "") . "</span></p>";
                    }
                }
                $singlefield .= "</div>";
            }

            $singlefield .= $description;

            if (!in_array('projects', $hide)) {
                $projects = $this->get_field_projects($id);
                    if ($projects) {
                    $singlefield .= "<h3>" . __('Projekte', 'fau-cris') . ": </h3>";
                    $singlefield .= $projects;
                }
            }
            if (!in_array('contactpersons', $hide)) {
                $contactsArray = array();
                $contacts = explode("|", $field['contact_names']);
                $contactIDs = explode(",", $field['contact_ids']);
                if (count($contacts) > 0) {
                    foreach ($contacts as $i => $name) {
                        $nameparts = explode(":", $name);
                        $contactsArray[$contactIDs[$i]] = array(
                            'lastname' => $nameparts[0],
                            'firstname' => $nameparts[1]);
                    }
                    $singlefield .= "<h3>" . __('Kontaktpersonen', 'fau-cris') . ": </h3>";
                    $singlefield .= "<ul>";
                    foreach($contactsArray as $c_id => $contact) {
                        $singlefield .= "<li>";
                        $singlefield .= Tools::get_person_link($c_id, $contact['firstname'], $contact['lastname'], $this->cris_project_link, $this->cms, $this->pathPersonenseiteUnivis, $this->univis);
                        $singlefield .= "</li>";
                    }
                    $singlefield .= "</ul>";

                }
            }

            if (!in_array('persons', $hide)) {
                $persons = $this->get_field_persons($id);
                if ($persons) {
                    $singlefield .= "<h3>" . __('Beteiligte Wissenschaftler', 'fau-cris') . ": </h3>";
                    $singlefield .= "<ul>";
                    foreach ($persons as $p_id => $person) {
                        $singlefield .= "<li>";
                        $singlefield .= Tools::get_person_link($p_id, $person['firstname'], $person['lastname'], $this->cris_project_link, $this->cms, $this->pathPersonenseiteUnivis, $this->univis);
                        $singlefield .= "</li>";
                    }
                    $singlefield .= "</ul>";
                }
            }
            if (!in_array('publications', $hide)) {
                $publications = $this->get_field_publications($param);
                if ($publications) {
                    $singlefield .= "<h3>" . __('Publikationen', 'fau-cris') . ": </h3>";
                    $singlefield .= $publications;
                }
            }
        }
        $singlefield .= "</div>";
        return $singlefield;
    }


    private function make_custom_single($fields, $content, $param = array()) {
        $lang = strpos(get_locale(), 'de') === 0 ? 'de' : 'en';
        $field_details = array();
        $output = "<div class=\"cris-fields\">";;

        foreach ($fields as $field) {
            $field = (array) $field;
            foreach ($field['attributes'] as $attribut => $v) {
                $field[$attribut] = $v;
            }
            unset($field['attributes']);
            $imgs = self::get_field_images($field['ID']);
            $id = $field['ID'];
            switch ($lang) {
                case 'en':
                    $title = (!empty($field['cfname_en'])) ? $field['cfname_en'] : $field['cfname'];
                    $description = (!empty($field['description_en'])) ? $field['description_en'] : $field['description'];
                    break;
                case 'de':
                default:
                    $title = (!empty($field['cfname'])) ? $field['cfname'] : $field['cfname_en'];
                    $description = (!empty($field['description'])) ? $field['description'] : $field['description_en'];
                    break;
            }
            $field_details['#title#'] = htmlentities($title, ENT_QUOTES);
            $field_details['#description#'] = strip_tags($description, '<br><br/><a><sup><sub><ul><ol><li>');
            $field_details['#projects#'] = $this->get_field_projects($id);
            $field_details['#persons#'] = '';
            $field_details['#contactpersons#'] = '';
            $persons = $this->get_field_persons($id);
            if ($persons) {
                $field_details['#persons#'] .= "<ul>";
                foreach ($persons as $p_id => $person) {
                        $field_details['#persons#'] .= "<li>";
                        $field_details['#persons#'] .= Tools::get_person_link($p_id, $person['firstname'], $person['lastname'], $this->cris_project_link, $this->cms, $this->pathPersonenseiteUnivis, $this->univis);
                        $field_details['#persons#'] .= "</li>";
                    }
                $field_details['#persons#'] .= "</ul>";
            }
            $contactsArray = array();
            $contacts = explode("|", $field['contact_names']);
            $contactIDs = explode(",", $field['contact_ids']);
            if (count($contacts) > 0) {
                foreach ($contacts as $i => $name) {
                    $nameparts = explode(":", $name);
                    $contactsArray[$contactIDs[$i]] = array(
                        'lastname' => $nameparts[0],
                        'firstname' => $nameparts[1]);
                }
                $field_details['#contactpersons#'] .= "<ul>";
                foreach($contactsArray as $c_id => $contact) {
                    $field_details['#contactpersons#'] .= "<li>";
                    $field_details['#contactpersons#'] .= Tools::get_person_link($c_id, $contact['firstname'], $contact['lastname'], $this->cris_project_link, $this->cms, $this->pathPersonenseiteUnivis, $this->univis);
                    $field_details['#contactpersons#'] .= "</li>";
                }
                $field_details['#contactpersons#'] .= "</ul>";

            }

            $field_details['#publications#'] = '';
            $publications = $this->get_field_publications($param);
            if ($publications)
                $field_details['#publications#'] = $publications;
            $field_details['#image1#'] = '';
            if (count($imgs)) {
                $i = 1;
                foreach($imgs as $img) {
                    $field_details['#image'.$i.'#'] = "<div class=\"cris-image\">";
                    if (isset($img->attributes['png180']) && mb_strlen($img->attributes['png180']) > 30) {
                       $field_details['#image'.$i.'#'] .= "<p><img alt=\"". $img->attributes['description'] ."\" src=\"data:image/PNG;base64," . $img->attributes['png180'] . "\" width=\"180\" height=\"180\"><br />"
                        . "<span class=\"wp-caption-text\">" . (($img->attributes['description'] !='') ? $img->attributes['description'] : "") . "</span></p>";
                    $field_details['#image'.$i.'#'] .= "</div>";
                    }
                    $i++;
                }
            }
            $output .= strtr($content, $field_details);
        }
        $output .= "</div>";
        return $output;
    }

    private function make_list($fields) {
        $lang = strpos(get_locale(), 'de') === 0 ? 'de' : 'en';

        $fieldslist = "<ul class=\"cris-fields\">";

        foreach ($fields as $field) {
            $field = (array) $field;
            foreach ($field['attributes'] as $attribut => $v) {
                $field[$attribut] = $v;
            }
            unset($field['attributes']);
            switch ($lang) {
                case 'en':
                    $title = (!empty($field['cfname_en'])) ? $field['cfname_en'] : $field['cfname'];
                    break;
                case 'de':
                default:
                    $title = (!empty($field['cfname'])) ? $field['cfname'] : $field['cfname_en'];
                    break;
            }
            $title = htmlentities($title, ENT_QUOTES);
            global $post;
            $title = "<a href=\"" . Tools::get_item_url("forschungsbereich", $title, $field['ID'], $post->ID) . "\">" . $title . "</a>";

            $fieldslist .= "<li>" . $title . "</li>";
        }

        $fieldslist .= "</ul>";

        return $fieldslist;
    }

    private function get_field_projects($field = NULL) {
        require_once('class_Projekte.php');
        $liste = new Projekte();
        return $liste->fieldProj($field);
    }

    private function get_field_persons($field = NULL) {
        require_once('class_Projekte.php');
        $liste = new Projekte();
        return $liste->fieldPersons($field);
    }

    private function get_field_publications($param = array()) {

        require_once('class_Publikationen.php');
        $liste = new Publikationen('field', $param['field']);
        foreach ($param as $_k => $_v) {
            if (substr($_k, 0, 13) == 'publications_') {
                $args[substr($_k,13)] = $_v;
            }
        }
        $args['sc_type'] = 'default';
        if ($param['publications_orderby'] == 'year')
            return $liste->pubNachJahr ($args, $param['field']);
        if ($param['publications_orderby'] == 'type')
            return $liste->pubNachTyp ($args, $param['field']);
        return $liste->fieldPub($param['field'], $param['quotation'], false, $param['publications_limit']);
    }

    private function get_field_images($field) {
        $images = array();
        $imgString = CRIS_Dicts::$base_uri . "getrelated/Forschungsbereich/" . $field . "/FOBE_has_PICT";
        $imgXml = Tools::XML2obj($imgString);

        if ($imgXml['size'] != 0) {
            foreach ($imgXml as $img) {
                $_i = new CRIS_field_image($img);
                $images[$_i->ID] = $_i;
            }
        }
        return $images;
    }
}

class CRIS_fields extends CRIS_webservice {
    /*
     * publication requests, supports multiple organisation ids given as array.
     */

    public function by_orga_id($orgaID = null, &$filter = null) {
        if ($orgaID === null || $orgaID === "0")
            throw new Exception('Please supply valid organisation ID');

        if (!is_array($orgaID))
            $orgaID = array($orgaID);

        $requests = array();
        foreach ($orgaID as $_o) {
            $requests[] = sprintf("getrelated/Organisation/%d/fobe_has_orga", $_o);
        }
        return $this->retrieve($requests, $filter);
    }

    public function by_pers_id($persID = null, &$filter = null) {
        if ($persID === null || $persID === "0")
            throw new Exception('Please supply valid person ID');

        if (!is_array($persID))
            $persID = array($persID);

        $requests = array();
        foreach ($persID as $_p) {
            $requests[] = sprintf('getautorelated/Person/%s/PERS_2_PUBL_1', $_p);
        }
        return $this->retrieve($requests, $filter);
    }

    public function by_id($fieldID = null) {
        if ($fieldID === null || $fieldID === "0")
            throw new Exception('Please supply valid field of research ID');

        if (!is_array($fieldID))
            $fieldID = array($fieldID);

        $requests = array();
        foreach ($fieldID as $_p) {
            $requests[] = sprintf('get/Forschungsbereich/%d', $_p);
        }
        return $this->retrieve($requests);
    }

    public function by_project($projID = null) {
        if ($projID === null || $projID === "0")
            throw new Exception('Please supply valid publication ID');

        if (!is_array($projID))
            $projID = array($projID);

        $requests = array();
        foreach ($projID as $_p) {
            $requests[] = sprintf('getrelated/Project/%d/proj_has_publ', $_p);
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

        $fields = array();

        foreach ($data as $_d) {
            foreach ($_d as $field) {
                $p = new CRIS_field($field);
            if ($p->ID && ($filter === null || $filter->evaluate($p)))
                    $fields[$p->ID] = $p;
            }
        }

        return $fields;
    }

}

class CRIS_field extends CRIS_Entity {
    /*
     * object for single publication
     */

    public function __construct($data) {
        parent::__construct($data);
    }

}

class CRIS_field_image extends CRIS_Entity {
    /*
     * object for single publication
     */

    public function __construct($data) {
        parent::__construct($data);

        foreach ($data->relation as $_r) {
            if ($_r['type'] != "FOBE_has_PICT")
                continue;
            foreach($_r->attribute as $_a) {
                if ($_a['name'] == 'description') {
                    $this->attributes["description"] = (string) $_a->data;
                }
            }
        }
    }
}

# tests possible if called on command-line
if (!debug_backtrace()) {
    $p = new CRIS_Publications();
    $f = new CRIS_Filter(array("publyear__le" => 2016, "publyear__gt" => 2014, "peerreviewed__eq" => "Yes"));
    $publs = $p->by_orga_id("142285", $f);
    $order = "virtualdate";
    $formatter = new CRIS_formatter(NULL, NULL, $order, SORT_DESC);
    $res = $formatter->execute($publs);
    foreach ($res[$order] as $key => $value) {
        echo sprintf("%s: %s\n", $key, $value->attributes[$order]);
    }
}
