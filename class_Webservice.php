<?php
/**
 * These classes provide generic access to the CRIS web service data including
 * filter and sorting methods.
 *
 * Namespaces are not used respecting older PHP versions.
 *
 * @author Marcus Walther
 */

require_once("class_Tools.php");

class CRIS_webservice {
    /*
     * generic class for web service access.
     */
    private $cache = true;

    private function fetch($url) {
        /*
         * fetch raw data from web service
         */
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);

        $xml = curl_exec($ch);

        if ($xml === false)
            throw new Exception('remote request failed '. curl_error($ch));

        curl_close($ch);
        return $xml;
    }

    public function disable_cache() {
        $this->cache = false;
    }

    public function enable_cache() {
        $this->cache = true;
    }

    public function get($id, &$filter) {
        /*
         * Initiate ws request and return parsed data (XML -> PHP object)
         *
         * $filter will be fully supported in future. Currently only filter
         * for "publyear" is enabled for organisation requests.
         */

        $supported = array();
        $id_parts = explode('/', $id);
        if ($filter instanceof CRIS_Filter) {
            $remaining = array();
            foreach ($filter->filters as $attr => $value) {
                if (
                    strtolower($attr) !== 'publyear' ||
                    strtolower($id_parts[1]) !== 'organisation'
                ) {
                    $remaining[$attr] = $value;
                    continue;
                }
                $supported = $value;
            }
        }

        if (count($supported)) {
            foreach ($supported as $operator => $value) {
                $id .= sprintf("/filter/publyear__%s__%s", $operator, $value);
            }
            // mark "publyear" for skip on next evaluation
            $filter->skip[] = "publyear";
        }

        $seed = '';
        if (!$this->cache) {
            $seed = '?flag=seednow';
        }

        try {
            $rawxml = $this->fetch(CRIS_Dicts::$base_uri . $id . $seed);
        } catch (Exception $ex) {
            $rawxml = null;
        }
        if ($rawxml === null)
            throw new Exception('request failed');

        // parse into object
        libxml_use_internal_errors(true);
        try {
            $xmlobj = new SimpleXMLElement($rawxml);
        } catch (Exception $e) {
            $error_message = array();
            foreach(libxml_get_errors() as $error_line) {
                $error_message[] = $error_line->message;
            }
            throw new Exception(implode('\n', $error_message));
        }

        # build envelope array if necessary
        if (empty($xmlobj->infoObject))
            return array($xmlobj);
        return $xmlobj->infoObject;
    }
}

class CRIS_entity {
    /*
     * basic object for all CRIS webservice objects
     */
    public function __construct($data) {
        $this->ID = (string) $data['id'];
        $this->attributes = array();
        $this->attributes["createdon"] = (string) $data['createdOn'];
        $this->attributes["updatedon"] = (string) $data['updatedOn'];

        foreach ($data->attribute as $_a) {
            if ($_a['language'] == 1) {
                $attr_name = (string) $_a['name'] . '_en';
            } else {
                $attr_name = (string) $_a['name'];
            }
            if ((string) $_a['disposition'] == 'choicegroup') {
                $attr_value = (string) $_a->additionalInfo;
            } else {
                $attr_value = (string) $_a->data;
            }
            // any attribute name is forced to lower case
            $this->attributes[strtolower($attr_name)] = $attr_value;
        }
        foreach ($data->relation as $_r) {
            if (!in_array($_r['type'], array("FOBE_has_ORGA", "FOBE_has_PROJ", "FOBE_FAC_has_PROJ", "PROJ_has_PUBL", "FOBE_has_top_PUBL")))
                continue;
            foreach($_r->attribute as $_ra) {
                if ($_ra['name'] == 'Left seq') {
                    $this->attributes["relation left seq"] = (string) $_ra->data;
                }
                if ($_ra['name'] == 'Right seq') {
                    $this->attributes["relation right seq"] = (string) $_ra->data;
                }
            }
        }
        if (isset($this->attributes["publication type"])) {
            switch ($this->attributes["publication type"]) {
                case 'Book':
                    $this->attributes['subtype'] = $this->attributes["publication book subtype"];
                    break;
                case 'Journal article':
                    $this->attributes['subtype'] = $this->attributes["publication journal subtype"];
                    break;
                case 'Article in Edited Volumes':
                    $this->attributes['subtype'] = $this->attributes["publicationtypeeditedvolumes"];
                    break;
                case 'Thesis':
                    $this->attributes['subtype'] = $this->attributes["publication thesis subtype"];
                    break;
                case 'Editorial':
                    $this->attributes['subtype'] = $this->attributes["publication editorship subtype"];
                    break;
                case 'Conference contribution':
                    $this->attributes['subtype'] = $this->attributes["publication conference subtype"];
                    break;
                case 'Other':
                    $this->attributes['subtype'] = $this->attributes["type other subtype"];
                    break;
                case 'Unpublished':
                    $this->attributes['subtype'] = $this->attributes["futurepublicationtype"];
                    break;
                case 'Translation':
                default:
                    $this->attributes['subtype'] = 'undefined';
                    break;
            }
            if ($this->attributes['subtype'] == '') {
                $this->attributes['subtype'] = 'undefined';
            }
        }
    }
}
