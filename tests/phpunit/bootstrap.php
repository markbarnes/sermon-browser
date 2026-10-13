<?php
/**
 * Loads WordPress's test suite (provided by wp-env in WP_TESTS_DIR) with the plugin active,
 * then installs the plugin's tables. Run with npm run test:unit.
 *
 * @package SermonBrowser
 */

$tests_dir = getenv( 'WP_TESTS_DIR' );
if ( ! $tests_dir || 'sb_phpunit' !== getenv( 'WORDPRESS_DB_NAME' ) ) {
	echo 'Run the tests with npm run test:unit: they need wp-env and a database of their own.' . PHP_EOL;
	exit( 1 );
}

require dirname( __DIR__, 2 ) . '/vendor/yoast/phpunit-polyfills/phpunitpolyfills-autoload.php';
require $tests_dir . '/includes/functions.php';

tests_add_filter(
	'muplugins_loaded',
	function () {
		require dirname( __DIR__, 2 ) . '/sermon.php';
	}
);

require $tests_dir . '/includes/bootstrap.php';

// Keep PHP's messages out of the test site's debug log, which the behaviour tests check.
ini_set( 'error_log', sys_get_temp_dir() . '/sb-phpunit-debug.log' ); // phpcs:ignore WordPress.PHP.IniSet.Risky -- tests only

// Install the plugin's tables afresh, outside any test's transaction, so they are real tables
// (WP_UnitTestCase turns tables created during a test into temporary ones).
global $wpdb;
foreach ( array( 'sb_preachers', 'sb_series', 'sb_services', 'sb_sermons', 'sb_stuff', 'sb_books', 'sb_books_sermons', 'sb_sermons_tags', 'sb_tags' ) as $sb_table ) {
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}{$sb_table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- the plugin's own tables
}
require_once SB_INCLUDES_DIR . '/sb-install.php';
sb_install();

require __DIR__ . '/fixtures/interface-fixture-writer.php';
require __DIR__ . '/fixtures/class-tables-fixture-writer.php';
require __DIR__ . '/contracts/class-sermon-repository-contract.php';
require __DIR__ . '/contracts/class-attachment-repository-contract.php';
