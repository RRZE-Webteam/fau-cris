<?php

namespace RRZE\Cris;

defined('ABSPATH') || exit;

class Cache
{
    public static function set(string $url, string $ical)
    {
        $prefix = parse_url($url, PHP_URL_SCHEME);
        $key = (strpos($url, $prefix) === 0) ? substr($url, strlen($prefix)) : $url;
        $cacheOption = 'cris_' . md5($key);
        $ttl = HOUR_IN_SECONDS;
        if (is_multisite()) {
            set_site_transient($cacheOption, $ical, $ttl);
        } else {
            set_transient($cacheOption, $ical, $ttl);
        }
    }

    public static function get(string $url)
    {
        $prefix = parse_url($url, PHP_URL_SCHEME);
        $key = (strpos($url, $prefix) === 0) ? substr($url, strlen($prefix)) : $url;
        $cacheOption = 'cris_' . md5($key);
        if (is_multisite()) {
            $ical = get_site_transient($cacheOption);
        } else {
            $ical = get_transient($cacheOption);
        }
        return $ical;
    }

    public static function delete(string $url): bool
    {
        $prefix = parse_url($url, PHP_URL_SCHEME);
        $key = (strpos($url, $prefix) === 0) ? substr($url, strlen($prefix)) : $url;
        $cacheOption = 'cris_' . md5($key);
        if (is_multisite()) {
            $return = delete_site_transient($cacheOption);
        } else {
            $return = delete_transient($cacheOption);
        }
        return $return;
    }
}
