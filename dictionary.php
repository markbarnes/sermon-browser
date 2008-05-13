<?php 

$mdict = array(
	'[filters_form]' => '<?php bb_print_filters() ?>',
	'[sermons_count]' => '<?php bb_print_sermons_count() ?>',
	'[sermons_loop]' => '<?php foreach ($sermons as $sermon): ?>',
	'[/sermons_loop]' => '<?php endforeach ?>',
	'[sermon_title]' => '<a href="<?php bb_print_sermon_link($sermon) ?>"><?php echo stripslashes($sermon->title) ?></a>',
	'[preacher_link]' => '<a href="<?php bb_print_preacher_link($sermon) ?>"><?php echo stripslashes($sermon->preacher) ?></a>',
	'[series_link]' => '<a href="<?php bb_print_series_link($sermon) ?>"><?php echo stripslashes($sermon->series) ?></a>',
	'[service_link]' => '<a href="<?php bb_print_service_link($sermon) ?>"><?php echo stripslashes($sermon->service) ?></a>',	
	'[date]' => '<?php echo date("j F Y", strtotime($sermon->date)) ?>',
	'[first_bible_passage]' => '<?php $foo = unserialize($sermon->start); $bar = unserialize($sermon->end); echo $foo[0]["book"] ?>, <?php echo $foo[0]["chapter"] ?> : <?php echo $foo[0]["verse"] ?> - <?php echo $bar[0]["book"] ?>, <?php echo $bar[0]["chapter"] ?> : <?php echo $bar[0]["verse"] ?>',
	'[files_loop]' => '<?php $files = bb_get_files($sermon) ?><?php foreach ($files as $file): ?>',
	'[/files_loop]' => '<?php endforeach ?>',
	'[file]' => '<?php bb_print_file($file->name) ?>',
	'[next_page]' => '<?php bb_print_next_page_link() ?>',
	'[previous_page]' => '<?php bb_print_prev_page_link() ?>',	
);

$sdict = array(
	'[sermon_title]' => '<?php echo stripslashes($sermon["Sermon"]->title) ?>',
	'[preacher_link]' => '<a href="<?php bb_print_preacher_link($sermon["Sermon"]) ?>"><?php echo stripslashes($sermon["Sermon"]->preacher) ?></a>',
	'[series_link]' => '<a href="<?php bb_print_series_link($sermon["Sermon"]) ?>"><?php echo stripslashes($sermon["Sermon"]->series) ?></a>',
	'[service_link]' => '<a href="<?php bb_print_service_link($sermon["Sermon"]) ?>"><?php echo stripslashes($sermon["Sermon"]->service) ?></a>',
	'[date]' => '<?php echo date("j F Y", strtotime($sermon["Sermon"]->date)) ?>',
	'[passages_loop]' => '<?php for ($i = 0; $i < count($sermon["Sermon"]->start); $i++): ?>',
	'[/passages_loop]' => '<?php endfor ?>',
	'[start_passage]' => '<?php echo $sermon["Sermon"]->start[$i]["book"] ?>, <?php echo $sermon["Sermon"]->start[$i]["chapter"] ?> : <?php echo $sermon["Sermon"]->start[$i]["verse"] ?>',
	'[end_passage]' => '<?php echo $sermon["Sermon"]->end[$i]["book"] ?>, <?php echo $sermon["Sermon"]->end[$i]["chapter"] ?> : <?php echo $sermon["Sermon"]->end[$i]["verse"] ?>',
	'[files_loop]' => '<?php foreach ($sermon["Files"] as $file): ?>',
	'[/files_loop]' => '<?php endforeach ?>',
	'[file]' => '<?php bb_print_file($file->name) ?>',
	'[next_sermon]' => '<?php bb_print_next_sermon_link($sermon["Sermon"]) ?>',
	'[prev_sermon]' => '<?php bb_print_prev_sermon_link($sermon["Sermon"]) ?>',
	'[sameday_sermon]' => '<?php bb_print_sameday_sermon_link($sermon["Sermon"]) ?>',
);

?>