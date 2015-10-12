CRIS-Plugin für Wordpress
=========================

Version 1.51

Einbinden von Daten aus der FAU-Forschungsdatenbank <strong>CRIS</strong> in Wordpress-Instanzen

Für die <strong>Publikationslisten</strong> lassen sich über den Shortcode verschiedene Ausgabeformen einstellen. Die Titel sind jeweils mit der Detailansicht der Publikation auf http://cris.fau.de verlinkt.

##Shortcodes

###[cris]
Bindet eine Liste aller Publikationen Ihrer Organisationseinheit ein.<br>

#### Mögliche Zusatzoptionen:

##### Gliederung
- <b>orderby="year"</b>: Liste nach Jahren absteigend gegliedert (Voreinstellung)
- <b>orderby="pubtype"</b>: Liste nach Publikationstypen gegliedert. Die Reihenfolge der Publikationstypen kann in den Einstellungen nach Belieben festgelegt werden.

##### Filter
- <b>year="2015"</b>: Nur Publikationen aus einem bestimmten Jahr
- <b>start="2000"</b>: Nur Publikationen ab einem bestimmten Jahr
- <b>pubtype="buecher"</b>: Es werden nur Publikationen eines bestimmten Typs angezeigt:
	- buecher
    - zeitschriftenartikel
    - sammelbandbeitraege
    - herausgeberschaften
    - konferenzbeitraege
    - uebersetzungen
    - abschlussarbeiten
    - andere
- <b>publication="12345678"</b>: Nur eine einzelne Publikation (hier die CRIS-ID der Publikation angeben)

##### ID überschreiben
Die in den Einstellungen festgelegte CRIS-ID kann überschrieben werden, entweder durch die ID einer anderen Organisationseinheit, oder durch die ID einer einzelnen Person:
- <b>orgID="123456"</b> für eine von den Einstellungen abweichende Organisations-ID
- <b>persID="123456"</b> für die Publikationsliste einer konkreten Person

#### Beispiele
Die Daten lassen sich gliedern und/oder filtern:<br>
<code>[cris pubtype="buecher"]</code> => Alle Bücher<br>
<code>[cris year="2015"]</code> => Alle Publikationen aus dem Jahr 2015<br>
<code>[cris persID="123456" year="2000" orderby="pubtype"]</code> => Alle Publikationen der Person mit der CRIS-ID 123456 aus dem Jahr 2000, nach Publikationstypen gegliedert

##Integration "FAU Person"

Wenn Sie das <a href="https://github.com/RRZE-Webteam/fau-person">FAU-Person-Plugin</a> verwenden, können Autoren aus der Publikationsliste mit ihrer FAU-Person-Kontaktseite verlinkt werden.

Wenn diese Option in den Einstellungen des CRIS-Plugins aktiviert ist, überprüft das Plugin selbstständig, welche Personen vorhanden sind und setzt die entsprechenden Links.