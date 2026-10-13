<?php
/**
 * The podcast feed (?podcast).
 *
 * @package SermonBrowser
 */

namespace SermonBrowser;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.Security.NonceVerification.Recommended -- the feed is a read-only GET request

/**
 * An RSS feed of the latest sermons' audio and video, for podcast players.
 *
 * Takes the same filters as the sermon list, so a feed can be limited to a preacher, series and so on.
 */
class Podcast {

	/**
	 * How many sermons the feed reads at a time, until it has enough episodes.
	 */
	const BATCH = 20;

	/**
	 * Sends the feed and ends the request.
	 */
	public static function serve() {
		// Always newest first, whatever order the list was in.
		$query           = Sermon_Query::from_filters( Sermon_Query::filters_from_request() );
		$query->per_page = self::BATCH;
		$episodes        = self::episodes( $query );
		$query->per_page = 1;
		$newest          = Storage::sermons()->find( $query )->sermons;
		$newest          = $newest ? $newest[0] : time();

		wp_timezone_override_offset();

		header( 'Content-Type: application/rss+xml' );
		echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		?>
<rss xmlns:itunes="http://www.itunes.com/dtds/podcast-1.0.dtd" version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
<channel>
	<atom:link href="<?php echo self::xml_encode(sb_get_option('podcast_url')) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped for XML by xml_encode() ?>" rel="self" type="application/rss+xml" />
	<title><?php echo self::xml_encode(get_bloginfo('name')) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped for XML by xml_encode() ?> Podcast</title>
	<itunes:author></itunes:author>
	<description><?php echo self::xml_encode(get_bloginfo('description')) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped for XML by xml_encode() ?></description>
	<link><?php echo self::xml_encode(site_url()) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped for XML by xml_encode() ?></link>
	<language>en-us</language>
	<copyright></copyright>
	<itunes:explicit>no</itunes:explicit>
	<itunes:owner>
		<itunes:name></itunes:name>
		<itunes:email></itunes:email>
	</itunes:owner>

	<lastBuildDate><?php self::print_date($newest) ?></lastBuildDate>
	<pubDate><?php self::print_date($newest) ?></pubDate>
	<generator>Wordpress Sermon Browser plugin <?php echo esc_xml(SB_CURRENT_VERSION) ?> (<?php echo esc_xml(Plugin::HOME_URL) ?>)</generator>
	<docs>http://blogs.law.harvard.edu/tech/rss</docs>
	<category>Religion &amp; Spirituality</category>
	<itunes:category text="Religion &amp; Spirituality"></itunes:category>
	<?php
		foreach ($episodes as $episode) {
			list($sermon, $attachment) = $episode;
?>
<item>
		<guid><?php echo self::file_url($attachment) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped for XML by xml_encode() ?></guid>
		<title><?php echo self::xml_encode($sermon->title) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped for XML by xml_encode() ?></title>
		<link><?php echo self::xml_encode(sb_display_url().sb_query_char(false).'sermon_id='.(int) $sermon->id) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped for XML by xml_encode() ?></link>
		<itunes:author><?php echo self::xml_encode($sermon->preacher ? $sermon->preacher->name : '') // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped for XML by xml_encode() ?></itunes:author>
<?php if ($sermon->description) { ?>
		<description><?php echo self::cdata($sermon->description) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the sermon description, in a CDATA section ?></description>
		<itunes:summary><?php echo self::cdata($sermon->description) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the sermon description, in a CDATA section ?></itunes:summary>
<?php } ?>
		<enclosure url="<?php echo self::file_url($attachment).'" '.self::media_size($attachment).self::mime_type($attachment->content); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by xml_encode(), (int) and esc_attr() ?> />
<?php   $duration = self::duration($attachment);
		if ($duration) { ?>
		<itunes:duration><?php echo self::xml_encode($duration) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped for XML by xml_encode() ?></itunes:duration>
<?php } ?>
		<category><?php echo self::xml_encode($sermon->service ? $sermon->service->name : '') // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped for XML by xml_encode() ?></category>
		<pubDate><?php self::print_date($sermon) ?></pubDate>
	</item>
	<?php
		}
	?>
</channel>
</rss>
<?php
		die();
	}

	/**
	 * Returns the episodes for a query: each audio or video file or URL of its sermons, newest sermon
	 * first, files before URLs. Stops after the sermon that brings it to 16, reading sermons a batch at a time.
	 *
	 * @param Sermon_Query $query The filters, newest first, with the batch size as its page size.
	 * @return array[] Each a Sermon and an Attachment.
	 */
	private static function episodes( Sermon_Query $query ) {
		$extensions = array( 'mp3', 'm4a', 'mp4', 'm4v', 'mov', 'wma', 'wmv' );
		$episodes   = array();
		$batch      = clone $query;
		do {
			$sermons = Storage::sermons()->find( $batch )->sermons;
			foreach ( $sermons as $sermon ) {
				if ( count( $episodes ) > 15 ) {
					return $episodes;
				}
				$files = array();
				$urls  = array();
				foreach ( $sermon->attachments as $attachment ) {
					if ( Attachment::FILE === $attachment->type ) {
						$files[] = $attachment;
					} elseif ( Attachment::URL === $attachment->type ) {
						$urls[] = $attachment;
					}
				}
				foreach ( array_merge( $files, $urls ) as $attachment ) {
					if ( in_array( strtolower( substr( $attachment->content, -3 ) ), $extensions, true ) ) {
						$episodes[] = array( $sermon, $attachment );
					}
				}
			}
			++$batch->page;
		} while ( count( $sermons ) === $batch->per_page );
		return $episodes;
	}

	/**
	 * Prints a date in the feed's format (RFC 822).
	 *
	 * @param Sermon|int $sermon A sermon, or a timestamp.
	 */
	private static function print_date( $sermon ) {
		if ( is_object( $sermon ) ) {
			// The date is the site's local time, printed as if it were UTC, as the feed always has.
			echo esc_xml( gmdate( 'D, d M Y H:i:s O', strtotime( $sermon->date ? $sermon->date->format( 'Y-m-d H:i:s' ) : '1970-01-01 00:00:00' ) ) );
		} else {
			echo esc_xml( gmdate( 'D, d M Y H:i:s O', strtotime( $sermon ) ) );
		}
	}

	/**
	 * Returns an enclosure's length attribute.
	 *
	 * @param Attachment $attachment A file or URL.
	 * @return string|null
	 */
	private static function media_size( Attachment $attachment ) {
		if ( Attachment::URL === $attachment->type ) {
			$response = wp_safe_remote_head( wp_specialchars_decode( $attachment->content, ENT_QUOTES ), array( 'timeout' => 5, 'redirection' => 3 ) );
			$filesize = (int) wp_remote_retrieve_header( $response, 'content-length' );
			if ( $filesize ) {
				return "length=\"{$filesize}\"";
			}
		} else {
			return 'length="' . (int) @filesize( SB_ABSPATH . sb_get_option( 'upload_dir' ) . $attachment->content ) . '"'; // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- a missing file has length 0
		}
	}

	/**
	 * Returns the duration of an uploaded MP3 file, reading it from the file the first time.
	 *
	 * @param Attachment $attachment A file or URL.
	 * @return string|null
	 */
	private static function duration( Attachment $attachment ) {
		if ( strtolower( substr( $attachment->content, -3 ) ) == 'mp3' && Attachment::FILE === $attachment->type ) {
			if ( $attachment->duration ) {
				return $attachment->duration;
			}
			if ( ! class_exists( 'getID3' ) ) {
				require ABSPATH . WPINC . '/ID3/getid3.php';
			}
			$getid3     = new \getID3();
			$media_info = $getid3->analyze( SB_ABSPATH . sb_get_option( 'upload_dir' ) . $attachment->content );
			$duration   = isset( $media_info['playtime_string'] ) ? $media_info['playtime_string'] : '';
			Storage::attachments()->set_duration( $attachment, $duration );
			return $duration;
		}
	}

	/**
	 * Puts text in a CDATA section, splitting any ]]> so it can't end the section early.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	private static function cdata( $text ) {
		return '<![CDATA[' . str_replace( ']]>', ']]]]><![CDATA[>', $text ) . ']]>';
	}

	/**
	 * Escapes text for XML.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	private static function xml_encode( $text ) {
		$text = str_replace( '&amp;amp;', '&amp;', str_replace( '&', '&amp;', $text ) );
		$text = str_replace( '"', '&quot;', $text );
		$text = str_replace( "'", '&apos;', $text );
		$text = str_replace( '<', '&lt;', $text );
		$text = str_replace( '>', '&gt;', $text );
		return $text;
	}

	/**
	 * Returns an attachment's URL, through the download counter unless the feed is read by iTunes.
	 *
	 * Download counting has to be off for iTunes.
	 *
	 * @param Attachment $attachment A file or URL.
	 * @return string Escaped for XML.
	 */
	private static function file_url( Attachment $attachment ) {
		$user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
		$stats      = ! ( stripos( $user_agent, 'itunes' ) !== false || stripos( $user_agent, 'FeedBurner' ) !== false || stripos( $user_agent, 'AppleCoreMedia' ) !== false );
		$media_name = $attachment->content;
		if ( Attachment::URL === $attachment->type ) {
			if ( $stats ) {
				$media_name = sb_display_url() . sb_query_char() . 'show&amp;url=' . rawurlencode( $media_name );
			}
		} elseif ( ! $stats ) {
			$media_name = trailingslashit( site_url() ) . ltrim( sb_get_option( 'upload_dir' ), '/' ) . rawurlencode( $media_name );
		} else {
			$media_name = sb_display_url() . sb_query_char() . 'show&amp;file_name=' . rawurlencode( $media_name );
		}
		return self::xml_encode( $media_name );
	}


	/**
	 * Returns an enclosure's type attribute, for the file types in filetypes.php.
	 *
	 * @param string $media_name File name or URL.
	 * @return string|null
	 */
	private static function mime_type( $media_name ) {
		require SB_INCLUDES_DIR . '/filetypes.php';
		$extension = strtolower( substr( $media_name, strrpos( $media_name, '.' ) + 1 ) );
		if ( array_key_exists( $extension, $filetypes ) ) {
			return ' type="' . esc_attr( $filetypes[ $extension ]['content-type'] ) . '"';
		}
	}
}
