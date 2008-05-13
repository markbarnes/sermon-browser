<div class="sermon-browser">

	<h2>Filters</h2>		

   <?php bb_print_filters() ?>

   <h2>Sermons (<?php bb_print_sermons_count() ?>)</h2>   	

   <?php foreach ($sermons as $sermon): ?>	

   		<h3><a href="<?php bb_print_sermon_link($sermon) ?>"><?php echo stripslashes($sermon->title) ?></a></h3>	

   		Preacher: <a href="<?php bb_print_preacher_link($sermon) ?>"><?php echo stripslashes($sermon->preacher) ?></a><br />	

   		Series: <a href="<?php bb_print_series_link($sermon) ?>"><?php echo stripslashes($sermon->series) ?></a><br />	

   		Service: <a href="<?php bb_print_service_link($sermon) ?>"><?php echo stripslashes($sermon->service) ?></a><br />	

   		Date: <?php echo date("j F Y", strtotime($sermon->date)) ?><br />

   		Passage: <?php $foo = unserialize($sermon->start); $bar = unserialize($sermon->end); echo $foo[0]["book"] ?>, <?php echo $foo[0]["chapter"] ?> : <?php echo $foo[0]["verse"] ?> - <?php echo $bar[0]["book"] ?>, <?php echo $bar[0]["chapter"] ?> : <?php echo $bar[0]["verse"] ?><br />

		Files:<br />

		<?php $files = bb_get_files($sermon) ?><?php foreach ($files as $file): ?>	

			<?php bb_print_file($file->name) ?>

		<?php endforeach ?> 

   	<?php endforeach ?>

   	<?php bb_print_next_page_link() ?>

   	<?php bb_print_prev_page_link() ?>

</div>