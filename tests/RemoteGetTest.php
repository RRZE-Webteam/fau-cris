<?php

namespace RRZE\Cris\Tests;

use Brain\Monkey\Functions;
use RRZE\Cris\RemoteGet;

/**
 * @covers \RRZE\Cris\RemoteGet
 */
class RemoteGetTest extends CrisTestCase
{
    private string $url = 'https://cris.fau.de/ws-cached/1.0/public/infoobject/123';
    private int $httpCalls = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->httpCalls = 0;
        $calls = &$this->httpCalls;

        Functions\when('wp_parse_args')->alias(function ($args, $defaults) {
            return array_merge($defaults, (array) $args);
        });
        Functions\when('is_wp_error')->justReturn(false);
        Functions\when('wp_remote_retrieve_response_code')->justReturn(200);
        Functions\when('do_action')->justReturn(null);

        $response = ['body' => 'FRESH', 'response' => ['code' => 200]];
        Functions\when('wp_safe_remote_get')->alias(function () use (&$calls, $response) {
            $calls++;
            return $response;
        });
        Functions\when('wp_remote_get')->alias(function () use (&$calls, $response) {
            $calls++;
            return $response;
        });
    }

    /** validate=>skip avoids the XML dependency; content is returned verbatim. */
    private function args(): array
    {
        return ['validate' => 'skip'];
    }

    public function test_second_fetch_of_same_url_uses_cache(): void
    {
        $first = RemoteGet::retrieveContent($this->url, $this->args());
        $second = RemoteGet::retrieveContent($this->url, $this->args());

        $this->assertSame('FRESH', $first);
        $this->assertSame('FRESH', $second);
        $this->assertSame(1, $this->httpCalls, 'Second fetch must be served from cache');
    }

    public function test_force_refresh_bypasses_existing_transient(): void
    {
        RemoteGet::retrieveContent($this->url, $this->args());
        $this->assertSame(1, $this->httpCalls);

        $forced = RemoteGet::retrieveContent($this->url, $this->args(), 200, true, true);

        $this->assertSame('FRESH', $forced);
        $this->assertSame(2, $this->httpCalls, 'Force refresh must perform a new HTTP request');
    }

    /**
     * A force refresh issued against the ?flag=seednow URL (as Webservice
     * builds it) must not leave a permanently-stale separate transient: the
     * fresh response is stored under the normal key, so a later normal read
     * hits the refreshed cache and only one transient exists.
     */
    public function test_force_refresh_leaves_no_separate_seednow_entry(): void
    {
        RemoteGet::retrieveContent($this->url . '?flag=seednow', $this->args(), 200, true, true);
        $this->assertSame(1, $this->httpCalls);
        $this->assertCount(1, $this->transients, 'No separate seednow transient may be created');

        $normal = RemoteGet::retrieveContent($this->url, $this->args());

        $this->assertSame('FRESH', $normal);
        $this->assertSame(1, $this->httpCalls, 'Normal read after force refresh must use the refreshed cache');
    }
}
