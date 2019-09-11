<?php

namespace FAU\CRIS\Config;

defined('ABSPATH') || exit;

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
