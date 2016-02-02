<?php

class CRIS_Dicts {

	public static $defaults = array(
		'show' => 'publications',
		'orderby' => '',
		'year' => '',
		'start' => '',
		'orgid' => '',
		'persid' => '',
		'publication' => '',
		'pubtype' => '',
		'award' => '',
		'type' => '',
		'showname' => 1,
		'showyear' => 1,
		'display' => 'list'
	);

	public static $pubNames = array(
		'zeitschriftenartikel' => array (
			'de' => 'Zeitschriftenartikel',
			'en' => 'Journal article'),
		'sammelbandbeitraege' => array (
			'de' => 'Beiträge in Sammelbänden',
			'en' => 'Article in Edited Volumes'),
		'uebersetzungen' => array (
			'de' => 'Übersetzungen',
			'en' => 'Translation'),
		'buecher' => array (
			'de' => "Bücher",
			'en' => 'Book'),
		'herausgeberschaften' => array (
			'de' => 'Herausgeberschaften',
			'en' => 'Editorial'),
		'konferenzbeitraege' => array (
			'de' => 'Konferenzbeiträge',
			'en' => 'Conference contribution'),
		'abschlussarbeiten' => array (
			'de' => 'Abschlussarbeiten',
			'en' => 'Thesis'),
		'andere' => array (
			'de' => 'Sonstige',
			'en' => 'Other')
	);

	public static $pubOrder = array(
		"sammelbandbeitraege",
		"zeitschriftenartikel",
		"uebersetzungen",
		"buecher",
		"herausgeberschaften",
		"konferenzbeitraege",
		"abschlussarbeiten",
		"andere"
	);

	public static $awardOrder = array(
		"Preis / Ehrung",
		"Stipendium / Grant",
		"Akademie-Mitgliedschaft",
		"Weitere Preise"
	);

	public static $awardNames = array(
		'preise'	=> array(
			'de' => 'Preis / Ehrung',
			'en' => 'Preis / Ehrung',
		),
		'stipendien'	=> array(
			'de' => 'Stipendium / Grant',
			'en' => 'Stipendium / Grant',
		),
		'mitgliedschaften'	=> array(
			'de' => 'Akademie-Mitgliedschaft',
			'en' => 'Akademie-Mitgliedschaft',
		),
		'andere'	=> array(
			'de' => 'Weitere Preise',
			'en' => 'Weitere Preise',
		)
	);
}
