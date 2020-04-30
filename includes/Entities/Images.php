<?php

namespace FAU\CRIS\Entities;

use FAU\CRIS\Entity;
use const FAU\CRIS\WS_REQUESTS;
use const FAU\CRIS\WS_URL;

require_once(plugin_dir_path(__DIR__). '/Tools.php');

class Images {

    public function __construct($parameter) {
        $this->parameter = $parameter;
    }

    public function get_images($entity, $id, $image_align = 'right') {
        $images = [];
        $imgdata = $this->get_data($entity, $id);
        if (count($imgdata)) {
            foreach($imgdata as $img) {
                $image = '';
                $img_size = getimagesizefromstring (base64_decode($img->attributes['png180']));
                $image .= "<div class=\"cris-image wp-caption $image_align\" style=\"width: $img_size[0]px;\">";
                $img_description = (isset($img->attributes['description']) ? $img->attributes['description'] : '');
                if (isset($img->attributes['png180']) && mb_strlen($img->attributes['png180']) > 30) {
                    $image .= "<img alt=\"". $img_description ."\" src=\"data:image/PNG;base64," . $img->attributes['png180'] . "\" " . $img_size[3].">"
                        . "<p class=\"wp-caption-text\">" . $img_description . "</p>";
                }
                $image .= "</div>";
                $images[] = $image;
            }
        }
        return $images;
    }

    private function get_data($entity, $id) {
        $images = array();
        $imgString = sprintf(WS_URL . WS_REQUESTS['images'][$entity], $id);
        $imgXml = \FAU\CRIS\XML2obj($imgString);
        if ($imgXml['size'] != 0) {
            foreach ($imgXml as $img) {
                $_i = new Image($img);
                $images[$_i->ID] = $_i;
            }
        }
        return $images;
    }
}

class Image extends Entity {
    /*
     * object for single image
     */

    public function __construct($data) {
        parent::__construct($data);

        foreach ($data->relation as $_r) {
            if (substr($_r['type'], -9) != 'has_PICT')
                continue;
            foreach($_r->attribute as $_a) {
                if ($_a['name'] == 'description') {
                    $this->attributes["description"] = (string) $_a->data;
                }
            }
        }
    }
}