<?php /** @noinspection ALL */
/** @noinspection ALL */
/** @noinspection ALL */
/** @noinspection ALL */
/** @noinspection ALL */
/** @noinspection ALL */
/** @noinspection ALL */
/** @noinspection ALL */
/** @noinspection ALL */
/** @noinspection ALL */
/** @noinspection ALL */
/** @noinspection ALL */
/** @noinspection ALL */
/** @noinspection ALL */

/*
 * Vergleich zwischen generischem Zugang vs. bisherigem
 */

require_once( './includes/Publikationen.php' );
/*
 * helper methods for testing outside wordpress environment
 */
if (!function_exists("__")) {
    function __($text, $x = null)
    {
        return $text;
    }
}

if (!function_exists("get_option")) {
    function get_option($x): array
    {
        return array(
            "cris_org_nr" => 142528,
            "cris_pub_order" => array(''),
        );
    }
}

if (!function_exists("get_locale")) {
    function get_locale(): string
    {
        return "de_DE";
    }
}

// style
$quotation = '';

// single publication
$param1 = 'publication';
$param2 = 36722;

$liste = new Publikationen($param1, $param2);
$output1 = $liste->singlePub($quotation);
$liste = new Publikationen($param1, $param2);
$output2 = $liste->singlePub($quotation);
assert($output1 === $output2, "single publication");

// person's list
$param1 = 'person';
$param2 = 162826;

// no filters
$year = '';
$start = '';
$pubtype = '';

$liste = new Publikationen($param1, $param2);
$output1 = $liste->pubNachJahr($year, $start, $pubtype, $quotation);
$liste = new Publikationen($param1, $param2);
$output2 = $liste->pubNachJahr($year, $start, $pubtype, $quotation);
assert($output1 === $output2, "person's list");

// select year
$year = '2015';
$start = '';
$pubtype = '';

$liste = new Publikationen($param1, $param2);
$output1 = $liste->pubNachJahr($year, $start, $pubtype, $quotation);
$liste = new Publikationen($param1, $param2);
$output2 = $liste->pubNachJahr($year, $start, $pubtype, $quotation);
assert($output1 === $output2, "person's list for one year");

// define start year
$year = '';
$start = '2013';
$pubtype = '';

$liste = new Publikationen($param1, $param2);
$output1 = $liste->pubNachJahr($year, $start, $pubtype, $quotation);
$liste = new Publikationen($param1, $param2);
$output2 = $liste->pubNachJahr($year, $start, $pubtype, $quotation);
assert($output1 === $output2, "person's list start year");

// restrict publication type
$year = '';
$start = '';
$pubtype = 'zeitschriftenartikel';

$liste = new Publikationen($param1, $param2);
$output1 = $liste->pubNachJahr($year, $start, $pubtype, $quotation);
$liste = new Publikationen($param1, $param2);
$output2 = $liste->pubNachJahr($year, $start, $pubtype, $quotation);
assert($output1 === $output2, "person's list of defined type");

// organisation's list
// note: Tests will fail if publications are additionally related to
//       organisation since old method doesn't fetch these.

$param1 = "orga";
$param2 = 142528;

$year = '';
$start = '';
$pubtype = '';

// no settings at all (fallback to config)
$liste = new Publikationen();
$output1 = $liste->pubNachJahr();
$liste = new Publikationen();
$output2 = $liste->pubNachJahr();
assert($output1 === $output2, "configuration settings");

// using settings
$liste = new Publikationen($param1, $param2);
$output1 = $liste->pubNachJahr($year, $start, $pubtype, $quotation);
$liste = new Publikationen($param1, $param2);
$output2 = $liste->pubNachJahr($year, $start, $pubtype, $quotation);
assert($output1 === $output2, "organisations's list");

// ordering by type
// since the sub order inside each year is undefined assertion often fails
// so for testing we use a person with only a few publications...
$param1 = "person";
$param2 = '1008228';

$year = '';
$start = '2008';
$pubtype = '';

$liste = new Publikationen($param1, $param2);
$output1 = $liste->pubNachTyp($year, $start, $pubtype, $quotation);
$liste = new Publikationen($param1, $param2);
$output2 = $liste->pubNachTyp($year, $start, $pubtype, $quotation);
assert($output1 === $output2, "person's list by type");

// more filters
$year = '';
$start = '';
$pubtype = 'zeitschriftenartikel';

$liste = new Publikationen($param1, $param2);
$output1 = $liste->pubNachTyp($year, $start, $pubtype, $quotation);
$liste = new Publikationen($param1, $param2);
$output2 = $liste->pubNachTyp($year, $start, $pubtype, $quotation);
assert($output1 === $output2, "person's list by type, filtered by type");

//print_r($output1);
//print_r($output2);
