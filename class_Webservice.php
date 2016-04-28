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