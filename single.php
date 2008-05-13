<div class="sermon-browser-results">

	<h2>View single sermon</h2>

	Title: <?php echo stripslashes($sermon["Sermon"]->title) ?><br />

	Preacher: <a href="<?php bb_print_preacher_link($sermon["Sermon"]) ?>"><?php echo stripslashes($sermon["Sermon"]->preacher) ?></a><br />

	Series: <a href="<?php bb_print_series_link($sermon["Sermon"]) ?>"><?php echo stripslashes($sermon["Sermon"]->series) ?></a><br />

	Service: <a href="<?php bb_print_service_link($sermon["Sermon"]) ?>"><?php echo stripslashes($sermon["Sermon"]->service) ?></a><br />

	Date: <?php echo date("j F Y", strtotime($sermon["Sermon"]->date)) ?><br />

	<?php for ($i = 0; $i < count($sermon["Sermon"]->start); $i++): ?>	

		<?php echo $sermon["Sermon"]->start[$i]["book"] ?>, <?php echo $sermon["Sermon"]->start[$i]["chapter"] ?> : <?php echo $sermon["Sermon"]->start[$i]["verse"] ?> - <?php echo $sermon["Sermon"]->end[$i]["book"] ?>, <?php echo $sermon["Sermon"]->end[$i]["chapter"] ?> : <?php echo $sermon["Sermon"]->end[$i]["verse"] ?><br />

	<?php endfor ?>

	Files:<br />

	<?php foreach ($sermon["Files"] as $file): ?>	

		<?php bb_print_file($file->name) ?>

	<?php endforeach ?>

	<?php bb_print_next_sermon_link($sermon["Sermon"]) ?>

	<?php bb_print_prev_sermon_link($sermon["Sermon"]) ?>

	Same day: <?php bb_print_sameday_sermon_link($sermon["Sermon"]) ?>

</div>		
