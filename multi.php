<div class="sermon-browser">
	<h2>Filters</h2>		
	<?php bb_print_filters() ?>
   	<div style="clear:both"><div class="podcastcustom"><a href="<?php echo bb_podcast_url() ?>"><img alt="Subscribe to custom podcast" title="Subscribe to custom podcast" class="podcasticon" src="<?php echo get_bloginfo("wpurl") ?>/wp-content/plugins/sermonbrowser/icons/podcast_custom.png"/></a><span><a href="<?php echo bb_podcast_url() ?>">Subscribe to custom podcast</a></span><br />(new sermons that match this <b>search</b>)</div><div class="podcastall"><a href="<?php echo get_option("sb_podcast") ?>"><img alt="Subscribe to full podcast" title="Subscribe to full podcast" class="podcasticon" src="<?php echo get_bloginfo("wpurl") ?>/wp-content/plugins/sermonbrowser/icons/podcast.png"/></a><span><a href="<?php echo get_option("sb_podcast") ?>">Subscribe to full podcast</a></span><br />(<b>all</b> new sermons)</div>
</div>
	<h2>Sermons (<?php bb_print_sermons_count() ?>)</h2>   	
   	<div class="floatright"><?php bb_print_next_page_link() ?></div>
   	<div class="floatleft"><?php bb_print_prev_page_link() ?></div>
	<table class="sermons">
	<?php foreach ($sermons as $sermon): ?><?php $stuff = bb_get_stuff($sermon) ?>	
		<tr>
			<td class="sermon-title"><a href="<?php bb_print_sermon_link($sermon) ?>"><?php echo stripslashes($sermon->title) ?></a></td>
		</tr>
		<tr>
			<td class="sermon-passage"><?php $foo = unserialize($sermon->start); $bar = unserialize($sermon->end); echo bb_get_books($foo[0], $bar[0]) ?> (Part of the <a href="<?php bb_print_series_link($sermon) ?>"><?php echo stripslashes($sermon->series) ?></a> series).</td>
		</tr>
		<tr>
			<td class="files"><?php foreach ((array) $stuff["Files"] as $file): ?><?php bb_print_file($file) ?><?php endforeach ?></td>
		</tr>
		<tr>
			<td class="urls"><?php foreach ((array) $stuff["URLs"] as $url): ?><?php bb_print_url($url) ?><?php endforeach ?></td>
		</tr>
		<tr>
			<td class="embed"><?php foreach ((array) $stuff["Code"] as $code): ?><?php bb_print_code($code) ?><?php endforeach ?></td>
		</tr>
		<tr>
			<td class="preacher">Preached by <a href="<?php bb_print_preacher_link($sermon) ?>"><?php echo stripslashes($sermon->preacher) ?></a> on <?php echo date("j F Y", strtotime($sermon->date)) ?> (<a href="<?php bb_print_service_link($sermon) ?>"><?php echo stripslashes($sermon->service) ?></a>).</td>
		</tr>
   	<?php endforeach ?>
	</table>
   	<div class="floatright"><?php bb_print_next_page_link() ?></div>
   	<div class="floatleft"><?php bb_print_prev_page_link() ?></div>
   	<div id="poweredbysermonbrowser">Powered by <a href="http://www.4-14.org.uk/sermon-browser">Sermon Browser</a></div>
</div>