<a style="font-weight:bold; font-size: 120%" href="<?php echo get_option("sb_podcast") ?>">Subscribe to our podcast</a><br />(automatically downloads <b>all</b> new sermons to your computer, iPod or MP3 player).

<div class="sermon-browser">
	<h2>Filters</h2>		
	<?php bb_print_filters() ?>
	<h2>Sermons (<?php bb_print_sermons_count() ?>)</h2>   	
   	<div class="floatright"><?php bb_print_next_page_link() ?></div>
   	<div class="floatleft"><?php bb_print_prev_page_link() ?></div>
   	<div style="clear:both"><a href="<?php bb_print_podcast_url() ?>">Subscribe to a podcast for this search</a><br />(automatically downloads sermons <b>that match this search</b> to your computer, iPod or MP3 player).</div>
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
</div>