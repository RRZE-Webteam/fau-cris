<?php

namespace RRZE\Cris;

defined('ABSPATH') || exit;

class Cache
{
    private static int|float $ttl = 6 * HOUR_IN_SECONDS;

    /**
     * Option holding the current cache "generation" token. It is baked into
     * every transient key (cris_<generation>_<hash>). Invalidation just rotates
     * this token, which makes all previously stored entries unreachable at once
     * — atomically and without a per-key index, so there is no read-modify-write
     * race under concurrent requests, and persistent object caches are covered
     * too (stale entries simply age out via their TTL once unreferenced).
     */
    private const GEN_OPTION = '_fau_cris_cache_gen';

    /** Per-request memo of the generation token to avoid repeated option reads. */
    private static ?string $generation = null;

    public static function set(string $url, string $ical): void
    {
        set_transient(self::buildOption($url), $ical, self::$ttl);
    }

    public static function get(string $url)
    {
        return get_transient(self::buildOption($url));
    }

    public static function delete(string $url): bool
    {
        return delete_transient(self::buildOption($url));
    }

    /**
     * Invalidate every CRIS transient. Rotating the generation makes all current
     * entries unreachable immediately (also covering persistent object caches).
     * A DB sweep then actively reclaims the leftover rows on plain-DB installs,
     * matching BOTH the data and timeout prefixes so orphaned rows are removed
     * as well. Returns the number of option rows removed by the sweep.
     */
    public static function flush(): int
    {
        self::rotateGeneration();

        global $wpdb;
        $dataLike = $wpdb->esc_like('_transient_cris_') . '%';
        $timeoutLike = $wpdb->esc_like('_transient_timeout_cris_') . '%';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $optionNames = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT option_name FROM {$wpdb->options}
                 WHERE option_name LIKE %s OR option_name LIKE %s",
                $dataLike,
                $timeoutLike
            )
        );

        $count = 0;
        foreach ((array) $optionNames as $optionName) {
            if (str_starts_with($optionName, '_transient_timeout_')) {
                $key = substr($optionName, strlen('_transient_timeout_'));
            } else {
                $key = substr($optionName, strlen('_transient_'));
            }
            // delete_transient() invalidates the object-cache entry; the explicit
            // delete_option() calls remove any orphaned data/timeout rows that
            // delete_transient() would miss (e.g. a leftover timeout without its
            // data row).
            delete_transient($key);
            delete_option('_transient_' . $key);
            delete_option('_transient_timeout_' . $key);
            $count++;
        }

        return $count;
    }

    /**
     * Build the transient key for a request URL. The "flag" query parameter
     * (e.g. ?flag=seednow) is stripped so a forced/server-reseed request maps to
     * the same local key as the normal request and never creates a duplicate
     * transient. The scheme is ignored (http/https collapse to one key).
     */
    private static function buildOption(string $url): string
    {
        $parts = wp_parse_url($url);
        if (is_array($parts) && !empty($parts['host'])) {
            $query = '';
            if (!empty($parts['query'])) {
                parse_str($parts['query'], $q);
                unset($q['flag']);
                if (!empty($q)) {
                    ksort($q);
                    $query = '?' . http_build_query($q);
                }
            }
            $key = '://' . $parts['host'] . ($parts['path'] ?? '') . $query;
        } else {
            $key = $url;
        }
        return 'cris_' . self::generation() . '_' . md5($key);
    }

    private static function generation(): string
    {
        if (self::$generation !== null) {
            return self::$generation;
        }
        $gen = get_option(self::GEN_OPTION);
        if (!is_string($gen) || $gen === '') {
            $gen = self::newToken();
            update_option(self::GEN_OPTION, $gen, false);
        }
        self::$generation = $gen;
        return $gen;
    }

    private static function rotateGeneration(): void
    {
        $token = self::newToken();
        update_option(self::GEN_OPTION, $token, false);
        self::$generation = $token;
    }

    private static function newToken(): string
    {
        return substr(wp_generate_uuid4(), 0, 8);
    }
}
