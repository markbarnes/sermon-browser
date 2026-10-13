<?php
if (!defined('ABSPATH'))
	exit;

//Prints ISO date for podcast
function sb_print_iso_date($sermon) {
	if (is_object($sermon)) {
		echo esc_xml(gmdate('D, d M Y H:i:s O', strtotime($sermon->datetime)));
	} else
		echo esc_xml(gmdate('D, d M Y H:i:s O', strtotime($sermon)));
}

//Prints size of file
function sb_media_size($media_name, $media_type) {
	if ($media_type == 'URLs') {
		$response = wp_safe_remote_head(wp_specialchars_decode($media_name, ENT_QUOTES), array('timeout' => 5, 'redirection' => 3));
		$filesize = (int) wp_remote_retrieve_header($response, 'content-length');
		if ($filesize)
			return "length=\"{$filesize}\"";
	} else
		return 'length="'.(int) @filesize(SB_ABSPATH.sb_get_option('upload_dir').$media_name).'"';
}

//Returns duration of .mp3 file
function sb_mp3_duration($media_name, $media_type) {
	global $wpdb;
	if (strtolower(substr($media_name, -3)) == 'mp3' && $media_type == 'Files') {
		$duration = $wpdb->get_var($wpdb->prepare("SELECT duration FROM {$wpdb->prefix}sb_stuff WHERE type = 'file' AND name = %s", $media_name));
		if ($duration)
			return $duration;
		else {
			if (!class_exists('getID3')) {
			    require(ABSPATH.WPINC.'/ID3/getid3.php');
			}
			$getID3 = new getID3;
			$MediaFileInfo = $getID3->analyze(SB_ABSPATH.sb_get_option('upload_dir').$media_name);
			$duration = isset($MediaFileInfo['playtime_string']) ? $MediaFileInfo['playtime_string'] : '';
			$wpdb->query($wpdb->prepare("UPDATE {$wpdb->prefix}sb_stuff SET duration = %s WHERE type = 'file' AND name = %s", $duration, $media_name));
			return $duration;
		}
	}
}

// Puts text in a CDATA section, splitting any ]]> so it can't end the section early
function sb_xml_cdata ($string) {
	return '<![CDATA['.str_replace(']]>', ']]]]><![CDATA[>', $string).']]>';
}

//Replaces & with &amp;
function sb_xml_entity_encode ($string) {
	$string = str_replace('&amp;amp;', '&amp;', str_replace('&', '&amp;', $string));
	$string = str_replace('"', '&quot;', $string);
	$string = str_replace("'", '&apos;', $string);
	$string = str_replace('<', '&lt;', $string);
	$string = str_replace('>', '&gt;', $string);
	return $string;
}

// Convert filename to URL, perhaps with stats
// Stats have to be turned off for iTunes compatibility
function sb_podcast_file_url($media_name, $media_type) {
	$user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
	if (stripos($user_agent, 'itunes') !== FALSE || stripos($user_agent, 'FeedBurner') !== FALSE || stripos($user_agent, 'AppleCoreMedia') !== FALSE)
		$stats = FALSE;
	else
		$stats = TRUE;
	if ($media_type == 'URLs') {
		if ($stats)
			$media_name=sb_display_url().sb_query_char().'show&amp;url='.rawurlencode($media_name);
	} else {
		if (!$stats)
			$media_name=trailingslashit(site_url()).ltrim(sb_get_option('upload_dir'), '/').rawurlencode($media_name);
		else
			$media_name=sb_display_url().sb_query_char().'show&amp;file_name='.rawurlencode($media_name);
	}
	return sb_xml_entity_encode($media_name);
}

// Returns correct MIME type
function sb_mime_type($media_name) {
	require (SB_INCLUDES_DIR.'/filetypes.php');
	$extension = strtolower(substr($media_name, strrpos($media_name, '.') + 1));
	if (array_key_exists ($extension, $filetypes))
		return ' type="'.esc_attr($filetypes[$extension]['content-type']).'"';
}

$sermons = sb_get_sermons(
	array(
		'title' => isset($_REQUEST['title']) ? sanitize_text_field(wp_unslash($_REQUEST['title'])) : '',
		'preacher' => isset($_REQUEST['preacher']) ? (int)$_REQUEST['preacher'] : '',
		'date' => isset($_REQUEST['date']) ? sanitize_text_field(wp_unslash($_REQUEST['date'])) : '',
		'enddate' => isset($_REQUEST['enddate']) ? sanitize_text_field(wp_unslash($_REQUEST['enddate'])) : '',
		'series' => isset($_REQUEST['series']) ? (int)$_REQUEST['series'] : '',
		'service' => isset($_REQUEST['service']) ? (int)$_REQUEST['service'] : '',
		'book' => isset($_REQUEST['book']) ? sanitize_text_field(wp_unslash($_REQUEST['book'])) : '',
		'tag' => isset($_REQUEST['stag']) ? sanitize_text_field(wp_unslash($_REQUEST['stag'])) : '',
	),
	array(
		'by' => 'm.datetime',
		'dir' => 'desc',
	),
	1,
	1000000
);

wp_timezone_override_offset();

header('Content-Type: application/rss+xml');
echo '<?xml version="1.0" encoding="UTF-8"?>'."\n";
?>
<rss xmlns:itunes="http://www.itunes.com/dtds/podcast-1.0.dtd" version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
<channel>
	<atom:link href="<?php echo sb_xml_entity_encode(sb_get_option('podcast_url')) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped for XML by sb_xml_entity_encode() ?>" rel="self" type="application/rss+xml" />
	<title><?php echo sb_xml_entity_encode(get_bloginfo('name')) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped for XML by sb_xml_entity_encode() ?> Podcast</title>
	<itunes:author></itunes:author>
	<description><?php echo sb_xml_entity_encode(get_bloginfo('description')) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped for XML by sb_xml_entity_encode() ?></description>
	<link><?php echo sb_xml_entity_encode(site_url()) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped for XML by sb_xml_entity_encode() ?></link>
	<language>en-us</language>
	<copyright></copyright>
	<itunes:explicit>no</itunes:explicit>
	<itunes:owner>
		<itunes:name></itunes:name>
		<itunes:email></itunes:email>
	</itunes:owner>

	<lastBuildDate><?php sb_print_iso_date(isset($sermons[0]) ? $sermons[0]: time()) ?></lastBuildDate>
	<pubDate><?php sb_print_iso_date(isset($sermons[0]) ? $sermons[0]: time()) ?></pubDate>
	<generator>Wordpress Sermon Browser plugin <?php echo esc_xml(SB_CURRENT_VERSION) ?> (http://www.sermonbrowser.com/)</generator>
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
		<guid><?php echo sb_podcast_file_url($media_name, $media_type) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped for XML by sb_xml_entity_encode() ?></guid>
		<title><?php echo sb_xml_entity_encode(stripslashes($sermon->title)) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped for XML by sb_xml_entity_encode() ?></title>
		<link><?php echo sb_xml_entity_encode(sb_display_url().sb_query_char(false).'sermon_id='.(int) $sermon->id) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped for XML by sb_xml_entity_encode() ?></link>
		<itunes:author><?php echo sb_xml_entity_encode(stripslashes($sermon->preacher)) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped for XML by sb_xml_entity_encode() ?></itunes:author>
<?php if ($sermon->description) { ?>
		<description><?php echo sb_xml_cdata(stripslashes($sermon->description)) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the sermon description, in a CDATA section ?></description>
		<itunes:summary><?php echo sb_xml_cdata(stripslashes($sermon->description)) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the sermon description, in a CDATA section ?></itunes:summary>
<?php } ?>
		<enclosure url="<?php echo sb_podcast_file_url($media_name, $media_type).'" '.sb_media_size($media_name, $media_type).sb_mime_type($media_name); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by sb_xml_entity_encode(), (int) and esc_attr() ?> />
<?php   $duration = sb_mp3_duration($media_name, $media_type);
		if ($duration) { ?>
		<itunes:duration><?php echo sb_xml_entity_encode($duration) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped for XML by sb_xml_entity_encode() ?></itunes:duration>
<?php } ?>
		<category><?php echo sb_xml_entity_encode(stripslashes($sermon->service)) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped for XML by sb_xml_entity_encode() ?></category>
		<pubDate><?php sb_print_iso_date($sermon) ?></pubDate>
	</item>
	<?php
				}
			}
		}
	?>
</channel>
</rss>
<?php die(); ?>