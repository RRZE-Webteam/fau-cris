<?php

namespace RRZE\Cris;

defined('ABSPATH') || exit;

class RemoteGet
{
    private static $defaultArgs = [
        'timeout' => 5,
        'sslverify' => true,
        'method' => 'GET'
    ];

    public static function retrieveResponse(string $url, array $args = [], int $code = 200, bool $safe = true)
    {
        $args = wp_parse_args($args, self::$defaultArgs);
        $args = array_intersect_key($args, $args);

        if ($safe) {
            $response = self::getSafeResponse($url, $args);
        } else {
            $response = self::getResponse($url, $args);
        }
        if (is_wp_error($response)) {
            do_action(
                'rrze.log.error',
                'Plugin: {plugin} WP-Error: {wp-error}',
                [
                    'plugin' => 'fau-cris',
                    'wp-error' => $response->get_error_message(),
                    'method' => debug_backtrace()[1]['function'] ?? '',
                    'url' => $url
                ]
            );
        } elseif (wp_remote_retrieve_response_code($response) != $code) {
            $response = false;
        }

        return $response;
    }

    private static function getSafeResponse(string $url, array $args)
    {
        return wp_safe_remote_get($url, $args);
    }

    private static function getResponse(string $url, array $args)
    {
        return wp_remote_get($url, $args);
    }
}
