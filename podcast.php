<?php
//Prints ISO date for podcast
function sb_print_iso_date($sermon) {
	echo date('d M Y H:i:s O', strtotime($sermon->date.' '.$sermon->time));
}

//Prints first .mp3 file
function sb_first_mp3($sermon) {
	$user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
	$itunes = stristr($user_agent, 'itunes') ? true : false;
	$stuff = sb_get_stuff($sermon);
	foreach ((array) $stuff['Files'] as $file) {
		if (strtolower(substr($file, strrpos($file, '.') + 1)) == 'mp3') {
			if (substr($file,0,7) == "http://") {
				if (!$itunes) $file=sb_get_value('wordpress_url').'?show&amp;url='.URLencode($file);
			} else {
				if ($itunes)
					$file=sb_get_value('wordpress_url').get_option('sb_sermon_upload_dir').URLencode($file);
				else
					$file=sb_get_value('wordpress_url').'?show&amp;file_name='.URLencode($file);
			}
			return $file;
			break;
		}
	}
}

//Prints size of first .mp3 file
function sb_first_mp3_size($sermon) {
	$stuff = sb_get_stuff($sermon);
	foreach ((array) $stuff['Files'] as $file) {
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
?><rss xmlns:itunes="http://www.itunes.com/dtds/podcast-1.0.dtd" version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
<channel>
	<atom:link href="<?php echo get_option('sb_podcast') ?>" rel="self" type="application/rss+xml" />
    <title><?php echo get_bloginfo('name')?> Podcast</title>
    <itunes:author></itunes:author>
    <description><?php echo get_bloginfo('description') ?></description>
    <link><?php echo get_bloginfo('url') ?></link>
    <language>en-us</language>
    <copyright></copyright>
	<itunes:explicit>no</itunes:explicit>
    <itunes:owner>
    	<itunes:name></itunes:name>
    	<itunes:email>webmaster@example.com</itunes:email>
    </itunes:owner>

    <lastBuildDate><?php sb_print_iso_date($sermon[0]) ?></lastBuildDate>
    <pubDate><?php sb_print_iso_date($sermon[0]) ?></pubDate>
    <generator>Wordpress Sermon Browser plugin (http://www.4-14.org.uk/sermon-browser)</generator>
    <docs>http://blogs.law.harvard.edu/tech/rss</docs>
    <category>Religion &amp; Spirituality</category>
    <itunes:category text="Religion &amp; Spirituality"></itunes:category>
<?php foreach ($sermons as $sermon):  ++$i; if($i>15) break; ?>
		<item>
			<guid><?php echo sb_first_mp3($sermon) ?></guid>
        	<title><?php echo stripslashes($sermon->title) ?></title>
        	<link><?php echo sb_display_url().sb_query_char().'sermon_id='.$sermon->id ?></link>
        	<description><?php echo stripslashes($sermon->description) ?></description>
        	<enclosure url="<?php echo sb_first_mp3($sermon) ?>" length="<?php echo sb_first_mp3_size($sermon) ?>" type="audio/mpeg"/>
        	<category><?php echo stripslashes($sermon->service) ?></category>
        	<pubDate><?php sb_print_iso_date($sermon) ?></pubDate>
        </item>
		<?php endforeach ?>
</channel>
</rss>