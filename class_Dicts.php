<?php

class CRIS_Dicts {

    public static $base_uri = "https://cris.fau.de/ws-cached/1.0/public/infoobject/";

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

    /* Sprachen, in denen die einzelnen Typbezeichnungen aus dem Webservice kommen:
     * Publication: EN (Conference contribution)
     * Awards:      DE (Preis / Ehrung)
     * Projekte:    EN (Own Funds)
     * Patente:     DE (Prioritätsbegründende Patentanmeldung)
     * Aktivitäten: DE (Sonstige FAU-externe Aktivitäten)
     */

    public static $publications = array(
        'Journal article' => array(
            'order' => 2,
            'short' => 'zeitschriftenartikel',
            'de' => array(
                'name' => 'Beitrag in einer Fachzeitschrift',
                'title' => 'Beiträge in Fachzeitschriften'),
            'en'  => array(
                'name' => 'Journal article',
                'title' => 'Journal Articles')
        ),
        'Article in Edited Volumes' => array(
            'order' => 3,
            'short' => 'sammelbandbeitraege',
            'de' => array(
                'name' => 'Beitrag in einem Sammelband',
                'title' => 'Beiträge in Sammelbänden'),
            'en'  => array(
                'name' => 'Book Contribution',
                'title' => 'Book Contributions')
        ),
        'Book' => array(
            'order' => 1,
            'short' => 'buecher',
            'de' => array(
                'name' => 'Buch',
                'title' => 'Bücher'),
            'en'  => array(
                'name' => 'Authored book',
                'title' => 'Authored Books')
        ),
        'Translation' => array(
            'order' => 6,
            'short' => 'uebersetzungen',
            'de' => array(
                'name' => 'Übersetzung',
                'title' => 'Übersetzungen'),
            'en'  => array(
                'name' => 'Translation',
                'title' => 'Translations')
        ),
        'Thesis' => array(
            'order' => 7,
            'short' => 'abschlussarbeiten',
            'de' => array(
                'name' => 'Abschlussarbeit',
                'title' => 'Abschlussarbeiten'),
            'en'  => array(
                'name' => 'Thesis',
                'title' => 'Thesis')
        ),
        'Editorial' => array(
            'order' => 4,
            'short' => 'herausgeberschaften',
            'de' => array(
                'name' => 'Herausgegebener Band',
                'title' => 'Herausgegebene Bände'),
            'en'  => array(
                'name' => 'Edited Volume',
                'title' => 'Edited Volumes')
        ),
        'Conference contribution' => array(
            'order' => 5,
            'short' => 'konferenzbeitraege',
            'de' => array(
                'name' => 'Beitrag bei einer Tagung',
                'title' => 'Beiträge bei Tagungen'),
            'en'  => array(
                'name' => 'Conference contribution',
                'title' => 'Conference Contributions')
        ),
        'Other' => array(
            'order' => 8,
            'short' => 'andere',
            'de' => array(
                'name' => 'Sonstige',
                'title' => 'Sonstige'),
            'en'  => array(
                'name' => 'Miscellaneous',
                'title' => 'Miscellaneous')
        ),
        'Unpublished' => array(
            'order' => 9,
            'short' => 'unveroeffentlicht',
            'de' => array(
                'name' => 'Unveröffentlichte Publikation / Preprint',
                'title' => 'Unveröffentlichte Publikationen / Preprint'),
            'en'  => array(
                'name' => 'Unpublished Publication',
                'title' => 'Unpublished Publications')
        ),
    );

    public static $awards = array(
        'Preis / Ehrung' => array(
            'order' => 1,
            'short' => 'preise',
            'de' => array(
                'name' => 'Preis / Ehrung',
                'title' => 'Preise / Ehrungen'),
            'en'  => array(
                'name' => 'Award / Honour',
                'title' => 'Awards / Honours')
        ),
        'Stipendium / Grant' => array(
            'order' => 2,
            'short' => 'stipendien',
            'de' => array(
                'name' => 'Stipendium / Grant',
                'title' => 'Stipendien / Grants'),
            'en'  => array(
                'name' => 'Scholarship / Grant',
                'title' => 'Scholarships / Grants')
        ),
        'Akademie-Mitgliedschaft' => array(
            'order' => 3,
            'short' => 'mitgliedschaften',
            'de' => array(
                'name' => 'Akademie-Mitgliedschaft',
                'title' => 'Akademie-Mitgliedschaften'),
            'en'  => array(
                'name' => 'Academy membership',
                'title' => 'Academy Memberships')
        ),
        'Kleiner Preis' => array(
            'order' => 4,
            'short' => 'andere',
            'de' => array(
                'name' => 'Weiterer Preis / Auszeichnung',
                'title' => 'Weiterere Preise / Auszeichnungen'),
            'en'  => array(
                'name' => 'Other Award',
                'title' => 'Other Awards')
        ),
        '' => array(
            'order' => 5,
            'short' => 'keintyp',
            'de' => array(
                'name' => 'Weiterer Preis / Auszeichnung',
                'title' => 'Weiterere Preise / Auszeichnungen'),
            'en'  => array(
                'name' => 'Other Award',
                'title' => 'Other Awards')
        ),
    );

    public static $projects = array(
        'Third Party Funds Single' => array(
            'order' => 1,
            'short' => 'einzelfoerderung',
            'de' => array(
                'name' => 'Drittmittelfinanzierte Einzelförderung',
                'title' => 'Drittmittelfinanzierte Einzelförderungen'),
            'en'  => array(
                'name' => 'Third Party Funds Single',
                'title' => 'Third Party Funds Single')
        ),
        'Third Party Funds Group - Sub project' => array(
            'order' => 2,
            'short' => 'teilprojekt',
            'de' => array(
                'name' => 'Drittmittelfinanzierte Gruppenförderung &ndash; Teilprojekt',
                'title' => 'Drittmittelfinanzierte Gruppenförderungen &ndash; Teilprojekte'),
            'en'  => array(
                'name' => 'Third Party Funds Group &ndash; Sub project',
                'title' => 'Third Party Funds Group &ndash; Sub projects')
        ),
        'Third Party Funds Group - Overall project' => array(
            'order' => 3,
            'short' => 'gesamtprojekt',
            'de' => array(
                'name' => 'Drittmittelfinanzierte Gruppenförderung &ndash; Gesamtprojekt',
                'title' => 'Drittmittelfinanzierte Gruppenförderungen &ndash; Gesamtprojekte'),
            'en'  => array(
                'name' => 'Third Party Funds Group &ndash; Overall project',
                'title' => 'Third Party Funds Group &ndash; Overall projects')
        ),
        'Own and Third Party Funds Doctoral Programm - Overall project' => array(
            'order' => 4,
            'short' => 'graduiertenkolleg',
            'de' => array(
                'name' => 'Promotionsprogramm / Graduiertenkolleg',
                'title' => 'Promotionsprogramme / Graduiertenkollegs'),
            'en'  => array(
                'name' => 'Own and Third Party Funds Doctoral Programm &ndash; Overall project',
                'title' => 'Own and Third Party Funds Doctoral Programms &ndash; Overall projects')
        ),
        'Own Funds' => array(
            'order' => 5,
            'short' => 'eigenmittel',
            'de' => array(
                'name' => 'Projekt aus Eigenmitteln',
                'title' => 'Projekte aus Eigenmitteln'),
            'en'  => array(
                'name' => 'Own Funds',
                'title' => 'Own Funds')
        ),
    );

    public static $patents = array(
        'Prioritätsbegründende Patentanmeldung' => array(
            'order' => 1,
            'short' => 'patentanmeldung',
            'de' => array(
                'name' => 'Prioritätsbegründende Patentanmeldung',
                'title' => 'Prioritätsbegründende Patentanmeldungen'),
            'en'  => array(
                'name' => 'Priority Patent Application',
                'title' => 'Priority Patent Applications')
        ),
        'Gebrauchsmuster' => array(
            'order' => 2,
            'short' => 'gebrauchsmuster',
            'de' => array(
                'sg' => 'Gebrauchsmuster',
                'title' => 'Gebrauchsmuster'),
            'en'  => array(
                'sg' => 'Utility Model',
                'title' => 'Utility Models')
        ),
        'Schutzrecht' => array(
            'order' => 3,
            'short' => 'schutzrecht',
            'de' => array(
                'sg' => 'Schutzrecht',
                'title' => 'Schutzrechte'),
            'en'  => array(
                'sg' => 'Property Right',
                'title' => 'Property Rights')
        ),
        'Nachanmeldung' => array(
            'order' => 4,
            'short' => 'nachanmeldung',
            'de' => array(
                'sg' => 'Nachanmeldung',
                'title' => 'Nachanmeldungen'),
            'en'  => array(
                'sg' => 'Secondary Application',
                'title' => 'Secondary Applications')
        ),
        'Nationalisierung' => array(
            'order' => 5,
            'short' => 'nationalisierung',
            'de' => array(
                'sg' => 'Nationalisierung',
                'title' => 'Nationalisierungen'),
            'en'  => array(
                'sg' => 'Nationalisation',
                'title' => 'Nationalisations')
        ),
        'Validierung' => array(
            'order' => 6,
            'short' => 'validierung',
            'de' => array(
                'sg' => 'Validierung',
                'title' => 'Validierungen'),
            'en'  => array(
                'sg' => 'Validation',
                'title' => 'Validations')
        ),
    );

    public static $activities = array(
        'FAU-interne Gremienmitgliedschaften / Funktionen' => array(
            'order' => 1,
            'short' => 'fau-gremienmitgliedschaft',
            'de' => array(
                'name' => 'FAU-interne Gremienmitgliedschaft / Funktion',
                'title' => 'FAU-interne Gremienmitgliedschaften / Funktionen'),
            'en'  => array(
                'name' => 'Membership in representative bodies / functions (FAU-internal)',
                'title' => 'Memberships in representative bodies / functions (FAU-internal)')
        ),
        'Organisation einer Tagung / Konferenz' => array(
            'order' => 2,
            'short' => 'organisation_konferenz',
            'de' => array(
                'name' => 'Organisation einer Tagung / Konferenz',
                'title' => 'Organisation von Tagungen / Konferenzen'),
            'en'  => array(
                'name' => 'Organisation of a congress / conference',
                'title' => 'Organisation of a congress / conference')
        ),
        'Herausgeberschaft' => array(
            'order' => 3,
            'short' => 'herausgeberschaft',
            'de' => array(
                'name' => 'Herausgeberschaft',
                'title' => 'Herausgeberschaften'),
            'en'  => array(
                'name' => 'Editorship of a scientific journal',
                'title' => 'Editorships scientific journals')
        ),
        'Gutachtertätigkeit für wissenschaftliche Zeitschrift' => array(
            'order' => 4,
            'short' => 'gutachter_zeitschrift',
            'de' => array(
                'name' => 'Gutachtertätigkeit für eine wissenschaftliche Zeitschrift',
                'title' => 'Gutachtertätigkeiten für wissenschaftliche Zeitschriften'),
            'en'  => array(
                'name' => 'Expert for reviewing a scientific journal',
                'title' => 'Experts for reviewing scientific journals')
        ),
        'Gutachtertätigkeit für Förderorganisation' => array(
            'order' => 5,
            'short' => 'gutachter_organisation',
            'de' => array(
                'name' => 'Gutachtertätigkeit für eine Förderorganisation',
                'title' => 'Gutachtertätigkeiten für Förderorganisationen'),
            'en'  => array(
                'name' => 'Expert for funding organisation',
                'title' => 'Experts for funding organisations')
        ),
        'Sonstige FAU-externe Gutachtertätigkeit' => array(
            'order' => 6,
            'short' => 'gutachter_sonstige',
            'de' => array(
                'name' => 'Sonstige FAU-externe Gutachtertätigkeit',
                'title' => 'Sonstige FAU-externe Gutachtertätigkeiten'),
            'en'  => array(
                'name' => 'Other expert activitiy (FAU-external)',
                'title' => 'Other expert activities (FAU-external)')
        ),
        'DFG-Fachkollegiat/in' => array(
            'order' => 7,
            'short' => 'dfg-fachkollegiat',
            'de' => array(
                'name' => 'DFG-Fachkollegiat/in',
                'title' => 'DFG-Fachkollegiate'),
            'en'  => array(
                'name' => 'DFG-Subject field membership',
                'title' => 'DFG-Subject field memberships')
        ),
        'Gremiumsmitglied Wissenschaftsrat' => array(
            'order' => 8,
            'short' => 'mitglied_wissenschaftsrat',
            'de' => array(
                'name' => 'Gremiumsmitglied im Wissenschaftsrat',
                'title' => 'Gremiumsmitgliedschaften im Wissenschaftsrat'),
            'en'  => array(
                'name' => 'Member of the German Science Council',
                'title' => 'Members of the German Science Council')
        ),
        'Vortrag' => array(
            'order' => 9,
            'short' => 'vortrag',
            'de' => array(
                'name' => 'Vortrag',
                'title' => 'Vorträge'),
            'en'  => array(
                'name' => 'Speech / Talk',
                'title' => 'Speeches / Talks')
        ),
        'Radio- / Fernsehbeitrag / Podcast' => array(
            'order' => 10,
            'short' => 'medien',
            'de' => array(
                'name' => 'Radio- / Fernsehbeitrag / Podcast',
                'title' => 'Radio- / Fernsehbeiträge / Podcasts'),
            'en'  => array(
                'name' => 'Radio, Television or Podcast',
                'title' => 'Radio / Television Broadcasts or Podcasts')
        ),
        'Sonstige FAU-externe Aktivitäten' => array(
            'order' => 11,
            'short' => 'sonstige',
            'de' => array(
                'name' => 'Sonstige FAU-externe Aktivität',
                'title' => 'Sonstige FAU-externe Aktivitäten'),
            'en'  => array(
                'name' => 'Other activitiy (FAU-external)',
                'title' => 'Other activities (FAU-external)')
        ),
    );

    public static $pubOtherSubtypes = array(
        'Rezension' => array(
            'order' => 1,
            'short' => 'rezension',
            'de' => array(
                'name' => 'Rezension / Buchbesprechung',
                'title' => 'Rezensionen / Buchbesprechungen'
            ),
            'en' => array(
                'name' => 'Recension / Book review',
                'title' => 'Recensions / Book Reviews'
            )
        ),
        'Lexikonbeitrag' => array(
            'order' => 2,
            'short' => 'lexikonbeitrag',
            'de' => array(
                'name' => 'Lexikonbeitrag',
                'title' => 'Lexikonbeiträge'
            ),
            'en' => array(
                'name' => 'Dictionary / Encyclopedia entry',
                'title' => 'Dictionary / Encyclopedia Entries'
            )
        ),
        'Zeitungsartikel' => array(
            'order' => 3,
            'short' => 'zeitungsartikel',
            'de' => array(
                'name' => 'Zeitungsartikel',
                'title' => 'Zeitungsartikel'
            ),
            'en' => array(
                'name' => 'Newspaper article',
                'title' => 'Newspaper Articles'
            )
        ),
        'Working Paper' => array(
            'order' => 4,
            'short' => 'workingpaper',
            'de' => array(
                'name' => 'Diskussionspapier / Working Paper',
                'title' => 'Diskussionspapiere / Working Papers'
            ),
            'en' => array(
                'name' => 'Working paper',
                'title' => 'Working Papers'
            )
        ),
        'online publication' => array(
            'order' => 5,
            'short' => 'onlinepublikation',
            'de' => array(
                'name' => 'Online-Publikation',
                'title' => 'Online-Publikationen'
            ),
            'en' => array(
                'name' => 'Online publication',
                'title' => 'Online Publications'
            )
        ),
        'Conference report' => array(
            'order' => 6,
            'short' => 'konferenzbericht',
            'de' => array(
                'name' => 'Konferenzbericht',
                'title' => 'Konferenzberichte'
            ),
            'en' => array(
                'name' => 'Conference report',
                'title' => 'Conference Reports'
            )
        ),
        'Techreport' => array(
            'order' => 7,
            'short' => 'techreport',
            'de' => array(
                'name' => 'Technical Report',
                'title' => 'Technical Reports'
            ),
            'en' => array(
                'name' => 'Technical report',
                'title' => 'Technical Reports'
            )
        ),
        'Gutachten' => array(
            'order' => 8,
            'short' => 'gutachten',
            'de' => array(
                'name' => 'Gutachten',
                'title' => 'Gutachten'
            ),
            'en' => array(
                'name' => 'Expertise',
                'title' => 'Expertises'
            )
        ),
        'anderer' => array(
            'order' => 9,
            'short' => 'anderer',
            'de' => array(
                'name' => 'Anderer',
                'title' => 'Andere'
            ),
            'en' => array(
                'name' => 'Other',
                'title' => 'Other'
            )
        ),
    );
    
    public static $pubThesisSubtypes = array(
        'Habilitationsschrift' => array(
            'order' => 1,
            'short' => 'habilitation',
            'de' => array(
                'name' => 'Habilitationsschrift',
                'title' => 'Habilitationsschriften'
            ),
            'en' => array(
                'name' => 'Habilitation',
                'title' => 'Habilitations'
            )
        ),
        'Dissertation' => array(
            'order' => 2,
            'short' => 'dissertation',
            'de' => array(
                'name' => 'Dissertation',
                'title' => 'Dissertationen'
            ),
            'en' => array(
                'name' => 'Dissertation',
                'title' => 'Dissertations'
            )
        ),
        'Diplomarbeit' => array(
            'order' => 3,
            'short' => 'diplomarbeit',
            'de' => array(
                'name' => 'Diplomarbeit',
                'title' => 'Diplomarbeiten'
            ),
            'en' => array(
                'name' => 'Diploma thesis',
                'title' => 'Diploma Theses'
            )
        ),
        'Magisterarbeit' => array(
            'order' => 4,
            'short' => 'magisterarbeit',
            'de' => array(
                'name' => 'Magisterarbeit',
                'title' => 'Magisterarbeiten'
            ),
            'en' => array(
                'name' => 'Magister thesis',
                'title' => 'Magister Theses'
            )
        ),
        'Zulassungsarbeit' => array(
            'order' => 5,
            'short' => 'zulassungsarbeit',
            'de' => array(
                'name' => 'Zulassungsarbeit',
                'title' => 'Zulassungsarbeiten'
            ),
            'en' => array(
                'name' => 'Degree thesis',
                'title' => 'Degree Theses'
            )
        ),
        'Masterarbeit' => array(
            'order' => 6,
            'short' => 'masterarbeit',
            'de' => array(
                'name' => 'Masterarbeit',
                'title' => 'Masterarbeiten'
            ),
            'en' => array(
                'name' => 'Master thesis',
                'title' => 'Master Theses'
            )
        ),
        'Bachelorarbeit' => array(
            'order' => 7,
            'short' => 'bachelorarbeit',
            'de' => array(
                'name' => 'Bachelorarbeit',
                'title' => 'Bachelorarbeiten'
            ),
            'en' => array(
                'name' => 'Bachelor thesis',
                'title' => 'Bachelor Theses'
            )
        ),
        'Studienarbeit' => array(
            'order' => 8,
            'short' => 'studienarbeit',
            'de' => array(
                'name' => 'Studienarbeit (Vordiplom)',
                'title' => 'Studienarbeiten (Vordiplom)'
            ),
            'en' => array(
                'name' => 'Mid-study thesis',
                'title' => 'Mid-study Theses'
            )        
        ),
    );    
}
