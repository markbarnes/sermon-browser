<?php
/**
 * WP-CLI commands.
 *
 * @package SermonBrowser
 */

namespace SermonBrowser;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Copies sermons from Sermon Browser's tables to WordPress posts, and checks the copy.
 */
class CLI_Command {

	/**
	 * Copies sermons, preachers, series, services and attachments from the plugin's tables to posts and terms.
	 *
	 * The tables are only read, and the site keeps showing sermons from them. Running it again updates the copy: it adds what is new, changes what has changed and deletes what has gone.
	 *
	 * Files stay where they are; deleting one's copy leaves the file.
	 *
	 * ## OPTIONS
	 *
	 * [--dry-run]
	 * : Say what would be copied, without writing anything.
	 *
	 * [--report=<file>]
	 * : Also write the report to this file.
	 *
	 * ## EXAMPLES
	 *
	 *     wp sermon-browser migrate --dry-run
	 *     wp sermon-browser migrate --user=admin --report=migration.txt
	 *
	 * @param string[] $args       Positional arguments (none).
	 * @param string[] $assoc_args Options.
	 */
	public function migrate( $args, $assoc_args ) {
		$dry_run     = (bool) \WP_CLI\Utils\get_flag_value( $assoc_args, 'dry-run', false );
		$migration   = new Migration();
		$report      = $migration->run( $dry_run );
		$lines       = $this->report( $report );
		$differences = array();
		if ( ! $dry_run ) {
			$differences = $migration->verify();
			$lines[]     = '';
			$lines[]     = $differences ? sprintf( 'Checked against the tables: %d differences.', count( $differences ) ) : 'Checked against the tables: no differences.';
			foreach ( $differences as $difference ) {
				$lines[] = '- ' . $difference;
			}
		}
		$this->output( $lines, $assoc_args );
		if ( $differences ) {
			\WP_CLI::error( 'The copy differs from the tables.' );
		}
		\WP_CLI::success( $dry_run ? 'Dry run: nothing was written.' : 'Copied.' );
	}

	/**
	 * Checks that the posts and terms copied from the plugin's tables match them.
	 *
	 * ## OPTIONS
	 *
	 * [--report=<file>]
	 * : Also write the differences to this file.
	 *
	 * @param string[] $args       Positional arguments (none).
	 * @param string[] $assoc_args Options.
	 */
	public function verify( $args, $assoc_args ) {
		$differences = ( new Migration() )->verify();
		$lines       = array( $differences ? sprintf( '%d differences:', count( $differences ) ) : 'No differences.' );
		foreach ( $differences as $difference ) {
			$lines[] = '- ' . $difference;
		}
		$this->output( $lines, $assoc_args );
		if ( $differences ) {
			\WP_CLI::error( 'The copy differs from the tables.' );
		}
		\WP_CLI::success( 'The copy matches the tables.' );
	}

	/**
	 * Returns a migration report as lines of text.
	 *
	 * @param Migration_Report $report The report.
	 * @return string[]
	 */
	private function report( Migration_Report $report ) {
		$lines = array( $report->dry_run ? 'What copying would do:' : 'Copied:' );
		foreach ( $report->counts as $kind => $counts ) {
			$lines[] = sprintf( '- %s: %d new, %d updated, %d deleted', $kind, $counts[ Migration_Report::CREATED ], $counts[ Migration_Report::UPDATED ], $counts[ Migration_Report::DELETED ] );
		}
		foreach ( array(
			'Problems' => $report->problems,
			'Notes'    => $report->notes,
		) as $heading => $items ) {
			$lines[] = '';
			$lines[] = $items ? sprintf( '%s (%d):', $heading, count( $items ) ) : sprintf( '%s: none.', $heading );
			foreach ( $items as $item ) {
				$lines[] = '- ' . $item;
			}
		}
		return $lines;
	}

	/**
	 * Prints lines, and writes them to the --report file if there is one.
	 *
	 * @param string[] $lines      Lines.
	 * @param string[] $assoc_args Options.
	 */
	private function output( array $lines, $assoc_args ) {
		foreach ( $lines as $line ) {
			\WP_CLI::log( $line );
		}
		if ( ! empty( $assoc_args['report'] ) ) {
			if ( false === file_put_contents( $assoc_args['report'], implode( PHP_EOL, $lines ) . PHP_EOL ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- a file the person running WP-CLI named
				\WP_CLI::warning( 'Couldn\'t write the report to ' . $assoc_args['report'] );
			}
		}
	}
}
