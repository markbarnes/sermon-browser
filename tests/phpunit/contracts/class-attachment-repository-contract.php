<?php
/**
 * What every attachment repository must do.
 *
 * @package SermonBrowser
 */

use SermonBrowser\Attachment;
use SermonBrowser\Attachment_Repository;
use SermonBrowser\Sermon_Repository;

/**
 * The contract tests for SermonBrowser\Attachment_Repository, run like Sermon_Repository_Contract.
 */
abstract class Attachment_Repository_Contract extends WP_UnitTestCase {

	/**
	 * Returns the repository under test.
	 *
	 * @return Attachment_Repository
	 */
	abstract protected function repository();

	/**
	 * Returns the sermon repository of the same storage, to see attachments through their sermons.
	 *
	 * @return Sermon_Repository
	 */
	abstract protected function sermons();

	/**
	 * Returns the writer that stores fixtures where the repositories read them.
	 *
	 * @return Fixture_Writer
	 */
	abstract protected function writer();

	public function set_up() {
		parent::set_up();
		$this->writer()->write( require dirname( __DIR__ ) . '/fixtures/sermons.php' );
	}

	public function test_find_returns_a_file_or_url_by_what_it_holds() {
		$file = $this->repository()->find( Attachment::FILE, '2026-03-01-faith-and-works.mp3' );
		$url  = $this->repository()->find( Attachment::URL, 'https://example.org/sermons/faith-and-works.mp3' );

		$this->assertInstanceOf( Attachment::class, $file );
		$this->assertSame( array( Attachment::FILE, '2026-03-01-faith-and-works.mp3', 5, '34:12' ), array( $file->type, $file->content, $file->downloads, $file->duration ) );
		$this->assertSame( array( Attachment::URL, 2 ), array( $url->type, $url->downloads ) );
		$this->assertSame( $this->sermons()->get( 1 )->attachments[2]->id, $file->id );
	}

	public function test_find_returns_files_attached_to_no_sermon() {
		$this->assertSame( 'unlinked-sermon.mp3', $this->repository()->find( Attachment::FILE, 'unlinked-sermon.mp3' )->content );
	}

	public function test_find_returns_null_for_anything_else() {
		$this->assertNull( $this->repository()->find( Attachment::URL, '2026-03-01-faith-and-works.mp3' ) );
		$this->assertNull( $this->repository()->find( Attachment::FILE, 'faith-and-works.mp3' ) );
		$this->assertNull( $this->repository()->find( Attachment::FILE, '../wp-config.php' ) );
		$this->assertNull( $this->repository()->find( Attachment::FILE, '' ) );
	}

	public function test_record_download_counts_one_more_for_that_attachment() {
		$this->repository()->record_download( $this->repository()->find( Attachment::FILE, '2026-03-01-faith-and-works.mp3' ) );
		$this->repository()->record_download( $this->repository()->find( Attachment::FILE, '2026-03-08-peace-with-god.mp3' ) );
		$this->repository()->record_download( $this->repository()->find( Attachment::FILE, '2026-03-08-peace-with-god.mp3' ) );

		$this->assertSame( 6, $this->sermons()->get( 1 )->attachments[2]->downloads );
		$this->assertSame( 2, $this->sermons()->get( 1 )->attachments[1]->downloads );
		$this->assertSame( 2, $this->sermons()->get( 3 )->attachments[0]->downloads );
	}

	public function test_set_duration_keeps_an_mp3s_length() {
		$this->repository()->set_duration( $this->repository()->find( Attachment::FILE, '2026-03-08-peace-with-god.mp3' ), '41:07' );

		$this->assertSame( '41:07', $this->repository()->find( Attachment::FILE, '2026-03-08-peace-with-god.mp3' )->duration );
		$this->assertSame( '41:07', $this->sermons()->get( 3 )->attachments[0]->duration );
		$this->assertSame( '34:12', $this->repository()->find( Attachment::FILE, '2026-03-01-faith-and-works.mp3' )->duration );
	}
}
