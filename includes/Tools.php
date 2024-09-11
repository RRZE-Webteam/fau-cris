<?php

namespace RRZE\Cris;

defined('ABSPATH') || exit;

use RRZE\Cris\RemoteGet;
use RRZE\Cris\XML;
use RRZE\Cris\Dicts;

class Tools
{

    public static function getAcronym($acadTitle): string
    {
        $acronym = '';
        foreach (explode(' ', $acadTitle) as $actitle) {
            if (array_key_exists($actitle, Dicts::$acronyms) && Dicts::$acronyms[$actitle] != '') {
                $acronym .= " " . Dicts::$acronyms[$actitle];
            }
            $acronym = trim($acronym);
        }
        return $acronym;
    }

    public static function getOrder($object, $type = ''): array
    {
        if ($type == '' || !isset(Dicts::$typeinfos[$object][$type]['subtypes'])) {
            foreach (Dicts::$typeinfos[$object] as $k => $v) {
                $order[$v['order']] = $k;
            }
        } else {
            foreach (Dicts::$typeinfos[$object][$type]['subtypes'] as $k => $v) {
                $order[$v['order']] = $k;
            }
        }
        ksort($order);
        return $order;
    }

    public static function getOptionsOrder($object, $type = ''): array
    {
        $order_raw = self::getOrder($object, $type);
        if ($type == '') {
            foreach ($order_raw as $k => $v) {
                $order[] = Dicts::$typeinfos[$object][$v]['short'];
            }
        } else {
            foreach ($order_raw as $k => $v) {
                $order[] = Dicts::$typeinfos[$object][$type]['subtypes'][$v]['short'];
            }
        }
        return $order;
    }

    public static function getType($object, $short, $type = '')
    {
        if ($type == '') {
            foreach (Dicts::$typeinfos[$object] as $k => $v) {
                if ($v['short'] == $short) {
                    return $k;
                }
                if (array_key_exists('short_alt', $v) && $v['short_alt'] == $short) {
                    return $k;
                }
            }
        } else {
            foreach (Dicts::$typeinfos[$object][$type]['subtypes'] as $k => $v) {
                if ($v['short'] == $short) {
                    return $k;
                }
            }
        }
    }

    public static function getName($object, $type, $lang, $subtype = '')
    {
        $lang = strpos($lang, 'de') === 0 ? 'de' : 'en';
        if ($subtype == '') {
            if (array_key_exists($type, Dicts::$typeinfos[$object])) {
                return Dicts::$typeinfos[$object][$type][$lang]['name'];
            } else {
                return $type;
            }
        } else {
            if (array_key_exists($subtype, Dicts::$typeinfos[$object][$type]['subtypes'])) {
                return Dicts::$typeinfos[$object][$type]['subtypes'][$subtype][$lang]['name'];
            } else {
                return $subtype;
            }
        }
        return $type;
    }

    public static function getTitle($object, $name, $lang, $type = '')
    {
        $lang = strpos($lang, 'de') === 0 ? 'de' : 'en';
        if ($type == '') {
            if (isset(Dicts::$typeinfos[$object][$name][$lang]['title'])) {
                return Dicts::$typeinfos[$object][$name][$lang]['title'];
            }
        } else {
            if (isset(Dicts::$typeinfos[$object][$type]['subtypes'][$name][$lang]['title'])) {
                return Dicts::$typeinfos[$object][$type]['subtypes'][$name][$lang]['title'];
            }
        }
        return $name;
    }

    public static function getSubtypeAttribute($object, $type)
    {
        return Dicts::$typeinfos[$object][$type]['subtypeattribute'];
    }

    public static function getPageLanguage($postID): string
    {
        $page_lang_meta = get_post_meta($postID, 'fauval_langcode', true);
        if ($page_lang_meta != '') {
            $page_lang = ($page_lang_meta == 'de') ? 'de' : 'en';
        } else {
            $page_lang = strpos(get_locale(), 'de') === 0 ? 'de' : 'en';
        }
        return $page_lang;
    }

    public static function XML2obj($url)
    {
        $content = RemoteGet::retrieveContent($url);
        return XML::element($content);
    }

    /*
     * Array sortieren
     */

    public static function record_sortByName($results)
    {

        // Define the custom sort function
        function custom_sort($a, $b): int
        {
            return (strcasecmp($a['lastName'], $b['lastName']));
        }

        // Sort the multidimensional array
        uasort($results, "custom_sort");
        return $results;
    }

    public static function record_sortByYear($results)
    {

        // Define the custom sort function
        function custom_sort_year($a, $b): bool
        {
            return $a['publYear'] < $b['publYear'];
        }

        // Sort the multidimensional array
        uasort($results, "custom_sort_year");
        return $results;
    }

    public static function record_sortByVirtualdate($results)
    {

        // Define the custom sort function
        function custom_sort_virtualdate($a, $b): bool
        {
            return $a['virtualdate'] < $b['virtualdate'];
        }

        // Sort the multidimensional array
        uasort($results, "custom_sort_virtualdate");
        return $results;
    }

    public static function sort_key(&$sort_array, $keys_array)
    {
        if (empty($sort_array) || !is_array($sort_array) || empty($keys_array)) {
            return;
        }
        if (!is_array($keys_array)) {
            $keys_array = explode(',', $keys_array);
        }
        if (!empty($keys_array)) {
            $keys_array = array_reverse($keys_array);
        }
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

    public static function array_msort($array, $cols): array
    {
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
                if (!isset($ret[$k])) {
                    $ret[$k] = $array[$k];
                }
                $ret[$k][$col] = $array[$k][$col];
            }
        }
        return $ret;
    }


// Function to sort by a specific key

public static function sortByKey(array &$array, string $key): void {
    usort($array, fn($a, $b) => $a->attributes[$key] <=> $b->attributes[$key]);
}




    /*
     * Array zur Definition des Filters für Publikationen
     */

    public static function publication_filter($year = '', $start = '', $end = '', $type = '', $subtype = '', $fau = '', $peerreviewed = '', $language = '', $curation = '')
    {
        $filter = array();
        if ($year !== '' && $year !== null) {
            $filter['publyear__eq'] = $year;
        }
        if ($start !== '' && $start !== null) {
            $filter['publyear__ge'] = $start;
        }
        if ($end !== '' && $end !== null) {
            $filter['publyear__le'] = $end;
        }
        if ($type !== '' && $type !== null) {
            if (strpos($type, ',')) {
                $type = str_replace(' ', '', $type);
                $types = explode(',', $type);
                foreach ($types as $v) {
                    if (strpos($v, '-') === 0) {
                        $tmpType = substr($v, 1);
                        $pubTypExclude[] = self::getType('publications', $tmpType);
                    } else {
                        $pubTyp[] = self::getType('publications', $v);
                    }
                }
            } else {
                if (strpos($type, '-') === 0) {
                    $tmpType = substr($type, 1);
                    $pubTypExclude = (array)self::getType('publications', $tmpType);
                } else {
                    $pubTyp = (array)self::getType('publications', $type);
                }
            }
            if (empty($pubTyp) && empty($pubTypExclude)) {
                return new \WP_Error(
                    'cris-publication-type',
                    __('Falscher Parameter für Publikationstyp', 'fau-cris')
                );
            }
            if (!empty($pubTyp)) {
                $filter['publication type__eq'] = $pubTyp;
            } elseif (!empty($pubTypExclude)) {
                $filter['publication type__not'] = $pubTypExclude;
            }
        }
        if ($subtype !== '' && $subtype !== null) {
            $subtype = str_replace(' ', '', $subtype);
            $subtypes = explode(',', $subtype);
            foreach ($subtypes as $v) {
                if (strpos($v, '-') === 0) {
                    $tmpSubType = substr($v, 1);
                    $pubSubTypExclude[] = self::getType('publications', $tmpSubType, $pubTyp[0]);
                } else {
                    $pubSubTyp[] = self::getType('publications', $v, $pubTyp[0]);
                }
            }
            if (empty($pubSubTyp) && empty($pubSubTypExclude)) {
                $output = '<p>' . __('Falscher Parameter für Publikationssubtyp', 'fau-cris') . '</p>';
                return $output;
            }
            if (!empty($pubSubTyp)) {
                $filter['subtype__eq'] = $pubSubTyp;
            } elseif (!empty($pubSubTypExclude)) {
                $filter['subtype__not'] = $pubSubTypExclude;
            }
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
        if ($language !== '' && $language !== null) {
            $language = str_replace(' ', '', $language);
            $pubLanguages = explode(',', $language);
            $filter['language__eq'] = $pubLanguages;
        }
        if ($curation == 1) {
            $filter['relation curationsetting__eq'] = 'curation_accepted';
        }
        if (count($filter)) {
            return $filter;
        }
        return null;
    }

    /*
     * Array zur Definition des Filters für Awards
     */

    public static function award_filter($year = '', $start = '', $end = '', $type = ''): array|string|null
    {
        $filter = array();
        if ($year !== '' && $year !== null) {
            $filter['year award__eq'] = $year;
        }
        if ($start !== '' && $start !== null) {
            $filter['year award__ge'] = $start;
        }
        if ($end !== '' && $end !== null) {
            $filter['year award__le'] = $end;
        }
        if ($type !== '' && $type !== null) {
            if (strpos($type, ',')) {
                $type = str_replace(' ', '', $type);
                $types = explode(',', $type);
                foreach ($types as $v) {
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
        if (count($filter)) {
            return $filter;
        }
        return null;
    }

    /*
     * Array zur Definition des Filters für Projekte
     */

    public static function project_filter($year = '', $start = '', $end = '', $type = '', $status = '')
    {
        $filter = array();
        if ($year !== '' && $year !== null) {
            if ($year == 'current') {
                $filter['startyear__le'] = date('Y');
                $filter['endyear__ge'] = date('Y');
            } else {
                $filter['endyear__ge'] = $year;
                $filter['startyear__le'] = $year;
            }
        }
        if ($start !== '' && $start !== null) {
            $filter['startyear__ge'] = $start;
        }
        if ($end !== '' && $end !== null) {
            $filter['startyear__le'] = $end;
        }
        if ($type !== '' && $type !== null) {
            if (strpos($type, ',') !== false) {
                $types = explode(',', str_replace(' ', '', $type));
                foreach ($types as $v) {
                    $projTyp[] = self::getType('projects', $v);
                }
            } else {
                $projTyp = (array) self::getType('projects', $type);
            }
            if (empty($projTyp)) {
                return new \WP_Error(
                    'cris-project-type',
                    __('Falscher Parameter für Projekttyp', 'fau-cris')
                );
            }
            $filter['project type__eq'] = $projTyp;
        }
        if ($status !== '' && $status !== null) {
            if (strpos($status, ',') !== false) {
                $arrStatus = explode(',', str_replace(' ', '', $status));
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
        if (count($filter)) {
            return $filter;
        }
        return null;
    }

    /*
     * Array zur Definition des Filters für Patente
     */

    public static function patent_filter($year = '', $start = '', $end = '', $type = '')
    {
        $filter = array();
        if ($year !== '' && $year !== null) {
            $filter['registryear__eq'] = $year;
        }
        if ($start !== '' && $start !== null) {
            $filter['registryear__ge'] = $start;
        }
        if ($end !== '' && $end !== null) {
            $filter['registryear__le'] = $end;
        }
        if ($type !== '' && $type !== null) {
            if (strpos($type, ',')) {
                $type = str_replace(' ', '', $type);
                $types = explode(',', $type);
                foreach ($types as $v) {
                    $patTyp[] = self::getType('patents', $v);
                }
            } else {
                $patTyp = (array) self::getType('patents', $type);
            }
            if (empty($patTyp)) {
                return __('Falscher Parameter für Patenttyp', 'fau-cris');
            }
            $filter['patenttype__eq'] = $patTyp;
        }
        if (count($filter)) {
            return $filter;
        }
        return null;
    }

    /*
     * Array zur Definition des Filters für Aktivitäten
     */

    public static function activity_filter($year = '', $start = '', $end = '', $type = ''): array|string|null
    {
        $filter = array();
        if ($year !== '' && $year !== null) {
            $filter['year__eq'] = $year;
        }
        if ($start !== '' && $start !== null) {
            $filter['year__ge'] = $start;
        }
        if ($end !== '' && $end !== null) {
            $filter['year__le'] = $end;
        }
        if ($type !== '' && $type !== null) {
            if (strpos($type, ',')) {
                $type = str_replace(' ', '', $type);
                $types = explode(',', $type);
                foreach ($types as $v) {
                    $activityTyp[] = self::getType('activities', $v);
                }
            } else {
                $activityTyp = (array) self::getType('activities', $type);
            }
            if (empty($activityTyp)) {
                return '<p>' . __('Falscher Parameter für Aktivitätstyp', 'fau-cris') . '</p>';
            }
            $filter['type of activity__eq'] = $activityTyp;
        }
        if (count($filter)) {
            return $filter;
        }
        return null;
    }

    /*
     * Array zur Definition des Filters für Forschungsbereiche
     */

    public static function field_filter($year = '', $start = ''): ?array
    {
        $filter = array();
        if ($year !== '' && $year !== null) {
            $filter['startyear__eq'] = $year;
        }
        if ($start !== '' && $start !== null) {
            $filter['startyear__ge'] = $start;
        }

        if (count($filter)) {
            return $filter;
        }
        return null;
    }

    /*
     * Array zur Definition des Filters für Equipment
     */

    public static function equipment_filter($manufacturer = '', $location = '', $constructionYear = '', $constructionYearStart = '', $constructionYearEnd = ''): ?array
    {
        $filter = array();
        if ($manufacturer !== '' && $manufacturer !== null) {
            $filter['hersteller__eq'] = $manufacturer;
        }
        if ($location !== '' && $location !== null) {
            if (strpos($location, ',')) {
                $location = str_replace(' ', '', $location);
                $locations = explode(',', $location);
            } else {
                $locations = (array) $location;
            }
            $filter['location__eq'] = $locations;
        }
        if ($constructionYear !== '' && $constructionYear !== null) {
            if (strpos($constructionYear, ',')) {
                $constructionYear = str_replace(' ', '', $constructionYear);
                $constructionYear = explode(',', $constructionYear);
            } else {
                $constructionYears = (array) $constructionYear;
            }
            $filter['baujahr__eq'] = $constructionYear;
        }
        if ($constructionYearStart !== '' && $constructionYearStart !== null) {
            $filter['baujahr__ge'] = $constructionYearStart;
        }
        if ($constructionYearEnd !== '' && $constructionYearEnd !== null) {
            $filter['baujahr__le'] = $constructionYearEnd;
        }

        if (count($filter)) {
            return $filter;
        }
        return null;
    }

    /*
     * Array zur Definition des Filters für Standardisierungen
     */

    public static function standardizations_filter($year = '', $start = '', $end = '', $type = ''): array|string|null
    {
        $filter = array();
        if ($year !== '' && $year !== null) {
            $filter['year__eq'] = $year;
        }
        if ($start !== '' && $start !== null) {
            $filter['year__ge'] = $start;
        }
        if ($end !== '' && $end !== null) {
            $filter['year__le'] = $end;
        }
        if ($type !== '' && $type !== null) {
            if (strpos($type, ',')) {
                $type = str_replace(' ', '', $type);
                $types = explode(',', $type);
                foreach ($types as $v) {
                    $stanTyp[] = self::getType('standardizations', $v);
                }
            } else {
                $stanTyp = (array) self::getType('standardizations', $type);
            }
            if (empty($stanTyp)) {
                return '<p>' . __('Falscher Parameter für Standardisierungstyp', 'fau-cris') . '</p>';
            }
            $filter['subtype__eq'] = $stanTyp;
        }

        if (count($filter)) {
            return $filter;
        }
        return null;
    }

    /*
     * Anbindung an UnivIS-/FAU-Person-Plugin
     */

    public static function get_univis(): array
    {
        $univis = [];
        $univisID = self::get_univis_id();

        // Ich liebe UnivIS: Welche Abfrage liefert mehr Ergebnisse (hängt davon ab, wie die
        // Mitarbeiter der Institution zugeordnet wurden...)?
        $url1 = "http://univis.uni-erlangen.de/prg?search=departments&number=" . $univisID . "&show=xml";
        $daten1 = self::XML2obj($url1);
        $url2 = "http://univis.uni-erlangen.de/prg?search=persons&department=" . $univisID . "&show=xml";
        $daten2 = self::XML2obj($url2);

        $num1 = !is_wp_error($daten1) && !empty($daten1->Person) ? count($daten1->Person) : 0;
        $num2 = !is_wp_error($daten2) && !empty($daten2->Person) ? count($daten2->Person) : 0;
        if (!$num1 && !$num2) {
            return $univis;
        }
        $daten = $num1 > $num2 ? $daten1 : $daten2;

        foreach ($daten->Person as $person) {
            $univis[] = array(
                'firstname' => (string) $person->firstname,
                'lastname' => (string) $person->lastname
            );
        }
        return $univis;
    }

    public static function person_exists($cms = '', $firstname = '', $lastname = '', $univis = array(), $nameorder = '')
    {
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
            $persons = $wpdb->get_results($sql);
            if (count($persons) == 1) {
                return $persons[0]->ID;
            } elseif (count($persons) >= 1) {
                // more than 1 match
                foreach ($persons as $person) {
                    if ((get_the_title($person->ID) == $firstname . ' ' . $lastname) || (get_the_title($person->ID) == $lastname . ', ' . $firstname)) {
                        return $person->ID;
                    }
                }
            }
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

    public static function person_id($cms = '', $firstname = '', $lastname = '',$nameorder = '')
    {
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

    public static function person_slug($cms = '', $firstname = '', $lastname = '', $nameorder = '')
    {
        if ($cms == 'wp') {
            // WordPress
            global $wpdb;
            if ($nameorder == 'lastname-firstname') {
                $person = '%' . $wpdb->esc_like($lastname) . '%' . $wpdb->esc_like($firstname) . '%';
            } else {
                $person = '%' . $wpdb->esc_like($firstname) . '%' . $wpdb->esc_like($lastname) . '%';
            }
            $sql = "SELECT ID FROM $wpdb->posts WHERE post_title LIKE %s AND post_type = 'person' AND post_status = 'publish'";
            $sql = $wpdb->prepare($sql, $person);
            $persons = $wpdb->get_results($sql);
            if (count($persons) == 1) {
                $personObj = get_post($persons[0]->ID);
                $person_slug = $personObj->post_name;
            } elseif (count($persons) >= 1) {
                // more than 1 match
                foreach ($persons as $person) {
                    $personObj = get_post($person->ID);
                    if (($personObj->post_title == $firstname . ' ' . $lastname) || ($personObj->post_title == $lastname . ', ' . $firstname)) {
                        $person_slug = $personObj->post_name;
                    }
                }
            }
        } else {
            //Webbauksten
            $person_slug = strtolower($firstname) . "-" . strtolower($lastname) . ".shtml";
        }
        return $person_slug;
    }

    public static function get_univis_id()
    {
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

    public static function get_person_link($id, $firstname, $lastname, $target, $cms, $path, $univis, $inv = 0, $shortfirst = 0, $nameorder = ''): string
    {
        $person = '';
        switch ($target) {
            case 'cris':
                if (is_numeric($id) && strlen($id) > 2) {
                    $link_pre = "<a href=\"" . FAU_CRIS::cris_publicweb . "persons/" . $id . "\" class=\"extern\">";
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
        if ($id == 0 && $target == 'cris') {
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
                $fn_shorts[] = mb_substr($_fn, 0, 1);
            }
            $firstname = implode('', $fn_shorts) . '.';
        }
        $name = $inv == 0 ? $firstname . " " . $lastname : $lastname . " " . $firstname;
        $person = "<span class=\"author\" itemprop=\"author\">" . $link_pre . $name . $link_post . "</span>";
        return $person;
    }

    public static function make_date($start, $end): string
    {
        $date = '';
        if ($start != '') {
            $start = date_i18n(get_option('date_format'), strtotime($start));
        }
        if ($end != '') {
            $end = date_i18n(get_option('date_format'), strtotime($end));
        }
        if ($start != '' && $end != '') {
            $date = $start . " - " . $end;
        } elseif ($start != '' && $end == '') {
            $date = __('seit', 'fau-cris') . " " . $start;
        } elseif ($start == '' && $end != '') {
            $date = __('bis', 'fau-cris') . " " . $end;
        }
        return $date;
    }

    public static function get_item_url($item, $title, $cris_id, $page_id = '', $lang = 'de')
    {
        // First search in subpages
        $pages = get_pages(array('child_of' => $page_id, 'post_status' => 'publish'));
        foreach ($pages as $page) {
            if ($page->post_title == $title && !empty($page->guid)) {
                return get_permalink($page->ID);
            }
        }
        // No subpage -> search all pages
        $page = get_posts(['post_type' => 'page', 'pagename' => sanitize_title($title)]);
        if ($page && !empty($page[0]->ID)) {
            return get_permalink($page[0]->ID);
        } else {
            return FAU_CRIS::cris_publicweb . $item . "/" . $cris_id . ($lang == 'de' ? '?lang=de_DE' : '?lang=en_GB');
        }
    }

    public static function numeric_xml_encode($text, $double_encode = true): string
    {
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

        if (!$double_encode) {
            $text = html_entity_decode(stripslashes($text), ENT_QUOTES, 'UTF-8');
        }

        $html_specials = array('&', '<', '>', '"');

        // array of chars (multibyte aware)
        $mbchars = preg_split('/(?<!^)(?!$)/u', $text);

        $encoded = '';
        foreach ($mbchars as $char) {
            if (in_array($char, $html_specials)) {
                $encoded .= htmlentities($char);
                continue;
            }
            $o = ord($char);
            if ((mb_strlen($char) > 1) || /* multi-byte [unicode] */
                ($o < 32 || $o > 126) || /* <- control / latin weird os -> */
                ($o > 33 && $o < 40) ||/* quotes + ambersand */
                ($o > 59 && $o < 63) /* html */
            ) {
                // convert to numeric entity
                $char = mb_encode_numericentity(
                    $char,
                    array(0x0, 0xffff, 0, 0xffff),
                    'UTF-8'
                );
            }
            $encoded .= $char;
        }
        return $encoded;
    }


    /**
     * Name : fieldProjectStatusFilter
     *
     * Use: it will filter the project status by taking project array, eg:current,future..
     *
     * Returns: $filteredProjects
     *
     *
     */
    public static function field_project_status_filter($projects = array(), $projects_status = '', $projects_start = '')
    {

        // Filter conditions
        $filteredProjects = [];
        $statusFilteredProjects = [];
        $today = date('Y-m-d');
        $statusSet = ['completed', 'current', 'future'];

         // Condition 1: Filter by status only if start is null

         if ($projects_status !== '' && $projects_status !== null && ($projects_start === '' || $projects_start === null)) {
            if (strpos($projects_status, ',') !== false) {
                $arrStatus = explode(',', str_replace(' ', '', $projects_status));
            } else {
                $arrStatus = (array) $projects_status;
            }
            foreach ($projects as $project) {
                foreach ($arrStatus as $selectedStatus) {
                    if (in_array($selectedStatus, $statusSet)) {
                        switch ($selectedStatus) {
                            case 'completed':
                                if (isset($project->attributes['virtualenddate']) && $project->attributes['virtualenddate'] < $today) {
                                    $filteredProjects[] = $project;
                                }
                                break;

                            case 'current':
                                if (
                                    isset($project->attributes['cfstartdate']) &&
                                    isset($project->attributes['virtualenddate']) &&
                                    $project->attributes['cfstartdate'] <= $today &&
                                    $project->attributes['virtualenddate'] >= $today
                                ) {
                                    $filteredProjects[] = $project;
                                }
                                break;

                            case 'future':
                                if (isset($project->attributes['cfstartdate']) && $project->attributes['cfstartdate'] > $today) {
                                    $filteredProjects[] = $project;
                                }
                                break;
                            case 'completed,current':
                                if (
                                    isset($project->attributes['cfstartdate']) &&
                                    $project->attributes['cfstartdate'] <= $today
                                ) {
                                    $filteredProjects[] = $project;
                                }
                                break;
                            case 'current,future':
                                if (
                                    isset($project->attributes['virtualenddate']) &&
                                    $project->attributes['virtualenddate'] >= $today
                                ) {
                                    $filteredProjects[] = $project;
                                }
                                break;
                        }
                    }
                }
            }
         }


    // Condition 2: Filter by start year only if status is null
    if ($projects_start !== '' && $projects_start !== null && ($projects_status === '' || $projects_status === null)) {
        foreach ($projects as $project) {
            if (isset($project->attributes['startyear']) && $project->attributes['startyear'] >= $projects_start) {
                $filteredProjects[] = $project;
            }
        }
    }

    // Condition 3: Filter by both if both are not null
    if ($projects_status !== '' && $projects_status !== null && $projects_start !== '' && $projects_start !== null) {
        if (strpos($projects_status, ',') !== false) {
            $arrStatus = explode(',', str_replace(' ', '', $projects_status));
        } else {
            $arrStatus = (array) $projects_status;
        }

        foreach ($projects as $project) {
                foreach ($arrStatus as $selectedStatus) {
                    if (in_array($selectedStatus, $statusSet)) {
                        switch ($selectedStatus) {
                            case 'completed':
                                if (isset($project->attributes['virtualenddate']) && $project->attributes['virtualenddate'] < $today) {
                                    $statusFilteredProjects[] = $project;
                                }
                                break;

                            case 'current':
                                if (
                                    isset($project->attributes['cfstartdate']) &&
                                    isset($project->attributes['virtualenddate']) &&
                                    $project->attributes['cfstartdate'] <= $today &&
                                    $project->attributes['virtualenddate'] >= $today
                                ) {
                                    $statusFilteredProjects[] = $project;
                                }
                                break;

                            case 'future':
                                if (isset($project->attributes['cfstartdate']) && $project->attributes['cfstartdate'] > $today) {
                                    $statusFilteredProjects[] = $project;
                                }
                                break;
                            case 'completed,current':
                                if (
                                    isset($project->attributes['cfstartdate']) &&
                                    $project->attributes['cfstartdate'] <= $today
                                ) {
                                    $statusFilteredProjects[] = $project;
                                }
                                break;
                            case 'current,future':
                                if (
                                    isset($project->attributes['virtualenddate']) &&
                                    $project->attributes['virtualenddate'] >= $today
                                ) {
                                    $statusFilteredProjects[] = $project;
                                }
                                break;
                        }
                    }
                }
            }
        
            

        // Further filter these status-filtered projects by start year
        $filteredProjects = array_filter($statusFilteredProjects, function ($project) use ($projects_start) {
            return isset($project->attributes['startyear']) && $project->attributes['startyear'] >= $projects_start;
        });

      
     
    }
    

                // Sort the array by the 'startyear' key

                Tools::sortByKey($filteredProjects, 'startyear');

    return $filteredProjects;
    }

    public static function filter_publication_bypersonid_postion($pubArray, $persIdArray, $authorPositionArray)
    {
        // Filter publications
        $filteredPublications = array_filter($pubArray, function ($publication) use ($persIdArray, $authorPositionArray) {
            // Check if relpersid is set and is a string
            if (isset($publication->attributes['relpersid']) && is_string($publication->attributes['relpersid'])) {
                // Split the relpersid string into an array
                $relpersidArray = explode(',', $publication->attributes['relpersid']);

                // Check for each persId and position combination
                foreach ($persIdArray as $persId) {
                    foreach ($authorPositionArray as $position) {
                        // Convert -1 to the last position
                        $positionToCheck = ($position == -1) ? count($relpersidArray) : $position;

                        // Check if the persId exists at the specified position in relpersid
                        if ($positionToCheck > 0 && isset($relpersidArray[$positionToCheck - 1]) && $relpersidArray[$positionToCheck - 1] == $persId) {
                            return true; // Include the publication if the condition is met
                        }
                    }
                }
            }

            return false; // Exclude the publication if no condition is met
        });

        return $filteredPublications;
    }

/**
     * Name : get_first_available_link
     *
     * Use: it will get the first available link for publication title 
     *
     * Returns: link according to priority 
     *
     *
     */
    public static function get_first_available_link($cris_pub_title_link_order, $pubDetails, $title, $id, $postID, $lang) {
        foreach ($cris_pub_title_link_order as $linkPriority) {
            switch ($linkPriority) {
                case 'internal link':
                case 'cris':
                    
                    $link = Tools::get_item_url("publications", $title, $id, $postID, $lang);
                    if (!empty($link)) return $link;
                    break;

                case 'url':
                    if (!empty($pubDetails['URI'])) {
                        return $pubDetails['URI'];
                    }
                    break;
    
                case 'open access link':
                    if (!empty($pubDetails['OAlink'])) {
                        return $pubDetails['OAlink'];
                    }
                    break;
    
                case 'doi':
                    if (!empty($pubDetails['doi_link'])) {
                        return $pubDetails['doi_link'];
                    }
                    break;
            }
        }
        return Tools::get_item_url("publications", $title, $id, $postID, $lang);
    }

/**
     * Name : get_external_project_partner_name
     *
     * Use: it will get all the external partner name for a project
     *
     * Returns: array of externalPartneName
     *
     *
     */
        public static function get_external_project_partner_name($externalPartnerArray=array())
        {

          $externalPartneName=array(); 
          foreach ($externalPartnerArray as $partner) {
            $partner = (array) $partner;
            foreach ($partner['attributes'] as $attribut => $v) {
                $partner[$attribut] = $v;
            }
            unset($partner['attributes']);
            $externalPartneName[]=$partner["name"];
            
            }  

        return $externalPartneName;
        }








}
