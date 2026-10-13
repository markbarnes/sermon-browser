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

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- the plugin's own tables, which WordPress has no API or cache for
// phpcs:disable WordPress.Security.NonceVerification.Recommended -- the feed is a read-only GET request

/**
 * An RSS feed of the latest sermons' audio and video, for podcast players.
 *
 * Takes the same filters as the sermon list, so a feed can be limited to a preacher, series and so on.
 */
class Podcast {

	/**
	 * Sends the feed and ends the request.
	 */
	public static function serve() {
		$sermons = sb_get_sermons(
			array(
				'title'    => isset( $_REQUEST['title'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['title'] ) ) : '',
				'preacher' => isset( $_REQUEST['preacher'] ) ? (int) $_REQUEST['preacher'] : '',
				'date'     => isset( $_REQUEST['date'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['date'] ) ) : '',
				'enddate'  => isset( $_REQUEST['enddate'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['enddate'] ) ) : '',
				'series'   => isset( $_REQUEST['series'] ) ? (int) $_REQUEST['series'] : '',
				'service'  => isset( $_REQUEST['service'] ) ? (int) $_REQUEST['service'] : '',
				'book'     => isset( $_REQUEST['book'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['book'] ) ) : '',
				'tag'      => isset( $_REQUEST['stag'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['stag'] ) ) : '',
			),
			array(
				'by'  => 'm.datetime',
				'dir' => 'desc',
			),
			1,
			1000000
		);

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

	<lastBuildDate><?php self::print_date(isset($sermons[0]) ? $sermons[0]: time()) ?></lastBuildDate>
	<pubDate><?php self::print_date(isset($sermons[0]) ? $sermons[0]: time()) ?></pubDate>
	<generator>Wordpress Sermon Browser plugin <?php echo esc_xml(SB_CURRENT_VERSION) ?> (<?php echo esc_xml(Plugin::HOME_URL) ?>)</generator>
	<docs>http://blogs.law.harvard.edu/tech/rss</docs>
	<category>Religion &amp; Spirituality</category>
	<itunes:category text="Religion &amp; Spirituality"></itunes:category>
	<?php
		$mp3count = 0;
		$accepted_extensions = array ('mp3', 'm4a', 'mp4', 'm4v','mov', 'wma', 'wmv');
		foreach ($sermons as $sermon) {
			if ($mp3count > 15)
				break;
			$media = sb_get_stuff($sermon);
			if (is_array($media['Files']) || is_array($media['URLs'])) {
				foreach ($media as $media_type => $media_names)
					if (is_array($media_names) && $media_type != 'Code')
						foreach ((array)$media_names as $media_name)
							if (in_array(strtolower(substr($media_name, -3)), $accepted_extensions)) {
								$mp3count++;
?>
<item>
		<guid><?php echo self::file_url($media_name, $media_type) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped for XML by xml_encode() ?></guid>
		<title><?php echo self::xml_encode(stripslashes($sermon->title)) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped for XML by xml_encode() ?></title>
		<link><?php echo self::xml_encode(sb_display_url().sb_query_char(false).'sermon_id='.(int) $sermon->id) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped for XML by xml_encode() ?></link>
		<itunes:author><?php echo self::xml_encode(stripslashes($sermon->preacher)) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped for XML by xml_encode() ?></itunes:author>
<?php if ($sermon->description) { ?>
		<description><?php echo self::cdata(stripslashes($sermon->description)) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the sermon description, in a CDATA section ?></description>
		<itunes:summary><?php echo self::cdata(stripslashes($sermon->description)) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the sermon description, in a CDATA section ?></itunes:summary>
<?php } ?>
		<enclosure url="<?php echo self::file_url($media_name, $media_type).'" '.self::media_size($media_name, $media_type).self::mime_type($media_name); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by xml_encode(), (int) and esc_attr() ?> />
<?php   $duration = self::duration($media_name, $media_type);
		if ($duration) { ?>
		<itunes:duration><?php echo self::xml_encode($duration) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped for XML by xml_encode() ?></itunes:duration>
<?php } ?>
		<category><?php echo self::xml_encode(stripslashes($sermon->service)) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped for XML by xml_encode() ?></category>
		<pubDate><?php self::print_date($sermon) ?></pubDate>
	</item>
	<?php
				}
			}
		}
	?>
</channel>
</rss>
<?php
		die();
	}

	/**
	 * Prints a date in the feed's format (RFC 822).
	 *
	 * @param object|int $sermon A sermon, or a timestamp.
	 */
	private static function print_date( $sermon ) {
		if ( is_object( $sermon ) ) {
			echo esc_xml( gmdate( 'D, d M Y H:i:s O', strtotime( $sermon->datetime ) ) );
		} else {
			echo esc_xml( gmdate( 'D, d M Y H:i:s O', strtotime( $sermon ) ) );
		}
	}

	/**
	 * Returns an enclosure's length attribute.
	 *
	 * @param string $media_name File name or URL.
	 * @param string $media_type 'Files' or 'URLs'.
	 * @return string|null
	 */
	private static function media_size( $media_name, $media_type ) {
		if ( 'URLs' === $media_type ) {
			$response = wp_safe_remote_head( wp_specialchars_decode( $media_name, ENT_QUOTES ), array( 'timeout' => 5, 'redirection' => 3 ) );
			$filesize = (int) wp_remote_retrieve_header( $response, 'content-length' );
			if ( $filesize ) {
				return "length=\"{$filesize}\"";
			}
		} else {
			return 'length="' . (int) @filesize( SB_ABSPATH . sb_get_option( 'upload_dir' ) . $media_name ) . '"'; // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- a missing file has length 0
		}
	}

	/**
	 * Returns the duration of an uploaded MP3 file, reading it from the file the first time.
	 *
	 * @param string $media_name File name or URL.
	 * @param string $media_type 'Files' or 'URLs'.
	 * @return string|null
	 */
	private static function duration( $media_name, $media_type ) {
		global $wpdb;
		if ( strtolower( substr( $media_name, -3 ) ) == 'mp3' && 'Files' === $media_type ) {
			$duration = $wpdb->get_var( $wpdb->prepare( "SELECT duration FROM {$wpdb->prefix}sb_stuff WHERE type = 'file' AND name = %s", $media_name ) );
			if ( $duration ) {
				return $duration;
			}
			if ( ! class_exists( 'getID3' ) ) {
				require ABSPATH . WPINC . '/ID3/getid3.php';
			}
			$getid3     = new \getID3();
			$media_info = $getid3->analyze( SB_ABSPATH . sb_get_option( 'upload_dir' ) . $media_name );
			$duration   = isset( $media_info['playtime_string'] ) ? $media_info['playtime_string'] : '';
			$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->prefix}sb_stuff SET duration = %s WHERE type = 'file' AND name = %s", $duration, $media_name ) );
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
	 * @param string $media_name File name or URL.
	 * @param string $media_type 'Files' or 'URLs'.
	 * @return string Escaped for XML.
	 */
	private static function file_url( $media_name, $media_type ) {
		$user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
		$stats      = ! ( stripos( $user_agent, 'itunes' ) !== false || stripos( $user_agent, 'FeedBurner' ) !== false || stripos( $user_agent, 'AppleCoreMedia' ) !== false );
		if ( 'URLs' === $media_type ) {
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
