<?php

class CRIS_Dicts {

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
			'en' => 'Conference Contribution'),
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

}
