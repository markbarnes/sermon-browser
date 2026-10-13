<?php
/**
 * The filters above the sermon list ([filters_form]).
 *
 * @package SermonBrowser
 */

namespace SermonBrowser;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- the plugin's own tables, which WordPress has no API or cache for
// phpcs:disable WordPress.Security.NonceVerification.Recommended -- the sermon list and its filters are read-only GET requests

/**
 * The filters: links to narrow the list by preacher, book, series, service and date ("oneclick"),
 * or a form with drop-down lists ("dropdown").
 */
class Filters {

	/**
	 * Ids of the filter lines with a "more" link.
	 *
	 * @var string[]
	 */
	private $more_applied = array();

	/**
	 * The request parameters the one-click filter can set.
	 *
	 * @var string[]
	 */
	private $filter_options = array();

	/**
	 * Prints the filters.
	 *
	 * @param array $filter The shortcode's attributes: filter (the type), filterhide, and the current filters.
	 */
	public static function display( $filter ) {
		( new self() )->print_filters( $filter );
	}

	/**
	 * Prints the filters.
	 *
	 * @param array $filter The shortcode's attributes.
	 */
	private function print_filters( $filter ) {
		global $wpdb;
		$hide_filter = false;
		if ( $filter['filterhide'] == 'hide' ) {
			$hide_filter = true;
			$js_hide     = "
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
			$js_hide     = str_replace( 'SHOW', esc_js( __( 'Show filter', 'sermon-browser' ) ), $js_hide );
			$js_hide     = str_replace( 'HIDE', esc_js( __( 'Hide filter', 'sermon-browser' ) ), $js_hide );
		}
		if ( $filter['filter'] == 'oneclick' ) {
			// One click filter
			$hide_custom_podcast  = true;
			$this->filter_options = array( 'preacher', 'book', 'service', 'series', 'date', 'enddate', 'title' );
			$output               = '';
			foreach ( $this->filter_options as $filter_option ) {
				if ( isset( $_REQUEST[ $filter_option ] ) ) {
					if ( $filter_option != 'enddate' ) {
						if ( $output != '' ) {
							$output .= "\r, ";
						}
						if ( $filter_option == 'date' ) {
							$date    = isset( $_REQUEST['date'] ) && is_string( $_REQUEST['date'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['date'] ) ) : '';
							$enddate = isset( $_REQUEST['enddate'] ) && is_string( $_REQUEST['enddate'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['enddate'] ) ) : '';
							$output .= '<strong>' . esc_html__( 'Date', 'sermon-browser' ) . '</strong>:&nbsp;';
							if ( substr( $date, 0, 4 ) == substr( $enddate, 0, 4 ) ) {
								$output .= (int) substr( $date, 0, 4 ) . '&nbsp;(<a href="' . $this->url_minus_parameter( 'date', 'enddate' ) . '">x</a>)';
							}
							if ( substr( $date, 5, 2 ) == substr( $enddate, 5, 2 ) ) {
								$output .= ', ' . esc_html( date_i18n( 'F', strtotime( $date ) ) ) . ' (<a href="' . sb_build_url(
									array(
										'date'    => (int) substr( $date, 0, 4 ) . '-01-01',
										'enddate' => (int) substr( $date, 0, 4 ) . '-12-31',
									),
									false
								) . '">x</a>)';
							}
						} else {
							$output .= '<strong>' . esc_html( self::heading( $filter_option ) ) . '</strong>:&nbsp;*' . $filter_option . '*';
							$output .= '&nbsp;(<a href="' . $this->url_minus_parameter( $filter_option ) . '">x</a>)';
						}
					}
					$hide_custom_podcast = false;
				}
			}
			$hide_empty = sb_get_option( 'hide_no_attachments' );
			$sermons    = sb_get_sermons( $filter, array(), 1, 99999, $hide_empty );
			$ids        = array();
			foreach ( $sermons as $sermon ) {
				$ids[] = $sermon->id;
			}
			// Sermon ids (integers), so the queries below need no placeholders
			$ids = '(' . ( count( $ids ) ? implode( ', ', array_map( 'intval', $ids ) ) : '0' ) . ')';

			$preachers  = $wpdb->get_results( "SELECT p.*, count(p.id) AS count FROM {$wpdb->prefix}sb_preachers AS p JOIN {$wpdb->prefix}sb_sermons AS sermons ON p.id = sermons.preacher_id WHERE sermons.id IN {$ids} GROUP BY p.id ORDER BY count DESC, MAX(sermons.datetime) DESC, p.name ASC" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $ids holds integers
			$series     = $wpdb->get_results( "SELECT ss.*, count(ss.id) AS count FROM {$wpdb->prefix}sb_series AS ss JOIN {$wpdb->prefix}sb_sermons AS sermons ON ss.id = sermons.series_id  WHERE sermons.id IN {$ids} GROUP BY ss.id ORDER BY MAX(sermons.datetime) DESC, ss.name ASC" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $ids holds integers
			$services   = $wpdb->get_results( "SELECT s.*, count(s.id) AS count FROM {$wpdb->prefix}sb_services AS s JOIN {$wpdb->prefix}sb_sermons AS sermons ON s.id = sermons.service_id  WHERE sermons.id IN {$ids} GROUP BY s.id ORDER BY count DESC, s.name ASC" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $ids holds integers
			$book_count = $wpdb->get_results( "SELECT bs.book_name AS name, count(distinct bs.sermon_id) AS count FROM {$wpdb->prefix}sb_books_sermons AS bs JOIN {$wpdb->prefix}sb_books as b ON bs.book_name=b.name AND bs.sermon_id IN {$ids} GROUP BY b.id ORDER BY b.id" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $ids holds integers
			$dates      = $wpdb->get_results( "SELECT YEAR(datetime) as year, MONTH (datetime) as month, DAY(datetime) as day FROM {$wpdb->prefix}sb_sermons WHERE id IN {$ids} ORDER BY datetime ASC" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $ids holds integers

			$this->more_applied = array();
			$output             = str_replace( '*preacher*', isset( $preachers[0]->name ) ? esc_html( stripslashes( $preachers[0]->name ) ) : '', $output );
			$book               = isset( $_REQUEST['book'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['book'] ) ) : '';
			$translated_books   = array_combine( sb_get_default( 'eng_bible_books' ), sb_get_default( 'bible_books' ) );
			$output             = str_replace( '*book*', esc_html( isset( $translated_books[ $book ] ) ? $translated_books[ $book ] : $book ), $output );
			$output             = str_replace( '*service*', isset( $services[0]->name ) ? esc_html( stripslashes( $services[0]->name ) ) : '', $output );
			$output             = str_replace( '*series*', isset( $series[0]->name ) ? esc_html( stripslashes( $series[0]->name ) ) : '', $output );
			$output             = str_replace( '*title*', isset( $_REQUEST['title'] ) ? esc_html( sanitize_text_field( wp_unslash( $_REQUEST['title'] ) ) ) : '', $output );

			echo '<span class="inline_controls"><a href="#" id="show_hide_filter"></a></span>';
			if ( $output != '' ) {
				echo '<div class="filtered">' . esc_html__( 'Active filter', 'sermon-browser' ) . ': ' . wp_kses_post( $output ) . "</div>\r";
			}
			echo '<div id="mainfilter">';
			if ( count( $preachers ) > 1 ) {
				$this->print_line( 'preacher', $preachers, 'id', 'name', 7 );
			}
			if ( count( $book_count ) > 1 ) {
				$this->print_line( 'book', $book_count, 'name', 'name', 10 );
			}
			if ( count( $series ) > 1 ) {
				$this->print_line( 'series', $series, 'id', 'name', 10 );
			}
			if ( count( $services ) > 1 ) {
				$this->print_line( 'service', $services, 'id', 'name', 10 );
			}
			$this->print_date_line( $dates );
			echo "</div>\r";
			if ( count( $this->more_applied ) > 0 || $output != '' || $hide_custom_podcast === true || $hide_filter === true ) {
				echo "<script type=\"text/javascript\">\r";
				echo "\tjQuery(document).ready(function() {\r";
				if ( $hide_filter === true ) {
					echo $js_hide . "\r"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JavaScript above, with its text escaped by esc_js()
				}
				if ( $hide_custom_podcast === true ) {
					echo "\t\tjQuery('.podcastcustom').hide();\r";
				}
				if ( count( $this->more_applied ) > 0 ) {
					foreach ( $this->more_applied as $element_id ) {
						$element_id = esc_js( $element_id );
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
		} elseif ( $filter['filter'] == 'dropdown' ) {
			// Drop-down filter
			$translated_books = array_combine( sb_get_default( 'eng_bible_books' ), sb_get_default( 'bible_books' ) );
			$preachers        = $wpdb->get_results( "SELECT p.*, count(p.id) AS count FROM {$wpdb->prefix}sb_preachers AS p JOIN {$wpdb->prefix}sb_sermons AS s ON p.id = s.preacher_id GROUP BY p.id ORDER BY count DESC, MAX(s.datetime) DESC, p.name ASC" );
			$series           = $wpdb->get_results( "SELECT ss.*, count(ss.id) AS count FROM {$wpdb->prefix}sb_series AS ss JOIN {$wpdb->prefix}sb_sermons AS sermons ON ss.id = sermons.series_id GROUP BY ss.id ORDER BY MAX(sermons.datetime) DESC, ss.name ASC" );
			$services         = $wpdb->get_results( "SELECT s.*, count(s.id) AS count FROM {$wpdb->prefix}sb_services AS s JOIN {$wpdb->prefix}sb_sermons AS sermons ON s.id = sermons.service_id GROUP BY s.id ORDER BY count DESC, s.name ASC" );
			$book_count       = $wpdb->get_results( "SELECT bs.book_name AS name, count(distinct bs.sermon_id) AS count FROM {$wpdb->prefix}sb_books_sermons AS bs JOIN {$wpdb->prefix}sb_books AS b ON bs.book_name = b.name GROUP BY b.id ORDER BY b.id" );
			$sb               = array(
				__( 'Title', 'sermon-browser' )    => 'm.title',
				__( 'Preacher', 'sermon-browser' ) => 'preacher',
				__( 'Date', 'sermon-browser' )     => 'm.datetime',
				__( 'Passage', 'sermon-browser' )  => 'passage',
			);
			$di               = array(
				__( 'Ascending', 'sermon-browser' )  => 'asc',
				__( 'Descending', 'sermon-browser' ) => 'desc',
			);
			$csb              = isset( $_REQUEST['sortby'] ) ? sb_normalise_sortby( sanitize_text_field( wp_unslash( $_REQUEST['sortby'] ) ) ) : 'm.datetime';
			$cd               = isset( $_REQUEST['dir'] ) ? strtolower( sanitize_text_field( wp_unslash( $_REQUEST['dir'] ) ) ) : '';
			if ( $cd != 'asc' && $cd != 'desc' ) {
				$cd = 'desc';
			}
			?>
		<span class="inline_controls"><a href="#" id="show_hide_filter"></a></span>
		<div id="mainfilter">
			<form method="post" id="sermon-filter" action="<?php echo esc_url( sb_display_url() ); ?>">
				<div style="clear:both">
					<table class="sermonbrowser">
						<tr>
							<td class="fieldname"><?php esc_html_e( 'Preacher', 'sermon-browser' ); ?></td>
							<td class="field"><select name="preacher" id="preacher">
									<option value="0" <?php echo ( isset( $_REQUEST['preacher'] ) && (int) $_REQUEST['preacher'] != 0 ) ? '' : 'selected="selected"'; ?>><?php esc_html_e( '[All]', 'sermon-browser' ); ?></option>
									<?php foreach ( $preachers as $preacher ) : ?>
									<option value="<?php echo (int) $preacher->id; ?>" <?php echo isset( $_REQUEST['preacher'] ) && $_REQUEST['preacher'] == $preacher->id ? 'selected="selected"' : ''; ?>><?php echo esc_html( stripslashes( $preacher->name ) ) . ' (' . (int) $preacher->count . ')'; ?></option>
									<?php endforeach ?>
								</select>
							</td>
							<td class="fieldname rightcolumn"><?php esc_html_e( 'Services', 'sermon-browser' ); ?></td>
							<td class="field"><select name="service" id="service">
									<option value="0" <?php echo isset( $_REQUEST['service'] ) && (int) $_REQUEST['service'] != 0 ? '' : 'selected="selected"'; ?>><?php esc_html_e( '[All]', 'sermon-browser' ); ?></option>
									<?php foreach ( $services as $service ) : ?>
									<option value="<?php echo (int) $service->id; ?>" <?php echo isset( $_REQUEST['service'] ) && $_REQUEST['service'] == $service->id ? 'selected="selected"' : ''; ?>><?php echo esc_html( stripslashes( $service->name ) ) . ' (' . (int) $service->count . ')'; ?></option>
									<?php endforeach ?>
								</select>
							</td>
						</tr>
						<tr>
							<td class="fieldname"><?php esc_html_e( 'Book', 'sermon-browser' ); ?></td>
							<td class="field"><select name="book">
									<option value=""><?php esc_html_e( '[All]', 'sermon-browser' ); ?></option>
									<?php foreach ( $book_count as $book ) : ?>
									<option value="<?php echo esc_attr( $book->name ); ?>" <?php echo isset( $_REQUEST['book'] ) && $_REQUEST['book'] == $book->name ? 'selected=selected' : ''; ?>><?php echo esc_html( isset( $translated_books[ stripslashes( $book->name ) ] ) ? $translated_books[ stripslashes( $book->name ) ] : stripslashes( $book->name ) ) . ' (' . (int) $book->count . ')'; ?></option>
									<?php endforeach ?>
								</select>
							</td>
							<td class="fieldname rightcolumn"><?php esc_html_e( 'Series', 'sermon-browser' ); ?></td>
							<td class="field"><select name="series" id="series">
									<option value="0" <?php echo ( isset( $_REQUEST['series'] ) && (int) $_REQUEST['series'] != 0 ) ? '' : 'selected="selected"'; ?>><?php esc_html_e( '[All]', 'sermon-browser' ); ?></option>
									<?php foreach ( $series as $item ) : ?>
									<option value="<?php echo (int) $item->id; ?>" <?php echo isset( $_REQUEST['series'] ) && $_REQUEST['series'] == $item->id ? 'selected="selected"' : ''; ?>><?php echo esc_html( stripslashes( $item->name ) ) . ' (' . (int) $item->count . ')'; ?></option>
									<?php endforeach ?>
								</select>
							</td>
						</tr>
						<tr>
							<td class="fieldname"><?php esc_html_e( 'Start date', 'sermon-browser' ); ?></td>
							<td class="field"><input type="text" name="date" id="date" value="<?php echo ( isset( $_REQUEST['date'] ) ? esc_attr( sanitize_text_field( wp_unslash( $_REQUEST['date'] ) ) ) : '' ); ?>" /></td>
							<td class="fieldname rightcolumn"><?php esc_html_e( 'End date', 'sermon-browser' ); ?></td>
							<td class="field"><input type="text" name="enddate" id="enddate" value="<?php echo ( isset( $_REQUEST['enddate'] ) ? esc_attr( sanitize_text_field( wp_unslash( $_REQUEST['enddate'] ) ) ) : '' ); ?>" /></td>
						</tr>
						<tr>
							<td class="fieldname"><?php esc_html_e( 'Keywords', 'sermon-browser' ); ?></td>
							<td class="field" colspan="3"><input style="width: 98.5%" type="text" id="title" name="title" value="<?php echo ( isset( $_REQUEST['title'] ) ? esc_attr( sanitize_text_field( wp_unslash( $_REQUEST['title'] ) ) ) : '' ); ?>" /></td>
						</tr>
						<tr>
							<td class="fieldname"><?php esc_html_e( 'Sort by', 'sermon-browser' ); ?></td>
							<td class="field"><select name="sortby" id="sortby">
									<?php foreach ( $sb as $k => $v ) : ?>
									<option value="<?php echo esc_attr( $v ); ?>" <?php echo $csb == $v ? 'selected="selected"' : ''; ?>><?php echo esc_html( $k ); ?></option>
									<?php endforeach ?>
								</select>
							</td>
							<td class="fieldname rightcolumn"><?php esc_html_e( 'Direction', 'sermon-browser' ); ?></td>
							<td class="field"><select name="dir" id="dir">
									<?php foreach ( $di as $k => $v ) : ?>
									<option value="<?php echo esc_attr( $v ); ?>" <?php echo $cd == $v ? 'selected="selected"' : ''; ?>><?php echo esc_html( $k ); ?></option>
									<?php endforeach ?>
								</select>
							</td>
						</tr>
						<tr>
							<td colspan="3">&nbsp;</td>
							<td class="field"><input type="submit" class="filter" value="<?php esc_attr_e( 'Filter &raquo;', 'sermon-browser' ); ?>">			</td>
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
			<?php if ( $hide_filter === true ) { ?>
			jQuery(document).ready(function() {
				<?php echo $js_hide; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JavaScript above, with its text escaped by esc_js() ?>
			});
			<?php } ?>
		</script>
	<?php // The tab before this tag is printed, as it always was.
		}
	}

	/**
	 * Returns the translated heading for a filter.
	 *
	 * @param string $id Request parameter.
	 * @return string
	 */
	private static function heading( $id ) {
		$headings = array(
			'preacher' => __( 'Preacher', 'sermon-browser' ),
			'book'     => __( 'Book', 'sermon-browser' ),
			'series'   => __( 'Series', 'sermon-browser' ),
			'service'  => __( 'Service', 'sermon-browser' ),
			'title'    => __( 'Title', 'sermon-browser' ),
		);
		return isset( $headings[ $id ] ) ? $headings[ $id ] : ucwords( $id );
	}

	/**
	 * Prints the one-click filter's line for a parameter, hiding all but the first $max_num - 1 choices behind a "more" link.
	 *
	 * @param string   $id      Request parameter.
	 * @param object[] $results The choices, with the number of sermons in count.
	 * @param string   $filter  The choice's property that holds the parameter's value.
	 * @param string   $display The choice's property that holds its name.
	 * @param int      $max_num Where the "more" link goes.
	 */
	private function print_line( $id, $results, $filter, $display, $max_num = 7 ) {
		$translate_book = false;
		if ( $id == 'book' ) {
			$translate_book   = true;
			$translated_books = array_combine( sb_get_default( 'eng_bible_books' ), sb_get_default( 'bible_books' ) );
		}
		echo '<div id = "' . esc_attr( $id ) . "\" class=\"filter\">\r<span class=\"filter-heading\">" . esc_html( self::heading( $id ) ) . ":</span> \r";
		$i    = 1;
		$more = false;
		foreach ( $results as $result ) {
			if ( $i == $max_num ) {
				echo '<span id="' . esc_attr( $id ) . '-more">';
				$more                 = true;
				$this->more_applied[] = $id;
			}
			if ( $i != 1 ) {
				echo ", \r";
			}
			if ( $translate_book ) {
				echo '<a href="' . esc_url( sb_build_url( array( $id => $result->$filter ), false ) ) . '">' . esc_html( isset( $translated_books[ stripslashes( $result->$display ) ] ) ? $translated_books[ stripslashes( $result->$display ) ] : stripslashes( $result->$display ) ) . '</a>&nbsp;(' . (int) $result->count . ')';
			} else {
				echo '<a href="' . esc_url( sb_build_url( array( $id => $result->$filter ), false ) ) . '">' . esc_html( stripslashes( $result->$display ) ) . '</a>&nbsp;(' . (int) $result->count . ')';
			}
			++$i;
		}
		echo '.';
		if ( $more ) {
			echo "</span>\r<span id=\"" . esc_attr( $id ) . '-more-link" style="display:none">&hellip; (<a  id="' . esc_attr( $id ) . '-toggle" href="#"><strong>' . (int) ( $i - $max_num ) . ' ' . esc_html__( 'more', 'sermon-browser' ) . '</strong></a>)</span>';
		}
		echo '</div>';
	}

	/**
	 * Prints the one-click filter's line for dates: years, or months if all the sermons are from one year.
	 *
	 * @param object[] $dates Each sermon's year, month and day, in date order.
	 */
	private function print_date_line( $dates ) {
		if ( empty( $dates ) ) {
			return;
		}
		$date_output = "<div id = \"dates\" class=\"filter\">\r<span class=\"filter-heading\">" . esc_html__( 'Date', 'sermon-browser' ) . ":</span> \r";
		$first       = $dates[0];
		$last        = end( $dates );
		$count       = 0;
		if ( $first->year == $last->year ) {
			if ( $first->month == $last->month ) {
				$date_output = '';
			} else {
				$previous_month = -1;
				foreach ( $dates as $date ) {
					if ( $date->month != $previous_month ) {
						if ( $count != 0 ) {
							$date_output .= '(' . $count . '), ';
						}
						$date_output   .= '<a href="' . sb_build_url(
							array(
								'date'    => $date->year . '-' . $date->month . '-01',
								'enddate' => $date->year . '-' . $date->month . '-31',
							),
							false
						) . '">' . esc_html( date_i18n( 'F', strtotime( "{$date->year}-{$date->month}-{$date->day}" ) ) ) . '</a> ';
						$previous_month = $date->month;
						$count          = 1;
					} else {
						++$count;
					}
				}
				$date_output .= '(' . $count . '), ';
			}
		} else {
			$previous_year = 0;
			foreach ( $dates as $date ) {
				if ( $date->year !== $previous_year ) {
					if ( $count !== 0 ) {
						$date_output .= '(' . $count . '), ';
					}
					$date_output  .= '<a href="' . sb_build_url(
						array(
							'date'    => $date->year . '-01-01',
							'enddate' => $date->year . '-12-31',
						),
						false
					) . '">' . (int) $date->year . '</a> ';
					$previous_year = $date->year;
					$count         = 1;
				} else {
					++$count;
				}
			}
			$date_output .= '(' . $count . '), ';
		}
		if ( $date_output != '' ) {
			echo rtrim( $date_output, ', ' ) . "</div>\r"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built above from escaped URLs, names and integers
		}
	}

	/**
	 * Returns the URL of the sermon list with the current filters except one or two.
	 *
	 * @param string $param1 Request parameter to leave out.
	 * @param string $param2 Another request parameter to leave out.
	 * @return string Escaped URL.
	 */
	private function url_minus_parameter( $param1, $param2 = '' ) {
		$existing_params = array_merge( (array) $_GET, (array) $_POST ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- the filter form is a read-only search; values are URL-encoded below
		$returned_query  = array();
		foreach ( array_keys( $existing_params ) as $query ) {
			if ( in_array( $query, $this->filter_options ) && $query != $param1 && $query != $param2 ) {
				if ( is_scalar( $existing_params[ $query ] ) ) {
					$returned_query[] = rawurlencode( $query ) . '=' . rawurlencode( wp_unslash( $existing_params[ $query ] ) );
				}
			}
		}
		if ( count( $returned_query ) > 0 ) {
			return esc_url( sb_display_url() . sb_query_char() . implode( '&', $returned_query ) );
		} else {
			return sb_display_url();
		}
	}
}
