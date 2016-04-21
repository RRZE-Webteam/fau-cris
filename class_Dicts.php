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

	public static $pubTitles = array(
		'Journal article' => array (
			'de_DE' => 'Zeitschriftenartikel',
			'de_DE_formal' => 'Zeitschriftenartikel',
			'en_US' => 'Journal articles',
			'en_GB' => 'Journal articles',
			'en_UK' => 'Journal articles'),
		'Conference contribution' => array (
			'de_DE' => 'Konferenzbeiträge',
			'de_DE_formal' => 'Konferenzbeiträge',
			'en_US' => 'Conference contributions',
			'en_GB' => 'Conference contributions',
			'en_UK' => 'Conference contributions'),
		'Translation' => array (
			'de_DE' => 'Konferenzbeiträge',
			'de_DE_formal' => 'Konferenzbeiträge',
			'en_US' => 'Translations',
			'en_GB' => 'Translations',
			'en_UK' => 'Translations'),
		'Book' => array (
			'de_DE' => 'Bücher',
			'de_DE_formal' => 'Bücher',
			'en_US' => 'Books',
			'en_GB' => 'Books',
			'en_UK' => 'Books'),
		'Editorial' => array (
			'de_DE' => 'Herausgeberschaften',
			'de_DE_formal' => 'Herausgeberschaften',
			'en_US' => 'Editorials',
			'en_GB' => 'Editorials',
			'en_UK' => 'Editorials'),
		'Thesis' => array (
			'de_DE' => 'Abschlussarbeiten',
			'de_DE_formal' => 'Abschlussarbeiten',
			'en_US' => 'Thesis',
			'en_GB' => 'Thesis',
			'en_UK' => 'Thesis'),
		'Other' => array (
			'de_DE' => 'Sonstige',
			'de_DE_formal' => 'Sonstige',
			'en_US' => 'Other',
			'en_GB' => 'Other',
			'en_UK' => 'Other'),
		'Article in Edited Volumes' => array (
			'de_DE' => 'Sammelbandbeiträge',
			'de_DE_formal' => 'Sammelbandbeiträge',
			'en_US' => 'Articles in Edited Volumes',
			'en_GB' => 'Articles in Edited Volumes',
			'en_UK' => 'Articles in Edited Volumes')
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
			'en' => 'Other'),
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
			'en' => 'Award / Honour',
		),
		'stipendien'	=> array(
			'de' => 'Stipendium / Grant',
			'en' => 'Scholarship / Grant',
		),
		'mitgliedschaften'	=> array(
			'de' => 'Akademie-Mitgliedschaft',
			'en' => 'Academy Member',
		),
		'andere'	=> array(
			'de' => 'Weiterer Preis / Auszeichnung',
			'en' => 'Other Award',
		)
	);
}
