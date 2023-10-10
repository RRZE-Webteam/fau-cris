<?php

namespace RRZE\Cris;

defined('ABSPATH') || exit;

class Cache
{
    private static int|float $ttl = 6 * HOUR_IN_SECONDS;

    public static function set(string $url, string $ical): void {
        $prefix = parse_url($url, PHP_URL_SCHEME);
        $key = (strpos($url, $prefix) === 0) ? substr($url, strlen($prefix)) : $url;
        $cacheOption = 'cris_' . md5($key);
        set_transient($cacheOption, $ical, self::$ttl);
    }

    public static function get(string $url)
    {
        $prefix = parse_url($url, PHP_URL_SCHEME);
        $key = (strpos($url, $prefix) === 0) ? substr($url, strlen($prefix)) : $url;
        $cacheOption = 'cris_' . md5($key);
        return get_transient($cacheOption);
    }

    public static function delete(string $url): bool
    {
        $prefix = parse_url($url, PHP_URL_SCHEME);
        $key = (strpos($url, $prefix) === 0) ? substr($url, strlen($prefix)) : $url;
        $cacheOption = 'cris_' . md5($key);
        return delete_transient($cacheOption);
    }
}
