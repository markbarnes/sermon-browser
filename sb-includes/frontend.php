<?php
// Error message for people using the old function
function display_sermons($options = array()) {
	echo "This function is now deprecated. Use sb_display_sermons or the sermon browser widget, instead.";
}
// Function to display sermons for users to add to their template
function sb_display_sermons($options = array()) {
	$default = array(
		'display_preacher' => 1,
		'display_passage' => 1,
		'display_date' => 1,
		'display_player' => 0,
		'preacher' => 0,
		'service' => 0,
		'series' => 0,
		'limit' => 5,
		'url_only' => 0,
	);
	$options = array_merge($default, (array) $options);
	extract($options);
	if ($url_only == 1)
		$limit = 1;
	$sermons = sb_get_sermons(array(
			'preacher' => $preacher,
			'service' => $service,
			'series' => $series
		),
		array(), 1, $limit
	);
	if ($url_only == 1)
		sb_print_sermon_link($sermons[0]);
	else {
		echo "<ul class=\"sermon-widget\">\r";
		foreach ((array) $sermons as $sermon) {
			echo "\t<li>";
			echo "<span class=\"sermon-title\"><a href=\"";
			sb_print_sermon_link($sermon);
			echo "\">".stripslashes($sermon->title)."</a></span>";
			if ($display_passage) {
				$foo = unserialize($sermon->start);
				$bar = unserialize($sermon->end);
				echo "<span class=\"sermon-passage\"> (".sb_get_books($foo[0], $bar[0]).")</span>";
			}
			if ($display_preacher) {
				echo "<span class=\"sermon-preacher\">".__('by', $sermon_domain)." <a href=\"";
				sb_print_preacher_link($sermon);
				echo "\">".stripslashes($sermon->preacher)."</a></span>";
			}
			if ($display_date)
				echo " <span class=\"sermon-date\">".__('on', $sermon_domain)." ".sb_formatted_date ($sermon)."</span>";
			if ($display_player)
				sb_display_mini_player($sermon);
			echo ".</li>\r";
		}
		echo "</ul>\r";
	}
}

// Displays the widget
function sb_widget_sermon($args, $widget_args=1) {
	global $sermon_domain;
	extract( $args, EXTR_SKIP );
	if ( is_numeric($widget_args) )
		$widget_args = array( 'number' => $widget_args );
	$widget_args = wp_parse_args( $widget_args, array( 'number' => -1 ) );
	extract( $widget_args, EXTR_SKIP );
	$options = sb_get_option('sermons_widget_options');
	if ( !isset($options[$number]) )
		return;
	extract($options[$number]);
	echo $before_widget;
	echo $before_title . $title . $after_title;
	$sermons = sb_get_sermons(array(
			'preacher' => $preacher,
			'service' => $service,
			'series' => $series
		),
		array(), 1, $limit
	);
	$i=0;
	echo "<ul class=\"sermon-widget\">";
	foreach ((array) $sermons as $sermon){
		$i++;
		echo "<li><span class=\"sermon-title\">";
		echo "<a href=".sb_build_url(array('sermon_id' => $sermon->id), true).">".stripslashes($sermon->title)."</a></span>";
		if ($book) {
			$foo = unserialize($sermon->start);
			$bar = unserialize($sermon->end);
			if (isset ($foo[0]) && isset($bar[0]))
				echo " <span class=\"sermon-passage\">(".sb_get_books($foo[0], $bar[0]).")</span>";
		}
		if ($preacherz) {
			echo " <span class=\"sermon-preacher\">".__('by', $sermon_domain)." <a href=\"";
			sb_print_preacher_link($sermon);
			echo "\">".stripslashes($sermon->preacher)."</a></span>";
		}
		if ($date)
			echo " <span class=\"sermon-date\">".__(' on ', $sermon_domain).sb_formatted_date ($sermon)."</span>";
		if ($player)
			sb_display_mini_player($sermon, $i);
		echo ".</li>";
	}
	echo "</ul>";
	echo $after_widget;
}

// Displays the tag cloud in the sidebar
function sb_widget_tag_cloud ($args) {
	global $sermon_domain;
	extract($args);
	echo $before_widget;
	echo $before_title.__('Sermon Browser tags', $sermon_domain).$after_title;
	sb_print_tag_clouds();
	echo $after_widget;
}

function sb_admin_bar_menu () {
	global $wp_admin_bar, $sermon_domain;
	if (!current_user_can('edit_posts') || !class_exists('WP_Admin_Bar'))
		return;
	if (isset($_GET['sermon_id']) && (int)$_GET['sermon_id'] != 0 && current_user_can('publish_pages')) {
		$wp_admin_bar->add_menu(array('id' => 'sermon-browser-menu', 'title' => __('Edit Sermon', $sermon_domain), 'href' => admin_url('admin.php?page=sermon-browser/new_sermon.php&mid='.(int)$_GET['sermon_id'])));
		$wp_admin_bar->add_menu(array('parent' => 'sermon-browser-menu', 'id' => 'sermon-browser-sermons', 'title' => __('List Sermons', $sermon_domain), 'href' => admin_url('admin.php?page=sermon-browser/sermon.php')));
	} else {
		$wp_admin_bar->add_menu(array('id' => 'sermon-browser-menu', 'title' => __('Sermons', $sermon_domain), 'href' => admin_url('admin.php?page=sermon-browser/sermon.php')));
		if (current_user_can('publish_pages'))
			$wp_admin_bar->add_menu(array('parent' => 'sermon-browser-menu', 'id' => 'sermon-browser-add', 'title' => __('Add Sermon', $sermon_domain), 'href' => admin_url('admin.php?page=sermon-browser/new_sermon.php')));
	}
	if (current_user_can('upload_files'))
		$wp_admin_bar->add_menu(array('parent' => 'sermon-browser-menu', 'id' => 'sermon-browser-files', 'title' => __('Files', $sermon_domain), 'href' => admin_url('admin.php?page=sermon-browser/files.php')));
	if (current_user_can('manage_categories')) {
		$wp_admin_bar->add_menu(array('parent' => 'sermon-browser-menu', 'id' => 'sermon-browser-preachers', 'title' => __('Preachers', $sermon_domain), 'href' => admin_url('admin.php?page=sermon-browser/preachers.php')));
		$wp_admin_bar->add_menu(array('parent' => 'sermon-browser-menu', 'id' => 'sermon-browser-series', 'title' => __('Series &amp; Services', $sermon_domain), 'href' => admin_url('admin.php?page=sermon-browser/manage.php')));
	}
	if (current_user_can('manage_options')) {
		$wp_admin_bar->add_menu(array('parent' => 'sermon-browser-menu', 'id' => 'sermon-browser-options', 'title' => __('Options', $sermon_domain), 'href' => admin_url('admin.php?page=sermon-browser/options.php')));
		$wp_admin_bar->add_menu(array('parent' => 'sermon-browser-menu', 'id' => 'sermon-browser-series', 'title' => __('Templates', $sermon_domain), 'href' => admin_url('admin.php?page=sermon-browser/templates.php')));
	}
	if (current_user_can('edit_plugins'))
		$wp_admin_bar->add_menu(array('parent' => 'sermon-browser-menu', 'id' => 'sermon-browser-uninstall', 'title' => __('Uninstall', $sermon_domain), 'href' => admin_url('admin.php?page=sermon-browser/uninstall.php')));
	$wp_admin_bar->add_menu(array('parent' => 'sermon-browser-menu', 'id' => 'sermon-browser-help', 'title' => __('Help', $sermon_domain), 'href' => admin_url('admin.php?page=sermon-browser/help.php')));
	$wp_admin_bar->add_menu(array('parent' => 'sermon-browser-menu', 'id' => 'sermon-browser-japan', 'title' => __('Pray for Japan', $sermon_domain), 'href' => admin_url('admin.php?page=sermon-browser/japan.php')));
	$wp_admin_bar->add_menu(array('parent' => 'new-content', 'id' => 'sermon-browser-add2', 'title' => __('Sermon', $sermon_domain), 'href' => admin_url('admin.php?page=sermon-browser/new_sermon.php')));
}

// Sorts an object by rank
function sb_sort_object($a,$b) {
	if(  $a->rank ==  $b->rank )
		return 0;
	return ($a->rank < $b->rank) ? -1 : 1;
}

// Displays the most popular sermons in the sidebar
function sb_widget_popular ($args) {

	global $wpdb, $sermon_domain;
	extract($args);
	if (!isset($suffix))
		$suffix = '_w';
	if (!isset($options))
		$options = sb_get_option('popular_widget_options');
	echo $before_widget;
	if ($options['title'] != '')
		echo $before_title.$options['title'].$after_title;
	$jscript = '';
	$trigger = array();
	if ($options['display_sermons']) {
		$sermons = $wpdb->get_results("SELECT sermons.id, sermons.title, sum(stuff.count) AS total
									   FROM {$wpdb->prefix}sb_stuff AS stuff
									   LEFT JOIN {$wpdb->prefix}sb_sermons AS sermons ON stuff.sermon_id = sermons.id
									   GROUP BY sermons.id ORDER BY total DESC LIMIT 0, {$options['limit']}");
		if ($sermons) {
			$output['sermons'] = '<div class="popular-sermons'.$suffix.'"><ul>';
			foreach ($sermons as $sermon)
				$output['sermons'] .= '<li><a href="'.sb_build_url(array('sermon_id' => $sermon->id), true).'">'.$sermon->title.'</a></li>';
			$output['sermons'] .= '</ul></div>';
			$trigger[] = '<a id="popular_sermons_trigger'.$suffix.'" href="#">Sermons</a>';
			$jscript .=  'jQuery("#popular_sermons_trigger'.$suffix.'").click(function() {
							jQuery(this).attr("style", "font-weight:bold");
							jQuery("#popular_series_trigger'.$suffix.'").removeAttr("style");
							jQuery("#popular_preachers_trigger'.$suffix.'").removeAttr("style");
							jQuery.setSbCookie ("sermons");
							jQuery("#sb_popular_wrapper'.$suffix.'").fadeOut("slow", function() {
								jQuery("#sb_popular_wrapper'.$suffix.'").html("'.addslashes($output['sermons']).'").fadeIn("slow");
							});
							return false;
						});';
		}
	}

	if ($options['display_series']) {
		$series1 = $wpdb->get_results("SELECT series.id, series.name, avg(stuff.count) AS average
									   FROM {$wpdb->prefix}sb_stuff AS stuff
									   LEFT JOIN {$wpdb->prefix}sb_sermons AS sermons ON stuff.sermon_id = sermons.id
									   LEFT JOIN {$wpdb->prefix}sb_series AS series ON sermons.series_id = series.id
									   GROUP BY series.id ORDER BY average DESC");
		$series2 = $wpdb->get_results("SELECT series.id, sum(stuff.count) AS total
									   FROM {$wpdb->prefix}sb_stuff AS stuff
									   LEFT JOIN {$wpdb->prefix}sb_sermons AS sermons ON stuff.sermon_id = sermons.id
									   LEFT JOIN {$wpdb->prefix}sb_series AS series ON sermons.series_id = series.id
									   GROUP BY series.id ORDER BY total DESC");
		if ($series1) {
			$i=1;
			foreach ($series1 as $series) {
				$series_final[$series->id]->name = $series->name;
				$series_final[$series->id]->rank = $i;
				$series_final[$series->id]->id = $series->id;
				$i++;
			}
			$i=1;
			foreach ($series2 as $series) {
				$series_final[$series->id]->rank += $i;
				$i++;
			}
			usort($series_final,'sb_sort_object');
			$series_final = array_slice($series_final, 0, $options['limit']);
			$output['series'] = '<div class="popular-series'.$suffix.'"><ul>';
			foreach ($series_final as $series)
				$output['series'] .= '<li><a href="'.sb_build_url(array('series' => $series->id), true).'">'.$series->name.'</a></li>';
			$output['series'] .= '</ul></div>';
		}
		$trigger[] = '<a id="popular_series_trigger'.$suffix.'" href="#">Series</a>';
		$jscript .=	 'jQuery("#popular_series_trigger'.$suffix.'").click(function() {
							jQuery(this).attr("style", "font-weight:bold");
							jQuery("#popular_sermons_trigger'.$suffix.'").removeAttr("style");
							jQuery("#popular_preachers_trigger'.$suffix.'").removeAttr("style");
							jQuery.setSbCookie ("series");
							jQuery("#sb_popular_wrapper'.$suffix.'").fadeOut("slow", function() {
								jQuery("#sb_popular_wrapper'.$suffix.'").html("'.addslashes($output['series']).'").fadeIn("slow");
							});
							return false;
						});';
	}

	if ($options['display_preachers']) {
		$preachers1 = $wpdb->get_results("SELECT preachers.id, preachers.name, avg(stuff.count) AS average
										  FROM {$wpdb->prefix}sb_stuff AS stuff
										  LEFT JOIN {$wpdb->prefix}sb_sermons AS sermons ON stuff.sermon_id = sermons.id
										  LEFT JOIN {$wpdb->prefix}sb_preachers AS preachers ON sermons.preacher_id = preachers.id
										  GROUP BY preachers.id
										  ORDER BY average DESC");
		$preachers2 = $wpdb->get_results("SELECT preachers.id, sum(stuff.count) AS total
										  FROM {$wpdb->prefix}sb_stuff AS stuff
										  LEFT JOIN {$wpdb->prefix}sb_sermons AS sermons ON stuff.sermon_id = sermons.id
										  LEFT JOIN {$wpdb->prefix}sb_preachers AS preachers ON sermons.preacher_id = preachers.id
										  GROUP BY preachers.id
										  ORDER BY total DESC");
		if ($preachers1) {
			$i=1;
			foreach ($preachers1 as $preacher) {
				$preachers_final[$preacher->id]->name = $preacher->name;
				$preachers_final[$preacher->id]->rank = $i;
				$preachers_final[$preacher->id]->id = $preacher->id;
				$i++;
			}
			$i=1;
			foreach ($preachers2 as $preacher) {
				$preachers_final[$preacher->id]->rank += $i;
				$i++;
			}
			usort($preachers_final,'sb_sort_object');
			$preachers_final = array_slice($preachers_final, 0, $options['limit']);
			$output['preachers'] = '<div class="popular-preachers'.$suffix.'"><ul>';
			foreach ($preachers_final as $preacher)
				$output['preachers'] .= '<li><a href="'.sb_build_url(array('preacher' => $preacher->id), true).'">'.$preacher->name.'</a></li>';
			$output['preachers'] .= '</ul></div>';
			$trigger[] = '<a id="popular_preachers_trigger'.$suffix.'" href="#">Preachers</a>';
			$jscript .=	 'jQuery("#popular_preachers_trigger'.$suffix.'").click(function() {
								jQuery(this).attr("style", "font-weight:bold");
								jQuery("#popular_series_trigger'.$suffix.'").removeAttr("style");
								jQuery("#popular_sermons_trigger'.$suffix.'").removeAttr("style");
								jQuery.setSbCookie("preachers");
								jQuery("#sb_popular_wrapper'.$suffix.'").fadeOut("slow", function() {
									jQuery("#sb_popular_wrapper'.$suffix.'").html("'.addslashes($output['preachers']).'").fadeIn("slow");
								});
								return false;
							 });';
		}
	}

	$jscript .= 'if (jQuery.getSbCookie() == "preachers") { jQuery("#popular_preachers_trigger'.$suffix.'").attr("style", "font-weight:bold"); jQuery("#sb_popular_wrapper'.$suffix.'").html("'.addslashes($output['preachers']).'")};';
	$jscript .= 'if (jQuery.getSbCookie() == "series") { jQuery("#popular_series_trigger'.$suffix.'").attr("style", "font-weight:bold"); jQuery("#sb_popular_wrapper'.$suffix.'").html("'.addslashes($output['series']).'")};';
	$jscript .= 'if (jQuery.getSbCookie() == "sermons") { jQuery("#popular_sermons_trigger'.$suffix.'").attr("style", "font-weight:bold"); jQuery("#sb_popular_wrapper'.$suffix.'").html("'.addslashes($output['sermons']).'")};';
	echo '<p>'.implode ($trigger, ' | ').'</p>';
	echo '<div id="sb_popular_wrapper'.$suffix.'">'.current($output).'</div>';
	echo '<script type="text/javascript">jQuery.setSbCookie = function (value) {
											document.cookie = "sb_popular="+encodeURIComponent(value);
										 };</script>';
	echo '<script type="text/javascript">jQuery.getSbCookie = function () {
											var cookieValue = null;
											if (document.cookie && document.cookie != "") {
												var cookies = document.cookie.split(";");
												for (var i = 0; i < cookies.length; i++) {
													var cookie = jQuery.trim(cookies[i]);
													var name = "sb_popular";
													if (cookie.substring(0, name.length + 1) == (name + "=")) {
														cookieValue = decodeURIComponent(cookie.substring(name.length + 1));
														break;
													}
												}
											}
										 return cookieValue;
										 }</script>';
	echo '<script type="text/javascript">jQuery(document).ready(function() {'.$jscript.'});</script>';
	echo $after_widget;
}

function sb_print_most_popular() {
	$args['before_widget'] = '<div id="sermon_most_popular" style="border: 1px solid ; margin: 0pt 0pt 1em 2em; padding: 5px; float: right; font-size: 75%; line-height: 1em">';
	$args['after_widget'] = '</div>';
	$args['before_title'] = '<span class="popular_title">';
	$args['after_title'] = '</span>';
	$args['suffix'] = '_f';
	sb_widget_popular ($args);
}

//Modify page title
function sb_page_title($title) {
	global $wpdb, $sermon_domain;
	if (isset($_GET['sermon_id'])) {
		$id = (int)$_GET['sermon_id'];
		$sermon = $wpdb->get_row("SELECT m.title, p.name FROM {$wpdb->prefix}sb_sermons as m LEFT JOIN {$wpdb->prefix}sb_preachers as p ON m.preacher_id = p.id where m.id = $id");
		if ($sermon)
			return $title.' ('.stripslashes($sermon->title).' - '.stripslashes($sermon->name).')';
		else
			return $title.' ('.__('No sermons found.', $sermon_domain).')';
	}
	else
		return $title;
}

//Downloads external webpage. Used to add Bible passages to sermon page.
function sb_download_page ($page_url) {
	if (function_exists('curl_init')) {
		$curl = curl_init();
		curl_setopt ($curl, CURLOPT_URL, $page_url);
		curl_setopt ($curl, CURLOPT_TIMEOUT, 2);
		curl_setopt ($curl, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt ($curl, CURLOPT_HTTPHEADER, array('Accept-Charset: utf-8;q=0.7,*;q=0.7'));
		$contents = curl_exec ($curl);
		$content_type = curl_getinfo( $curl, CURLINFO_CONTENT_TYPE );
		curl_close ($curl);
	}
	else
		{
		$handle = @fopen ($page_url, 'r');
		if ($handle) {
			stream_set_blocking($handle, TRUE );
			stream_set_timeout($handle, 2);
			$info = socket_get_status($handle);
			while (!feof($handle) && !$info['timed_out']) {
				$contents .= fread($handle, 8192);
				$info = socket_get_status($handle);
			}
		@fclose($handle);
		}
	}
	return $contents;
}

// Returns human friendly Bible reference (e.g. John 3:1-16, not John 3:1-John 3:16)
function sb_tidy_reference ($start, $end, $add_link = FALSE) {
	if (!trim($start['book'])) {
		return "";
	}
	$start_book = trim($start['book']);
	$end_book = trim($end['book']);
	$start_chapter = trim($start['chapter']);
	$end_chapter = trim($end['chapter']);
	$start_verse = trim($start['verse']);
	$end_verse = trim($end['verse']);
	if ($add_link) {
		$start_book = "<a href=\"".sb_get_book_link($start_book)."\">{$start_book}</a>";
		$end_book = "<a href=\"".sb_get_book_link($end_book)."\">{$end_book}</a>";
	}
	if ($start_book == $end_book) {
		if ($start_chapter == $end_chapter) {
			if ($start_verse == $end_verse) {
				$reference = "$start_book $start_chapter:$start_verse";
			} else {
				$reference = "$start_book $start_chapter:$start_verse-$end_verse";
			}
		} else {
			 $reference = "$start_book $start_chapter:$start_verse-$end_chapter:$end_verse";
		}
	} else {
		$reference =  "$start_book $start_chapter:$start_verse - $end_book $end_chapter:$end_verse";
	}
	return $reference;
}

//Print unstyled bible passage
function sb_print_bible_passage ($start, $end) {
	echo "<p class='bible-passage'>".sb_tidy_reference($start, $end)."</p>";
}

// Returns human friendly Bible reference with link to filter
function sb_get_books($start, $end) {
	return sb_tidy_reference ($start, $end, TRUE);
}

//Add Bible text to single sermon page
function sb_add_bible_text ($start, $end, $version) {
	if ($version == 'esv')
		return sb_add_esv_text ($start, $end);
	elseif ($version == 'net')
		return sb_add_net_text ($start, $end);
	else
		return sb_add_other_bibles ($start, $end, $version);
}

//Returns ESV text
function sb_add_esv_text ($start, $end) {
	// If you are experiencing errors, you should sign up for an ESV API key,
	// and insert the name of your key in place of the letters IP in the URL
	// below (.e.g. ...passageQuery?key=YOURAPIKEY&passage=...)
	$esv_url = 'http://www.esvapi.org/v2/rest/passageQuery?key=IP&passage='.rawurlencode(sb_tidy_reference ($start, $end)).'&include-headings=false&include-footnotes=false';
	return sb_download_page ($esv_url);
}

// Converts XML string to object
function sb_get_xml ($content) {
	if (class_exists('SimpleXMLElement')) {
		$xml = new SimpleXMLElement($content);
	} else {
		$xml = new SimpleXMLElement4();
		$xml = $xml->xml_load_file($content, 'object', 'utf-8');
	}
	return $xml;
}

//Returns NET Bible text
function sb_add_net_text ($start, $end) {
	$reference = str_replace(' ', '+', sb_tidy_reference ($start, $end));
	$old_chapter = $start['chapter'];
	$net_url = "http://labs.bible.org/api/xml/verse.php?passage={$reference}";
	if (class_exists('SimpleXMLElement')) // Ignore paragraph formatting on PHP4 because xml_parse_into_struct doesn't like HTML tags
		$xml = sb_get_xml(sb_download_page($net_url.'&formatting=para'));
	else
		$xml = sb_get_xml(sb_download_page($net_url));
	$output='';
	$items = array();
	$items = $xml->item;
	foreach ($items as $item) {
		if ($item->text != '[[EMPTY]]') {
			if (substr($item->text, 0, 8) == '<p class') {
				$paraend = stripos($item->text, '>', 8)+1;
				$output .= "\n".substr($item->text, 0, $paraend);
				$item->text = substr ($item->text, $paraend);
			}
			if ($old_chapter == $item->chapter) {
				$output .= " <span class=\"verse-num\">{$item->verse}</span>";
			} else {
				$output .= " <span class=\"chapter-num\">{$item->chapter}:{$item->verse}</span> ";
				$old_chapter = strval($item->chapter);
			}
			$output .= 	$item->text;
		}
	}
	return "<div class=\"net\">\r<h2>".sb_tidy_reference ($start, $end)."</h2><p>{$output} (<a href=\"http://net.bible.org/?{$reference}\">NET Bible</a>)</p></div>";
}

//Returns Bible text using SermonBrowser's own API
function sb_add_other_bibles ($start, $end, $version) {
	if ($version == 'hnv')
		return '<div class="'.$version.'"><p>Sorry, the Hebrew Names Version is no longer available.</p></div>';
	$reference = str_replace(' ', '+', sb_tidy_reference ($start, $end));
	$old_chapter = $start['chapter'];
	$url = "http://api.preachingcentral.com/bible.php?passage={$reference}&version={$version}";
	$xml = sb_get_xml(sb_download_page($url));
	$output='';
	$items = array();
	$items = $xml->range->item;
	if ($xml->range->item)
		foreach ($xml->range->item as $item) {
			if ($item->text != '[[EMPTY]]') {
				if ($old_chapter == $item->chapter) {
					$output .= " <span class=\"verse-num\">{$item->verse}</span>";
				} else {
					$output .= " <span class=\"chapter-num\">{$item->chapter}:{$item->verse}</span> ";
					$old_chapter = strval($item->chapter);
				}
				$output .=	 $item->text;
			}
		}
	return '<div class="'.$version.'"><h2>'.sb_tidy_reference ($start, $end). '</h2><p>'.$output.' (<a href="http://biblepro.bibleocean.com/dox/default.aspx">'. strtoupper($version). '</a>)</p></div>';
}

//Adds edit sermon link if current user has edit rights
function sb_edit_link ($id) {
	if (current_user_can('publish_posts')) {
		$id = (int)$id;
		echo '<div class="sb_edit_link"><a href="'.site_url().'/wp-admin/admin.php?page=sermon-browser/new_sermon.php&mid='.$id.'">Edit Sermon</a></div>';
	}
}

// Returns URL for search links
function sb_build_url($arr, $clear = false) {
	global $wpdb;
	// Word list for URL building purpose
	$wl = array('preacher', 'title', 'date', 'enddate', 'series', 'service', 'sortby', 'dir', 'book', 'stag', 'podcast');
	$foo = array_merge((array) $_GET, (array) $_POST, $arr);
	foreach ($foo as $k => $v) {
		if (in_array($k, array_keys($arr)) | (in_array($k, $wl) && !$clear)) {
			$bar[] = rawurlencode($k).'='.rawurlencode($v);
		}
	}
	if (isset($bar))
		return sb_display_url().sb_query_char().implode('&amp;', $bar);
	else
		return sb_display_url();
}

// Adds javascript and CSS where required
function sb_add_headers() {
	global $sermon_domain, $post, $wpdb, $wp_scripts;
	if (isset($post->ID) && $post->ID != '') {
		echo "<!-- Added by SermonBrowser (version ".SB_CURRENT_VERSION.") - http://www.sermonbrowser.com/ -->\r";
		echo "<link rel=\"alternate\" type=\"application/rss+xml\" title=\"".__('Sermon podcast', $sermon_domain)."\" href=\"".sb_get_option('podcast_url')."\" />\r";
		wp_enqueue_style('sb_style');
		$pageid = $wpdb->get_var("SELECT ID FROM {$wpdb->posts} WHERE post_content LIKE '%[sermons%' AND (post_status = 'publish' OR post_status = 'private') AND ID={$post->ID} AND post_date < NOW();");
		if ($pageid !== NULL) {
			if (sb_get_option('filter_type') == 'dropdown') {
				wp_enqueue_script('sb_datepicker');
				wp_enqueue_style ('sb_datepicker');
			}
			if (isset($_REQUEST['title']) || isset($_REQUEST['preacher']) || isset($_REQUEST['date']) || isset($_REQUEST['enddate']) || isset($_REQUEST['series']) || isset($_REQUEST['service']) || isset($_REQUEST['book']) || isset($_REQUEST['stag']))
				echo "<link rel=\"alternate\" type=\"application/rss+xml\" title=\"".__('Custom sermon podcast', $sermon_domain)."\" href=\"".sb_podcast_url()."\" />\r";
			wp_enqueue_script('jquery');
		} else {
			$sidebars_widgets = wp_get_sidebars_widgets();
			if (isset($sidebars_widgets['wp_inactive_widgets']))
				unset($sidebars_widgets['wp_inactive_widgets']);
			if (is_array($sidebars_widgets))
				foreach ($sidebars_widgets as $sb_w)
					if (is_array($sb_w) && in_array('sermon-browser-popular', $sb_w))
						wp_enqueue_script('jquery');
		}
	}
}

// Formats date into words
function sb_formatted_date ($sermon) {
	global $sermon_domain;
	if (isset($sermon->time) && $sermon->time != '')
		$sermon_time = $sermon->time;
	else
		$sermon_time = sb_default_time ($sermon->sid);
	if ($sermon->datetime == '1970-01-01 00:00:00')
		return __('Unknown Date', $sermon_domain);
	else
		return date_i18n(get_option('date_format'), strtotime($sermon->datetime));
}

// Returns podcast URL
function sb_podcast_url() {
	return str_replace(' ', '%20', sb_build_url(array('podcast' => 1, 'dir'=>'desc', 'sortby'=>'m.datetime')));
}

// Prints sermon search URL
function sb_print_sermon_link($sermon, $echo = true) {
	if ($echo)
		echo sb_build_url(array('sermon_id' => $sermon->id), true);
	else
		return sb_build_url(array('sermon_id' => $sermon->id), true);
}

// Prints preacher search URL
function sb_print_preacher_link($sermon) {
	echo sb_build_url(array('preacher' => $sermon->pid));
}

// Prints series search URL
function sb_print_series_link($sermon) {
	echo sb_build_url(array('series' => $sermon->ssid));
}

// Prints service search URL
function sb_print_service_link($sermon) {
	echo sb_build_url(array('service' => $sermon->sid));
}

// Prints bible book search URL
function sb_get_book_link($book_name) {
	return sb_build_url(array('book' => $book_name));
}

// Prints tag search URL
function sb_get_tag_link($tag) {
	return sb_build_url(array('stag' => $tag));
}

// Prints tags
function sb_print_tags($tags) {
	$out = array();
	foreach ((array) $tags as $tag) {
		$tag = stripslashes($tag);
		$out[] = '<a href="'.sb_get_tag_link($tag).'">'.$tag.'</a>';
	}
	$tags = implode(', ', (array) $out);
	echo $tags;
}

//Prints tag cloud
function sb_print_tag_clouds($minfont=80, $maxfont=150) {
	global $wpdb;
	$rawtags = $wpdb->get_results("SELECT name FROM {$wpdb->prefix}sb_tags as t RIGHT JOIN {$wpdb->prefix}sb_sermons_tags as st ON t.id = st.tag_id");
	foreach ($rawtags as $tag) {
		if (isset($cnt[$tag->name]))
			$cnt[$tag->name]++;
		else
			$cnt[$tag->name] = 1;
	}
	$fontrange = $maxfont - $minfont;
	$maxcnt = 0;
	$mincnt = 1000000;
	foreach ($cnt as $cur) {
		if ($cur > $maxcnt) $maxcnt = $cur;
		if ($cur < $mincnt) $minct = $cur;
	}
	$cntrange = $maxcnt + 1 - $mincnt;
	$minlog = log($mincnt);
	$maxlog = log($maxcnt);
	$logrange = $maxlog == $minlog ? 1 : $maxlog - $minlog;
	arsort($cnt);
	foreach ($cnt as $tag => $count) {
		$size = $minfont + $fontrange * (log($count) - $minlog) / $logrange;
		$out[] = '<a style="font-size:'.(int) $size.'%" href="'.sb_get_tag_link($tag).'">'.$tag.'</a>';
	}
	echo implode(' ', $out);
}

//Prints link to next page
function sb_print_next_page_link($limit = 0) {
	global $sermon_domain, $record_count;
	if ($limit == 0)
		$limit = sb_get_option('sermons_per_page');
	$current = isset($_REQUEST['page']) ? (int) $_REQUEST['page'] : 1;
	if ($current < ceil($record_count / $limit)) {
		$url = sb_build_url(array('page' => ++$current));
		echo '<a href="'.$url.'">'.__('Next page', $sermon_domain).' &raquo;</a>';
	}
}

//Prints link to previous page
function sb_print_prev_page_link($limit = 0) {
	global $sermon_domain;
	if ($limit == 0) $limit = sb_get_option('sermons_per_page');
	$current = isset($_REQUEST['page']) ? (int) $_REQUEST['page'] : 1;
	if ($current > 1) {
		$url = sb_build_url(array('page' => --$current));
		echo '<a href="'.$url.'">&laquo; '.__('Previous page', $sermon_domain).'</a>';
	}
}

// Print link to attached files
function sb_print_url($url) {
	global $sermon_domain;
	require (SB_INCLUDES_DIR.'/filetypes.php');
	$pathinfo = pathinfo($url);
	$ext = $pathinfo['extension'];
	if (substr($url,0,7) == "http://")
		$url=sb_display_url().sb_query_char(FALSE).'show&url='.rawurlencode($url);
	else
		if (strtolower($ext) == 'mp3')
			$url=sb_display_url().sb_query_char(FALSE).'show&file_name='.rawurlencode($url);
		else
			$url=sb_display_url().sb_query_char(FALSE).'download&file_name='.rawurlencode($url);
	$uicon = $default_site_icon;
	foreach ($siteicons as $site => $icon) {
		if (strpos($url, $site) !== false) {
			$uicon = $icon;
			break;
		}
	}
	$uicon = isset($filetypes[$ext]['icon']) ? $filetypes[$ext]['icon'] : $uicon;
	if (strtolower($ext) == 'mp3') {
		if ((substr(sb_get_option('mp3_shortcode'), 0, 18) == '[audio:%SERMONURL%') && function_exists('ap_insert_player_widgets')) {
			echo ap_insert_player_widgets(str_ireplace('%SERMONURL%', $url, sb_get_option('mp3_shortcode')));
			return;
		} elseif (do_shortcode(sb_get_option('mp3_shortcode')) != sb_get_option('mp3_shortcode')) {
			echo do_shortcode(str_ireplace('%SERMONURL%', $url, sb_get_option('mp3_shortcode')));
			return;
		}
	}
	$uicon = SB_PLUGIN_URL.'/sb-includes/icons/'.$uicon;
	if (!isset($filetypes[$ext]['name']))
		$filetypes[$ext]['name'] = sprintf(__('%s file', $sermon_domain), addslashes($ext));
	else
		$filetypes[$ext]['name'] = addslashes($filetypes[$ext]['name']);
	echo "<a href=\"{$url}\"><img class=\"site-icon\" alt=\"{$filetypes[$ext]['name']}\" title=\"{$filetypes[$ext]['name']}\" src=\"{$uicon}\"></a>";
}

// Print link to attached external URLs
function sb_print_url_link($url) {
	echo '<div class="sermon_file">';
	sb_print_url ($url);
	if (substr($url, -4) == ".mp3" && function_exists('ap_insert_player_widgets')) {
		if (substr($url,0,7) == "http://") {
			$param="url"; }
		else {
			$param="file_name"; }
		$url = rawurlencode($url);
		echo ' <a href="'.sb_display_url().sb_query_char().'download&amp;'.$param.'='.$url.'">Download</a>';
	}
	echo '</div>';
}

//Decode base64 encoded data
function sb_print_code($code) {
	echo do_shortcode(base64_decode($code));
}

//Prints preacher description
function sb_print_preacher_description($sermon) {
	global $sermon_domain;
	if (strlen($sermon->preacher_description)>0) {
		echo "<div class='preacher-description'><span class='about'>" . __('About', $sermon_domain).' '.stripslashes($sermon->preacher).': </span>';
		echo "<span class='description'>".stripslashes($sermon->preacher_description)."</span></div>";
	}
}

//Prints preacher image
function sb_print_preacher_image($sermon) {
	if ($sermon->image)
		echo "<img alt='".stripslashes($sermon->preacher)."' class='preacher' src='".trailingslashit(site_url()).sb_get_option('upload_dir').'images/'.$sermon->image."'>";
}

//Prints link to sermon preached next (but not today)
function sb_print_next_sermon_link($sermon) {
	global $wpdb;
	$next = $wpdb->get_row("SELECT id, title FROM {$wpdb->prefix}sb_sermons WHERE DATE(datetime) > DATE('{$sermon->datetime}') AND id <> {$sermon->id} ORDER BY datetime asc LIMIT 1");
	if (!$next) return;
	echo '<a href="';
	sb_print_sermon_link($next);
	echo '">'.stripslashes($next->title).' &raquo;</a>';
}

//Prints link to sermon preached on previous days
function sb_print_prev_sermon_link($sermon) {
	global $wpdb;
	$prev = $wpdb->get_row("SELECT id, title FROM {$wpdb->prefix}sb_sermons WHERE DATE(datetime) < DATE('{$sermon->datetime}') AND id <> {$sermon->id} ORDER BY datetime desc LIMIT 1");
	if (!$prev) return;
	echo '<a href="';
	sb_print_sermon_link($prev);
	echo '">&laquo; '.stripslashes($prev->title).'</a>';
}

//Prints links to other sermons preached on the same day
function sb_print_sameday_sermon_link($sermon) {
	global $wpdb, $sermon_domain;
	$same = $wpdb->get_results("SELECT id, title FROM {$wpdb->prefix}sb_sermons WHERE DATE(datetime) = DATE('{$sermon->datetime}') AND id <> {$sermon->id}");
	if (!$same) {
		_e('None', $sermon_domain);
		return;
	}
	$output = array();
	foreach ($same as $cur)
		$output[] = '<a href="'.sb_print_sermon_link($cur, false).'">'.stripslashes($cur->title).'</a>';
	echo implode($output, ', ');
}

//Gets single sermon from the database
function sb_get_single_sermon($id) {
	global $wpdb;
	$id = (int) $id;
	$sermon = $wpdb->get_row("SELECT m.id, m.title, m.datetime, m.start, m.end, m.description, p.id as pid, p.name as preacher, p.image as image, p.description as preacher_description, s.id as sid, s.name as service, ss.id as ssid, ss.name as series FROM {$wpdb->prefix}sb_sermons as m, {$wpdb->prefix}sb_preachers as p, {$wpdb->prefix}sb_services as s, {$wpdb->prefix}sb_series as ss where m.preacher_id = p.id and m.service_id = s.id and m.series_id = ss.id and m.id = {$id}");
	if ($sermon) {
		$file = $code = $tags = array();
		$stuff = $wpdb->get_results("SELECT f.id, f.type, f.name FROM {$wpdb->prefix}sb_stuff as f WHERE sermon_id = $id ORDER BY id desc");
		$rawtags = $wpdb->get_results("SELECT t.name FROM {$wpdb->prefix}sb_sermons_tags as st LEFT JOIN {$wpdb->prefix}sb_tags as t ON st.tag_id = t.id WHERE st.sermon_id = {$sermon->id} ORDER BY t.name asc");
		foreach ($rawtags as $tag) {
			$tags[] = $tag->name;
		}
		foreach ($stuff as $cur)
			${$cur->type}[] = $cur->name;
		$sermon->start = unserialize($sermon->start);
		$sermon->end = unserialize($sermon->end);
		return array(
			'Sermon' => $sermon,
			'Files' => $file,
			'Code' => $code,
			'Tags' => $tags,
		);
	} else
		return false;
}

//Prints the filter line for a given parameter
function sb_print_filter_line ($id, $results, $filter, $display, $max_num = 7) {
	global $sermon_domain, $more_applied;
	echo "<div id = \"{$id}\" class=\"filter\">\r<span class=\"filter-heading\">".ucwords($id).":</span> \r";
	$i = 1;
	$more = FALSE;
	foreach ($results as $result) {
		if ($i == $max_num) {
			echo "<span id=\"{$id}-more\">";
			$more = TRUE;
			$more_applied[] = $id;
		}
		if ($i != 1)
			echo ", \r";
		echo '<a href="'.sb_build_url(array($id => $result->$filter)).'">'.stripslashes($result->$display).'</a>&nbsp;('.$result->count.')';
		$i++;
	}
	echo ".";
	if ($more)
		echo "</span>\r<span id=\"{$id}-more-link\" style=\"display:none\">&hellip; (<a  id=\"{$id}-toggle\" href=\"#\"><strong>".($i-$max_num).' '.__('more', $sermon_domain).'</strong></a>)</span>';
	echo '</div>';
}

//Prints the filter line for the date parameter
function sb_print_date_filter_line ($dates) {
	global $more_applied;
	$date_output = "<div id = \"dates\" class=\"filter\">\r<span class=\"filter-heading\">Date:</span> \r";
	$first = $dates[0];
	$last = end($dates);
	$count = 0;
	if ($first->year == $last->year) {
		if ($first->month == $last->month) {
			$date_output = '';
		} else {
			$previous_month = -1;
			foreach ($dates as $date) {
				if ($date->month != $previous_month) {
					if ($count != 0)
						$date_output .= '('.$count.'), ';
					$date_output .= '<a href="'.sb_build_url(Array ('date' => $date->year.'-'.$date->month.'-01', 'enddate' => $date->year.'-'.$date->month.'-31')).'">'.strftime('%B', strtotime("{$date->year}-{$date->month}-{$date->day}")).'</a> ';
					$previous_month = $date->month;
					$count = 1;
				} else
					$count++;
			}
			$date_output .= '('.$count.'), ';
		}
	} else {
		$previous_year = 0;
		foreach ($dates as $date) {
			if ($date->year !== $previous_year) {
				if ($count !== 0)
					$date_output .= '('.$count.'), ';
				$date_output .= '<a href="'.sb_build_url(Array ('date' => $date->year.'-01-01', 'enddate' => $date->year.'-12-31')).'">'.$date->year.'</a> ';
				$previous_year = $date->year;
				$count = 1;
			} else
				$count++;
		}
		$date_output .= '('.$count.'), ';
	}
	if ($date_output != '')
		echo rtrim($date_output, ', ')."</div>\r";
}

//Returns the filter URL minus a given parameter
function sb_url_minus_parameter ($param1, $param2='') {
	global $filter_options;
	$existing_params = array_merge((array) $_GET, (array) $_POST);
	$returned_query = array();
	foreach (array_keys($existing_params) as $query) {
		if (in_array($query, $filter_options) && $query != $param1 && $query != $param2) {
			$returned_query[] = "{$query}={$existing_params[$query]}";
		}
	}
	if (count($returned_query) > 0)
		return sb_display_url().sb_query_char().implode('&amp;', $returned_query);
	else
		return sb_display_url();
}

//Displays the filter on sermon search page
function sb_print_filters($filter) {
	global $wpdb, $sermon_domain, $more_applied, $filter_options;
	$hide_filter = FALSE;
	if ($filter['filterhide'] == 'hide') {
		$hide_filter = TRUE;
		$js_hide = "
		var filter_visible = false;
		jQuery('#mainfilter').hide();
		jQuery('#show_hide_filter').text('[ SHOW ]');
		jQuery('#show_hide_filter').click(function() {
			jQuery('#mainfilter:visible').slideUp('slow');
			jQuery('#mainfilter:hidden').slideDown('slow');
			if (filter_visible) {
				jQuery('#show_hide_filter').text('[ SHOW ]');
				filter_visible = false;
			} else {
				jQuery('#show_hide_filter').text('[ HIDE ]');
				filter_visible = true;
			}
			return false;
		});";
		$js_hide = str_replace ('SHOW', __('Show filter', $sermon_domain), $js_hide);
		$js_hide = str_replace ('HIDE', __('Hide filter', $sermon_domain), $js_hide);
	}
	if ($filter['filter'] == 'oneclick') {
		// One click filter
		$hide_custom_podcast = true;
		$filter_options = array ('preacher', 'book', 'service', 'series', 'date', 'enddate', 'title');
		$output = '';
		foreach ($filter_options AS $filter_option)
			if (isset($_REQUEST[$filter_option])) {
				if ($filter_option != 'enddate') {
					if ($output != '')
						$output .= "\r, ";
					if ($filter_option == 'date') {
						$output .= '<strong>Date</strong>:&nbsp;';
						if (substr($_REQUEST['date'],0,4) == substr($_REQUEST['enddate'],0,4))
							$output .= substr($_REQUEST['date'],0,4).'&nbsp;(<a href="'.sb_url_minus_parameter('date', 'enddate').'">x</a>)';
						if (substr($_REQUEST['date'],5,2) == substr($_REQUEST['enddate'],5,2))
							$output .= ', '.strftime('%B', strtotime($_REQUEST['date'])).' (<a href="'.sb_build_url(Array ('date' => substr($_REQUEST['date'],0,4).'-01-01', 'enddate' => substr($_REQUEST['date'],0,4).'-12-31')).'">x</a>)';
					} else {
						$output .= '<strong>'.ucwords($filter_option).'</strong>:&nbsp;*'.$filter_option.'*';
						$output .= '&nbsp;(<a href="'.sb_url_minus_parameter($filter_option).'">x</a>)';
					}
				}
				$hide_custom_podcast = FALSE;
			}
		$hide_empty = sb_get_option('hide_no_attachments');
		$sermons=sb_get_sermons($filter, array(), 1, 99999, $hide_empty);
		$ids = array();
		foreach ($sermons as $sermon)
			$ids[] = $sermon->id;
		$ids = "('".implode ("', '", $ids)."')";

		$preachers = $wpdb->get_results("SELECT p.*, count(p.id) AS count FROM {$wpdb->prefix}sb_preachers AS p JOIN {$wpdb->prefix}sb_sermons AS sermons ON p.id = sermons.preacher_id WHERE sermons.id IN {$ids} GROUP BY p.id ORDER BY count DESC, sermons.datetime DESC");
		$series = $wpdb->get_results("SELECT ss.*, count(ss.id) AS count FROM {$wpdb->prefix}sb_series AS ss JOIN {$wpdb->prefix}sb_sermons AS sermons ON ss.id = sermons.series_id  WHERE sermons.id IN {$ids} GROUP BY ss.id ORDER BY sermons.datetime DESC");
		$services = $wpdb->get_results("SELECT s.*, count(s.id) AS count FROM {$wpdb->prefix}sb_services AS s JOIN {$wpdb->prefix}sb_sermons AS sermons ON s.id = sermons.service_id  WHERE sermons.id IN {$ids} GROUP BY s.id ORDER BY count DESC");
		$book_count = $wpdb->get_results("SELECT bs.book_name AS name, count(distinct bs.sermon_id) AS count FROM {$wpdb->prefix}sb_books_sermons AS bs JOIN {$wpdb->prefix}sb_books as b ON bs.book_name=b.name AND bs.sermon_id IN {$ids} GROUP BY b.id");
		$dates = $wpdb->get_results("SELECT YEAR(datetime) as year, MONTH (datetime) as month, DAY(datetime) as day FROM {$wpdb->prefix}sb_sermons WHERE id IN {$ids} ORDER BY datetime ASC");

		$more_applied = array();
		$output = str_replace ('*preacher*', isset($preachers[0]->name) ? $preachers[0]->name : '', $output);
		$output = str_replace ('*book*', isset($_REQUEST['book']) ? htmlentities($_REQUEST['book']) : '', $output);
		$output = str_replace ('*service*', isset($services[0]->name) ? $services[0]->name : '', $output);
		$output = str_replace ('*series*', isset($series[0]->name) ? $series[0]->name : '', $output);

		echo "<span class=\"inline_controls\"><a href=\"#\" id=\"show_hide_filter\"></a></span>";
		if ($output != '')
			echo '<div class="filtered">'.__('Active filter', $sermon_domain).': '.$output."</div>\r";
		echo '<div id="mainfilter">';
		if (count($preachers) > 1)
			sb_print_filter_line ('preacher', $preachers, 'id', 'name', 7);
		if (count($book_count) > 1)
			sb_print_filter_line ('book', $book_count, 'name', 'name', 10);
		if (count($series) > 1)
			sb_print_filter_line ('series', $series, 'id', 'name', 10);
		if (count($services) > 1)
			sb_print_filter_line ('service', $services, 'id', 'name', 10);
		sb_print_date_filter_line ($dates);
		echo "</div>\r";
		if (count($more_applied) > 0 | $output != '' | $hide_custom_podcast === TRUE | $hide_filter === TRUE) {
			echo "<script type=\"text/javascript\">\r";
			echo "\tjQuery(document).ready(function() {\r";
			if ($hide_filter === TRUE)
				echo $js_hide."\r";
			if ($hide_custom_podcast === TRUE)
				echo "\t\tjQuery('.podcastcustom').hide();\r";
			if (count($more_applied) > 0) {
				foreach ($more_applied as $element_id) {
					echo "\t\tjQuery('#{$element_id}-more').hide();\r";
					echo "\t\tjQuery('#{$element_id}-more-link').show();\r";
					echo "\t\tjQuery('a#{$element_id}-toggle').click(function() {\r";
					echo "\t\t\tjQuery('#{$element_id}-more').show();\r";
					echo "\t\t\tjQuery('#{$element_id}-more-link').hide();\r";
					echo "\t\t\treturn false;\r";
					echo "\t\t});\r";
				}
			}
			echo "\t});\r";
			echo "</script>\r";
		}
	} elseif ($filter['filter'] == 'dropdown') {
		// Drop-down filter
		$preachers = $wpdb->get_results("SELECT p.*, count(p.id) AS count FROM {$wpdb->prefix}sb_preachers AS p JOIN {$wpdb->prefix}sb_sermons AS s ON p.id = s.preacher_id GROUP BY p.id ORDER BY count DESC, s.datetime DESC");
		$series = $wpdb->get_results("SELECT ss.*, count(ss.id) AS count FROM {$wpdb->prefix}sb_series AS ss JOIN {$wpdb->prefix}sb_sermons AS sermons ON ss.id = sermons.series_id GROUP BY ss.id ORDER BY sermons.datetime DESC");
		$services = $wpdb->get_results("SELECT s.*, count(s.id) AS count FROM {$wpdb->prefix}sb_services AS s JOIN {$wpdb->prefix}sb_sermons AS sermons ON s.id = sermons.service_id GROUP BY s.id ORDER BY count DESC");
		$book_count = $wpdb->get_results("SELECT bs.book_name AS name, count(distinct bs.sermon_id) AS count FROM {$wpdb->prefix}sb_books_sermons AS bs JOIN {$wpdb->prefix}sb_books AS b ON bs.book_name = b.name GROUP BY b.id");
		$sb = array(
			'Title' => 'm.title',
			'Preacher' => 'preacher',
			'Date' => 'm.datetime',
			'Passage' => 'b.id',
		);
		$di = array(
			'Ascending' => 'asc',
			'Descending' => 'desc',
		);
		$csb = isset($_REQUEST['sortby']) ? $_REQUEST['sortby'] : 'm.datetime';
		$cd = isset($_REQUEST['dir']) ? $_REQUEST['dir'] : 'desc';
		?>
		<span class="inline_controls"><a href="#" id="show_hide_filter"></a></span>
		<div id="mainfilter">
			<form method="post" id="sermon-filter" action="<?php echo sb_display_url(); ?>">
				<div style="clear:both">
					<table class="sermonbrowser">
						<tr>
							<td class="fieldname"><?php _e('Preacher', $sermon_domain) ?></td>
							<td class="field"><select name="preacher" id="preacher">
									<option value="0" <?php echo (isset($_REQUEST['preacher']) && $_REQUEST['preacher'] != 0) ? '' : 'selected="selected"' ?>><?php _e('[All]', $sermon_domain) ?></option>
									<?php foreach ($preachers as $preacher): ?>
									<option value="<?php echo $preacher->id ?>" <?php echo isset($_REQUEST['preacher']) && $_REQUEST['preacher'] == $preacher->id ? 'selected="selected"' : '' ?>><?php echo stripslashes($preacher->name).' ('.$preacher->count.')' ?></option>
									<?php endforeach ?>
								</select>
							</td>
							<td class="fieldname rightcolumn"><?php _e('Services', $sermon_domain) ?></td>
							<td class="field"><select name="service" id="service">
									<option value="0" <?php echo isset($_REQUEST['service']) && $_REQUEST['service'] != 0 ? '' : 'selected="selected"' ?>><?php _e('[All]', $sermon_domain) ?></option>
									<?php foreach ($services as $service): ?>
									<option value="<?php echo $service->id ?>" <?php echo isset($_REQUEST['service']) && $_REQUEST['service'] == $service->id ? 'selected="selected"' : '' ?>><?php echo stripslashes($service->name).' ('.$service->count.')' ?></option>
									<?php endforeach ?>
								</select>
							</td>
						</tr>
						<tr>
							<td class="fieldname"><?php _e('Book', $sermon_domain) ?></td>
							<td class="field"><select name="book">
									<option value=""><?php _e('[All]', $sermon_domain) ?></option>
									<?php foreach ($book_count as $book): ?>
									<option value="<?php echo $book->name ?>" <?php echo isset($_REQUEST['book']) && $_REQUEST['book'] == $book->name ? 'selected=selected' : '' ?>><?php echo stripslashes($book->name). ' ('.$book->count.')' ?></option>
									<?php endforeach ?>
								</select>
							</td>
							<td class="fieldname rightcolumn"><?php _e('Series', $sermon_domain) ?></td>
							<td class="field"><select name="series" id="series">
									<option value="0" <?php echo (isset($_REQUEST['series']) && $_REQUEST['series'] != 0) ? '' : 'selected="selected"' ?>><?php _e('[All]', $sermon_domain) ?></option>
									<?php foreach ($series as $item): ?>
									<option value="<?php echo $item->id ?>" <?php echo isset($_REQUEST['series']) && $_REQUEST['series'] == $item->id ? 'selected="selected"' : '' ?>><?php echo stripslashes($item->name).' ('.$item->count.')' ?></option>
									<?php endforeach ?>
								</select>
							</td>
						</tr>
						<tr>
							<td class="fieldname"><?php _e('Start date', $sermon_domain) ?></td>
							<td class="field"><input type="text" name="date" id="date" value="<?php echo isset($_REQUEST['date']) ? mysql_real_escape_string($_REQUEST['date']) : '' ?>" /></td>
							<td class="fieldname rightcolumn"><?php _e('End date', $sermon_domain) ?></td>
							<td class="field"><input type="text" name="enddate" id="enddate" value="<?php echo isset($_REQUEST['enddate']) ? mysql_real_escape_string($_REQUEST['enddate']) : '' ?>" /></td>
						</tr>
						<tr>
							<td class="fieldname"><?php _e('Keywords', $sermon_domain) ?></td>
							<td class="field" colspan="3"><input style="width: 98.5%" type="text" id="title" name="title" value="<?php echo isset($_REQUEST['title']) ? mysql_real_escape_string($_REQUEST['title']) : '' ?>" /></td>
						</tr>
						<tr>
							<td class="fieldname"><?php _e('Sort by', $sermon_domain) ?></td>
							<td class="field"><select name="sortby" id="sortby">
									<?php foreach ($sb as $k => $v): ?>
									<option value="<?php echo $v ?>" <?php echo $csb == $v ? 'selected="selected"' : '' ?>><?php _e($k, $sermon_domain) ?></option>
									<?php endforeach ?>
								</select>
							</td>
							<td class="fieldname rightcolumn"><?php _e('Direction', $sermon_domain) ?></td>
							<td class="field"><select name="dir" id="dir">
									<?php foreach ($di as $k => $v): ?>
									<option value="<?php echo $v ?>" <?php echo $cd == $v ? 'selected="selected"' : '' ?>><?php _e($k, $sermon_domain) ?></option>
									<?php endforeach ?>
								</select>
							</td>
						</tr>
						<tr>
							<td colspan="3">&nbsp;</td>
							<td class="field"><input type="submit" class="filter" value="<?php _e('Filter &raquo;', $sermon_domain) ?>">			</td>
						</tr>
					</table>
					<input type="hidden" name="page" value="1">
				</div>
			</form>
		</div>
		<script type="text/javascript">
			jQuery.datePicker.setDateFormat('ymd','-');
			jQuery('#date').datePicker({startDate:'01/01/1970'});
			jQuery('#enddate').datePicker({startDate:'01/01/1970'});
			<?php if ($hide_filter === TRUE) { ?>
			jQuery(document).ready(function() {
				<?php echo $js_hide; ?>
			});
			<?php } ?>
		</script>
	<?php
	}
}
// Returns the first MP3 file attached to a sermon
// Stats have to be turned off for iTunes compatibility
function sb_first_mp3($sermon, $stats= TRUE) {
	$user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
	if (stripos($user_agent, 'itunes') !== FALSE | stripos($user_agent, 'FeedBurner') !== FALSE)
		$stats = FALSE;
	$stuff = sb_get_stuff($sermon, true);
	$stuff = array_merge((array)$stuff['Files'], (array)$stuff['URLs']);
	foreach ((array) $stuff as $file) {
		if (strtolower(substr($file, strrpos($file, '.') + 1)) == 'mp3') {
			if (substr($file,0,7) == "http://") {
				if ($stats)
					$file=sb_display_url().sb_query_char().'show&amp;url='.rawurlencode($file);
			} else {
				if (!$stats)
					$file=trailingslashit(site_url()).sb_get_option('upload_dir').rawurlencode($file);
				else
					$file=sb_display_url().sb_query_char().'show&amp;file_name='.rawurlencode($file);
			}
			return $file;
			break;
		}
	}
}

//Gets colour for mini-flash player from the options of another flash player plugin.
function sb_get_flash_player_colour ($type) {
	if ($type == 'foreground') {
		//AudioPlayer v2
		$options = get_option('AudioPlayer_options');
		if ($options)
			return $options['colorScheme']['rightbg'];
		//AudioPlayer v1
		$options = get_option('audio_player_rightbgcolor');
		if ($options)
			return str_replace('0x', '', $options);
		//Default
		return '000000';
	} elseif ($type == 'background') {
		//AudioPlayer v2
		$options = get_option('AudioPlayer_options');
		if ($options)
			if ($options['colorScheme']['transparentpagebg'] == 'true')
				return 'transparent';
			else
				return $options['colorScheme']['rightbg'];
		//AudioPlayer v1
		$options = get_option('audio_player_transparentpagebgcolor');
		if ($options)
			return 'transparent';
		else
			return str_replace('0x', '', get_option('audio_player_pagebgcolor'));

	}
}

// Displays the mini flash mp3 player
function sb_display_mini_player ($sermon, $id=1, $flashvars="") {
	$filename = sb_first_mp3($sermon, FALSE);
	if ($filename !="") {
		$flashvars .= "&foreColor=#".sb_get_flash_player_colour ('foreground');
		$flashvars .= "&filename=".$filename;
		if (substr($flashvars, 0, 1) == "&")
			$flashvars = substr($flashvars, 1);
		echo " <span class=\"sermon-player\"><embed id=\"oneBitInsert_{$id}\" width=\"10\" height=\"10\"";
		if (sb_get_flash_player_colour ('background') == 'transparent')
			echo " wmode=\"transparent\"";
		else
			echo " bgcolor=\"0x".sb_get_flash_player_colour ('background')."\"";
		echo " quality=\"high\"";
		echo " flashvars=\"".$flashvars."\"";
		echo " src=\"".SB_PLUGIN_URL."/sb-includes/1bit.swf\"";
		echo " type=\"application/x-shockwave-flash\"/></span>";
	}
}
?>