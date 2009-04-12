<?php

//Prints ISO date for podcast
function sb_print_iso_date($sermon) {
	$sermon_time = $sermon->time;
	if ($sermon_time == '')
		$sermon_time = sb_default_time ($sermon->sid);
	echo date('D, d M Y H:i:s O', strtotime($sermon->date.' '.$sermon_time));
}

//Prints size of file
function sb_media_size($media_name, $media_type) {
	if ($media_type == 'URLs') {
		if(ini_get('allow_url_fopen')) {
			$headers = array_change_key_case(@get_headers($media_name, 1),CASE_LOWER);
			$filesize = $headers['content-length'];
			if ($filesize) return "length=\"{$filesize}\"";
		}
	} else
		return 'length="'.@filesize(sb_get_value('wordpress_path').get_option('sb_sermon_upload_dir').$media_name).'"';
}

//Prints size of .mp3 file
function sb_mp3_length($media_name, $media_type) {
	if (strtolower(substr($media_name, -3)) == 'mp3' && $media_type == 'Files') {
		$mp3file = new mp3file(sb_get_value('wordpress_path').get_option('sb_sermon_upload_dir').$media_name);
		$meta = $mp3file->get_metadata();
		return $meta['Length'];
	}
}

//Replaces & with &amp;
function sb_ampersand_entity ($string) {
	return str_replace('&amp;amp;', '&amp;', str_replace('&', '&amp;', $string));
}

// Convert filename to URL, perhaps with stats
// Stats have to be turned off for iTunes compatibility
function sb_podcast_file_url($media_name, $media_type) {
	$user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
	if (stripos($user_agent, 'itunes') !== FALSE | stripos($user_agent, 'FeedBurner') !== FALSE)
		$stats = FALSE;
	else
		$stats = TRUE;
	if ($media_type == 'URLs') {
		if ($stats)
			$media_name=sb_display_url().sb_query_char().'show&amp;url='.URLencode($media_name);
	} else {
		if (!$stats)
			$media_name=sb_get_value('wordpress_url').get_option('sb_sermon_upload_dir').URLencode($media_name);
		else
			$media_name=sb_display_url().sb_query_char().'show&amp;file_name='.URLencode($media_name);
	}
	return sb_ampersand_entity($media_name);
}

// Returns correct MIME type
function sb_mime_type($media_name) {
	global $filetypes;
	$extension = strtolower(substr($media_name, strrpos($media_name, '.') + 1));
	if (array_key_exists ($extension, $filetypes))
		return ' type="'.$filetypes[$extension]['content-type'].'"';
}

?><rss xmlns:itunes="http://www.itunes.com/dtds/podcast-1.0.dtd" version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
<channel>
	<atom:link href="<?php sb_ampersand_entity(get_option('sb_podcast')) ?>" rel="self" type="application/rss+xml" />
	<title><?php echo sb_ampersand_entity(get_bloginfo('name')) ?> Podcast</title>
	<itunes:author></itunes:author>
	<description><?php echo get_bloginfo('description') ?></description>
	<link><?php echo sb_ampersand_entity(get_bloginfo('home')) ?></link>
	<language>en-us</language>
	<copyright></copyright>
	<itunes:explicit>no</itunes:explicit>
	<itunes:owner>
		<itunes:name></itunes:name>
		<itunes:email></itunes:email>
	</itunes:owner>

    <lastBuildDate><?php sb_print_iso_date($sermon[0]) ?></lastBuildDate>
    <pubDate><?php sb_print_iso_date($sermon[0]) ?></pubDate>
    <generator>Wordpress Sermon Browser plugin <?php SB_CURRENT_VERSION ?> (http://www.4-14.org.uk/sermon-browser)</generator>
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
			if (is_array($media['Files']) | is_array($media['URLs'])) {
				foreach ($media as $media_type => $media_names)
					if (is_array($media_names) && $media_type != 'Code')
						foreach ((array)$media_names as $media_name)
							if (in_array(strtolower(substr($media_name, -3)), $accepted_extensions)) {
								$mp3count++;
?>
<item>
		<guid><?php echo sb_podcast_file_url($media_name, $media_type) ?></guid>
		<title><?php echo sb_ampersand_entity(stripslashes($sermon->title)) ?></title>
		<link><?php echo sb_display_url().sb_query_char().'sermon_id='.$sermon->id ?></link>
		<itunes:author><?php echo sb_ampersand_entity(stripslashes($sermon->preacher)) ?></itunes:author>
		<description><![CDATA[<?php echo stripslashes($sermon->description) ?>]]></description>
		<itunes:summary><![CDATA[<?php echo stripslashes($sermon->description) ?>]]></itunes:summary>
		<enclosure url="<?php echo sb_podcast_file_url($media_name, $media_type).'" '.sb_media_size($media_name, $media_type).sb_mime_type($media_name); ?> />
		<itunes:duration><?php echo sb_mp3_length($media_name, $media_type) ?></itunes:duration>
		<category><?php echo stripslashes($sermon->service) ?></category>
		<pubDate><?php sb_print_iso_date($sermon) ?></pubDate>
	</item>
	<?php
				}
			}
		}
	?>
</channel>
</rss>