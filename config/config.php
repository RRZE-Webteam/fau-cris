<?php

namespace FAU\CRIS\Config;

defined('ABSPATH') || exit;

/**
 * Fixe und nicht aenderbare Plugin-Optionen
 * @return array
 */
function getConstants()
{
    $constants = [
        'div_open' => '<div class="fau-cris">',
        'div_close' => '</div>',
        'bibtex_url' => "https://cris.fau.de/bibtex/publication/%s.bib",
        'publicweb_url' => 'https://cris.fau.de/converis/portal/',
        'person_path' => '/person/',
        'doi_url' => 'https://dx.doi.org/',
        'ws_url' => 'https://cris.fau.de/ws-cached/1.0/public/infoobject/',
        'ws_requests' => [
            'activities' => [
                'id' => 'get/Activity/%d',
                'organisation' => 'getrelated/Organisation/%d/acti_has_orga',
                'person' => 'getrelated/Person/%s/acti_has_pers',
            ],
            'awards' => [
                'id' => 'get/Award/%d',
                'organisation' => 'getautorelated/Organisation/%d/orga_3_awar_1',
                'person' => 'getrelated/Person/%s/awar_has_pers',
                'type' => 'getrelated/Award%%20Type/%d/awar_has_awat',
            ],
            'equipment' => [
                'id' => 'get/Equipment/%d',
                'organisation' => 'getrelated/Organisation/%d/equi_has_orga',
            ],
            'organisation' => [
                'id' => 'get/Organisation/%d'
            ],
            'patents' => [
                'id' => 'get/cfrespat/%d',
                'organisation' => 'getautorelated/Organisation/%d/ORGA_2_PATE_1',
                'person' => 'getautorelated/Person/%s/PERS_2_PATE_1',
            ],
            'projects' => [
                'id' => 'get/Project/%d',
                'organisation' => ['getautorelated/Organisation/%d/ORGA_2_PROJ_1',
                    'getrelated/Organisation/%d/PROJ_has_int_ORGA'],
                'person' => ['getautorelated/Person/%s/PERS_2_PROJ_1',
                    'getautorelated/Person/%s/PERS_2_PROJ_2'],
                'leader' => 'getautorelated/Person/%s/PERS_2_PROJ_1',
                'member' => 'getautorelated/Person/%s/PERS_2_PROJ_2',
                'field' => ['getrelated/Forschungsbereich/%d/fobe_has_proj',
                    'getrelated/Forschungsbereich/%d/fobe_fac_has_proj'],
                'publication' => 'getrelated/Publication/%d/PROJ_has_PUBL',
            ],
            'publications' => [
                'id' => 'get/Publication/%d',
                'organisation' => ['getautorelated/Organisation/%d/orga_2_publ_1',
                    'getrelated/Organisation/%d/publ_has_orga'],
                'person' => 'getautorelated/Person/%s/pers_2_publ_1',
                'person_notable' => 'getrelated/Person/%s/publ_has_PERS',
                'project' => 'getrelated/Project/%d/proj_has_publ',
                'field' => 'getrelated/Forschungsbereich/%d/fobe_has_top_publ',
                'field_notable' => 'getrelated/Forschungsbereich/%d/fobe_has_cur_publ',
                'field_projects' => 'getrelated/Forschungsbereich/%d/fobe_proj_publ',
                'fsp' => 'getrelated/Forschungsbereich/%d/fobe_FSP_has_publ',
                'fsp_projects' => 'getrelated/Forschungsbereich/%d/fsp_proj_publ',
                'equipment' => 'getrelated/Equipment/%d/publ_has_equi',
            ],
            'images' => [
                'publication' => 'getrelated/Publication/%d/PUBL_has_PICT',
                'field' => 'getrelated/Forschungsbereich/%d/FOBE_has_PICT',
                'project' => 'getrelated/project/%d/PROJ_has_PICT',
                'equipment' => 'getrelated/equipment/%d/equi_has_pict',
            ]
    ],
        /* Sprachen, in denen die einzelnen Typbezeichnungen aus dem Webservice kommen:
         * Publication: EN (Conference contribution)
         * Awards:      DE (Preis / Ehrung)
         * Projekte:    EN (Own Funds)
         * Patente:     DE (Prioritätsbegründende Patentanmeldung)
         * Aktivitäten: DE (Sonstige FAU-externe Aktivitäten)
         */
        'typeinfos' => [
            'publications' => [
                'Journal article' => [
                    'order' => 2,
                    'short' => 'beitrag_fachzeitschrift',
                    'short_alt' => 'zeitschriftenartikel',
                    'de' => [
                        'name' => 'Beitrag in einer Fachzeitschrift',
                        'title' => 'Beiträge in Fachzeitschriften'],
                    'en' => [
                        'name' => 'Journal article',
                        'title' => 'Journal Articles'],
                    'subtypeattribute' => 'Publication Journal Subtype',
                    'subtypes' => [
                        'Editorial' => [
                            'order' => 1,
                            'short' => 'editorial',
                            'de' => [
                                'name' => 'Editorial',
                                'title' => 'Editorials'
                            ],
                            'en' => [
                                'name' => 'Editorial',
                                'title' => 'Editorials'
                            ]
                        ],
                        'Erratum' => [
                            'order' => 2,
                            'short' => 'erratum',
                            'de' => [
                                'name' => 'Erratum',
                                'title' => 'Errata'
                            ],
                            'en' => [
                                'name' => 'Erratum',
                                'title' => 'Errata'
                            ]
                        ],
                        'Case study' => [
                            'order' => 3,
                            'short' => 'fallstudie',
                            'de' => [
                                'name' => 'Medizinische Fallstudie',
                                'title' => 'Medizinische Fallstudien'
                            ],
                            'en' => [
                                'name' => 'Medical case study',
                                'title' => 'Medical Case Studies'
                            ]
                        ],
                        'Letter' => [
                            'order' => 4,
                            'short' => 'letter',
                            'de' => [
                                'name' => 'Letter',
                                'title' => 'Letters'
                            ],
                            'en' => [
                                'name' => 'Letter',
                                'title' => 'Letters'
                            ]
                        ],
                        'Article in Journal' => [
                            'order' => 5,
                            'short' => 'originalarbeit',
                            'de' => [
                                'name' => 'Originalarbeit',
                                'title' => 'Originalarbeiten'
                            ],
                            'en' => [
                                'name' => 'Original article',
                                'title' => 'Original Articles'
                            ]
                        ],
                        'Report' => [
                            'order' => 6,
                            'short' => 'report',
                            'de' => [
                                'name' => 'Report',
                                'title' => 'Reports'
                            ],
                            'en' => [
                                'name' => 'Report',
                                'title' => 'Reports'
                            ]
                        ],
                        'Review article' => [
                            'order' => 7,
                            'short' => 'reviewartikel',
                            'de' => [
                                'name' => 'Review-Artikel',
                                'title' => 'Review-Artikel'
                            ],
                            'en' => [
                                'name' => 'Review article',
                                'title' => 'Review Articles'
                            ]
                        ],
                        'Short survey' => [
                            'order' => 8,
                            'short' => 'shortsurvey',
                            'de' => [
                                'name' => 'Short survey',
                                'title' => 'Short surveys'
                            ],
                            'en' => [
                                'name' => 'Short survey',
                                'title' => 'Short Surveys'
                            ]
                        ],
                        'Note' => [
                            'order' => 9,
                            'short' => 'note',
                            'de' => [
                                'name' => 'Note',
                                'title' => 'Notes'
                            ],
                            'en' => [
                                'name' => 'Note',
                                'title' => 'Notes'
                            ]
                        ],
                        'online publication' => [
                            'order' => 10,
                            'short' => 'onlinepublikation',
                            'de' => [
                                'name' => 'Online-Publikation',
                                'title' => 'Online-Publikationen'
                            ],
                            'en' => [
                                'name' => 'Online publication',
                                'title' => 'Online Publications'
                            ]
                        ],
                        'Entscheidungsanmerkung' => [
                            'order' => 11,
                            'short' => 'entscheidungsanmerkung',
                            'de' => [
                                'name' => 'Entscheidungsanmerkung',
                                'title' => 'Entscheidungsanmerkungen'
                            ],
                            'en' => [
                                'name' => 'Decision note',
                                'title' => 'Decision Notes'
                            ]
                        ],
                        'undefined' => [
                            'order' => 12,
                            'short' => 'andere',
                            'de' => [
                                'name' => 'Andere / O.A.',
                                'title' => 'Andere / O.A.'
                            ],
                            'en' => [
                                'name' => 'Other / na',
                                'title' => 'Other / na'
                            ]
                        ],
                    ],

                ],
                'Article in Edited Volumes' => [
                    'order' => 3,
                    'short' => 'beitrag_sammelwerk',
                    'short_alt' => 'sammelbandbeitraege',
                    'de' => [
                        'name' => 'Beitrag in einem Sammelwerk',
                        'title' => 'Beiträge in Sammelwerken'],
                    'en' => [
                        'name' => 'Book Contribution',
                        'title' => 'Book Contributions'],
                    'subtypeattribute' => 'PublicationTypeEditedVolumes',
                    'subtypes' => [
                        'article' => [
                            'order' => 1,
                            'short' => 'originalarbeit',
                            'de' => [
                                'name' => 'Originalarbeit',
                                'title' => 'Originalarbeiten'
                            ],
                            'en' => [
                                'name' => 'Original article',
                                'title' => 'Original Articles'
                            ]
                        ],
                        'Case study' => [
                            'order' => 2,
                            'short' => 'fallstudie',
                            'de' => [
                                'name' => 'Fallstudie',
                                'title' => 'Fallstudien'
                            ],
                            'en' => [
                                'name' => 'Case study',
                                'title' => 'Case studies'
                            ]
                        ],
                        'Review Article' => [
                            'order' => 3,
                            'short' => 'reviewartikel',
                            'de' => [
                                'name' => 'Review-Artikel',
                                'title' => 'Review-Artikel'
                            ],
                            'en' => [
                                'name' => 'Review article',
                                'title' => 'Review Articles'
                            ]
                        ],
                        'Buchkapitel' => [
                            'order' => 4,
                            'short' => 'buchkapitel',
                            'de' => [
                                'name' => 'Buchkapitel',
                                'title' => 'Buchkapitel'
                            ],
                            'en' => [
                                'name' => 'Book chapter',
                                'title' => 'Book Chapters'
                            ]
                        ],
                        'Ausstellungskatalogsbeitrag' => [
                            'order' => 5,
                            'short' => 'beitrag_ausstellungskatalog',
                            'de' => [
                                'name' => 'Beitrag in Ausstellungskatalog',
                                'title' => 'Beitraege in Ausstellungskatalogen'
                            ],
                            'en' => [
                                'name' => 'Exhibition catalogue contribution',
                                'title' => 'Exhibition Catalogue Contributions'
                            ]
                        ],
                        'Beitrag in Festschrift' => [
                            'order' => 6,
                            'short' => 'beitrag_festschrift',
                            'de' => [
                                'name' => 'Beitrag in einer Festschrift / Gedenkschrift',
                                'title' => 'Beitraege in Festschriften / Gedenkschriften'
                            ],
                            'en' => [
                                'name' => 'Article in a Festschrift / memorial volume',
                                'title' => 'Articles in Festschriften / Memorial Volumes'
                            ]
                        ],
                        'Beitrag in Handbuch' => [
                            'order' => 7,
                            'short' => 'beitrag_handbuch',
                            'de' => [
                                'name' => 'Beitrag in einem Handbuch',
                                'title' => 'Beitraege in Handbüchern'
                            ],
                            'en' => [
                                'name' => 'Article in a Manual',
                                'title' => 'Articles in Manuals'
                            ]
                        ],
                        'Aufsatz' => [
                            'order' => 8,
                            'short' => 'aufsatz',
                            'de' => [
                                'name' => 'Aufsatz',
                                'title' => 'Aufsätze'
                            ],
                            'en' => [
                                'name' => 'Essay',
                                'title' => 'Essays'
                            ]
                        ],
                        'Other' => [
                            'order' => 9,
                            'short' => 'andere',
                            'de' => [
                                'name' => 'Anderer',
                                'title' => 'Andere'
                            ],
                            'en' => [
                                'name' => 'Other',
                                'title' => 'Other'
                            ]
                        ],
                        'online pub' => [
                            'order' => 10,
                            'short' => 'onlinepublikation',
                            'de' => [
                                'name' => 'Online-Publikation',
                                'title' => 'Online-Publikationen'
                            ],
                            'en' => [
                                'name' => 'Online publication',
                                'title' => 'Online Publications'
                            ]
                        ],
                        'Entscheidungsanmerkung' => [
                            'order' => 11,
                            'short' => 'entscheidungsanmerkung',
                            'de' => [
                                'name' => 'Entscheidungsanmerkung',
                                'title' => 'Entscheidungsanmerkungen'
                            ],
                            'en' => [
                                'name' => 'Decision note',
                                'title' => 'Decision Notes'
                            ]
                        ],
                        'undefined' => [
                            'order' => 12,
                            'short' => 'andere',
                            'de' => [
                                'name' => 'Andere / O.A.',
                                'title' => 'Andere / O.A.'
                            ],
                            'en' => [
                                'name' => 'Other / na',
                                'title' => 'Other / na'
                            ]
                        ],
                    ],
                ],
                'Book' => [
                    'order' => 1,
                    'short' => 'buch',
                    'short_alt' => 'buecher',
                    'de' => [
                        'name' => 'Buch',
                        'title' => 'Bücher'],
                    'en' => [
                        'name' => 'Authored book',
                        'title' => 'Authored Books'],
                    'subtypeattribute' => 'Publication Book Subtype',
                    'subtypes' => [
                        'Monographie' => [
                            'order' => 1,
                            'short' => 'mongraphien',
                            'short_alt' => 'mongraphie',
                            'de' => [
                                'name' => 'Monographie',
                                'title' => 'Monographien'
                            ],
                            'en' => [
                                'name' => 'Authored book',
                                'title' => 'Authored Books'
                            ]
                        ],
                        'Band aus einer Reihe' => [
                            'order' => 2,
                            'short' => 'band_reihe',
                            'short_alt' => 'bandausreihe',
                            'de' => [
                                'name' => 'Band aus einer Reihe',
                                'title' => 'Bände aus einer Reihe'
                            ],
                            'en' => [
                                'name' => 'Volume of book series',
                                'title' => 'Volumes of Book Series'
                            ]
                        ],
                        'Manual' => [
                            'order' => 3,
                            'short' => 'handbuch',
                            'short_alt' => 'handbuecher',
                            'de' => [
                                'name' => 'Handbuch',
                                'title' => 'Handbücher'
                            ],
                            'en' => [
                                'name' => 'Manual',
                                'title' => 'Manuals'
                            ]
                        ],
                        'Lehrbuch' => [
                            'order' => 4,
                            'short' => 'lehrbuch',
                            'short_alt' => 'lehrbuecher',
                            'de' => [
                                'name' => 'Lehrbuch',
                                'title' => 'Lehrbücher'
                            ],
                            'en' => [
                                'name' => 'Textbook',
                                'title' => 'Textbooks'
                            ]
                        ],
                        'undefined' => [
                            'order' => 5,
                            'short' => 'andere',
                            'de' => [
                                'name' => 'Anderer / O.A.',
                                'title' => 'Andere / O.A.'
                            ],
                            'en' => [
                                'name' => 'Other / na',
                                'title' => 'Other / na'
                            ]
                        ],
                    ],

                ],
                'Translation' => [
                    'order' => 6,
                    'short' => 'uebersetzung',
                    'short_alt' => 'uebersetzungen',
                    'de' => [
                        'name' => 'Übersetzung',
                        'title' => 'Übersetzungen'],
                    'en' => [
                        'name' => 'Translation',
                        'title' => 'Translations']
                ],
                'Thesis' => [
                    'order' => 7,
                    'short' => 'abschlussarbeit',
                    'short_alt' => 'abschlussarbeiten',
                    'de' => [
                        'name' => 'Abschlussarbeit',
                        'title' => 'Abschlussarbeiten'],
                    'en' => [
                        'name' => 'Thesis',
                        'title' => 'Thesis'],
                    'subtypeattribute' => 'Publication Thesis Subtype',
                    'subtypes' => [
                        'Habilitationsschrift' => [
                            'order' => 1,
                            'short' => 'habilitation',
                            'de' => [
                                'name' => 'Habilitationsschrift',
                                'title' => 'Habilitationsschriften'
                            ],
                            'en' => [
                                'name' => 'Habilitation',
                                'title' => 'Habilitations'
                            ]
                        ],
                        'Dissertation' => [
                            'order' => 2,
                            'short' => 'dissertation',
                            'de' => [
                                'name' => 'Dissertation',
                                'title' => 'Dissertationen'
                            ],
                            'en' => [
                                'name' => 'Dissertation',
                                'title' => 'Dissertations'
                            ]
                        ],
                        'Diplomarbeit' => [
                            'order' => 3,
                            'short' => 'diplomarbeit',
                            'de' => [
                                'name' => 'Diplomarbeit',
                                'title' => 'Diplomarbeiten'
                            ],
                            'en' => [
                                'name' => 'Diploma thesis',
                                'title' => 'Diploma Theses'
                            ]
                        ],
                        'Magisterarbeit' => [
                            'order' => 4,
                            'short' => 'magisterarbeit',
                            'de' => [
                                'name' => 'Magisterarbeit',
                                'title' => 'Magisterarbeiten'
                            ],
                            'en' => [
                                'name' => 'Magister thesis',
                                'title' => 'Magister Theses'
                            ]
                        ],
                        'Zulassungsarbeit' => [
                            'order' => 5,
                            'short' => 'zulassungsarbeit',
                            'de' => [
                                'name' => 'Zulassungsarbeit',
                                'title' => 'Zulassungsarbeiten'
                            ],
                            'en' => [
                                'name' => 'Degree thesis',
                                'title' => 'Degree Theses'
                            ]
                        ],
                        'Masterarbeit' => [
                            'order' => 6,
                            'short' => 'masterarbeit',
                            'de' => [
                                'name' => 'Masterarbeit',
                                'title' => 'Masterarbeiten'
                            ],
                            'en' => [
                                'name' => 'Master thesis',
                                'title' => 'Master Theses'
                            ]
                        ],
                        'Bachelorarbeit' => [
                            'order' => 7,
                            'short' => 'bachelorarbeit',
                            'de' => [
                                'name' => 'Bachelorarbeit',
                                'title' => 'Bachelorarbeiten'
                            ],
                            'en' => [
                                'name' => 'Bachelor thesis',
                                'title' => 'Bachelor Theses'
                            ]
                        ],
                        'Studienarbeit' => [
                            'order' => 8,
                            'short' => 'studienarbeit',
                            'de' => [
                                'name' => 'Studienarbeit (Vordiplom]',
                                'title' => 'Studienarbeiten (Vordiplom]'
                            ],
                            'en' => [
                                'name' => 'Mid-study thesis',
                                'title' => 'Mid-study Theses'
                            ]
                        ],
                        'undefined' => [
                            'order' => 9,
                            'short' => 'andere',
                            'de' => [
                                'name' => 'Anderer / O.A.',
                                'title' => 'Andere / O.A.'
                            ],
                            'en' => [
                                'name' => 'Other / na',
                                'title' => 'Other / na'
                            ]
                        ],
                    ],

                ],
                'Editorial' => [
                    'order' => 4,
                    'short' => 'herausgegebener_band',
                    'short_alt' => 'herausgeberschaften',
                    'de' => [
                        'name' => 'Herausgegebener Band',
                        'title' => 'Herausgegebene Bände'],
                    'en' => [
                        'name' => 'Edited Volume',
                        'title' => 'Edited Volumes'],
                    'subtypeattribute' => 'Publication Editorship Subtype',
                    'subtypes' => [
                        'Buch' => [
                            'order' => 1,
                            'short' => 'buch',
                            'de' => [
                                'name' => 'Buch',
                                'title' => 'Bücher'
                            ],
                            'en' => [
                                'name' => 'Book',
                                'title' => 'Books'
                            ]
                        ],
                        'Festschrift' => [
                            'order' => 2,
                            'short' => 'festschrift',
                            'de' => [
                                'name' => 'Festschrift',
                                'title' => 'Festschriften'
                            ],
                            'en' => [
                                'name' => 'Memorial Volume',
                                'title' => 'Memorial Volumes'
                            ]
                        ],
                        'Ausstellungskatalog' => [
                            'order' => 3,
                            'short' => 'ausstellungskatalog',
                            'de' => [
                                'name' => 'Ausstellungskatalog',
                                'title' => 'Ausstellungskataloge'
                            ],
                            'en' => [
                                'name' => 'Exhibition catalogue',
                                'title' => 'Exhibition Catalogues'
                            ]
                        ],
                        'Quellenedition' => [
                            'order' => 4,
                            'short' => 'quellenedition',
                            'de' => [
                                'name' => 'Quellenedition',
                                'title' => 'Quelleneditionen'
                            ],
                            'en' => [
                                'name' => 'Source edition',
                                'title' => 'Source Editions'
                            ]
                        ],
                        'specialissue' => [
                            'order' => 5,
                            'short' => 'themenheft_zeitschrift',
                            'de' => [
                                'name' => 'Themenheft einer Zeitschrift',
                                'title' => 'Themenhefte von Zeitschriften'
                            ],
                            'en' => [
                                'name' => 'Special issue of a journal',
                                'title' => 'Special Issues of Journals'
                            ]
                        ],
                        'undefined' => [
                            'order' => 6,
                            'short' => 'andere',
                            'de' => [
                                'name' => 'Anderer / O.A.',
                                'title' => 'Andere / O.A.'
                            ],
                            'en' => [
                                'name' => 'Other / na',
                                'title' => 'Other / na'
                            ]
                        ],
                    ],

                ],
                'Conference contribution' => [
                    'order' => 5,
                    'short' => 'beitrag_tagung',
                    'short_alt' => 'konferenzbeitraege',
                    'de' => [
                        'name' => 'Beitrag bei einer Tagung',
                        'title' => 'Beiträge bei Tagungen'],
                    'en' => [
                        'name' => 'Conference contribution',
                        'title' => 'Conference Contributions'],
                    'subtypeattribute' => 'Publication Conference Subtype',
                    'subtypes' => [
                        'Journal Article' => [
                            'order' => 1,
                            'short' => 'originalarbeit',
                            'de' => [
                                'name' => 'Originalarbeit',
                                'title' => 'Originalarbeiten'
                            ],
                            'en' => [
                                'name' => 'Original article',
                                'title' => 'Original Articles'
                            ]
                        ],
                        'Konferenzschrift' => [
                            'order' => 2,
                            'short' => 'konferenzbeitrag',
                            'de' => [
                                'name' => 'Konferenzbeitrag',
                                'title' => 'Konferenzbeiträge'
                            ],
                            'en' => [
                                'name' => 'Conference contribution',
                                'title' => 'Conference Contributions'
                            ]
                        ],
                        'Abstract zum Vortrag' => [
                            'order' => 3,
                            'short' => 'abstract_vortrag',
                            'de' => [
                                'name' => 'Abstract zum Vortrag',
                                'title' => 'Abstracts zu Vorträgen'
                            ],
                            'en' => [
                                'name' => 'Abstract of lecture',
                                'title' => 'Abstracts of Lectures'
                            ]
                        ],
                        'Abstract zum Poster' => [
                            'order' => 4,
                            'short' => 'abstract_poster',
                            'de' => [
                                'name' => 'Abstract zum Poster',
                                'title' => 'Abstracts zu Postern'
                            ],
                            'en' => [
                                'name' => 'Abstract of a poster',
                                'title' => 'Abstracts of Posters'
                            ]
                        ],
                        'undefined' => [
                            'order' => 5,
                            'short' => 'andere',
                            'de' => [
                                'name' => 'Anderer / O.A.',
                                'title' => 'Andere / O.A.'
                            ],
                            'en' => [
                                'name' => 'Other / na',
                                'title' => 'Other / na'
                            ]
                        ],
                    ],
                ],
                'Other' => [
                    'order' => 8,
                    'short' => 'andere',
                    'de' => [
                        'name' => 'Sonstige',
                        'title' => 'Sonstige'],
                    'en' => [
                        'name' => 'Miscellaneous',
                        'title' => 'Miscellaneous'],
                    'subtypeattribute' => 'Type other subtype',
                    'subtypes' => [
                        'Rezension' => [
                            'order' => 1,
                            'short' => 'rezension',
                            'de' => [
                                'name' => 'Rezension / Buchbesprechung',
                                'title' => 'Rezensionen / Buchbesprechungen'
                            ],
                            'en' => [
                                'name' => 'Recension / Book review',
                                'title' => 'Recensions / Book Reviews'
                            ]
                        ],
                        'Lexikonbeitrag' => [
                            'order' => 2,
                            'short' => 'lexikonbeitrag',
                            'de' => [
                                'name' => 'Lexikonbeitrag',
                                'title' => 'Lexikonbeiträge'
                            ],
                            'en' => [
                                'name' => 'Dictionary / Encyclopedia entry',
                                'title' => 'Dictionary / Encyclopedia Entries'
                            ]
                        ],
                        'Zeitungsartikel' => [
                            'order' => 3,
                            'short' => 'zeitungsartikel',
                            'de' => [
                                'name' => 'Zeitungsartikel',
                                'title' => 'Zeitungsartikel'
                            ],
                            'en' => [
                                'name' => 'Newspaper article',
                                'title' => 'Newspaper Articles'
                            ]
                        ],
                        'Working Paper' => [
                            'order' => 4,
                            'short' => 'workingpaper',
                            'de' => [
                                'name' => 'Diskussionspapier / Working Paper',
                                'title' => 'Diskussionspapiere / Working Papers'
                            ],
                            'en' => [
                                'name' => 'Working paper',
                                'title' => 'Working Papers'
                            ]
                        ],
                        'online publication' => [
                            'order' => 5,
                            'short' => 'onlinepublikation',
                            'de' => [
                                'name' => 'Online-Publikation',
                                'title' => 'Online-Publikationen'
                            ],
                            'en' => [
                                'name' => 'Online publication',
                                'title' => 'Online Publications'
                            ]
                        ],
                        'Conference report' => [
                            'order' => 6,
                            'short' => 'konferenzbericht',
                            'de' => [
                                'name' => 'Konferenzbericht',
                                'title' => 'Konferenzberichte'
                            ],
                            'en' => [
                                'name' => 'Conference report',
                                'title' => 'Conference Reports'
                            ]
                        ],
                        'Techreport' => [
                            'order' => 7,
                            'short' => 'techreport',
                            'de' => [
                                'name' => 'Technical Report',
                                'title' => 'Technical Reports'
                            ],
                            'en' => [
                                'name' => 'Technical report',
                                'title' => 'Technical Reports'
                            ]
                        ],
                        'Gutachten' => [
                            'order' => 8,
                            'short' => 'gutachten',
                            'de' => [
                                'name' => 'Gutachten',
                                'title' => 'Gutachten'
                            ],
                            'en' => [
                                'name' => 'Expertise',
                                'title' => 'Expertises'
                            ]
                        ],
                        'anderer' => [
                            'order' => 9,
                            'short' => 'andere',
                            'de' => [
                                'name' => 'Anderer',
                                'title' => 'Andere'
                            ],
                            'en' => [
                                'name' => 'Other',
                                'title' => 'Other'
                            ]
                        ],
                    ],

                ],
                'Unpublished' => [
                    'order' => 9,
                    'short' => 'unveroeffentlicht',
                    'de' => [
                        'name' => 'Unveröffentlichte Publikation / Preprint',
                        'title' => 'Unveröffentlichte Publikationen / Preprint'],
                    'en' => [
                        'name' => 'Unpublished Publication',
                        'title' => 'Unpublished Publications'],
                    'subtypeattribute' => 'FuturePublicationType',
                    'subtypes' => [
                        'Journal Article' => [
                            'order' => 1,
                            'short' => 'beitrag_fachzeitschrift',
                            'de' => [
                                'name' => 'Beitrag in einer Fachzeitschrift',
                                'title' => 'Beiträge in Fachzeitschriften'
                            ],
                            'en' => [
                                'name' => 'Journal Article',
                                'title' => 'Journal Articles'
                            ]
                        ],
                        'Article in Edited Volumes' => [
                            'order' => 2,
                            'short' => 'beitrag_sammelwerk',
                            'de' => [
                                'name' => 'Beitrag in einem Sammelwerk',
                                'title' => 'Beiträge in Sammelwerken'
                            ],
                            'en' => [
                                'name' => 'Article in Edited Volume',
                                'title' => 'Articles in Edited Volumes'
                            ]
                        ],
                        'Book' => [
                            'order' => 3,
                            'short' => 'buch',
                            'de' => [
                                'name' => 'Buch',
                                'title' => 'Bücher'
                            ],
                            'en' => [
                                'name' => 'Book',
                                'title' => 'Books'
                            ]
                        ],
                        'Translation' => [
                            'order' => 4,
                            'short' => 'uebersetzung',
                            'de' => [
                                'name' => 'Übersetzung',
                                'title' => 'Übersetzung'
                            ],
                            'en' => [
                                'name' => 'Translation',
                                'title' => 'Translations'
                            ]
                        ],
                        'Thesis' => [
                            'order' => 5,
                            'short' => 'abschlussarbeit',
                            'de' => [
                                'name' => 'Abschlussarbeit',
                                'title' => 'abschlussarbeiten'
                            ],
                            'en' => [
                                'name' => 'Thesis',
                                'title' => 'Thesis'
                            ]
                        ],
                        'Edited Volumes' => [
                            'order' => 6,
                            'short' => 'herausgegebener_band',
                            'de' => [
                                'name' => 'Herausgegebener Band',
                                'title' => 'Herausgegebene Bände'
                            ],
                            'en' => [
                                'name' => 'Edited Volume',
                                'title' => 'Edited Volumes'
                            ]
                        ],
                        'Conference contribution' => [
                            'order' => 7,
                            'short' => 'beitrag_tagung',
                            'de' => [
                                'name' => 'Beitrag bei einer Tagung',
                                'title' => 'Beiträge bei Tagungen'
                            ],
                            'en' => [
                                'name' => 'Conference contribution',
                                'title' => 'Conference Contributions'
                            ]
                        ],
                        'Other' => [
                            'order' => 8,
                            'short' => 'andere',
                            'de' => [
                                'name' => 'Anderer',
                                'title' => 'Andere'
                            ],
                            'en' => [
                                'name' => 'Other',
                                'title' => 'Other'
                            ]
                        ],
                    ]
                ],
            ],
            'awards' => [
                'Preis / Ehrung' => [
                    'order' => 1,
                    'short' => 'preis',
                    'short_alt' => 'preise',
                    'de' => [
                        'name' => 'Preis / Ehrung',
                        'title' => 'Preise / Ehrungen'],
                    'en' => [
                        'name' => 'Award / Honour',
                        'title' => 'Awards / Honours']
                ],
                'Stipendium / Grant' => [
                    'order' => 2,
                    'short' => 'stipendium',
                    'short_alt' => 'stipendien',
                    'de' => [
                        'name' => 'Stipendium / Grant',
                        'title' => 'Stipendien / Grants'],
                    'en' => [
                        'name' => 'Scholarship / Grant',
                        'title' => 'Scholarships / Grants']
                ],
                'Akademie-Mitgliedschaft' => [
                    'order' => 3,
                    'short' => 'akademiemitgliedschaft',
                    'short_alt' => 'mitgliedschaften',
                    'de' => [
                        'name' => 'Akademie-Mitgliedschaft',
                        'title' => 'Akademie-Mitgliedschaften'],
                    'en' => [
                        'name' => 'Academy membership',
                        'title' => 'Academy Memberships']
                ],
                'Kleiner Preis' => [
                    'order' => 4,
                    'short' => 'andere',
                    'de' => [
                        'name' => 'Weiterer Preis / Auszeichnung',
                        'title' => 'Weiterere Preise / Auszeichnungen'],
                    'en' => [
                        'name' => 'Other Award',
                        'title' => 'Other Awards']
                ],
                '' => [
                    'order' => 5,
                    'short' => 'keintyp',
                    'de' => [
                        'name' => 'Weiterer Preis / Auszeichnung',
                        'title' => 'Weiterere Preise / Auszeichnungen'],
                    'en' => [
                        'name' => 'Other Award',
                        'title' => 'Other Awards']
                ],
            ],
            'projects' => [
                'Third Party Funds Single' => [
                    'order' => 1,
                    'short' => 'einzelfoerderung',
                    'de' => [
                        'name' => 'Drittmittelfinanzierte Einzelförderung',
                        'title' => 'Drittmittelfinanzierte Einzelförderungen'],
                    'en' => [
                        'name' => 'Third Party Funds Single',
                        'title' => 'Third Party Funds Single']
                ],
                'Third Party Funds Group - Sub project' => [
                    'order' => 2,
                    'short' => 'teilprojekt',
                    'de' => [
                        'name' => 'Drittmittelfinanzierte Gruppenförderung &ndash; Teilprojekt',
                        'title' => 'Drittmittelfinanzierte Gruppenförderungen &ndash; Teilprojekte'],
                    'en' => [
                        'name' => 'Third Party Funds Group &ndash; Sub project',
                        'title' => 'Third Party Funds Group &ndash; Sub projects']
                ],
                'Third Party Funds Group - Overall project' => [
                    'order' => 3,
                    'short' => 'gesamtprojekt',
                    'de' => [
                        'name' => 'Drittmittelfinanzierte Gruppenförderung &ndash; Gesamtprojekt',
                        'title' => 'Drittmittelfinanzierte Gruppenförderungen &ndash; Gesamtprojekte'],
                    'en' => [
                        'name' => 'Third Party Funds Group &ndash; Overall project',
                        'title' => 'Third Party Funds Group &ndash; Overall projects']
                ],
                'Own and Third Party Funds Doctoral Programm - Overall project' => [
                    'order' => 4,
                    'short' => 'graduiertenkolleg',
                    'de' => [
                        'name' => 'Promotionsprogramm / Graduiertenkolleg',
                        'title' => 'Promotionsprogramme / Graduiertenkollegs'],
                    'en' => [
                        'name' => 'Own and Third Party Funds Doctoral Programm &ndash; Overall project',
                        'title' => 'Own and Third Party Funds Doctoral Programms &ndash; Overall projects']
                ],
                'Own Funds' => [
                    'order' => 5,
                    'short' => 'eigenmittel',
                    'de' => [
                        'name' => 'Projekt aus Eigenmitteln',
                        'title' => 'Projekte aus Eigenmitteln'],
                    'en' => [
                        'name' => 'Own Funds',
                        'title' => 'Own Funds']
                ],
                'Fremdprojekt' => [
                    'order' => 5,
                    'short' => 'fremdprojekt',
                    'de' => [
                        'name' => 'FAU-externes Projekt',
                        'title' => 'FAU-externe Projekte'],
                    'en' => [
                        'name' => 'Non-FAU Project',
                        'title' => 'Non-FAU Projects']
                ],
            ],
            'patents' => [
                'Prioritätsbegründende Patentanmeldung' => [
                    'order' => 1,
                    'short' => 'patentanmeldung',
                    'de' => [
                        'name' => 'Prioritätsbegründende Patentanmeldung',
                        'title' => 'Prioritätsbegründende Patentanmeldungen'],
                    'en' => [
                        'name' => 'Priority Patent Application',
                        'title' => 'Priority Patent Applications']
                ],
                'Gebrauchsmuster' => [
                    'order' => 2,
                    'short' => 'gebrauchsmuster',
                    'de' => [
                        'name' => 'Gebrauchsmuster',
                        'title' => 'Gebrauchsmuster'],
                    'en' => [
                        'name' => 'Utility Model',
                        'title' => 'Utility Models']
                ],
                'Schutzrecht' => [
                    'order' => 3,
                    'short' => 'schutzrecht',
                    'de' => [
                        'name' => 'Schutzrecht',
                        'title' => 'Schutzrechte'],
                    'en' => [
                        'name' => 'Property Right',
                        'title' => 'Property Rights']
                ],
                'Nachanmeldung' => [
                    'order' => 4,
                    'short' => 'nachanmeldung',
                    'de' => [
                        'name' => 'Nachanmeldung',
                        'title' => 'Nachanmeldungen'],
                    'en' => [
                        'name' => 'Secondary Application',
                        'title' => 'Secondary Applications']
                ],
                'Nationalisierung' => [
                    'order' => 5,
                    'short' => 'nationalisierung',
                    'de' => [
                        'name' => 'Nationalisierung',
                        'title' => 'Nationalisierungen'],
                    'en' => [
                        'name' => 'Nationalisation',
                        'title' => 'Nationalisations']
                ],
                'Validierung' => [
                    'order' => 6,
                    'short' => 'validierung',
                    'de' => [
                        'name' => 'Validierung',
                        'title' => 'Validierungen'],
                    'en' => [
                        'name' => 'Validation',
                        'title' => 'Validations']
                ],
            ],
            'activities' => [
                'FAU-interne Gremienmitgliedschaften / Funktionen' => [
                    'order' => 1,
                    'short' => 'fau-gremienmitgliedschaft',
                    'de' => [
                        'name' => 'FAU-interne Gremienmitgliedschaft / Funktion',
                        'title' => 'FAU-interne Gremienmitgliedschaften / Funktionen'],
                    'en' => [
                        'name' => 'Membership in representative bodies / functions (FAU-internal)',
                        'title' => 'Memberships in representative bodies / functions (FAU-internal)'],
                    'subtypes' => [
                        'Women\'s representative of FAU' => [
                            'de' => ['name' => 'Frauenbeautragte/r der FAU'],
                            'en' => ['name' => 'Women\'s representative of FAU'],
                        ],
                        'Senat (Membership)' => [
                            'de' => ['name' => 'Senat (Mitglied)'],
                            'en' => ['name' => 'Senat (Membership)'],
                        ],
                        'Special Representative' => [
                            'de' => ['name' => 'Sonderbeauftragte/r'],
                            'en' => ['name' => 'Special Representative'],
                        ],
                        'Speaker of the Faculty' => [
                            'de' => ['name' => 'Fachbereichsleitung'],
                            'en' => ['name' => 'Speaker of the Faculty'],
                        ],
                        'Library Commettee (Presidency)' => [
                            'de' => ['name' => 'Bibliotheksausschuss (Vorsitz)'],
                            'en' => ['name' => 'Library Commettee (Presidency)'],
                        ],
                        'Library Commettee (Membership)' => [
                            'de' => ['name' => 'Bibliotheksausschuss (Mitglied)'],
                            'en' => ['name' => 'Library Commettee (Membership)'],
                        ],
                        'Open-Access-Representative' => [
                            'de' => ['name' => 'Open-Access-Beauftragte/r'],
                            'en' => ['name' => 'Open-Access-Representative'],
                        ],
                        'Faculty Management Board (Presidency)' => [
                            'de' => ['name' => 'Fakultätsvorstand (Vorsitz)'],
                            'en' => ['name' => 'Faculty Management Board (Presidency)'],
                        ],
                        'Fakultätsvorstand (Mitglied)' => [
                            'de' => ['name' => 'Fakultätsvorstand (Mitglied)'],
                            'en' => ['name' => 'Faculty Management Board (Membership)'],
                        ],
                        'Department\'s board of directors (Membership)' => [
                            'de' => ['name' => 'Departmentleitung (Mitglied)'],
                            'en' => ['name' => 'Department\'s board of directors (Membership)'],
                        ],
                        'Commission for Ethical Issues (Presidency)' => [
                            'de' => ['name' => 'Ethikkommission (Vorsitz)'],
                            'en' => ['name' => 'Commission for Ethical Issues (Presidency)'],
                        ],
                        'Commission for Ethical Issues (Membership)' => [
                            'de' => ['name' => 'Ethikkommission (Mitglied)'],
                            'en' => ['name' => 'Commission for Ethical Issues (Membership)'],
                        ],
                        'Commission for Internationalisation (Presidency)' => [
                            'de' => ['name' => 'Kommission Internationalisierung (Vorsitz)'],
                            'en' => ['name' => 'Commission for Internationalisation (Presidency)'],
                        ],
                        'Commission for Internationalisation (Membership)' => [
                            'de' => ['name' => 'Kommission Internationalisierung (Mitglied)'],
                            'en' => ['name' => 'Commission for Internationalisation (Membership)'],
                        ],
                        'Commission on Study & Teaching (Presidency)' => [
                            'de' => ['name' => 'Kommission Lehre / Studium (Vorsitz)'],
                            'en' => ['name' => 'Commission on Study & Teaching (Presidency)'],
                        ],
                        'Commission on Study & Teaching (Membership)' => [
                            'de' => ['name' => 'Kommission Lehre / Studium (Mitglied)'],
                            'en' => ['name' => 'Commission on Study & Teaching (Membership)'],
                        ],
                        'Commission on Computer Systems (Membership)' => [
                            'de' => ['name' => 'Kommission Rechenanlagen (Mitglied)'],
                            'en' => ['name' => 'Commission on Computer Systems (Membership)'],
                        ],
                        'Commission on Erratic Behaviour in Science (Presidency)' => [
                            'de' => ['name' => 'Kommission wissenschaftliches Fehlverhalten (Vorsitz)'],
                            'en' => ['name' => 'Commission on Erratic Behaviour in Science (Presidency)'],
                        ],
                        'Commission on Erratic Behaviour in Science (Membership)' => [
                            'de' => ['name' => 'Kommission wissenschaftliches Fehlverhalten (Mitglied)'],
                            'en' => ['name' => 'Commission on Erratic Behaviour in Science (Membership)'],
                        ],
                        'Commission on Conflict Settlement (Presidency)' => [
                            'de' => ['name' => 'Konfliktkommision (Vorsitz)'],
                            'en' => ['name' => 'Commission on Conflict Settlement (Presidency)'],
                        ],
                        'Commission on Conflict Settlement (Membership)' => [
                            'de' => ['name' => 'Konfliktkommision (Mitglied)'],
                            'en' => ['name' => 'Commission on Conflict Settlement (Membership)'],
                        ],
                        'Board of Examiners of the Faculty (Presidency)' => [
                            'de' => ['name' => 'Prüfungsausschuss der Fakultät (Vorsitz)'],
                            'en' => ['name' => 'Board of Examiners of the Faculty (Presidency)'],
                        ],
                        'Board of Examiners of the Faculty (Membership)' => [
                            'de' => ['name' => 'Prüfungsausschuss der Fakultät (Mitglied)'],
                            'en' => ['name' => 'Board of Examiners of the Faculty (Membership)'],
                        ],
                        'Commission for Equal Opportunities (Membership)' => [
                            'de' => ['name' => 'Komission Chancengleichheit (Mitgliedschaft)'],
                            'en' => ['name' => 'Commission for Equal Opportunities (Membership)'],
                        ],
                        'Board of the Faculty (Membership)' => [
                            'de' => ['name' => 'Fakultätsrat (Mitglied)'],
                            'en' => ['name' => 'Board of the Faculty (Membership)'],
                        ],
                        'Board of Research / Young academics (Membership)' => [
                            'de' => ['name' => 'Kommission Forschung / wissenschaftl. Nachwuchs (Mitglied)'],
                            'en' => ['name' => 'Board of Research / Young academics (Membership)'],
                        ],
                        'Board of Research / Young academics (Presidency)' => [
                            'de' => ['name' => 'Kommission Forschung / wissenschaftl. Nachwuchs (Vorsitz)'],
                            'en' => ['name' => 'Board of Research / Young academics (Presidency)'],
                        ],
                        'Convention on Researchers (Presidency)' => [
                            'de' => ['name' => 'Konvent wissenschaftliche Mitarbeiter (Vorsitz)'],
                            'en' => ['name' => 'Convention on Researchers (Presidency)'],
                        ],
                        'Convention on Researchers (Managing Board)' => [
                            'de' => ['name' => 'Konvent wissenschaftliche Mitarbeiter (Leitungsgremium)'],
                            'en' => ['name' => 'Convention on Researchers (Managing Board)'],
                        ],
                        'Senat (Presidency)' => [
                            'de' => ['name' => 'Senat (Vorsitz)'],
                            'en' => ['name' => 'Senat (Presidency)'],
                        ],
                        'Management Board / Speaker of a FAU-Central Institute\')' => [
                            'de' => ['name' => 'Kollegiale Leitung / Sprecher/in eines FAU-Zentralinstituts'],
                            'en' => ['name' => 'Management Board / Speaker of a FAU-Central Institute'],
                        ],
                        'Speaker of SFB, GRK or elite program' => [
                            'de' => ['name' => 'Sprecher für SFB, GRK oder Elitestudiengang'],
                            'en' => ['name' => 'Speaker of SFB, GRK or elite program'],
                        ],
                        'Central Board on Disposition of the Tuition Fees' => [
                            'de' => ['name' => 'Zentrales Gremium Verwendung Studienbeiträge'],
                            'en' => ['name' => 'Central Board on Disposition of the Tuition Fees'],
                        ],
                        'Decentralised Board on Disposition of the Tuition Fees' => [
                            'de' => ['name' => 'Dezentrales Gremium zur Verwendung der Studienbeiträge'],
                            'en' => ['name' => 'Decentralised Board on Disposition of the Tuition Fees'],
                        ],
                        'Interdisziplinary Center (Speaker)' => [
                            'de' => ['name' => 'Interdisziplinäres Zentrum (Sprecher/in)'],
                            'en' => ['name' => 'Interdisziplinary Center (Speaker)'],
                        ],
                        'Interdisziplinary Center (Member)' => [
                            'de' => ['name' => 'Interdisziplinäres Zentrum (Mitglied)'],
                            'en' => ['name' => 'Interdisziplinary Center (Member)'],
                        ],
                        'Appointment Committee (Member)' => [
                            'de' => ['name' => 'Berufungskommission (Mitglied)'],
                            'en' => ['name' => 'Appointment Committee (Member)'],
                        ],
                        'Appointment Committee (Presidency)' => [
                            'de' => ['name' => 'Berufungskommission (Vorsitz)'],
                            'en' => ['name' => 'Appointment Committee (Presidency)'],
                        ],
                        'Dean' => [
                            'de' => ['name' => 'Dekan'],
                            'en' => ['name' => 'Dean'],
                        ],
                        'Vice Dean' => [
                            'de' => ['name' => 'Prodekan'],
                            'en' => ['name' => 'Vice Dean'],
                        ],
                        'Interdisziplinary Center (Board member)' => [
                            'de' => ['name' => 'Interdisziplinäres Zentrum (Vorstandsmitglied)'],
                            'en' => ['name' => 'Interdisziplinary Center (Board member)'],
                        ],
                        'EDP committee' => [
                            'de' => ['name' => 'EDV-Kommission'],
                            'en' => ['name' => 'EDP committee'],
                        ],
                    ]
                ],
                'Organisation einer Tagung / Konferenz' => [
                    'order' => 2,
                    'short' => 'organisation_konferenz',
                    'de' => [
                        'name' => 'Organisation einer Tagung / Konferenz',
                        'title' => 'Organisation von Tagungen / Konferenzen'],
                    'en' => [
                        'name' => 'Organisation of a congress / conference',
                        'title' => 'Organisation of a congress / conference']
                ],
                'Herausgeberschaft' => [
                    'order' => 3,
                    'short' => 'herausgeberschaft',
                    'de' => [
                        'name' => 'Herausgeberschaft',
                        'title' => 'Herausgeberschaften'],
                    'en' => [
                        'name' => 'Editorship of a scientific journal',
                        'title' => 'Editorships scientific journals']
                ],
                'Gutachtertätigkeit für wissenschaftliche Zeitschrift' => [
                    'order' => 4,
                    'short' => 'gutachter_zeitschrift',
                    'de' => [
                        'name' => 'Gutachtertätigkeit für eine wissenschaftliche Zeitschrift',
                        'title' => 'Gutachtertätigkeiten für wissenschaftliche Zeitschriften'],
                    'en' => [
                        'name' => 'Expert for reviewing a scientific journal',
                        'title' => 'Experts for reviewing scientific journals']
                ],
                'Gutachtertätigkeit für Förderorganisation' => [
                    'order' => 5,
                    'short' => 'gutachter_organisation',
                    'de' => [
                        'name' => 'Gutachtertätigkeit für eine Förderorganisation',
                        'title' => 'Gutachtertätigkeiten für Förderorganisationen'],
                    'en' => [
                        'name' => 'Expert for funding organisation',
                        'title' => 'Experts for funding organisations']
                ],
                'Sonstige FAU-externe Gutachtertätigkeit' => [
                    'order' => 6,
                    'short' => 'gutachter_sonstige',
                    'de' => [
                        'name' => 'Sonstige FAU-externe Gutachtertätigkeit',
                        'title' => 'Sonstige FAU-externe Gutachtertätigkeiten'],
                    'en' => [
                        'name' => 'Other expert activitiy (FAU-external)',
                        'title' => 'Other expert activities (FAU-external)']
                ],
                'DFG-Fachkollegiat/in' => [
                    'order' => 7,
                    'short' => 'dfg-fachkollegiat',
                    'de' => [
                        'name' => 'DFG-Fachkollegiat/in',
                        'title' => 'DFG-Fachkollegiate'],
                    'en' => [
                        'name' => 'DFG-Subject field membership',
                        'title' => 'DFG-Subject field memberships']
                ],
                'Gremiumsmitglied Wissenschaftsrat' => [
                    'order' => 8,
                    'short' => 'mitglied_wissenschaftsrat',
                    'de' => [
                        'name' => 'Gremiumsmitglied im Wissenschaftsrat',
                        'title' => 'Gremiumsmitgliedschaften im Wissenschaftsrat'],
                    'en' => [
                        'name' => 'Member of the German Science Council',
                        'title' => 'Members of the German Science Council']
                ],
                'Vortrag' => [
                    'order' => 9,
                    'short' => 'vortrag',
                    'de' => [
                        'name' => 'Vortrag',
                        'title' => 'Vorträge'],
                    'en' => [
                        'name' => 'Speech / Talk',
                        'title' => 'Speeches / Talks']
                ],
                'Radio- / Fernsehbeitrag / Podcast' => [
                    'order' => 10,
                    'short' => 'medien',
                    'de' => [
                        'name' => 'Radio- / Fernsehbeitrag / Podcast',
                        'title' => 'Radio- / Fernsehbeiträge / Podcasts'],
                    'en' => [
                        'name' => 'Radio, Television or Podcast',
                        'title' => 'Radio / Television Broadcasts or Podcasts']
                ],
                'Sonstige FAU-externe Aktivitäten' => [
                    'order' => 11,
                    'short' => 'sonstige',
                    'de' => [
                        'name' => 'Sonstige FAU-externe Aktivität',
                        'title' => 'Sonstige FAU-externe Aktivitäten'],
                    'en' => [
                        'name' => 'Other activitiy (FAU-external)',
                        'title' => 'Other activities (FAU-external)']
                ],
            ],
            'projectroles' => [
                'leader' => [
                    'order' => 1,
                    'short' => 'leader',
                    'de' => [
                        'name' => 'Projektleiter',
                        'title' => 'Projektleitung'
                    ],
                    'en' => [
                        'name' => 'Project leader',
                        'title' => 'Project Management'
                    ]
                ],
                'member' => [
                    'order' => 2,
                    'short' => 'member',
                    'de' => [
                        'name' => 'Projektmitarbeiter',
                        'title' => 'Projektmitarbeit'
                    ],
                    'en' => [
                        'name' => 'Project member',
                        'title' => 'Project Membership'
                    ]
                ],
            ],
        ],
        'pub_languages' => [
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
        ]
    ];
    return $constants;
}

/**
 * Gibt der Name der Option zurück.
 * @return array [description]
 */
function getOptionName()
{
	return '_fau_cris';
}

/**
 * Gibt die Einstellungen des Menus zurück.
 * @return array [description]
 */
function getMenuSettings()
{
    return [
        'page_title'    => __('CRIS', 'fau-cris'),
        'menu_title'    => __('CRIS', 'fau-cris'),
        'capability'    => 'manage_options',
        'menu_slug'     => 'fau-cris',
        'title'         => __('CRIS Settings', 'fau-cris'),
    ];
}

/**
 * Gibt die Einstellungen der Inhaltshilfe zurück.
 * @return array [description]
 */
function getHelpTab()
{
    return [
        [
            'id'        => 'fau-cris-help',
            'content'   => [
                '<p>' . __('Here comes the Context Help content.', 'fau-cris') . '</p>'
            ],
            'title'     => __('Overview', 'fau-cris'),
            'sidebar'   => sprintf('<p><strong>%1$s:</strong></p><p><a href="https://blogs.fau.de/webworking">RRZE Webworking</a></p><p><a href="https://github.com/RRZE Webteam">%2$s</a></p>', __('For more information', 'fau-cris'), __('RRZE Webteam on Github', 'fau-cris'))
        ]
    ];
}

/**
 * Gibt die Einstellungen der Optionsbereiche zurück.
 * @return array [description]
 */
function getSections()
{
    return [
        [
            'id'    => 'cris_general',
            'title' => __('General Settings', 'fau-cris')
        ],
	    [
		    'id'    => 'cris_layout',
		    'title' => __('Layout', 'fau-cris')
	    ],
	    [
		    'id'    => 'cris_sync',
		    'title' => __('Sync', 'fau-cris')
	    ]
    ];
}

/**
 * Gibt die Einstellungen der Optionsfelder zurück.
 * @return array [description]
 */
function getFields()
{
    return [
    	'cris_general' => [
    		[
			    'name'              => 'cris_org_nr',
			    'label'             => __('CRIS-OrgNr.', 'fau-cris'),
			    'desc'              => __('You may enter multiple organization IDs, separated by a comma.', 'fau-cris'),
			    'placeholder'       => '',
			    'type'              => 'text',
			    'default'           => '',
			    'sanitize_callback' => 'sanitize_text_field'
			]
	    ],
		'cris_layout' => [
			[
				'name'        => 'cris_pub_subtypes_order',
				'label'       => __('Publication subtypes order for "Other"publications', 'fau-cris'),
				'desc'        => __('Choose the publication type order for publication lists ordered by type. One item per line.', 'fau-cris'),
				'placeholder' => __('Textarea placeholder', 'fau-cris'),
				'type'        => 'textarea',
				'default'     => 'rezension
lexikonbeitrag
zeitungsartikel
workingpaper
onlinepublikation
konferenzbericht
techreport
gutachten
andere',
			],
			[
				'name'        => 'cris_pub_order',
				'label'       => __('Publications Order', 'fau-cris'),
				'desc'        => __('Choose the publication type order for publication lists ordered by type. One item per line.', 'fau-cris'),
				'placeholder' => __('Textarea placeholder', 'fau-cris'),
				'type'        => 'textarea',
				'default'     => 'buch
beitrag_fachzeitschrift
beitrag_sammelwerk
herausgegebener_band
beitrag_tagung
uebersetzung
abschlussarbeit
andere
unveroeffentlicht',
			],
			[
				'name'  => 'cris_doi',
				'label' => __('DOI link', 'fau-cris'),
				'desc'  => __('Add DOI link to APA or MLA citations?', 'fau-cris'),
				'type'  => 'checkbox'
			],
			[
				'name'  => 'cris_url',
				'label' => __('URL', 'fau-cris'),
				'desc'  => __('Add URL to APA or MLA citations?', 'fau-cris'),
				'type'  => 'checkbox'
			],
			[
				'name'  => 'cris_oa',
				'label' => __('OA-Icon', 'fau-cris'),
				'desc'  => __('Add Open Access icon to APA or MLA citations?', 'fau-cris'),
				'type'  => 'checkbox'
			],
			[
				'name'  => 'cris_bibtex',
				'label' => __('BibTeX Link', 'fau-cris'),
				'desc'  => __('Display a BibTeX export link for each publication?', 'fau-cris'),
				'type'  => 'checkbox'
			],
			[
				'name'    => 'cris_univis',
				'label'   => __('Link Authors', 'fau-cris'),
				'desc'    => '',
				'type'    => 'radio',
				'options' => [
					'person' => __('Link authors to their FAU-Person plugin page', 'fau-cris'),
					'cris'   => __('Link authors to their profile page on cris.fau.de', 'fau-cris'),
					'none'   => __('No link', 'fau-cris')
				]
			],
			[
				'name'    => 'cris_name_order_plugin',
				'label'   => __('Name order in FAU-Person Plugin', 'fau-cris'),
				'desc'    => __('In which order are the persons\' full names provided by the FAU-Person plugin?', 'fau-cris'),
				'type'    => 'select',
				'default' => 'firstname-lastname',
				'options' => [
					'firstname-lastname' => __('First name, last name', 'fau-cris'),
					'lastname-firstname'  => __('Last name, first name', 'fau-cris')
				]
			],
			[
				'name'        => 'cris_award_order',
				'label'       => __('Awards Order', 'fau-cris'),
				'desc'        => '',
				'placeholder' => '',
				'type'        => 'textarea',
				'default'     => 'preis
stipendium
akademiemitgliedschaft
andere
keintyp',
			],
			[
				'name'    => 'cris_award_link',
				'label'   => __('Link awardees', 'fau-cris'),
				'desc'    => '',
				'type'    => 'radio',
				'options' => [
					'person' => __('Link awardees to their FAU-Person plugin page', 'fau-cris'),
					'cris'   => __('Link awardees to their profile page on cris.fau.de', 'fau-cris'),
					'none'   => __('No link', 'fau-cris')
				]
			],
			[
				'name'              => 'cris_fields_num_pub',
				'label'             => __('Research Areas: Number of Publications', 'fau-cris'),
				'desc'              => __('Maximum number of publications to be listed on a research area page.', 'fau-cris'),
				'placeholder'       => '5',
				'min'               => 0,
				'max'               => 10000000,
				'step'              => '1',
				'type'              => 'number',
				'default'           => '5',
				'sanitize_callback' => 'intval'
			],
			[
				'name'    => 'cris_field_link',
				'label'   => __('Research Areas: Link contact persons', 'fau-cris'),
				'desc'    => '',
				'type'    => 'radio',
				'options' => [
					'person' => __('Link contact persons to their FAU-Person plugin page', 'fau-cris'),
					'cris'   => __('Link contact persons to their profile page on cris.fau.de', 'fau-cris'),
					'none'   => __('No link', 'fau-cris')
				]
			],
			[
				'name'        => 'cris_project_order',
				'label'       => __('Research projects order', 'fau-cris'),
				'desc'        => '',
				'placeholder' => '',
				'type'        => 'textarea',
				'default'     => 'einzelfoerderung
teilprojekt
gesamtprojekt
graduiertenkolleg
fremdprojekt',
			],
			[
				'name'    => 'cris_project_link',
				'label'   => __('Link project members', 'fau-cris'),
				'desc'    => '',
				'type'    => 'radio',
				'options' => [
					'person' => __('Link project leaders and members to their FAU-Person plugin page', 'fau-cris'),
					'cris'   => __('Link project leaders and members to their profile page on cris.fau.de', 'fau-cris'),
					'none'   => __('No link', 'fau-cris')
				]
			],
			[
				'name'        => 'cris_patent_order',
				'label'       => __('Patents order', 'fau-cris'),
				'desc'        => '',
				'placeholder' => '',
				'type'        => 'textarea',
				'default'     => 'einzelfoerderung
teilprojekt
gesamtprojekt
graduiertenkolleg
fremdprojekt',
			],
			[
				'name'    => 'cris_patent_link',
				'label'   => __('Link patent holders', 'fau-cris'),
				'desc'    => '',
				'type'    => 'radio',
				'options' => [
					'person' => __('Link patent holders and members to their FAU-Person plugin page', 'fau-cris'),
					'cris'   => __('Link patent holders and members to their profile page on cris.fau.de', 'fau-cris'),
					'none'   => __('No link', 'fau-cris')
				]
			],
			[
				'name'        => 'cris_activities_order',
				'label'       => __('Activities order', 'fau-cris'),
				'desc'        => '',
				'placeholder' => '',
				'type'        => 'textarea',
				'default'     => 'fau-gremienmitgliedschaft
organisation_konferenz
herausgeberschaft
gutachter_zeitschrift
gutachter_organisation
gutachter_sonstige
dfg-fachkollegiat
mitglied_wissenschaftsrat
vortrag
medien
sonstige',
			],
			[
				'name'    => 'cris_activities_link',
				'label'   => __('Link persons', 'fau-cris'),
				'desc'    => '',
				'type'    => 'radio',
				'options' => [
					'person' => __('Link persons to their FAU-Person plugin page', 'fau-cris'),
					'cris'   => __('Link persons to their profile page on cris.fau.de', 'fau-cris'),
					'none'   => __('No link', 'fau-cris')
				]
			],
		],
		'cris_sync' => [
			[
				'name'  => 'cris_sync_check',
				'label' => __('Auto Sync', 'fau-cris'),
				'desc'  => sprintf(__('Automatically create pages and menu items for new research projects and research areas.%1s Important! %s Read the instructions in our %3s manual %3s carefully before activating auto sync!', 'fau-cris'),'<br /><strong>', '</strong>', '<a href="https://www.wordpress.rrze.fau.de/plugins/fau-cris/erweiterte-optionen/">', '</a>'),
				'type'  => 'checkbox'
			],
			[
				'name'    => 'cris_sync_shortcode_format',
				'label'   => __('Shortcode Format', 'fau-cris'),
				'type'    => 'multicheck',
				'options'   => [
					'one'   => __('Custom shortcode for "Research" Page', 'fau-cris'),
					'two'   => __('Custom shortcode for "Research Field" Pages', 'fau-cris'),
					'three' => __('Custom shortcode for "Research Project" Pages', 'fau-cris')
				]
			],
		],
    ];
}
