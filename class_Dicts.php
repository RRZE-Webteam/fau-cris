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
        'sortby' => '',
        'award' => '',
        'type' => '',
        'showname' => 1,
        'showyear' => 1,
        'showawardname' => 1,
        'display' => 'list',
        'quotation' => '',
        'items' => '',
        'role' => 'leader'
    );
    public static $pubTitles = array(
        'Journal article' => array(
            'de_DE' => 'Zeitschriftenartikel',
            'de_DE_formal' => 'Zeitschriftenartikel',
            'en_US' => 'Journal articles',
            'en_GB' => 'Journal articles',
            'en_UK' => 'Journal articles'),
        'Conference contribution' => array(
            'de_DE' => 'Konferenzbeiträge',
            'de_DE_formal' => 'Konferenzbeiträge',
            'en_US' => 'Conference contributions',
            'en_GB' => 'Conference contributions',
            'en_UK' => 'Conference contributions'),
        'Translation' => array(
            'de_DE' => 'Übersetzungen',
            'de_DE_formal' => 'Übersetzungen',
            'en_US' => 'Translations',
            'en_GB' => 'Translations',
            'en_UK' => 'Translations'),
        'Book' => array(
            'de_DE' => 'Bücher',
            'de_DE_formal' => 'Bücher',
            'en_US' => 'Books',
            'en_GB' => 'Books',
            'en_UK' => 'Books'),
        'Editorial' => array(
            'de_DE' => 'Herausgeberschaften',
            'de_DE_formal' => 'Herausgeberschaften',
            'en_US' => 'Editorials',
            'en_GB' => 'Editorials',
            'en_UK' => 'Editorials'),
        'Thesis' => array(
            'de_DE' => 'Abschlussarbeiten',
            'de_DE_formal' => 'Abschlussarbeiten',
            'en_US' => 'Thesis',
            'en_GB' => 'Thesis',
            'en_UK' => 'Thesis'),
        'Other' => array(
            'de_DE' => 'Sonstige',
            'de_DE_formal' => 'Sonstige',
            'en_US' => 'Other',
            'en_GB' => 'Other',
            'en_UK' => 'Other'),
        'Article in Edited Volumes' => array(
            'de_DE' => 'Sammelbandbeiträge',
            'de_DE_formal' => 'Sammelbandbeiträge',
            'en_US' => 'Articles in Edited Volumes',
            'en_GB' => 'Articles in Edited Volumes',
            'en_UK' => 'Articles in Edited Volumes')
    );
    public static $pubNames = array(
        'zeitschriftenartikel' => array(
            'de' => 'Zeitschriftenartikel',
            'en' => 'Journal article'),
        'sammelbandbeitraege' => array(
            'de' => 'Beiträge in Sammelbänden',
            'en' => 'Article in Edited Volumes'),
        'uebersetzungen' => array(
            'de' => 'Übersetzungen',
            'en' => 'Translation'),
        'buecher' => array(
            'de' => "Bücher",
            'en' => 'Book'),
        'herausgeberschaften' => array(
            'de' => 'Herausgeberschaften',
            'en' => 'Editorial'),
        'konferenzbeitraege' => array(
            'de' => 'Konferenzbeiträge',
            'en' => 'Conference contribution'),
        'abschlussarbeiten' => array(
            'de' => 'Abschlussarbeiten',
            'en' => 'Thesis'),
        'andere' => array(
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
        'preise',
        'stipendien',
        'mitgliedschaften',
        'andere'
    );
    public static $awardTitles = array(
        'Akademie-Mitgliedschaft' => array(
            'de_DE' => 'Akademie-Mitgliedschaften',
            'de_DE_formal' => 'Akademie-Mitgliedschaften',
            'en_US' => 'Academy Memberships',
            'en_GB' => 'Academy Memberships',
            'en_UK' => 'Academy Memberships'),
        'Preis / Ehrung' => array(
            'de_DE' => 'Preise / Ehrungen',
            'de_DE_formal' => 'Preise / Ehrungen',
            'en_US' => 'Awards / Honours',
            'en_GB' => 'Awards / Honours',
            'en_UK' => 'Awards / Honours'),
        'Stipendium / Grant' => array(
            'de_DE' => 'Stipendien / Grants',
            'de_DE_formal' => 'Stipendien / Grants',
            'en_US' => 'Scholarships / Grants',
            'en_GB' => 'Scholarships / Grants',
            'en_UK' => 'Scholarships / Grants'),
        'Weiterer Preis / Auszeichnung' => array(
            'de_DE' => 'Weitere Preise / Auszeichnungen',
            'de_DE_formal' => 'Weitere Preise / Auszeichnungen',
            'en_US' => 'Other Awards',
            'en_GB' => 'Other Awards',
            'en_UK' => 'Other Awards')
    );
    public static $awardNames = array(
        'preise' => array(
            'de' => 'Preis / Ehrung',
            'en' => 'Award / Honour',
        ),
        'stipendien' => array(
            'de' => 'Stipendium / Grant',
            'en' => 'Scholarship / Grant',
        ),
        'mitgliedschaften' => array(
            'de' => 'Akademie-Mitgliedschaft',
            'en' => 'Academy Member',
        ),
        'andere' => array(
            'de' => 'Weiterer Preis / Auszeichnung',
            'en' => 'Other Award',
        )
    );

    public static $projOrder = array(
        'einzelfoerderung',
        'teilprojekt',
        'gesamtprojekt',
        'graduiertenkolleg',
        'eigenmittel'
    );

    public static $projNames = array(
        'einzelfoerderung' => array(
            'de' => 'Drittmittelfinanzierte Einzelförderung',
            'en' => 'Third Party Funds Single'),
        'teilprojekt' => array(
            'de' => 'Drittmittelfinanzierte Gruppenförderung - Teilprojekt',
            'en' => 'Third Party Funds Group - Sub project'),
        'gesamtprojekt' => array(
            'de' => 'Drittmittelfinanzierte Gruppenförderung - Gesamtprojekt',
            'en' => 'Third Party Funds Group - Overall project'),
        'graduiertenkolleg' => array(
            'de' => 'Promotionsprogramm / Graduiertenkolleg',
            'en' => 'Own and Third Party Funds Doctoral Programm - Overall project'),
        'eigenmittel' => array(
            'de' => 'Projekt aus Eigenmitteln',
            'en' => 'Own Funds')
    );

    public static $projTitles = array(
        'Third Party Funds Single' => array(
            'de_DE' => 'Drittmittelfinanzierte Einzelförderungen',
            'en_US' => 'Third Party Funds Single'),
        'Third Party Funds Group - Sub project' => array(
            'de_DE' => 'Drittmittelfinanzierte Gruppenförderungen &ndash; Teilprojekte',
            'en_US' => 'Third Party Funds Group - Sub projects'),
        'Third Party Funds Group - Overall project' => array(
            'de_DE' => 'Drittmittelfinanzierte Gruppenförderungen &ndash; Gesamtprojekte',
            'en_US' => 'Third Party Funds Group - Overall projects'),
        'Own and Third Party Funds Doctoral Programm &hdash; Overall project' => array(
            'de_DE' => 'Graduiertenkollegs',
            'en_US' => 'Own and Third Party Funds Doctoral Programms &ndash; Overall projects'),
        'Own Funds' => array(
            'de_DE' => 'Projekte aus Eigenmitteln',
            'en_US' => 'Own Funds'),
    );

}
