<?php
/*
 * Examples for using the generic CRIS web service access.
 */
require_once( './includes/Publikationen.php' );

include('cris-generic.php');

$publ = new CRIS_publications();

/*
 * Define filter. You may pass an array or a instance of CRIS_filter to a
 * CRIS_publications instance. For list of operators see class constructor.
 */
$filter = array(
    // 2012-2014
    "publyear__ge" => 2012,
    "publyear__lt" => 2015,
    // request only publications that are created at FAU
    "FAU Publikation__eq" => "yes",
);

// request organisation's publications
// EAM: 140965, Inf5: 142477
$res = $publ->by_orga_id(142477, $filter);

// merge multiple organisations (e.g. chair & single professorship)
//$res = $publ->by_orga_id(array(142534, 142526), $filter);

// request publications for person
// Hornegger: 163324, Trinczek: 162826, Bodendorf: 163536
//$res = $publ->by_pers_id(162826);
// request publications of multiple distinct authors (not: get cooperations)
//$res = $publ->by_pers_id(array(162826, 163536));
// filter may be used:
//$res = $publ->by_pers_id(162826, $filter);

// direct (filter makes no sense)
//$res = $publ->by_id(36722);
// request multiple publications
//$res = $publ->by_id(array(36722, 1042922));

// For use on websites you may want to reformat the unsorted publication list.

// group by year + sort by title inside each year
//$formatter = new CRIS_formatter("publyear", SORT_ASC, "cftitle", SORT_ASC);
// group by year + sort by publication date inside each
//$formatter = new CRIS_formatter("publyear", SORT_ASC, "virtualdate", SORT_DESC);

// Grouping by user-defined list of publication types need this list *sigh*
// If any value occurs that is not listed here, it will be put at the end.
$o = array(
    "journal article",
    "conference contribution",
    "other",
    "article in edited volumes",
);

// sort inside each group by ...
// ... year
//$formatter = new CRIS_formatter("publication type", $o, "publyear", SORT_ASC);
// ... first author
$formatter = new CRIS_formatter("publication type", $o, "relauthors", SORT_ASC);
// ... title
//$formatter = new CRIS_formatter("publication type", $o, "cftitle", SORT_ASC);

$data = $formatter->execute($res);

// just displaying formatted data
foreach ($data as $group => $publs) {
    // Escape the group name before printing it
    printf("%s\n", esc_html($group));

    foreach ($publs as $p) {
        // Escape attributes before printing them
        printf(
            "%7d - %s %s\n",
            esc_html($p->ID),
            esc_html($p->attributes["publyear"]),
            esc_html($p->attributes["cftitle"])
        );
    }
}


// quotion link example
$publ = new CRIS_publications();
$res = $publ->by_id(array(36722, 1036431));
$p = $res[36722];
$p->insert_quotation_links();
print_r(array(
    $p->attributes["quotationapa"], $p->attributes["quotationapalink"],
    $p->attributes["quotationmla"], $p->attributes["quotationmlalink"]
));

$p = $res[1036431];
$p->insert_quotation_links();
print_r(array(
    $p->attributes["quotationapa"], $p->attributes["quotationapalink"],
    $p->attributes["quotationmla"], $p->attributes["quotationmlalink"]
));
