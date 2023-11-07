<?php
namespace RRZE\Cris;
use RRZE\Cris\Tools;
use RRZE\Cris\Webservice;
use RRZE\Cris\Filter;
use RRZE\Cris\Formatter;
//require_once( "Tools.php" );
//require_once( "Webservice.php" );
//require_once( "Filter.php" );
//require_once( "Formatter.php" );

class Organisation
{

    private array $options;
    public $output;

    public function __construct($einheit = 'orga', $id = '', $page_lang = 'de', $sc_lang = 'de')
    {

        if (strpos($_SERVER['PHP_SELF'], "vkdaten/tools/")) {
            $this->cms = 'wbk';
            $this->options = CRIS::ladeConf();
        } else {
            $this->cms = 'wp';
            $this->options = (array) FAU_CRIS::get_options();
        }
        $this->orgNr = $this->options['cris_org_nr'];

        if ((!$this->orgNr || $this->orgNr == 0) && $id == '') {
            $this->error = new \WP_Error(
                'cris-orgid-error',
                __('Bitte geben Sie die CRIS-ID der Organisation an.', 'fau-cris')
            );
        }

        $this->id = ($id != '' ? $id : $this->orgNr);
        $this->einheit = "orga";
        $this->page_lang = $page_lang;
        $this->sc_lang = $sc_lang;
        $this->langdiv_open = '<div class="cris">';
        $this->langdiv_close = '</div>';
        if ($sc_lang != $this->page_lang) {
            $this->langdiv_open = '<div class="cris" lang="' . $sc_lang . '">';
        }
    }



    public function singleOrganisation($hide = '', $image_align = 'alignright')
    {
        $ws = new CRIS_organisations();
        try {
            $orgaArray = $ws->by_id($this->id);
        } catch (Exception $ex) {
            return;
        }

        if (!count($orgaArray)) {
            $output = '<p>' . __('Es wurden leider keine Informationen gefunden.', 'fau-cris') . '</p>';
            return $output;
        }
        $output = $this->make_single($orgaArray, $hide, $image_align);

        return $this->langdiv_open . $output . $this->langdiv_close;
    }

    /*
     * Ausgabe eines Organisation per Custom-Shortcode
     */

    public function customOrganisation($content = '', $image_align = 'alignright')
    {
        $ws = new CRIS_organisations();
        try {
            $orgaArray = $ws->by_id($this->id);
        } catch (Exception $ex) {
            return;
        }

        if (!count($orgaArray)) {
            $output = '<p>' . __('Es wurden leider keine Projekte gefunden.', 'fau-cris') . '</p>';
            return $output;
        }
        $output = $this->make_custom_single($orgaArray, $content, $image_align);
        return $this->langdiv_open . $output . $this->langdiv_close;
    }

    public function researchContacts($seed = false)
    {
        $ws = new CRIS_organisations();
        if ($seed) {
            $ws->disable_cache();
        }
        try {
            $orgaArray = $ws->by_id($this->id);
        } catch (Exception $ex) {
            return;
        }
        if (!count($orgaArray)) {
            $output = '<p>' . __('Es wurden leider keine Informationen gefunden.', 'fau-cris') . '</p>';
            return $output;
        }
        $research_contacts = array();
        foreach ($orgaArray as $organisation) {
            $organisation = (array) $organisation;
            foreach ($organisation['attributes'] as $attribut => $v) {
                $organisation[$attribut] = $v;
            }
            unset($organisation['attributes']);
            $contacts = explode('|', $organisation['research_contact_names']);
            foreach ($contacts as $_c) {
                $nameparts = explode(':', $_c);
                $lastname = $nameparts[0];
                $firstname = array_key_exists(1, $nameparts) ? $nameparts[1] : '';
                $cid = Tools::person_exists($this->cms, $firstname, $lastname);
                if ($cid) {
                    $research_contacts[] = $cid;
                }
            }
        }
        return $research_contacts;
    }

    /* =========================================================================
     * Private Functions
      ======================================================================== */

    /*
     * Holt Daten vom Webservice je nach definierter Einheit.
     */

    private function fetch_organisation(): array
    {

        $ws = new CRIS_organisations();
        $orgaArray = array();

        try {
            $orgaArray = $ws->by_id($this->id);
        } catch (Exception $ex) {
            $awardArray = array();
        }
        return $awardArray;
    }

    /*
     * Ausgabe der Organisation
     */

    private function make_single($organisations, $image_align): string
    {
        $image_align = 'alignright';
        $output      = "<div class=\"cris-organisation\">";

        foreach ($organisations as $organisation) {
            $organisation = (array) $organisation;
            foreach ($organisation['attributes'] as $attribut => $v) {
                $organisation[$attribut] = $v;
            }
            unset($organisation['attributes']);
            $research_imgs = self::get_research_images($organisation['ID']);

            if (count($research_imgs)) {
                $output .= "<div class=\"cris-image wp-caption " . $image_align .  "\">";
                foreach ($research_imgs as $img) {
                    if (isset($img->attributes['png180']) && mb_strlen($img->attributes['png180']) > 30) {
                        $img_description = (isset($img->attributes['description']) ? "<p class=\"wp-caption-text\">" . $img->attributes['description'] . "</p>" : '');
                        $output .= "<img alt=\"\" src=\"data:image/PNG;base64," . $img->attributes['png180'] . "\" width=\"\" height=\"\">" . $img_description;
                    }
                }
                $output .= "</div>";
            }
            if (!empty($organisation['research_desc']) || !empty($organisation['research_desc_en'])) {
                $research = ($this->page_lang == 'en' && !empty($organisation['research_desc_en'])) ? $organisation['research_desc_en'] : $organisation['research_desc'];
                $output .= "<p class=\"cris-research\">" . $research . "</p>";
            }
        }

        $output .= "</div>";
        return $output;
    }

    private function make_custom_single($organisations, $custom_text, $image_align = 'alignright'): string
    {
        $output = "<div class=\"cris-organisation\">";

        foreach ($organisations as $organisation) {
            $organisation = (array) $organisation;
            foreach ($organisation['attributes'] as $attribut => $v) {
                $organisation[$attribut] = $v;
            }
            unset($organisation['attributes']);
            $details['#image1#'] = '';
            $details['#images#'] = '';
            if (strpos($custom_text, '#image') !== false) {
                $imgs = self::get_research_images($organisation['ID']);
                if (count($imgs)) {
                    $i = 1;
                    foreach ($imgs as $img) {
                        if (isset($img->attributes['png180']) && mb_strlen($img->attributes['png180']) > 30) {
                            $img_description = (isset($img->attributes['description']) ? "<p class=\"wp-caption-text\">" . $img->attributes['description'] . "</p>" : '');
                            $details['#image' . $i . '#'] = "<div class=\"cris-image wp-caption " . $image_align .  "\">" . "<img alt=\"\" src=\"data:image/PNG;base64," . $img->attributes['png180'] . "\" width=\"\" height=\"\">" . $img_description . "</div>";
                            $details['#images#'] .= $details['#image' . $i . '#'];
                        }
                        $i++;
                    }
                }
            }
            $details['#image#'] = $details['#image1#'];
            $details['#description#'] = '';
            if (!empty($organisation['research_desc']) || !empty($organisation['research_desc_en'])) {
                $research = ($this->page_lang == 'en' && !empty($organisation['research_desc_en'])) ? $organisation['research_desc_en'] : $organisation['research_desc'];
                $details['#description#'] .= "<p class=\"cris-research\">" . $research . "</p>";
            }
            $output .= strtr($custom_text, $details);
        }
        $output .= "</div>";
        return $output;
    }

    private function get_research_images($orga): array
    {
        $images = array();
        //$imgString = Dicts::$base_uri . "getrelated/Organisation/" . $orga . "/ORGA_has_PICT";
        $imgString = Dicts::$base_uri . "getrelated/Organisation/" . $orga . "/ORGA_has_research_PICT";
        $imgXml = Tools::XML2obj($imgString);

        if (!is_wp_error($imgXml) && isset($imgXml['size']) && $imgXml['size'] != 0) {
            foreach ($imgXml as $img) {
                $_i = new CRIS_research_image($img);
                $images[$_i->ID] = $_i;
            }
        }
        return $images;
    }
}

class CRIS_organisations extends Webservice
{
    /*
     * projects requests
     */

    public function by_id($orgaID = null): array
    {
        if ($orgaID === null || $orgaID === "0") {
	       return new \WP_Error(
		        'cris-orgid-error',
		        __('Bitte geben Sie die CRIS-ID der Organisation an.', 'fau-cris')
	        );
        }

        if (!is_array($orgaID)) {
            $orgaID = array($orgaID);
        }

        $requests = array();
        foreach ($orgaID as $_o) {
            $requests[] = sprintf('get/Organisation/%d', $_o);
        }
        return $this->retrieve($requests);
    }

    private function retrieve($reqs, &$filter = null): array
    {
        if ($filter !== null && !$filter instanceof CRIS_filter) {
            $filter = new CRIS_filter($filter);
        }

        $data = array();
        foreach ($reqs as $_i) {
            $_data = $this->get($_i, $filter);
            if (!is_wp_error($_data)) {
                $data[] = $_data;
            }
        }

        $organisations = array();

        foreach ($data as $_d) {
            foreach ($_d as $organisation) {
                $a = new CRIS_organisation($organisation);
                if ($a->ID && ($filter === null || $filter->evaluate($a))) {
                    $organisations[$a->ID] = $a;
                }
            }
        }

        return $organisations;
    }
}

class CRIS_organisation extends CRIS_Entity
{
    /*
     * object for single award
     */

    public function __construct($data)
    {
        parent::__construct($data);
    }
}

class CRIS_research_image extends CRIS_Entity
{
    /*
     * object for single publication
     */

    public function __construct($data)
    {
        parent::__construct($data);

        foreach ($data->relation as $_r) {
            if ($_r['type'] != "ORGA_has_research_PICT") {
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
