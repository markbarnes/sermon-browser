<?php
/**
 * The attachment repository that reads attachments stored as posts.
 *
 * @package SermonBrowser
 */

use SermonBrowser\Attachment;

/**
 * Runs the attachment repository contract against the post types, and tests what only they have:
 * media that isn't the plugin's, and meta read through WordPress.
 */
class Posts_Attachment_Repository_Test extends Attachment_Repository_Contract {

	protected function repository() {
		return new SermonBrowser\Storage\Posts\Attachment_Repository();
	}

	protected function sermons() {
		return new SermonBrowser\Storage\Posts\Sermon_Repository();
	}

	protected function writer() {
		return new Posts_Fixture_Writer();
	}

	public function test_find_ignores_media_in_the_upload_folder_that_isnt_the_plugins() {
		self::factory()->attachment->create_object( 'sermons/uploaded-elsewhere.mp3', 0, array( 'post_mime_type' => 'audio/mpeg' ) );

		$this->assertNull( $this->repository()->find( Attachment::FILE, 'uploaded-elsewhere.mp3' ) );
	}

	public function test_record_download_is_seen_through_wordpress() {
		$file = $this->repository()->find( Attachment::FILE, '2026-03-01-faith-and-works.mp3' );
		$this->assertSame( '5', get_post_meta( $file->id, 'sb_downloads', true ) );

		$this->repository()->record_download( $file );

		$this->assertSame( '6', get_post_meta( $file->id, 'sb_downloads', true ) );
	}
}
