<?php
/**
 * Stores fixtures in the plugin's own tables.
 *
 * @package SermonBrowser
 */

/**
 * Stores fixtures in the plugin's tables the way the plugin's admin pages store them: titles and
 * descriptions with quotes escaped (God\'s), embed code base64-encoded, passages both serialized in
 * the sermon and as rows of sb_books_sermons, and an unknown date as 1970-01-01.
 */
class Tables_Fixture_Writer implements Fixture_Writer {

	/**
	 * {@inheritDoc}
	 *
	 * @param array $fixtures As tests/phpunit/fixtures/sermons.php returns.
	 */
	public function write( array $fixtures ) {
		global $wpdb;
		foreach ( array( 'sb_preachers', 'sb_series', 'sb_services', 'sb_sermons', 'sb_stuff', 'sb_books_sermons', 'sb_sermons_tags', 'sb_tags' ) as $table ) {
			$wpdb->query( "DELETE FROM {$wpdb->prefix}{$table}" );
		}
		foreach ( $fixtures['preachers'] as $id => $preacher ) {
			$wpdb->insert( "{$wpdb->prefix}sb_preachers", array( 'id' => $id, 'name' => $preacher[0], 'description' => $preacher[1], 'image' => $preacher[2] ) );
		}
		foreach ( $fixtures['series'] as $id => $name ) {
			$wpdb->insert( "{$wpdb->prefix}sb_series", array( 'id' => $id, 'name' => $name, 'page_id' => 0 ) );
		}
		foreach ( $fixtures['services'] as $id => $service ) {
			$wpdb->insert( "{$wpdb->prefix}sb_services", array( 'id' => $id, 'name' => $service[0], 'time' => $service[1] ) );
		}
		foreach ( $fixtures['sermons'] as $id => $sermon ) {
			$start = array();
			$end   = array();
			foreach ( $sermon['passages'] as $passage ) {
				$start[] = array( 'book' => $passage[0], 'chapter' => (string) $passage[1], 'verse' => (string) $passage[2] );
				$end[]   = array( 'book' => $passage[3], 'chapter' => (string) $passage[4], 'verse' => (string) $passage[5] );
			}
			$wpdb->insert(
				"{$wpdb->prefix}sb_sermons",
				array(
					'id'          => $id,
					'title'       => addslashes( $sermon['title'] ),
					'preacher_id' => $sermon['preacher'],
					'datetime'    => null === $sermon['date'] ? '1970-01-01 00:00:00' : $sermon['date'],
					'service_id'  => $sermon['service'],
					'series_id'   => $sermon['series'],
					'start'       => serialize( $start ),
					'end'         => serialize( $end ),
					'description' => addslashes( $sermon['description'] ),
					'time'        => $sermon['time'],
					'override'    => '' === $sermon['time'] ? 0 : 1,
					'page_id'     => 0,
				)
			);
			foreach ( $start as $order => $verse ) {
				$wpdb->insert( "{$wpdb->prefix}sb_books_sermons", array( 'book_name' => $verse['book'], 'chapter' => $verse['chapter'], 'verse' => $verse['verse'], 'order' => $order, 'type' => 'start', 'sermon_id' => $id ) );
				$wpdb->insert( "{$wpdb->prefix}sb_books_sermons", array( 'book_name' => $end[ $order ]['book'], 'chapter' => $end[ $order ]['chapter'], 'verse' => $end[ $order ]['verse'], 'order' => $order, 'type' => 'end', 'sermon_id' => $id ) );
			}
			foreach ( $sermon['tags'] as $tag ) {
				$tag_id = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}sb_tags WHERE name = %s", $tag ) );
				if ( ! $tag_id ) {
					$wpdb->insert( "{$wpdb->prefix}sb_tags", array( 'name' => $tag ) );
					$tag_id = $wpdb->insert_id;
				}
				$wpdb->insert( "{$wpdb->prefix}sb_sermons_tags", array( 'sermon_id' => $id, 'tag_id' => $tag_id ) );
			}
			foreach ( $sermon['attachments'] as $attachment ) {
				list( $type, $content, $downloads, $duration ) = $attachment;
				$wpdb->insert(
					"{$wpdb->prefix}sb_stuff",
					array(
						'type'      => $type,
						'name'      => 'code' === $type ? base64_encode( $content ) : $content,
						'sermon_id' => $id,
						'count'     => $downloads,
						'duration'  => $duration,
					)
				);
			}
		}
	}
}
