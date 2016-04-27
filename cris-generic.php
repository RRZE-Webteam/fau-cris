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

class CRIS_publications extends CRIS_webservice {
    /*
     * publication requests, supports multiple organisation ids given as array.
     */
    public function by_orga_id($orgaID=null, &$filter=null) {
        if ($orgaID === null || $orgaID === "0")
            throw new Exception('Please supply valid organisation ID');

        if (!is_array($orgaID))
            $orgaID = array($orgaID);

        $requests = array();
        foreach ($orgaID as $_o) {
            $requests = array_merge($requests, array(
                sprintf("getautorelated/Organisation/%d/ORGA_2_PUBL_1", $_o),
                sprintf("getrelated/Organisation/%d/Publ_has_ORGA", $_o),
            ));
        }
        return $this->retrieve($requests, $filter);
    }

    public function by_pers_id($persID=null, &$filter=null) {
        if ($persID === null || $persID === "0")
            throw new Exception('Please supply valid person ID');

        if (!is_array($persID))
            $persID = array($persID);

        $requests = array();
        foreach ($persID as $_p) {
            $requests[] = sprintf('getautorelated/Person/%d/PERS_2_PUBL_1', $_p);
        }
        return $this->retrieve($requests, $filter);
    }

    public function by_id($publID=null) {
        if ($publID === null || $publID === "0")
            throw new Exception('Please supply valid publication ID');

        if (!is_array($publID))
            $publID = array($publID);

        $requests = array();
        foreach ($publID as $_p) {
            $requests[] = sprintf('get/Publication/%d', $_p);
        }
        return $this->retrieve($requests);
    }

    private function retrieve($reqs, &$filter=null) {
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

        if ($filter !== null && !$filter instanceof CRIS_filter)
            $filter = new CRIS_filter($filter);

        $publs = array();

        foreach ($data as $_d) {
            foreach ($_d as $publ) {
                $p = new CRIS_publication($publ);
                if ($p->ID && ($filter === null || $filter->evaluate($p)))
                    $publs[$p->ID] = $p;
            }
        }

        return $publs;
    }
}


class CRIS_awards extends CRIS_webservice {
    /*
     * awards/grands requests
     */
}


class CRIS_filter {
    /*
     * This class provides filter options for any CRIS data.
     */
    public function __construct($definitions) {
        /*
         * Parse filter operators
         *
         * Currently supported: eq (equal), gt (greater), ge (greater equal),
         * lt (lower), le (lower equal)
         *
         * Operators are concatenated using __ (two underscores) to the
         * attribute name in order to denote the filter, e.g. publyear__eq
         *
         * All filters are expected as array. Array key is the filter, value is
         * the reference, e.g. array("publyear__eq" => 2015).
         *
         * If more than one filter is set, all filters are combined using "AND"
         */

        $filterlist = array();
        foreach ($definitions as $_k => $_v) {
            // force lower case statements
            $_op = explode('__', strtolower($_k));
            if (count($_op) != 2)
                throw new Exception('invalid filter operator: '. $_k);

            if (!array_key_exists($_op[0], $filterlist))
                $filterlist[$_op[0]] = array();
            $filterlist[$_op[0]][$_op[1]] = $_v;
        }
        $this->filters = $filterlist;
    }

    public function evaluate($data) {
        /*
         * Test "AND"-combined filters against data attributes.
         */
        foreach ($this->filters as $attr => $_f) {
            if (empty($data->attributes[$attr]))
                /*
                 * If attribute is not present, skip filter silently. This makes
                 * the test successful and may be therefore a bad idea.
                 */
                continue;

            foreach ($_f as $operator => $reference) {
                if ($this->compare($data->attributes[$attr], $operator, $reference) === false)
                    return false;
            }
        }
        return true;
    }

    private function compare($value, $operator, $reference) {
        /*
         * Check attribute value. The comparision is done non-strict so we
         * don't have to care for value types.
         */
        switch ($operator) {
            case "eq":
                return ($value == $reference);
            case "le":
                return ($value <= $reference);
            case "lt":
                return ($value < $reference);
            case "ge":
                return ($value >= $reference);
            case "gt":
                return ($value > $reference);
            default:
                throw new Exception('invalid compare operator: '. $operator);
        }
    }
}


class CRIS_formatter {
    /*
     * This class provides grouping and sorting methods for any CRIS data.
     * It will return reformatted data.
     */
    private $sortkey;
    private $sortvalues;

    public function __construct(
            $group_attribute, $group_order=SORT_DESC, $sort_attribute=null,
            $sort_order=SORT_ASC) {
        /*
         * The method takes up to four arguments. First two group all datasets
         * into a sorted array. Last two arguments define the order inside
         * every group.
         * E.g. one can group all publications by type and sort them inside by
         * year.
         */

        $this->group = strtolower($group_attribute);
        # make all lookup values lower case
        if (is_array($group_order))
            $this->group_order = array_map('strtolower', $group_order);
        else
            $this->group_order = $group_order;

        $this->sort = strtolower($sort_attribute);
        if (is_array($sort_order))
            $this->sort_order = array_map('strtolower', $sort_order);
        else
            $this->sort_order = $sort_order;
    }

    public function execute($data) {
        /*
         * Perform formatting on $data.
         */
        $final = array();
        foreach ($data as $single_dataset) {
            if (!array_key_exists($this->group, $single_dataset->attributes))
                throw new Exception('attribute not found: '. $this->group);

            $value = $single_dataset->attributes[$this->group];

            if (!array_key_exists($value, $final))
                $final[$value] = array();

            $final[$value][] = $single_dataset;
        }

        # first sort main groups
        if (is_array($this->group_order)) {
            # user-defined array for sorting
            $this->sortkey = $this->group;
            $this->sortvalues = $this->group_order;
            uksort($final, "self::compare_group");
        } elseif ($this->group_order === SORT_ASC)
            ksort($final);
        elseif ($this->group_order === SORT_DESC)
            krsort($final);
        else
            throw new Exception('unknown sorting');

        # sort data inside groups
        foreach ($final as $_k => $group) {
            $this->sortkey = $this->sort;
            uasort($group, "self::compare_attributes");
            if ($this->sort_order === SORT_DESC)
                $final[$_k] = array_reverse ($group, true);
            else
                $final[$_k] = $group;
        }

        return $final;
    }

    private function compare_group($a, $b) {
        # look-up index
        # returns false if not found (in case that sort array is incomplete)
        $_a = array_search(strtolower($a), $this->sortvalues);
        $_b = array_search(strtolower($b), $this->sortvalues);

        if ($_a == $b) return 0;
        if ($_a === false || $_a > $_b) return 1;
        if ($_b === false || $_a < $_b) return -1;
    }

    private function compare_attributes($a, $b) {
        # Compare data based on attribute specified in self::sortkey
        return strcmp($a->attributes[$this->sortkey], $b->attributes[$this->sortkey]);
    }
}


class CRIS_publication {
    /*
     * object for single publication
     */

    public function __construct($data) {
        $this->ID = (string) $data['id'];
        $this->attributes = array();

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

    public function insert_quotation_links() {
        /*
         * Enrich APA/MLA quotation by links to publication details (CRIS
         * website) and DOI (if present, applies only to APA).
         */

        $doilink = preg_quote("https://dx.doi.org/", "/");
        $title = preg_quote($this->attributes["cftitle"], "/");

        $cristmpl = '<a href="https://cris.fau.de/converis/publicweb/publication/%d" target="_blank">%s</a>';

        $apa = $this->attributes["quotationapa"];
        $mla = $this->attributes["quotationmla"];

        $matches = array();
        $splitapa = preg_match("/^(.+)(". $title .")(.+)(". $doilink .".+)?$/Uu",
                $apa, $matches);

        if ($splitapa === 1) {
            $apalink = $matches[1] . \
                    sprintf($cristmpl, $this->ID, $matches[2]) . $matches[3];
            if (isset($matches[4]))
                $apalink .= sprintf('<a href="%s" target="_blank">%s</a>',
                        $matches[4], $matches[4]);
        } else {
            $apalink = $apa;
        }

        $this->attributes["quotationapalink"] = $apalink;

        $matches = array();
        $splitmla = preg_match("/^(.+)(". $title .")(.+)$/", $mla, $matches);

        if ($splitmla === 1) {
            $mlalink = $matches[1] . \
                    sprintf($cristmpl, $this->ID, $matches[2]) . $matches[3];
        } else {
            $mlalink = $mla;
        }

        $this->attributes["quotationmlalink"] = $mlalink;
    }
}
