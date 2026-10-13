<?php
/**
 * Test site only: records which lines of the plugin each request runs, for the
 * coverage report (npm run test:coverage, see tests/coverage/run.js).
 *
 * It records only when Xdebug runs in coverage mode (wp-env start --xdebug=coverage)
 * and the folder coverage/raw exists in the plugin folder, which run.js creates.
 * Of the requests to the test-only endpoint, only "php" is recorded (some tests call
 * the plugin's functions that way); seeding and queries set up and inspect tests.
 *
 * Each request writes coverage/raw/<id>.json with:
 *   lines:     plugin file => { line: 1 (ran) or -1 (didn't run) }
 *   functions: plugin function => [file, first line, last line]
 *   templates: the templates rendered ('search', 'single') as their uncompiled text
 */

if ( ! function_exists( 'xdebug_start_code_coverage' ) || false === strpos( (string) ini_get( 'xdebug.mode' ), 'coverage' ) ) {
	return;
}
if ( 'local' !== wp_get_environment_type() ) {
	return;
}
if ( isset( $_SERVER['HTTP_X_SB_TEST'] ) ) {
	$sb_coverage_request = json_decode( (string) file_get_contents( 'php://input' ), true );
	if ( ! isset( $sb_coverage_request['action'] ) || 'php' !== $sb_coverage_request['action'] ) {
		return;
	}
}

$sb_coverage_plugin = WP_PLUGIN_DIR . '/sermon-browser/';
if ( ! is_dir( $sb_coverage_plugin . 'coverage/raw' ) ) {
	return;
}

// Xdebug finds the lines that didn't run when it compiles a file, which it can't
// do for files OPcache already holds, so compile the plugin afresh.
ini_set( 'opcache.enable', '0' );
xdebug_start_code_coverage( XDEBUG_CC_UNUSED | XDEBUG_CC_DEAD_CODE );

register_shutdown_function(
	function () use ( $sb_coverage_plugin ) {
		$coverage = xdebug_get_code_coverage();
		xdebug_stop_code_coverage();
		$result = array(
			'lines'     => array(),
			'functions' => array(),
			'templates' => array(),
		);
		foreach ( $coverage as $file => $lines ) {
			if ( 0 !== strpos( $file, $sb_coverage_plugin ) ) {
				continue;
			}
			$relative = substr( $file, strlen( $sb_coverage_plugin ) );
			// Templates run as eval()'d code, reported as "sermon.php(451) : eval()'d code".
			if ( preg_match( '/^(.+)\((\d+)\) : eval\(\)\'d code$/', $relative, $match ) ) {
				$source = file( $sb_coverage_plugin . $match[1] );
				$type   = false !== strpos( $source[ $match[2] - 1 ], 'single_output' ) ? 'single' : 'search';
				if ( function_exists( 'sb_get_option' ) ) {
					$result['templates'][ $type ] = sb_get_option( $type . '_template' );
				}
				continue;
			}
			if ( 0 === strpos( $relative, 'tests/' ) || 0 === strpos( $relative, 'node_modules/' ) ) {
				continue;
			}
			$result['lines'][ $relative ] = array();
			foreach ( $lines as $line => $state ) {
				if ( -2 !== $state ) { // -2 is dead code, which can't run.
					$result['lines'][ $relative ][ $line ] = $state > 0 ? 1 : -1;
				}
			}
		}
		$functions = get_defined_functions();
		foreach ( $functions['user'] as $name ) {
			$function = new ReflectionFunction( $name );
			$file     = (string) $function->getFileName();
			if ( 0 === strpos( $file, $sb_coverage_plugin ) && 0 !== strpos( $file, $sb_coverage_plugin . 'tests/' ) ) {
				$result['functions'][ $function->getName() ] = array( substr( $file, strlen( $sb_coverage_plugin ) ), $function->getStartLine(), $function->getEndLine() );
			}
		}
		file_put_contents( $sb_coverage_plugin . 'coverage/raw/' . uniqid( '', true ) . '.json', wp_json_encode( $result ) );
	}
);
