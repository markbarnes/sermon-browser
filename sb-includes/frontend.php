<?php
if (!defined('ABSPATH'))
	exit;
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- the plugin's own tables, which WordPress has no API or cache for
// phpcs:disable WordPress.Security.NonceVerification.Recommended -- the sermon list, its filters, feed and downloads are read-only GET requests
// Function to display sermons for users to add to their template
function sb_display_sermons($options = array()) {
	$default = array(
		'display_preacher' => 1,
		'display_passage' => 1,
		'display_date' => 1,
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
		sb_print_sermon_link($sermons[0], true);
	else {
		echo "<ul class=\"sermon-widget\">\r";
		foreach ((array) $sermons as $sermon) {
			echo "\t<li>";
			echo "<span class=\"sermon-title\"><a href=\"";
			sb_print_sermon_link($sermon, true);
			echo "\">".esc_html(stripslashes($sermon->title))."</a></span>";
			if ($display_passage) {
				$foo = sb_unserialize($sermon->start);
				$bar = sb_unserialize($sermon->end);
				echo "<span class=\"sermon-passage\"> (".(isset($foo[0], $bar[0]) ? wp_kses_post(sb_get_books($foo[0], $bar[0])) : '').")</span>";
			}
			if ($display_preacher) {
				echo "<span class=\"sermon-preacher\">".esc_html__('by', 'sermon-browser')." <a href=\"";
				sb_print_preacher_link($sermon);
				echo "\">".esc_html(stripslashes($sermon->preacher))."</a></span>";
			}
			if ($display_date)
				echo " <span class=\"sermon-date\">".esc_html__('on', 'sermon-browser')." ".esc_html(sb_formatted_date ($sermon))."</span>";
			echo ".</li>\r";
		}
		echo "</ul>\r";
	}
}

// Displays the widget
function sb_widget_sermon($args, $widget_args=1) {
	extract( $args, EXTR_SKIP );
	if ( is_numeric($widget_args) )
		$widget_args = array( 'number' => $widget_args );
	$widget_args = wp_parse_args( $widget_args, array( 'number' => -1 ) );
	extract( $widget_args, EXTR_SKIP );
	$options = sb_get_option('sermons_widget_options');
	if ( !isset($options[$number]) )
		return;
	extract($options[$number]);
	echo $before_widget; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- markup from the theme's register_sidebar()
	echo $before_title . esc_html($title) . $after_title; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- markup from the theme's register_sidebar()
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
		echo '<a href="'.esc_url(sb_build_url(array('sermon_id' => $sermon->id), true)).'">'.esc_html(stripslashes($sermon->title)).'</a></span>';
		if ($book) {
			$foo = sb_unserialize($sermon->start);
			$bar = sb_unserialize($sermon->end);
			if (isset ($foo[0]) && isset($bar[0]))
				echo " <span class=\"sermon-passage\">(".wp_kses_post(sb_get_books($foo[0], $bar[0])).")</span>";
		}
		if ($preacherz) {
			echo " <span class=\"sermon-preacher\">".esc_html__('by', 'sermon-browser')." <a href=\"";
			sb_print_preacher_link($sermon);
			echo "\">".esc_html(stripslashes($sermon->preacher))."</a></span>";
		}
		if ($date)
			echo " <span class=\"sermon-date\">".esc_html__(' on ', 'sermon-browser').esc_html(sb_formatted_date ($sermon))."</span>";
		echo ".</li>";
	}
	echo "</ul>";
	echo $after_widget; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- markup from the theme's register_sidebar()
}

// Displays the tag cloud in the sidebar
function sb_widget_tag_cloud ($args) {
	extract($args);
	echo $before_widget; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- markup from the theme's register_sidebar()
	echo $before_title.esc_html__('Sermon Browser tags', 'sermon-browser').$after_title; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- markup from the theme's register_sidebar()
	sb_print_tag_clouds();
	echo $after_widget; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- markup from the theme's register_sidebar()
}

function sb_admin_bar_menu () {
	global $wp_admin_bar;
	if (!current_user_can('edit_posts') || !class_exists('WP_Admin_Bar'))
		return;
	if (isset($_GET['sermon_id']) && (int)$_GET['sermon_id'] != 0 && current_user_can('publish_pages')) {
		$wp_admin_bar->add_menu(array('id' => 'sermon-browser-menu', 'title' => __('Edit Sermon', 'sermon-browser'), 'href' => admin_url('admin.php?page=sermon-browser/new_sermon.php&mid='.(int)$_GET['sermon_id'])));
		$wp_admin_bar->add_menu(array('parent' => 'sermon-browser-menu', 'id' => 'sermon-browser-sermons', 'title' => __('List Sermons', 'sermon-browser'), 'href' => admin_url('admin.php?page=sermon-browser/sermon.php')));
	} else {
		$wp_admin_bar->add_menu(array('id' => 'sermon-browser-menu', 'title' => __('Sermons', 'sermon-browser'), 'href' => admin_url('admin.php?page=sermon-browser/sermon.php')));
		if (current_user_can('publish_pages'))
			$wp_admin_bar->add_menu(array('parent' => 'sermon-browser-menu', 'id' => 'sermon-browser-add', 'title' => __('Add Sermon', 'sermon-browser'), 'href' => admin_url('admin.php?page=sermon-browser/new_sermon.php')));
	}
	if (current_user_can('upload_files'))
		$wp_admin_bar->add_menu(array('parent' => 'sermon-browser-menu', 'id' => 'sermon-browser-files', 'title' => __('Files', 'sermon-browser'), 'href' => admin_url('admin.php?page=sermon-browser/files.php')));
	if (current_user_can('manage_categories')) {
		$wp_admin_bar->add_menu(array('parent' => 'sermon-browser-menu', 'id' => 'sermon-browser-preachers', 'title' => __('Preachers', 'sermon-browser'), 'href' => admin_url('admin.php?page=sermon-browser/preachers.php')));
		$wp_admin_bar->add_menu(array('parent' => 'sermon-browser-menu', 'id' => 'sermon-browser-series', 'title' => __('Series &amp; Services', 'sermon-browser'), 'href' => admin_url('admin.php?page=sermon-browser/manage.php')));
	}
	if (current_user_can('manage_options')) {
		$wp_admin_bar->add_menu(array('parent' => 'sermon-browser-menu', 'id' => 'sermon-browser-options', 'title' => __('Options', 'sermon-browser'), 'href' => admin_url('admin.php?page=sermon-browser/options.php')));
		$wp_admin_bar->add_menu(array('parent' => 'sermon-browser-menu', 'id' => 'sermon-browser-series', 'title' => __('Templates', 'sermon-browser'), 'href' => admin_url('admin.php?page=sermon-browser/templates.php')));
	}
	if (current_user_can('edit_plugins'))
		$wp_admin_bar->add_menu(array('parent' => 'sermon-browser-menu', 'id' => 'sermon-browser-uninstall', 'title' => __('Uninstall', 'sermon-browser'), 'href' => admin_url('admin.php?page=sermon-browser/uninstall.php')));
	$wp_admin_bar->add_menu(array('parent' => 'sermon-browser-menu', 'id' => 'sermon-browser-help', 'title' => __('Help', 'sermon-browser'), 'href' => admin_url('admin.php?page=sermon-browser/help.php')));
	$wp_admin_bar->add_menu(array('parent' => 'new-content', 'id' => 'sermon-browser-add2', 'title' => __('Sermon', 'sermon-browser'), 'href' => admin_url('admin.php?page=sermon-browser/new_sermon.php')));
}

// Sorts an object by rank
function sb_sort_object($a,$b) {
	if(  $a->rank ==  $b->rank )
		return 0;
	return ($a->rank < $b->rank) ? -1 : 1;
}

// Displays the most popular sermons in the sidebar
function sb_widget_popular ($args) {
	global $wpdb;
	extract($args);
	if (!isset($suffix))
		$suffix = '_w';
	if (!isset($options))
		$options = sb_get_option('popular_widget_options');
	if (!is_array($options))
		$options = array('title' => '', 'limit' => 5, 'display_sermons' => true, 'display_series' => true, 'display_preachers' => true);
	echo $before_widget; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- markup from the theme's register_sidebar()
	if ($options['title'] != '')
		echo $before_title.esc_html($options['title']).$after_title; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- markup from the theme's register_sidebar()
	$jscript = '';
	$trigger = array();
	$output = array();
	$series_final = array();
	$preachers_final = array();
	if ($options['display_sermons']) {
		$sermons = $wpdb->get_results("SELECT sermons.id, sermons.title, sum(stuff.count) AS total
									   FROM {$wpdb->prefix}sb_stuff AS stuff
									   JOIN {$wpdb->prefix}sb_sermons AS sermons ON stuff.sermon_id = sermons.id
									   GROUP BY sermons.id ORDER BY total DESC LIMIT 0, ".(int) $options['limit']);
		if ($sermons) {
			$output['sermons'] = '<div class="popular-sermons'.$suffix.'"><ul>';
			foreach ($sermons as $sermon)
				$output['sermons'] .= '<li><a href="'.sb_build_url(array('sermon_id' => $sermon->id), true).'">'.esc_html(stripslashes($sermon->title)).'</a></li>';
			$output['sermons'] .= '</ul></div>';
			$trigger[] = '<a id="popular_sermons_trigger'.$suffix.'" href="#">Sermons</a>';
			$jscript .=  'jQuery("#popular_sermons_trigger'.$suffix.'").click(function() {
							jQuery(this).attr("style", "font-weight:bold");
							jQuery("#popular_series_trigger'.$suffix.'").removeAttr("style");
							jQuery("#popular_preachers_trigger'.$suffix.'").removeAttr("style");
							jQuery.setSbCookie ("sermons");
							jQuery("#sb_popular_wrapper'.$suffix.'").fadeOut("slow", function() {
								jQuery("#sb_popular_wrapper'.$suffix.'").html('.wp_json_encode(isset($output['sermons']) ? $output['sermons'] : '').').fadeIn("slow");
							});
							return false;
						});';
		}
	}

	if ($options['display_series']) {
		$series1 = $wpdb->get_results("SELECT series.id, series.name, avg(stuff.count) AS average
									   FROM {$wpdb->prefix}sb_stuff AS stuff
									   JOIN {$wpdb->prefix}sb_sermons AS sermons ON stuff.sermon_id = sermons.id
									   JOIN {$wpdb->prefix}sb_series AS series ON sermons.series_id = series.id
									   GROUP BY series.id ORDER BY average DESC");
		$series2 = $wpdb->get_results("SELECT series.id, sum(stuff.count) AS total
									   FROM {$wpdb->prefix}sb_stuff AS stuff
									   JOIN {$wpdb->prefix}sb_sermons AS sermons ON stuff.sermon_id = sermons.id
									   JOIN {$wpdb->prefix}sb_series AS series ON sermons.series_id = series.id
									   GROUP BY series.id ORDER BY total DESC");
		if ($series1) {
			$i=1;
			foreach ($series1 as $series) {
				$series_final[$series->id] = new stdClass();
				$series_final[$series->id]->name = $series->name;
				$series_final[$series->id]->rank = $i;
				$series_final[$series->id]->id = $series->id;
				$i++;
			}
			$i=1;
			foreach ($series2 as $series) {
				if (isset($series_final[$series->id]))
					$series_final[$series->id]->rank += $i;
				$i++;
			}
			usort($series_final,'sb_sort_object');
			$series_final = array_slice($series_final, 0, $options['limit']);
			$output['series'] = '<div class="popular-series'.$suffix.'"><ul>';
			foreach ($series_final as $series)
				$output['series'] .= '<li><a href="'.sb_build_url(array('series' => $series->id), true).'">'.esc_html(stripslashes($series->name)).'</a></li>';
			$output['series'] .= '</ul></div>';
		}
		$trigger[] = '<a id="popular_series_trigger'.$suffix.'" href="#">Series</a>';
		$jscript .=	 'jQuery("#popular_series_trigger'.$suffix.'").click(function() {
							jQuery(this).attr("style", "font-weight:bold");
							jQuery("#popular_sermons_trigger'.$suffix.'").removeAttr("style");
							jQuery("#popular_preachers_trigger'.$suffix.'").removeAttr("style");
							jQuery.setSbCookie ("series");
							jQuery("#sb_popular_wrapper'.$suffix.'").fadeOut("slow", function() {
								jQuery("#sb_popular_wrapper'.$suffix.'").html('.wp_json_encode(isset($output['series']) ? $output['series'] : '').').fadeIn("slow");
							});
							return false;
						});';
	}

	if ($options['display_preachers']) {
		$preachers1 = $wpdb->get_results("SELECT preachers.id, preachers.name, avg(stuff.count) AS average
										  FROM {$wpdb->prefix}sb_stuff AS stuff
										  JOIN {$wpdb->prefix}sb_sermons AS sermons ON stuff.sermon_id = sermons.id
										  JOIN {$wpdb->prefix}sb_preachers AS preachers ON sermons.preacher_id = preachers.id
										  GROUP BY preachers.id
										  ORDER BY average DESC");
		$preachers2 = $wpdb->get_results("SELECT preachers.id, sum(stuff.count) AS total
										  FROM {$wpdb->prefix}sb_stuff AS stuff
										  JOIN {$wpdb->prefix}sb_sermons AS sermons ON stuff.sermon_id = sermons.id
										  JOIN {$wpdb->prefix}sb_preachers AS preachers ON sermons.preacher_id = preachers.id
										  GROUP BY preachers.id
										  ORDER BY total DESC");
		if ($preachers1) {
			$i=1;
			foreach ($preachers1 as $preacher) {
				$preachers_final[$preacher->id] = new stdClass();
				$preachers_final[$preacher->id]->name = $preacher->name;
				$preachers_final[$preacher->id]->rank = $i;
				$preachers_final[$preacher->id]->id = $preacher->id;
				$i++;
			}
			$i=1;
			foreach ($preachers2 as $preacher) {
				if (isset($preachers_final[$preacher->id]))
					$preachers_final[$preacher->id]->rank += $i;
				$i++;
			}
			usort($preachers_final,'sb_sort_object');
			$preachers_final = array_slice($preachers_final, 0, $options['limit']);
			$output['preachers'] = '<div class="popular-preachers'.$suffix.'"><ul>';
			foreach ($preachers_final as $preacher)
				$output['preachers'] .= '<li><a href="'.sb_build_url(array('preacher' => $preacher->id), true).'">'.esc_html(stripslashes($preacher->name)).'</a></li>';
			$output['preachers'] .= '</ul></div>';
			$trigger[] = '<a id="popular_preachers_trigger'.$suffix.'" href="#">Preachers</a>';
			$jscript .=	 'jQuery("#popular_preachers_trigger'.$suffix.'").click(function() {
								jQuery(this).attr("style", "font-weight:bold");
								jQuery("#popular_series_trigger'.$suffix.'").removeAttr("style");
								jQuery("#popular_sermons_trigger'.$suffix.'").removeAttr("style");
								jQuery.setSbCookie("preachers");
								jQuery("#sb_popular_wrapper'.$suffix.'").fadeOut("slow", function() {
									jQuery("#sb_popular_wrapper'.$suffix.'").html('.wp_json_encode(isset($output['preachers']) ? $output['preachers'] : '').').fadeIn("slow");
								});
								return false;
							 });';
		}
	}

	$jscript .= 'if (jQuery.getSbCookie() == "preachers") { jQuery("#popular_preachers_trigger'.$suffix.'").attr("style", "font-weight:bold"); jQuery("#sb_popular_wrapper'.$suffix.'").html('.wp_json_encode(isset($output['preachers']) ? $output['preachers'] : '').')};';
	$jscript .= 'if (jQuery.getSbCookie() == "series") { jQuery("#popular_series_trigger'.$suffix.'").attr("style", "font-weight:bold"); jQuery("#sb_popular_wrapper'.$suffix.'").html('.wp_json_encode(isset($output['series']) ? $output['series'] : '').')};';
	$jscript .= 'if (jQuery.getSbCookie() == "sermons") { jQuery("#popular_sermons_trigger'.$suffix.'").attr("style", "font-weight:bold"); jQuery("#sb_popular_wrapper'.$suffix.'").html('.wp_json_encode(isset($output['sermons']) ? $output['sermons'] : '').')};';
	echo '<p>'.wp_kses_post(implode (' | ', $trigger)).'</p>';
	echo '<div id="sb_popular_wrapper'.esc_attr($suffix).'">'.wp_kses_post(current($output)).'</div>';
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
	echo '<script type="text/javascript">jQuery(document).ready(function() {'.$jscript.'});</script>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JavaScript built above, with its HTML encoded by wp_json_encode()
	echo $after_widget; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- markup from the theme's register_sidebar()
}

function sb_print_most_popular() {
	$args['before_widget'] = '<div id="sermon_most_popular" style="border: 1px solid ; margin: 0pt 0pt 1em 2em; padding: 5px; float: right; font-size: 75%; line-height: 1em">';
	$args['after_widget'] = '</div>';
	$args['before_title'] = '<span class="popular_title">';
	$args['after_title'] = '</span>';
	$args['suffix'] = '_f';
	sb_widget_popular ($args);
}

//Downloads external webpage. Used to add Bible passages to sermon page.
function sb_download_page ($page_url, $headers = array()) {
	$response = wp_safe_remote_get($page_url, array ('headers' => $headers, 'timeout' => 10));
	if (is_wp_error($response) || wp_remote_retrieve_response_code($response) != 200)
		return '';
	return wp_remote_retrieve_body($response);
}

// Returns human friendly Bible reference (e.g. John 3:1-16, not John 3:1-John 3:16)
function sb_tidy_reference ($start, $end, $add_link = FALSE) {
	if (!is_array($start) || !is_array($end) || !isset($start['book']) || !trim($start['book'])) {
		return "";
	}
	$translated_books = array_combine(sb_get_default('eng_bible_books'), sb_get_default('bible_books'));
	$start_book = esc_html(isset($translated_books[trim($start['book'])]) ? $translated_books[trim($start['book'])] : trim($start['book']));
	$end_book = esc_html(isset($translated_books[trim($end['book'])]) ? $translated_books[trim($end['book'])] : trim($end['book']));
	$start_chapter = (int) $start['chapter'];
	$end_chapter = (int) $end['chapter'];
	$start_verse = (int) $start['verse'];
	$end_verse = (int) $end['verse'];
	if ($add_link) {
		$start_book = "<a href=\"".sb_get_book_link(trim($start['book']))."\">{$start_book}</a>";
		$end_book = "<a href=\"".sb_get_book_link(trim($end['book']))."\">{$end_book}</a>";
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
	echo "<p class='bible-passage'>".wp_kses_post(sb_tidy_reference($start, $end))."</p>";
}

// Returns human friendly Bible reference with link to filter
function sb_get_books($start, $end) {
	return sb_tidy_reference ($start, $end, TRUE);
}

//Add Bible text to single sermon page
//Other versions (KJV, ASV and so on) came from api.preachingcentral.com, which no longer exists, so their template tags print nothing
function sb_add_bible_text ($start, $end, $version) {
	if ($version == 'esv')
		return sb_add_esv_text ($start, $end);
	elseif ($version == 'net')
		return sb_add_net_text ($start, $end);
	else
		return '';
}

//Returns ESV text
function sb_add_esv_text ($start, $end) {
	$api_key = sb_get_option('esv_api_key');
	if ($api_key) {
		$header = array('Authorization' => 'Token '.$api_key);
		$esv_url = 'https://api.esv.org/v3/passage/html/?q='.rawurlencode(wp_specialchars_decode(sb_tidy_reference ($start, $end))).'&include-headings=false&include-footnotes=false';
		$api_body = sb_download_page ($esv_url, $header);
		$decode = json_decode ($api_body);
		if (is_object($decode) && isset($decode->passages) && is_array($decode->passages)) {
			return wp_kses_post(implode ('', $decode->passages));
		}
	}
	return '';
}

// Converts XML string to object
function sb_get_xml ($content) {
	if ($content == '')
		return false;
	$previous = libxml_use_internal_errors(true);
	try {
		$xml = new SimpleXMLElement($content, LIBXML_NONET);
	} catch (Exception $e) {
		$xml = false;
	}
	libxml_use_internal_errors($previous);
	return $xml;
}

//Returns NET Bible text
function sb_add_net_text ($start, $end) {
	$reference = rawurlencode(wp_specialchars_decode(sb_tidy_reference ($start, $end)));
	$old_chapter = $start['chapter'];
	// https://labs.bible.org/api_web_service
	$net_url = "https://labs.bible.org/api/?passage={$reference}&type=xml";
	$xml = sb_get_xml(sb_download_page($net_url.'&formatting=para'));
	if (!$xml)
		return '';
	$output='';
	$items = $xml->item;
	foreach ($items as $item) {
		if ($item->text != '[[EMPTY]]') {
			if (substr($item->text, 0, 8) == '<p class') {
				$paraend = stripos($item->text, '>', 8)+1;
				$output .= "\n".substr($item->text, 0, $paraend);
				$item->text = substr ($item->text, $paraend);
			}
			if ($old_chapter == $item->chapter) {
				$output .= " <span class=\"verse-num\">".(int) $item->verse."</span>";
			} else {
				$output .= " <span class=\"chapter-num\">".(int) $item->chapter.":".(int) $item->verse."</span> ";
				$old_chapter = strval($item->chapter);
			}
			$output .= 	$item->text;
		}
	}
	return "<div class=\"net\">\r<h2>".sb_tidy_reference ($start, $end)."</h2><p>".wp_kses_post($output)." (<a href=\"https://netbible.org/bible/{$reference}\">NET Bible</a>)</p></div>";
}

//Adds edit sermon link if current user has edit rights
function sb_edit_link ($id) {
	if (current_user_can('publish_posts')) {
		$id = (int)$id;
		echo '<div class="sb_edit_link"><a href="'.esc_url(admin_url('admin.php?page=sermon-browser/new_sermon.php&mid='.$id)).'">'.esc_html__('Edit Sermon', 'sermon-browser').'</a></div>';
	}
}

// Returns URL for search links
// Relative links now deprecated
function sb_build_url($arr, $clear = false) {
	global $wpdb;
	// Word list for URL building purpose
	$wl = array('preacher', 'title', 'date', 'enddate', 'series', 'service', 'sortby', 'dir', 'book', 'stag', 'podcast');
	$foo = array_merge((array) $_GET, (array) $_POST, $arr); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- the filter form is a read-only search; values are URL-encoded below
	foreach ($foo as $k => $v) {
		if (is_scalar($v) && (in_array($k, array_keys($arr)) || (in_array($k, $wl) && !$clear))) {
			$bar[] = rawurlencode($k).'='.rawurlencode($v);
		}
	}
	if (isset($bar)) {
			return esc_url(sb_display_url().sb_query_char().implode('&', $bar));
	}
	else {
		return sb_display_url();
	}
}

// Adds javascript and CSS where required
function sb_add_headers() {
	global $post, $wpdb, $wp_scripts;
	if (isset($post->ID) && $post->ID != '') {
		echo "<!-- Added by SermonBrowser (version ".esc_html(SB_CURRENT_VERSION).") - http://www.sermonbrowser.com/ -->\r";
		echo "<link rel=\"alternate\" type=\"application/rss+xml\" title=\"".esc_attr__('Sermon podcast', 'sermon-browser')."\" href=\"".esc_url(sb_get_option('podcast_url'))."\" />\r";
		wp_enqueue_style('sb_style');
		$pageid = $wpdb->get_var($wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE post_content LIKE %s AND (post_status = 'publish' OR post_status = 'private') AND ID = %d AND post_date < NOW();", '%'.$wpdb->esc_like('[sermons').'%', $post->ID));
		if ($pageid !== NULL) {
			if (sb_get_option('filter_type') == 'dropdown') {
				wp_enqueue_script('sb_datepicker');
				wp_enqueue_style ('sb_datepicker');
			}
			if (isset($_REQUEST['title']) || isset($_REQUEST['preacher']) || isset($_REQUEST['date']) || isset($_REQUEST['enddate']) || isset($_REQUEST['series']) || isset($_REQUEST['service']) || isset($_REQUEST['book']) || isset($_REQUEST['stag']))
				echo "<link rel=\"alternate\" type=\"application/rss+xml\" title=\"".esc_attr__('Custom sermon podcast', 'sermon-browser')."\" href=\"".esc_url(sb_podcast_url())."\" />\r";
			wp_enqueue_script('jquery');
		} else {
			if (is_active_widget(false, 'sermon-browser-popular'))
				wp_enqueue_script('jquery');
		}
	}
}

// Formats date into words
function sb_formatted_date ($sermon) {
	if (isset($sermon->time) && $sermon->time != '')
		$sermon_time = $sermon->time;
	else
		$sermon_time = sb_default_time ($sermon->sid);
	if ($sermon->datetime == '1970-01-01 00:00:00')
		return __('Unknown Date', 'sermon-browser');
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
		echo esc_url(sb_build_url(array('sermon_id' => $sermon->id), true));
	else
		return sb_build_url(array('sermon_id' => $sermon->id), true);
}

// Prints preacher search URL
function sb_print_preacher_link($sermon) {
	echo esc_url(sb_build_url(array('preacher' => $sermon->pid), false));
}

// Prints series search URL
function sb_print_series_link($sermon) {
	echo esc_url(sb_build_url(array('series' => $sermon->ssid), false));
}

// Prints service search URL
function sb_print_service_link($sermon) {
	echo esc_url(sb_build_url(array('service' => $sermon->sid), false));
}

// Prints bible book search URL
function sb_get_book_link($book_name) {
	return sb_build_url(array('book' => $book_name), false);
}

// Prints tag search URL
function sb_get_tag_link($tag) {
	return sb_build_url(array('stag' => $tag), false);
}

// Prints tags
function sb_print_tags($tags) {
	$out = array();
	foreach ((array) $tags as $tag) {
		$tag = stripslashes($tag);
		$out[] = '<a href="'.esc_url(sb_get_tag_link($tag)).'">'.esc_html($tag).'</a>';
	}
	echo wp_kses_post(implode(', ', (array) $out));
}

//Prints tag cloud
function sb_print_tag_clouds($minfont=80, $maxfont=150) {
	global $wpdb;
	$rawtags = $wpdb->get_results("SELECT name FROM {$wpdb->prefix}sb_tags as t RIGHT JOIN {$wpdb->prefix}sb_sermons_tags as st ON t.id = st.tag_id");
	$cnt = array();
	$out = array();
	foreach ($rawtags as $tag) {
		if (isset($cnt[$tag->name]))
			$cnt[$tag->name]++;
		else
			$cnt[$tag->name] = 1;
	}
	unset($cnt['']);
	if (count($cnt) == 0)
		return;
	$fontrange = $maxfont - $minfont;
	$maxcnt = 0;
	$mincnt = 1000000;
	foreach ($cnt as $cur) {
		if ($cur > $maxcnt) $maxcnt = $cur;
		if ($cur < $mincnt) $mincnt = $cur;
	}
	$cntrange = $maxcnt + 1 - $mincnt;
	$minlog = log($mincnt);
	$maxlog = log($maxcnt);
	$logrange = $maxlog == $minlog ? 1 : $maxlog - $minlog;
	arsort($cnt);
	foreach ($cnt as $tag => $count) {
		$size = $minfont + $fontrange * (log($count) - $minlog) / $logrange;
		$out[] = '<a style="font-size:'.(int) $size.'%" href="'.esc_url(sb_get_tag_link($tag)).'">'.esc_html(stripslashes($tag)).'</a>';
	}
	echo wp_kses_post(implode(' ', $out));
}

//Prints link to next page
function sb_print_next_page_link($limit = 0) {
	global $record_count;
	if ($limit == 0)
		$limit = sb_get_option('sermons_per_page');
	$current = isset($_REQUEST['pagenum']) ? (int) $_REQUEST['pagenum'] : 1;
	if ($current < ceil($record_count / $limit)) {
		$url = sb_build_url(array('pagenum' => ++$current), false);
		echo '<a href="'.esc_url($url).'">'.esc_html__('Next page', 'sermon-browser').' &raquo;</a>';
	}
}

//Prints link to previous page
function sb_print_prev_page_link($limit = 0) {
	if ($limit == 0) $limit = sb_get_option('sermons_per_page');
	$current = isset($_REQUEST['pagenum']) ? (int) $_REQUEST['pagenum'] : 1;
	if ($current > 1) {
		$url = sb_build_url(array('pagenum' => --$current), false);
		echo '<a href="'.esc_url($url).'">&laquo; '.esc_html__('Previous page', 'sermon-browser').'</a>';
	}
}

// Print link to attached files
function sb_print_url($url) {
	require (SB_INCLUDES_DIR.'/filetypes.php');
	$pathinfo = pathinfo($url);
	$ext = isset($pathinfo['extension']) ? $pathinfo['extension'] : '';
	if ((substr($url,0,7) == "http://") or (substr($url,0,8) == 'https://'))
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
		if (do_shortcode(sb_get_option('mp3_shortcode')) != sb_get_option('mp3_shortcode')) {
			echo do_shortcode(str_ireplace('%SERMONURL%', $url, sb_get_option('mp3_shortcode'))); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the player from the shortcode the site owner chose
			return;
		}
	}
	$uicon = SB_PLUGIN_URL.'/sb-includes/icons/'.$uicon;
	if (!isset($filetypes[$ext]['name']))
		/* translators: %s: a file extension, such as pdf */
		$filetypes[$ext]['name'] = sprintf(__('%s file', 'sermon-browser'), $ext);
	echo '<a href="'.esc_url($url).'"><img class="site-icon" alt="'.esc_attr($filetypes[$ext]['name']).'" title="'.esc_attr($filetypes[$ext]['name']).'" src="'.esc_url($uicon).'"></a>';
}

// Print link to attached external URLs
function sb_print_url_link($url) {
	echo '<div class="sermon_file">';
	sb_print_url ($url);
	if (strtolower(substr($url, -4)) == ".mp3") {
		if ((substr($url,0,7) == "http://") or (substr($url,0,8) == 'https://')) {
			$param="url"; }
		else {
			$param="file_name"; }
		$url = rawurlencode($url);
        echo ' <a href="'.esc_url(sb_display_url().sb_query_char(false).'download&'.$param.'='.$url).'">'.esc_html__('Download', 'sermon-browser').'</a>';
	}
	echo '</div>';
}

//Decode base64 encoded data
function sb_print_code($code) {
	echo do_shortcode(sb_kses_embed(base64_decode($code))); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- filtered by sb_kses_embed()
}

//Prints preacher description
function sb_print_preacher_description($sermon) {
	if (strlen($sermon->preacher_description)>0) {
		echo "<div class='preacher-description'><span class='about'>" . esc_html__('About', 'sermon-browser').' '.esc_html(stripslashes($sermon->preacher)).': </span>';
		echo "<span class='description'>".wp_kses_post(stripslashes($sermon->preacher_description))."</span></div>";
	}
}

//Prints preacher image
function sb_print_preacher_image($sermon) {
	if ($sermon->image)
		echo "<img alt='".esc_attr(stripslashes($sermon->preacher))."' class='preacher' src='".esc_url(trailingslashit(site_url()).sb_get_option('upload_dir').'images/'.rawurlencode($sermon->image))."'>";
}

//Prints link to sermon preached next (but not today)
function sb_print_next_sermon_link($sermon) {
	global $wpdb;
	$next = $wpdb->get_row($wpdb->prepare("SELECT id, title FROM {$wpdb->prefix}sb_sermons WHERE DATE(datetime) > DATE(%s) AND id <> %d ORDER BY datetime asc LIMIT 1", $sermon->datetime, $sermon->id));
	if (!$next) return;
	echo '<a href="';
	sb_print_sermon_link($next);
	echo '">'.esc_html(stripslashes($next->title)).' &raquo;</a>';
}

//Prints link to sermon preached on previous days
function sb_print_prev_sermon_link($sermon) {
	global $wpdb;
	$prev = $wpdb->get_row($wpdb->prepare("SELECT id, title FROM {$wpdb->prefix}sb_sermons WHERE DATE(datetime) < DATE(%s) AND id <> %d ORDER BY datetime desc LIMIT 1", $sermon->datetime, $sermon->id));
	if (!$prev) return;
	echo '<a href="';
	sb_print_sermon_link($prev);
	echo '">&laquo; '.esc_html(stripslashes($prev->title)).'</a>';
}

//Prints links to other sermons preached on the same day
function sb_print_sameday_sermon_link($sermon) {
	global $wpdb;
	$same = $wpdb->get_results($wpdb->prepare("SELECT id, title FROM {$wpdb->prefix}sb_sermons WHERE DATE(datetime) = DATE(%s) AND id <> %d", $sermon->datetime, $sermon->id));
	if (!$same) {
		esc_html_e('None', 'sermon-browser');
		return;
	}
	$output = array();
	foreach ($same as $cur)
		$output[] = '<a href="'.esc_url(sb_print_sermon_link($cur, false)).'">'.esc_html(stripslashes($cur->title)).'</a>';
	echo wp_kses_post(implode(', ', $output));
}

//Gets single sermon from the database
function sb_get_single_sermon($id) {
	global $wpdb;
	$id = (int) $id;
	$sermon = $wpdb->get_row($wpdb->prepare("SELECT m.id, m.title, m.datetime, m.start, m.end, m.description, p.id as pid, p.name as preacher, p.image as image, p.description as preacher_description, s.id as sid, s.name as service FROM {$wpdb->prefix}sb_sermons as m, {$wpdb->prefix}sb_preachers as p, {$wpdb->prefix}sb_services as s where m.preacher_id = p.id and m.service_id = s.id and m.id = %d", $id));
	$series = $wpdb->get_row($wpdb->prepare("SELECT ss.id as ssid, ss.name as series FROM {$wpdb->prefix}sb_sermons as m, {$wpdb->prefix}sb_series as ss WHERE m.series_id = ss.id and m.id = %d", $id));
	if ($sermon) {
		if ($series) {
			$sermon->series = $series->series;
			$sermon->ssid = $series->ssid;
		}
		else {
			$sermon->series = '';
			$sermon->ssid = 0;
		}
		$file = $code = $tags = array();
		$stuff = $wpdb->get_results($wpdb->prepare("SELECT f.id, f.type, f.name FROM {$wpdb->prefix}sb_stuff as f WHERE sermon_id = %d ORDER BY id desc", $id));
		$rawtags = $wpdb->get_results($wpdb->prepare("SELECT t.name FROM {$wpdb->prefix}sb_sermons_tags as st LEFT JOIN {$wpdb->prefix}sb_tags as t ON st.tag_id = t.id WHERE st.sermon_id = %d ORDER BY t.name asc", $id));
		foreach ($rawtags as $tag) {
			$tags[] = $tag->name;
		}
		foreach ($stuff as $cur)
			${$cur->type}[] = $cur->name;
		$sermon->start = sb_unserialize($sermon->start);
		$sermon->end = sb_unserialize($sermon->end);
		return array(
			'Sermon' => $sermon,
			'Files' => $file,
			'Code' => $code,
			'Tags' => $tags,
		);
	} else
		return false;
}

//Returns the translated heading for a filter
function sb_filter_heading ($id) {
	$headings = array(
		'preacher' => __('Preacher', 'sermon-browser'),
		'book' => __('Book', 'sermon-browser'),
		'series' => __('Series', 'sermon-browser'),
		'service' => __('Service', 'sermon-browser'),
		'title' => __('Title', 'sermon-browser'),
	);
	return isset($headings[$id]) ? $headings[$id] : ucwords($id);
}

//Prints the filter line for a given parameter
function sb_print_filter_line ($id, $results, $filter, $display, $max_num = 7) {
	global $more_applied;
	$translate_book = FALSE;
	if ($id == 'book') {
		$translate_book = TRUE;
		$translated_books = array_combine(sb_get_default('eng_bible_books'), sb_get_default('bible_books'));
	}
	echo "<div id = \"".esc_attr($id)."\" class=\"filter\">\r<span class=\"filter-heading\">".esc_html(sb_filter_heading($id)).":</span> \r";
	$i = 1;
	$more = FALSE;
	foreach ($results as $result) {
		if ($i == $max_num) {
			echo "<span id=\"".esc_attr($id)."-more\">";
			$more = TRUE;
			$more_applied[] = $id;
		}
		if ($i != 1)
			echo ", \r";
			if ($translate_book) {
				echo '<a href="'.esc_url(sb_build_url(array($id => $result->$filter), false)).'">'.esc_html(isset($translated_books[stripslashes($result->$display)]) ? $translated_books[stripslashes($result->$display)] : stripslashes($result->$display)).'</a>&nbsp;('.(int) $result->count.')';
			}
			else {
				echo '<a href="'.esc_url(sb_build_url(array($id => $result->$filter), false)).'">'.esc_html(stripslashes($result->$display)).'</a>&nbsp;('.(int) $result->count.')';
			}
		$i++;
	}
	echo ".";
	if ($more)
		echo "</span>\r<span id=\"".esc_attr($id)."-more-link\" style=\"display:none\">&hellip; (<a  id=\"".esc_attr($id)."-toggle\" href=\"#\"><strong>".(int) ($i-$max_num).' '.esc_html__('more', 'sermon-browser').'</strong></a>)</span>';
	echo '</div>';
}

//Prints the filter line for the date parameter
function sb_print_date_filter_line ($dates) {
	global $more_applied;
	if (empty($dates))
		return;
	$date_output = "<div id = \"dates\" class=\"filter\">\r<span class=\"filter-heading\">".esc_html__('Date', 'sermon-browser').":</span> \r";
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
					$date_output .= '<a href="'.sb_build_url(Array ('date' => $date->year.'-'.$date->month.'-01', 'enddate' => $date->year.'-'.$date->month.'-31'), false).'">'.esc_html(date_i18n('F', strtotime("{$date->year}-{$date->month}-{$date->day}"))).'</a> ';
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
				$date_output .= '<a href="'.sb_build_url(Array ('date' => $date->year.'-01-01', 'enddate' => $date->year.'-12-31'), false).'">'.(int) $date->year.'</a> ';
				$previous_year = $date->year;
				$count = 1;
			} else
				$count++;
		}
		$date_output .= '('.$count.'), ';
	}
	if ($date_output != '')
		echo rtrim($date_output, ', ')."</div>\r"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built above from escaped URLs, names and integers
}

//Returns the filter URL minus a given parameter
function sb_url_minus_parameter ($param1, $param2='') {
	global $filter_options;
	$existing_params = array_merge((array) $_GET, (array) $_POST); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- the filter form is a read-only search; values are URL-encoded below
	$returned_query = array();
	foreach (array_keys($existing_params) as $query) {
		if (in_array($query, $filter_options) && $query != $param1 && $query != $param2) {
			if (is_scalar($existing_params[$query]))
				$returned_query[] = rawurlencode($query).'='.rawurlencode(wp_unslash($existing_params[$query]));
		}
	}
	if (count($returned_query) > 0)
		return esc_url(sb_display_url().sb_query_char().implode('&', $returned_query));
	else
		return sb_display_url();
}

//Displays the filter on sermon search page
function sb_print_filters($filter) {
	global $wpdb, $more_applied, $filter_options;
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
		$js_hide = str_replace ('SHOW', esc_js(__('Show filter', 'sermon-browser')), $js_hide);
		$js_hide = str_replace ('HIDE', esc_js(__('Hide filter', 'sermon-browser')), $js_hide);
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
						$date = isset($_REQUEST['date']) && is_string($_REQUEST['date']) ? sanitize_text_field(wp_unslash($_REQUEST['date'])) : '';
						$enddate = isset($_REQUEST['enddate']) && is_string($_REQUEST['enddate']) ? sanitize_text_field(wp_unslash($_REQUEST['enddate'])) : '';
						$output .= '<strong>'.esc_html__('Date', 'sermon-browser').'</strong>:&nbsp;';
						if (substr($date,0,4) == substr($enddate,0,4))
							$output .= (int)substr($date,0,4).'&nbsp;(<a href="'.sb_url_minus_parameter('date', 'enddate').'">x</a>)';
						if (substr($date,5,2) == substr($enddate,5,2))
							$output .= ', '.esc_html(date_i18n('F', strtotime($date))).' (<a href="'.sb_build_url(Array ('date' => (int)substr($date,0,4).'-01-01', 'enddate' => (int)substr($date,0,4).'-12-31'), false).'">x</a>)';
					} else {
						$output .= '<strong>'.esc_html(sb_filter_heading($filter_option)).'</strong>:&nbsp;*'.$filter_option.'*';
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
		// Sermon ids (integers), so the queries below need no placeholders
		$ids = '('.(count($ids) ? implode(', ', array_map('intval', $ids)) : '0').')';

		$preachers = $wpdb->get_results("SELECT p.*, count(p.id) AS count FROM {$wpdb->prefix}sb_preachers AS p JOIN {$wpdb->prefix}sb_sermons AS sermons ON p.id = sermons.preacher_id WHERE sermons.id IN {$ids} GROUP BY p.id ORDER BY count DESC, MAX(sermons.datetime) DESC, p.name ASC"); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $ids holds integers
		$series = $wpdb->get_results("SELECT ss.*, count(ss.id) AS count FROM {$wpdb->prefix}sb_series AS ss JOIN {$wpdb->prefix}sb_sermons AS sermons ON ss.id = sermons.series_id  WHERE sermons.id IN {$ids} GROUP BY ss.id ORDER BY MAX(sermons.datetime) DESC, ss.name ASC"); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $ids holds integers
		$services = $wpdb->get_results("SELECT s.*, count(s.id) AS count FROM {$wpdb->prefix}sb_services AS s JOIN {$wpdb->prefix}sb_sermons AS sermons ON s.id = sermons.service_id  WHERE sermons.id IN {$ids} GROUP BY s.id ORDER BY count DESC, s.name ASC"); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $ids holds integers
		$book_count = $wpdb->get_results("SELECT bs.book_name AS name, count(distinct bs.sermon_id) AS count FROM {$wpdb->prefix}sb_books_sermons AS bs JOIN {$wpdb->prefix}sb_books as b ON bs.book_name=b.name AND bs.sermon_id IN {$ids} GROUP BY b.id ORDER BY b.id"); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $ids holds integers
		$dates = $wpdb->get_results("SELECT YEAR(datetime) as year, MONTH (datetime) as month, DAY(datetime) as day FROM {$wpdb->prefix}sb_sermons WHERE id IN {$ids} ORDER BY datetime ASC"); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $ids holds integers

		$more_applied = array();
		$output = str_replace ('*preacher*', isset($preachers[0]->name) ? esc_html(stripslashes($preachers[0]->name)) : '', $output);
		$book = isset($_REQUEST['book']) ? sanitize_text_field(wp_unslash($_REQUEST['book'])) : '';
		$translated_books = array_combine(sb_get_default('eng_bible_books'), sb_get_default('bible_books'));
		$output = str_replace ('*book*', esc_html(isset($translated_books[$book]) ? $translated_books[$book] : $book), $output);
		$output = str_replace ('*service*', isset($services[0]->name) ? esc_html(stripslashes($services[0]->name)) : '', $output);
		$output = str_replace ('*series*', isset($series[0]->name) ? esc_html(stripslashes($series[0]->name)) : '', $output);
		$output = str_replace ('*title*', isset($_REQUEST['title']) ? esc_html(sanitize_text_field(wp_unslash($_REQUEST['title']))) : '', $output);

		echo "<span class=\"inline_controls\"><a href=\"#\" id=\"show_hide_filter\"></a></span>";
		if ($output != '')
			echo '<div class="filtered">'.esc_html__('Active filter', 'sermon-browser').': '.wp_kses_post($output)."</div>\r";
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
		if (count($more_applied) > 0 || $output != '' || $hide_custom_podcast === TRUE || $hide_filter === TRUE) {
			echo "<script type=\"text/javascript\">\r";
			echo "\tjQuery(document).ready(function() {\r";
			if ($hide_filter === TRUE)
				echo $js_hide."\r"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JavaScript above, with its text escaped by esc_js()
			if ($hide_custom_podcast === TRUE)
				echo "\t\tjQuery('.podcastcustom').hide();\r";
			if (count($more_applied) > 0) {
				foreach ($more_applied as $element_id) {
					$element_id = esc_js($element_id);
					echo "\t\tjQuery('#{$element_id}-more').hide();\r"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped with esc_js() above
					echo "\t\tjQuery('#{$element_id}-more-link').show();\r"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					echo "\t\tjQuery('a#{$element_id}-toggle').click(function() {\r"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					echo "\t\t\tjQuery('#{$element_id}-more').show();\r"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					echo "\t\t\tjQuery('#{$element_id}-more-link').hide();\r"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					echo "\t\t\treturn false;\r";
					echo "\t\t});\r";
				}
			}
			echo "\t});\r";
			echo "</script>\r";
		}
	} elseif ($filter['filter'] == 'dropdown') {
		// Drop-down filter
		$translated_books = array_combine(sb_get_default('eng_bible_books'), sb_get_default('bible_books'));
		$preachers = $wpdb->get_results("SELECT p.*, count(p.id) AS count FROM {$wpdb->prefix}sb_preachers AS p JOIN {$wpdb->prefix}sb_sermons AS s ON p.id = s.preacher_id GROUP BY p.id ORDER BY count DESC, MAX(s.datetime) DESC, p.name ASC");
		$series = $wpdb->get_results("SELECT ss.*, count(ss.id) AS count FROM {$wpdb->prefix}sb_series AS ss JOIN {$wpdb->prefix}sb_sermons AS sermons ON ss.id = sermons.series_id GROUP BY ss.id ORDER BY MAX(sermons.datetime) DESC, ss.name ASC");
		$services = $wpdb->get_results("SELECT s.*, count(s.id) AS count FROM {$wpdb->prefix}sb_services AS s JOIN {$wpdb->prefix}sb_sermons AS sermons ON s.id = sermons.service_id GROUP BY s.id ORDER BY count DESC, s.name ASC");
		$book_count = $wpdb->get_results("SELECT bs.book_name AS name, count(distinct bs.sermon_id) AS count FROM {$wpdb->prefix}sb_books_sermons AS bs JOIN {$wpdb->prefix}sb_books AS b ON bs.book_name = b.name GROUP BY b.id ORDER BY b.id");
		$sb = array(
			__('Title', 'sermon-browser') => 'm.title',
			__('Date', 'sermon-browser') => 'm.datetime',
		);
		$di = array(
			__('Ascending', 'sermon-browser') => 'asc',
			__('Descending', 'sermon-browser') => 'desc',
		);
		$csb = isset($_REQUEST['sortby']) ? sanitize_text_field(wp_unslash($_REQUEST['sortby'])) : 'm.datetime';
		$cd = isset($_REQUEST['dir']) ? strtolower(sanitize_text_field(wp_unslash($_REQUEST['dir']))) : '';
		if ($cd != 'asc' && $cd != 'desc')
			$cd = 'desc';
		?>
		<span class="inline_controls"><a href="#" id="show_hide_filter"></a></span>
		<div id="mainfilter">
			<form method="post" id="sermon-filter" action="<?php echo esc_url(sb_display_url()); ?>">
				<div style="clear:both">
					<table class="sermonbrowser">
						<tr>
							<td class="fieldname"><?php esc_html_e('Preacher', 'sermon-browser') ?></td>
							<td class="field"><select name="preacher" id="preacher">
									<option value="0" <?php echo (isset($_REQUEST['preacher']) && (int) $_REQUEST['preacher'] != 0) ? '' : 'selected="selected"' ?>><?php esc_html_e('[All]', 'sermon-browser') ?></option>
									<?php foreach ($preachers as $preacher): ?>
									<option value="<?php echo (int) $preacher->id ?>" <?php echo isset($_REQUEST['preacher']) && $_REQUEST['preacher'] == $preacher->id ? 'selected="selected"' : '' ?>><?php echo esc_html(stripslashes($preacher->name)).' ('.(int) $preacher->count.')' ?></option>
									<?php endforeach ?>
								</select>
							</td>
							<td class="fieldname rightcolumn"><?php esc_html_e('Services', 'sermon-browser') ?></td>
							<td class="field"><select name="service" id="service">
									<option value="0" <?php echo isset($_REQUEST['service']) && (int) $_REQUEST['service'] != 0 ? '' : 'selected="selected"' ?>><?php esc_html_e('[All]', 'sermon-browser') ?></option>
									<?php foreach ($services as $service): ?>
									<option value="<?php echo (int) $service->id ?>" <?php echo isset($_REQUEST['service']) && $_REQUEST['service'] == $service->id ? 'selected="selected"' : '' ?>><?php echo esc_html(stripslashes($service->name)).' ('.(int) $service->count.')' ?></option>
									<?php endforeach ?>
								</select>
							</td>
						</tr>
						<tr>
							<td class="fieldname"><?php esc_html_e('Book', 'sermon-browser') ?></td>
							<td class="field"><select name="book">
									<option value=""><?php esc_html_e('[All]', 'sermon-browser') ?></option>
									<?php foreach ($book_count as $book): ?>
									<option value="<?php echo esc_attr($book->name) ?>" <?php echo isset($_REQUEST['book']) && $_REQUEST['book'] == $book->name ? 'selected=selected' : '' ?>><?php echo esc_html(isset($translated_books[stripslashes($book->name)]) ? $translated_books[stripslashes($book->name)] : stripslashes($book->name)). ' ('.(int) $book->count.')' ?></option>
									<?php endforeach ?>
								</select>
							</td>
							<td class="fieldname rightcolumn"><?php esc_html_e('Series', 'sermon-browser') ?></td>
							<td class="field"><select name="series" id="series">
									<option value="0" <?php echo (isset($_REQUEST['series']) && (int) $_REQUEST['series'] != 0) ? '' : 'selected="selected"' ?>><?php esc_html_e('[All]', 'sermon-browser') ?></option>
									<?php foreach ($series as $item): ?>
									<option value="<?php echo (int) $item->id ?>" <?php echo isset($_REQUEST['series']) && $_REQUEST['series'] == $item->id ? 'selected="selected"' : '' ?>><?php echo esc_html(stripslashes($item->name)).' ('.(int) $item->count.')' ?></option>
									<?php endforeach ?>
								</select>
							</td>
						</tr>
						<tr>
							<td class="fieldname"><?php esc_html_e('Start date', 'sermon-browser') ?></td>
							<td class="field"><input type="text" name="date" id="date" value="<?php echo (isset($_REQUEST['date']) ? esc_attr(sanitize_text_field(wp_unslash($_REQUEST['date']))) : '') ?>" /></td>
							<td class="fieldname rightcolumn"><?php esc_html_e('End date', 'sermon-browser') ?></td>
							<td class="field"><input type="text" name="enddate" id="enddate" value="<?php echo (isset($_REQUEST['enddate']) ? esc_attr(sanitize_text_field(wp_unslash($_REQUEST['enddate']))) : '') ?>" /></td>
						</tr>
						<tr>
							<td class="fieldname"><?php esc_html_e('Keywords', 'sermon-browser') ?></td>
							<td class="field" colspan="3"><input style="width: 98.5%" type="text" id="title" name="title" value="<?php echo (isset($_REQUEST['title']) ? esc_attr(sanitize_text_field(wp_unslash($_REQUEST['title']))) : '') ?>" /></td>
						</tr>
						<tr>
							<td class="fieldname"><?php esc_html_e('Sort by', 'sermon-browser') ?></td>
							<td class="field"><select name="sortby" id="sortby">
									<?php foreach ($sb as $k => $v): ?>
									<option value="<?php echo esc_attr($v) ?>" <?php echo $csb == $v ? 'selected="selected"' : '' ?>><?php echo esc_html($k) ?></option>
									<?php endforeach ?>
								</select>
							</td>
							<td class="fieldname rightcolumn"><?php esc_html_e('Direction', 'sermon-browser') ?></td>
							<td class="field"><select name="dir" id="dir">
									<?php foreach ($di as $k => $v): ?>
									<option value="<?php echo esc_attr($v) ?>" <?php echo $cd == $v ? 'selected="selected"' : '' ?>><?php echo esc_html($k) ?></option>
									<?php endforeach ?>
								</select>
							</td>
						</tr>
						<tr>
							<td colspan="3">&nbsp;</td>
							<td class="field"><input type="submit" class="filter" value="<?php esc_attr_e('Filter &raquo;', 'sermon-browser') ?>">			</td>
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
				<?php echo $js_hide; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JavaScript above, with its text escaped by esc_js() ?>
			});
			<?php } ?>
		</script>
	<?php
	}
}
