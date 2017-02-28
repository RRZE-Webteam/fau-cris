<?php

require_once("class_Tools.php");
require_once("class_DB.php");
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

    public function fieldListe() {
        $sortby = 'cfname';
        $fieldsArray = $this->fetch_fields();

        if (!count($fieldsArray)) {
            $output = '<p>' . __('Es wurden leider keine Forschungsbereiche gefunden.', 'fau-cris') . '</p>';
            return $output;
        }
        if ($this->lang == 'en')
            $order = $sortby . '_en';
         else
            $order = $sortby;

        $formatter = new CRIS_formatter(NULL, NULL, $order, SORT_DESC);
        $res = $formatter->execute($fieldsArray);
        $fieldList = $res[$order];

        $output = '';
        $output .= $this->make_list($fieldList);

        return $output;
    }

    /*
     * Ausgabe eines einzelnen Forschungsbereichs
     */

    public function singleField($hide) {
        $ws = new CRIS_fields();

        try {
            $fieldsArray = $ws->by_id($this->id);
        } catch (Exception $ex) {
            return;
        }

        if (!count($fieldsArray))
            return;

        $output = $this->make_single($fieldsArray, $hide);

        return $output;
    }

    /*
     * Array aller Forschungsbereiche für die Synchonisierung
     */

    public function fieldsArray() {
        $sortby = 'cfname';
        $fieldsArray = $this->fetch_fields();

        if (!count($fieldsArray)) {
            return false;
        }
        if ($this->lang == 'en')
            $order = $sortby . '_en';
         else
            $order = $sortby;

        $formatter = new CRIS_formatter(NULL, NULL, $order, SORT_DESC);
        $res = $formatter->execute($fieldsArray);
        $fieldList = $res[$order];

        return $fieldList;
    }


    /* =========================================================================
     * Private Functions
      ======================================================================== */

    /*
     * Holt Daten vom Webservice je nach definierter Einheit.
     */

    private function fetch_fields() {
        $filter = Tools::field_filter();
        $ws = new CRIS_fields();

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

    private function make_single($fields, $hide = array()) {

        $lang = strpos(get_locale(), 'de') === 0 ? 'de' : 'en';
        $singlefield = '';
        $singlefield .= "<div class=\"cris-fields\">";

        foreach ($fields as $field) {
            $field = (array) $field;
            foreach ($field['attributes'] as $attribut => $v) {
                $field[$attribut] = $v;
            }
            unset($field['attributes']);

            $id = $field['ID'];
            $title = ($lang == 'en' && !empty($field['cfname_en'])) ? $field['cfname_en'] : $field['cfname'];
            $description = ($lang == 'en' && !empty($field['description_en'])) ? $field['description_en'] : $field['description'];

            if (!in_array('title', $hide))
                $singlefield .= "<h2>" . $title . "</h2>";
            $singlefield .= $description;

            if (!in_array('projects', $hide)) {
                $projects = $this->get_field_projects($id);
                    if ($projects) {
                    $singlefield .= "<h3>" . __('Projekte', 'fau-cris') . ": </h3>";
                    $singlefield .= $projects;
                }
            }
            if (!in_array('persons', $hide)) {
                $persons = $this->get_field_persons($id);
                if ($persons) {
                    $singlefield .= "<h3>" . __('Beteiligte Wissenschaftler', 'fau-cris') . ": </h3>";
                    $singlefield .= "<ul>";
                    foreach ($persons as $type => $person) {
                        foreach ($person as $id => $details) {
                            $singlefield .= "<li>";
                            $singlefield .= Tools::get_person_link($id, $details['firstname'], $details['lastname'], $this->cris_project_link, $this->cms, $this->pathPersonenseiteUnivis, $this->univis);
                            $singlefield .= "</li>";
                        }
                    }
                    $singlefield .= "</ul>";
                }
            }
            // TODO
            /* if (!in_array('publications', $hide)) {
                $singlefield .= "<h3>" . __('Publikationen', 'fau-cris') . ": </h3>";
                $singlefield .= "ToDo";
            }*/
        }
        $singlefield .= "</div>";
        return $singlefield;
    }


    /*
     * Ausgabe der Forschungsbereiche
     */

    private function make_list($fields) {
        $lang = strpos(get_locale(), 'de') === 0 ? 'de' : 'en';

        $fieldslist = "<ul class=\"cris-fields\">";

        foreach ($fields as $field) {
            $field = (array) $field;
            foreach ($field['attributes'] as $attribut => $v) {
                $field[$attribut] = $v;
            }
            unset($field['attributes']);

            $title = ($lang == 'en' && !empty($field['cfname_en'])) ? $field['cfname_en'] : $field['cfname'];

            if ($this->cms == 'wp') {
                $page = get_page_by_title($title);
                if ($page && !empty($page->guid)) {
                    $title = "<a href=\"" . $page->guid . "\">" . $title . "</a>";
                }
            }

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
        //var_dump($liste->fieldPersons($field));
    }

}

class CRIS_fields extends CRIS_DB {
    /*
     * publication requests, supports multiple organisation ids given as array.
     */

    public function by_orga_id($orgaID = null, &$filter = null, $order = 'cfname_de', $items = null) {
        if ($orgaID === null || $orgaID === "0")
            throw new Exception('Please supply valid organisation ID');

        $query = "SELECT *"
                . " FROM fieldsOfResearch f "
                . " JOIN organisation_fieldOfResearch of "
                . " ON f.id = of.p_id";
        if (count($orgaID) == 1) {
            $query .= " WHERE of.o_id = '" . $orgaID[0] . "'" ;
        } else {
            $query .= " WHERE of.o_id IN ('" . implode("', '", $orgaID) . "')";
        }
        if ($filter) {
            foreach ($filter as $_f) {
                $query .= ' AND (' . $_f . ')';
            }
        }
        //$query .= " ORDER BY f." . $order;
        if ($items != null) {
            $query .= " LIMIT " . $items;
        }
        $query .= ";";
        print $query . "<br />";
        return;
        return $this->get($query);
    }

    public function by_pers_id($persID = null, &$filter = null) {
    /*    if ($persID === null || $persID === "0")
            throw new Exception('Please supply valid person ID');

        $query = "SELECT *"
                . " FROM fieldsOfResearch f "
                . " JOIN organisation_fieldOfResearch of "
                . " ON f.id = of.p_id";
        if (count($orgaID) == 1) {
            $query .= " WHERE of.o_id = '" . $orgaID[0] . "'" ;
        } else {
            $query .= " WHERE of.o_id IN ('" . implode("', '", $orgaID) . "')";
        }
        if ($filter) {
            foreach ($filter as $_f) {
                $query .= ' AND (' . $_f . ')';
            }
        }
        //$query .= " ORDER BY f." . $order;
        if ($items != null) {
            $query .= " LIMIT " . $items;
        }
        $query .= ";";
        print $query . "<br />";
        return;
        return $this->get($query);*/
    }

    public function by_id($fieldID = null) {
        if ($fieldID === null || $fieldID === "0")
            throw new Exception('Please supply valid field of research ID');

        $query = "SELECT *"
                . " FROM fieldsOfResearch f ";
        if (count($fieldID) == 1) {
            $query .= " WHERE f.id = '" . $fieldID[0] . "'" ;
        } else {
            $query .= " WHERE p.id IN ('" . implode("', '", $fieldID) . "')";
        }
        $query .= ";";
        print $query . "<br />";
        return;
        return $this->get($query);
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