<?php

require_once("class_Dicts.php");

class Tools {

    public static function getAcronym($acadTitle) {
        $acronym = '';
        foreach (explode(' ', $acadTitle) as $actitle) {
            if (array_key_exists($actitle, CRIS_Dicts::$acronyms) && CRIS_Dicts::$acronyms[$actitle] != '') {
                $acronym .= " " . CRIS_Dicts::$acronyms[$actitle];
            }
            $acronym = trim($acronym);
        }
        return $acronym;
    }

    public static function getOrder ($object) {
        switch ($object) {
            case 'publications':
                $search = CRIS_Dicts::$publications;
                break;
            case 'awards':
                $search = CRIS_Dicts::$awards;
                break;
            case 'projects':
                $search = CRIS_Dicts::$projects;
                break;
            case 'patents':
                $search = CRIS_Dicts::$patents;
                break;
            case 'activities':
                $search = CRIS_Dicts::$activities;
                break;
        }
        foreach ($search as $k => $v) {
            $order[$v['order']] = $k;
        }
        ksort($order);
        return $order;
    }

    public static function getType($object, $short) {
        switch ($object) {
            case 'publications':
                $search = CRIS_Dicts::$publications;
                break;
            case 'awards':
                $search = CRIS_Dicts::$awards;
                break;
            case 'projects':
                $search = CRIS_Dicts::$projects;
                break;
            case 'patents':
                $search = CRIS_Dicts::$patents;
                break;
            case 'activities':
                $search = CRIS_Dicts::$activities;
                break;
        }
        foreach ($search as $k => $v) {
            if($v['short'] == $short)
                return $k;
        }

    }

    public static function getName($object, $type, $lang) {
        $lang = strpos($lang, 'de') === 0 ? 'de' : 'en';
        $search = array();
        switch ($object) {
            case 'publications':
                $search = CRIS_Dicts::$publications;
                break;
            case 'awards':
                $search = CRIS_Dicts::$awards;
                break;
            case 'projects':
                $search = CRIS_Dicts::$projects;
                break;
            case 'patents':
                $search = CRIS_Dicts::$patents;
                break;
            case 'activities':
                $search = CRIS_Dicts::$activities;
                break;
        }
        return $search[$type][$lang]['name'];
    }

    public static function getTitle($object, $name, $lang) {
        $lang = strpos($lang, 'de') === 0 ? 'de' : 'en';
        switch ($object) {
            case 'publications':
                $search = CRIS_Dicts::$publications;
                break;
            case 'awards':
                $search = CRIS_Dicts::$awards;
                break;
            case 'projects':
                $search = CRIS_Dicts::$projects;
                break;
            case 'patents':
                $search = CRIS_Dicts::$patents;
                break;
            case 'activities':
                $search = CRIS_Dicts::$activities;
                break;
        }
        return $search[$name][$lang]['title'];
    }


    public static function XML2obj($xml_url) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $xml_url);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $xml = curl_exec($ch);
        curl_close($ch);

        $xmlTree = '';

        libxml_use_internal_errors(true);
        try {
            $xmlTree = new SimpleXMLElement($xml);
        } catch (Exception $e) {
            // Something went wrong.

            $error_message = '<strong>' . __('Fehler beim Einlesen der Daten: Bitte überprüfen Sie die CRIS-ID.', 'fau-cris') . '</strong>';
            if (defined('WP_DEBUG') && true === WP_DEBUG) {
                print '<p>';
                foreach (libxml_get_errors() as $error_line) {
                    $error_message .= "<br>" . $error_line->message;
                }
                trigger_error($error_message);
                print '</p>';
            } else {
                //print $error_message;
            }
            return false;
        }
        return $xmlTree;
    }

    /*
     * Array sortieren
     */

    public static function record_sortByName($results) {

        // Define the custom sort function
        function custom_sort($a, $b) {
            return (strcasecmp($a['lastName'], $b['lastName']));
        }

        // Sort the multidimensional array
        uasort($results, "custom_sort");
        return $results;
    }

    public static function record_sortByYear($results) {

        // Define the custom sort function
        function custom_sort_year($a, $b) {
            return $a['publYear'] < $b['publYear'];
        }

        // Sort the multidimensional array
        uasort($results, "custom_sort_year");
        return $results;
    }

    public static function record_sortByVirtualdate($results) {

        // Define the custom sort function
        function custom_sort_virtualdate($a, $b) {
            return $a['virtualdate'] < $b['virtualdate'];
        }

        // Sort the multidimensional array
        uasort($results, "custom_sort_virtualdate");
        return $results;
    }

    public static function sort_key(&$sort_array, $keys_array) {
        if (empty($sort_array) || !is_array($sort_array) || empty($keys_array))
            return;
        if (!is_array($keys_array))
            $keys_array = explode(',', $keys_array);
        if (!empty($keys_array))
            $keys_array = array_reverse($keys_array);
        foreach ($keys_array as $n) {
            if (array_key_exists($n, $sort_array)) {
                $newarray = array($n => $sort_array[$n]); //copy the node before unsetting
                unset($sort_array[$n]); //remove the node
                $sort_array = $newarray + array_filter($sort_array); //combine copy with filtered array
            }
        }
        return $sort_array;
    }

    /*
     * Mehrdimensionales Array nach value sortieren
     * Quelle: http://php.net/manual/de/function.array-multisort.php#91638
     */

    public static function array_msort($array, $cols) {
        $colarr = array();
        foreach ($cols as $col => $order) {
            $colarr[$col] = array();
            foreach ($array as $k => $row) {
                $colarr[$col]['_' . $k] = strtolower($row[$col]);
            }
        }
        $eval = 'array_multisort(';
        foreach ($cols as $col => $order) {
            $eval .= '$colarr[\'' . $col . '\'],' . $order . ',';
        }
        $eval = substr($eval, 0, -1) . ');';
        eval($eval);
        $ret = array();
        foreach ($colarr as $col => $arr) {
            foreach ($arr as $k => $v) {
                $k = substr($k, 1);
                if (!isset($ret[$k]))
                    $ret[$k] = $array[$k];
                $ret[$k][$col] = $array[$k][$col];
            }
        }
        return $ret;
    }

    /*
     * Array zur Definition des Filters für Publikationen
     */

    public static function publication_filter($year = '', $start = '', $type = '') {
        $filter = array();
        if ($year !== '' && $year !== NULL)
            $filter['publyear__eq'] = $year;
        if ($start !== '' && $start !== NULL)
            $filter['publyear__ge'] = $start;
        if ($type !== '' && $type !== NULL) {
            $pubTyp = self::getType('publications', $type);
            if (empty($pubTyp)) {
                $output = '<p>' . __('Falscher Parameter für Publikationstyp', '') . '</p>';
                return $output;
            }
            $filter['publication type__eq'] = $pubTyp;
        }
        if (count($filter))
            return $filter;
        return null;
    }

    /*
     * Array zur Definition des Filters für Awards
     */

    public static function award_filter($year = '', $start = '', $type = '') {
        $filter = array();
        if ($year !== '' && $year !== NULL)
            $filter['year award__eq'] = $year;
        if ($start !== '' && $start !== NULL)
            $filter['year award__ge'] = $start;
        if ($type !== '' && $type !== NULL) {
            $awardTyp = self::getType('awards', $type);
            if (empty($awardTyp)) {
                $output .= '<p>' . __('Falscher Parameter für Auszeichnungstyp', '') . '</p>';
                return $output;
            }
            $filter['type of award__eq'] = $awardTyp;
        }
        if (count($filter))
            return $filter;
        return null;
    }

    /*
     * Array zur Definition des Filters für Projekte
     */

    public static function project_filter($year = '', $start = '', $type = '') {
        $filter = array();
        if ($year !== '' && $year !== NULL)
            $filter['startyear__eq'] = $year;
        if ($start !== '' && $start !== NULL)
            $filter['startyear__ge'] = $start;
        if ($type !== '' && $type !== NULL) {
            $projTyp = self::getType('projects', $type);
            if (empty($projTyp)) {
                $output .= '<p>' . __('Falscher Parameter für Projekttyp', '') . '</p>';
                return $output;
            }
            $filter['project type__eq'] = $projTyp;
        }
        if (count($filter))
            return $filter;
        return null;
    }

    /*
     * Array zur Definition des Filters für Patente
     */

    public static function patent_filter($year = '', $start = '', $type = '') {
        $filter = array();
        if ($year !== '' && $year !== NULL)
            $filter['startyear__eq'] = $year;
        if ($start !== '' && $start !== NULL)
            $filter['startyear__ge'] = $start;
        if ($type !== '' && $type !== NULL) {
            $patTyp = self::getType('patents', $type);
            if (empty($patTyp)) {
                $output .= '<p>' . __('Falscher Parameter für Patenttyp', '') . '</p>';
                return $output;
            }
            $filter['patenttype__eq'] = $patTyp;
        }
        if (count($filter))
            return $filter;
        return null;
    }

    /*
     * Array zur Definition des Filters für Patente
     */

    public static function activity_filter($year = '', $start = '', $type = '') {
        $filter = array();
        if ($year !== '' && $year !== NULL)
            $filter['startyear__eq'] = $year;
        if ($start !== '' && $start !== NULL)
            $filter['startyear__ge'] = $start;
        if ($type !== '' && $type !== NULL) {
            $patTyp = self::getType('activities', $type);
            if (empty($patTyp)) {
                $output .= '<p>' . __('Falscher Parameter für Aktivitätstyp', '') . '</p>';
                return $output;
            }
            $filter['type of activity__eq'] = $patTyp;
        }
        if (count($filter))
            return $filter;
        return null;
    }

    /*
     * Anbindung an UnivIS-/FAU-Person-Plugin
     */

    public static function get_univis() {
        $univisID = self::get_univis_id();
        // Ich liebe UnivIS: Welche Abfrage liefert mehr Ergebnisse (hängt davon ab, wie die
        // Mitarbeiter der Institution zugeordnet wurden...)?
        $url1 = "http://univis.uni-erlangen.de/prg?search=departments&number=" . $univisID . "&show=xml";
        $daten1 = self::XML2obj($url1);
        $num1 = count($daten1->Person);
        $url2 = "http://univis.uni-erlangen.de/prg?search=persons&department=" . $univisID . "&show=xml";
        $daten2 = self::XML2obj($url2);
        $num2 = count($daten2->Person);
        $daten = $num1 > $num2 ? $daten1 : $daten2;

        foreach ($daten->Person as $person) {
            $univis[] = array('firstname' => (string) $person->firstname,
                               'lastname' => (string) $person->lastname);
        }
        return $univis;
    }

    public static function person_exists($cms = '', $firstname = '', $lastname = '', $univis) {
        if ($cms == 'wp') {
            // WordPress
            return self::person_slug($cms, $firstname, $lastname);
        }
        if ($cms == 'wbk') {
            // Webbaukasten
            foreach ($univis as $_p) {
                if (strpos($_p['firstname'], $firstname) !== false && strpos($_p['lastname'], $lastname) !== false) {
                    return true;
                }
            }
        }
    }

    public static function person_slug($cms = '', $firstname = '', $lastname = '') {
        if ($cms == 'wp') {
            // WordPress
            global $wpdb;
            $person = $wpdb->esc_like($firstname) . '%' . $wpdb->esc_like($lastname);
            $sql = "SELECT post_name FROM $wpdb->posts WHERE post_title LIKE %s AND post_type = 'person' AND post_status = 'publish'";
            $sql = $wpdb->prepare($sql, $person);
            $person_slug = $wpdb->get_var($sql);
        } else {
            //Webbauksten
            $person_slug = strtolower($firstname) . "-" . strtolower($lastname) . ".shtml";
        }
        return $person_slug;
    }

    public static function get_univis_id() {
        $fpath = $_SERVER["DOCUMENT_ROOT"] . '/vkdaten/tools/univis/univis.conf';
        $fpath_alternative = $_SERVER["DOCUMENT_ROOT"] . '/vkdaten/univis.conf';
        if (file_exists($fpath_alternative)) {
            $fpath = $fpath_alternative;
        }
        $fh = fopen($fpath, 'r') or die('Cannot open file!');
        while (!feof($fh)) {
            $line = fgets($fh);
            $line = trim($line);
            if ((substr($line, 0, 11) == 'UnivISOrgNr')) {
                $arr_opts = preg_split('/\t/', $line);
                $univisID = $arr_opts[1];
            }
        }
        fclose($fh);
        return $univisID;
    }

    public static function get_person_link($id, $firstname, $lastname, $target, $cms, $path, $univis, $inv = 0) {
        $person = '';
        switch ($target) {
            case 'cris' :
                if (is_numeric($id)) {
                    $link_pre = "<a href=\"https://cris.fau.de/converis/publicweb/Person/" . $id . "\" class=\"extern\">";
                    $link_post = "</a>";
                } else {
                    $link_pre = '';
                    $link_post = '';
                }
                break;
            case 'person':
                if (self::person_exists($cms, $firstname, $lastname, $univis)) {
                    $link_pre = "<a href=\"" . $path . self::person_slug($cms, $firstname, $lastname) . "\">";
                    $link_post = "</a>";
                } else {
                    $link_pre = '';
                    $link_post = '';
                }
                break;
            default:
                $link_pre = '';
                $link_post = '';
        }
        $name = $inv == 0 ? $firstname . " " . $lastname : $lastname . " " . $firstname;
        $person = "<span class=\"author\" itemprop=\"author\">" . $link_pre . $name . $link_post . "</span>";
        return $person;
    }

    public static function make_date ($start, $end) {
        setlocale(LC_TIME, get_locale());
        $date = '';
        if ($start != '')
            $start = strftime('%x', strtotime($start));
        if ($end != '')
            $end = strftime('%x', strtotime($end));
        if ($start !='' && $end != '') {
            $date = $start . " - " . $end;
        } elseif ($start != '' && $end =='') {
            $date = __('seit', 'fau-cris') . " " . $start;
        } elseif ($start == '' && $end != '') {
            $date = __('bis', 'fau-cris') . " " . $end;
        }
        return $date;
    }

    public static function numeric_xml_encode($text, $double_encode=true){
        /*
         * Deliver numerically encoded XML representation of special characters.
         * E.g. use &#8211; instead of &ndash;
         * 
         * Adopted from user-contributed notes of 
         * http://php.net/manual/de/function.htmlentities.php
         * 
         * @param string $text Input text
         * @param bool $double_encode flag for double encoding (defaults to true)
         * 
         * @return string $encoded Encoded text representation
         */
        
        if (!$double_encode)
            $text = html_entity_decode(stripslashes($text), ENT_QUOTES, 'UTF-8');

        // array of chars (multibyte aware)
        $mbchars = preg_split('/(?<!^)(?!$)/u', $text);
        
        $encoded = '';
        foreach ($mbchars as $char){
            $o = ord($char);
            if ( (strlen($char) > 1) || /* multi-byte [unicode] */
                ($o <32 || $o > 126) || /* <- control / latin weird os -> */
                ($o >33 && $o < 40) ||/* quotes + ambersand */
                ($o >59 && $o < 63) /* html */
            ) {
                // convert to numeric entity
                $char = mb_encode_numericentity($char, 
                                        array(0x0, 0xffff, 0, 0xffff), 'UTF-8');
            }
            $encoded .= $char;
        }
        return $encoded;
    }
}
