<?php
/**
 * The sermon repository that reads the post types and taxonomies.
 *
 * @package SermonBrowser
 */

use SermonBrowser\Post_Types;
use SermonBrowser\Sermon_Query;

/**
 * Runs the sermon repository contract against the post types, and tests what only they have:
 * unpublished sermons, and other media attached to a sermon.
 */
class Posts_Sermon_Repository_Test extends Sermon_Repository_Contract {

	protected function repository() {
		return new SermonBrowser\Storage\Posts\Sermon_Repository();
	}

	protected function writer() {
		return new Posts_Fixture_Writer();
	}

	/**
	 * Returns the post id of a fixture sermon (the writer stores them in order).
	 *
	 * @param string $title The sermon's title.
	 * @return int
	 */
	private function post_id( $title ) {
		return get_posts(
			array(
				'post_type' => Post_Types::SERMON,
				'title'     => $title,
				'fields'    => 'ids',
			)
		)[0];
	}

	/**
	 * Runs a query and returns the titles of the sermons it finds.
	 *
	 * @param array $properties Sermon_Query properties.
	 * @return string[]
	 */
	private function titles( array $properties = array() ) {
		$query = new Sermon_Query();
		foreach ( $properties as $name => $value ) {
			$query->$name = $value;
		}
		return wp_list_pluck( $this->repository()->find( $query )->sermons, 'title' );
	}

	public function test_leaves_out_sermons_that_arent_published() {
		$id = $this->post_id( 'Romans 5: peace with God' );
		wp_update_post(
			array(
				'ID'          => $id,
				'post_status' => 'draft',
			)
		);

		$this->assertNull( $this->repository()->get( $id ) );
		$this->assertNotContains( 'Romans 5: peace with God', $this->titles() );
		$this->assertSame( 5, $this->repository()->find( new Sermon_Query() )->total );
		$this->assertSame( array( 'Paul\'s letter: Faith & Works' ), $this->titles( array( 'with_attachments' => true ) ) );
		$this->assertSame( 1, $this->repository()->facets( new Sermon_Query() )->series[0]->count );
		$this->assertSame( array( 'faith', 'grace', 'new birth', 'psalms' ), wp_list_pluck( $this->repository()->tag_counts(), 'name' ) );
		$this->assertSame( 1, $this->repository()->tag_counts()[0]->count );
		$this->assertSame( 'A sermon on new birth', $this->repository()->next( $this->repository()->get( $this->post_id( "Paul's letter: Faith & Works" ) ) )->title );
	}

	public function test_doesnt_count_other_media_attached_to_a_sermon_as_its_files() {
		$id    = $this->post_id( 'Christmas morning: Unto us a child is born' );
		$image = self::factory()->attachment->create_object(
			'nativity.jpg',
			$id,
			array( 'post_mime_type' => 'image/jpeg' )
		);
		set_post_thumbnail( $id, $image );

		$this->assertSame( array(), $this->repository()->get( $id )->attachments );
		$this->assertNotContains( 'Christmas morning: Unto us a child is born', $this->titles( array( 'with_attachments' => true ) ) );
		$this->assertNotContains( 'Christmas morning: Unto us a child is born', wp_list_pluck( $this->repository()->most_downloaded( 10 ), 'title' ) );
	}

	public function test_leaves_out_a_file_outside_the_upload_folder() {
		$id = $this->post_id( 'Christmas morning: Unto us a child is born' );
		$elsewhere = self::factory()->attachment->create_object(
			'2025/12/carols.mp3',
			$id,
			array( 'post_mime_type' => 'audio/mpeg' )
		);
		add_post_meta( $elsewhere, 'sb_downloads', 3 );

		$this->assertSame( array(), $this->repository()->get( $id )->attachments );
	}
}
