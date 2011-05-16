<?php
function sb_install() {
	global $wpdb;
	$sermonUploadDir = sb_get_default('sermon_path');
	require (SB_INCLUDES_DIR.'/dictionary.php');
	if (!is_dir(SB_ABSPATH.$sermonUploadDir))
		sb_mkdir(SB_ABSPATH.$sermonUploadDir);
	if (!is_dir(SB_ABSPATH.$sermonUploadDir.'images'))
		sb_mkdir(SB_ABSPATH.$sermonUploadDir.'images');

	$table_name = "{$wpdb->prefix}sb_preachers";
	if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") != $table_name) {
		$sql = "CREATE TABLE {$table_name} (
			id INT(10) NOT NULL AUTO_INCREMENT,
			name VARCHAR(30) NOT NULL,
			description TEXT NOT NULL,
			image VARCHAR(255) NOT NULL,
			PRIMARY KEY (id)
		)";
		$wpdb->query ($sql);
		$wpdb->query ("INSERT INTO {$table_name} (name, description, image) VALUES ('C H Spurgeon', '', '')");
		$wpdb->query ("INSERT INTO {$table_name} (name, description, image) VALUES ('Martyn Lloyd-Jones', '', '')");
	}

	$table_name = "{$wpdb->prefix}sb_series";
	if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") != $table_name) {
		$sql = "CREATE TABLE {$table_name} (
			id INT(10) NOT NULL AUTO_INCREMENT,
			name VARCHAR(255) NOT NULL,
			page_id INT(10) NOT NULL,
			PRIMARY KEY (id)
		)";
		$wpdb->query ($sql);
		$wpdb->query ("INSERT INTO {$table_name} (name, page_id) VALUES ('Exposition of the Psalms', 0)");
		$wpdb->query ("INSERT INTO {$table_name} (name, page_id) VALUES ('Exposition of Romans', 0)");
	}

	$table_name = "{$wpdb->prefix}sb_services";
	if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") != $table_name) {
		$sql = "CREATE TABLE {$table_name} (
			id INT(10) NOT NULL AUTO_INCREMENT,
			name VARCHAR(255) NOT NULL,
			time VARCHAR(5) NOT NULL,
			PRIMARY KEY (id)
		)";
		$wpdb->query ($sql);
		$wpdb->query ("INSERT INTO {$table_name} (name, time) VALUES ('Sunday Morning', '10:30')");
		$wpdb->query ("INSERT INTO {$table_name} (name, time) VALUES ('Sunday Evening', '18:00')");
		$wpdb->query ("INSERT INTO {$table_name} (name, time) VALUES ('Midweek Meeting', '19:00')");
		$wpdb->query ("INSERT INTO {$table_name} (name, time) VALUES ('Special event', '20:00')");
	}

	$table_name = "{$wpdb->prefix}sb_sermons";
	if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") != $table_name) {
		$sql = "CREATE TABLE {$table_name} (
			id INT(10) NOT NULL AUTO_INCREMENT,
			title VARCHAR(255) NOT NULL,
			preacher_id INT(10) NOT NULL,
			datetime DATETIME NOT NULL,
			service_id INT(10) NOT NULL,
			series_id INT(10) NOT NULL,
			start TEXT NOT NULL,
			end TEXT NOT NULL,
			description TEXT,
			time VARCHAR (5),
			override TINYINT (1),
			page_id INT(10) NOT NULL,
			PRIMARY KEY (id)
		)";
		$wpdb->query ($sql);
	}

	$table_name = "{$wpdb->prefix}sb_books_sermons";
	if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") != $table_name) {
		$sql = "CREATE TABLE {$table_name} (
			id INT(10) NOT NULL AUTO_INCREMENT,
			book_name VARCHAR(30) NOT NULL,
			chapter INT(10) NOT NULL,
			verse INT(10) NOT NULL,
			`order` INT(10) NOT NULL,
			type VARCHAR (30) DEFAULT NULL,
			sermon_id INT(10) NOT NULL,
			PRIMARY KEY (id),
			KEY sermon_id (sermon_id)
		)";
		$wpdb->query ($sql);
	}

	$table_name = "{$wpdb->prefix}sb_books";
	if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") != $table_name) {
		$sql = "CREATE TABLE {$table_name} (
			id INT(10) NOT NULL AUTO_INCREMENT,
			name VARCHAR(30) NOT NULL,
			PRIMARY KEY (id)
		)";
		$wpdb->query ($sql);
	}

	$table_name = "{$wpdb->prefix}sb_stuff";
	if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") != $table_name) {
		$sql = "CREATE TABLE {$table_name} (
			id INT(10) NOT NULL AUTO_INCREMENT ,
			type VARCHAR(30) NOT NULL,
			name TEXT NOT NULL,
			sermon_id INT(10) NOT NULL,
			count INT(10) NOT NULL,
			duration VARCHAR (6) NOT NULL,
			PRIMARY KEY (id)
		)";
		$wpdb->query ($sql);
	}

	$table_name = "{$wpdb->prefix}sb_tags";
	if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") != $table_name) {
		$sql = "CREATE TABLE {$table_name} (
			id int(10) NOT NULL auto_increment,
			name varchar(255) default NULL,
			PRIMARY KEY (id),
			UNIQUE KEY name (name)
		)";
		$wpdb->query ($sql);
   }

	$table_name = "{$wpdb->prefix}sb_sermons_tags";
	if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") != $table_name) {
		$sql = "CREATE TABLE {$table_name} (
			id INT(10) NOT NULL AUTO_INCREMENT,
			sermon_id INT(10) NOT NULL,
			tag_id INT(10) NOT NULL,
			INDEX (sermon_id),
			PRIMARY KEY (id)
		)";
		$wpdb->query ($sql);
   }

	sb_update_option('upload_dir', $sermonUploadDir);
	sb_update_option('upload_url', sb_get_default('attachment_url'));
	sb_update_option('podcast_url', site_url().'?podcast');
	sb_update_option('display_method', 'dynamic');
	sb_update_option('sermons_per_page', '10');
	sb_update_option('search_template', sb_default_multi_template());
	sb_update_option('single_template', sb_default_single_template());
	sb_update_option('css_style', sb_default_css());
	sb_update_option('style_date_modified', strtotime('now'));
	sb_update_option('search_output', strtr(sb_default_multi_template(), sb_search_results_dictionary()));
	sb_update_option('single_output', strtr(sb_default_single_template(), sb_sermon_page_dictionary()));
	$books = sb_get_default('bible_books');
	foreach ($books as $book)
		$wpdb->query("INSERT INTO {$wpdb->prefix}sb_books VALUES (null, '{$book}')");
	sb_update_option('db_version', SB_DATABASE_VERSION);
	sb_update_option('filter_type', 'oneclick');
	sb_update_option('filter_hide', 'hide');
	sb_update_option('import_prompt',true);
	sb_update_option('hide_no_attachments',false);
	sb_update_option('import_title', false);
	sb_update_option('import_artist', false);
	sb_update_option('import_album', false);
	sb_update_option('import_comments', false);
	sb_update_option('import_filename', 'none');
	sb_update_option('mp3_shortcode', '[audio:%SERMONURL%]');
}

//Default template for search results
function sb_default_multi_template () {
$multi = <<<HERE
<div class="sermon-browser">
	[filters_form]
   	<div class="clear">
		<h4>Subscribe to Podcast</h4>
		<table class="podcast">
			<tr>
				<td class="podcastall">
					<table>
						<tr>
							<td class="podcast-icon"><a href="[podcast]">[podcasticon]</a></td>
							<td><strong>All sermons</strong><br /><a href="[itunes_podcast]">iTunes</a> &bull; <a href="[podcast]">Other</a></td>
						</tr>
					</table>
				<td style="width: 2em"> </td>
				<td class="podcastcustom">
					<table>
						<tr>
							<td class="podcast-icon"><a href="[podcast_for_search]">[podcasticon_for_search]</a></td>
							<td><strong>Filtered sermons</strong><br /><a href="[itunes_podcast_for_search]">iTunes</a> &bull; <a href="[podcast_for_search]">Other</a></td>
						</tr>
					</table>
				</td>
			</tr>
		</table>
	</div>
	<h2 class="clear">Sermons ([sermons_count])</h2>
   	<div class="floatright">[next_page]</div>
   	<div class="floatleft">[previous_page]</div>
	<table class="sermons">
	[sermons_loop]
		<tr>
			<td class="sermon-title">[sermon_title]</td>
		</tr>
		<tr>
			<td class="sermon-passage">[first_passage] (Part of the [series_link] series).</td>
		</tr>
		<tr>
			<td class="files">[files_loop][file][/files_loop]</td>
		</tr>
		<tr>
			<td class="embed">[embed_loop][embed][/embed_loop]</td>
		</tr>
		<tr>
			<td class="preacher">Preached by [preacher_link] on [date] ([service_link]). [editlink]</td>
		</tr>
   	[/sermons_loop]
	</table>
   	<div class="floatright">[next_page]</div>
   	<div class="floatleft">[previous_page]</div>
   	[creditlink]
</div>
HERE;
	return $multi;
}

//Default template for single sermon page
function sb_default_single_template () {
$single = <<<HERE
<div class="sermon-browser-results">
	<h2>[sermon_title] <span class="scripture">([passages_loop][passage][/passages_loop])</span> [editlink]</h2>
	[preacher_image]<span class="preacher">[preacher_link], [date]</span><br />
	Part of the [series_link] series, preached at a [service_link] service<br />
	<div class="sermon-description">[sermon_description]</div>
	<p class="sermon-tags">Tags: [tags]</p>
	[files_loop]
		[file_with_download]
	[/files_loop]
	[embed_loop]
		<br />[embed]<br />
	[/embed_loop]
	[preacher_description]
	<table class="nearby-sermons">
		<tr>
			<th class="earlier">Earlier:</th>
			<th>Same day:</th>
			<th class="later">Later:</th>
		</tr>
		<tr>
			<td class="earlier">[prev_sermon]</td>
			<td>[sameday_sermon]</td>
			<td class="later">[next_sermon]</td>
		</tr>
	</table>
	[esvtext]
   	[creditlink]
</div>
HERE;
	return $single;
}

//Default CSS
function sb_default_css () {
$css = <<<HERE
.sermon-browser h2 {
	clear: both;
}

#content div.sermon-browser table, #content div.sermon-browser td {
	border-top: none;
	border-bottom: none;
	border-left: none;
	border-right: none;
}

#content div.sermon-browser tr td {
	padding: 4px 0;
}

#content div.sermon-browser table.podcast table {
	margin: 0 1em;
}

#content div.sermon-browser td.sermon-title, #content div.sermon-browser td.sermon-passage {
	font-family: "Helvetica Neue",Arial,Helvetica,"Nimbus Sans L",sans-serif;
}

div.sermon-browser table.sermons {
	width: 100%;
	clear:both;
}

#content div.sermon-browser table.sermons td.sermon-title {
	font-weight:bold;
	font-size: 140%;
	padding-top: 2em;
}

div.sermon-browser table.sermons td.sermon-passage {
	font-weight:bold;
	font-size: 110%;
}

#content div.sermon-browser table.sermons td.preacher {
	border-bottom: 1px solid #444444;
	padding-bottom: 1em;
}

div.sermon-browser table.sermons td.files img {
	border: none;
	margin-right: 24px;
}

table.sermonbrowser td.fieldname {
	font-weight:bold;
	padding-right: 10px;
	vertical-align:bottom;
}

table.sermonbrowser td.field input, table.sermonbrowser td.field select{
	width: 170px;
}

table.sermonbrowser td.field  #date, table.sermonbrowser td.field #enddate {
	width: 150px;
}

table.sermonbrowser td {
	white-space: nowrap;
	padding-top: 5px;
	padding-bottom: 5px;
}

table.sermonbrowser td.rightcolumn {
	padding-left: 10px;
}

div.sermon-browser div.floatright {
	float: right
}

div.sermon-browser div.floatleft {
	float: left
}

img.sermon-icon , img.site-icon {
	border: none;
}

table.podcast {
	margin: 0 0 1em 0;
}

.podcastall {
	float:left;
	background: #fff0c8 url(wp-content/plugins/sermon-browser/sb-includes/icons/podcast_background.png) repeat-x;
	padding: 0.5em;
	font-size: 1em;
	-moz-border-radius: 7px;
	-webkit-border-radius: 7px;
}

.podcastcustom {
	float:right;
	background: #fce4ff url(wp-content/plugins/sermon-browser/sb-includes/icons/podcast_custom_background.png) repeat-x;
	padding: 0.5em;
	font-size: 1em;
	-moz-border-radius: 7px;
	-webkit-border-radius: 7px;
}

td.podcast-icon {
	padding-right:1em;
}

div.filtered, div.mainfilter {
	text-align: left;
}

div.filter {
	margin-bottom: 1em;
}

.filter-heading {
	font-weight: bold;
}

div.sermon-browser-results span.preacher {
	font-size: 120%;
}

div.sermon-browser-results span.scripture {
	font-size: 80%;
}

div.sermon-browser-results img.preacher {
	float:right;
	margin-left: 1em;
}

div.sermon-browser-results div.preacher-description {
	margin-top: 0.5em;
}

div.sermon-browser-results div.preacher-description span.about {
	font-weight: bold;
	font-size: 120%;
}

span.chapter-num {
	font-weight: bold;
	font-size: 150%;
}

span.verse-num {
	vertical-align:super;
	line-height: 1em;
	font-size: 65%;
}

div.esv span.small-caps {
	font-variant: small-caps;
}

div.net p.poetry {
	font-style: italics;
	margin: 0
}

div.sermon-browser #poweredbysermonbrowser {
	text-align:center;
}
div.sermon-browser-results #poweredbysermonbrowser {
	text-align:right;
}

table.nearby-sermons {
	width: 100%;
	clear:both;
}

table.nearby-sermons td, table.nearby-sermons th {
	text-align: center;
}

table.nearby-sermons .earlier {
	padding-right: 1em;
	text-align: left;
}

table.nearby-sermons .later {
	padding-left: 1em;
	text-align:right;
}

table.nearby-sermons td {
	width: 33%;
	vertical-align: top;
}

ul.sermon-widget {
	list-style-type:none;
	margin:0;
	padding: 0;
}

ul.sermon-widget li {
	list-style-type:none;
	margin:0;
	padding: 0.25em 0;
}

ul.sermon-widget li span.sermon-title {
	font-weight:bold;
}

p.audioplayer_container {
	display:inline !important;
}

div.sb_edit_link {
	display:inline;
}
h2 div.sb_edit_link {
	font-size: 80%;
}

.clear {
	clear:both;
}
HERE;
   return $css;
}

function sb_default_excerpt_template () {
$multi = <<<HERE
<div class="sermon-browser">
	<table class="sermons">
	[sermons_loop]
		<tr>
			<td class="sermon-title">[sermon_title]</td>
		</tr>
		<tr>
			<td class="sermon-passage">[first_passage] (Part of the [series_link] series).</td>
		</tr>
		<tr>
			<td class="files">[files_loop][file][/files_loop]</td>
		</tr>
		<tr>
			<td class="embed">[embed_loop][embed][/embed_loop]</td>
		</tr>
		<tr>
			<td class="preacher">Preached by [preacher_link] on [date] ([service_link]).</td>
		</tr>
   	[/sermons_loop]
	</table>
</div>
HERE;
	return $multi;
}
?>