<?php
//Prints ISO date for podcast
function sb_print_iso_date($sermon) {
	$sermon_time = $sermon->time;
	if ($sermon_time == "")
		$sermon_time = sb_default_time ($sermon->sid);
	echo date('D, d M Y H:i:s', strtotime($sermon->date.' '.$sermon_time))." +0000";
}

//Prints size of first .mp3 file
function sb_first_mp3_size($sermon) {
	$stuff = sb_get_stuff($sermon);
	$stuff = array_merge((array)$stuff['Files'], (array)$stuff['URLs']);
	foreach ((array) $stuff as $file) {
		if (strtolower(substr($file, strrpos($file, '.') + 1)) == 'mp3') {
			if (substr($file,0,7) == "http://") {
				if(ini_get('allow_url_fopen')) {
					$headers = array_change_key_case(get_headers($file, 1),CASE_LOWER);
					$filesize = $headers['content-length'];
					if ($filesize) return $filesize;
				}
			} else {
				return @filesize(sb_get_value('wordpress_path').get_option('sb_sermon_upload_dir').$file);
			break;
			}
		}
	}
}

//Prints size of first .mp3 file
function sb_first_mp3_length($sermon) {
	$stuff = sb_get_stuff($sermon);
	$stuff = array_merge((array)$stuff['Files'], (array)$stuff['URLs']);
	foreach ((array) $stuff as $file) {
		if (strtolower(substr($file, strrpos($file, '.') + 1)) == 'mp3')
			if (substr($file,0,7) == "http://")
				return;
			else {
				$mp3file = new mp3file(sb_get_value('wordpress_path').get_option('sb_sermon_upload_dir').$file);
				$meta = $mp3file->get_metadata();
				return $meta['Length'];
			}
	}
}

//Replaces & with &amp;
function sb_ampersand_entity ($string) {
	return str_replace('&amp;&amp;', '&amp;', str_replace('&', '&amp;', $string));
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
<?php foreach ($sermons as $sermon):  ++$i; if($i>15) break;
	$stuff = sb_get_stuff($sermon);
	if ($stuff['Files'][0] != "") {
	?>
	<item>
		<guid><?php echo sb_first_mp3($sermon) ?></guid>
		<title><?php echo sb_ampersand_entity(stripslashes($sermon->title)) ?></title>
		<link><?php echo sb_display_url().sb_query_char().'sermon_id='.$sermon->id ?></link>
		<itunes:author><?php echo sb_ampersand_entity(stripslashes($sermon->preacher)) ?></itunes:author>
		<description><![CDATA[<?php echo stripslashes($sermon->description) ?>]]></description>
		<itunes:summary><![CDATA[<?php echo stripslashes($sermon->description) ?>]]></itunes:summary>
		<enclosure url="<?php echo sb_first_mp3($sermon) ?>" length="<?php echo sb_first_mp3_size($sermon) ?>" type="audio/mpeg"/>
		<itunes:duration><?php echo sb_first_mp3_length($sermon) ?></itunes:duration>
		<category><?php echo stripslashes($sermon->service) ?></category>
		<pubDate><?php sb_print_iso_date($sermon) ?></pubDate>
	</item>
	<?php }
		endforeach ?>
</channel>
</rss>