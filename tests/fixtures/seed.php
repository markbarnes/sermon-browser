<?php
// Only inside WordPress (the test API or wp eval-file): the seed starts by dropping the plugin's tables.
defined( 'ABSPATH' ) || exit;
/**
 * Resets the test site to a known state and prints it as JSON.
 *
 * Run on the wp-env test site with:
 *   wp eval-file wp-content/plugins/sermon-browser/tests/fixtures/seed.php
 *
 * Drops and reinstalls Sermon Browser's tables, options and upload folder,
 * then adds:
 * - users for each role (passwords below are test values for this site only);
 * - a published "Sermons" page at /sermons/ with the [sermons] shortcode;
 * - the plugin's widgets in the test widget area, and a "Sermon widgets" page at
 *   /sermon-widgets/ that shows them;
 * - genuine sermons with the awkward content the live site's data has:
 *   apostrophes stored with slashes as the plugin saves them, unescaped
 *   ampersands, typographic punctuation, an HTML entity in a series name,
 *   a sermon with no Bible passage, a service time without a leading zero,
 *   a preacher with an image, Welsh characters;
 * - a "hostile" sermon whose fields hold script payloads, stored directly in
 *   the database as an attacker could have stored them before 0.46.0.
 *
 * Ids are deterministic because the tables are recreated each time.
 */

global $wpdb, $sermonbrowser_options;

$users = array(
	'author'     => array( 'sb_author', 'author-test-pass', 'author' ),
	'editor'     => array( 'sb_editor', 'editor-test-pass', 'editor' ),
	'subscriber' => array( 'sb_subscriber', 'subscriber-test-pass', 'subscriber' ),
);

// Start from a fresh install.
foreach ( array( 'sb_preachers', 'sb_series', 'sb_services', 'sb_sermons', 'sb_stuff', 'sb_books', 'sb_books_sermons', 'sb_sermons_tags', 'sb_tags' ) as $table ) {
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}{$table}" );
}
delete_option( 'sermonbrowser_options' );
foreach ( sb_special_option_names() as $option ) {
	delete_option( "sermonbrowser_{$option}" );
}
$sermonbrowser_options = null;
$upload_dir            = SB_ABSPATH . sb_get_default( 'sermon_path' );
if ( is_dir( $upload_dir ) ) {
	foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $upload_dir, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST ) as $file ) {
		$file->isDir() ? rmdir( $file->getPathname() ) : unlink( $file->getPathname() );
	}
	rmdir( $upload_dir );
}
require_once SB_INCLUDES_DIR . '/sb-install.php';
sb_install();
sb_update_option( 'code_version', SB_CURRENT_VERSION );

// Permalinks, users and the sermons page.
global $wp_rewrite;
$wp_rewrite->set_permalink_structure( '/%postname%/' );
$wp_rewrite->flush_rules( true );
update_option( 'blogname', 'Sermon Browser tests' );
update_option( 'date_format', 'j F Y' );
update_option( 'timezone_string', 'Europe/London' );

$user_ids = array( 'admin' => 1 );
foreach ( $users as $key => $user ) {
	list( $login, $password, $role ) = $user;
	$existing = get_user_by( 'login', $login );
	$user_id  = $existing ? $existing->ID : wp_insert_user( array( 'user_login' => $login, 'user_pass' => $password, 'user_email' => "{$login}@example.org", 'role' => $role ) );
	wp_set_password( $password, $user_id );
	( new WP_User( $user_id ) )->set_role( $role );
	$user_ids[ $key ] = $user_id;
}

foreach ( get_posts( array( 'post_type' => 'page', 'name' => 'sermons', 'post_status' => 'any', 'numberposts' => -1 ) ) as $page ) {
	wp_delete_post( $page->ID, true );
}
// Dated in the past: sb_add_headers() only treats a page as the sermons page if post_date
// (local time) is before the database's NOW(), which a page created this hour may not be.
$page_id = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Sermons', 'post_name' => 'sermons', 'post_content' => '[sermons]', 'post_date' => '2026-01-01 00:00:00' ) );
sb_update_option( 'podcast_url', get_permalink( $page_id ) . '?podcast' );

// The widgets, in the test widget area (tests/mu-plugins/sb-test-widgets.php) and on a page
// of their own. The sermons widget only exists once sermons_widget_options has an instance 2.
$widgets = array( 'sermon-2', 'sermon-browser-tags', 'sermon-browser-popular' );
update_option( 'sidebars_widgets', array( 'wp_inactive_widgets' => array(), 'sb-tests' => $widgets, 'array_version' => 3 ) );
foreach ( get_posts( array( 'post_type' => 'page', 'name' => 'sermon-widgets', 'post_status' => 'any', 'numberposts' => -1 ) ) as $page ) {
	wp_delete_post( $page->ID, true );
}
wp_insert_post(
	array(
		'post_type'    => 'page',
		'post_status'  => 'publish',
		'post_title'   => 'Sermon widgets',
		'post_name'    => 'sermon-widgets',
		'post_date'    => '2026-01-01 00:00:00',
		'post_content' => implode( "\n", array_map( function ( $id ) { return '<!-- wp:legacy-widget {"id":"' . $id . '"} /-->'; }, $widgets ) ),
	)
);

// Lookup tables: replace the install's sample rows with known ones.
foreach ( array( 'sb_preachers', 'sb_series', 'sb_services' ) as $table ) {
	$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}{$table}" );
}
$hostile = '<script>alert("xss")</script>';
$preachers = array(
	1 => array( 'Dylan Jones', 'Pastor of Bethel since 2015.', 'profile.jpg' ),
	2 => array( 'Rhiannon Llŷr', 'Visiting preacher from Aberystwyth.' ),
	3 => array( $hostile, '<img src=x onerror=alert("preacher")>' ),
);
foreach ( $preachers as $id => $preacher ) {
	$wpdb->insert( "{$wpdb->prefix}sb_preachers", array( 'id' => $id, 'name' => $preacher[0], 'description' => $preacher[1], 'image' => isset( $preacher[2] ) ? $preacher[2] : '' ) );
}
foreach ( array( 1 => 'Romans', 2 => 'The Psalms &#8211; a book of mixed emotions', 3 => $hostile ) as $id => $name ) {
	$wpdb->insert( "{$wpdb->prefix}sb_series", array( 'id' => $id, 'name' => $name, 'page_id' => 0 ) );
}
foreach ( array( 1 => array( 'Sunday Morning', '10:30' ), 2 => array( 'Sunday Evening', '18:00' ), 3 => array( $hostile, '19:00' ), 4 => array( 'Christmas morning', '9:30' ) ) as $id => $service ) {
	$wpdb->insert( "{$wpdb->prefix}sb_services", array( 'id' => $id, 'name' => $service[0], 'time' => $service[1] ) );
}

/**
 * Adds a sermon with its passages, tags and attachments.
 */
$add_sermon = function ( $sermon ) use ( $wpdb ) {
	$start = $end = array();
	foreach ( $sermon['passages'] as $passage ) {
		$start[] = array( 'book' => $passage[0], 'chapter' => $passage[1], 'verse' => $passage[2] );
		$end[]   = array( 'book' => $passage[0], 'chapter' => $passage[3], 'verse' => $passage[4] );
	}
	$wpdb->insert(
		"{$wpdb->prefix}sb_sermons",
		array(
			'id'          => $sermon['id'],
			'title'       => $sermon['title'],
			'preacher_id' => $sermon['preacher'],
			'datetime'    => $sermon['datetime'],
			'service_id'  => $sermon['service'],
			'series_id'   => $sermon['series'],
			'start'       => serialize( $start ),
			'end'         => serialize( $end ),
			'description' => $sermon['description'],
			'time'        => '',
			'override'    => 0,
			'page_id'     => 0,
		)
	);
	foreach ( $start as $order => $passage ) {
		$wpdb->insert( "{$wpdb->prefix}sb_books_sermons", array( 'book_name' => $passage['book'], 'chapter' => $passage['chapter'], 'verse' => $passage['verse'], 'order' => $order, 'type' => 'start', 'sermon_id' => $sermon['id'] ) );
		$wpdb->insert( "{$wpdb->prefix}sb_books_sermons", array( 'book_name' => $end[ $order ]['book'], 'chapter' => $end[ $order ]['chapter'], 'verse' => $end[ $order ]['verse'], 'order' => $order, 'type' => 'end', 'sermon_id' => $sermon['id'] ) );
	}
	foreach ( $sermon['tags'] as $tag ) {
		$tag_id = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}sb_tags WHERE name = %s", $tag ) );
		if ( ! $tag_id ) {
			$wpdb->insert( "{$wpdb->prefix}sb_tags", array( 'name' => $tag ) );
			$tag_id = $wpdb->insert_id;
		}
		$wpdb->insert( "{$wpdb->prefix}sb_sermons_tags", array( 'sermon_id' => $sermon['id'], 'tag_id' => $tag_id ) );
	}
	foreach ( $sermon['attachments'] as $attachment ) {
		$wpdb->insert( "{$wpdb->prefix}sb_stuff", array( 'type' => $attachment[0], 'name' => $attachment[1], 'sermon_id' => $sermon['id'], 'count' => 0, 'duration' => '' ) );
	}
};

$add_sermon(
	array(
		'id'          => 1,
		// Saved by the plugin from slashed $_POST data, so the apostrophe is stored escaped.
		'title'       => "Paul\\'s letter: Faith & Works",
		'preacher'    => 1,
		'service'     => 1,
		'series'      => 1,
		'datetime'    => '2026-03-01 10:30:00',
		'description' => '<p>Justified by grace <em>alone</em>, through faith.</p>',
		'passages'    => array( array( 'Romans', 3, 21, 3, 31 ) ),
		'tags'        => array( 'grace', 'faith' ),
		'attachments' => array(
			array( 'file', '2026-03-01-faith-and-works.mp3' ),
			array( 'url', 'https://example.org/sermons/faith-and-works-remote.mp3' ),
			array( 'code', base64_encode( '<iframe width="560" height="315" src="https://www.youtube-nocookie.com/embed/abc123" allowfullscreen></iframe>' ) ),
		),
	)
);
$add_sermon(
	array(
		'id'          => 2,
		'title'       => 'Gŵyl Dewi — the Lord’s my shepherd…',
		'preacher'    => 2,
		'service'     => 2,
		'series'      => 2,
		'datetime'    => '2026-03-01 18:00:00',
		'description' => 'Psalm 23 for St David\'s Day.',
		'passages'    => array( array( 'Psalm', 23, 1, 23, 6 ) ),
		'tags'        => array( 'psalms' ),
		'attachments' => array(),
	)
);
$add_sermon(
	array(
		'id'          => 3,
		'title'       => 'Romans 5: peace with God',
		'preacher'    => 1,
		'service'     => 1,
		'series'      => 1,
		'datetime'    => '2026-03-08 10:30:00',
		'description' => '',
		'passages'    => array( array( 'Romans', 5, 1, 5, 11 ), array( 'Isaiah', 53, 4, 53, 6 ) ),
		'tags'        => array( 'grace' ),
		'attachments' => array( array( 'file', '2026-03-08-peace-with-god.mp3' ) ),
	)
);
$add_sermon(
	array(
		'id'          => 4,
		'title'       => $hostile,
		// Ids 1-3 and 5 are genuine; 4 holds payloads in every field.
		'preacher'    => 3,
		'service'     => 3,
		'series'      => 3,
		'datetime'    => '2026-03-15 19:00:00',
		'description' => '',
		'passages'    => array( array( 'John', 3, 16, 3, 16 ) ),
		'tags'        => array( '<img src=x onerror=alert("tag")>' ),
		'attachments' => array(
			array( 'file', 'x"><img src=x onerror=alert("file")>.mp3' ),
			array( 'url', 'javascript:alert("url")' ),
			array( 'code', base64_encode( '<iframe srcdoc="&lt;script&gt;alert(&quot;srcdoc&quot;)&lt;/script&gt;" src="https://example.org/" onload="alert(\'onload\')"></iframe><script>alert("embed")</script>' ) ),
		),
	)
);

$add_sermon(
	array(
		'id'          => 5,
		'title'       => 'Christmas morning: Unto us a child is born',
		'preacher'    => 1,
		'service'     => 4,
		'series'      => 2,
		'datetime'    => '2025-12-25 09:30:00',
		'description' => '',
		'passages'    => array(),
		'tags'        => array(),
		'attachments' => array(),
	)
);

// Audio files on disk: the two attached files and one not yet linked to a sermon.
$mp3 = "ID3\x03\x00\x00\x00\x00\x00\x00" . str_repeat( "\xFF\xFB\x90\x64" . str_repeat( "\x00", 413 ), 20 );
foreach ( array( '2026-03-01-faith-and-works.mp3', '2026-03-08-peace-with-god.mp3', 'unlinked-sermon.mp3' ) as $file_name ) {
	file_put_contents( $upload_dir . $file_name, $mp3 );
}
$wpdb->insert( "{$wpdb->prefix}sb_stuff", array( 'type' => 'file', 'name' => 'unlinked-sermon.mp3', 'sermon_id' => 0, 'count' => 0, 'duration' => '' ) );
// A 1x1 PNG for the preacher image.
file_put_contents( $upload_dir . 'images/profile.jpg', base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==' ) );

// Start each test file with an empty debug log and HTTP request log.
delete_option( 'sb_test_http_log' );
if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
	file_put_contents( WP_CONTENT_DIR . '/debug.log', '' );
}

$passwords = array( 'admin' => 'password' );
foreach ( $users as $key => $user ) {
	$passwords[ $key ] = $user[1];
}
echo wp_json_encode(
	array(
		'page_url'   => get_permalink( $page_id ),
		'upload_dir' => sb_get_option( 'upload_dir' ),
		'users'      => array_map(
			function ( $key ) use ( $users, $user_ids, $passwords ) {
				return array(
					'login'    => 'admin' === $key ? 'admin' : $users[ $key ][0],
					'password' => $passwords[ $key ],
					'id'       => $user_ids[ $key ],
				);
			},
			array_combine( array_keys( $user_ids ), array_keys( $user_ids ) )
		),
	)
);
