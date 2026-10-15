<?php
/**
 * PHPUnit bootstrap for fau-cris unit tests.
 *
 * Defines the minimum WordPress constants the plugin classes guard against
 * (ABSPATH, HOUR_IN_SECONDS) BEFORE they are autoloaded, then loads Composer's
 * autoloader. WordPress functions are mocked per test via Brain Monkey.
 */

// Refuse direct web access: this bootstrap is only ever run by PHPUnit on the
// command line, where ABSPATH does not exist yet and is defined below.
if (PHP_SAPI !== 'cli' && !defined('ABSPATH')) {
    exit;
}

defined('ABSPATH') || define('ABSPATH', __DIR__ . '/');
defined('HOUR_IN_SECONDS') || define('HOUR_IN_SECONDS', 3600);

// No global variable here: the autoloader path is used inline so the file
// declares nothing in the global namespace.
if (!file_exists(dirname(__DIR__) . '/vendor/autoload.php')) {
    // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- CLI bootstrap: WordPress and WP_Filesystem are not loaded here, STDERR is the only channel.
    fwrite(STDERR, "Missing vendor/autoload.php — run `composer install` first.\n");
    exit(1);
}
require dirname(__DIR__) . '/vendor/autoload.php';
