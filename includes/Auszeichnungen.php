<?php
namespace RRZE\Cris;
defined('ABSPATH') || exit;

use RRZE\Cris\Tools;
use RRZE\Cris\Webservice;
use RRZE\Cris\Filter;
use RRZE\Cris\Formatter;

class Auszeichnungen
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


        $this->order = $this->options['cris_award_order'];
        $this->cris_award_link = $this->options['cris_award_link'] ?? 'none';
        if ($this->cms == 'wbk' && $this->cris_award_link == 'person') {
            $this->univis = Tools::get_univis();
        }

        if ((!$this->orgNr || $this->orgNr == 0) && $id == '') {
            $this->error= new \WP_Error(
                'cris-orgid-error',
                __('Bitte geben Sie die CRIS-ID der Organisation, Person oder Auszeichnung an.', 'fau-cris')
            );
        }
        if (in_array($einheit, array("person", "orga", "award", "awardnameid"))) {
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
     * Ausgabe aller Auszeichnungen ohne Gliederung
     */

    public function awardsListe($param = array(), $content = ''): string
    {
        $year = (isset($param['year']) && $param['year'] != '') ? $param['year'] : '';
        $start = (isset($param['start']) && $param['start'] != '') ? $param['start'] : '';
        $end = (isset($param['end']) && $param['end'] != '') ? $param['end'] : '';
        $type = (isset($param['type']) && $param['type'] != '') ? $param['type'] : '';
        $awardnameid = (isset($param['awardnameid']) && $param['awardnameid'] != '') ? $param['awardnameid'] : '';
        $showname = (isset($param['showname']) && $param['showname'] != '') ? $param['showname'] : 1;
        $showyear = (isset($param['showyear']) && $param['showyear'] != '') ? $param['showyear'] : 1;
        $showawardname = (isset($param['showawardname']) && $param['showawardname'] != '') ? $param['showawardname'] : 1;
        $display = (isset($param['display']) && $param['display'] != '') ? $param['display'] : 'list';
        $limit = (isset($param['limit']) && $param['limit'] != '') ? $param['limit'] : '';

        $awardArray = $this->fetch_awards($year, $start, $end, $type, $awardnameid);
        if (!count($awardArray)) {
            $output = '<p>' . __('Es wurden leider keine Auszeichnungen gefunden.', 'fau-cris') . '</p>';
            return $output;
        }

        $order = "year award";
        $formatter = new Formatter(null, null, $order, SORT_DESC);
        $res = $formatter->execute($awardArray);
        if ($limit != '') {
            $awardList = array_slice($res[$order], 0, $limit);
        } else {
            $awardList = $res[$order];
        }

        $output = '';

        if ($content == '') {
            if ($display == 'gallery') {
                $output .= $this->make_gallery($awardList, $showname, $showyear, $showawardname);
            } else {
                $output .= $this->make_list($awardList, $showname, $showyear, $showawardname);
            }
        } else {
            $output .= $this->make_custom_list($awardList, $content, $param);
        }

        return $this->langdiv_open . $output . $this->langdiv_close;
    }

    /*
     * Ausgabe aller Auszeichnungen nach Jahren gegliedert
     */

    public function awardsNachJahr($param = array()): string
    {
        $year = (isset($param['year']) && $param['year'] != '') ? $param['year'] : '';
        $start = (isset($param['start']) && $param['start'] != '') ? $param['start'] : '';
        $end = (isset($param['end']) && $param['end'] != '') ? $param['end'] : '';
        $type = (isset($param['type']) && $param['type'] != '') ? $param['type'] : '';
        $awardnameid = (isset($param['awardnameid']) && $param['awardnameid'] != '') ? $param['awardnameid'] : '';
        $showname = (isset($param['showname']) && $param['showname'] != '') ? $param['showname'] : 1;
        $showyear = (isset($param['showyear']) && $param['showyear'] != '') ? $param['showyear'] : 0;
        $showawardname = (isset($param['showawardname']) && $param['showawardname'] != '') ? $param['showawardname'] : 1;
        $display = (isset($param['display']) && $param['display'] != '') ? $param['display'] : 'list';
        $order2 = (isset($param['order2']) && $param['order2'] != '') ? $param['order2'] : 'name';
        $format = (isset($param['format']) && $param['format'] != '') ? $param['format'] : '';

        $awardArray = $this->fetch_awards($year, $start, $end, $type, $awardnameid);

        if (!count($awardArray)) {
            $output = '<p>' . __('Es wurden leider keine Auszeichnungen gefunden.', 'fau-cris') . '</p>';
            return $output;
        }

        if ($order2 == 'year') {
            $formatter = new Formatter("year award", SORT_DESC, "year award", SORT_DESC);
        } else {
            $formatter = new Formatter("year award", SORT_DESC, "exportnames", SORT_ASC);
        }
        $awardList = $formatter->execute($awardArray);

        $output = '';
        if (shortcode_exists('collapsibles') && $format == 'accordion') {
            $shortcode_data = '';
            if (empty($year) || strpos($year, ',') !== false) {
                $openfirst = ' load="open"';
                $expandall = ' expand-all-link="true"';
            } else {
                $openfirst = '';
                $expandall = '';
            }
            foreach ($awardList as $array_year => $awards) {
                if ($display == 'gallery') {
                    $shortcode_data .= do_shortcode('[collapse title="' . $array_year . '"' . $openfirst . ']' . $this->make_gallery($awards, $showname, $showyear, $showawardname) . '[/collapse]');
                } else {
                    $shortcode_data .= do_shortcode('[collapse title="' . $array_year . '"' . $openfirst . ']' . $this->make_list($awards, $showname, $showyear, $showawardname) . '[/collapse]');
                }
                $openfirst = '';
            }
            $output .= do_shortcode('[collapsibles ' . $expandall . ']' . $shortcode_data . '[/collapsibles]');
        } else {
            foreach ($awardList as $array_year => $awards) {
                if (count($awards) < 1) {
                    return $output;
                }
                if (empty($year)) {
                    $output .= '<h3 class="clearfix clear">';
                    $output .= $array_year;
                    $output .= '</h3>';
                }
                if ($display == 'gallery') {
                    $output .= $this->make_gallery($awards, $showname, $showyear, $showawardname);
                } else {
                    $output .= $this->make_list($awards, $showname, $showyear, $showawardname);
                }
            }
        }
        return $this->langdiv_open . $output . $this->langdiv_close;
    }

    /*
     * Ausgabe aller Auszeichnungen nach Auszeichnungstypen gegliedert
     */

    public function awardsNachTyp($param = array()): string
    {
        $year = (isset($param['year']) && $param['year'] != '') ? $param['year'] : '';
        $start = (isset($param['start']) && $param['start'] != '') ? $param['start'] : '';
        $end = (isset($param['end']) && $param['end'] != '') ? $param['end'] : '';
        $type = (isset($param['type']) && $param['type'] != '') ? $param['type'] : '';
        $awardnameid = (isset($param['awardnameid']) && $param['awardnameid'] != '') ? $param['awardnameid'] : '';
        $showname = (isset($param['showname']) && $param['showname'] != '') ? $param['showname'] : 1;
        $showyear = (isset($param['showyear']) && $param['showyear'] != '') ? $param['showyear'] : 0;
        $showawardname = (isset($param['showawardname']) && $param['showawardname'] != '') ? $param['showawardname'] : 1;
        $display = (isset($param['display']) && $param['display'] != '') ? $param['display'] : '';
        $order2 = (isset($param['order2']) && $param['order2'] != '') ? $param['order2'] : 'year';
        $format = (isset($param['format']) && $param['format'] != '') ? $param['format'] : '';

        $awardArray = $this->fetch_awards($year, $start, $end, $type, $awardnameid);

        if (!count($awardArray)) {
            $output = '<p>' . __('Es wurden leider keine Auszeichnungen gefunden.', 'fau-cris') . '</p>';
            return $output;
        }

        // Auszeichnungstypen sortieren
        $order = $this->order;
        if ($order[0] != '' && array_search($order[0], array_column(Dicts::$typeinfos['awards'], 'short'))) {
            foreach ($order as $key => $value) {
                $order[$key] = Tools::getType('awards', $value);
            }
        } else {
            $order = Tools::getOrder('awards');
        }

        // sortiere nach Typenliste, innerhalb des Typs nach Name aufwärts sortieren
        if ($order2 == 'name') {
            $formatter = new Formatter("type of award", array_values($order), "exportnames", SORT_ASC);
        } else {
            $formatter = new Formatter("type of award", array_values($order), "year award", SORT_DESC);
        }
        $awardList = $formatter->execute($awardArray);
        $output = '';
        if (shortcode_exists('collapsibles') && $format == 'accordion') {
            $shortcode_data = '';
            if (!empty($type) && strpos($type, ',') !== false) {
                $openfirst = ' load="open"';
                $expandall = ' expand-all-link="true"';
            } else {
                $openfirst = '';
                $expandall = '';
            }
            foreach ($awardList as $array_type => $awards) {
                $title = Tools::getTitle('awards', $array_type, $this->page_lang);
                if ($display == 'gallery') {
                    $shortcode_data .= do_shortcode('[collapse title="' . $title . '"' .$openfirst . ']' . $this->make_gallery($awards, $showname, $showyear, $showawardname) . '[/collapse]');
                } else {
                    $shortcode_data .= do_shortcode('[collapse title="' . $title . '"' .$openfirst . ']' . $this->make_list($awards, $showname, $showyear, $showawardname) . '[/collapse]');
                }
                $openfirst = '';
            }
            $output .= do_shortcode('[collapsibles ' . $expandall . ']' . $shortcode_data . '[/collapsibles]');
        } else {
            foreach ($awardList as $array_type => $awards) {
                if (empty($type)) {
                    $title = Tools::getTitle('awards', $array_type, $this->page_lang);
                    $output .= '<h3 class="clearfix clear">';
                    $output .= $title;
                    $output .= "</h3>";
                }
                if ($display == 'gallery') {
                    $output .= $this->make_gallery($awards, $showname, $showyear, $showawardname);
                } else {
                    $output .= $this->make_list($awards, $showname, $showyear, $showawardname);
                }
            }
        }
        return $this->langdiv_open . $output . $this->langdiv_close;
    }

    /*
     * Ausgabe einer einzelnen Auszeichnung
     */

    public function singleAward($showname = 1, $showyear = 0, $showawardname = 1, $display = 'list')
    {
        $ws = new CRIS_awards();

        try {
            $awardArray = $ws->by_id($this->id);
        } catch (Exception $ex) {
            return;
        }

        if (!count($awardArray)) {
            $output = '<p>' . __('Es wurden leider keine Auszeichnungen gefunden.', 'fau-cris') . '</p>';
            return $output;
        }

        if ($display == 'gallery') {
            $output = $this->make_gallery($awardArray, $showname, $showyear, $showawardname);
        } else {
            $output = $this->make_list($awardArray, $showname, $showyear, $showawardname);
        }

        return $this->langdiv_open . $output . $this->langdiv_close;
    }

    /*
     * Ausgabe eines Awards per Custom-Shortcode
     */

    public function customAward($content = '', $param = array())
    {
        $ws = new CRIS_awards();
        try {
            $awardArray = $ws->by_id($this->id);
        } catch (Exception $ex) {
            return;
        }

        if (!count($awardArray)) {
            $output = '<p>' . __('Es wurden leider keine Auszeichnungen gefunden.', 'fau-cris') . '</p>';
            return $output;
        }

        $output = $this->make_custom_list($awardArray, $content, $param);
        return $this->langdiv_open . $output . $this->langdiv_close;
    }

    /* =========================================================================
     * Private Functions
      ======================================================================== */

    /*
     * Holt Daten vom Webservice je nach definierter Einheit.
     */

    private function fetch_awards($year = '', $start = '', $end = '', $type = '', $awardnameid = ''): array
    {
        if (!isset($this->einheit) || !isset($this->id)) {
            return array();
        }

        $filter = Tools::award_filter($year, $start, $end, $type);

        $ws = new CRIS_awards();
        $awardArray = array();

        try {
            if ($this->einheit === "orga") {
                $awardArray = $ws->by_orga_id($this->id, $filter);
            }
            if ($this->einheit === "person") {
                $awardArray = $ws->by_pers_id($this->id, $filter);
            }
            if ($this->einheit === "awardnameid") {
                $awardArray = $ws->by_awardtype_id($this->id, $filter);
            }
        } catch (Exception $ex) {
            $awardArray = array();
        }
        return $awardArray;
    }

    /*
     * Ausgabe der Awards
     */

    private function make_list($awards, $name = 1, $year = 1, $awardname = 1): string
    {
        $awardlist = "<ul class=\"cris-awards\">";

        foreach ($awards as $award) {
            $award = (array) $award;
            foreach ($award['attributes'] as $attribut => $v) {
                $award[$attribut] = $v;
            }
            unset($award['attributes']);

            $preistraeger = explode("|", $award['exportnames']);
            $preistraegerIDs = explode(",", $award['relpersid']);
            $preistraegerArray = array();
            foreach ($preistraegerIDs as $i => $key) {
                $nameparts = explode(":", $preistraeger[$i]);
                $preistraegerArray[] = array(
                    'id' => $key,
                    'lastname' => $nameparts[0],
                    'firstname' => array_key_exists(1, $nameparts) ? $nameparts[1] : '');
            }
            $preistraegerList = array();
            foreach ($preistraegerArray as $pt) {
                $preistraegerList[] = Tools::get_person_link($pt['id'], $pt['firstname'], $pt['lastname'], $this->cris_award_link, $this->cms, $this->pathPersonenseiteUnivis, $this->univis, 0);
            }
            $preistraeger_html = implode(", ", $preistraegerList);

            if (!empty($award['award_name'])) {
                $award_name = $award['award_name'];
            } elseif (!empty($award['award_name_manual'])) {
                $award_name = $award['award_name_manual'];
            }
            if (!empty($award['award_organisation'])) {
                $organisation = $award['award_organisation'];
            } elseif (!empty($award['award_organisation_manual'])) {
                $organisation = $award['award_organisation_manual'];
            } else {
                $organisation = null;
            }
            $award_year = $award['year award'];

            $awardlist .= "<li>";
            if ($year == 1 && $name == 1) {
                $awardlist .= (!empty($preistraeger_html) ? $preistraeger_html : "")
                        . (($awardname == 1) ? ": <strong>" . $award_name . "</strong> "
                        . ((isset($organisation) && $award['type of award'] != 'Akademie-Mitgliedschaft') ? " (" . $organisation . ")" : "") : "")
                        . (!empty($award_year) ? " &ndash; " . $award_year : "");
            } elseif ($year == 1 && $name == 0) {
                $awardlist .= (!empty($award_year) ? $award_year . ": " : "")
                        . (($awardname == 1) ? "<strong>" . $award_name . "</strong> "
                        . ((isset($organisation) && $award['type of award'] != 'Akademie-Mitgliedschaft') ? " (" . $organisation . ")" : "") : "");
            } elseif ($year == 0 && $name == 1) {
                $awardlist .= (!empty($preistraeger_html) ? $preistraeger_html . ": " : "")
                        . (($awardname == 1) ? "<strong>" . $award_name . "</strong> "
                        . ((isset($organisation) && $award['type of award'] != 'Akademie-Mitgliedschaft') ? " (" . $organisation . ")" : "") : "");
            } else {
                $awardlist .= "<strong>" . $award_name . "</strong>"
                        . ((isset($organisation) && $award['type of award'] != 'Akademie-Mitgliedschaft') ? " (" . $organisation . ")" : "");
            }
            $awardlist .= "</li>";
        }

        $awardlist .= "</ul>";
        return $awardlist;
    }

    private function make_custom_list($awards, $custom_text = '', $param = array())
    {
        switch ($param['display']) {
            case 'accordion':
                $tag_open = '[collapsibles expand-all-link="true"]';
                $tag_close = '[/collapsibles]';
                $item_open = '[collapse title="%1s" color="%2s" name="%3s"]';
                $item_close = '[/collapse]';
                break;
            case 'no-list':
                $tag_open = '<div class="cris-awards">';
                $tag_close = '</div>';
                $item_open = '<div class="cris-award">';
                $item_close = '</div>';
                break;
            case 'gallery':
                $tag_open = '<ul class="cris-awards cris-gallery">';
                $tag_close = '</ul>';
                $item_open = '<li class="cris-award">';
                $item_close = '</li>';
                break;
            case 'list':
            default:
                $tag_open = '<ul class="cris-awards">';
                $tag_close = '</ul>';
                $item_open = '<li>';
                $item_close = '</li>';
        }

        $awardlist = $tag_open;

        foreach ($awards as $award) {
            $award = (array) $award;
            foreach ($award['attributes'] as $attribut => $v) {
                $award[$attribut] = $v;
            }
            unset($award['attributes']);

            $preistraeger = explode("|", $award['exportnames']);
            $preistraegerIDs = explode(",", $award['relpersid']);
            $preistraegerArray = array();
            foreach ($preistraegerIDs as $i => $key) {
                $nameparts = explode(":", $preistraeger[$i]);
                $preistraegerArray[] = array(
                    'id' => $key,
                    'lastname' => $nameparts[0],
                    'firstname' => array_key_exists(1, $nameparts) ? $nameparts[1] : '');
            }
            $preistraegerList = array();
            foreach ($preistraegerArray as $pt) {
                $preistraegerList[] = Tools::get_person_link($pt['id'], $pt['firstname'], $pt['lastname'], $this->cris_award_link, $this->cms, $this->pathPersonenseiteUnivis, $this->univis, 0);
            }
            $preistraeger_html = implode(", ", $preistraegerList);

            $award_details = array();
            switch ($this->page_lang) {
                case 'en':
                    $subtitle = ($award['description_subtitle_en'] != '') ? $award['description_subtitle_en'] : $award['description_subtitle'];
                    $description = ($award['reasonforaward_en'] != '') ? $award['reasonforaward_en'] : $award['reasonforaward'];
                    break;
                case 'de':
                default:
                    $subtitle = ($award['description_subtitle'] != '') ? $award['description_subtitle'] : $award['description_subtitle_en'];
                    $description = ($award['reasonforaward'] != '') ? $award['reasonforaward'] : $award['reasonforaward_en'];
                    break;
            }

            if (!empty($award['award_name'])) {
                $award_name = $award['award_name'];
            } elseif (!empty($award['award_name_manual'])) {
                $award_name = $award['award_name_manual'];
            }
            if (!empty($award['award_organisation'])) {
                $organisation = $award['award_organisation'];
            } elseif (!empty($award['award_organisation_manual'])) {
                $organisation = $award['award_organisation_manual'];
            } else {
                $organisation = null;
            }

            $award_details['#name#'] = $award['award_preistraeger'];
            $award_details['#year#'] = $award['year award'];
            $award_details['#subtitle#'] = htmlentities($subtitle, ENT_QUOTES);
            $award_details['#description#'] = $description;
            //$award_details['#description#'] = strip_tags($description);
            $award_details['#url_pressrelease#'] = $award['url_pressrelease'];
            if ($this->page_lang == 'en' && $award['url_pressrelease_title_en'] != '') {
                $award_details['#title_pressrelease#'] = $award['url_pressrelease_title_en'];
            } else {
                $award_details['#title_pressrelease#'] = $award['url_pressrelease_title'];
            }
            $award_details['#url_cris#'] = FAU_CRIS::cris_publicweb . "Person/" . $award['relpersid'];

            if (strpos($custom_text, '#image') !== false) {
                $imgs = self::get_pic($award['ID']);
                $award_details['#image1#'] = '';
                $award_details['#image1desc#'] = '';
                if (count($imgs)) {
                    $i = 1;
                    foreach ($imgs as $img) {
                        if (isset($img['png180']) && mb_strlen($img['png180']) > 30) {
                            $award_details['#image'.$i.'#'] = "<div class='cris-image wp-caption " . $param['image_align'] . "'>"
                                ."<img alt=\"". $award_details['#name#'] ."\" src=\"" . $img['png180'] . "\">"
                                . "</div>";
                            $award_details['#image'.$i.'desc#'] = ($img['desc'] != '' ? "<span class=\"wp-caption-text imgsrc\">" . $img['desc'] . "</span>" : '');
                        }
                        $i++;
                    }
                }
                $award_details['#image#'] = $award_details['#image1#'];
                $award_details['#imagedesc#'] = $award_details['#image1desc#'];
            }
            $award_details['#url_cris#'] = FAU_CRIS::cris_publicweb . "Person/" . $award['relpersid'];

            if ($param['display'] == 'accordion') {
                $item_open_mod = sprintf($item_open, $param['accordion_title'], $param['accordion_color'], sanitize_title($award_details['#name#']));
            } else {
                $item_open_mod = $item_open;
            }

            $awardlist .= strtr($item_open_mod . $custom_text . $item_close, $award_details);
        }

        $awardlist .= $tag_close;

        return do_shortcode($awardlist);
    }

    private function make_gallery($awards, $name = 1, $year = 1, $awardname = 1): string
    {
        $awardlist = "<ul class=\"cris-awards cris-gallery clear clearfix\">";

        foreach ($awards as $award) {
            $award = (array) $award;
            foreach ($award['attributes'] as $attribut => $v) {
                $award[$attribut] = $v;
            }
            unset($award['attributes']);

            $preistraeger = explode("|", $award['exportnames']);
            $preistraegerIDs = explode(",", $award['relpersid']);
            $preistraegerArray = array();
            foreach ($preistraegerIDs as $i => $key) {
                $nameparts = explode(":", $preistraeger[$i]);
                $preistraegerArray[] = array(
                    'id' => $key,
                    'lastname' => $nameparts[0],
                    'firstname' => $nameparts[1]);
            }
            $preistraegerList = array();
            foreach ($preistraegerArray as $pt) {
                $preistraegerList[] = Tools::get_person_link($pt['id'], $pt['firstname'], $pt['lastname'], $this->cris_award_link, $this->cms, $this->pathPersonenseiteUnivis, $this->univis, 0);
            }
            $preistraeger_html = implode(", ", $preistraegerList);

            if (!empty($award['award_name'])) {
                $award_name = $award['award_name'];
            } elseif (!empty($award['award_name_manual'])) {
                $award_name = $award['award_name_manual'];
            }
            if (!empty($award['award_organisation'])) {
                $organisation = $award['award_organisation'];
            } elseif (!empty($award['award_organisation_manual'])) {
                $organisation = $award['award_organisation_manual'];
            } else {
                $organisation = null;
            }
            $award_year = $award['year award'];
            $award_pic = self::get_pic($award['ID']);
            $awardlist .= "<li>";
            $awardlist .= (isset($award_pic[1]['png180']) && mb_strlen($award_pic[1]['png180']) > 30) ? "<img alt=\"Portrait " . $award['award_preistraeger'] . "\" src=\"" . $award_pic[1]['png180'] . "\"  />" : "<div class=\"noimage\">&nbsp</div>";
            $awardlist .= $name == 1 ? $preistraeger_html . ': ' : '';
            $awardlist .= (($awardname == 1) ? " <strong>" . $award_name . "</strong> "
                    . ((isset($organisation) && $award['type of award'] != 'Akademie-Mitgliedschaft') ? " (" . $organisation . ")" : "") : "");
            $awardlist .= ($year == 1 && !empty($award_year)) ? "<br />" . $award_year : '';
            $awardlist .= (isset($award_pic[1]['desc']) && mb_strlen($award_pic[1]['desc']) > 0) ? "<br /><span class=\"imgsrc\">(" . $award_pic[1]['desc'] . ")</span>" : "";
            $awardlist .= "</li>";
        }

        $awardlist .= "</ul><div style='clear:left;height:0;width:0;visibility:hidden;'></div>";

        return $awardlist;
    }

    private function get_pic($award): array
    {

        $images = array();
        $picString = Dicts::$base_uri . "getrelated/Award/" . $award . "/awar_has_pict";
        $picXml = Tools::XML2obj($picString);
        $i = 1;
        if (!is_wp_error($picXml) && !empty($picXml->infoObject)) {
            foreach ($picXml->infoObject as $pic) {
                foreach ($pic->attribute as $picAttribut) {
                    if ($picAttribut['name'] == 'png180') {
                        $images[$i]['png180'] = (!empty($picAttribut->data)) ? 'data:image/PNG;base64,' . $picAttribut->data
                            : '';
                    }
                }
                foreach ($pic->relation->attribute as $picRelAttribut) {
                    if ($picRelAttribut['name'] == 'description') {
                        $images[$i]['desc'] = (!empty($picRelAttribut->data)) ? (string) $picRelAttribut->data : '';
                    }
                }
                $i ++;
            }
        }
        return $images;
    }
}

class CRIS_awards extends Webservice
{
    /*
     * awards/grants requests
     */

    public function by_orga_id($orgaID = null, &$filter = null): array
    {
        if ($orgaID === null || $orgaID === "0") {
            return new \WP_Error(
                'cris-orgid-error',
                __('Bitte geben Sie die CRIS-ID der Organisation, Person oder Auszeichnung an.', 'fau-cris')
            );
        }

        if (!is_array($orgaID)) {
            $orgaID = array($orgaID);
        }

        $requests = array();
        foreach ($orgaID as $_o) {
            $requests[] = sprintf("getautorelated/Organisation/%d/ORGA_3_AWAR_1", $_o);
        }
        return $this->retrieve($requests, $filter);
    }

    public function by_pers_id($persID = null, &$filter = null) {
        if ($persID === null || $persID === "0") {
	        return new \WP_Error(
		        'cris-orgid-error',
		        __('Bitte geben Sie die CRIS-ID der Organisation, Person oder Auszeichnung an.', 'fau-cris')
	        );
        }

        if (!is_array($persID)) {
            $persID = array($persID);
        }

        $requests = array();
        foreach ($persID as $_p) {
            $requests[] = sprintf('getrelated/Person/%s/awar_has_pers', $_p);
        }
        return $this->retrieve($requests, $filter);
    }

    public function by_id($awarID = null): array
    {
        if ($awarID === null || $awarID === "0") {
	        return new \WP_Error(
		        'cris-orgid-error',
		        __('Bitte geben Sie die CRIS-ID der Organisation, Person oder Auszeichnung an.', 'fau-cris')
	        );
        }

        if (!is_array($awarID)) {
            $awarID = array($awarID);
        }

        $requests = array();
        foreach ($awarID as $_p) {
            $requests[] = sprintf('get/Award/%d', $_p);
        }
        return $this->retrieve($requests);
    }

    public function by_awardtype_id($awatID = null): array
    {
        if ($awatID === null || $awatID === "0") {
	        return new \WP_Error(
		        'cris-orgid-error',
		        __('Bitte geben Sie die CRIS-ID der Organisation, Person oder Auszeichnung an.', 'fau-cris')
	        );
        }

        if (!is_array($awatID)) {
            $awatID = array($awatID);
        }

        $requests = array();
        foreach ($awatID as $_p) {
            $requests[] = sprintf("getrelated/Award%%20Type/%d/awar_has_awat", $_p);
        }
        return $this->retrieve($requests);
    }

    private function retrieve($reqs, &$filter = null): array
    {
        if ($filter !== null && !$filter instanceof Filter) {
            $filter = new Filter($filter);
        }

        $data = array();
        foreach ($reqs as $_i) {
            $_data = $this->get($_i, $filter);
            if (!is_wp_error($_data)) {
                $data[] = $_data;
            }
        }
        $awards = array();

        foreach ($data as $_d) {
            foreach ($_d as $award) {
                $a = new CRIS_award($award);
                if ($a->ID && ($filter === null || $filter->evaluate($a))) {
                    $awards[$a->ID] = $a;
                }
            }
        }
        return $awards;
    }
}

class CRIS_award extends CRIS_Entity
{
    /*
     * object for single award
     */

    public function __construct($data)
    {
        parent::__construct($data);
    }
}
