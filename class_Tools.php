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

    public static function getOrder ($object, $type = '') {
        if ($type == '') {
            foreach (CRIS_Dicts::$typeinfos[$object] as $k => $v) {
                $order[$v['order']] = $k;
            }
        } else {
            foreach (CRIS_Dicts::$typeinfos[$object][$type]['subtypes'] as $k => $v) {
                $order[$v['order']] = $k;
            }
        }
        ksort($order);
        return $order;
    }

    public static function getOptionsOrder($object, $type = '') {
        $order_raw = self::getOrder($object, $type);
        if ($type == '') {
            foreach ($order_raw as $k => $v) {
                $order[] = CRIS_Dicts::$typeinfos[$object][$v]['short'];
            }
        } else {
            foreach ($order_raw as $k => $v) {
                $order[] = CRIS_Dicts::$typeinfos[$object][$type]['subtypes'][$v]['short'];
            }
        }
        return $order;
    }

    public static function getType($object, $short, $type = '') {
        if ($type == '') {
            foreach (CRIS_Dicts::$typeinfos[$object] as $k => $v) {
                if($v['short'] == $short)
                    return $k;
                if (array_key_exists('short_alt', $v) && $v['short_alt'] == $short) {
                    return $k;
                }
            }
        } else {
            foreach (CRIS_Dicts::$typeinfos[$object][$type]['subtypes'] as $k => $v) {
                if($v['short'] == $short)
                    return $k;
            }
        }
    }

    public static function getName($object, $type, $lang, $subtype = '') {
        $lang = strpos($lang, 'de') === 0 ? 'de' : 'en';
        if ($subtype == '') {
            if (array_key_exists($type, CRIS_Dicts::$typeinfos[$object])) {
                return CRIS_Dicts::$typeinfos[$object][$type][$lang]['name'];
            } else {
                return $type;
            }
        } else {
            if (array_key_exists($subtype, CRIS_Dicts::$typeinfos[$object][$type]['subtypes'])) {
                return CRIS_Dicts::$typeinfos[$object][$type]['subtypes'][$subtype][$lang]['name'];
            } else {
                return $subtype;
            }
        }
        return $type;
    }

    public static function getTitle($object, $name, $lang, $type = '') {
        $lang = strpos($lang, 'de') === 0 ? 'de' : 'en';
        if ($type == '') {
            if (isset(CRIS_Dicts::$typeinfos[$object][$name][$lang]['title']))
                return CRIS_Dicts::$typeinfos[$object][$name][$lang]['title'];
        } else {
            if (isset(CRIS_Dicts::$typeinfos[$object][$type]['subtypes'][$name][$lang]['title']))
                return CRIS_Dicts::$typeinfos[$object][$type]['subtypes'][$name][$lang]['title'];
        }
        return $name;
    }

    public static function getSubtypeAttribute($object, $type) {
        return CRIS_Dicts::$typeinfos[$object][$type]['subtypeattribute'];
    }

    public static function getPageLanguage($postID) {
        $page_lang_meta = get_post_meta($postID, 'fauval_langcode', true);
        if ($page_lang_meta != '') {
            $page_lang = ($page_lang_meta == 'de') ? 'de' : 'en';
        } else {
            $page_lang = strpos(get_locale(), 'de') === 0 ? 'de' : 'en';
        }
        return $page_lang;
    }

    public static function XML2obj($xml_url) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $xml_url);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
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
        $eval = mb_substr($eval, 0, -1) . ');';
        eval($eval);
        $ret = array();
        foreach ($colarr as $col => $arr) {
            foreach ($arr as $k => $v) {
                $k = mb_substr($k, 1);
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

    public static function publication_filter($year = '', $start = '', $end = '', $type = '', $subtype = '', $fau = '', $peerreviewed = '', $language = '') {
        $filter = array();
        if ($year !== '' && $year !== NULL)
            $filter['publyear__eq'] = $year;
        if ($start !== '' && $start !== NULL)
            $filter['publyear__ge'] = $start;
        if ($end !== '' && $end !== NULL)
            $filter['publyear__le'] = $end;
        if ($type !== '' && $type !== NULL) {
            if (strpos($type, ',')) {
                $type = str_replace(' ', '', $type);
                $types = explode(',', $type);
                foreach($types as $v) {
                    $pubTyp[] = self::getType('publications', $v);
                }
            } else {
                $pubTyp = (array) self::getType('publications', $type);
            }
            if (empty($pubTyp)) {
                $output = '<p>' . __('Falscher Parameter für Publikationstyp', 'fau-cris') . '</p>';
                return $output;
            }
            $filter['publication type__eq'] = $pubTyp;
        }
        if ($subtype !== '' && $subtype !== NULL) {
            $subtype = str_replace(' ', '', $subtype);
            $subtypes = explode(',', $subtype);
            foreach($subtypes as $v) {
                $pubSubTyp[] = self::getType('publications', $v, $pubTyp[0]);
            }
            if (empty($pubSubTyp)) {
                $output = '<p>' . __('Falscher Parameter für Publikationssubtyp', 'fau-cris') . '</p>';
                return $output;
            }
            $filter['subtype__eq'] = $pubSubTyp;
        }
        if ($fau !== '') {
            if ($fau == 1) {
                $filter['fau publikation__eq'] = 'yes';
            } elseif ($fau == 0) {
                $filter['fau publikation__eq'] = 'no';
            }
        }
        if ($peerreviewed !== '') {
            if ($peerreviewed == 1) {
                $filter['peerreviewed__eq'] = 'Yes';
            } elseif ($fau == 0) {
                $filter['peerreviewed__eq'] = 'No';
            }
        }
        if ($language !== '' && $language !== NULL) {
            $language = str_replace(' ', '', $language);
            $pubLanguages = explode(',', $language);
            $filter['language__eq'] = $pubLanguages;
        }
        if (count($filter))
            return $filter;
        return null;
    }

    /*
     * Array zur Definition des Filters für Awards
     */

    public static function award_filter($year = '', $start = '', $end = '', $type = '') {
        $filter = array();
        if ($year !== '' && $year !== NULL)
            $filter['year award__eq'] = $year;
        if ($start !== '' && $start !== NULL)
            $filter['year award__ge'] = $start;
        if ($end !== '' && $end !== NULL)
            $filter['year award__le'] = $end;
        if ($type !== '' && $type !== NULL) {
            if (strpos($type, ',')) {
                $type = str_replace(' ', '', $type);
                $types = explode(',', $type);
                foreach($types as $v) {
                    $awardTyp[] = self::getType('awards', $v);
                }
            } else {
                $awardTyp = (array) self::getType('awards', $type);
            }
            if (empty($awardTyp)) {
                $output = '<p>' . __('Falscher Parameter für Auszeichnungstyp', 'fau-cris') . '</p>';
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

    public static function project_filter($year = '', $start = '', $end = '', $type = '', $status = '') {
        $filter = array();
        if ($year !== '' && $year !== NULL) {
            if ($year == 'current') {
                $current = date('Y');
                $filter['startyear__le'] = date('Y');
                $filter['endyear__ge'] = date('Y');
            } else {
                $filter['endyear__ge'] = $year;
                $filter['startyear__le'] = $year;
            }
        }
        if ($start !== '' && $start !== NULL)
            $filter['startyear__ge'] = $start;
        if ($end !== '' && $end !== NULL)
            $filter['startyear__le'] = $end;
        if ($type !== '' && $type !== NULL) {
            if (strpos($type, ',')) {
                $type = str_replace(' ', '', $type);
                $types = explode(',', $type);
                foreach($types as $v) {
                    $projTyp[] = self::getType('projects', $v);
                }
            } else {
                $projTyp = (array) self::getType('projects', $type);
            }
            if (empty($projTyp)) {
                $output .= '<p>' . __('Falscher Parameter für Projekttyp', 'fau-cris') . '</p>';
                return $output;
            }
            $filter['project type__eq'] = $projTyp;
        }
        if ($status !== '' && $status !== NULL) {
            if (strpos($status, ',')) {
                $status = str_replace(' ', '', $status);
                $arrStatus = explode(',', $status);
            } else {
                $arrStatus = (array) $status;
            }
            $today = date('Y-m-d');
            $statusSet = ['completed', 'current', 'future'];
            if (array_intersect($arrStatus, $statusSet) == ['completed']) {
                $filter['virtualenddate__lt'] = $today;
            } elseif (array_intersect($arrStatus, $statusSet) == ['current']) {
                $filter['cfstartdate__le'] = $today;
                $filter['virtualenddate__ge'] = $today;
            } elseif (array_intersect($arrStatus, $statusSet) == ['future']) {
                $filter['cfstartdate__gt'] = $today;
            } elseif (array_intersect($arrStatus, $statusSet) == ['completed', 'current']) {
                $filter['cfstartdate__le'] = $today;
            } elseif (array_intersect($arrStatus, $statusSet) == ['current', 'future']) {
                $filter['virtualenddate__ge'] = $today;
            }
        }
        if (count($filter))
            return $filter;
        return null;
    }

    /*
     * Array zur Definition des Filters für Patente
     */

    public static function patent_filter($year = '', $start = '', $end = '', $type = '') {
        $filter = array();
        if ($year !== '' && $year !== NULL)
            $filter['registryear__eq'] = $year;
        if ($start !== '' && $start !== NULL)
            $filter['registryear__ge'] = $start;
        if ($end !== '' && $end !== NULL)
            $filter['registryear__le'] = $end;
        if ($type !== '' && $type !== NULL) {
            if (strpos($type, ',')) {
                $type = str_replace(' ', '', $type);
                $types = explode(',', $type);
                foreach($types as $v) {
                    $patTyp[] = self::getType('patents', $v);
                }
            } else {
                $patTyp = (array) self::getType('patents', $type);
            }
            if (empty($patTyp)) {
                $output .= '<p>' . __('Falscher Parameter für Patenttyp', 'fau-cris') . '</p>';
                return $output;
            }
            $filter['patenttype__eq'] = $patTyp;
        }
        if (count($filter))
            return $filter;
        return null;
    }

    /*
     * Array zur Definition des Filters für Aktivitäten
     */

    public static function activity_filter($year = '', $start = '', $end = '', $type = '') {
        $filter = array();
        if ($year !== '' && $year !== NULL)
            $filter['year__eq'] = $year;
        if ($start !== '' && $start !== NULL)
            $filter['year__ge'] = $start;
        if ($end !== '' && $end !== NULL)
            $filter['year__le'] = $end;
        if ($type !== '' && $type !== NULL) {
            if (strpos($type, ',')) {
                $type = str_replace(' ', '', $type);
                $types = explode(',', $type);
                foreach($types as $v) {
                    $activityTyp[] = self::getType('activities', $v);
                }
            } else {
                $activityTyp = (array) self::getType('activities', $type);
            }
            if (empty($activityTyp)) {
                $output .= '<p>' . __('Falscher Parameter für Aktivitätstyp', 'fau-cris') . '</p>';
                return $output;
            }
            $filter['type of activity__eq'] = $activityTyp;
        }
        if (count($filter))
            return $filter;
        return null;
    }

    /*
     * Array zur Definition des Filters für Forschungsbereiche
     */

    public static function field_filter($year = '', $start = '') {
        $filter = array();
        if ($year !== '' && $year !== NULL)
            $filter['startyear__eq'] = $year;
        if ($start !== '' && $start !== NULL)
            $filter['startyear__ge'] = $start;

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

    public static function person_exists($cms = '', $firstname = '', $lastname = '', $univis = array(), $nameorder = '') {
        if ($cms == 'wp') {
            // WordPress
            if ($firstname == '' && $lastname == '') {
                return;
            }
            global $wpdb;
            if ($nameorder == 'lastname-firstname') {
                $person = '%' . $wpdb->esc_like($lastname) . '%' . $wpdb->esc_like($firstname) . '%';
            } else {
                $person = '%' . $wpdb->esc_like($firstname) . '%' . $wpdb->esc_like($lastname) . '%';
            }
            $sql = "SELECT ID FROM $wpdb->posts WHERE post_title LIKE %s AND post_type = 'person' AND post_status = 'publish'";
            $sql = $wpdb->prepare($sql, $person);
            $person_id = $wpdb->get_var($sql);
            return $person_id;
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

    public static function person_id($cms = '', $firstname = '', $lastname = '') {
        if ($cms == 'wp') {
            global $wpdb;
            if ($nameorder == 'lastname-firstname') {
                $person = '%' . $wpdb->esc_like($lastname) . '%' . $wpdb->esc_like($firstname) . '%';
            } else {
                $person = '%' . $wpdb->esc_like($firstname) . '%' . $wpdb->esc_like($lastname) . '%';
            }
            $sql = "SELECT ID FROM $wpdb->posts WHERE post_title LIKE %s AND post_type = 'person' AND post_status = 'publish'";
            $sql = $wpdb->prepare($sql, $person);
            $person_id = $wpdb->get_var($sql);
        }
        return $person_id;
    }

    public static function person_slug($cms = '', $firstname = '', $lastname = '', $nameorder = '') {
        if ($cms == 'wp') {
            // WordPress
            global $wpdb;
            if ($nameorder == 'lastname-firstname') {
                $person = '%' . $wpdb->esc_like($lastname) . '%' . $wpdb->esc_like($firstname) . '%';
            } else {
                $person = '%' . $wpdb->esc_like($firstname) . '%' . $wpdb->esc_like($lastname) . '%';
            }
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
            if ((mb_substr($line, 0, 11) == 'UnivISOrgNr')) {
                $arr_opts = preg_split('/\t/', $line);
                $univisID = $arr_opts[1];
            }
        }
        fclose($fh);
        return $univisID;
    }

    public static function get_person_link($id, $firstname, $lastname, $target, $cms, $path, $univis, $inv = 0, $shortfirst = 0, $nameorder = '') {
        $person = '';
        switch ($target) {
            case 'cris' :
                if (is_numeric($id)) {
                    $link_pre = "<a href=\"" . FAU_CRIS::cris_publicweb . "Person/" . $id . "\" class=\"extern\">";
                    $link_post = "</a>";
                } else {
                    $link_pre = '';
                    $link_post = '';
                }
                break;
            case 'person':
                if (self::person_exists($cms, $firstname, $lastname, $univis, $nameorder)) {
                    $link_pre = "<a href=\"" . $path . self::person_slug($cms, $firstname, $lastname, $nameorder) . "\">";
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
        if ($id == 0) {
            $link_pre = '';
            $link_post = '';
        }
        if ($shortfirst == 1) {
            if (strpos($firstname, ' ') !== false) {
                $firstnames = explode(' ', $firstname);
            } elseif (strpos($firstname, '-') !== false) {
                $firstnames = explode('-', $firstname);
            } else {
                $firstnames[] = $firstname;
            }
            foreach ($firstnames as $_fn) {
                $fn_shorts[] = mb_substr($_fn,0,1);
            }
            $firstname = implode('', $fn_shorts) . '.';
        }
        $name = $inv == 0 ? $firstname . " " . $lastname : $lastname . " " . $firstname;
        $person = "<span class=\"author\" itemprop=\"author\">" . $link_pre . $name . $link_post . "</span>";
        return $person;
    }

    public static function make_date ($start, $end) {
        $fmt = datefmt_create(
            get_locale(),
            IntlDateFormatter::MEDIUM,
            IntlDateFormatter::NONE,
            date_default_timezone_get(),
            IntlDateFormatter::GREGORIAN
        );

        $date = '';
        if ($start != '')
            $start = datefmt_format($fmt, strtotime($start));
        if ($end != '')
            $end = datefmt_format($fmt, strtotime($end));
        if ($start !='' && $end != '') {
            $date = $start . " - " . $end;
        } elseif ($start != '' && $end =='') {
            $date = __('seit', 'fau-cris') . " " . $start;
        } elseif ($start == '' && $end != '') {
            $date = __('bis', 'fau-cris') . " " . $end;
        }
        return $date;
    }

    public static function get_item_url($item, $title, $cris_id, $page_id = '', $lang = 'de') {
        // First search in subpages
        $pages = get_pages(array('child_of' => $page_id, 'post_status' => 'publish'));
        foreach ($pages as $page) {
            if ($page->post_title == $title && !empty($page->guid)) {
                return get_permalink($page->ID);
            }
        }
        // No subpage -> search all pages
        $page = get_page_by_title($title);
        if ($page && !empty($page->ID)) {
            return get_permalink($page->ID);
        } else {
            return FAU_CRIS::cris_publicweb . $item . "/" . $cris_id . ($lang == 'de' ? '?lang=de_DE' : '?lang=en_GB');
        }
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

        $html_specials = array('&', '<', '>', '"');

        // array of chars (multibyte aware)
        $mbchars = preg_split('/(?<!^)(?!$)/u', $text);

        $encoded = '';
        foreach ($mbchars as $char){
            if (in_array($char, $html_specials)) {
                $encoded .= htmlentities($char);
                continue;
            }
            $o = ord($char);
            if ( (mb_strlen($char) > 1) || /* multi-byte [unicode] */
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
