<?php
/**
 * PHPUnit bootstrap for fau-cris unit tests.
 *
 * Defines the minimum WordPress constants the plugin classes guard against
 * (ABSPATH, HOUR_IN_SECONDS) BEFORE they are autoloaded, then loads Composer's
 * autoloader. WordPress functions are mocked per test via Brain Monkey.
 */

defined('ABSPATH') || define('ABSPATH', __DIR__ . '/');
defined('HOUR_IN_SECONDS') || define('HOUR_IN_SECONDS', 3600);

$autoload = dirname(__DIR__) . '/vendor/autoload.php';
if (!file_exists($autoload)) {
    fwrite(STDERR, "Missing vendor/autoload.php — run `composer install` first.\n");
    exit(1);
}
require $autoload;
