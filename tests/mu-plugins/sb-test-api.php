<?php
/**
 * Test site only: a fast way for the behaviour tests to seed the site, query the
 * database and run PHP, instead of starting WP-CLI for every check.
 *
 * Requests must be POSTed with the header X-SB-Test: run and a JSON body:
 *   {"action": "seed"}
 *   {"action": "query", "sql": "SELECT ... FROM {prefix}sb_sermons"}
 *   {"action": "php", "code": "return sb_get_option('upload_dir');"}
 * The response is the JSON-encoded result.
 *
 * Mapped into wp-content/mu-plugins of the wp-env test site only (see .wp-env.json),
 * so it never exists on the development site or in the plugin zip.
 */

if ( ! isset( $_SERVER['HTTP_X_SB_TEST'] ) || 'run' !== $_SERVER['HTTP_X_SB_TEST'] ) {
	return;
}

add_action(
	'wp_loaded',
	function () {
		global $wpdb;
		$request = json_decode( file_get_contents( 'php://input' ), true );
		$action  = isset( $request['action'] ) ? $request['action'] : '';
		ob_start();
		if ( 'seed' === $action ) {
			require WP_PLUGIN_DIR . '/sermon-browser/tests/fixtures/seed.php';
			$result = json_decode( ob_get_clean(), true );
		} elseif ( 'query' === $action ) {
			$result = $wpdb->get_results( str_replace( '{prefix}', $wpdb->prefix, $request['sql'] ), ARRAY_A );
			if ( $wpdb->last_error ) {
				$result = array( 'error' => $wpdb->last_error );
			}
			ob_end_clean();
		} elseif ( 'php' === $action ) {
			$result = eval( $request['code'] );
			$output = ob_get_clean();
			if ( null === $result ) {
				$result = $output;
			}
		} else {
			ob_end_clean();
			status_header( 400 );
			$result = array( 'error' => 'Unknown action' );
		}
		header( 'Content-Type: application/json' );
		echo wp_json_encode( $result );
		exit;
	},
	0
);
