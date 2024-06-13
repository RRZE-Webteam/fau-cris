<?php
namespace RRZE\Cris;
defined('ABSPATH') || exit;

class Formatter
{
    /*
     * This class provides grouping and sorting methods for any CRIS data.
     * It will return reformatted data.
     */
    private $sortkey;
    private $group;
    private $sort_order;
    private $sortvalues;
    private $group_order;
    private $sort;
    public function __construct(
        $group_attribute,
        $group_order = SORT_DESC,
        $sort_attribute = null,
        $sort_order = SORT_ASC

    ) {
        /*
         * The method takes up to four arguments. First two group all datasets
         * into a sorted array. Last two arguments define the order inside
         * every group.
         * E.g. one can group all publications by type and sort them inside by
         * year.
         */

        if ($group_attribute != null) {
            $this->group = strtolower($group_attribute);
        } else {
            $this->group = null;
        }
        # make all lookup values lower case
        if (is_array($group_order)) {
            $this->group_order = array_map('strtolower', $group_order);
        } else {
            $this->group_order = $group_order;
        }

        if ($sort_attribute != null) {
            $this->sort = strtolower($sort_attribute);
        } else {
            $this->sort = null;
        }
            
        if (is_array($sort_order)) {
            $this->sort_order = array_map('strtolower', $sort_order);
        } else {
            $this->sort_order = $sort_order;
        }
    }

    public function execute($data): array
    {
        /*
         * Perform formatting on $data. If $limit is set, return $limit entries
         * at max.
         */

        $final = array();
        foreach ($data as $single_dataset) {
            if ($this->group != null) {
                if (!array_key_exists($this->group, $single_dataset->attributes)) {
                    trigger_error('Attribute not found: '. $this->group);
                    $group_key = $this->sort;
                } else {
                    $group_key = $this->group;
                }
            } else {
                # no grouping requested, we assume that sort is set in this case
                # also the case if a maximum limit is set
                $group_key = $this->sort;
            }

            if ($this->group === null) {
                $value = $group_key;
            } else {
                $value = $single_dataset->attributes[$group_key];
            }

            if (!array_key_exists($value, $final)) {
                $final[$value] = array();
            }

            if (!empty($value)) {
                $final[$value][] = $single_dataset;
            } else {
                $final[__('O.A.', 'fau-cris')][] = $single_dataset;
            }
        }
        unset($final[0]);

        # first sort main groups
        if (is_array($this->group_order)) {
            # user-defined array for sorting
            $this->sortkey = $group_key;
            $this->sortvalues = $this->group_order;
            uksort($final, [Formatter::class, 'compare_group']);
        } elseif ($this->group_order === SORT_ASC) {
            ksort($final);
        } elseif ($this->group_order === SORT_DESC) {
            krsort($final);
        } elseif ($this->group_order !== null) {
            trigger_error('Unknown sorting');
        }

        # sort data inside groups
        foreach ($final as $_k => $group) {
            if ($_k == "Other" || $_k == "O.A." || $_k == "o.a." || $_k == __('O.A.', 'fau-cris') || $_k ==  __('o.a.', 'fau-cris')) {
                /* } elseif (!is_array($this->sort)){*/
                $final[$_k] = $group;
            } else {
                $this->sortkey = $this->sort;
                uasort($group,[Formatter::class,'compare_attributes']);
                if ($this->sort_order === SORT_DESC) {
                    $final[$_k] = array_reverse($group, true);
                } else {
                    $final[$_k] = $group;
                }
            }
            if (empty($group)) {
                unset($final[$_k]);
            }
        }
        return $final;
    }

    private function compare_group($a, $b)
    {
        # look-up index
        # returns false if not found (in case that sort array is incomplete)
        $_a = array_search(strtolower($a), $this->sortvalues);
        $_b = array_search(strtolower($b), $this->sortvalues);

        if ($_a == $b) {
            return 0;
        }
        if ($_a === false || $_a > $_b) {
            return 1;
        }
        if ($_b === false || $_a < $_b) {
            return -1;
        }
    }

    private function compare_attributes($a, $b)
    {
        # Compare data based on attribute specified in self::sortkey
        if (is_numeric($a->attributes[$this->sortkey]) && is_numeric($b->attributes[$this->sortkey])) {
            return $a->attributes[$this->sortkey] - $b->attributes[$this->sortkey];
        } else {
            return strcmp($a->attributes[$this->sortkey], $b->attributes[$this->sortkey]);
        }
    }
}
