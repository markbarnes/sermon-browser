<div class="sermon-browser">
	<h2>Filters</h2>		
	<?php sb_print_filters() ?>
   	<div style="clear:both"><div class="podcastcustom"><a href="<?php echo sb_podcast_url() ?>"><img alt="Subscribe to custom podcast" title="Subscribe to custom podcast" class="podcasticon" src="<?php echo get_bloginfo("wpurl") ?>/wp-content/plugins/sermon-browser/icons/podcast_custom.png"/></a><span><a href="<?php echo sb_podcast_url() ?>">Subscribe to custom podcast</a></span><br />(new sermons that match this <b>search</b>)</div><div class="podcastall"><a href="<?php echo get_option("sb_podcast") ?>"><img alt="Subscribe to full podcast" title="Subscribe to full podcast" class="podcasticon" src="<?php echo get_bloginfo("wpurl") ?>/wp-content/plugins/sermon-browser/icons/podcast.png"/></a><span><a href="<?php echo get_option("sb_podcast") ?>">Subscribe to full podcast</a></span><br />(<b>all</b> new sermons)</div>
</div>
	<h2>Sermons (<?php sb_print_sermons_count() ?>)</h2>   	
   	<div class="floatright"><?php sb_print_next_page_link() ?></div>
   	<div class="floatleft"><?php sb_print_prev_page_link() ?></div>
	<table class="sermons">
	<?php foreach ($sermons as $sermon): ?><?php $stuff = sb_get_stuff($sermon) ?>	
		<tr>
			<td class="sermon-title"><a href="<?php sb_print_sermon_link($sermon) ?>"><?php echo stripslashes($sermon->title) ?></a></td>
		</tr>
		<tr>
			<td class="sermon-passage"><?php $foo = unserialize($sermon->start); $bar = unserialize($sermon->end); echo sb_get_books($foo[0], $bar[0]) ?> (Part of the <a href="<?php sb_print_series_link($sermon) ?>"><?php echo stripslashes($sermon->series) ?></a> series).</td>
		</tr>
		<tr>
			<td class="files"><?php foreach ((array) $stuff["Files"] as $file): ?><?php sb_print_url($file) ?><?php endforeach ?></td>
		</tr>
		<tr>
			<td class="embed"><?php foreach ((array) $stuff["Code"] as $code): ?><?php sb_print_code($code) ?><?php endforeach ?></td>
		</tr>
		<tr>
			<td class="preacher">Preached by <a href="<?php sb_print_preacher_link($sermon) ?>"><?php echo stripslashes($sermon->preacher) ?></a> on <?php echo date("j F Y", strtotime($sermon->date)) ?> (<a href="<?php sb_print_service_link($sermon) ?>"><?php echo stripslashes($sermon->service) ?></a>). <?php sb_edit_link($sermon->id) ?></td>
		</tr>
   	<?php endforeach ?>
	</table>
   	<div class="floatright"><?php sb_print_next_page_link() ?></div>
   	<div class="floatleft"><?php sb_print_prev_page_link() ?></div>
   	<div id="poweredbysermonbrowser">Powered by <a href="http://www.4-14.org.uk/sermon-browser">Sermon Browser</a></div>
</div>