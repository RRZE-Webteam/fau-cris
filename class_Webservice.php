<?php
/**
 * These classes provide generic access to the CRIS web service data including
 * filter and sorting methods.
 *
 * Namespaces are not used respecting older PHP versions.
 *
 * @author Marcus Walther
 */

class CRIS_webservice {
    /*
     * generic class for web service access.
     */
    private $base_uri = "https://cris.fau.de/ws-cached/1.0/public/infoobject/";

    private function fetch($url) {
        /*
         * fetch raw data from web service
         */
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $xml = curl_exec($ch);

        if ($xml === false)
            throw new Exception('remote request failed '. curl_error($ch));

        curl_close($ch);
        return $xml;
    }

    public function get($id, &$filter) {
        /*
         * Initiate ws request and return parsed data (XML -> PHP object)
         *
         * $filter will be used in future here after CRIS web service supports
         * using filters directly.
         */

        try {
            $rawxml = $this->fetch($this->base_uri . $id);
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
        $this->attributes["createdon"] = $data['createdOn'];
        $this->attributes["updatedon"] = $data['updatedOn'];

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
    }
}
