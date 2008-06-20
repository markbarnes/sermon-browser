<!-- must include xmlns:itunes tag -->
<rss xmlns:itunes="http://www.itunes.com/dtds/podcast-1.0.dtd" version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
<channel>
	<atom:link href="<?php echo get_option('sb_podcast') ?>" rel="self" type="application/rss+xml" />
    <title><?php echo get_bloginfo('name') ?></title>
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
    <docs>http://blogs.law.harvard.edu/tech/rss</docs>
    <category>Religion &amp; Spirituality</category>
    <itunes:category text="Religion &amp; Spirituality"></itunes:category>
		<?php foreach ($sermons as $sermon):  ++$i; if($i>15) break; ?>
		<item>
			<guid><?php sb_print_first_mp3($sermon) ?></guid>
        	<title><?php echo stripslashes($sermon->title) ?></title>
        	<link><?php sb_print_first_mp3($sermon) ?></link>
        	<description><?php echo stripslashes($sermon->description) ?></description>
        	<enclosure url="<?php sb_print_first_mp3($sermon) ?>" length="<?php sb_print_first_mp3_size($sermon) ?>" type="audio/mpeg"/>
        	<category><?php echo stripslashes($sermon->service) ?></category>
        	<pubDate><?php sb_print_iso_date($sermon) ?></pubDate>
        </item>
		<?php endforeach ?>
</channel>
</rss>