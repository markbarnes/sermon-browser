<?php
/**
 * Test site only: no WP-Cron. Scheduled events ran in the middle of tests: the spawned
 * wp-cron.php request showed up in the log of outside requests, and a task saving a post
 * rewrote the podcast URL. Neither the plugin nor the tests use cron.
 *
 * Defined here rather than in .wp-env.tests.json because WordPress's PHPUnit bootstrap
 * defines it too, without checking, and a second definition is a warning.
 */

defined( 'DISABLE_WP_CRON' ) || define( 'DISABLE_WP_CRON', true );
