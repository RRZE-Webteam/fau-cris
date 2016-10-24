<?php

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

        if ($group_attribute != null)
            $this->group = strtolower($group_attribute);
        else
            $this->group = null;
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
         * Perform formatting on $data. If $limit is set, return $limit entries
         * at max.
         */

        $final = array();
        foreach ($data as $single_dataset) {
            if ($this->group != null) {
                if (!array_key_exists($this->group, $single_dataset->attributes))
                        throw new Exception('attribute not found: '. $this->group);
                $group_key = $this->group;
            } else {
                # no grouping requested, we assume that sort is set in this case
                # also the case if a maximum limit is set
                $group_key = $this->sort;
            }

            if ($this->group === null)
                $value = $group_key;
            else
                $value = $single_dataset->attributes[$group_key];

            if (!array_key_exists($value, $final))
                $final[$value] = array();

            if (!empty($value)) {
                $final[$value][] = $single_dataset;
            } else {
                $final['o.J.'][] = $single_dataset;
            }
        }

        # first sort main groups
        if (is_array($this->group_order)) {
                # user-defined array for sorting
                $this->sortkey = $group_key;
                $this->sortvalues = $this->group_order;
                uksort($final, "self::compare_group");
        } elseif ($this->group_order === SORT_ASC)
                ksort($final);
        elseif ($this->group_order === SORT_DESC)
                krsort($final);
        elseif ($this->group_order !== NULL)
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