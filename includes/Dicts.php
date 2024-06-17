<?php
namespace RRZE\Cris;
defined('ABSPATH') || exit;


class Dicts
{

    public static string $base_uri = "https://cris.fau.de/ws-cached/1.0/public/infoobject/";
    
    public static array $defaults = array(
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
        'limit' => '',
        'role' => 'leader'
    );

    /* Sprachen, in denen die einzelnen Typbezeichnungen aus dem Webservice kommen:
     * Publication: EN (Conference contribution)
     * Awards:      DE (Preis / Ehrung)
     * Projekte:    EN (Own Funds)
     * Patente:     DE (Prioritätsbegründende Patentanmeldung)
     * Aktivitäten: DE (Sonstige FAU-externe Aktivitäten)
     */
    public static array $typeinfos = array(
        'publications' => array(
            'Journal article' => array(
                'order' => 2,
                'short' => 'beitrag_fachzeitschrift',
                'short_alt' => 'zeitschriftenartikel',
                'de' => array(
                    'name' => 'Beitrag in einer Fachzeitschrift',
                    'title' => 'Beiträge in Fachzeitschriften'),
                'en' => array(
                    'name' => 'Journal article',
                    'title' => 'Journal Articles'),
                'subtypeattribute' => 'Publication Journal Subtype',
                'subtypes' => array(
                    'Editorial' => array(
                        'order' => 1,
                        'short' => 'editorial',
                        'de' => array(
                            'name' => 'Editorial',
                            'title' => 'Editorials'
                        ),
                        'en' => array(
                            'name' => 'Editorial',
                            'title' => 'Editorials'
                        )
                    ),
                    'Erratum' => array(
                        'order' => 2,
                        'short' => 'erratum',
                        'de' => array(
                            'name' => 'Erratum',
                            'title' => 'Errata'
                        ),
                        'en' => array(
                            'name' => 'Erratum',
                            'title' => 'Errata'
                        )
                    ),
                    'Case study' => array(
                        'order' => 3,
                        'short' => 'fallstudie',
                        'de' => array(
                            'name' => 'Medizinische Fallstudie',
                            'title' => 'Medizinische Fallstudien'
                        ),
                        'en' => array(
                            'name' => 'Medical case study',
                            'title' => 'Medical Case Studies'
                        )
                    ),
                    'Letter' => array(
                        'order' => 4,
                        'short' => 'letter',
                        'de' => array(
                            'name' => 'Letter',
                            'title' => 'Letters'
                        ),
                        'en' => array(
                            'name' => 'Letter',
                            'title' => 'Letters'
                        )
                    ),
                    'Article in Journal' => array(
                        'order' => 5,
                        'short' => 'originalarbeit',
                        'de' => array(
                            'name' => 'Originalarbeit',
                            'title' => 'Originalarbeiten'
                        ),
                        'en' => array(
                            'name' => 'Original article',
                            'title' => 'Original Articles'
                        )
                    ),
                    'Report' => array(
                        'order' => 6,
                        'short' => 'report',
                        'de' => array(
                            'name' => 'Report',
                            'title' => 'Reports'
                        ),
                        'en' => array(
                            'name' => 'Report',
                            'title' => 'Reports'
                        )
                    ),
                    'Review article' => array(
                        'order' => 7,
                        'short' => 'reviewartikel',
                        'de' => array(
                            'name' => 'Review-Artikel',
                            'title' => 'Review-Artikel'
                        ),
                        'en' => array(
                            'name' => 'Review article',
                            'title' => 'Review Articles'
                        )
                    ),
                    'Short survey' => array(
                        'order' => 8,
                        'short' => 'shortsurvey',
                        'de' => array(
                            'name' => 'Short survey',
                            'title' => 'Short surveys'
                        ),
                        'en' => array(
                            'name' => 'Short survey',
                            'title' => 'Short Surveys'
                        )
                    ),
                    'Note' => array(
                        'order' => 9,
                        'short' => 'note',
                        'de' => array(
                            'name' => 'Note',
                            'title' => 'Notes'
                        ),
                        'en' => array(
                            'name' => 'Note',
                            'title' => 'Notes'
                        )
                    ),
                    'online publication' => array(
                        'order' => 10,
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
                    'Entscheidungsanmerkung' => array(
                        'order' => 11,
                        'short' => 'entscheidungsanmerkung',
                        'de' => array(
                            'name' => 'Entscheidungsanmerkung',
                            'title' => 'Entscheidungsanmerkungen'
                        ),
                        'en' => array(
                            'name' => 'Decision note',
                            'title' => 'Decision Notes'
                        )
                    ),
                    'undefined' => array(
                        'order' => 12,
                        'short' => 'andere',
                        'de' => array(
                            'name' => 'Andere / O.A.',
                            'title' => 'Andere / O.A.'
                        ),
                        'en' => array(
                            'name' => 'Other / na',
                            'title' => 'Other / na'
                        )
                    ),
                ),

            ),
            'Article in Edited Volumes' => array(
                'order' => 3,
                'short' => 'beitrag_sammelwerk',
                'short_alt' => 'sammelbandbeitraege',
                'de' => array(
                    'name' => 'Beitrag in einem Sammelwerk',
                    'title' => 'Beiträge in Sammelwerken'),
                'en' => array(
                    'name' => 'Book Contribution',
                    'title' => 'Book Contributions'),
                'subtypeattribute' => 'PublicationTypeEditedVolumes',
                'subtypes' => array(
                    'article' => array(
                        'order' => 1,
                        'short' => 'originalarbeit',
                        'de' => array(
                            'name' => 'Originalarbeit',
                            'title' => 'Originalarbeiten'
                        ),
                        'en' => array(
                            'name' => 'Original article',
                            'title' => 'Original Articles'
                        )
                    ),
                    'Case study' => array(
                        'order' => 2,
                        'short' => 'fallstudie',
                        'de' => array(
                            'name' => 'Fallstudie',
                            'title' => 'Fallstudien'
                        ),
                        'en' => array(
                            'name' => 'Case study',
                            'title' => 'Case studies'
                        )
                    ),
                    'Review Article' => array(
                        'order' => 3,
                        'short' => 'reviewartikel',
                        'de' => array(
                            'name' => 'Review-Artikel',
                            'title' => 'Review-Artikel'
                        ),
                        'en' => array(
                            'name' => 'Review article',
                            'title' => 'Review Articles'
                        )
                    ),
                    'Buchkapitel' => array(
                        'order' => 4,
                        'short' => 'buchkapitel',
                        'de' => array(
                            'name' => 'Buchkapitel',
                            'title' => 'Buchkapitel'
                        ),
                        'en' => array(
                            'name' => 'Book chapter',
                            'title' => 'Book Chapters'
                        )
                    ),
                    'Ausstellungskatalogsbeitrag' => array(
                        'order' => 5,
                        'short' => 'beitrag_ausstellungskatalog',
                        'de' => array(
                            'name' => 'Beitrag in Ausstellungskatalog',
                            'title' => 'Beitraege in Ausstellungskatalogen'
                        ),
                        'en' => array(
                            'name' => 'Exhibition catalogue contribution',
                            'title' => 'Exhibition Catalogue Contributions'
                        )
                    ),
                    'Beitrag in Festschrift' => array(
                        'order' => 6,
                        'short' => 'beitrag_festschrift',
                        'de' => array(
                            'name' => 'Beitrag in einer Festschrift / Gedenkschrift',
                            'title' => 'Beitraege in Festschriften / Gedenkschriften'
                        ),
                        'en' => array(
                            'name' => 'Article in a Festschrift / memorial volume',
                            'title' => 'Articles in Festschriften / Memorial Volumes'
                        )
                    ),
                    'Beitrag in Handbuch' => array(
                        'order' => 7,
                        'short' => 'beitrag_handbuch',
                        'de' => array(
                            'name' => 'Beitrag in einem Handbuch',
                            'title' => 'Beitraege in Handbüchern'
                        ),
                        'en' => array(
                            'name' => 'Article in a Manual',
                            'title' => 'Articles in Manuals'
                        )
                    ),
                    'Aufsatz' => array(
                        'order' => 8,
                        'short' => 'aufsatz',
                        'de' => array(
                            'name' => 'Aufsatz',
                            'title' => 'Aufsätze'
                        ),
                        'en' => array(
                            'name' => 'Essay',
                            'title' => 'Essays'
                        )
                    ),
                    'Other' => array(
                        'order' => 9,
                        'short' => 'andere',
                        'de' => array(
                            'name' => 'Anderer',
                            'title' => 'Andere'
                        ),
                        'en' => array(
                            'name' => 'Other',
                            'title' => 'Other'
                        )
                    ),
                    'online pub' => array(
                        'order' => 10,
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
                    'Entscheidungsanmerkung' => array(
                        'order' => 11,
                        'short' => 'entscheidungsanmerkung',
                        'de' => array(
                            'name' => 'Entscheidungsanmerkung',
                            'title' => 'Entscheidungsanmerkungen'
                        ),
                        'en' => array(
                            'name' => 'Decision note',
                            'title' => 'Decision Notes'
                        )
                    ),
                    'EditedVolumesSubtype_educational' => array(
                        'order' => 12,
                        'short' => 'lehrmaterial',
                        'de' => array(
                            'name' => 'Lehrmaterial',
                            'title' => 'Lehrmaterial'
                        ),
                        'en' => array(
                            'name' => 'Educational material',
                            'title' => 'Educational Material'
                        )
                    ),
                    'Kommentierung' => array(
                        'order' => 13,
                        'short' => 'kommentierung',
                        'de' => array(
                            'name' => 'Kommentierung',
                            'title' => 'Kommentierungen'
                        ),
                        'en' => array(
                            'name' => 'Annotation',
                            'title' => 'Annotations'
                        )
                    ),
                    'undefined' => array(
                        'order' => 14,
                        'short' => 'andere',
                        'de' => array(
                            'name' => 'Andere / O.A.',
                            'title' => 'Andere / O.A.'
                        ),
                        'en' => array(
                            'name' => 'Other / na',
                            'title' => 'Other / na'
                        )
                    ),
                ),
            ),
            'Book' => array(
                'order' => 1,
                'short' => 'buch',
                'short_alt' => 'buecher',
                'de' => array(
                    'name' => 'Buch',
                    'title' => 'Bücher'),
                'en' => array(
                    'name' => 'Authored book',
                    'title' => 'Authored Books'),
                'subtypeattribute' => 'Publication Book Subtype',
                'subtypes' => array(
                    'Monographie' => array(
                        'order' => 1,
                        'short' => 'monographie',
                        'short_alt' => 'monographie',
                        'de' => array(
                            'name' => 'Monographie',
                            'title' => 'Monographien'
                        ),
                        'en' => array(
                            'name' => 'Authored book',
                            'title' => 'Authored Books'
                        )
                    ),
                    'Band aus einer Reihe' => array(
                        'order' => 2,
                        'short' => 'band_reihe',
                        'short_alt' => 'bandausreihe',
                        'de' => array(
                            'name' => 'Band aus einer Reihe',
                            'title' => 'Bände aus einer Reihe'
                        ),
                        'en' => array(
                            'name' => 'Volume of book series',
                            'title' => 'Volumes of Book Series'
                        )
                    ),
                    'Manual' => array(
                        'order' => 3,
                        'short' => 'handbuch',
                        'short_alt' => 'handbuecher',
                        'de' => array(
                            'name' => 'Handbuch',
                            'title' => 'Handbücher'
                        ),
                        'en' => array(
                            'name' => 'Manual',
                            'title' => 'Manuals'
                        )
                    ),
                    'Lehrbuch' => array(
                        'order' => 4,
                        'short' => 'lehrbuch',
                        'short_alt' => 'lehrbuecher',
                        'de' => array(
                            'name' => 'Lehrbuch',
                            'title' => 'Lehrbücher'
                        ),
                        'en' => array(
                            'name' => 'Textbook',
                            'title' => 'Textbooks'
                        )
                    ),
                    'undefined' => array(
                        'order' => 5,
                        'short' => 'andere',
                        'de' => array(
                            'name' => 'Anderer / O.A.',
                            'title' => 'Andere / O.A.'
                        ),
                        'en' => array(
                            'name' => 'Other / na',
                            'title' => 'Other / na'
                        )
                    ),
                ),

            ),
            'Translation' => array(
                'order' => 6,
                'short' => 'uebersetzung',
                'short_alt' => 'uebersetzungen',
                'de' => array(
                    'name' => 'Übersetzung',
                    'title' => 'Übersetzungen'),
                'en' => array(
                    'name' => 'Translation',
                    'title' => 'Translations')
            ),
            'Thesis' => array(
                'order' => 7,
                'short' => 'abschlussarbeit',
                'short_alt' => 'abschlussarbeiten',
                'de' => array(
                    'name' => 'Abschlussarbeit',
                    'title' => 'Abschlussarbeiten'),
                'en' => array(
                    'name' => 'Thesis',
                    'title' => 'Thesis'),
                'subtypeattribute' => 'Publication Thesis Subtype',
                'subtypes' => array(
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
                    'undefined' => array(
                        'order' => 9,
                        'short' => 'andere',
                        'de' => array(
                            'name' => 'Anderer / O.A.',
                            'title' => 'Andere / O.A.'
                        ),
                        'en' => array(
                            'name' => 'Other / na',
                            'title' => 'Other / na'
                        )
                    ),
                ),

            ),
            'Editorial' => array(
                'order' => 4,
                'short' => 'herausgegebener_band',
                'short_alt' => 'herausgeberschaften',
                'de' => array(
                    'name' => 'Herausgegebener Band',
                    'title' => 'Herausgegebene Bände'),
                'en' => array(
                    'name' => 'Edited Volume',
                    'title' => 'Edited Volumes'),
                'subtypeattribute' => 'Publication Editorship Subtype',
                'subtypes' => array(
                    'Buch' => array(
                        'order' => 1,
                        'short' => 'buch',
                        'de' => array(
                            'name' => 'Buch',
                            'title' => 'Bücher'
                        ),
                        'en' => array(
                            'name' => 'Book',
                            'title' => 'Books'
                        )
                    ),
                    'Festschrift' => array(
                        'order' => 2,
                        'short' => 'festschrift',
                        'de' => array(
                            'name' => 'Festschrift',
                            'title' => 'Festschriften'
                        ),
                        'en' => array(
                            'name' => 'Memorial Volume',
                            'title' => 'Memorial Volumes'
                        )
                    ),
                    'Ausstellungskatalog' => array(
                        'order' => 3,
                        'short' => 'ausstellungskatalog',
                        'de' => array(
                            'name' => 'Ausstellungskatalog',
                            'title' => 'Ausstellungskataloge'
                        ),
                        'en' => array(
                            'name' => 'Exhibition catalogue',
                            'title' => 'Exhibition Catalogues'
                        )
                    ),
                    'Quellenedition' => array(
                        'order' => 4,
                        'short' => 'quellenedition',
                        'de' => array(
                            'name' => 'Quellenedition',
                            'title' => 'Quelleneditionen'
                        ),
                        'en' => array(
                            'name' => 'Source edition',
                            'title' => 'Source Editions'
                        )
                    ),
                    'specialissue' => array(
                        'order' => 5,
                        'short' => 'themenheft_zeitschrift',
                        'de' => array(
                            'name' => 'Themenheft einer Zeitschrift',
                            'title' => 'Themenhefte von Zeitschriften'
                        ),
                        'en' => array(
                            'name' => 'Special issue of a journal',
                            'title' => 'Special Issues of Journals'
                        )
                    ),
                    'undefined' => array(
                        'order' => 6,
                        'short' => 'andere',
                        'de' => array(
                            'name' => 'Anderer / O.A.',
                            'title' => 'Andere / O.A.'
                        ),
                        'en' => array(
                            'name' => 'Other / na',
                            'title' => 'Other / na'
                        )
                    ),
                ),

            ),
            'Conference contribution' => array(
                'order' => 5,
                'short' => 'beitrag_tagung',
                'short_alt' => 'konferenzbeitraege',
                'de' => array(
                    'name' => 'Beitrag bei einer Tagung',
                    'title' => 'Beiträge bei Tagungen'),
                'en' => array(
                    'name' => 'Conference contribution',
                    'title' => 'Conference Contributions'),
                'subtypeattribute' => 'Publication Conference Subtype',
                'subtypes' => array(
                    'Journal Article' => array(
                        'order' => 1,
                        'short' => 'originalarbeit',
                        'de' => array(
                            'name' => 'Originalarbeit',
                            'title' => 'Originalarbeiten'
                        ),
                        'en' => array(
                            'name' => 'Original article',
                            'title' => 'Original Articles'
                        )
                    ),
                    'Konferenzschrift' => array(
                        'order' => 2,
                        'short' => 'konferenzbeitrag',
                        'de' => array(
                            'name' => 'Konferenzbeitrag',
                            'title' => 'Konferenzbeiträge'
                        ),
                        'en' => array(
                            'name' => 'Conference contribution',
                            'title' => 'Conference Contributions'
                        )
                    ),
                    'Abstract zum Vortrag' => array(
                        'order' => 3,
                        'short' => 'abstract_vortrag',
                        'de' => array(
                            'name' => 'Abstract zum Vortrag',
                            'title' => 'Abstracts zu Vorträgen'
                        ),
                        'en' => array(
                            'name' => 'Abstract of lecture',
                            'title' => 'Abstracts of Lectures'
                        )
                    ),
                    'Abstract zum Poster' => array(
                        'order' => 4,
                        'short' => 'abstract_poster',
                        'de' => array(
                            'name' => 'Abstract zum Poster',
                            'title' => 'Abstracts zu Postern'
                        ),
                        'en' => array(
                            'name' => 'Abstract of a poster',
                            'title' => 'Abstracts of Posters'
                        )
                    ),
                    'undefined' => array(
                        'order' => 5,
                        'short' => 'andere',
                        'de' => array(
                            'name' => 'Anderer / O.A.',
                            'title' => 'Andere / O.A.'
                        ),
                        'en' => array(
                            'name' => 'Other / na',
                            'title' => 'Other / na'
                        )
                    ),
                ),
            ),
            'Other' => array(
                'order' => 8,
                'short' => 'andere',
                'de' => array(
                    'name' => 'Sonstige',
                    'title' => 'Sonstige'),
                'en' => array(
                    'name' => 'Miscellaneous',
                    'title' => 'Miscellaneous'),
                'subtypeattribute' => 'Type other subtype',
                'subtypes' => array(
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
                        'short' => 'andere',
                        'de' => array(
                            'name' => 'Anderer',
                            'title' => 'Andere'
                        ),
                        'en' => array(
                            'name' => 'Other',
                            'title' => 'Other'
                        )
                    ),
                ),

            ),
            'Unpublished' => array(
                'order' => 9,
                'short' => 'unveroeffentlicht',
                'de' => array(
                    'name' => 'Unveröffentlichte Publikation / Preprint',
                    'title' => 'Unveröffentlichte Publikationen / Preprint'),
                'en' => array(
                    'name' => 'Unpublished Publication',
                    'title' => 'Unpublished Publications'),
                'subtypeattribute' => 'FuturePublicationType',
                'subtypes' => array(
                    'Journal Article' => array(
                        'order' => 1,
                        'short' => 'beitrag_fachzeitschrift',
                        'de' => array(
                            'name' => 'Beitrag in einer Fachzeitschrift',
                            'title' => 'Beiträge in Fachzeitschriften'
                        ),
                        'en' => array(
                            'name' => 'Journal Article',
                            'title' => 'Journal Articles'
                        )
                    ),
                    'Article in Edited Volumes' => array(
                        'order' => 2,
                        'short' => 'beitrag_sammelwerk',
                        'de' => array(
                            'name' => 'Beitrag in einem Sammelwerk',
                            'title' => 'Beiträge in Sammelwerken'
                        ),
                        'en' => array(
                            'name' => 'Article in Edited Volume',
                            'title' => 'Articles in Edited Volumes'
                        )
                    ),
                    'Book' => array(
                        'order' => 3,
                        'short' => 'buch',
                        'de' => array(
                            'name' => 'Buch',
                            'title' => 'Bücher'
                        ),
                        'en' => array(
                            'name' => 'Book',
                            'title' => 'Books'
                        )
                    ),
                    'Translation' => array(
                        'order' => 4,
                        'short' => 'uebersetzung',
                        'de' => array(
                            'name' => 'Übersetzung',
                            'title' => 'Übersetzung'
                        ),
                        'en' => array(
                            'name' => 'Translation',
                            'title' => 'Translations'
                        )
                    ),
                    'Thesis' => array(
                        'order' => 5,
                        'short' => 'abschlussarbeit',
                        'de' => array(
                            'name' => 'Abschlussarbeit',
                            'title' => 'abschlussarbeiten'
                        ),
                        'en' => array(
                            'name' => 'Thesis',
                            'title' => 'Thesis'
                        )
                    ),
                    'Edited Volumes' => array(
                        'order' => 6,
                        'short' => 'herausgegebener_band',
                        'de' => array(
                            'name' => 'Herausgegebener Band',
                            'title' => 'Herausgegebene Bände'
                        ),
                        'en' => array(
                            'name' => 'Edited Volume',
                            'title' => 'Edited Volumes'
                        )
                    ),
                    'Conference contribution' => array(
                        'order' => 7,
                        'short' => 'beitrag_tagung',
                        'de' => array(
                            'name' => 'Beitrag bei einer Tagung',
                            'title' => 'Beiträge bei Tagungen'
                        ),
                        'en' => array(
                            'name' => 'Conference contribution',
                            'title' => 'Conference Contributions'
                        )
                    ),
                    'Other' => array(
                        'order' => 8,
                        'short' => 'andere',
                        'de' => array(
                            'name' => 'Anderer',
                            'title' => 'Andere'
                        ),
                        'en' => array(
                            'name' => 'Other',
                            'title' => 'Other'
                        )
                    ),
                )
            ),
        ),
        'awards' => array(
            'Preis / Ehrung' => array(
                'order' => 1,
                'short' => 'preis',
                'short_alt' => 'preise',
                'de' => array(
                    'name' => 'Preis / Ehrung',
                    'title' => 'Preise / Ehrungen'),
                'en' => array(
                    'name' => 'Award / Honour',
                    'title' => 'Awards / Honours')
            ),
            'Stipendium / Grant' => array(
                'order' => 2,
                'short' => 'stipendium',
                'short_alt' => 'stipendien',
                'de' => array(
                    'name' => 'Stipendium / Grant',
                    'title' => 'Stipendien / Grants'),
                'en' => array(
                    'name' => 'Scholarship / Grant',
                    'title' => 'Scholarships / Grants')
            ),
            'Akademie-Mitgliedschaft' => array(
                'order' => 3,
                'short' => 'akademiemitgliedschaft',
                'short_alt' => 'mitgliedschaften',
                'de' => array(
                    'name' => 'Akademie-Mitgliedschaft',
                    'title' => 'Akademie-Mitgliedschaften'),
                'en' => array(
                    'name' => 'Academy membership',
                    'title' => 'Academy Memberships')
            ),
            'Kleiner Preis' => array(
                'order' => 4,
                'short' => 'andere',
                'de' => array(
                    'name' => 'Weiterer Preis / Auszeichnung',
                    'title' => 'Weiterere Preise / Auszeichnungen'),
                'en' => array(
                    'name' => 'Other Award',
                    'title' => 'Other Awards')
            ),
            '' => array(
                'order' => 5,
                'short' => 'keintyp',
                'de' => array(
                    'name' => 'Weiterer Preis / Auszeichnung',
                    'title' => 'Weiterere Preise / Auszeichnungen'),
                'en' => array(
                    'name' => 'Other Award',
                    'title' => 'Other Awards')
            ),
        ),
        'projects' => array(
            'Third Party Funds Single' => array(
                'order' => 1,
                'short' => 'einzelfoerderung',
                'de' => array(
                    'name' => 'Drittmittelfinanzierte Einzelförderung',
                    'title' => 'Drittmittelfinanzierte Einzelförderungen'),
                'en' => array(
                    'name' => 'Third Party Funds Single',
                    'title' => 'Third Party Funds Single')
            ),
            'Third Party Funds Group - Sub project' => array(
                'order' => 2,
                'short' => 'teilprojekt',
                'de' => array(
                    'name' => 'Drittmittelfinanzierte Gruppenförderung &ndash; Teilprojekt',
                    'title' => 'Drittmittelfinanzierte Gruppenförderungen &ndash; Teilprojekte'),
                'en' => array(
                    'name' => 'Third Party Funds Group &ndash; Sub project',
                    'title' => 'Third Party Funds Group &ndash; Sub projects')
            ),
            'Third Party Funds Group - Overall project' => array(
                'order' => 3,
                'short' => 'gesamtprojekt',
                'de' => array(
                    'name' => 'Drittmittelfinanzierte Gruppenförderung &ndash; Gesamtprojekt',
                    'title' => 'Drittmittelfinanzierte Gruppenförderungen &ndash; Gesamtprojekte'),
                'en' => array(
                    'name' => 'Third Party Funds Group &ndash; Overall project',
                    'title' => 'Third Party Funds Group &ndash; Overall projects')
            ),
            'Own and Third Party Funds Doctoral Programm - Overall project' => array(
                'order' => 4,
                'short' => 'graduiertenkolleg',
                'de' => array(
                    'name' => 'Promotionsprogramm / Graduiertenkolleg',
                    'title' => 'Promotionsprogramme / Graduiertenkollegs'),
                'en' => array(
                    'name' => 'Own and Third Party Funds Doctoral Programm &ndash; Overall project',
                    'title' => 'Own and Third Party Funds Doctoral Programms &ndash; Overall projects')
            ),
            'Own Funds' => array(
                'order' => 5,
                'short' => 'eigenmittel',
                'de' => array(
                    'name' => 'Projekt aus Eigenmitteln',
                    'title' => 'Projekte aus Eigenmitteln'),
                'en' => array(
                    'name' => 'Own Funds',
                    'title' => 'Own Funds')
            ),
            'Fremdprojekt' => array(
                'order' => 5,
                'short' => 'fremdprojekt',
                'de' => array(
                    'name' => 'FAU-externes Projekt',
                    'title' => 'FAU-externe Projekte'),
                'en' => array(
                    'name' => 'Non-FAU Project',
                    'title' => 'Non-FAU Projects')
            ),
        ),
        'patents' => array(
            'Prioritätsbegründende Patentanmeldung' => array(
                'order' => 1,
                'short' => 'patentanmeldung',
                'de' => array(
                    'name' => 'Prioritätsbegründende Patentanmeldung',
                    'title' => 'Prioritätsbegründende Patentanmeldungen'),
                'en' => array(
                    'name' => 'Priority Patent Application',
                    'title' => 'Priority Patent Applications')
            ),
            'Gebrauchsmuster' => array(
                'order' => 2,
                'short' => 'gebrauchsmuster',
                'de' => array(
                    'name' => 'Gebrauchsmuster',
                    'title' => 'Gebrauchsmuster'),
                'en' => array(
                    'name' => 'Utility Model',
                    'title' => 'Utility Models')
            ),
            'Schutzrecht' => array(
                'order' => 3,
                'short' => 'schutzrecht',
                'de' => array(
                    'name' => 'Schutzrecht',
                    'title' => 'Schutzrechte'),
                'en' => array(
                    'name' => 'Property Right',
                    'title' => 'Property Rights')
            ),
            'Nachanmeldung' => array(
                'order' => 4,
                'short' => 'nachanmeldung',
                'de' => array(
                    'name' => 'Nachanmeldung',
                    'title' => 'Nachanmeldungen'),
                'en' => array(
                    'name' => 'Secondary Application',
                    'title' => 'Secondary Applications')
            ),
            'Nationalisierung' => array(
                'order' => 5,
                'short' => 'nationalisierung',
                'de' => array(
                    'name' => 'Nationalisierung',
                    'title' => 'Nationalisierungen'),
                'en' => array(
                    'name' => 'Nationalisation',
                    'title' => 'Nationalisations')
            ),
            'Validierung' => array(
                'order' => 6,
                'short' => 'validierung',
                'de' => array(
                    'name' => 'Validierung',
                    'title' => 'Validierungen'),
                'en' => array(
                    'name' => 'Validation',
                    'title' => 'Validations')
            ),
        ),
        'activities' => array(
            'FAU-interne Gremienmitgliedschaften / Funktionen' => array(
                'order' => 1,
                'short' => 'fau-gremienmitgliedschaft',
                'de' => array(
                    'name' => 'FAU-interne Gremienmitgliedschaft / Funktion',
                    'title' => 'FAU-interne Gremienmitgliedschaften / Funktionen'),
                'en' => array(
                    'name' => 'Membership in representative bodies / functions (FAU-internal)',
                    'title' => 'Memberships in representative bodies / functions (FAU-internal)'),
                'subtypes' => array(
                    'Women\'s representative of FAU' => array(
                        'de' => array('name' => 'Frauenbeautragte/r der FAU'),
                        'en' => array('name' => 'Women\'s representative of FAU'),
                    ),
                    'Senat (Membership)' => array(
                        'de' => array('name' => 'Senat (Mitglied)'),
                        'en' => array('name' => 'Senat (Membership)'),
                    ),
                    'Special Representative' => array(
                        'de' => array('name' => 'Sonderbeauftragte/r'),
                        'en' => array('name' => 'Special Representative'),
                    ),
                    'Speaker of the Faculty' => array(
                        'de' => array('name' => 'Fachbereichsleitung'),
                        'en' => array('name' => 'Speaker of the Faculty'),
                    ),
                    'Library Commettee (Presidency)' => array(
                        'de' => array('name' => 'Bibliotheksausschuss (Vorsitz)'),
                        'en' => array('name' => 'Library Commettee (Presidency)'),
                    ),
                    'Library Commettee (Membership)' => array(
                        'de' => array('name' => 'Bibliotheksausschuss (Mitglied)'),
                        'en' => array('name' => 'Library Commettee (Membership)'),
                    ),
                    'Open-Access-Representative' => array(
                        'de' => array('name' => 'Open-Access-Beauftragte/r'),
                        'en' => array('name' => 'Open-Access-Representative'),
                    ),
                    'Faculty Management Board (Presidency)' => array(
                        'de' => array('name' => 'Fakultätsvorstand (Vorsitz)'),
                        'en' => array('name' => 'Faculty Management Board (Presidency)'),
                    ),
                    'Fakultätsvorstand (Mitglied)' => array(
                        'de' => array('name' => 'Fakultätsvorstand (Mitglied)'),
                        'en' => array('name' => 'Faculty Management Board (Membership)'),
                    ),
                    'Department\'s board of directors (Membership)' => array(
                        'de' => array('name' => 'Departmentleitung (Mitglied)'),
                        'en' => array('name' => 'Department\'s board of directors (Membership)'),
                    ),
                    'Commission for Ethical Issues (Presidency)' => array(
                        'de' => array('name' => 'Ethikkommission (Vorsitz)'),
                        'en' => array('name' => 'Commission for Ethical Issues (Presidency)'),
                    ),
                    'Commission for Ethical Issues (Membership)' => array(
                        'de' => array('name' => 'Ethikkommission (Mitglied)'),
                        'en' => array('name' => 'Commission for Ethical Issues (Membership)'),
                    ),
                    'Commission for Internationalisation (Presidency)' => array(
                        'de' => array('name' => 'Kommission Internationalisierung (Vorsitz)'),
                        'en' => array('name' => 'Commission for Internationalisation (Presidency)'),
                    ),
                    'Commission for Internationalisation (Membership)' => array(
                        'de' => array('name' => 'Kommission Internationalisierung (Mitglied)'),
                        'en' => array('name' => 'Commission for Internationalisation (Membership)'),
                    ),
                    'Commission on Study & Teaching (Presidency)' => array(
                        'de' => array('name' => 'Kommission Lehre / Studium (Vorsitz)'),
                        'en' => array('name' => 'Commission on Study & Teaching (Presidency)'),
                    ),
                    'Commission on Study & Teaching (Membership)' => array(
                        'de' => array('name' => 'Kommission Lehre / Studium (Mitglied)'),
                        'en' => array('name' => 'Commission on Study & Teaching (Membership)'),
                    ),
                    'Commission on Computer Systems (Membership)' => array(
                        'de' => array('name' => 'Kommission Rechenanlagen (Mitglied)'),
                        'en' => array('name' => 'Commission on Computer Systems (Membership)'),
                    ),
                    'Commission on Erratic Behaviour in Science (Presidency)' => array(
                        'de' => array('name' => 'Kommission wissenschaftliches Fehlverhalten (Vorsitz)'),
                        'en' => array('name' => 'Commission on Erratic Behaviour in Science (Presidency)'),
                    ),
                    'Commission on Erratic Behaviour in Science (Membership)' => array(
                        'de' => array('name' => 'Kommission wissenschaftliches Fehlverhalten (Mitglied)'),
                        'en' => array('name' => 'Commission on Erratic Behaviour in Science (Membership)'),
                    ),
                    'Commission on Conflict Settlement (Presidency)' => array(
                        'de' => array('name' => 'Konfliktkommision (Vorsitz)'),
                        'en' => array('name' => 'Commission on Conflict Settlement (Presidency)'),
                    ),
                    'Commission on Conflict Settlement (Membership)' => array(
                        'de' => array('name' => 'Konfliktkommision (Mitglied)'),
                        'en' => array('name' => 'Commission on Conflict Settlement (Membership)'),
                    ),
                    'Board of Examiners of the Faculty (Presidency)' => array(
                        'de' => array('name' => 'Prüfungsausschuss der Fakultät (Vorsitz)'),
                        'en' => array('name' => 'Board of Examiners of the Faculty (Presidency)'),
                    ),
                    'Board of Examiners of the Faculty (Membership)' => array(
                        'de' => array('name' => 'Prüfungsausschuss der Fakultät (Mitglied)'),
                        'en' => array('name' => 'Board of Examiners of the Faculty (Membership)'),
                    ),
                    'Commission for Equal Opportunities (Membership)' => array(
                        'de' => array('name' => 'Komission Chancengleichheit (Mitgliedschaft)'),
                        'en' => array('name' => 'Commission for Equal Opportunities (Membership)'),
                    ),
                    'Board of the Faculty (Membership)' => array(
                        'de' => array('name' => 'Fakultätsrat (Mitglied)'),
                        'en' => array('name' => 'Board of the Faculty (Membership)'),
                    ),
                    'Board of Research / Young academics (Membership)' => array(
                        'de' => array('name' => 'Kommission Forschung / wissenschaftl. Nachwuchs (Mitglied)'),
                        'en' => array('name' => 'Board of Research / Young academics (Membership)'),
                    ),
                    'Board of Research / Young academics (Presidency)' => array(
                        'de' => array('name' => 'Kommission Forschung / wissenschaftl. Nachwuchs (Vorsitz)'),
                        'en' => array('name' => 'Board of Research / Young academics (Presidency)'),
                    ),
                    'Convention on Researchers (Presidency)' => array(
                        'de' => array('name' => 'Konvent wissenschaftliche Mitarbeiter (Vorsitz)'),
                        'en' => array('name' => 'Convention on Researchers (Presidency)'),
                    ),
                    'Convention on Researchers (Managing Board)' => array(
                        'de' => array('name' => 'Konvent wissenschaftliche Mitarbeiter (Leitungsgremium)'),
                        'en' => array('name' => 'Convention on Researchers (Managing Board)'),
                    ),
                    'Senat (Presidency)' => array(
                        'de' => array('name' => 'Senat (Vorsitz)'),
                        'en' => array('name' => 'Senat (Presidency)'),
                    ),
                    'Management Board / Speaker of a FAU-Central Institute\')' => array(
                        'de' => array('name' => 'Kollegiale Leitung / Sprecher/in eines FAU-Zentralinstituts'),
                        'en' => array('name' => 'Management Board / Speaker of a FAU-Central Institute'),
                    ),
                    'Speaker of SFB, GRK or elite program' => array(
                        'de' => array('name' => 'Sprecher für SFB, GRK oder Elitestudiengang'),
                        'en' => array('name' => 'Speaker of SFB, GRK or elite program'),
                    ),
                    'Central Board on Disposition of the Tuition Fees' => array(
                        'de' => array('name' => 'Zentrales Gremium Verwendung Studienbeiträge'),
                        'en' => array('name' => 'Central Board on Disposition of the Tuition Fees'),
                    ),
                    'Decentralised Board on Disposition of the Tuition Fees' => array(
                        'de' => array('name' => 'Dezentrales Gremium zur Verwendung der Studienbeiträge'),
                        'en' => array('name' => 'Decentralised Board on Disposition of the Tuition Fees'),
                    ),
                    'Interdisziplinary Center (Speaker)' => array(
                        'de' => array('name' => 'Interdisziplinäres Zentrum (Sprecher/in)'),
                        'en' => array('name' => 'Interdisziplinary Center (Speaker)'),
                    ),
                    'Interdisziplinary Center (Member)' => array(
                        'de' => array('name' => 'Interdisziplinäres Zentrum (Mitglied)'),
                        'en' => array('name' => 'Interdisziplinary Center (Member)'),
                    ),
                    'Appointment Committee (Member)' => array(
                        'de' => array('name' => 'Berufungskommission (Mitglied)'),
                        'en' => array('name' => 'Appointment Committee (Member)'),
                    ),
                    'Appointment Committee (Presidency)' => array(
                        'de' => array('name' => 'Berufungskommission (Vorsitz)'),
                        'en' => array('name' => 'Appointment Committee (Presidency)'),
                    ),
                    'Dean' => array(
                        'de' => array('name' => 'Dekan'),
                        'en' => array('name' => 'Dean'),
                    ),
                    'Vice Dean' => array(
                        'de' => array('name' => 'Prodekan'),
                        'en' => array('name' => 'Vice Dean'),
                    ),
                    'Interdisziplinary Center (Board member)' => array(
                        'de' => array('name' => 'Interdisziplinäres Zentrum (Vorstandsmitglied)'),
                        'en' => array('name' => 'Interdisziplinary Center (Board member)'),
                    ),
                    'EDP committee' => array(
                        'de' => array('name' => 'EDV-Kommission'),
                        'en' => array('name' => 'EDP committee'),
                    ),
                )
            ),
            'Organisation einer Tagung / Konferenz' => array(
                'order' => 2,
                'short' => 'organisation_konferenz',
                'de' => array(
                    'name' => 'Organisation einer Tagung / Konferenz',
                    'title' => 'Organisation von Tagungen / Konferenzen'),
                'en' => array(
                    'name' => 'Organisation of a congress / conference',
                    'title' => 'Organisation of a congress / conference')
            ),
            'Herausgeberschaft' => array(
                'order' => 3,
                'short' => 'herausgeberschaft',
                'de' => array(
                    'name' => 'Herausgeberschaft',
                    'title' => 'Herausgeberschaften'),
                'en' => array(
                    'name' => 'Editorship of a scientific journal',
                    'title' => 'Editorships scientific journals')
            ),
            'Gutachtertätigkeit für wissenschaftliche Zeitschrift' => array(
                'order' => 4,
                'short' => 'gutachter_zeitschrift',
                'de' => array(
                    'name' => 'Gutachtertätigkeit für eine wissenschaftliche Zeitschrift',
                    'title' => 'Gutachtertätigkeiten für wissenschaftliche Zeitschriften'),
                'en' => array(
                    'name' => 'Expert for reviewing a scientific journal',
                    'title' => 'Experts for reviewing scientific journals')
            ),
            'Gutachtertätigkeit für Förderorganisation' => array(
                'order' => 5,
                'short' => 'gutachter_organisation',
                'de' => array(
                    'name' => 'Gutachtertätigkeit für eine Förderorganisation',
                    'title' => 'Gutachtertätigkeiten für Förderorganisationen'),
                'en' => array(
                    'name' => 'Expert for funding organisation',
                    'title' => 'Experts for funding organisations')
            ),
            'Sonstige FAU-externe Gutachtertätigkeit' => array(
                'order' => 6,
                'short' => 'gutachter_sonstige',
                'de' => array(
                    'name' => 'Sonstige FAU-externe Gutachtertätigkeit',
                    'title' => 'Sonstige FAU-externe Gutachtertätigkeiten'),
                'en' => array(
                    'name' => 'Other expert activitiy (FAU-external)',
                    'title' => 'Other expert activities (FAU-external)')
            ),
            'DFG-Fachkollegiat/in' => array(
                'order' => 7,
                'short' => 'dfg-fachkollegiat',
                'de' => array(
                    'name' => 'DFG-Fachkollegiat/in',
                    'title' => 'DFG-Fachkollegiate'),
                'en' => array(
                    'name' => 'DFG-Subject field membership',
                    'title' => 'DFG-Subject field memberships')
            ),
            'Gremiumsmitglied Wissenschaftsrat' => array(
                'order' => 8,
                'short' => 'mitglied_wissenschaftsrat',
                'de' => array(
                    'name' => 'Gremiumsmitglied im Wissenschaftsrat',
                    'title' => 'Gremiumsmitgliedschaften im Wissenschaftsrat'),
                'en' => array(
                    'name' => 'Member of the German Science Council',
                    'title' => 'Members of the German Science Council')
            ),
            'Vortrag' => array(
                'order' => 9,
                'short' => 'vortrag',
                'de' => array(
                    'name' => 'Vortrag',
                    'title' => 'Vorträge'),
                'en' => array(
                    'name' => 'Speech / Talk',
                    'title' => 'Speeches / Talks')
            ),
            'Radio- / Fernsehbeitrag / Podcast' => array(
                'order' => 10,
                'short' => 'medien',
                'de' => array(
                    'name' => 'Radio- / Fernsehbeitrag / Podcast',
                    'title' => 'Radio- / Fernsehbeiträge / Podcasts'),
                'en' => array(
                    'name' => 'Radio, Television or Podcast',
                    'title' => 'Radio / Television Broadcasts or Podcasts')
            ),
            'Sonstige FAU-externe Aktivitäten' => array(
                'order' => 11,
                'short' => 'sonstige',
                'de' => array(
                    'name' => 'Sonstige FAU-externe Aktivität',
                    'title' => 'Sonstige FAU-externe Aktivitäten'),
                'en' => array(
                    'name' => 'Other activitiy (FAU-external)',
                    'title' => 'Other activities (FAU-external)')
            ),
        ),
        'standardizations' => array(
            'standardization_type_norm' => array(
                'order' => 1,
                'short' => 'norm',
                'de' => array(
                    'name' => 'Normung',
                    'title' => 'Normungen'),
                'en' => array(
                    'name' => 'Standard',
                    'title' => 'Standards')
            ),
            'standardization_type_standard' => array(
                'order' => 2,
                'short' => 'standardization',
                'de' => array(
                    'name' => 'Standardisierung',
                    'title' => 'Standardisierungen'),
                'en' => array(
                    'name' => 'Standardization',
                    'title' => 'Standardizations')
            ),
            'standardization_type_regulation' => array(
                'order' => 3,
                'short' => 'regulation',
                'de' => array(
                    'name' => 'Regulierung',
                    'title' => 'Regulierungen'),
                'en' => array(
                    'name' => 'Regulation',
                    'title' => 'Regulations')
            ),
        ),
        'projectroles' => array(
            'leader' => array(
                'order' => 1,
                'short' => 'leader',
                'de' => array(
                    'name' => 'Projektleiter',
                    'title' => 'Projektleitung'
                ),
                'en' => array(
                    'name' => 'Project leader',
                    'title' => 'Project Management'
                )
            ),
            'member' => array(
                'order' => 2,
                'short' => 'member',
                'de' => array(
                    'name' => 'Projektmitarbeiter',
                    'title' => 'Projektmitarbeit'
                ),
                'en' => array(
                    'name' => 'Project member',
                    'title' => 'Project Membership'
                )
            ),
        ),
    );

    public static array $publicationTitleLinksOptions=array(
    'internal link',
    'url',
    'doi',
    'cris',
    'open access link'
    );

    public static array $pubLanguages = array(
        'German',
        'English',
        'Arabic',
        'Chinese',
        'Danish',
        'Finnish',
        'French',
        'Greek',
        'Italian',
        'Japanese',
        'Latin',
        'Dutch',
        'Norwegian',
        'Polish',
        'Portuguese',
        'Russian',
        'Swedish',
        'Slovak',
        'Slovenian',
        'Spanish',
        'Czech',
        'Turkish',
        'Ukrainian'
    );
}
