<?php

namespace RRZE\Cris\Tests;

use Brain\Monkey\Functions;
use RRZE\Cris\Cache;

/**
 * @covers \RRZE\Cris\Cache
 */
class CacheTest extends CrisTestCase
{
    private string $base = 'https://cris.fau.de/ws-cached/1.0/public/infoobject/123';

    /** Install a minimal $wpdb whose get_col() returns the given option names. */
    private function mockWpdb(array $optionNames): void
    {
        $GLOBALS['wpdb'] = new class($optionNames) {
            public string $options = 'wp_options';
            /** @var string[] */
            private array $names;
            public function __construct(array $names)
            {
                $this->names = $names;
            }
            public function esc_like($text)
            {
                return $text;
            }
            public function prepare($query, ...$args)
            {
                return $query;
            }
            public function get_col($query)
            {
                return $this->names;
            }
        };
    }

    /**
     * A forced (?flag=seednow) request must read the SAME transient as the
     * normal request — no duplicate "seednow" twin is created.
     */
    public function test_seednow_url_maps_to_same_key_as_normal_url(): void
    {
        Cache::set($this->base, 'DATA');

        $this->assertSame('DATA', Cache::get($this->base . '?flag=seednow'));
        $this->assertCount(1, $this->transients);
    }

    public function test_different_urls_use_different_keys(): void
    {
        Cache::set($this->base, 'A');

        $other = 'https://cris.fau.de/ws-cached/1.0/public/infoobject/999';
        $this->assertFalse(Cache::get($other));
    }

    /**
     * flush() rotates the generation, so a value stored under the old
     * generation is no longer reachable afterwards (covers object caches where
     * old rows cannot be enumerated and simply age out via TTL).
     */
    public function test_flush_rotates_generation_so_old_entries_are_unreachable(): void
    {
        $tokens = ['11111111-0000', '22222222-0000'];
        $i = 0;
        Functions\when('wp_generate_uuid4')->alias(function () use (&$i, $tokens) {
            return $tokens[$i++] ?? ('zzzz-' . $i);
        });
        $this->mockWpdb([]);

        Cache::set($this->base, 'A');
        $this->assertSame('A', Cache::get($this->base));

        Cache::flush();

        $this->assertFalse(Cache::get($this->base), 'Old generation must be unreachable after flush');
    }

    /**
     * flush() must sweep BOTH the data and timeout rows of CRIS transients,
     * including an orphaned timeout row, without touching foreign transients.
     */
    public function test_flush_sweeps_data_and_timeout_rows_including_orphans(): void
    {
        $this->options['_transient_cris_g_abc'] = 'A';
        $this->options['_transient_timeout_cris_g_abc'] = 9999999999;
        $this->options['_transient_timeout_cris_g_orphan'] = 9999999999; // orphan
        $this->options['_transient_other_plugin'] = 'keep';

        $this->mockWpdb([
            '_transient_cris_g_abc',
            '_transient_timeout_cris_g_abc',
            '_transient_timeout_cris_g_orphan',
        ]);

        $count = Cache::flush();

        $this->assertSame(3, $count);
        $this->assertArrayNotHasKey('_transient_cris_g_abc', $this->options);
        $this->assertArrayNotHasKey('_transient_timeout_cris_g_abc', $this->options);
        $this->assertArrayNotHasKey('_transient_timeout_cris_g_orphan', $this->options);
        $this->assertArrayHasKey('_transient_other_plugin', $this->options);
    }
}
