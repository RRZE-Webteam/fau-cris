<?php
namespace RRZE\Cris;
defined('ABSPATH') || exit;

use RRZE\Cris\Tools;
use RRZE\Cris\Webservice;
use RRZE\Cris\Filter;
use RRZE\Cris\Formatter;
use RRZE\Cris\Projekte;



class Publikationen
{

    private array $options;
    public $output;
    public $cms; 
    public $pathPersonenseiteUnivis;
    public $id;
    public $suchstring;
    public $univis; 
    public $order; 
    public $subtypeorder; 
    public $univisLink; 
    public $bibtex; 
    public $bibtexlink; 
    public $nameorder; 
    public $page_lang; 
    public $sc_lang; 
    public $langdiv_open;
    public $langdiv_close; 
    public $einheit; 
    public $error; 
    public $cris_pub_title_link_order;
    public function __construct($einheit = '', $id = '', $nameorder = '', $page_lang = 'de', $sc_lang = 'de')
    {


        
        if ( isset($_SERVER['PHP_SELF']) && strpos(sanitize_text_field(wp_unslash($_SERVER['PHP_SELF'])), "vkdaten/tools/")) {
            $this->cms = 'wbk';
            $this->options = CRIS::ladeConf();
            $this->pathPersonenseiteUnivis = $this->options['Pfad_Personenseite_Univis'] . '/';
        } else {
            $this->cms = 'wp';
            $this->options = (array) FAU_CRIS::get_options();
            $this->pathPersonenseiteUnivis = '/person/';
        }

        $this->id = $id ?: $this->options['cris_org_nr'];
        $this->suchstring = '';
        $this->univis = null;

        $this->order = $this->options['cris_pub_order'];
        $this->subtypeorder = $this->options['cris_pub_subtypes_order'];
        $this->cris_pub_title_link_order = $this->options['cris_pub_title_link_order'];
        $this->univisLink = $this->options['cris_univis'] ?? 'none';
        $this->bibtex = $this->options['cris_bibtex'];
        $this->bibtexlink = "https://cris.fau.de/bibtex/publication/%s.bib";
        if ($this->cms == 'wbk' && $this->univisLink == 'person') {
            $this->univis = Tools::get_univis();
        }


       
        if (strlen(trim($nameorder))) {
            $this->nameorder = $nameorder;
        } else {
            $this->nameorder = $this->options['cris_name_order_plugin'];
        }
        $this->page_lang = $page_lang;
        $this->sc_lang = $sc_lang;
        $this->langdiv_open = '<div class="cris">';
        $this->langdiv_close = '</div>';
        if ($sc_lang != $this->page_lang) {
            $this->langdiv_open = '<div class="cris" lang="' . $sc_lang . '">';
        }

        if (in_array($einheit, array("person", "orga", "award", "awardnameid", "project", "field","field_incl_proj"))) {
            $this->einheit = $einheit;
        } else {
            $this->einheit = "orga";
        }

        if (!$this->id) {
            $this->error = new \WP_Error(
                'cris-orgid-error',
                __('Bitte geben Sie die CRIS-ID der Organisation, Person oder des Projektes an.', 'fau-cris')
            );
        }



    }

    /**
     * Name : pubListe
     *
     * Use: get publication list sorted by year
     *
     * Returns: publications list
     *
     */
    public function pubListe(array $param = [], string $content = ''): string
    {
        $year = $param['year'] ?: '';
        $start = $param['start'] ?: '';
        $end = $param['end'] ?: '';
        $type = $param['type'] ?: '';
        $subtype = $param['subtype'] ?: '';
        $quotation = $param['quotation'] ?? '';
        $limit = $param['limit'] ?: '';
        $sortby = $param['sortby'] ?: 'virtualdate';
        $fau = $param['fau'] ?: '';
        $peerreviewed = $param['peerreviewed'] ?: '';
        $notable = $param['notable'] ?: 0;
        $language = $param['language'] ?: '';
        $authorPositionArray=$param['author_position'];
        $listType=$param['listtype'];
       

        $pubArray = $this->fetch_publications($year, $start, $end, $type, $subtype, $fau, $peerreviewed, $notable, $field='',$language,$fsp=false,$project='',$authorPositionArray);

        if (!count($pubArray)) {
            $output = '<p>' . __('Es wurden leider keine Publikationen gefunden.', 'fau-cris') . '</p>';
            return $output;
        }

        switch ($sortby) {
            case 'created':
                $order = 'createdon';
                break;
            case 'updated':
                $order = 'updatedon';
                break;
            default:
                $order = 'virtualdate';
        }
        $formatter = new Formatter(null, null, $order, SORT_DESC);
        $res = $formatter->execute($pubArray);
        if ($limit != '') {
            $pubList = array_slice($res[$order], 0, $limit);
        } else {
            $pubList = $res[$order];
        }

        $output = '';

        if ($quotation == 'apa' || $quotation == 'mla') {
            $output .= $this->make_quotation_list($pubList, $quotation, $param['showimage'], $param['display'],$listType,$startCount='1');
        } else {
            if ($param['sc_type'] == 'custom') {
                $output .= $this->make_custom_list($pubList, $content, '', $param['display_language'], $param['image_align'],$listType,$param['display'],$startCount='1');
            } else {
                $output .= $this->make_list($pubList, 1, $this->nameorder, $param['display_language'], $param['showimage'], $param['image_align'], $param['image_position'], $param['display'],$listType,$startCount='1');
            }
        }

        return $this->langdiv_open . $output . $this->langdiv_close;
    }

    /**
     * Name : pubNachJahr
     *
     * Use: get publication list sorted by year
     *
     * Returns: publications list
     *
     * Start::pubNachJahr
     */

    public function pubNachJahr(
        array $param = [], // It get all shortcode parameter
        $field = '',
        string $content = '', //It get the conetet which is passed by [cris-custom]#title# DOI: #DOI# URL: #url#[/cris-custom] eg:#title# DOI: #DOI# URL: #url#
        $fsp = false,
        $project = ''
    ): string {
        // Extracting parameters from the $param array
        // error_log(sprintf("pubNachJahr, field %s",$field));
        $year = $param['year'] ?: ''; // The year of publication
        $start = $param['start'] ?: ''; // The start of year from where publication is needed
        $end = $param['end'] ?: ''; // The end of year from where publication is needed
        $type = $param['type'] ?: ''; //Type of publication
        $subtype = $param['subtype'] ?: ''; //SubType of publication
        $quotation = $param['quotation'] ?: ''; // Quotation of publication eg:apa
        $order2 =  $param['order2'] ?: 'author';
        $fau = $param['fau'] ?: ''; // It is a fau publication or not if yes 1 and 0 for no
        $peerreviewed = $param['peerreviewed'] ?: '';
        $notable =  $param['notable'] ?: 0;
        $format = $param['format'] ?: '';
        $language = $param['language'] ?: '';
        $sortby = $param['sortby'] ?: 'virtualdate';
        $authorPositionArray=$param['author_position'];
        $muteheadings = $param['muteheadings'] ?? 0; 
        // it will use for showing number of publication by year or in total
        $total_publication_html='';

        $startCount=1;
        
        if (!is_array($param['publicationsum'])) {
            $publicationSumArray=array($param['publicationsum']);
        }else{
            $publicationSumArray=$param['publicationsum'];
        }
        $listType=$param['listtype'];
        
        // fetching the publication
        $pubArray = $this->fetch_publications($year, $start, $end, $type, $subtype, $fau, $peerreviewed, $notable, $field, $language, $fsp, $project,$authorPositionArray );

        if (!count($pubArray)) {
            $output = '<p>' . __('Es wurden leider keine Publikationen gefunden.', 'fau-cris') . '</p>';
            return $output;
        }



        // Sorting order
        $typeorder = $this->order; //cris default order given by plugin options
        if ($typeorder[0] != '' && ((array_search($typeorder[0], array_column(Dicts::$typeinfos['publications'], 'short')) !== false) || array_search($typeorder[0], array_column(Dicts::$typeinfos['publications'], 'short_alt')) !== false)) {
            foreach ($typeorder as $key => $value) {
                $typeorder[$key] = Tools::getType('publications', $value);
            }
        } else {
            $typeorder = Tools::getOrder('publications');
        }
        switch ($order2) {
            case 'author':
                $formatter = new Formatter("publyear", SORT_DESC, "relauthors", SORT_ASC);
                $subformatter = new Formatter(null, null, "relauthors", SORT_ASC);
                break;
            case 'type':
                $formatter = new Formatter("publyear", SORT_DESC, "virtualdate", SORT_DESC);
                $subformatter = new Formatter("publication type", array_values($typeorder), "virtualdate", SORT_DESC);
                break;
            default:
                $formatter = new Formatter("publyear", SORT_DESC, "virtualdate", SORT_DESC);
                $subformatter = new Formatter(null, null, "virtualdate", SORT_DESC);
                break;
        }
        $pubList = $formatter->execute($pubArray);

        $output = '';
        $showsubtype = ($subtype == '') ? 1 : 0;

        if (shortcode_exists('collapsibles') && $format == 'accordion'){
            
            $total_number_publication_in_accordion=0;
            $shortcode_data = '';
            if ((empty($year) || strpos($year, ',') !== false) && ($muteheadings!=1))
             {
                $openfirst = ' load="open"';
                $expandall = ' expand-all-link="true"';
            } else {
                $openfirst = '';
                $expandall = '';
            }

            foreach ($pubList as $array_year => $publications) {
                $shortcode_data_inner = '';
                $number_of_pub_in_accordion=count($publications);
                $pubSubList = $subformatter->execute($publications);
                
                if (in_array('subtotal', $publicationSumArray) ||
                (in_array('total', $publicationSumArray) && in_array('subtotal', $publicationSumArray))) {
                $suffix = ($number_of_pub_in_accordion == 1) ? __('Publikation','fau-cris') : __('Publikationen','fau-cris') ;
                $subtotal_publication_html_in_accordion = ' (' . $number_of_pub_in_accordion . ' ' . $suffix . ')';
            }

                else{
                    $subtotal_publication_html_in_accordion='';
                }

                foreach ($pubSubList as $array_subtype => $publications_sub) {
                    if ($order2 == 'type') {
                        $shortcode_data_inner .= "<h4>";
                        $shortcode_data_inner .= Tools::getTitle('publications', $array_subtype, $param['display_language']);
                        ;
                        $shortcode_data_inner .= "</h4>";
                    }
                    if ($quotation == 'apa' || $quotation == 'mla') {
                        $shortcode_data_inner .= $this->make_quotation_list($publications_sub, $quotation, $param['showimage'], $param['display'],$listType,$startCount);
                    } else {
                        if ($param['sc_type'] == 'custom') {
                            $shortcode_data_inner .= $this->make_custom_list($publications_sub, $content, '', $param['display_language'], $param['image_align'],$listType,$param['display'],$startCount);
                        } else {
                            $shortcode_data_inner .= $this->make_list($publications_sub, $showsubtype, $this->nameorder, $param['display_language'], $param['showimage'], $param['image_align'], $param['image_position'], $param['display'],$listType,$startCount);
                        }
                    }
                    $startCount += count($publications_sub);
                }
                $shortcode_data .= do_shortcode('[collapse title="' . $array_year.$subtotal_publication_html_in_accordion . '"' . $openfirst . ']' . $shortcode_data_inner . '[/collapse]');
                $openfirst = '';
                $total_number_publication_in_accordion += count($publications);
            }
            $output .= do_shortcode('[collapsibles ' . $expandall . ']' . $shortcode_data . '[/collapsibles]');

            // To show total number of publication
            if (in_array('total', $publicationSumArray) || (in_array('total', $publicationSumArray) && in_array('subtotal', $publicationSumArray))) {
                $total_publication_html = '<h2>' . __('Publikationen','fau-cris') . '  '.'('. $total_number_publication_in_accordion .')'.'</h2>';
            }

        } else {
            
            
            $total_number_publication=0;
            foreach ($pubList as $array_year => $publications) {
                $number_of_pub=count($publications);

               if (in_array('subtotal', $publicationSumArray) ||
                        (in_array('total', $publicationSumArray) && in_array('subtotal', $publicationSumArray))) {
                                    $suffix = ($number_of_pub == 1) ? __('Publikation','fau-cris') : __('Publikationen','fau-cris');
                                    $subtotal_publication_html = ' (' . $number_of_pub . ' ' . $suffix . ')';
                                    
                }
                else{
                    $subtotal_publication_html='';
                }
                if (empty($year)  && ($muteheadings!=1)) {
                    $output .= '<h3>' . $array_year . $subtotal_publication_html .'</h3>';
                }
                
                $pubSubList = $subformatter->execute($publications);
                foreach ($pubSubList as $array_subtype => $publications_sub) {
                    // Zwischenüberschrift
                    if ($order2 == 'type') {
                        $output .= "<h4>";
                        $output .= Tools::getTitle('publications', $array_subtype, $param['display_language']);
                        ;
                        $output .= "</h4>";
                    }
                    if ($quotation == 'apa' || $quotation == 'mla') {
                        $output .= $this->make_quotation_list($publications_sub, $quotation, $param['showimage'], $param['display'],$listType,$startCount);
                    } else {
                        if ($param['sc_type'] == 'custom') {
                            $output .= $this->make_custom_list($publications_sub, $content, '', $param['display_language'], $param['image_align'], $listType, $param['display'],$startCount);
                        } else {
                            $output .= $this->make_list($publications_sub, $showsubtype, $this->nameorder, $param['display_language'], $param['showimage'], $param['image_align'], $param['image_position'], $param['display'],$listType,$startCount);
                        }
                    }
                    $startCount += count($publications_sub);
                }
                $total_number_publication += $number_of_pub;
            }
            // To show total number of publication
            if (in_array('total', $publicationSumArray) || (in_array('total', $publicationSumArray) && in_array('subtotal', $publicationSumArray))) {
            $total_publication_html = '<h2>' . __('Publikationen','fau-cris') . '  '.'('. $total_number_publication .')'.'</h2>';            }

        }
        return $this->langdiv_open . $total_publication_html .$output . $this->langdiv_close;
    }
    //End ::pubNachJahr

    /**
    * Name : pubNachTyp
    *
    * Use: get publication list according to their types
    *
    * Returns: publications list by type
    *
    * Start::pubNachTyp
    */

    public function pubNachTyp(
        array $param = [],
        $field = '',
        $content = '',
        $fsp = false,
        $project = ''
    ): string {

        $year =  $param['year'] ?: '';
        $start = $param['start'] ?: '';
        $end = $param['end'] ?: '';
        $type = $param['type'] ?: '';
        $subtype = $param['subtype'] ?: '';
        $quotation = $param['quotation'] ?: '';
        $order2 = $param['order2'] ?: 'date';
        $fau = $param['fau'] ?: '';
        $peerreviewed = $param['peerreviewed'] ?: '';
        $notable = $param['notable'] ?: 0;
        $format = $param['format'] ?: '';
        $language = $param['language'] ?: '';
        $sortby =  $param['sortby'] ?: 'virtualdate';
        $sortorder =  $param['sortorder'] ?: SORT_DESC;
        $authorPositionArray=$param['author_position'];
        $listType=$param['listtype'];

         // it will use for showing number of publication by year or in total
        $total_publication_html='';
        $startCount=1;
        
        if (!is_array($param['publicationsum'])) {
            $publicationSumArray=array($param['publicationsum']);
        }else{
            $publicationSumArray=$param['publicationsum'];
        }
        $pubArray = $this->fetch_publications($year, $start, $end, $type, $subtype, $fau, $peerreviewed, $notable, $field, $language, $fsp, $project,$authorPositionArray);
        

        if (!count($pubArray)) {
            $output = '<p>' . __('Es wurden leider keine Publikationen gefunden.', 'fau-cris') . '</p>';
            return $output;
        }

        // Publikationstypen sortieren
        $order = $this->order;
        if ($order[0] != '' && ((array_search($order[0], array_column(Dicts::$typeinfos['publications'], 'short')) !== false) || array_search($order[0], array_column(Dicts::$typeinfos['publications'], 'short_alt')) !== false)) {
            foreach ($order as $key => $value) {
                $order[$key] = Tools::getType('publications', $value);
            }
        } else {
            $order = Tools::getOrder('publications');
        }

        // sortiere nach Typenliste, innerhalb des Typs nach $order2 sortieren
        if ($order2 == 'author') {
            $formatter = new Formatter("publication type", array_values($order), "relauthors", SORT_ASC);
        } else {
            $formatter = new Formatter("publication type", array_values($order), "virtualdate", SORT_DESC);
        }
        $pubList = $formatter->execute($pubArray);

        $output = '';

        if (shortcode_exists('collapsibles') && $format == 'accordion') {
            $total_number_publication_in_accordion=0;
            $shortcode_data = '';
            if (!empty($type) && strpos($type, ',') !== false) {
                $openfirst = ' load="open"';
                $expandall = ' expand-all-link="true"';
            } else {
                $openfirst = '';
                $expandall = '';
            }
            foreach ($pubList as $array_type => $publications) {
                // Zwischenüberschrift (= Publikationstyp), außer wenn nur ein Typ gefiltert wurde
                $title = Tools::getTitle('publications', $array_type, $param['display_language']);
                //if ($array_type == 'Other') {
                $shortcode_data_other = '';
                // Weitrere Untergliederung für Subtypen
                $subtypeorder = $this->subtypeorder;
                //count total number of publication in pub type
                $number_of_pub_in_accordion=count($publications);

               if (in_array('subtotal', $publicationSumArray) ||
                    (in_array('total', $publicationSumArray) && in_array('subtotal', $publicationSumArray))) {
                    $suffix = ($number_of_pub_in_accordion == 1) ? __('Publikation','fau-cris') : __('Publikationen','fau-cris') ;                    $subtotal_publication_html_in_accordion = ' (' . $number_of_pub_in_accordion . ' ' . $suffix . ')';
                }

                else{
                    $subtotal_publication_html_in_accordion='';
                }

                if ($array_type == 'Other' && $subtypeorder[0] != '' && array_search($subtypeorder[0], array_column(Dicts::$typeinfos['publications'][$array_type]['subtypes'], 'short'))) {
                    foreach ($subtypeorder as $key => $value) {
                        $subtypeorder[$key] = Tools::getType('publications', $value, $array_type);
                    }
                } else {
                    $subtypeorder = Tools::getOrder('publications', $array_type);
                }
                switch ($order2) {
                    case 'subtype':
                        $subformatter = new Formatter("subtype", array_values($subtypeorder), $sortby, $sortorder);
                        break;
                    case 'year':
                        $subformatter = new Formatter("publyear", SORT_DESC, $sortby, $sortorder);
                        break;
                    case 'author':
                    default:
                        $subformatter = new Formatter(null, null, $sortby, $sortorder);
                        break;
                }
                $pubOtherList = $subformatter->execute($publications);

                foreach ($pubOtherList as $array_subtype => $publications_sub) {
                    // Zwischenüberschrift (= Publikationstyp), außer wenn nur ein Typ gefiltert wurde
                    if ($order2 == 'subtype') {
                        $title_sub = Tools::getTitle('publications', $array_subtype, $param['display_language'], $array_type);
                        $shortcode_data_other .= "<h4>";
                        $shortcode_data_other .= $title_sub;
                        $shortcode_data_other .= "</h4>";
                    }
                    if ($order2 == 'year') {
                        $shortcode_data_other .= "<h4>";
                        $shortcode_data_other .= $array_subtype;
                        $shortcode_data_other .= "</h4>";
                    }
                    if ($quotation == 'apa' || $quotation == 'mla') {
                        $shortcode_data_other .= $this->make_quotation_list($publications_sub, $quotation, $param['showimage'], $param['display'],$listType,$startCount);
                    } else {
                        $shortcode_data_other .= $this->make_list($publications_sub, 0, $this->nameorder, $param['display_language'], $param['showimage'], $param['image_align'], $param['image_position'], $param['display'],$listType,$startCount);
                    }
                     $startCount += count($publications_sub);
                }
                $shortcode_data .= do_shortcode('[collapse title="' . $title. $subtotal_publication_html_in_accordion .  '"' . $openfirst . ']' . $shortcode_data_other . '[/collapse]');
                $openfirst = '';

                 $total_number_publication_in_accordion += $number_of_pub_in_accordion;
            }
            $output .= do_shortcode('[collapsibles ' . $expandall . ']' . $shortcode_data . '[/collapsibles]');

            // To show total number of publication
            if (in_array('total', $publicationSumArray) || (in_array('total', $publicationSumArray) && in_array('subtotal', $publicationSumArray))) {
                $total_publication_html = '<h2>' . __('Publikationen','fau-cris') . '  '.'('. $total_number_publication_in_accordion .')'.'</h2>';
            }

        } else {
            foreach ($pubList as $array_type => $publications) {
                $total_number_publication=0;
                 $number_of_pub=count($publications);
                if (in_array('subtotal', $publicationSumArray) ||
                (in_array('total', $publicationSumArray) && in_array('subtotal', $publicationSumArray))) {
                $suffix = ($number_of_pub == 1) ? __('Publikation','fau-cris') : __('Publikationen','fau-cris');
                $subtotal_publication_html = ' (' . $number_of_pub . ' ' . $suffix . ')';
            }

                else{
                    $subtotal_publication_html='';
                }
                // Zwischenüberschrift (= Publikationstyp), außer wenn nur ein Typ gefiltert wurde
                if (empty($type) || strpos($type, '-') === 0 || strpos($type, ',') !== false) {
                    $title = Tools::getTitle('publications', $array_type, $param['display_language']);
                    if (!shortcode_exists('collapsibles') || $format != 'accordion') {
                        $output .= "<h3>";
                        $output .= $title.$subtotal_publication_html;
                        $output .= "</h3>";
                    }
                }

                 

                // Weitrere Untergliederung (Subtypen)
                $subtypeorder = $this->subtypeorder;
                if ($array_type == 'Other' && $subtypeorder[0] != '' && array_search($subtypeorder[0], array_column(Dicts::$typeinfos['publications'][$array_type]['subtypes'], 'short'))) {
                    foreach ($subtypeorder as $key => $value) {
                        $subtypeorder[$key] = Tools::getType('publications', $value, $array_type);
                    }
                } else {
                    $subtypeorder = Tools::getOrder('publications', $array_type);
                }
                switch ($order2) {
                    case 'subtype':
                        $subformatter = new Formatter("subtype", array_values($subtypeorder), $sortby, $sortorder);
                        break;
                    case 'year':
                        $subformatter = new Formatter("publyear", SORT_DESC, $sortby, $sortorder);
                        break;
                    case 'author':
                    default:
                        $subformatter = new Formatter(null, null, $sortby, $sortorder);
                        break;
                }
                $pubOtherList = $subformatter->execute($publications);

                foreach ($pubOtherList as $array_subtype => $publications_sub) {
                    // Zwischenüberschrift (= Publikationstyp), außer wenn nur ein Typ gefiltert wurde
                    if ($order2 == 'subtype') {
                        $title_sub = Tools::getTitle('publications', $array_subtype, $param['display_language'], $array_type);
                        $output .= "<h4>";
                        $output .= $title_sub;
                        $output .= "</h4>";
                    }
                    if ($order2 == 'year') {
                        $output .= "<h4>";
                        $output .= $array_subtype;
                        $output .= "</h4>";
                    }
                    if ($quotation == 'apa' || $quotation == 'mla') {
                        $output .= $this->make_quotation_list($publications_sub, $quotation, $param['showimage'], $param['display'],$listType,$startCount);
                    } else {
                        if ($param['sc_type'] == 'custom') {
                            $output .= $this->make_custom_list($publications_sub, $content, '', $param['display_language'], $param['image_align'],$listType, $param['display'],$startCount);
                        } else {
                            $output .= $this->make_list($publications_sub, 0, $this->nameorder, $param['display_language'], $param['showimage'], $param['image_align'], $param['image_position'], $param['display'],$listType,$startCount);
                        }
                    }
                }
                $total_number_publication += $number_of_pub;
                $startCount += count($publications);
            }
             // To show total number of publication
            if (in_array('total', $publicationSumArray) || (in_array('total', $publicationSumArray) && in_array('subtotal', $publicationSumArray))) {
                $total_publication_html = '<h2>' . __('Publikationen','fau-cris') . '  '.'('. $total_number_publication .')'.'</h2>';
            }
        }
        return $this->langdiv_open . $total_publication_html . $output . $this->langdiv_close;
    }

    // End::pubNachTyp

    /**
        * Name : singlePub
        *
        * Use: get single publication by the publication id
        *
        * Returns: publications a single publication
        *
        * Start::singlePub
        */

    public function singlePub(
        $quotation = '',
        $content = '',
        $sc_type = 'default',
        $showimage = 0,
        $image_align = 'right',
        $image_position = "top",
        $display = 'no-list',
        $listType='ol'
    ) {
        $ws = new CRIS_publications();

        try {
            $pubArray = $ws->by_id($this->id);
        } catch (Exception $ex) {
            return;
        }

        if (!count($pubArray)) {
            return;
        }

        $display = (count($pubArray) < 2 ? 'no-list' : $display);

        if ($quotation == 'apa' || $quotation == 'mla') {
            $output = $this->make_quotation_list($pubArray, $quotation, $showimage, $display,$listType,$startCount='1');
        } else {
            if ($sc_type == 'custom') {
                $output = $this->make_custom_list($pubArray, $content, '', $this->sc_lang, $image_align,$listType,$display,$startCount='1');
            } else {
                $output = $this->make_list($pubArray, 0, $this->nameorder, $this->sc_lang, $showimage, $image_align, $image_position, $display,$listType,$startCount='1');
            }
        }
        return $this->langdiv_open . $output . $this->langdiv_close;
    }
    // End::singlePub

    /**
     * Name : projectPub
     *
     * Use: get projects of publication
     *
     * Returns: list of project in publications
     *
     * Start::projectPub
     */

    public function projectPub($param = array()): string
    {
        $pubArray = [];

        $filter = Tools::publication_filter($param['publications_year'], $param['publications_start'], $param['publications_end'], $param['publications_type'], $param['publications_subtype'], $param['publications_fau'], $param['publications_peerreviewed'], $param['publications_language']);
        if (!is_wp_error($filter)) {
            $ws = new CRIS_publications();
            if (isset($this->id) && !empty($this->id)) {
                $project_id=$this->id;
            }
            else{
                $project_id=$param['project'];
            }
            $pubArray = $ws->by_project($project_id, $filter);
        } else {
            return '';
        }

        $firstItem = reset($pubArray);
        if ($firstItem && isset($firstItem->attributes['relation right seq'])) {
            //if (array_key_exists('relation right seq', reset($pubArray)->attributes)) {
            $sortby = 'relation right seq';
            $orderby = $sortby;
        } else {
            $sortby = null;
            $orderby = __('O.A.', 'fau-cris');
        }
        $formatter = new Formatter(null, null, $sortby, SORT_ASC);
        $res = $formatter->execute($pubArray);
        $pubList = $res[$orderby] ?? [];

        if ($param['project_publications_limit'] != '') {
            $pubList = array_slice($pubList, 0, $param['project_publications_limit'], true);
        }
        if ($param['quotation'] == 'apa' || $param['quotation'] == 'mla') {
            $output = $this->make_quotation_list($pubList, $param['quotation'], 0, $param['publications_format'],$param['listtype'],$startCount='1');
        } else {
            $output = $this->make_list($pubList, 0, $this->nameorder, $param['display_language'], 0, '', '', $param['publications_format'],$param['listtype'],$startCount='1');
        }

        return $output;
    }

    // End::projectPub
    
    /**
    * Name : fieldPub
    *
    * Use: get field of publication
    *
    * Returns: list of field of publications
    *
    * Start::fieldPub
    */

    public function fieldPub(
        $param = array(),
        $seed = false
    ): string {

        $pubArray = [];

        $filter = Tools::publication_filter($param['publications_year'], $param['publications_start'], $param['publications_end'], $param['publications_type'], $param['publications_subtype'], $param['publications_fau'], $param['publications_peerreviewed'], $param['publications_language']);
        if ($param['publications_notable'] == '1') {
            $filter = Tools::publication_filter('', '', '', '', '', '', '', '', '1');
        }
        if (!is_wp_error($filter)) {
            $ws = new CRIS_publications();
            if ($seed) {
                $ws->disable_cache();
            }
            $pubArray = $ws->by_field($param['field'], $filter, $param['fsp'], $this->einheit);
        } else {
            return '';
        }

        $firstItem = reset($pubArray);
        if ($firstItem && isset($firstItem->attributes['relation right seq'])) {
            //if (array_key_exists('relation right seq', reset($pubArray)->attributes)) {
            $sortby = 'relation right seq';
            $orderby = $sortby;
        } else {
            $sortby = null;
            $orderby = __('O.A.', 'fau-cris');
        }
        
        if ($this->einheit == 'field_incl_proj') {
          $sortby = 'relauthors';
          $orderby = 'relauthors';
        }

        $formatter = new Formatter(null, null, $sortby, SORT_ASC);
        $res = $formatter->execute($pubArray);
        $pubList = $res[$orderby] ?? [];

            if ($param['publications_limit'] != '') {
                $pubList = array_slice($pubList, 0, $param['publications_limit'], true);
            }
        

        $output = '';
        if ($param['quotation'] == 'apa' || $param['quotation'] == 'mla') {
            $output = $this->make_quotation_list($pubList, $param['quotation'], 0, $param['publications_format'],$param['listtype'],$startCount='1');
        } else {
            $output = $this->make_list($pubList, 0, $this->nameorder, $param['display_language'], 0, '', '', $param['publications_format'],$param['listtype'],$startCount='1');
        }

        return $output;
    }

    // End::fieldPub

    /**
     * Name : equiPub
     *
     * Use: get equipment of publication
     *
     * Returns: list of equipment of publications
     *
     * Start::equiPub
     */

    public function equiPub($equipment, $quotation = '', $seed = false, $publications_limit = '', $display_language = 'de')
    {
        $ws = new CRIS_publications();
        if ($seed) {
            $ws->disable_cache();
        }
        try {
            $pubArray = $ws->by_equipment($equipment);
        } catch (Exception $ex) {
            return;
        }

        if (!count($pubArray)) {
            return;
        }

        $firstItem = reset($pubArray);
        if ($firstItem && isset($firstItem->attributes['relation right seq'])) {
            //if (array_key_exists('relation right seq', reset($pubArray)->attributes)) {
            $sortby = 'relation right seq';
            $orderby = $sortby;
        } else {
            $sortby = null;
            $orderby = __('O.A.', 'fau-cris');
        }
        $formatter = new Formatter(null, null, $sortby, SORT_ASC);
        $res = $formatter->execute($pubArray);
        $pubList = $res[$orderby] ?? [];

        if ($publications_limit != '') {
            $pubList = array_slice($pubList, 0, $publications_limit, true);
        }

        $output = '';
        if ($quotation == 'apa' || $quotation == 'mla') {
            $output = $this->make_quotation_list($pubList, $quotation);
        } else {
            $output = $this->make_list($pubList, 0, $this->nameorder, $display_language);
        }

        return $output;
    }

    // End::equiPub

    /* =========================================================================
     * Private Functions
      ======================================================================== */

    /*
     * Holt Daten vom Webservice je nach definierter Einheit.
     */



    /**
     * Name : fetch_publications
     *
     * Use: get all publication by_orga_id,by_pers_id,by_project,by_field or by_id
     *
     * Returns: publication array
     *
     * Start::fetch_publications
     */
    private function fetch_publications($year = '', $start = '', $end = '', $type = '', $subtype = '', $fau = '', $peerreviewed = '', $notable = 0, $field = '', $language = '', $fsp = false, $project = '',$authorPositionArray=''): array
    {
        $pubArray = [];

        $filter = Tools::publication_filter($year, $start, $end, $type, $subtype, $fau, $peerreviewed, $language);
        if (!is_wp_error($filter)) {
            $ws = new CRIS_publications();
            if ($this->einheit === "orga") {
                $pubArray = $ws->by_orga_id($this->id, $filter);
            }
            if ($this->einheit === "person") {
                $pubArray = $ws->by_pers_id($this->id, $filter, $notable);

                if (isset($authorPositionArray) && $authorPositionArray != '') {
                    if (!is_array($this->id)) {
                        $persIdArray = array($this->id);
                    }else{
                        $persIdArray=$this->id;
                    }
                    if (!is_array($authorPositionArray)) {
                        $authorPositionArray=array($authorPositionArray);
                    }
                    $pubArray=Tools::filter_publication_bypersonid_postion($pubArray,$persIdArray,$authorPositionArray);
                }
            }
            if ($this->einheit === "project") {
                $pubArray = $ws->by_project($this->id, $filter, $notable);
            }
            if ($this->einheit === "field" || $this->einheit === "field_proj" || $this->einheit === "field_incl_proj") {
                $pubArray = $ws->by_field($this->id, $filter, $fsp, $this->einheit);
            }
            if ($this->einheit === "publication") {
                $pubArray = $ws->by_id($this->id);
            }
        }

        return $pubArray;
    }
    //  End::fetch_publications

    /**
     * Name : make_quotation_list
     *
     * Use: get all publication with by formatted with (MLA/APA)
     *
     * Returns: publication array by quotation
     *
     * Start::make_quotation_list
     */
    private function make_quotation_list($publications, $quotation, $showimage = 0, $display = 'list',$listType='ul',$startCount=''): string
    {

        $quotation = strtolower($quotation);
        $list_class = ($display == 'no-list' ? 'no-list' : '');
        $image_align = 'alignright';
        $startCount = (int) $startCount;
        $publist = '';
        $listTag  = (strtolower($listType) === 'ol') ? 'ol' : 'ul';

           if ($listTag=='ol' && $list_class !== 'no-list') {
                $publist .= "<ol class=\"cris-publications $list_class\"  start=\"$startCount\">";
            }
            else{
            // Open list wrapper
            $publist .= "<ul class=\"cris-publications $list_class\"  >";
            }

        foreach ($publications as $publication) {
            $id = $publication->ID;
            $publication->insert_quotation_links();
            $cleardiv = '';
            $publist .= "<li>";
            if ($showimage == 1) {
                $publication->attributes['image'] = '';
                $imgs = self::get_pub_images($id);
                if (count($imgs)) {
                    $cleardiv = '<div style="float: none; clear: both;"></div>';
                    foreach ($imgs as $img) {
                        $img_size = getimagesizefromstring(base64_decode($img->attributes['png180']));
                        $publication->attributes['image'] = "<div class=\"cris-image wp-caption " . $image_align  . "\" style=\"width: " . $img_size[0] . "px;\">";
                        $img_description = ($img->attributes['description'] ??
                                             '');
                        if (isset($img->attributes['png180']) && mb_strlen($img->attributes['png180']) > 30) {
                            $publication->attributes['image'] .= "<img alt=\"Coverbild: " . $img_description . "\" src=\"data:image/PNG;base64," . $img->attributes['png180'] . "\" " . $img_size[3] . ">"
                                . "<p class=\"wp-caption-text\">" . $img_description . "</p>";
                            //$publication['image'] .= "<img alt=\"". $img->attributes['description'] ."\" src=\"\" width=\"\" height=\"\">" . $img_description;
                        }
                        $publication->attributes['image'] .= "</div>";
                    }
                }
                $publist .= $publication->attributes['image'];
            }
            $publist .= $publication->attributes['quotation' . $quotation . 'link'];
            if ($publication->attributes['openaccess'] == "Ja" && isset($this->options['cris_oa']) && $this->options['cris_oa'] == 1) {
                $publist .= "<span aria-hidden class=\"oa-icon\" title=\"Open-Access-Publikation\"></span>";
            }
            if (isset($this->options['cris_doi']) && $this->options['cris_doi'] == 1 && !empty($publication->attributes['doi'])) {
                $publist .= "<br />DOI: <a href='" . FAU_CRIS::doi . $publication->attributes['doi'] . "' target='blank' itemprop=\"url\">" . $publication->attributes['doi'] . "</a>";
            }
            if (isset($this->options['cris_url']) && $this->options['cris_url'] == 1 && !empty($publication->attributes['cfuri'])) {
                $publist .= "<br />URL: <a href='" . $publication->attributes['cfuri'] . "' target='blank' itemprop=\"url\">" . $publication->attributes['cfuri'] . "</a>";
            }
            if (isset($this->options['cris_bibtex']) && $this->options['cris_bibtex'] == 1) {
                $publist .= '<br />BibTeX: <a href="' . sprintf($this->bibtexlink, $publication->attributes['id_publ']) . '">Download</a>';
            }
            $publist .= $cleardiv . "</li>";
        }

        $publist .= "</$listTag>";

        return $publist;
    }

    //  End::make_quotation_list

    /**
     * Name : make_list
     *
     * Use: format the publications attributes in html
     *
     * Returns: html formatted list
     *
     * Start::make_list
     */

    private function make_list($publications, $showsubtype = 0, $nameorder = '', $lang = 'de', $showimage = 0, $image_align = 'alignright', $image_position = 'top', $display = 'list',$listType ='ul',$startCount='1'): string
    {
        $listTag    = (strtolower($listType) === 'ol') ? 'ol' : 'ul';
        $list_class = ($display == 'no-list' ? 'no-list' : '');
        // $publist = "<ol class=\"cris-publications $list_class\" lang=\"" . $lang . "\">";
        $langAttr   = 'lang="' . esc_attr($lang) . '"';
        $publist    = '';
        $currentYear= null;
        $listOpen   = false;
        $startCount = (int) $startCount;
        foreach ($publications as $publicationObject) {
            $publication = $publicationObject->attributes;
              // id
            $id = $publicationObject->ID;
            // authors
            if (strtolower($publication['complete author relations']) == 'yes') {
                $authors = explode("|", $publication['exportauthors']);
                $authorIDs = explode(",", $publication['relpersid']);
                $authorsArray = array();
                foreach ($authorIDs as $i => $key) {
                    $nameparts = explode(":", $authors[$i]);
                    $authorsArray[] = array(
                        'id' => $key,
                        'lastname' => $nameparts[0],
                        'firstname' => $nameparts[1]
                    );
                }
                $authorList = array();
                foreach ($authorsArray as $author) {
                    $authorList[] = Tools::get_person_link($author['id'], $author['firstname'], $author['lastname'], $this->univisLink, $this->cms, $this->pathPersonenseiteUnivis, $this->univis, 1, 1, $nameorder);
                }
                $authors_html = implode(", ", $authorList);
            } else {
                if ($publication['publication type'] == "Editorial") {
                    $authors_html = $publication['srceditors'];
                } else {
                    $authors_html = $publication['srcauthors'];
                }
            }
            // title (bei Rezensionen mit Original-Autor davor)

            if (!empty($publication['doi'])) {

                $doilink=FAU_CRIS::doi . (array_key_exists('doi', $publication) ? strip_tags($publication['doi']) : __('O.A.', 'fau-cris'));
            }
            else{
                $doilink='';
            }

            $pubTitlePrioLinks=array (
                'doi_link'=>$doilink,
                'OAlink'=>(array_key_exists('openaccesslink', $publication) ? strip_tags($publication['openaccesslink']) : ''),
                'URI'=>(array_key_exists('cfuri', $publication) ? strip_tags($publication['cfuri']) : __('O.A.', 'fau-cris')),
                );

            $title = '';
            if (($publication['publication type'] == 'Translation' || $publication['subtype'] == 'Rezension') && $publication['originalauthors'] != '') {
                $title = strip_tags($publication['originalauthors']) . ': ';
            }
            $title .= (array_key_exists('cftitle', $publication) ? strip_tags($publication['cftitle']) : __('O.T.', 'fau-cris'));
            global $post;
            $link=Tools::get_first_available_link($this->cris_pub_title_link_order,$pubTitlePrioLinks,$title,$id,$post->ID,$lang);
            $title_html = "<span class=\"title\" itemprop=\"name\"><strong>"
            . "<a href=\"" .$link. "\" title=\"Detailansicht in neuem Fenster &ouml;ffnen\">"
            . $title
            . "</a></strong></span>";
            if ($publication['openaccess'] == "Ja") {
                $title_html .= "<span aria-hidden=\"true\" tabindex=\"-1\" class=\"oa-icon\" title=\"Open-Access-Publikation\"></span>";
            }

            // make array
            $pubDetails = array(
                'id' => $id,
                'authors' => $authors_html,
                'title' => $title_html,
                'city' => (array_key_exists('cfcitytown', $publication) ? strip_tags($publication['cfcitytown']) : __('O.O.', 'fau-cris')),
                'publisher' => (array_key_exists('publisher', $publication) ? strip_tags($publication['publisher']) : __('O.A.', 'fau-cris')),
                'year' => (array_key_exists('publyear', $publication) ? strip_tags($publication['publyear']) : __('O.J.', 'fau-cris')),
                'pubType' => (array_key_exists('futurepublicationtype', $publication) && $publication['futurepublicationtype'] != '') ? strip_tags($publication['futurepublicationtype']) : (array_key_exists('publication type', $publication) ? strip_tags($publication['publication type']) : __('O.A.', 'fau-cris')),
                'pubStatus' => (array_key_exists('publstatus', $publication) ? strip_tags($publication['publstatus']) : ''),
                'pagesTotal' => (array_key_exists('cftotalpages', $publication) ? strip_tags($publication['cftotalpages']) : ''),
                'pagesRange' => (array_key_exists('pagesrange', $publication) ? strip_tags($publication['pagesrange']) : ''),
                'lexiconColumn' => (array_key_exists('lexiconcolumn', $publication) ? strip_tags($publication['lexiconcolumn']) : ''),
                'volume' => (array_key_exists('cfvol', $publication) ? strip_tags($publication['cfvol']) : __('O.A.', 'fau-cris')),
                'series' => (array_key_exists('cfseries', $publication) ? strip_tags($publication['cfseries']) : __('O.A.', 'fau-cris')),
                'seriesNumber' => !empty($publication['book volume']) ? strip_tags($publication['book volume']) : '',
                'ISBN' => (array_key_exists('cfisbn', $publication) ? strip_tags($publication['cfisbn']) : __('O.A.', 'fau-cris')),
                'ISSN' => (array_key_exists('cfissn', $publication) ? strip_tags($publication['cfissn']) : __('O.A.', 'fau-cris')),
                'DOI' => (array_key_exists('doi', $publication) ? strip_tags($publication['doi']) : __('O.A.', 'fau-cris')),
                'OA' => (array_key_exists('openaccess', $publication) ? strip_tags($publication['openaccess']) : false),
                'OAlink' => (array_key_exists('openaccesslink', $publication) ? strip_tags($publication['openaccesslink']) : ''),
                'URI' => (array_key_exists('cfuri', $publication) ? strip_tags($publication['cfuri']) : __('O.A.', 'fau-cris')),
                'editiors' => (array_key_exists('editor', $publication) ? strip_tags($publication['editor']) : __('O.A.', 'fau-cris')),
                'booktitle' => (array_key_exists('edited volumes', $publication) ? strip_tags($publication['edited volumes']) : __('O.A.', 'fau-cris')), // Titel des Sammelbands
                'journaltitle' => (array_key_exists('journalname', $publication) ? strip_tags($publication['journalname']) : __('O.A.', 'fau-cris')),
                'eventtitle' => (array_key_exists('event title', $publication) ? strip_tags($publication['event title']) : ''),
                'eventlocation' => (array_key_exists('event location', $publication) ? strip_tags($publication['event location']) : ''),
                'eventstart_raw' => !empty($publication['event start date']) ? $publication['event start date'] : (!empty($publication['publyear']) ? $publication['publyear'] : '-----'),
                'eventend_raw' => (!empty($publication['event end date']) ? $publication['event end date'] : ''),
                'eventstart' => !empty($publication['event start date']) ? date_i18n(get_option('date_format'), strtotime(strip_tags($publication['event start date']))) : '',
                'eventend' => (!empty($publication['event end date']) ? date_i18n(get_option('date_format'), strtotime(strip_tags($publication['event end date']))) : ''),
                'origTitle' => (array_key_exists('originaltitel', $publication) ? strip_tags($publication['originaltitel']) : __('O.A.', 'fau-cris')),
                'language' => (array_key_exists('language', $publication) ? strip_tags($publication['language']) : __('O.A.', 'fau-cris')),
                'bibtex_link' => '<a href="' . sprintf($this->bibtexlink, $id) . '">Download</a>',
                'otherSubtype' => (array_key_exists('type other subtype', $publication) ? $publication['type other subtype'] : ''),
                'thesisSubtype' => (array_key_exists('publication thesis subtype', $publication) ? $publication['publication thesis subtype'] : ''),
                'articleNumber' => (array_key_exists('article number', $publication) ? $publication['article number'] : ''),
                'conferenceProceedingsTitle' => (array_key_exists('conference proceedings title', $publication) ? $publication['conference proceedings title'] : '')
            );
            
            

            $publication['image'] = '';
            $cleardiv = '';
            if ($showimage == 1) {
                $imgs = self::get_pub_images($id);
                if (count($imgs)) {
                    $cleardiv = '<div style="float: none; clear: both;"></div>';
                    foreach ($imgs as $img) {
                        $img_size = getimagesizefromstring(base64_decode($img->attributes['png180']));
                        $publication['image'] = "<div class=\"cris-image wp-caption " . $image_align  . "\" style=\"width: " . $img_size[0] . "px;\">";
                        $img_description = ($img->attributes['description'] ??
                                             '');
                        if (isset($img->attributes['png180']) && mb_strlen($img->attributes['png180']) > 30) {
                            $publication['image'] .= "<img alt=\"Coverbild: " . $img_description . "\" src=\"data:image/PNG;base64," . $img->attributes['png180'] . "\" " . $img_size[3] . ">"
                                . "<p class=\"wp-caption-text\">" . $img_description . "</p>";
                            //$publication['image'] .= "<img alt=\"". $img->attributes['description'] ."\" src=\"\" width=\"\" height=\"\">" . $img_description;
                        }
                        $publication['image'] .= "</div>";
                    }
                }
            }

             $year = $pubDetails['year'];
            if ($listTag === 'ol' && $list_class !== 'no-list') {
                // Restart numbering when year changes
                if ($year !== $currentYear) {
                    if ($listOpen) {
                        $publist .= "</ol>";
                        
                    }
                    // Use the start attribute for continuous numbering
                    $publist .= "<ol class=\"cris-publications $list_class\" $langAttr start=\"$startCount\">";
                    $currentYear = $year;
                    $listOpen    = true;
                    
                }
            } else {
                // For unordered, open once
                if (!$listOpen) {
                    
                    $publist .= "<ul class=\"cris-publications $list_class\" $langAttr>";
                    $listOpen = true;
                }
            }           
            switch (strtolower($pubDetails['pubType'])) {
                case "book": // OK
                    $publist .= "<li itemscope itemtype=\"http://schema.org/Book\">";
                    if ($image_position == 'top') {
                        $publist .= $publication['image'];
                    }
                    $publist .= $pubDetails['authors'] . ':';
                    $publist .= "<br />" . $pubDetails['title'];
                    $publist .= $publication['publication type'] == 'Unpublished' ? ' (' . Tools::getName('publications', $publication['publication type'], $lang, $pubDetails['pubType']) . ')' : '';
                    $publist .= (($pubDetails['city'] != '') || ($pubDetails['publisher'] != '') || ($pubDetails['year'] != '')) ? "<br />" : '';
                    $publist .= $pubDetails['volume'] != '' ? $pubDetails['volume'] . ". " : '';
                    if (!empty($pubDetails['publisher'])) {
                        $publist .= "<span itemprop=\"publisher\" itemscope itemtype=\"http://schema.org/Organization\">";
                        $publist .= $pubDetails['city'] != '' ? "<span class=\"city\" itemprop=\"address\" itemscope itemtype=\"http://schema.org/PostalAddress\">"
                            . "<span itemprop=\"addressLocality\">" . $pubDetails['city'] . "</span></span>: " : '';
                        $publist .= "<span itemprop=\"name\">" . $pubDetails['publisher'] . "</span></span>, ";
                    } else {
                        $publist .= $pubDetails['city'] != '' ? $pubDetails['city'] . ", " : '';
                    }
                    $publist .= $pubDetails['year'] != '' ? "<span itemprop=\"datePublished\">" . $pubDetails['year'] . "</span>" : '';
                    if (!empty($pubDetails['series'])) {
                        $publist .= $pubDetails['series'] != '' ? "<br />(" . $pubDetails['series'] : '';
                        $publist .= $pubDetails['seriesNumber'] != '' ? ", " . _x('Bd.', 'Abkürzung für "Band" bei Publikationen', 'fau-cris') . $pubDetails['seriesNumber'] : '';
                        $publist .= ")";
                    }
                    $publist .= $pubDetails['pagesTotal'] != '' ? "<br /><span itemprop=\"numberOfPages\">" . $pubDetails['pagesTotal'] . "</span> " . __('Seiten', 'fau-cris') : '';
                    $publist .= $pubDetails['ISBN'] != '' ? "<br /><span itemprop=\"isbn\">ISBN: " . $pubDetails['ISBN'] . "</span>" : '';
                    $publist .= $pubDetails['DOI'] != '' ? "<br />DOI: <a href='" . FAU_CRIS::doi . $pubDetails['DOI'] . "' target='blank' itemprop=\"sameAs\">" . $pubDetails['DOI'] . "</a>" : '';
                    $publist .= ($pubDetails['DOI'] == '' && $pubDetails['OA'] == 'Ja' && $pubDetails['OAlink'] != '') ? "<br />Open Access: <a href='" . $pubDetails['OAlink'] . "' target='blank' itemprop=\"sameAs\">" . $pubDetails['OAlink'] . "</a>" : '';
                    $publist .= $pubDetails['URI'] != '' ? "<br />URL: <a href='" . $pubDetails['URI'] . "' target='blank' itemprop=\"url\">" . $pubDetails['URI'] . "</a>" : '';
                    break;

                case "other":
                case "article in edited volumes":
                    if (($pubDetails['pubType'] == 'Other' && $pubDetails['booktitle'] != '') || $pubDetails['pubType'] == 'Article in Edited Volumes') {
                        $publist .= "<li itemscope itemtype=\"http://schema.org/ScholarlyArticle\">";
                        if ($image_position == 'top') {
                            $publist .= $publication['image'];
                        }
                        $publist .= $pubDetails['authors'] . ':';
                        $publist .= "<br />" . $pubDetails['title'];
                        if ($pubDetails['booktitle'] != '') {
                            $publist .= "<br /><span itemscope itemtype=\"http://schema.org/Book\">In: ";
                            $publist .= $pubDetails['editiors'] != '' ? "<span itemprop=\"author\">" . $pubDetails['editiors'] . " (" . __('Hrsg.', 'fau-cris') . "): </span>" : '';
                            $publist .= "<span itemprop=\"name\"><strong>" . $pubDetails['booktitle'] . "</strong></span>";
                            $publist .= ($pubDetails['city'] != '' || $pubDetails['publisher'] != '') ? ", <span itemprop=\"publisher\" itemscope itemtype=\"http://schema.org/Organization\">" : '';
                            $publist .= $pubDetails['city'] != '' ? "<span class=\"city\" itemprop=\"address\" itemscope itemtype=\"http://schema.org/PostalAddress\">"
                                . "<span itemprop=\"addressLocality\">" . $pubDetails['city'] . "</span></span>: " : '';
                            $publist .= $pubDetails['publisher'] != '' ? "<span itemprop=\"name\">" . $pubDetails['publisher'] . "</span>" : '';
                            $publist .= ($pubDetails['city'] != '' || $pubDetails['publisher'] != '') ? "</span>" : '';
                            $publist .= $pubDetails['year'] != '' ? ", <span itemprop=\"datePublished\">" . $pubDetails['year'] . "</span>" : '';
                            if ($pubDetails['pagesRange'] != '') {
                                $publist .= ", " . _x('S.', 'Abkürzung für "Seite" bei Publikationen', 'fau-cris') . " <span itemprop=\"pagination\">" . $pubDetails['pagesRange'] . "</span>";
                            } elseif ($pubDetails['articleNumber'] != '') {
                                $publist .= ", " . _x('Art.Nr.', 'Abkürzung für "Artikelnummer" bei Publikationen', 'fau-cris') . ": <span itemprop=\"pagination\">" . $pubDetails['articleNumber'] . "</span>";
                            }
                            $publist .= $pubDetails['lexiconColumn'] != '' ? ", " . _x('Sp.', 'Abkürzung für "Spalte" bei Lexikonartikeln', 'fau-cris') . " <span itemprop=\"pagination\">" . $pubDetails['lexiconColumn'] . "</span>" : '';
                            if (!empty($pubDetails['series'])) {
                                $publist .= $pubDetails['series'] != '' ? " (" . $pubDetails['series'] : '';
                                $publist .= $pubDetails['seriesNumber'] != '' ? ", " . _x('Bd.', 'Abkürzung für "Band" bei Publikationen', 'fau-cris') . $pubDetails['seriesNumber'] : '';
                                $publist .= ")";
                            }
                            $publist .= $pubDetails['ISBN'] != '' ? "<br /><span itemprop=\"isbn\">ISBN: " . $pubDetails['ISBN'] . "</span>" : '';
                            $publist .= "</span>";
                        }
                        $publist .= $pubDetails['DOI'] != '' ? "<br />DOI: <a href='" . FAU_CRIS::doi . $pubDetails['DOI'] . "' target='blank' itemprop=\"sameAs\">" . $pubDetails['DOI'] . "</a></span>" : '';
                        $publist .= ($pubDetails['DOI'] == '' && $pubDetails['OA'] == 'Ja' && $pubDetails['OAlink'] != '') ? "<br />Open Access: <a href='" . $pubDetails['OAlink'] . "' target='blank' itemprop=\"sameAs\">" . $pubDetails['OAlink'] . "</a>" : '';
                        $publist .= $pubDetails['URI'] != '' ? "<br />URL: <a href='" . $pubDetails['URI'] . "' target='blank' itemprop=\"url\">" . $pubDetails['URI'] . "</a>" : '';
                        break;
                    }
                    // no break
                case "journal article":
                    $publist .= "<li itemscope itemtype=\"http://schema.org/ScholarlyArticle\">";
                    if ($image_position == 'top') {
                        $publist .= $publication['image'];
                    }
                    $publist .= $pubDetails['authors'] . ":";
                    $publist .= "<br />" . $pubDetails['title'];
                    $publist .= (($pubDetails['journaltitle'] != '') || ($pubDetails['volume'] != '') || ($pubDetails['year'] != '') || ($pubDetails['pagesRange'] != '')) ? "<br />" : '';
                    $publist .= $pubDetails['journaltitle'] != '' ? "In: <span itemscope itemtype=\"http://schema.org/Periodical\" itemid=\"#periodical_" . $pubDetails['id'] . "\"><span itemprop=\"name\"><strong>" . $pubDetails['journaltitle'] . "</strong></span></span>" : '';
                    $publist .= $pubDetails['seriesNumber'] != '' ? " <span itemprop=\"isPartOf\" itemscope itemtype=\"http://schema.org/PublicationVolume\"><link itemprop=\"isPartOf\" href=\"#periodical_" . $pubDetails['id'] . "\" /><span itemprop=\"volumeNumber\">" . $pubDetails['seriesNumber'] . "</span></span> " : '';
                    $publist .= $pubDetails['year'] != '' ? " (<span itemprop=\"datePublished\">" . $pubDetails['year'] . "</span>)" : '';
                    if ($pubDetails['pagesRange'] != '') {
                        $publist .= ", " . _x('S.', 'Abkürzung für "Seite" bei Publikationen', 'fau-cris') . " <span itemprop=\"pagination\">" . $pubDetails['pagesRange'] . "</span>";
                    } elseif ($pubDetails['articleNumber'] != '') {
                        $publist .= ", " . _x('Art.Nr.', 'Abkürzung für "Artikelnummer" bei Publikationen', 'fau-cris') . ": <span itemprop=\"pagination\">" . $pubDetails['articleNumber'] . "</span>";
                    }
                    $publist .= $pubDetails['lexiconColumn'] != '' ? ", " . _x('Sp.', 'Abkürzung für "Spalte" bei Lexikonartikeln', 'fau-cris') . " <span itemprop=\"pagination\">" . $pubDetails['lexiconColumn'] . "</span>" : '';
                    $publist .= $pubDetails['ISSN'] != '' ? "<br><span itemscope itemtype=\"http://schema.org/Periodical\" itemid=\"#periodical_" . $pubDetails['id'] . "\"><span itemprop=\"issn\">ISSN: " . $pubDetails['ISSN'] . "</span></span></span>" : "</span>";
                    $publist .= $pubDetails['DOI'] != '' ? "<br />DOI: <a href='" . FAU_CRIS::doi . $pubDetails['DOI'] . "' target='blank' itemprop=\"sameAs\">" . $pubDetails['DOI'] . "</a>" : '';
                    $publist .= ($pubDetails['DOI'] == '' && $pubDetails['OA'] == 'Ja' && $pubDetails['OAlink'] != '') ? "<br />Open Access: <a href='" . $pubDetails['OAlink'] . "' target='blank' itemprop=\"sameAs\">" . $pubDetails['OAlink'] . "</a>" : '';
                    $publist .= $pubDetails['URI'] != '' ? "<br />URL: <a href='" . $pubDetails['URI'] . "' target='blank' itemprop=\"url\">" . $pubDetails['URI'] . "</a>" : '';
                    break;

                case "conference contribution": // OK
                    $publist .= "<li itemscope itemtype=\"http://schema.org/ScholarlyArticle\">";
                    if ($image_position == 'top') {
                        $publist .= $publication['image'];
                    }
                    $publist .= $pubDetails['authors'] . ':';
                    $publist .= "<br />" . $pubDetails['title'];
                    $publist .= $publication['publication type'] == 'Unpublished' ? ' (' . Tools::getName('publications', $publication['publication type'], $lang, $pubDetails['pubType']) . (!empty($pubDetails['pubStatus']) ? ', ' . strtolower($pubDetails['pubStatus']) : '') . ')' : '';
                    if ($pubDetails['eventtitle'] != '') {
                        $publist .= "<br /><span itemscope itemtype=\"http://schema.org/Event\" style=\"font-style:italic;\">";
                        $publist .= "<span itemprop=\"name\">" . $pubDetails['eventtitle'] . "</span>";
                        $publist .= ($pubDetails['eventlocation'] != '' || $pubDetails['eventstart'] != '' || $pubDetails['eventend'] != '') ? " (" : '';
                        $publist .= $pubDetails['eventlocation'] != '' ? "<span itemprop =\"location\" itemscope itemtype=\"http://schema.org/PostalAddress\">"
                            . "<span itemprop=\"name\">" . $pubDetails['eventlocation'] . "</span></span>" : '';
                        $publist .= $pubDetails['eventstart'] != '' ? ", <span itemprop=\"startDate\" content=\"" . $pubDetails['eventstart_raw'] . "\">" . $pubDetails['eventstart'] . "</span>" : "<span itemprop=\"startDate\" content=\"" . $pubDetails['eventstart_raw'] . "\"></span>";
                        $publist .= $pubDetails['eventend'] != '' ? " - <span itemprop=\"endDate\" content=\"" . $pubDetails['eventend_raw'] . "\">" . $pubDetails['eventend'] . "</span>" : '';
                        $publist .= ($pubDetails['eventlocation'] != '' || $pubDetails['eventstart'] != '' || $pubDetails['eventend'] != '') ? ")" : '';
                        $publist .= "</span>";
                    }
                    if ($pubDetails['conferenceProceedingsTitle'] != '') {
                        $publist .= "<br /><span itemscope itemtype=\"http://schema.org/Book\">In: ";
                        $publist .= $pubDetails['editiors'] != '' ? "<span itemprop=\"author\">" . $pubDetails['editiors'] . " (" . __('Hrsg.', 'fau-cris') . "): </span>" : '';
                        $publist .= "<span itemprop=\"name\" style=\"font-weight:bold;\">" . $pubDetails['conferenceProceedingsTitle'] . "</span>";
                        $publist .= ($pubDetails['city'] != '') ? ", <span itemprop=\"publisher\" itemscope itemtype=\"http://schema.org/Organization\">" : '';
                        $publist .= $pubDetails['city'] != '' ? "<span class=\"city\" itemprop=\"address\" itemscope itemtype=\"http://schema.org/PostalAddress\">"
                            . "<span itemprop=\"addressLocality\">" . $pubDetails['city'] . "</span></span>: " : '';
                        $publist .= ($pubDetails['city'] != '') ? "</span>" : '';
                        $publist .= $pubDetails['year'] != '' ? " <span itemprop=\"datePublished\">" . $pubDetails['year'] . "</span>" : '';
                        $publist .= "</span>";
                    }
                    $publist .= $pubDetails['DOI'] != '' ? "<br />DOI: <a href='" . FAU_CRIS::doi . $pubDetails['DOI'] . "' target='blank' itemprop=\"sameAs\">" . $pubDetails['DOI'] . "</a>" : '';
                    $publist .= ($pubDetails['DOI'] == '' && $pubDetails['OA'] == 'Ja' && $pubDetails['OAlink'] != '') ? "<br />Open Access: <a href='" . $pubDetails['OAlink'] . "' target='blank' itemprop=\"sameAs\">" . $pubDetails['OAlink'] . "</a>" : '';
                    $publist .= $pubDetails['URI'] != '' ? "<br />URL: <a href='" . $pubDetails['URI'] . "' target='blank' itemprop=\"url\">" . $pubDetails['URI'] . "</a>" : '';
                    break;

                case "editorial":
                case "edited volumes":
                    $publist .= "<li itemscope itemtype=\"http://schema.org/Book\">";
                    if ($image_position == 'top') {
                        $publist .= $publication['image'];
                    }
                    $publist .= $pubDetails['authors'] . ':';
                    $publist .= "<br />" . $pubDetails['title'];
                    $publist .= $publication['publication type'] == 'Unpublished' ? ' (' . Tools::getName('publications', $publication['publication type'], $lang, $pubDetails['pubType']) . ')' : '';
                    $publist .= (($pubDetails['city'] != '') || ($pubDetails['publisher'] != '') || ($pubDetails['year'] != '')) ? "<br />" : '';
                    $publist .= $pubDetails['volume'] != '' ? $pubDetails['volume'] . ". " : '';


                    if (!empty($pubDetails['publisher'])) {
                        $publist .= "<span itemprop=\"publisher\" itemscope itemtype=\"http://schema.org/Organization\">";
                        $publist .= $pubDetails['city'] != '' ? "<span class=\"city\" itemprop=\"address\" itemscope itemtype=\"http://schema.org/PostalAddress\">"
                            . "<span itemprop=\"addressLocality\">" . $pubDetails['city'] . "</span></span>: " : '';
                        $publist .= "<span itemprop=\"name\">" . $pubDetails['publisher'] . "</span></span>, ";
                    } else {
                        $publist .= $pubDetails['city'] != '' ? $pubDetails['city'] . ", " : '';
                    }

                    $publist .= $pubDetails['year'] != '' ? "<span itemprop=\"datePublished\">" . $pubDetails['year'] . "</span>" : '';

                    if (!empty($pubDetails['series'])) {
                        $publist .= $pubDetails['series'] != '' ? "<br />(" . $pubDetails['series'] : '';
                        $publist .= $pubDetails['seriesNumber'] != '' ? ", " . _x('Bd.', 'Abkürzung für "Band" bei Publikationen', 'fau-cris') . $pubDetails['seriesNumber'] : '';
                        $publist .= ")";
                    }

                    $publist .= $pubDetails['pagesTotal'] != '' ? "<br /><span itemprop=\"numberOfPages\">" . $pubDetails['pagesTotal'] . "</span> " . __('Seiten', 'fau-cris') : '';
                    $publist .= $pubDetails['ISBN'] != '' ? "<br /><span itemprop=\"isbn\">ISBN: " . $pubDetails['ISBN'] . "</span>" : '';
                    $publist .= $pubDetails['DOI'] != '' ? "<br />DOI: <a href='" . FAU_CRIS::doi . $pubDetails['DOI'] . "' target='blank' itemprop=\"sameAs\">" . $pubDetails['DOI'] . "</a>" : '';
                    $publist .= ($pubDetails['DOI'] == '' && $pubDetails['OA'] == 'Ja' && $pubDetails['OAlink'] != '') ? "<br />Open Access: <a href='" . $pubDetails['OAlink'] . "' target='blank' itemprop=\"sameAs\">" . $pubDetails['OAlink'] . "</a>" : '';
                    $publist .= $pubDetails['URI'] != '' ? "<br />URL: <a href='" . $pubDetails['URI'] . "' target='blank' itemprop=\"url\">" . $pubDetails['URI'] . "</a>" : '';
                    break;

                case "thesis":
                    $publist .= "<li itemscope itemtype=\"http://schema.org/Thesis\">";
                    if ($image_position == 'top') {
                        $publist .= $publication['image'];
                    }
                    $publist .= $pubDetails['authors'] . ':';
                    $publist .= "<br />" . $pubDetails['title'];
                    $publist .= " (" . ($pubDetails['thesisSubtype'] != '' ? Tools::getName('publications', 'Thesis', $lang, $pubDetails['thesisSubtype']) : __('Abschlussarbeit', 'fau-cris')) . ", <span itemprop=\"datePublished\">" . $pubDetails['year'] . "</span>)";
                    $publist .= $pubDetails['DOI'] != '' ? "<br />DOI: <a href='" . FAU_CRIS::doi . $pubDetails['DOI'] . "' target='blank' itemprop=\"sameAs\">" . $pubDetails['DOI'] . "</a>" : '';
                    $publist .= $pubDetails['URI'] != '' ? "<br />URL: <a href='" . $pubDetails['URI'] . "' target='blank' itemprop=\"url\">" . $pubDetails['URI'] . "</a>" : '';
                    break;

                case "translation":
                    $publist .= "<li itemscope itemtype=\"http://schema.org/Book\">";
                    if ($image_position == 'top') {
                        $publist .= $publication['image'];
                    }
                    $publist .= $pubDetails['authors'] . ':';
                    $publist .= $pubDetails['title'];
                    $publist .= $publication['publication type'] == 'Unpublished' ? ' (' . Tools::getName('publications', $publication['publication type'], $lang, $pubDetails['pubType']) . ')' : '';
                    $publist .= (($pubDetails['city'] != '') || ($pubDetails['publisher'] != '') || ($pubDetails['year'] != '')) ? "<br />" : '';
                    $publist .= $pubDetails['volume'] != '' ? $pubDetails['volume'] . ". " : '';
                    if (!empty($pubDetails['publisher'])) {
                        $publist .= "<span itemprop=\"publisher\" itemscope itemtype=\"http://schema.org/Organization\">";
                        $publist .= $pubDetails['city'] != '' ? "<span class=\"city\" itemprop=\"address\" itemscope itemtype=\"http://schema.org/PostalAddress\">"
                            . "<span itemprop=\"addressLocality\">" . $pubDetails['city'] . "</span></span>: " : '';
                        $publist .= "<span itemprop=\"name\">" . $pubDetails['publisher'] . "</span></span>, ";
                    } else {
                        $publist .= $pubDetails['city'] != '' ? $pubDetails['city'] . ", " : '';
                    }
                    $publist .= $pubDetails['year'] != '' ? "<span itemprop=\"datePublished\">" . $pubDetails['year'] . "</span>" : '';
                    $publist .= $pubDetails['series'] != '' ? "<br />" . $pubDetails['series'] : '';
                    $publist .= $pubDetails['seriesNumber'] != '' ? ", " . _x('Bd.', 'Abkürzung für "Band" bei Publikationen', 'fau-cris') . $pubDetails['seriesNumber'] : '';
                    $publist .= $pubDetails['pagesTotal'] != '' ? "<br /><span itemprop=\"numberOfPages\">" . $pubDetails['pagesTotal'] . "</span> " . __('Seiten', 'fau-cris') : '';
                    $publist .= $pubDetails['ISBN'] != '' ? "<br /><span itemprop=\"isbn\">ISBN: " . $pubDetails['ISBN'] . "</span>" : '';
                    $publist .= $pubDetails['ISSN'] != '' ? "<br /><span itemprop=\"issn\">ISSN: " . $pubDetails['ISSN'] . "</span>" : '';
                    $publist .= $pubDetails['DOI'] != '' ? "<br />DOI: <a href='" . FAU_CRIS::doi . $pubDetails['DOI'] . "' target='blank' itemprop=\"sameAs\">" . $pubDetails['DOI'] . "</a>" : '';
                    $publist .= ($pubDetails['DOI'] == '' && $pubDetails['OA'] == 'Ja' && $pubDetails['OAlink'] != '') ? "<br />Open Access: <a href='" . $pubDetails['OAlink'] . "' target='blank' itemprop=\"sameAs\">" . $pubDetails['OAlink'] . "</a>" : '';
                    $publist .= $pubDetails['URI'] != '' ? "<br />URL: <a href='" . $pubDetails['URI'] . "' target='blank' itemprop=\"url\">" . $pubDetails['URI'] . "</a>" : '';
                    $publist .= $pubDetails['origTitle'] != '' ? "<br />Originaltitel: " . $pubDetails['origTitle'] : '';
                    $publist .= $pubDetails['language'] != '' ? "<br />Sprache: <span itemprop=\"inLanguage\">" . $pubDetails['language'] . "</span>" : '';
                    break;
            }
            if ($this->bibtex == 1) {
                $publist .= "<br />BibTeX: " . $pubDetails['bibtex_link'];
            }
            if ($showsubtype == 1 && $pubDetails['otherSubtype'] != '') {
                $publist .= "<br />(" . $pubDetails['otherSubtype'] . ")";
            }
            if ($image_position == 'bottom') {
                $publist .= $publication['image'];
            }
            $publist .= $cleardiv . "</li>";
        }
        
        // Close any open list
        if ($listOpen) {
            $publist .= "</{$listTag}>";
            
        }
        
        return $publist;
    }
    //  End::make_list

    /**
     * Name : make_custom_list
     *
     * Use: format the customize publications attributes in html
     *
     * Returns: html formatted list
     *
     * Start::make_custom_list
     */

    private function make_custom_list($publications, $custom_text, $nameorder = '', $lang = 'de', $image_align = 'alignright', $listType='',$display = 'list',$startCount=''): string
    {
        
        // Determine list tag and language attribute
            $langAttr = 'lang="' . esc_attr($lang) . '"';
            $listTag  = (strtolower($listType) === 'ol') ? 'ol' : 'ul';
            $publist  = '';
            $hasItems = !empty($publications);
            $list_class = ($display == 'no-list' ? 'no-list' : '');
            $startCount = (int) $startCount;
           

        // Wrap in div if no items
            if (! $hasItems) {
                $publist .= "<div class=\"cris-publications\" $langAttr>";
            }  
            if ($listTag=='ol' && $list_class !== 'no-list') {
                $publist .= "<ol class=\"cris-publications $list_class\" $langAttr start=\"$startCount\">";
            }
            else{
            // Open list wrapper
            $publist .= "<ul class=\"cris-publications $list_class\" $langAttr >";
            }

        // Loop through publications
        foreach ($publications as $publObject) {
            $publication = (array) $publObject;
            foreach ($publication['attributes'] as $attribut => $v) {
                $publication[$attribut] = $v;
            }
            unset($publication['attributes']);
            $id = $publication['ID'];
            // authors
            if (strtolower($publication['complete author relations']) == 'yes') {
                $authors = explode("|", $publication['exportauthors']);
                $authorIDs = explode(",", $publication['relpersid']);
                $authorsArray = array();
                foreach ($authorIDs as $i => $key) {
                    $nameparts = explode(":", $authors[$i]);
                    $authorsArray[] = array(
                        'id' => $key,
                        'lastname' => $nameparts[0],
                        'firstname' => $nameparts[1]
                    );
                }
                $authorList = array();
                foreach ($authorsArray as $author) {
                    $authorList[] = Tools::get_person_link($author['id'], $author['firstname'], $author['lastname'], $this->univisLink, $this->cms, $this->pathPersonenseiteUnivis, $this->univis, 1, 1, $nameorder);
                }
                $authors_html = implode(", ", $authorList);
            } else {
                if ($publication['publication type'] == "Editorial") {
                    $authors_html = $publication['srceditors'];
                } else {
                    $authors_html = $publication['srcauthors'];
                }
            }
            // title (bei Rezensionen mit Original-Autor davor)
            $title = '';
            if (($publication['publication type'] == 'Translation' || $publication['subtype'] == 'Rezension') && $publication['originalauthors'] != '') {
                $title = strip_tags($publication['originalauthors']) . ': ';
            }
            $title .= (array_key_exists('cftitle', $publication) ? strip_tags($publication['cftitle']) : __('O.T.', 'fau-cris'));
            global $post;
            $title_html = "<span class=\"title\" itemprop=\"name\"><strong>"
                . "<a href=\"" . Tools::get_item_url("publications", $title, $id, $post->ID, $lang) . "\" title=\"Detailansicht in neuem Fenster &ouml;ffnen\">"
                . $title
                . "</a></strong></span>";
            //pubType
            $pubTypeRaw = (array_key_exists('futurepublicationtype', $publication) && $publication['futurepublicationtype'] != '') ? strip_tags($publication['futurepublicationtype']) : (array_key_exists('publication type', $publication) ? strip_tags($publication['publication type']) : __('O.A.', 'fau-cris'));

            $pubType = Tools::getName('publications', $pubTypeRaw, $lang);
            // make array
            setlocale(LC_TIME, get_locale());
            $pubDetails = array(
                '#id#' => $id,
                '#author#' => $authors_html,
                '#title#' => $title,
                '#url#' => Tools::get_item_url("publications", $title, $id, $post->ID, $lang),
                '#city#' => (array_key_exists('cfcitytown', $publication) ? strip_tags($publication['cfcitytown']) : __('O.O.', 'fau-cris')),
                '#publisher#' => (array_key_exists('publisher', $publication) ? strip_tags($publication['publisher']) : __('O.A.', 'fau-cris')),
                '#year#' => (array_key_exists('publyear', $publication) ? strip_tags($publication['publyear']) : __('O.J.', 'fau-cris')),
                '#pubType#' => $pubType,
                '#pubStatus#' => (array_key_exists('publstatus', $publication) ? strip_tags($publication['publstatus']) : ''),
                '#pagesTotal#' => (array_key_exists('cftotalpages', $publication) ? strip_tags($publication['cftotalpages']) : ''),
                '#pagesRange#' => (array_key_exists('pagesrange', $publication) ? strip_tags($publication['pagesrange']) : ''),
                '#lexiconColumn#' => (array_key_exists('lexiconcolumn', $publication) ? strip_tags($publication['lexiconcolumn']) : ''),
                '#volume#' => (array_key_exists('cfvol', $publication) ? strip_tags($publication['cfvol']) : __('O.A.', 'fau-cris')),
                '#series#' => (array_key_exists('cfseries', $publication) ? strip_tags($publication['cfseries']) : __('O.A.', 'fau-cris')),
                '#seriesNumber#' => !empty($publication['book volume']) ? strip_tags($publication['book volume']) : '',
                '#ISBN#' => (array_key_exists('cfisbn', $publication) ? strip_tags($publication['cfisbn']) : __('O.A.', 'fau-cris')),
                '#ISSN#' => (array_key_exists('cfissn', $publication) ? strip_tags($publication['cfissn']) : __('O.A.', 'fau-cris')),
                '#DOI#' => (array_key_exists('doi', $publication) ? strip_tags($publication['doi']) : __('O.A.', 'fau-cris')),
                '#URI#' => (array_key_exists('cfuri', $publication) ? strip_tags($publication['cfuri']) : __('O.A.', 'fau-cris')),
                '#editors#' => (array_key_exists('editor', $publication) ? strip_tags($publication['editor']) : __('O.A.', 'fau-cris')),
                '#bookTitle#' => (array_key_exists('edited volumes', $publication) ? strip_tags($publication['edited volumes']) : __('O.A.', 'fau-cris')), // Titel des Sammelbands
                '#journalTitle#' => (array_key_exists('journalname', $publication) ? strip_tags($publication['journalname']) : __('O.A.', 'fau-cris')),
                '#eventTitle#' => (array_key_exists('event title', $publication) ? strip_tags($publication['event title']) : ''),
                '#eventLocation#' => (array_key_exists('event location', $publication) ? strip_tags($publication['event location']) : ''),
                '#eventstart_raw#' => !empty($publication['event start date']) ? $publication['event start date'] : (!empty($publication['publyear']) ? $publication['publyear'] : '-----'),
                '#eventend_raw#' => (!empty($publication['event end date']) ? $publication['event end date'] : ''),
                '#eventStart#' => !empty($publication['event start date']) ? date_i18n(get_option('date_format'), strtotime(strip_tags($publication['event start date']))) : '',
                '#eventEnd#' => (!empty($publication['event end date']) ? date_i18n(get_option('date_format'), strtotime(strip_tags($publication['event end date']))) : ''),
                '#originalTitle#' => (array_key_exists('originaltitel', $publication) ? strip_tags($publication['originaltitel']) : __('O.A.', 'fau-cris')),
                '#language#' => (array_key_exists('language', $publication) ? strip_tags($publication['language']) : __('O.A.', 'fau-cris')),
                '#bibtexLink#' => '<a href="' . sprintf($this->bibtexlink, $id) . '">Download</a>',
                '#subtype#' => (array_key_exists('subtype', $publication) ? $publication['subtype'] : ''),
                '#articleNumber#' => (array_key_exists('article number', $publication) ? $publication['article number'] : ''),
                '#projectTitle#' => '',
                '#projectLink#' => '',
                '#oaIcon#' => ($publication['openaccess'] == "Ja") ? "<span aria-hidden class=\"oa-icon\" title=\"Open-Access-Publikation\"></span>" : '',
            );
            if (strpos($custom_text, '#projectTitle#') !== false) {
                $pubDetails['#projectTitle#'] = $this->get_pub_projects($id, 'title');
            }
            if (strpos($custom_text, '#projectLink#') !== false) {
                $pubDetails['#projectLink#'] = $this->get_pub_projects($id, 'link');
            }
            $pubDetails['#image1#'] = '';
            if (strpos($custom_text, '#image#') !== false) {
                $imgs = self::get_pub_images($publication['ID']);
                $pubDetails['#image#'] = '';
                $imgs = self::get_pub_images($id);
                if (count($imgs)) {
                    foreach ($imgs as $img) {
                        if (isset($img->attributes['png180']) && mb_strlen($img->attributes['png180']) > 30) {
                            $pubDetails['#image#'] = "<div class=\"cris-image wp-caption " . $image_align  . "\">";
                            $img_description = (isset($img->attributes['description']) ? "<p class=\"wp-caption-text\">" . $img->attributes['description'] . "</p>" : '');
                            $pubDetails['#image#'] .= "<img alt=\"" . $img_description . "\" src=\"data:image/PNG;base64," . $img->attributes['png180'] . "\" width=\"\" height=\"\">" . $img_description;
                            $pubDetails['#image#'] .= "</div>";
                        }
                    }
                }
            }

           // Render item
             if ($hasItems) {
                $publist .= "<li>";
                }
                $publist .= strtr($custom_text, $pubDetails);
                if ($hasItems) {
                    $publist .= "</li>";
                }
        }
        if ($hasItems) {
            $publist .= "</div>";
        } else {
            $publist .= "</{$listTag}>";
        }
        return $publist;
    }
    //  End::make_custom_list


    /**
     * Name : get_pub_projects
     *
     * Use: get all projects of publications
     *
     * Returns: publications project array
     *
     * Start::get_pub_projects
     */
    private function get_pub_projects($pub = null, $item = 'title')
    {

        $liste = new Projekte();
        if (is_wp_error($liste)) {
            return $liste->get_error_message();
        } else {
            $projects = $liste->pubProj($pub);
            return $projects[$item];
        }
    }

    //  End::get_pub_projects

    /**
     * Name : get_pub_images
     *
     * Use: get all images of publications
     *
     * Returns: publications images array
     *
     * Start::get_pub_images
     */
    private function get_pub_images($pub): array
    {
        $images = array();
        $imgString = Dicts::$base_uri . "getrelated/Publication/" . $pub . "/PUBL_has_PICT";
        $imgXml = Tools::XML2obj($imgString);

        if (!is_wp_error($imgXml) && isset($imgXml['size']) && $imgXml['size'] != 0) {
            foreach ($imgXml as $img) {
                $_i = new CRIS_pub_image($img);
                $images[$_i->ID] = $_i;
            }
        }
        return $images;
    }
}

//End::get_pub_images

class CRIS_publications extends Webservice
{
    /*
     * publication requests, supports multiple organisation ids given as array.
     */

    public function by_orga_id($orgaID = null, &$filter = null): array
    {
        if ($orgaID === null || $orgaID === "0" || $orgaID === "") {
            return new \WP_Error(
                'cris-orgid-error',
                __('Bitte geben Sie die CRIS-ID der Organisation, Person oder des Projektes an.', 'fau-cris')
            );
        }

        if (!is_array($orgaID)) {
            $orgaID = array($orgaID);
        }

        $requests = array();
        foreach ($orgaID as $_o) {
            $requests = array_merge($requests, array(
                sprintf("getautorelated/Organisation/%d/ORGA_2_PUBL_1", $_o),
                sprintf("getrelated/Organisation/%d/Publ_has_ORGA", $_o),
            ));
        }
        return $this->retrieve($requests, $filter);
    }

    public function by_pers_id($persID = null, &$filter = null, $notable = 0): array
    {
        if ($persID === null || $persID === "0") {
            return new \WP_Error(
                'cris-orgid-error',
                __('Bitte geben Sie die CRIS-ID der Organisation, Person oder des Projektes an.', 'fau-cris')
            );
        }

        if (!is_array($persID)) {
            $persID = array($persID);
        }

        $requests = array();
        if ($notable == 1) {
            foreach ($persID as $_p) {
                $requests[] = sprintf('getrelated/Person/%s/PUBL_has_PERS', $_p);
            }
        } else {
            foreach ($persID as $_p) {
                $requests[] = sprintf('getautorelated/Person/%s/PERS_2_PUBL_1', $_p);
            }
        }
        return $this->retrieve($requests, $filter);
    }

    public function by_id($publID = null): array
    {
        if ($publID === null || $publID === "0") {
            return new \WP_Error(
                'cris-orgid-error',
                __('Bitte geben Sie die CRIS-ID der Organisation, Person oder des Projektes an.', 'fau-cris')
            );
        }

        if (!is_array($publID)) {
            $publID = array($publID);
        }

        $requests = array();
        foreach ($publID as $_p) {
            $requests[] = sprintf('get/Publication/%d', $_p);
        }
        return $this->retrieve($requests);
    }

    public function by_project($projID = null, &$filter = null): array
    {
        if ($projID === null || $projID === "0") {
            return new \WP_Error(
                'cris-orgid-error',
                __('Bitte geben Sie die CRIS-ID der Organisation, Person oder des Projektes an.', 'fau-cris')
            );
        }

        if (!is_array($projID)) {
            $projID = array($projID);
        }

        $requests = array();
        foreach ($projID as $_p) {
            $requests[] = sprintf('getrelated/Project/%d/proj_has_publ', $_p);
        }
        return $this->retrieve($requests, $filter);
    }

    public function by_field($fieldID = null, &$filter = null, $fsp = false, $entity = 'field'): array
    {
        if ($fieldID === null || $fieldID === "0") {
            return new \WP_Error(
                'cris-orgid-error',
                __('Bitte geben Sie die CRIS-ID der Organisation, Person oder des Projektes an.', 'fau-cris')
            );
        }

        if (!is_array($fieldID)) {
            $fieldID = array($fieldID);
        }

        $requests = array();
        $retaions = array();
        switch ($entity) {
            case 'field_proj':
                $relations[] = $fsp ? 'fsp_proj_publ' : 'fobe_proj_publ';
                break;
            case 'field_notable':
                $relations[] = 'FOBE_has_cur_PUBL';
                break;
            case 'field_incl_proj':
                $relations[] = $fsp ? 'FOBE_FSP_has_PUBL' : 'fobe_has_top_publ';
                $relations[] = $fsp ? 'fsp_proj_publ' : 'fobe_proj_publ'; 
                break;
            case 'field':
            default:
                $relations[] = $fsp ? 'FOBE_FSP_has_PUBL' : 'fobe_has_top_publ';
        }   
        foreach ($fieldID as $_p) {
            foreach ($relations as $_r) {
                $requests[] =sprintf( 'getrelated/Forschungsbereich/%d/', $_p ) . $_r;
            }
    }
    $publs=$this->retrieve($requests, $filter);
    
    return $publs;
    }

    public function by_equipment($equiID = null, &$filter = null): array
    {
        if ($equiID === null || $equiID === "0") {
            return new \WP_Error(
                'cris-orgid-error',
                __('Bitte geben Sie die CRIS-ID der Organisation, Person oder des Projektes an.', 'fau-cris')
            );
        }

        if (!is_array($equiID)) {
            $equiID = array($equiID);
        }

        $requests = array();
        foreach ($equiID as $_p) {
            $requests[] = sprintf('getrelated/equipment/%d/PUBL_has_EQUI', $_p);
        }
        return $this->retrieve($requests, $filter);
    }


    private function retrieve($reqs, &$filter = null): array
    {
        if ($filter !== null && !$filter instanceof Filter) {
            $filter = new Filter($filter);
        }
        $data = array();
        foreach ($reqs as $_i) {
            $_data = $this->get($_i, $filter);
            if (!is_wp_error($_data)) {
                $data[] = $_data;
            }
        }

        $publs = array();

        foreach ($data as $_d) {
            foreach ($_d as $publ) {
                $p = new CRIS_publication($publ);
                if ($p->ID && ($filter === null || $filter->evaluate($p))) {
                    $publs[$p->ID] = $p;
                }
            }
        }
        return $publs;
    }
}

class CRIS_publication extends CRIS_Entity
{
    /*
     * object for single publication
     */

    public function __construct($data)
    {
        parent::__construct($data);
    }

    public function insert_quotation_links(): void
    {
        /*
         * Enrich APA/MLA quotation by links to publication details (CRIS
         * website) and DOI (if present, applies only to APA).
         */

        $doilink = preg_quote("https://doi.org/", "/");
        $doilinkdx = preg_quote("https://dx.doi.org/", "/");

        $title = preg_quote(Tools::numeric_xml_encode($this->attributes["cftitle"]), "/");

        $cristmpl = '<a href="' . FAU_CRIS::cris_publicweb . 'publications/%d" target="_blank">%s</a>';

        $apa = $this->attributes["quotationapa"];
        $mla = $this->attributes["quotationmla"];

        $matches = array();
        $matchesdx = array();

        $splitapa = preg_match("/^(.+)(" . $title . ")(.+)(" . $doilink . ".+)?$/Uu", $apa, $matches);
        $splitapadx = preg_match("/^(.+)(" . $title . ")(.+)(" . $doilinkdx . ".+)?$/Uu", $apa, $matchesdx);

        // use old format if present
        if (count($matchesdx) > count($matches))
                $matches = $matchesdx;

        if ($splitapa === 1 && isset($matches[2])) {
            $apalink = $matches[1] . sprintf($cristmpl, $this->ID, $matches[2]) . $matches[3];
            if (isset($matches[4])) {
                $apalink .= sprintf('<a href="%s" target="_blank">%s</a>', $matches[4], $matches[4]);
            }
        } else {
            // try to identify DOI at least
            $splitapa = preg_match("/^(.+)(" . $doilink . ".+)?$/Uu", $apa, $matches);
            if ($splitapa === 1 && isset($matches[2])) {
                $apalink = $matches[1] . sprintf('<a href="%s" target="_blank">%s</a>', $matches[2], $matches[2]);
            } else {
                $apalink = $apa;
            }
        }

        $this->attributes["quotationapalink"] = $apalink;

        $matches = array();
        $splitmla = preg_match("/^(.+)(" . $title . ")(.+)$/", $mla, $matches);

        if ($splitmla === 1) {
            $mlalink = $matches[1] . sprintf($cristmpl, $this->ID, $matches[2]) . $matches[3];
        } else {
            $mlalink = $mla;
        }

        $this->attributes["quotationmlalink"] = $mlalink;
    }
}

class CRIS_pub_image extends CRIS_Entity
{
    /*
     * object for single publication
     */

    public function __construct($data)
    {
        parent::__construct($data);

        foreach ($data->relation as $_r) {
            if ($_r['type'] != "PUBL_has_PICT") {
                continue;
            }
            foreach ($_r->attribute as $_a) {
                if ($_a['name'] == 'description') {
                    $this->attributes["description"] = (string) $_a->data;
                }
            }
        }
    }
}

# tests possible if called on command-line
// if (!debug_backtrace()) {
//     $p = new CRIS_Publications();
//     // default uses the cache automatically
//     // $p->disable_cache();
//     $f = new Filter(array("publyear__le" => 2016, "publyear__gt" => 2014, "peerreviewed__eq" => "Yes"));
//     $publs = $p->by_orga_id("142285", $f);
//     $order = "virtualdate";
//     $formatter = new Formatter(null, null, $order, SORT_DESC);
//     $res = $formatter->execute($publs);
//     foreach ($res[$order] as $key => $value) {
//         // Escape the key and the value before printing them
//             echo sprintf(
//                 "%s: %s\n", 
//                 esc_html($key), 
//                 esc_html($value->attributes[$order])
//             );
//         }
// }
