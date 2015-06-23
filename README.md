CRIS-Plugin für Wordpress
=========================

Version 1.3.2

Einbinden von Daten aus der FAU-Forschungsdatenbank <strong>CRIS</strong> in Wordpress-Instanzen

Für die <strong>Publikationslisten</strong> lassen sich über den Shortcode verschiedene Ausgabeformen einstellen. Die Titel sind jeweils mit der Detailansicht der Publikation auf http://cris.fau.de verlinkt.

## Integration FAU-Plugin:

Wenn Sie das <b>FAU-Person</b>-Plugin verwenden, können Autoren mit ihrer FAU-Person-Kontaktseite verlinkt werden.

Wenn diese Option in den Einstellungen des CRIS-Plugins aktiviert ist, überprüft das Plugin selbstständig, welche Personen vorhanden sind und setzt die entsprechenden Links.

##Shortcode:

###[cris show="publikationen"]
Bindet eine Liste aller Publikationen Ihrer Organisationseinheit ein.
Mögliche Zusatzoptionen:
- <b>orderby="year"</b>: Liste nach Jahren absteigend gegliedert (Voreinstellung)
- <b>orderby="pubtype"</b>: Liste nach Publikationstypen gegliedert.
- <b>year="2015"</b>: Nur Publikationen aus einem bestimmten Jahr
- <b>pubtype="buecher"</b>: Es werden nur Publikationen eines bestimmten Typs angezeigt:
	- buecher
    - zeitschriftenartikel
    - sammelbandbeitraege
    - herausgeberschaften
    - konferenzbeitraege
    - uebersetzungen
    - abschlussarbeiten
    - andere