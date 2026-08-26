<?php

namespace RRZE\Cris\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

/**
 * Base test case: sets up Brain Monkey and an in-memory transient/option store
 * so the cache-related classes can be exercised without a WordPress runtime.
 */
abstract class CrisTestCase extends TestCase
{
    /** @var array<string,mixed> In-memory transient store (keyed by transient name). */
    protected array $transients = [];

    /** @var array<string,mixed> In-memory option store. */
    protected array $options = [];

    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();

        $this->transients = [];
        $this->options = [];

        $t = &$this->transients;
        Functions\when('get_transient')->alias(function ($key) use (&$t) {
            return array_key_exists($key, $t) ? $t[$key] : false;
        });
        Functions\when('set_transient')->alias(function ($key, $value, $ttl = 0) use (&$t) {
            $t[$key] = $value;
            return true;
        });
        Functions\when('delete_transient')->alias(function ($key) use (&$t) {
            if (array_key_exists($key, $t)) {
                unset($t[$key]);
                return true;
            }
            return false;
        });

        $o = &$this->options;
        Functions\when('get_option')->alias(function ($key, $default = false) use (&$o) {
            return array_key_exists($key, $o) ? $o[$key] : $default;
        });
        Functions\when('update_option')->alias(function ($key, $value, $autoload = null) use (&$o) {
            $o[$key] = $value;
            return true;
        });
        Functions\when('delete_option')->alias(function ($key) use (&$o) {
            if (array_key_exists($key, $o)) {
                unset($o[$key]);
                return true;
            }
            return false;
        });

        Functions\when('wp_parse_url')->alias(function ($url, $component = -1) {
            return $component === -1 ? parse_url($url) : parse_url($url, $component);
        });

        // Default generation token; individual tests can override with a
        // sequence to exercise generation rotation.
        Functions\when('wp_generate_uuid4')->justReturn('aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee');

        // Reset the Cache class's per-request generation memo so static state
        // does not leak between tests.
        $memo = new \ReflectionProperty(\RRZE\Cris\Cache::class, 'generation');
        $memo->setAccessible(true);
        $memo->setValue(null, null);
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }
}
