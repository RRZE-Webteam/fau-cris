CRIS-Plugin für Wordpress
=========================

Einbinden von Daten aus der FAU-Forschungsdatenbank <strong>CRIS</strong> in Wordpress-Instanzen

Für die <strong>Mitarbeiter</strong> wird jeweils eine Profilseite mit Kontaktdaten und (optional) den Publikationen des Mitarbeiters erstellt und von der <strong>Mitarbeiterliste</strong> aus verlinkt.

Für die <strong>Publikationslisten</strong> lassen sich über den Shortcode verschiedene Ausgabeformen einstellen. Die Titel sind jeweils mit der Detailansicht der Publikation auf http://cris.fau.de verlinkt.

##Shortcodes:
###[cris show="mitarbeiter"]
Bindet eine Liste aller Mitarbeiter Ihrer Organisationseinheit ein.
Mögliche Zusatzoptionen:
- <b>orderby="job"</b>: Liste hierarchisch nach Funktionen gegliedert (Voreinstellung)
- <b>orderby="name"</b>: Alphabetische Liste, die Funktion wird jeweils in Klammern hinter dem Namen angezeigt.

###[cris show="publikationen"]
Bindet eine Liste aller Publikationen Ihrer Organisationseinheit ein.
Mögliche Zusatzoptionen:
- <b>orderby="year"</b>: Liste nach Jahren absteigend gegliedert (Voreinstellung)
- <b>orderby="pubtype"</b>: Liste nach Publikationstypen gegliedert.
- <b>year="2015"</b>: Nur Publikationen aus einem bestimmten Jahr
- <b>pubtype="Book"</b>: Es werden nur Publikationen eines bestimmten Typs angezeigt:
	- Book	->	Bücher
	- Journal article	->	Zeitschriftenartikel
	- Article in Edited Volumes	->	Beiträge in Sammelbänden
	- Editorial	->	Herausgegebene Sammelbände
	- Conference contribution	->	Konferenzbeiträge
	- Translation	->	Übersetzungen
	- Thesis	->	Abschlussarbeiten
	- Other	->	Sonstige