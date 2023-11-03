<?php

require_once( "class_Tools.php" );
require_once( "class_Webservice.php" );
require_once( "class_Filter.php" );
require_once( "class_Formatter.php" );

class Standardisierungen
{
    private array $options;
    public $output;

    public function __construct($einheit = '', $id = '', $page_lang = 'de', $sc_lang = 'de')
    {
        $this->options = (array) FAU_CRIS::get_options();
        $this->cms = 'wp';
        $this->pathPersonenseiteUnivis = '/person/';
        $this->orgNr = $this->options['cris_org_nr'];
        $this->suchstring = '';
        $this->univis = null;

        //$this->order = $this->options['cris_standardization_order'];
        $this->cris_standardizations_link = $this->options['cris_standardizations_link'] ?? 'none';
        if ((!$this->orgNr || $this->orgNr == 0) && $id == '') {
            $this->error= new \WP_Error(
                'cris-orgid-error',
                __('Bitte geben Sie die CRIS-ID der Organisation, Person oder Forschungsaktivität an.', 'fau-cris')
            );
        }
        
        if (in_array($einheit, array("person", "orga", "standardization"))) {
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
     * Ausgabe einer einzelnen Standardisierung
     */

    public function singleStandardization($hide = array())
    {
        $ws = new CRIS_standardizations();

        try {
            $standardizationArray = $ws->by_id($this->id);
        } catch (Exception $ex) {
            return;
        }

        if (!count($standardizationArray)) {
            $output = '<p>' . __('Es wurde leider kein Eintrag gefunden.', 'fau-cris') . '</p>';
            return $output;
        }

        $output = $this->make_single($standardizationArray, $hide);
        
        return $this->langdiv_open . $output . $this->langdiv_close;
    }

    public function standardizationListe($param = array(), $custom_text = '')
    {

        $standardizationArray = $this->fetch_standardizations($param['year'], $param['start'], $param['end'], $param['type']);

        if (!count($standardizationArray)) {
            $output = '<p>' . __('Es wurden leider kein Eintrag gefunden.', 'fau-cris') . '</p>';
            return $output;
        }

        // Gruppierung
        if ($param['orderby'] == 'year') {
            $group = 'year';
            $groupOrder = SORT_DESC;
        } elseif ($param['orderby'] == 'type') {
            $group = 'subtype';
            $groupOrder = SORT_ASC;
        } else {
            $group = null;
            $groupOrder = null;
        }
        // sortiere nach Erscheinungsdatum
        $sort = "venue_start";
        $sortOrder = SORT_DESC;
        $formatter = new CRIS_formatter($group, $groupOrder, $sort, $sortOrder);
        $standardizations = $formatter->execute($standardizationArray);
        $isGroupAccordion = ($param['display'] == 'accordion' && in_array($param['orderby'], ['year', 'type']));
        $isSingleAccordion = ($param['display'] == 'accordion' && $param['orderby'] == '');

        $output = '';
        if ($isGroupAccordion) {
            $output .= '[collapsibles expand-all-link="true"]';
        }
        foreach ($standardizations as $key => $stanGroup) {
            switch ($param['orderby']) {
                case 'year':
                    $subtitle = $key;
                    break;
                case 'type':
                    $subtitle = Tools::getTitle('standardizations', $key, $this->page_lang);
                    break;
                default:
                    $subtitle = '';
            }
            if ($isGroupAccordion) {
                $output .= sprintf('[collapse title="%1s" color="%2s" name="%3s"]', $subtitle, $param['accordion_color'], sanitize_title($subtitle));
            } elseif ($subtitle != '') {
                $output .= '<h3>' . $subtitle . '</h3>';
            }
            if ($param['sc_type'] == 'custom') {
                $output .= $this->make_custom($stanGroup, $param, $custom_text, $isSingleAccordion);
            } else {
                if ($isSingleAccordion) {
                    $output .= $this->make_single($stanGroup, $param, $isSingleAccordion);
                } else {
                    $output .= $this->make_list($stanGroup, $param);
                }
            }
            if ($isGroupAccordion) {
                $output .= '[/collapse]';
            }
        }
        if ($isGroupAccordion) {
            $output .= '[/collapsibles]';
        }
        return do_shortcode($this->langdiv_open . $output . $this->langdiv_close);
    }

    private function make_list($standardizations, $param = array(), $isSingleAccordion = false): string
    {
        global $post;
        $hide = $param['hide'];
        $standardizationList = ($isSingleAccordion
            ? '[collapsibles expand-all-link="true"]'
            : "<ul class=\"cris-standardizations\">");

        foreach($standardizations as $standardization) {
            $standardization = (array) $standardization;
            foreach ($standardization['attributes'] as $attribut => $v) {
                $standardization[$attribut] = $v;
            }
            unset($standardization['attributes']);

            $standardizationList .= ($isSingleAccordion ? sprintf('[collapse title="%1s" color="%2s" name="%3s"]', $standardization['title'], $param['accordion_color'], sanitize_title($standardization['title'])) : "<li>");
            if (!in_array('author', (array)$hide)) {
                $author = explode("|", $standardization['exportnames']);
                $authorIDs = explode(",", $standardization['persid']);
                $authorArray = array();
                foreach ($authorIDs as $i => $key) {
                    $nameparts = explode(":", $author[$i]);
                    $authorArray[] = array(
                        'id' => $key,
                        'lastname' => $nameparts[0],
                        'firstname' => array_key_exists(1, $nameparts) ? substr( $nameparts[1],
		                        0, 1 ) . 'fau-cris ' : '');
                }
                $authorList = array();
                foreach ($authorArray as $v) {
                    $authorList[] = Tools::get_person_link($v['id'], $v['firstname'], $v['lastname'], $this->cris_standardizations_link, $this->cms, $this->pathPersonenseiteUnivis, $this->univis, 0);
                }
                $authorHtml = implode(", ", $authorList);
                if ($authorHtml) {
                    $standardizationList .= $authorHtml . ": ";
                }
            }
            if (!in_array('title', (array)$hide)) {
                $title = $standardization['title'];
                $title = htmlentities($title, ENT_QUOTES);
                if ($title) {
                    $standardizationList .= "<span class=\"standardization-title\"><a href=\"" . Tools::get_item_url("standardization", $title, $standardization['ID'], $post->ID, $this->page_lang) . "\" title=\"" . __('Detailansicht auf cris.fau.de in neuem Fenster &ouml;ffnen', 'fau-cris') . "\">" . $title . "</a></span>";
                }
            }
            if (!in_array('number', (array)$hide)) {
                $documentNumber = $standardization['document_number'];
                if ($documentNumber) {
                    $standardizationList .= ', document ' . $documentNumber;
                }
            }
            if (!in_array('location', (array)$hide)) {
                $meetingVenue = $standardization['ws_location'];
                if ($meetingVenue) {
                    $standardizationList .= ', ' . $meetingVenue;
                }
            }
            if (!in_array('year', (array)$hide)) {
                $year = $standardization['year'];
                if ($year) {
                    $standardizationList .= ', ' . $year;
                }
            }
            $standardizationList .= ($isSingleAccordion ? '[/collapse]' : "</li>");
        }

        $standardizationList .= ($isSingleAccordion ? '[/collapsibles]' : "</ul>");

        return $standardizationList;
    }

    private function make_custom($standardizations, $param = array(), $custom_text = '', $isSingleAccordion = false)
    {
        if  ($param['display'] == 'no-list') {
            $tag_open = '<div class="cris-standardizations">';
            $tag_close = '</div>';
            $item_open = '<div class="cris-standardization">';
            $item_close = '</div>';
        } elseif ($isSingleAccordion) {
            $tag_open = '[collapsibles expand-all-link="true"]';
            $tag_close = '[/collapsibles]';
            $item_open = '[collapse title="%1s" color="%2s" name="%3s"]';
            $item_close = '[/collapse]';
        } else {
            $tag_open = '<ul class="cris-standardizations">';
            $tag_close= '</ul>';
            $item_open = '<li>';
            $item_close = '</li>';
        }

        $standardizationList = $tag_open;

        foreach($standardizations as $standardization) {
            $standardization = (array)$standardization;
            foreach ($standardization['attributes'] as $attribut => $v) {
                $standardization[$attribut] = $v;
            }
            unset($standardization['attributes']);
            $stanDetails['#title#'] = htmlentities($standardization['title'], ENT_QUOTES);
            $stanDetails['#name#'] = htmlentities($standardization['title'], ENT_QUOTES);
            $description = str_replace(["\n", "\t", "\r"], '', $standardization['abstract']);
            $stanDetails['#description#'] = strip_tags($description, '<br><a><sup><sub><ul><ol><li><b><p><i><strong><em>');
            $stanDetails['#documentNumber#'] = $standardization['document_number'];
            $stanDetails['#url#'] = $standardization['uri'];
            $stanDetails['#contributionTo#'] = $standardization['document_contribution'];
            $stanDetails['#acceptedIn#'] = $standardization['document_final'];
            $stanDetails['#committeeOrganization#'] = $standardization['ws_organisation'];
            $stanDetails['#committeeGroup#'] = $standardization['group'];
            $stanDetails['#meetingStart#'] = $standardization['venue_start'];
            $stanDetails['#meetingEnd#'] = $standardization['venue_end'];
            $stanDetails['#meetingVenue#'] = $standardization['ws_location'];
            $stanDetails['#meetingHost#'] = $standardization['host'];
            $stanDetails['#year#'] = $standardization['year'];
            $stanDetails['#type#'] = Tools::getName('standardizations', $standardization['subtype'], $this->sc_lang);
            $author = explode("|", $standardization['exportnames']);
            $authorIDs = explode(",", $standardization['persid']);
            $authorArray = array();
            foreach ($authorIDs as $i => $key) {
                $nameparts = explode(":", $author[$i]);
                $authorArray[] = array(
                    'id' => $key,
                    'lastname' => $nameparts[0],
                    'firstname' => array_key_exists(1, $nameparts) ? $nameparts[1] : '');
            }
            $authorList = array();
            foreach ($authorArray as $v) {
                $authorList[] = Tools::get_person_link($v['id'], $v['firstname'], $v['lastname'], $this->cris_standardizations_link, $this->cms, $this->pathPersonenseiteUnivis, $this->univis, 0);
            }
            $stanDetails['#author#'] = implode(", ", $authorList);

            if ($param['display'] == 'accordion') {
                $item_open_mod = sprintf($item_open, $param['accordion_title'], $param['accordion_color'], sanitize_title($stanDetails['#title#']));
            } else {
                $item_open_mod = $item_open;
            }
            $standardizationList .= strtr($item_open_mod . $custom_text . $item_close, $stanDetails);
        }

        $standardizationList .= $tag_close;

        return do_shortcode($standardizationList);
    }

    private function make_single($standardizations, $param = array(), $isSingleAccordion = false): string
    {
        $hide = (isset($param['hide']) && is_array($param['hide'])) ? $param['hide'] : [];
        if ($isSingleAccordion) {
            array_push($hide, ['hide']);
        }

        $standardizationList = ($isSingleAccordion
            ? '[collapsibles expand-all-link="true"]'
            : "<div class=\"cris-standardizations\">");

        foreach($standardizations as $standardization) {
            $standardization = (array) $standardization;
            foreach ($standardization['attributes'] as $attribut => $v) {
                $standardization[$attribut] = $v;
            }
            unset($standardization['attributes']);

            $standardizationList .= ($isSingleAccordion ? sprintf('[collapse title="%1s" color="%2s" name="%3s"]', $standardization['title'], $param['accordion_color'], sanitize_title($standardization['title'])) : "<div class=\"cris-standardization\">");

            if (!in_array('title', (array)$hide)) {
                $standardizationList .= "<h3>" . htmlentities($standardization['title'], ENT_QUOTES) . "</h3>";
            }

            if (!in_array('type', (array)$hide)) {
                $type = Tools::getName('standardizations', $standardization['subtype'], $this->sc_lang);
                if ($type) {
                    $standardizationList .= "<p>" . $type . '</p>';
                }
            }

            if (!in_array('description', (array)$hide)) {
                $description = str_replace(["\n", "\t", "\r"], '', $standardization['abstract']);
                $description = strip_tags($description, '<br><a><sup><sub><ul><ol><li><b><p><i><strong><em>');
                if ($description) {
                    $standardizationList .= "<div class=\"standardization-description\">" . $description . '</div>';
                }
            }
            if (!in_array('details', $hide)) {
                if (!in_array('documentNumber', (array)$hide) && !empty($standardization['document_number'])) {
                    $standardizationList .= "<strong>" . __('Nummer des Dokuments', 'fau-cris') . ': </strong>' . $standardization['document_number'];
                }
                if (!in_array('url', (array)$hide) && !empty($standardization['uri'])) {
                    $standardizationList .= "<br /><strong>" . __('URL', 'fau-cris') . ': </strong>' . $standardization['uri'];
                }
                if (!in_array('contributionTo', (array)$hide) && !empty($standardization['document_contribution'])) {
                    $standardizationList .= "<br /><strong>" . __('Beitrag zum Dokument (Nummer)', 'fau-cris') . ': </strong>' . $standardization['document_contribution'];
                }
                if (!in_array('acceptedIn', (array)$hide) && !empty($standardization['document_final'])) {
                    $standardizationList .= "<br /><strong>" . __('Übernahme in öffentliches Dokument (Nummer)', 'fau-cris') . ': </strong>' . $standardization['document_final'];
                }
                if (!in_array('authors', (array)$hide) && !empty($standardization['exportnames'])) {
                    $author = explode("|", $standardization['exportnames']);
                    $authorIDs = explode(",", $standardization['persid']);
                    $authorArray = array();
                    foreach ($authorIDs as $i => $key) {
                        $nameparts = explode(":", $author[$i]);
                        $authorArray[] = array(
                            'id' => $key,
                            'lastname' => $nameparts[0],
                            'firstname' => array_key_exists(1, $nameparts) ? $nameparts[1] : '');
                    }
                    $authorList = array();
                    foreach ($authorArray as $v) {
                        $authorList[] = Tools::get_person_link($v['id'], $v['firstname'], $v['lastname'], $this->cris_standardizations_link, $this->cms, $this->pathPersonenseiteUnivis, $this->univis, 0);
                    }
                    $authorHtml = implode(", ", $authorList);
                    $standardizationList .= "<br /><strong>" . __('Autor/-innen', 'fau-cris') . ': </strong>' . $authorHtml;
                }
            }
            if (!in_array('committee', $hide)) {
                $standardizationList .= '<h4>' . __('Gremium') . '</h4>';
                if (!in_array('organization', (array)$hide) && !empty($standardization['ws_organisation'])) {
                    $standardizationList .= "<strong>" . __('Organisation', 'fau-cris') . ': </strong>' . $standardization['ws_organisation'];
                }
                if (!in_array('group', (array)$hide) && !empty($standardization['group'])) {
                    $standardizationList .= "<br /><strong>" . __('Arbeitsgruppe', 'fau-cris') . ': </strong>' . $standardization['group'];
                }
            }
            if (!in_array('meeting', $hide)) {
                $standardizationList .= '<h4>' . __('Meeting') . '</h4>';
                if (!in_array('date', (array)$hide) && (!empty($standardization['venue_start']) || !empty($standardization['venue_end']))) {
                    $startDate = date_i18n(get_option('date_format'), strtotime($standardization['venue_start']));
                    $endDate = date_i18n(get_option('date_format'), strtotime($standardization['venue_end']));
                    $standardizationList .= "<br /><strong>" . __('Datum', 'fau-cris') . ': </strong>';
                    if (!empty($standardization['venue_start']) && !empty($standardization['venue_end'])) {
                        $standardizationList .= $startDate . ' &mdash; ' . $endDate;
                    } elseif (empty($standardization['venue_start']) xor empty($standardization['venue_end'])) {
                        $standardizationList .= $startDate . $endDate;
                    } elseif ($standardization['venue_start'] == $standardization['venue_end']) {
                        $standardizationList .= $startDate;
                    }
                }
                if (!in_array('host', (array)$hide) && !empty($standardization['host'])) {
                    $standardizationList .= "<br /><strong>" . __('Veranstalter', 'fau-cris') . ': </strong>' . $standardization['host'];
                }
                if (!in_array('venue', (array)$hide) && !empty($standardization['ws_location'])) {
                    $standardizationList .= "<br /><strong>" . __('Ort', 'fau-cris') . ': </strong>' . $standardization['ws_location'];
                }
            }

            $standardizationList .= ($isSingleAccordion ? '[/collapse]' : "</div>");
        }
        $standardizationList .= ($isSingleAccordion ? '[/collapsibles]' : "</div>");

        return $standardizationList;

    }

    /*
     * Holt Daten vom Webservice je nach definierter Einheit.
     */

    private function fetch_standardizations($year = '', $start = '', $end = '', $type = ''): array
    {

        $filter = Tools::standardizations_filter($year, $start, $end, $type, );
        $ws = new CRIS_standardizations();
        $standardizationArray = array();

        try {
            if ($this->einheit === "orga") {
                $standardizationArray = $ws->by_orga_id($this->id, $filter);
            }
            if ($this->einheit === "person") {
                $standardizationArray = $ws->by_pers_id($this->id, $filter);
            }
        } catch (Exception $ex) {
            $standardizationArray = array();
        }
        return $standardizationArray;
    }
}

class CRIS_standardizations extends CRIS_webservice
{
    /*
     * actients/grants requests
     */

    public function by_orga_id($orgaID = null, &$filter = null): WP_Error
    {
        if ($orgaID === null || $orgaID === "0") {
            return new \WP_Error(
                'cris-orgid-error',
                __('Bitte geben Sie die CRIS-ID der Organisation, Person oder Forschungsaktivität an.', 'fau-cris')
            );
        }

        if (!is_array($orgaID)) {
            $orgaID = array($orgaID);
        }

        $requests = array();
        foreach ($orgaID as $_o) {
            $requests[] = sprintf("getautorelated/organisation/%d/orga_card_stan", $_o);
        }
        return $this->retrieve($requests, $filter);
    }

    public function by_pers_id($persID = null, &$filter = null, $role = 'all'): array
    {
        if ($persID === null || $persID === "0") {
	        return new \WP_Error(
		        'cris-orgid-error',
		        __('Bitte geben Sie die CRIS-ID der Organisation, Person oder Forschungsaktivität an.', 'fau-cris')
	        );
        }

        if (!is_array($persID)) {
            $persID = array($persID);
        }

        $requests = array();
        foreach ($persID as $_p) {
            $requests[] = sprintf('getautorelated/person/%s/pers_card_stan', $_p);
        }
        return $this->retrieve($requests, $filter);
    }

    public function by_id($stanID = null): array
    {
        if ($stanID === null || $stanID === "0") {
	        return new \WP_Error(
		        'cris-orgid-error',
		        __('Bitte geben Sie die CRIS-ID der Organisation, Person oder Forschungsaktivität an.', 'fau-cris')
	        );
        }

        if (!is_array($stanID)) {
            $stanID = array($stanID);
        }

        $requests = array();
        foreach ($stanID as $_p) {
            $requests[] = sprintf('get/standardization/%d', $_p);
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

        $standardizations = array();

        foreach ($data as $_d) {
            foreach ($_d as $standardization) {
                $a = new CRIS_standardization($standardization);
                if ($a->ID && ($filter === null || $filter->evaluate($a))) {
                    $standardizations[$a->ID] = $a;
                }
            }
        }

        return $standardizations;
    }

}

class CRIS_standardization extends CRIS_Entity
{
    /*
     * object for single standardization
     */

    public function __construct($data)
    {
        parent::__construct($data);
    }
}
