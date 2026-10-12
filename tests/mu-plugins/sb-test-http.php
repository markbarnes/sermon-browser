<?php
/**
 * Test site only: makes outside HTTP requests predictable.
 *
 * - Requests to example.org are answered with fixed responses, so tests of
 *   remote attachments, URL imports and downloads don't need the internet.
 * - Every other outside request (Bible text APIs, WordPress.org update checks)
 *   fails immediately, so rendered output is the same on every run.
 *
 * Mapped into wp-content/mu-plugins of the wp-env test site by .wp-env.json.
 */

add_filter(
	'pre_http_request',
	function ( $response, $args, $url ) {
		$host = wp_parse_url( $url, PHP_URL_HOST );
		if ( in_array( $host, array( 'localhost', '127.0.0.1' ), true ) ) {
			return $response;
		}
		if ( 'example.org' !== $host ) {
			return new WP_Error( 'http_request_blocked', 'Outside HTTP requests are blocked on the test site.' );
		}

		$path = (string) wp_parse_url( $url, PHP_URL_PATH );
		if ( '.mp3' === substr( $path, -4 ) ) {
			// A minimal MPEG audio file: one ID3v2 header and silent frames.
			$body         = "ID3\x03\x00\x00\x00\x00\x00\x00" . str_repeat( "\xFF\xFB\x90\x64" . str_repeat( "\x00", 413 ), 20 );
			$content_type = 'audio/mpeg';
		} elseif ( '.php' === substr( $path, -4 ) ) {
			$body         = '<?php echo "remote code"; ?>';
			$content_type = 'text/plain';
		} else {
			return array(
				'headers'  => array(),
				'body'     => 'Not found',
				'response' => array( 'code' => 404, 'message' => 'Not Found' ),
				'cookies'  => array(),
				'filename' => null,
			);
		}

		if ( ! empty( $args['stream'] ) && ! empty( $args['filename'] ) ) {
			file_put_contents( $args['filename'], $body );
		}
		return array(
			'headers'  => array(
				'content-type'   => $content_type,
				'content-length' => strlen( $body ),
			),
			'body'     => 'HEAD' === $args['method'] ? '' : $body,
			'response' => array( 'code' => 200, 'message' => 'OK' ),
			'cookies'  => array(),
			'filename' => empty( $args['stream'] ) ? null : $args['filename'],
		);
	},
	10,
	3
);
