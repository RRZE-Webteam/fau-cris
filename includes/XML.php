<?php

namespace RRZE\Cris;

defined('ABSPATH') || exit;

class XML
{
    public static function element(string $xml = ''): bool|\WP_Error|\SimpleXMLElement {
        $error = self::isXML($xml);
        if (is_wp_error($error)) {
            return $error;
        }
        return new \SimpleXMLElement($xml);
    }

    public static function isXML(string $xml = ''): bool|\WP_Error {
        $xml = $xml ?: '<>';

        libxml_use_internal_errors(true);

        $doc = new \DOMDocument('1.0', 'utf-8');
        $doc->loadXML($xml);

        $errors = libxml_get_errors();

        if (empty($errors)) {
            return true;
        }

        $error = $errors[0];
        if ($error->level < 3) {
            return true;
        }

        $explodedxml = explode('r', $xml);
        $badxml = $explodedxml[($error->line) - 1];
        $message = $error->message . ' at line ' . $error->line . '. Invalid XML: ' . htmlentities($badxml);

        // if (defined('WP_DEBUG') && WP_DEBUG) {
        //     error_log($message);
        // }

        return new \WP_Error('cris-xml-rror', $message);
    }
}
