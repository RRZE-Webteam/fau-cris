CRIS-Plugin für Wordpress
=========================

Version 1.74 (Stand 19.04.2016)

Einbinden von Daten aus dem FAU-Forschungsinformationssystem <b>CRIS</b> in Webseiten

Aktuell werden folgende in CRIS erfasste Forschungsleistungen unterstützt:
- Publikationen
- Auszeichnungen
Über den Shortcode lassen sich jeweils verschiedene Ausgabeformate einstellen.

##Shortcodes
- Publikationsliste (automatisch nach Jahren gegliedert):<br />
  <b>[cris show="publications"]</b>
- Auszeichnungen (automatisch nach Jahren sortiert):<br />
  <b>[cris show="awards"]</b>

## Mögliche Zusatzoptionen:

### Gliederung
- <b>orderby="year"</b>: Liste nach Jahren absteigend gegliedert (Voreinstellung)
- <b>orderby="type"</b>: Liste nach Publikations- bzw. Auszeichnungstypen gegliedert. Die Reihenfolge der kann in den Einstellungen nach Belieben festgelegt werden.

### Filter
- <b>year="2015"</b>: Nur Einträge aus einem bestimmten Jahr
- <b>start="2000"</b>: Nur Einträge ab einem bestimmten Jahr
- <b>type=XXX</b>: Es werden nur Einträge eines bestimmten Typs angezeigt:
	- Publikationen:
		- buecher
		- zeitschriftenartikel
		- sammelbandbeitraege
		- herausgeberschaften
		- konferenzbeitraege
		- uebersetzungen
		- abschlussarbeiten
		- andere
	- Auszeichnungen:
		- preise
		- stipendien
		- mitgliedschaften
		- andere
- <b>publication="12345678"</b>: Nur eine einzelne Publikation (hier die CRIS-ID der Publikation angeben)
- <b>awardnameid="158"</b>: Nur eine einzelne Auszeichnung (hier die CRIS-ID der Auszeichnung angeben)
- <b>award="12345678"</b>: Nur eine einzelne Preisverleihung (hier die CRIS-ID der Verleihung angeben)<br>
  Hinweis zum Unterschied zwischen awardnameid und award: <b>awardnameid</b> bedeutet die ID eines Preises, der normalerweise mehrfach vergeben wird, z.B. der "Gottfried-Wilhelm-Leibniz-Preis". <b>award</b> (bzw. dessen ID) bedeutet die konkrete, einmalige Verleihung dieses Preises an eine bestimmte Person.
- Filter lassen sich auch kombinieren: z.B. <b>year="2014" type="buecher"</b> (= alle Bücher aus dem Jahr 2014)

### Darstellung

#### Publikationen
- <b>quotation="apa"</b> bzw. <b>quotation="mla"</b>: Ausgabe im Zitationsstil APA bzw. MLA

#### Auszeichnungen
- <b>display="gallery"</b>: Bildergalerie mit Bild des Preisträgers und Angaben zum Preis
- <b>showname=0</b> oder <b>showyear=0</b>: Name des Preisträgers bzw. Jahreszahl wird nicht angezeigt. Das kann z.B. bei Darstellungen auf einer Personenprofilseite bzw. in der nach Jahren gegliederten Ansicht sinnvoll sein.

### ID überschreiben
Die in den Einstellungen festgelegte CRIS-ID kann überschrieben werden, entweder durch die ID einer anderen Organisationseinheit, oder durch die ID einer einzelnen Person:
- <b>orgID="123456"</b> für eine von den Einstellungen abweichende Organisations-ID
- <b>persID="123456"</b> für die Einträge zu einer konkreten Person

## Beispiele
Die Daten lassen sich gliedern und/oder filtern:<br>
<code>[cris show="publications" pubtype="buecher"]</code> => Alle Bücher<br>
<code>[cris show="publications" year="2015"]</code> => Alle Publikationen aus dem Jahr 2015<br>
<code>[cris show="publications" persID="123456" year="2000" orderby="pubtype"]</code> => Alle Publikationen der Person mit der CRIS-ID 123456 aus dem Jahr 2000, nach Publikationstypen gegliedert

##Integration "FAU Person"

Wenn Sie das <a href="https://github.com/RRZE-Webteam/fau-person">FAU-Person-Plugin</a> verwenden, können Autoren aus der Publikationsliste mit ihrer FAU-Person-Kontaktseite verlinkt werden.

Wenn diese Option in den Einstellungen des CRIS-Plugins aktiviert ist, überprüft das Plugin selbstständig, welche Personen vorhanden sind und setzt die entsprechenden Links.