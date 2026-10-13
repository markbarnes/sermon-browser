<?php
/**
 * Checks the test set-up itself.
 *
 * @package SermonBrowser
 */

/**
 * The plugin is loaded and its tables exist, in the unit tests' own database.
 */
class Bootstrap_Test extends WP_UnitTestCase {

	public function test_uses_its_own_database() {
		$this->assertSame( 'sb_phpunit', DB_NAME );
	}

	public function test_plugin_is_loaded_with_its_tables() {
		global $wpdb;
		$this->assertTrue( function_exists( 'sb_get_option' ) );
		$this->assertSame( "{$wpdb->prefix}sb_sermons", $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', "{$wpdb->prefix}sb_sermons" ) ) );
	}
}
