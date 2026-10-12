/**
 * Uploads: the Files page, URL imports, the Add Sermon page, preacher images and the upload folder.
 *
 * Before 0.46.0 uploads had no nonce and no file type check (CVE-2022-0499), so a forged
 * request, or any Author or Editor, could upload PHP to a web-accessible folder.
 */
import { before, describe, it } from 'node:test';
import assert from 'node:assert/strict';
import { one, php, query, seed, uploadExists, SITE } from './lib/wp.js';
import { Client } from './lib/client.js';

const MP3 = Buffer.concat( [ Buffer.from( 'ID3\x03\x00\x00\x00\x00\x00\x00', 'latin1' ), Buffer.alloc( 20 * 417 ).fill( 0 ) ] );
for ( let frame = 0; frame < 20; frame++ ) {
	MP3.set( [ 0xff, 0xfb, 0x90, 0x64 ], 10 + frame * 417 );
}
const PHP = Buffer.from( '<?php echo "pwned"; ?>' );
const PNG = Buffer.from( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==', 'base64' );

let fixtures;
const clients = {};

before( async () => {
	fixtures = await seed();
	for ( const role of [ 'subscriber', 'author', 'editor', 'admin' ] ) {
		clients[ role ] = await Client.as( fixtures.users[ role ] );
	}
} );

/**
 * Builds a multipart form from fields and files ({name: [content, fileName]}).
 */
function multipart( fields, files = {} ) {
	const form = new FormData();
	for ( const [ name, value ] of Object.entries( fields ) ) {
		for ( const item of [].concat( value ) ) {
			form.append( name, item );
		}
	}
	for ( const [ name, [ content, fileName ] ] of Object.entries( files ) ) {
		form.append( name, new File( [ content ], fileName ) );
	}
	return form;
}

/**
 * Uploads a file on the Files page.
 */
async function uploadOnFilesPage( client, content, fileName, { nonce = true } = {} ) {
	const fields = { save: 'Upload' };
	if ( nonce ) {
		fields.sb_upload_nonce = await client.nonce( 'files.php', 'sb_upload_nonce' );
	}
	return client.request( '/wp-admin/admin.php?page=sermon-browser/files.php', { multipart: multipart( fields, { upload: [ content, fileName ] } ) } );
}

const fileRows = async ( name ) => ( await query( `SELECT id FROM {prefix}sb_stuff WHERE type = 'file' AND name = '${ name }'` ) ).length;

describe( 'the upload folder', () => {
	it( 'has an .htaccess that refuses scripts', async () => {
		const htaccess = await php( "return (string) @file_get_contents( SB_ABSPATH . sb_get_option( 'upload_dir' ) . '.htaccess' );" );
		assert.match( htaccess, /<FilesMatch "[^"]*php[^"]*">\s*Require all denied\s*<\/FilesMatch>/ );
		assert.ok( await php( "return file_exists( SB_ABSPATH . sb_get_option( 'upload_dir' ) . 'images/.htaccess' );" ) );
	} );

	it( 'does not run a PHP file placed in it', async () => {
		await php( "file_put_contents( SB_ABSPATH . sb_get_option( 'upload_dir' ) . 'planted.php', '<?php echo \"ran\" . \"-planted\";' ); return true;" );
		const response = await new Client().request( `/${ fixtures.upload_dir }planted.php` );
		assert.equal( response.status, 403 );
		assert.doesNotMatch( response.text, /ran-planted/ );
		await php( "unlink( SB_ABSPATH . sb_get_option( 'upload_dir' ) . 'planted.php' ); return true;" );
	} );
} );

describe( 'uploads on the Files page', () => {
	it( 'accepts an MP3 from an author', async () => {
		const response = await uploadOnFilesPage( clients.author, MP3, 'author-sermon.mp3' );
		assert.equal( response.status, 200 );
		assert.ok( await uploadExists( 'author-sermon.mp3' ) );
		assert.equal( await fileRows( 'author-sermon.mp3' ), 1 );
	} );

	for ( const [ description, content, fileName ] of [
		[ 'a PHP file', PHP, 'evil.php' ],
		[ 'a PHP file with another PHP extension', PHP, 'evil.phtml' ],
		[ 'PHP code named as an MP3', PHP, 'disguised.mp3' ],
	] ) {
		it( `refuses ${ description }, even from an admin`, async () => {
			const response = await uploadOnFilesPage( clients.admin, content, fileName );
			assert.match( response.text, /You are not permitted to upload files of that type/ );
			assert.ok( ! ( await uploadExists( fileName ) ) );
			assert.equal( await fileRows( fileName ), 0 );
		} );
	}

	it( 'neutralises a double extension', async () => {
		await uploadOnFilesPage( clients.admin, MP3, 'double.php.mp3' );
		assert.ok( ! ( await uploadExists( 'double.php.mp3' ) ) );
		const stored = await one( "SELECT name FROM {prefix}sb_stuff WHERE name LIKE 'double%'" );
		assert.ok( stored, 'the file should be saved under a safe name' );
		assert.match( stored.name, /^double\.php_\.mp3$/ );
	} );

	it( 'refuses an upload without a nonce (cross-site request forgery)', async () => {
		const response = await uploadOnFilesPage( clients.admin, MP3, 'forged.mp3', { nonce: false } );
		assert.match( response.text, /The link you followed has expired/ );
		assert.ok( ! ( await uploadExists( 'forged.mp3' ) ) );
	} );

	it( 'refuses an upload from a subscriber', async () => {
		const response = await clients.subscriber.request( '/wp-admin/admin.php?page=sermon-browser/files.php', { multipart: multipart( { save: 'Upload' }, { upload: [ MP3, 'subscriber.mp3' ] } ) } );
		assert.notEqual( response.status, 200 );
		assert.ok( ! ( await uploadExists( 'subscriber.mp3' ) ) );
	} );
} );

describe( 'importing a file from a URL', () => {
	const importUrl = async ( client, url, importType, { nonce = true } = {} ) => {
		const form = { url, import_type: importType, import_url: 'Import' };
		if ( nonce ) {
			form.sb_upload_nonce = await client.nonce( 'files.php', 'sb_upload_nonce' );
		}
		return client.request( '/wp-admin/admin.php?page=sermon-browser/files.php', { form } );
	};

	it( 'copies a remote MP3 to the upload folder', async () => {
		await importUrl( clients.admin, 'https://example.org/remote-copy.mp3', 'download' );
		assert.ok( await uploadExists( 'remote-copy.mp3' ) );
		assert.equal( await fileRows( 'remote-copy.mp3' ), 1 );
	} );

	it( 'refuses to copy a remote PHP file', async () => {
		const response = await importUrl( clients.admin, 'https://example.org/evil.php', 'download' );
		assert.match( response.text, /You are not permitted to upload files of that type/ );
		assert.ok( ! ( await uploadExists( 'evil.php' ) ) );
	} );

	it( 'refuses an import without a nonce (cross-site request forgery)', async () => {
		const response = await importUrl( clients.admin, 'https://example.org/forged-import.mp3', 'download', { nonce: false } );
		assert.match( response.text, /The link you followed has expired/ );
		assert.ok( ! ( await uploadExists( 'forged-import.mp3' ) ) );
	} );

	it( 'only requests URLs with WordPress’s private-address protection', async () => {
		await php( "delete_option( 'sb_test_http_log' ); return true;" );
		const response = await importUrl( clients.admin, 'http://169.254.169.254/latest/meta-data.mp3', 'remote' );
		assert.match( response.text, /Invalid URL/ );
		const requests = await php( "return get_option( 'sb_test_http_log', array() );" );
		assert.ok( requests.length > 0, 'the import should check the URL' );
		for ( const request of requests ) {
			assert.equal( request.safe, true, `${ request.url } was requested without reject_unsafe_urls` );
		}
		assert.equal( ( await one( "SELECT COUNT(*) n FROM {prefix}sb_stuff WHERE name LIKE '%169.254%'" ) ).n, '0' );
	} );
} );

describe( 'uploads on the Add Sermon page', () => {
	const saveSermonWithUpload = async ( client, content, fileName ) => {
		const nonce = await client.nonce( 'new_sermon.php&mid=3', 'sermon_browser_save_nonce' );
		return client.request( '/wp-admin/admin.php?page=sermon-browser/new_sermon.php&mid=3', {
			multipart: multipart(
				{ sermon_browser_save_nonce: nonce, save: 'Save', title: 'Romans 5: peace with God', preacher: '1', service: '1', series: '1', date: '2026-03-08', tags: 'grace', 'file[]': '0' },
				{ 'upload[]': [ content, fileName ] }
			),
		} );
	};

	it( 'accepts an MP3 from an author', async () => {
		await saveSermonWithUpload( clients.author, MP3, 'attached-by-author.mp3' );
		assert.ok( await uploadExists( 'attached-by-author.mp3' ) );
		assert.equal( ( await one( "SELECT sermon_id FROM {prefix}sb_stuff WHERE name = 'attached-by-author.mp3'" ) ).sermon_id, '3' );
	} );

	it( 'refuses PHP from an author', async () => {
		const response = await saveSermonWithUpload( clients.author, PHP, 'author-shell.php' );
		assert.match( response.text, /You are not permitted to upload files of that type/ );
		assert.ok( ! ( await uploadExists( 'author-shell.php' ) ) );
		assert.equal( await fileRows( 'author-shell.php' ), 0 );
	} );
} );

describe( 'preacher images', () => {
	const savePreacher = async ( client, name, content, fileName ) => {
		const nonce = await client.nonce( 'preachers.php&act=new', 'sermon_manage_preachers_nonce' );
		return client.request( '/wp-admin/admin.php?page=sermon-browser/preachers.php&act=new', {
			multipart: multipart( { sermon_manage_preachers_nonce: nonce, pid: '0', name, description: '', save: 'Save' }, { upload: [ content, fileName ] } ),
		} );
	};

	it( 'accepts an image from an editor', async () => {
		await savePreacher( clients.editor, 'Image preacher', PNG, 'face.png' );
		const preacher = await one( "SELECT image FROM {prefix}sb_preachers WHERE name = 'Image preacher'" );
		assert.equal( preacher.image, 'face.png' );
		assert.ok( await uploadExists( 'images/face.png' ) );
	} );

	it( 'gives a second image with the same name a unique name', async () => {
		await savePreacher( clients.editor, 'Second image preacher', PNG, 'face.png' );
		const preacher = await one( "SELECT image FROM {prefix}sb_preachers WHERE name = 'Second image preacher'" );
		assert.notEqual( preacher.image, 'face.png' );
		assert.ok( await uploadExists( `images/${ preacher.image }` ) );
	} );

	for ( const [ description, content, fileName ] of [
		[ 'a PHP file', PHP, 'face.php' ],
		[ 'PHP code named as an image', PHP, 'face2.png' ],
		[ 'an MP3', MP3, 'face.mp3' ],
	] ) {
		it( `refuses ${ description }`, async () => {
			const response = await savePreacher( clients.editor, `Refused ${ fileName }`, content, fileName );
			assert.match( response.text, /Preacher images must be JPEG, PNG, GIF or WebP files/ );
			assert.ok( ! ( await uploadExists( `images/${ fileName }` ) ) );
		} );
	}

	it( 'refuses to delete a preacher from a link without a nonce (cross-site request forgery)', async () => {
		const response = await clients.admin.request( '/wp-admin/admin.php?page=sermon-browser/preachers.php&act=kill&pid=2' );
		assert.match( response.text, /The link you followed has expired/ );
		assert.equal( ( await one( 'SELECT COUNT(*) n FROM {prefix}sb_preachers WHERE id = 2' ) ).n, '1' );
	} );
} );

describe( 'the upload folder setting', () => {
	const saveFolder = async ( dir ) => {
		const nonce = await clients.admin.nonce( 'options.php', 'sermon_options_save_reset_nonce' );
		const response = await clients.admin.request( '/wp-admin/admin.php?page=sermon-browser/options.php', {
			form: { sermon_options_save_reset_nonce: nonce, save: 'Save', dir, podcast: `${ SITE }/sermons/?podcast`, perpage: '10', filtertype: 'oneclick', import_filename: 'none', mp3_shortcode: '[audio mp3="%SERMONURL%"]', esv_api_key: '' },
		} );
		return { response, folder: await php( "return sb_get_option( 'upload_dir' );" ) };
	};

	for ( const dir of [ '../../../../tmp/', '/etc/', 'wp-content/plugins/', 'wp-admin/', 'wp-content/themes/twentytwentyfive/', 'C:/Windows/' ] ) {
		it( `refuses ${ dir }`, async () => {
			const { response, folder } = await saveFolder( dir );
			assert.match( response.text, /The upload folder must be a folder inside your WordPress folder/ );
			assert.equal( folder, fixtures.upload_dir );
		} );
	}

	it( 'accepts another folder inside uploads, and protects it', async () => {
		const { folder } = await saveFolder( 'wp-content/uploads/other-sermons/' );
		assert.equal( folder, 'wp-content/uploads/other-sermons/' );
		assert.ok( await php( "return file_exists( SB_ABSPATH . 'wp-content/uploads/other-sermons/.htaccess' );" ) );
		await saveFolder( fixtures.upload_dir );
	} );
} );
