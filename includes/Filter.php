<?php
namespace RRZE\Cris;
defined('ABSPATH') || exit;

class Filter
{
    /*
     * This class provides filter options for any CRIS data.
     */
    public $filters;
    public $skip;


    public function __construct($definitions)
    {
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
        foreach ((array)$definitions as $_k => $_v) {
            // force lower case statements
            $_op = explode('__', strtolower($_k));
            if (count($_op) != 2) {
                throw new Exception('invalid filter operator: '. $_k);
            }

            if (!array_key_exists($_op[0], $filterlist)) {
                $filterlist[$_op[0]] = array();
            }
            $filterlist[$_op[0]][$_op[1]] = $_v;
        }
        $this->filters = $filterlist;
        // list for attributes to be skipped once on evaluation
        $this->skip = array();
    }

    public function evaluate($data): bool
    {
        /*
         * Test "AND"-combined filters against data attributes.
         */

        // get and clear skip list
        $skip = $this->skip;
        $this->skip = array();

        foreach ($this->filters as $attr => $_f) {
            // skip marked attributes
            if (in_array($attr, $skip)) {
                continue;
            }
            if (empty($data->attributes[$attr])) {
                /*
                 * If attribute is not present, skip filter silently. This makes
                 * the test successful and may be therefore a bad idea.
                 */
                //continue;
                return false;
            }
            foreach ($_f as $operator => $reference) {
                if ($operator == 'eq' && !is_array($reference)) {
                    $reference = (array)$reference;
                }
                if ($this->compare($data->attributes[$attr], $operator, $reference) == false) {
                    return false;
                }
            }
        }
        return true;
    }

    private function compare($value, $operator, $reference): bool
    {
        /*
         * Check attribute value. The comparision is done non-strict so we
         * don't have to care for value types.
         */
        switch ($operator) {
            case "eq":
                if (is_string($value)) {
                    return (in_array(strtolower($value), array_map('strtolower', $reference)));
                } else {
                    return (in_array($value, $reference));
                }
                // no break
            case "not":
                if (is_string($value)) {
                    return (!in_array(strtolower($value), array_map('strtolower', $reference)));
                } else {
                    return (!in_array($value, $reference));
                }
                // no break
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
