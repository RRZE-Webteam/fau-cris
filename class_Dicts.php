<?php

class CRIS_Dicts {

	public static $acronyms = array(
		"Dr."   =>  "Doktor",
		"Prof." =>  "Professor",
		"Dr.-Ing." => "Doktor-Ingenieur",
		"Dipl." =>  "Diplom",
		"Inf."  =>  "Informatik",
		"Wi."   =>  "Wirtschaftsinformatik",
		"Ma."   =>  "Mathematik",
		"Ing."  =>  "Ingenieurwissenschaft",
		"B.A."  =>  "Bakkalaureus",
		"M.A."  =>  "Magister Artium",
		"phil." =>  "Geisteswissenschaft",
		"pol." =>  "Politikwissenschaft",
		"nat." =>  "Naturwissenschaft",
		"soc."  =>  "Sozialwissenschaft",
		"techn."    =>  "technische Wissenschaften",
		"vet.med." =>  "Tiermedizin",
		"med.dent."    =>  "Zahnmedizin",
		"h.c."  =>  "ehrenhalber",
		"med."  =>  "Medizin",
		"jur."  =>  "Recht",
		"rer."  =>  ""
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
			'en' => 'Conference Contribution'),
		'abschlussarbeiten' => array (
			'de' => 'Abschlussarbeiten',
			'en' => 'Thesis'),
		'andere' => array (
			'de' => 'Sonstige',
			'en' => 'Other')
	);

	public static $jobOrder = array(
		"Lehrstuhlinhaber/in",
		"Professurinhaber/in",
		"Juniorprofessor/in",
		"apl. Professor/in",
		"Privatdozent/in",
		"Emeritus / Emerita",
		"Professor/in im Ruhestand",
		"Wissenschaftler/in",
		"Gastprofessoren (h.b.) an einer Univ.",
		"Honorarprofessor/in",
		"Doktorand/in",
		"HiWi",
		"Verwaltungsmitarbeiter/in",
		"technische/r Mitarbeiter/in",
		"FoDa-Administrator/in",
		"Andere"
	);

	public static $pubOrder = array(
		"Journal article",
		"Article in edited volumes",
		"Translation",
		"Book",
		"Editorial",
		"Conference Contribution",
		"Thesis",
		"Other"
	);

}
