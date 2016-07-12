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

    public static function getPubName($pub, $lang) {
        if (array_key_exists($lang, CRIS_Dicts::$pubNames[$pub])) {
            return CRIS_Dicts::$pubNames[$pub][$lang];
        }
        return CRIS_Dicts::$pubNames[$pub]['en'];
    }

    public static function getpubTitle($pub, $lang) {
        if (array_key_exists($lang, CRIS_Dicts::$pubTitles[$pub])) {
            return CRIS_Dicts::$pubTitles[$pub][$lang];
        }
        if (strpos($lang, 'de_') === 0) {
            return CRIS_Dicts::$pubTitles[$pub]['de_DE'];
        }
        return CRIS_Dicts::$pubTitles[$pub]['en_US'];
    }

    public static function getAwardName($award, $lang) {
        if (array_key_exists($lang, CRIS_Dicts::$awardNames[$award])) {
            return CRIS_Dicts::$awardNames[$award][$lang];
        }
        return CRIS_Dicts::$awardNames[$award]['en'];
    }

    public static function getawardTitle($award, $lang) {
        if (array_key_exists($lang, CRIS_Dicts::$awardTitles[$award])) {
            return CRIS_Dicts::$awardTitles[$award][$lang];
        }
        if (strpos($lang, 'de_') === 0) {
            return CRIS_Dicts::$awardTitles[$award]['de_DE'];
        }
        return CRIS_Dicts::$awardTitles[$award]['en_US'];
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
            $pubTyp = Tools::getPubName($type, "en");
            if (empty($pubTyp)) {
                $output .= '<p>' . __('Falscher Parameter für Publikationstyp', '') . '</p>';
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
            $type = Tools::getAwardName($type, "de");
            $filter['type of award__eq'] = $type;
        }
        if (count($filter))
            return $filter;
        return null;
    }

    /*
     * Anbindung an UnivIS-/FAU-Person-Plugin
     */

    public static function person_exists($cms = '', $firstname = '', $lastname = '', $univis = array()) {
        if ($cms == 'wp') {
            // WordPress
            return self::person_slug($cms, $firstname, $lastname);
        } else {
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

}
