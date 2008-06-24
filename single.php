<div class="sermon-browser-results">
	<h2><?php echo stripslashes($sermon["Sermon"]->title) ?> <span class="scripture">(<?php for ($i = 0; $i < count($sermon["Sermon"]->start); $i++): ?><?php echo sb_get_books($sermon["Sermon"]->start[$i], $sermon["Sermon"]->end[$i]) ?> <?php endfor ?>)</span> <?php sb_edit_link($_GET["sermon_id"]) ?></h2>
	<?php sb_print_preacher_image($sermon["Sermon"]) ?><span class="preacher"><a href="<?php sb_print_preacher_link($sermon["Sermon"]) ?>"><?php echo stripslashes($sermon["Sermon"]->preacher) ?></a>, <?php echo date("j F Y", strtotime($sermon["Sermon"]->date)) ?></span><br />
	Part of the <a href="<?php sb_print_series_link($sermon["Sermon"]) ?>"><?php echo stripslashes($sermon["Sermon"]->series) ?></a> series, preached at a <a href="<?php sb_print_service_link($sermon["Sermon"]) ?>"><?php echo stripslashes($sermon["Sermon"]->service) ?></a> service<br />
	Tags: <?php sb_print_tags($sermon["Tags"]) ?><br />
	<?php foreach ((array) $sermon["Files"] as $file): ?>
		<?php sb_print_url_link($file) ?>
	<?php endforeach ?>
	<?php foreach ((array) $sermon["Code"] as $code): ?>
		<br /><?php sb_print_code($code) ?><br />
	<?php endforeach ?>
	<?php sb_print_preacher_description($sermon["Sermon"]) ?>
	<table class="nearby-sermons">
		<tr>
			<th class="earlier">Earlier:</th>
			<th>Same day:</th>
			<th class="later">Later:</th>
		</tr>
		<tr>
			<td class="earlier"><?php sb_print_prev_sermon_link($sermon["Sermon"]) ?></td>
			<td><?php sb_print_sameday_sermon_link($sermon["Sermon"]) ?></td>
			<td class="later"><?php sb_print_next_sermon_link($sermon["Sermon"]) ?></td>
		</tr>
	</table>
	<?php for ($i = 0; $i < count($sermon["Sermon"]->start); $i++): echo sb_add_bible_text ($sermon["Sermon"]->start[$i], $sermon["Sermon"]->end[$i], "esv"); endfor ?>
   	<div id="poweredbysermonbrowser">Powered by <a href="http://www.4-14.org.uk/sermon-browser">Sermon Browser</a></div>
</div>