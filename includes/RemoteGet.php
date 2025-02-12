<?php

namespace RRZE\Cris;

defined('ABSPATH') || exit;

class RemoteGet
{
    private static array $defaultArgs = [
        'timeout' => 5,
        'sslverify' => true,
        'method' => 'GET',
        'validate' => 'xml'
    ];

    public static function retrieveContent(string $url, array $args = [], int $code = 200, bool $safe = true)
    {
        $args = wp_parse_args($args, self::$defaultArgs);
        $args = array_intersect_key($args, self::$defaultArgs);

        $content = Cache::get($url);
        if ($content === false) {
            $response = self::remoteGet($url, $args, $safe);

            if (is_wp_error($response)) {
                do_action(
                    'rrze.log.error',
                    'Plugin: {plugin} WP-Error: {wp-error}',
                    [
                        'plugin' => 'fau-cris',
                        'wp-error' => $response->get_error_message(),
                        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_debug_backtrace
                        'method' => debug_backtrace()[1]['function'] ?? '',
                        'url' => $url
                    ]
                );
                $response = [];
            } elseif (wp_remote_retrieve_response_code($response) != $code) {
                $response = [];
            }
            $content = $response['body'] ?? '';
            switch ($args['validate']) {
                case 'xml':
                    if ($content && is_wp_error(XML::isXML($content))) {
                        $content = '';
                    }
                    break;
                default:
                    //
            }
            if ($content) {
                Cache::set($url, $content);
            }
        }

        return $content;
    }

    private static function remoteGet(string $url, array $args, bool $safe)
    {
        if ($safe) {
            return wp_safe_remote_get($url, $args);
        } else {
            return wp_remote_get($url, $args);
        }
    }
}
