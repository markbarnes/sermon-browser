<?php
/**
 * Bible references and the text of Bible passages.
 *
 * @package SermonBrowser
 */

namespace SermonBrowser;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Formats Bible references, and fetches passages' text from the ESV and NET Bible APIs.
 *
 * A passage's start and end are arrays with the keys book (its English name), chapter and verse.
 */
class Bible {

	/**
	 * Returns a human friendly Bible reference (John 3:1-16, not John 3:1-John 3:16), with the book translated.
	 *
	 * For callers that still pass arrays: Passage::reference() does the work.
	 *
	 * @param array $start    Start of the passage.
	 * @param array $end      End of the passage.
	 * @param bool  $add_link Whether to link the book names to the sermons on that book.
	 * @return string HTML.
	 */
	public static function reference( $start, $end, $add_link = false ) {
		$passage = Passage::from_arrays( $start, $end );
		return $passage ? $passage->reference( $add_link ) : '';
	}

	/**
	 * Returns the text of a passage, or '' if it can't be fetched.
	 *
	 * Other versions (KJV, ASV and so on) came from api.preachingcentral.com, which no longer exists,
	 * so their template tags print nothing.
	 *
	 * @param Passage $passage The passage.
	 * @param string  $version 'esv' or 'net'.
	 * @return string HTML.
	 */
	public static function text( Passage $passage, $version ) {
		if ( $version == 'esv' ) {
			return self::esv_text( $passage );
		} elseif ( $version == 'net' ) {
			return self::net_text( $passage );
		} else {
			return '';
		}
	}

	/**
	 * Returns the ESV text of a passage, from api.esv.org, if an API key is set.
	 *
	 * @param Passage $passage The passage.
	 * @return string HTML.
	 */
	private static function esv_text( Passage $passage ) {
		$api_key = sb_get_option( 'esv_api_key' );
		if ( $api_key ) {
			$header   = array( 'Authorization' => 'Token ' . $api_key );
			$esv_url  = 'https://api.esv.org/v3/passage/html/?q=' . rawurlencode( wp_specialchars_decode( $passage->reference() ) ) . '&include-headings=false&include-footnotes=false';
			$api_body = self::download( $esv_url, $header );
			$decode   = json_decode( $api_body );
			if ( is_object( $decode ) && isset( $decode->passages ) && is_array( $decode->passages ) ) {
				return wp_kses_post( implode( '', $decode->passages ) );
			}
		}
		return '';
	}

	/**
	 * Returns the NET Bible text of a passage, from labs.bible.org.
	 *
	 * @param Passage $passage The passage.
	 * @return string HTML.
	 */
	private static function net_text( Passage $passage ) {
		$reference   = rawurlencode( wp_specialchars_decode( $passage->reference() ) );
		$old_chapter = $passage->start->chapter;
		// https://labs.bible.org/api_web_service
		$net_url = "https://labs.bible.org/api/?passage={$reference}&type=xml";
		$xml     = self::xml( self::download( $net_url . '&formatting=para' ) );
		if ( ! $xml ) {
			return '';
		}
		$output = '';
		$items  = $xml->item;
		foreach ( $items as $item ) {
			if ( $item->text != '[[EMPTY]]' ) {
				if ( substr( $item->text, 0, 8 ) == '<p class' ) {
					$paraend    = stripos( $item->text, '>', 8 ) + 1;
					$output    .= "\n" . substr( $item->text, 0, $paraend );
					$item->text = substr( $item->text, $paraend );
				}
				if ( $old_chapter == $item->chapter ) {
					$output .= ' <span class="verse-num">' . (int) $item->verse . '</span>';
				} else {
					$output     .= ' <span class="chapter-num">' . (int) $item->chapter . ':' . (int) $item->verse . '</span> ';
					$old_chapter = strval( $item->chapter );
				}
				$output .= $item->text;
			}
		}
		return "<div class=\"net\">\r<h2>" . $passage->reference() . '</h2><p>' . wp_kses_post( $output ) . " (<a href=\"https://netbible.org/bible/{$reference}\">NET Bible</a>)</p></div>";
	}

	/**
	 * Parses XML.
	 *
	 * @param string $content XML.
	 * @return \SimpleXMLElement|false
	 */
	private static function xml( $content ) {
		if ( $content == '' ) {
			return false;
		}
		$previous = libxml_use_internal_errors( true );
		try {
			$xml = new \SimpleXMLElement( $content, LIBXML_NONET );
		} catch ( \Exception $e ) {
			$xml = false;
		}
		libxml_use_internal_errors( $previous );
		return $xml;
	}

	/**
	 * Fetches a web page.
	 *
	 * @param string $page_url URL.
	 * @param array  $headers  Request headers.
	 * @return string The page, or '' if it couldn't be fetched.
	 */
	private static function download( $page_url, $headers = array() ) {
		$response = wp_safe_remote_get(
			$page_url,
			array(
				'headers' => $headers,
				'timeout' => 10,
			)
		);
		if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) != 200 ) {
			return '';
		}
		return wp_remote_retrieve_body( $response );
	}
}
