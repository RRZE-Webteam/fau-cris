<?php
namespace RRZE\Cris;

use RRZE\Cris\Tools;
use RRZE\Cris\CRIS_webservice;
use RRZE\Cris\CRIS_filter;
use RRZE\Cris\CRIS_formatter;
use  RRZE\Cris\Publikationen;
use RRZE\Cris\Projekte;
//require_once( "class_Tools.php" );
//require_once( "class_Webservice.php" );
//require_once( "class_Filter.php" );
//require_once( "class_Formatter.php" );

class Forschungsbereiche
{

    private array $options;
    public $output;

    public function __construct($einheit = '', $id = '', $page_lang = 'de', $sc_lang = 'de')
    {
        if (strpos($_SERVER['PHP_SELF'], "vkdaten/tools/")) {
            $this->cms = 'wbk';
            $this->options = CRIS::ladeConf();
            $this->pathPersonenseiteUnivis = $this->options['Pfad_Personenseite_Univis'] . '/';
        } else {
            $this->cms = 'wp';
            $this->options = (array) FAU_CRIS::get_options();
            $this->pathPersonenseiteUnivis = '/person/';
        }
        $this->orgNr = $this->options['cris_org_nr'];
        $this->suchstring = '';
        $this->univis = null;
        
        $this->cris_field_link = $this->options['cris_field_link'] ?? 'none';
        if ($this->cms == 'wbk' && $this->cris_field_link == 'person') {
            $this->univis = Tools::get_univis();
        }

        if ((!$this->orgNr || $this->orgNr == 0) && $id == '') {
            $this->error= new \WP_Error(
                'cris-orgid-error',
                __('Bitte geben Sie die CRIS-ID der Organisation oder des Forschungsbereichs an.', 'fau-cris')
            );
        }
        if (in_array($einheit, array("orga", "field"))) {
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
     * Ausgabe aller Forschungsbereiche
     */

    public function fieldListe($param = array()): string
    {

        $fieldsArray = $this->fetch_fields();

        if (!count($fieldsArray)) {
            $output = '<p>' . __('Es wurden leider keine Forschungsbereiche gefunden.', 'fau-cris') . '</p>';
            return $output;
        }
        $firstItem = reset($fieldsArray);
        if ($firstItem && isset($firstItem->attributes['relation left seq'])) {
            //if (array_key_exists('relation left seq', reset($fieldsArray)->attributes)) {
            $sortby = 'relation left seq';
            $orderby = $sortby;
        } else {
            $sortby = null;
            $orderby = __('O.A.', 'fau-cris');
        }
        $hide = $param['hide'];
        $formatter = new CRIS_formatter(null, null, $sortby, SORT_ASC);
        $res = $formatter->execute($fieldsArray);
        $list = $res[$orderby] ?? [];
        if ($param['limit'] != '') {
            $fieldList = array_slice($list, 0, $param['limit']);
        } else {
            $fieldList = $list;
        }
        $output = $this->make_list($fieldList);

        return $this->langdiv_open . $output . $this->langdiv_close;
    }

    /*
     * Ausgabe eines einzelnen Forschungsbereichs
     */

    public function singleField($param = array())
    {
        $ws = new CRIS_fields();
        try {
            $fieldsArray = $ws->by_id($this->id);
        } catch (Exception $ex) {
            return;
        }
        if (!count($fieldsArray)) {
            return;
        }

        $output = $this->make_single($fieldsArray, $param);

        return $this->langdiv_open . $output . $this->langdiv_close;
    }

    /*
     * Ausgabe eines einzelnen Forschungsbereichs per Custom-Shortcode
     */

    public function customField($content = '', $param = array())
    {
        $ws = new CRIS_fields();

        try {
            $fieldsArray = $ws->by_id($this->id);
        } catch (Exception $ex) {
            return;
        }

        if (!count($fieldsArray)) {
            return;
        }

        $output = $this->make_custom_single($fieldsArray, $content, $param);

        return $this->langdiv_open . $output . $this->langdiv_close;
    }

    /*
     * Array aller Forschungsbereiche für die Synchonisierung
     */

    public function fieldsArray($seed = false, $sortby = null)
    {
        $fieldsArray = $this->fetch_fields($seed);

        if (!count($fieldsArray)) {
            return false;
        }
        if ($sortby != null) {
            if ($this->sc_lang == 'en') {
                $sortby = $sortby . '_en';
            }
            $orderby = $sortby;
        } else {
            $firstItem = reset($fieldsArray);
            if ($firstItem && isset($firstItem->attributes['relation left seq'])) {
                //if (array_key_exists('relation left seq', reset($fieldsArray)->attributes)) {
                $sortby = 'relation left seq';
                $orderby = $sortby;
            } else {
                $sortby = null;
                $orderby = __('O.A.', 'fau-cris');
            }
        }

        $formatter = new CRIS_formatter(null, null, $sortby, SORT_ASC);
        $res = $formatter->execute($fieldsArray);
        $fieldList = $res[$orderby] ?? [];

        return $fieldList;
    }


    /* =========================================================================
     * Private Functions
      ======================================================================== */

    /*
     * Holt Daten vom Webservice je nach definierter Einheit.
     */

    private function fetch_fields($seed = false): array
    {
        $filter = Tools::field_filter();
        $ws = new CRIS_fields();
        if ($seed) {
            $ws->disable_cache();
        }
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

    /**
     * @param $fields
     * @param $param
     *
     * @return string
     */
    private function make_single($fields, $param): string
    {
        $hide = $param['hide'];

        $singlefield = "<div class=\"cris-fields\">";

        foreach ($fields as $field) {
            $field = (array) $field;
            foreach ($field['attributes'] as $attribut => $v) {
                $field[$attribut] = $v;
            }
            unset($field['attributes']);
            $imgs = self::get_field_images($field['ID']);
            $id = $field['ID'];
            switch ($this->sc_lang) {
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
            $description = str_replace(["\n", "\t", "\r"], '', $description);
            $description = strip_tags($description, '<br><a><sup><sub><ul><ol><li><b><p><i><strong><em>');
            $param['fsp'] = ($field['selektion'] == 'Forschungsschwerpunkt') ? true : false;
            
            if (!in_array('title', $hide)) {
                $singlefield .= "<h2>" . $title . "</h2>";
            }

            if (count($imgs)) {
                $singlefield .= "<div class=\"cris-image wp-caption " . $param['image_align'] .  "\">";
                foreach ($imgs as $img) {
                    foreach ($imgs as $img) {
                        $img_size = getimagesizefromstring(base64_decode($img->attributes['png180']));
                        $singlefield = "<div class=\"cris-image wp-caption " . $param['image_align']  . "\" style=\"width: " . $img_size[0] . "px;\">";
                        $img_description = ($img->attributes['description'] ??
                                             '');
                        if (isset($img->attributes['png180']) && mb_strlen($img->attributes['png180']) > 30) {
                            $singlefield .= "<img alt=\"Coverbild: ". $img_description ."\" src=\"data:image/PNG;base64," . $img->attributes['png180'] . "\" " . $img_size[3].">"
                                . "<p class=\"wp-caption-text\">" . $img_description . "</p>";
                            //$publication['image'] .= "<img alt=\"". $img->attributes['description'] ."\" src=\"\" width=\"\" height=\"\">" . $img_description;
                        }
                        $singlefield .= "</div>";
                    }
                }
                $singlefield .= "</div>";
            }

            $singlefield .= $description;

            if (!in_array('projects', $hide)
                && !is_array($param['field'])) {
                $projects = $this->get_field_projects($id);
                if ($projects) {
                    $singlefield .= "<h3>" . __('Projekte', 'fau-cris') . ": </h3>";
                    $singlefield .= $projects;
                }
            }
            if (!in_array('contactpersons', $hide)
                && !is_array($param['field'])
                && $field['contact_names'] != ''
                && $field['contact_ids'] != '') {
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
                    foreach ($contactsArray as $c_id => $contact) {
                        $singlefield .= "<li>";
                        $singlefield .= Tools::get_person_link($c_id, $contact['firstname'], $contact['lastname'], $this->cris_field_link, $this->cms, $this->pathPersonenseiteUnivis, $this->univis);
                        $singlefield .= "</li>";
                    }
                    $singlefield .= "</ul>";
                }
            }

            if (!in_array('persons', $hide)
                && !is_array($param['field'])) {
                $persons = $this->get_field_persons($id);
                if (!is_wp_error($persons)) {
                    $singlefield .= "<h3>" . __('Beteiligte Wissenschaftler', 'fau-cris') . ": </h3>";
                    $singlefield .= "<ul>";
                    foreach ($persons ?? [] as $p_id => $person) {
                        $singlefield .= "<li>";
                        $singlefield .= Tools::get_person_link($p_id, $person['firstname'], $person['lastname'], $this->cris_field_link, $this->cms, $this->pathPersonenseiteUnivis, $this->univis);
                        $singlefield .= "</li>";
                    }
                    $singlefield .= "</ul>";
                }
            }
            if (!in_array('publications', $hide)
                && !is_array($param['field'])) {
                $publications = $this->get_field_publications($param);
                if ($publications) {
                    $singlefield .= "<h3>" . __('Publikationen', 'fau-cris') . ": </h3>";
                    $singlefield .= $publications;
                }
            }
            if (is_array($param['field'])) {
                global $post;
                $singlefield .= "<p></p><a href=\"" . Tools::get_item_url("forschungsbereich", $title, $field['ID'], $post->ID, $this->page_lang) . "\">" . __('Mehr Informationen', 'fau-cris') . " &#8594;</a></p>";
            }
        }
        $singlefield .= "</div>";
        return $singlefield;
    }


    private function make_custom_single($fields, $content, $param = array()): string
    {
        $field_details = array();
        $output = "<div class=\"cris-fields\">";
        ;

        foreach ($fields as $field) {
            $field = (array) $field;
            foreach ($field['attributes'] as $attribut => $v) {
                $field[$attribut] = $v;
            }
            unset($field['attributes']);
            $id = $field['ID'];
            switch ($this->sc_lang) {
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
            $param['fsp'] = ($field['selektion'] == 'Forschungsschwerpunkt') ? true : false;
            $param['field'] = $id;
            
            $field_details['#title#'] = htmlentities($title, ENT_QUOTES);
            $field_details['#description#'] = strip_tags($description, '<br><br/><a><sup><sub><ul><ol><li>');
            $field_details['#projects#'] = '';
            if (strpos($content, '#projects#') !== false) {
                $field_details['#projects#'] = $this->get_field_projects($id);
            }
            $field_details['#persons#'] = '';
            if (strpos($content, '#persons#') !== false) {
                $persons = $this->get_field_persons($id);
                if (!is_wp_error($persons)) {
                    $field_details['#persons#'] .= "<ul>";
                    foreach ($persons as $p_id => $person) {
                        $field_details['#persons#'] .= "<li>";
                        $field_details['#persons#'] .= Tools::get_person_link($p_id, $person['firstname'], $person['lastname'], $this->cris_field_link, $this->cms, $this->pathPersonenseiteUnivis, $this->univis);
                        $field_details['#persons#'] .= "</li>";
                    }
                    $field_details['#persons#'] .= "</ul>";
                }
            }
            $field_details['#contactpersons#'] = '';
            if (strpos($content, '#contactpersons#') !== false) {
                $contactsArray = array();
                $contacts = explode("|", $field['contact_names']);
                $contactIDs = explode(",", $field['contact_ids']);
                if ($field['contact_names'] !='' && count($contacts) > 0) {
                    foreach ($contacts as $i => $name) {
                        $nameparts = explode(":", $name);
                        $contactsArray[$contactIDs[$i]] = array(
                            'lastname' => $nameparts[0],
                            'firstname' => $nameparts[1]);
                    }
                    $field_details['#contactpersons#'] .= "<ul>";
                    foreach ($contactsArray as $c_id => $contact) {
                        $field_details['#contactpersons#'] .= "<li>";
                        $field_details['#contactpersons#'] .= Tools::get_person_link($c_id, $contact['firstname'], $contact['lastname'], $this->cris_field_link, $this->cms, $this->pathPersonenseiteUnivis, $this->univis);
                        $field_details['#contactpersons#'] .= "</li>";
                    }
                    $field_details['#contactpersons#'] .= "</ul>";
                }
            }
            $field_details['#publications#'] = '';
            if (strpos($content, '#publications#') !== false) {
                $publications = $this->get_field_publications($param);
                if ($publications) {
                    $field_details['#publications#'] = $publications;
                }
            }
            $field_details['#project_publications#'] = '';
            if (strpos($content, '#project_publications#') !== false) {
                $project_publications = $this->get_field_publications($param, 'field_proj');
                if ($project_publications) {
                    $field_details['#project_publications#'] = $project_publications;
                }
            }
            $field_details['#image1#'] = '';
            $field_details['#images#'] = '';
            if (strpos($content, '#image') !== false) {
                $imgs = self::get_field_images($field['ID']);
                if (count($imgs)) {
                    $i = 1;
                    foreach ($imgs as $img) {
                        if (isset($img->attributes['png180']) && mb_strlen($img->attributes['png180']) > 30) {
                            $img_description = (isset($img->attributes['description'])? "<p class=\"wp-caption-text\">" . $img->attributes['description'] . "</p>" : '');
                            $field_details['#image'.$i.'#'] = "<div class=\"cris-image wp-caption " . $param['image_align'] .  "\">" . "<img alt=\"\" src=\"data:image/PNG;base64," . $img->attributes['png180'] . "\" width=\"\" height=\"\">" . $img_description . "</div>";
                            $field_details['#images#'] .= $field_details['#image'.$i.'#'];
                        }
                        $i++;
                    }
                }
            }
            $field_details['#image#'] = $field_details['#image1#'];
            $output .= strtr($content, $field_details);
        }
        $output .= "</div>";
        return $output;
    }

    private function make_list($fields): string
    {
        $fieldslist = "<ul class=\"cris-fields\">";

        foreach ($fields as $field) {
            $field = (array) $field;
            foreach ($field['attributes'] as $attribut => $v) {
                $field[$attribut] = $v;
            }
            unset($field['attributes']);
            switch ($this->sc_lang) {
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
            $title = "<a href=\"" . Tools::get_item_url("forschungsbereich", $title, $field['ID'], $post->ID, $this->page_lang) . "\">" . $title . "</a>";

            $fieldslist .= "<li>" . $title . "</li>";
        }

        $fieldslist .= "</ul>";

        return $fieldslist;
    }

    private function get_field_projects($field = null)
    {
//        require_once( 'class_Projekte.php' );
        $liste = new Projekte('field', $field, $this->sc_lang);
        if (isset($liste->error) && is_wp_error($liste->error)) {
            return $liste->error->get_error_message();
        } else {
            return $liste->fieldProj($field);
        }
    }

    private function get_field_persons($field = null)
    {
//        require_once( 'class_Projekte.php' );
        $liste = new Projekte('field', $field, $this->sc_lang);
        if (isset($liste->error) && is_wp_error($liste->error)) {
            return $liste->error->get_error_message();
        } else {
            return $liste->fieldPersons($field);
        }
    }

    private function get_field_publications($param = array(), $entity = 'field'): string
    {
//        require_once( 'class_Publikationen.php' );
        if ($param['publications_notable'] == '1') {
            $entity = 'field_notable';
        }
        $liste = new Publikationen($entity, $param['field'], '', $this->page_lang, $this->sc_lang);
        foreach ($param as $_k => $_v) {
            if (substr($_k, 0, 13) == 'publications_') {
                $args[substr($_k, 13)] = $_v;
            }
        }
        $args['sc_type'] = 'default';
        $args['quotation'] = $param['quotation'];
        $args['display_language'] = $this->sc_lang;
        $args['showimage'] = $param['showimage'];
        $args['image_align'] = $param['image_align'];
        $args['image_position'] = $param['image_position'];
        $args['format'] = $param['publications_format'];
        if ($param['publications_orderby'] == 'year') {
            return $liste->pubNachJahr($args, $param['field'], '', $param['fsp']);
        }
        if ($param['publications_orderby'] == 'type') {
            return $liste->pubNachTyp($args, $param['field'], '', $param['fsp']);
        }
        return $liste->fieldPub($param, false);
    }

    private function get_field_images($field): array
    {
        $images = array();
        $imgString = CRIS_Dicts::$base_uri . "getrelated/Forschungsbereich/" . $field . "/FOBE_has_PICT";
        $imgXml = Tools::XML2obj($imgString);

        if (!is_wp_error($imgXml) && isset($imgXml['size']) && $imgXml['size'] != 0) {
            foreach ($imgXml as $img) {
                $_i = new CRIS_field_image($img);
                $images[$_i->ID] = $_i;
            }
        }
        return $images;
    }
}

class CRIS_fields extends CRIS_webservice
{
    /*
     * publication requests, supports multiple organisation ids given as array.
     */

    public function by_orga_id($orgaID = null, &$filter = null): array
    {
        if ($orgaID === null || $orgaID === "0") {
	        return new \WP_Error(
		        'cris-orgid-error',
		        __('Bitte geben Sie die CRIS-ID der Organisation oder des Forschungsbereichs an.', 'fau-cris')
	        );
        }

        if (!is_array($orgaID)) {
            $orgaID = array($orgaID);
        }

        $requests = array();
        foreach ($orgaID as $_o) {
            $requests[] = sprintf("getrelated/Organisation/%d/fobe_has_orga", $_o);
        }
        return $this->retrieve($requests, $filter);
    }

    public function by_pers_id($persID = null, &$filter = null): array
    {
        if ($persID === null || $persID === "0") {
	        return new \WP_Error(
		        'cris-personid-error',
		        __('Bitte geben Sie die CRIS-ID  des Forschungsbereichs an.', 'fau-cris')
	        );
        }

        if (!is_array($persID)) {
            $persID = array($persID);
        }

        $requests = array();
        foreach ($persID as $_p) {
            $requests[] = sprintf('getautorelated/Person/%s/PERS_2_PUBL_1', $_p);
        }
        return $this->retrieve($requests, $filter);
    }

    public function by_id($fieldID = null): array
    {
        if ($fieldID === null || $fieldID === "0") {
            throw new Exception('Please supply valid field of research ID');
        }

        if (!is_array($fieldID)) {
            $fieldID = array($fieldID);
        }

        $requests = array();
        foreach ($fieldID as $_p) {
            $requests[] = sprintf('get/Forschungsbereich/%d', $_p);
        }
        return $this->retrieve($requests);
    }

    public function by_project($projID = null): array
    {
        if ($projID === null || $projID === "0") {
	        return new \WP_Error(
		        'cris-orgid-error',
		        __('Bitte geben Sie die CRIS-ID der Organisation oder des Forschungsbereichs an.', 'fau-cris')
	        );
        }

        if (!is_array($projID)) {
            $projID = array($projID);
        }

        $requests = array();
        foreach ($projID as $_p) {
            $requests[] = sprintf('getrelated/Project/%d/proj_has_publ', $_p);
        }
        return $this->retrieve($requests);
    }

    private function retrieve($reqs, &$filter = null): array
    {
        if ($filter !== null && !$filter instanceof CRIS_filter) {
            $filter = new CRIS_filter($filter);
        }

        $data = array();
        foreach ($reqs as $_i) {
            $_data = $this->get($_i, $filter);
            if (!is_wp_error($_data)) {
                $data[] = $_data;
            }
        }

        $fields = array();

        foreach ($data as $_d) {
            foreach ($_d as $field) {
                $p = new CRIS_field($field);
                if ($p->ID && ($filter === null || $filter->evaluate($p))) {
                    $fields[$p->ID] = $p;
                }
            }
        }

        return $fields;
    }
}

class CRIS_field extends CRIS_Entity
{
    /*
     * object for single publication
     */

    public function __construct($data)
    {
        parent::__construct($data);
    }
}

class CRIS_field_image extends CRIS_Entity
{
    /*
     * object for single publication
     */

    public function __construct($data)
    {
        parent::__construct($data);

        foreach ($data->relation as $_r) {
            if ($_r['type'] != "FOBE_has_PICT") {
                continue;
            }
            foreach ($_r->attribute as $_a) {
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
    $formatter = new CRIS_formatter(null, null, $order, SORT_DESC);
    $res = $formatter->execute($publs);
    foreach ($res[$order] as $key => $value) {
        echo sprintf("%s: %s\n", $key, $value->attributes[$order]);
    }
}
