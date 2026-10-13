<?php
/**
 * Test site only: makes outside HTTP requests predictable.
 *
 * - Requests to example.org are answered with fixed responses, so tests of
 *   remote attachments, URL imports and downloads don't need the internet.
 * - The ESV and NET Bible APIs are answered like the real ones, with a script
 *   tag in the text so tests can check that it is filtered. The ESV stub only
 *   answers the key the tests set (test-esv-key).
 * - Every other outside request (WordPress.org update checks)
 *   fails immediately, so rendered output is the same on every run.
 * - Requests made with reject_unsafe_urls (wp_safe_remote_*, download_url) are
 *   validated first, as WordPress would, so tests can check that the plugin
 *   refuses private addresses.
 * - Every request is logged in the sb_test_http_log option, which the seed clears.
 *
 * Mapped into wp-content/mu-plugins of the wp-env test site by .wp-env.json.
 */

add_filter(
	'pre_http_request',
	function ( $response, $args, $url ) {
		$host = wp_parse_url( $url, PHP_URL_HOST );
		$log   = get_option( 'sb_test_http_log', array() );
		$log[] = array( 'url' => $url, 'method' => $args['method'], 'safe' => ! empty( $args['reject_unsafe_urls'] ) );
		update_option( 'sb_test_http_log', $log, false );
		if ( ! empty( $args['reject_unsafe_urls'] ) && ! wp_http_validate_url( $url ) ) {
			return new WP_Error( 'http_request_failed', 'A valid URL was not provided.' );
		}
		if ( in_array( $host, array( 'localhost', '127.0.0.1' ), true ) ) {
			return $response;
		}
		if ( 'api.esv.org' === $host ) {
			return sb_test_esv_response( $url, $args );
		}
		if ( 'labs.bible.org' === $host ) {
			return sb_test_net_response( $url );
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

/**
 * Builds a fake HTTP response.
 */
function sb_test_response( $code, $body, $content_type ) {
	return array(
		'headers'  => array( 'content-type' => $content_type ),
		'body'     => $body,
		'response' => array( 'code' => $code, 'message' => 200 === $code ? 'OK' : 'Error' ),
		'cookies'  => array(),
		'filename' => null,
	);
}

/**
 * Answers the ESV API (https://api.esv.org/docs/passage-html/) for the key test-esv-key.
 */
function sb_test_esv_response( $url, $args ) {
	$headers = isset( $args['headers'] ) ? (array) $args['headers'] : array();
	if ( ! isset( $headers['Authorization'] ) || 'Token test-esv-key' !== $headers['Authorization'] ) {
		return sb_test_response( 401, '{"detail":"Invalid token."}', 'application/json' );
	}
	if ( '/v3/passage/html/' !== wp_parse_url( $url, PHP_URL_PATH ) ) {
		return sb_test_response( 404, '{"detail":"Not found."}', 'application/json' );
	}
	parse_str( (string) wp_parse_url( $url, PHP_URL_QUERY ), $query );
	$reference = isset( $query['q'] ) ? $query['q'] : '';
	$passage   = '<h2 class="extra_text">' . esc_html( $reference ) . ' <small class="audio extra_text">(<a class="mp3link" href="https://audio.esv.org/david-cochran-heath/mq/' . rawurlencode( $reference ) . '.mp3" title="' . esc_attr( $reference ) . '" type="audio/mpeg">Listen</a>)</small></h2>'
		. '<p id="p45003021_01-1" class="starts-chapter"><b class="verse-num" id="v45003021-1">21&nbsp;</b>But now the righteousness of God has been manifested apart from the law<script>alert("esv")</script>, although the Law and the Prophets bear witness to it&mdash; </p>'
		. '<p>(<a href="http://www.esv.org" class="copyright">ESV</a>)</p>';
	return sb_test_response(
		200,
		wp_json_encode(
			array(
				'query'     => $reference,
				'canonical' => $reference,
				'parsed'    => array( array( 45003021, 45003031 ) ),
				'passages'  => array( $passage ),
			)
		),
		'application/json'
	);
}

/**
 * Answers the NET Bible API (https://labs.bible.org/api_web_service) with two verses
 * of the requested chapter and the first verse of the next, in its paragraph format.
 */
function sb_test_net_response( $url ) {
	parse_str( (string) wp_parse_url( $url, PHP_URL_QUERY ), $query );
	if ( '/api/' !== wp_parse_url( $url, PHP_URL_PATH ) || ! isset( $query['passage'], $query['type'] ) || 'xml' !== $query['type'] ) {
		return sb_test_response( 500, 'Internal Server Error', 'text/html' );
	}
	if ( ! preg_match( '/^(.+) (\d+):(\d+)/', $query['passage'], $match ) ) {
		return sb_test_response( 200, '<?xml version="1.0" encoding="UTF-8"?><bible></bible>', 'text/xml' );
	}
	list( , $book, $chapter, $verse ) = $match;
	$items = array(
		array( $chapter, $verse, '<p class="bodytext">But now, apart from the law, the righteousness of God<script>alert("net")</script> has been disclosed ' ),
		array( $chapter, $verse + 1, 'namely, the righteousness of God through the faithfulness of Jesus Christ.</p>' ),
		array( $chapter + 1, 1, '<p class="bodytext">What then shall we say?</p>' ),
	);
	$xml = '<?xml version="1.0" encoding="UTF-8"?><bible><title>' . esc_html( $query['passage'] ) . '</title>';
	foreach ( $items as $item ) {
		$xml .= '<item><bookname>' . esc_html( $book ) . '</bookname><chapter>' . (int) $item[0] . '</chapter><verse>' . (int) $item[1] . '</verse><text>' . htmlspecialchars( $item[2], ENT_XML1 ) . '</text></item>';
	}
	return sb_test_response( 200, $xml . '</bible>', 'text/xml' );
}
