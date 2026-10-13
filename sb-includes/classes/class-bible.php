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
	 * @param array $start    Start of the passage.
	 * @param array $end      End of the passage.
	 * @param bool  $add_link Whether to link the book names to the sermons on that book.
	 * @return string HTML.
	 */
	public static function reference( $start, $end, $add_link = false ) {
		if ( ! is_array( $start ) || ! is_array( $end ) || ! isset( $start['book'] ) || ! trim( $start['book'] ) ) {
			return '';
		}
		$translated_books = array_combine( sb_get_default( 'eng_bible_books' ), sb_get_default( 'bible_books' ) );
		$start_book       = esc_html( isset( $translated_books[ trim( $start['book'] ) ] ) ? $translated_books[ trim( $start['book'] ) ] : trim( $start['book'] ) );
		$end_book         = esc_html( isset( $translated_books[ trim( $end['book'] ) ] ) ? $translated_books[ trim( $end['book'] ) ] : trim( $end['book'] ) );
		$start_chapter    = (int) $start['chapter'];
		$end_chapter      = (int) $end['chapter'];
		$start_verse      = (int) $start['verse'];
		$end_verse        = (int) $end['verse'];
		if ( $add_link ) {
			$start_book = '<a href="' . sb_get_book_link( trim( $start['book'] ) ) . "\">{$start_book}</a>";
			$end_book   = '<a href="' . sb_get_book_link( trim( $end['book'] ) ) . "\">{$end_book}</a>";
		}
		if ( $start_book == $end_book ) {
			if ( $start_chapter == $end_chapter ) {
				if ( $start_verse == $end_verse ) {
					$reference = "$start_book $start_chapter:$start_verse";
				} else {
					$reference = "$start_book $start_chapter:$start_verse-$end_verse";
				}
			} else {
				$reference = "$start_book $start_chapter:$start_verse-$end_chapter:$end_verse";
			}
		} else {
			$reference = "$start_book $start_chapter:$start_verse - $end_book $end_chapter:$end_verse";
		}
		return $reference;
	}

	/**
	 * Returns the text of a passage, or '' if it can't be fetched.
	 *
	 * Other versions (KJV, ASV and so on) came from api.preachingcentral.com, which no longer exists,
	 * so their template tags print nothing.
	 *
	 * @param array  $start   Start of the passage.
	 * @param array  $end     End of the passage.
	 * @param string $version 'esv' or 'net'.
	 * @return string HTML.
	 */
	public static function text( $start, $end, $version ) {
		if ( $version == 'esv' ) {
			return self::esv_text( $start, $end );
		} elseif ( $version == 'net' ) {
			return self::net_text( $start, $end );
		} else {
			return '';
		}
	}

	/**
	 * Returns the ESV text of a passage, from api.esv.org, if an API key is set.
	 *
	 * @param array $start Start of the passage.
	 * @param array $end   End of the passage.
	 * @return string HTML.
	 */
	private static function esv_text( $start, $end ) {
		$api_key = sb_get_option( 'esv_api_key' );
		if ( $api_key ) {
			$header   = array( 'Authorization' => 'Token ' . $api_key );
			$esv_url  = 'https://api.esv.org/v3/passage/html/?q=' . rawurlencode( wp_specialchars_decode( self::reference( $start, $end ) ) ) . '&include-headings=false&include-footnotes=false';
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
	 * @param array $start Start of the passage.
	 * @param array $end   End of the passage.
	 * @return string HTML.
	 */
	private static function net_text( $start, $end ) {
		$reference   = rawurlencode( wp_specialchars_decode( self::reference( $start, $end ) ) );
		$old_chapter = $start['chapter'];
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
		return "<div class=\"net\">\r<h2>" . self::reference( $start, $end ) . '</h2><p>' . wp_kses_post( $output ) . " (<a href=\"https://netbible.org/bible/{$reference}\">NET Bible</a>)</p></div>";
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
