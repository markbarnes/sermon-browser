/**
 * Runs the behaviour tests with code coverage and reports which parts of the
 * plugin they never reach (npm run test:coverage).
 *
 * The test site must be running with Xdebug in coverage mode
 * (npx wp-env start --xdebug=coverage). tests/mu-plugins/sb-test-coverage.php
 * records each request into coverage/raw/ while it exists; it's deleted once the
 * report is written.
 *
 * Writes:
 *   coverage/summary.md       lines covered per file, functions never or partly run, template tags never rendered
 *   coverage/files/<file>.txt each file's source, with uncovered lines marked "!!"
 *
 * Usage: node tests/coverage/run.js [test files...]
 */
import { spawnSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { php } from '../behaviour/lib/wp.js';

const root = fileURLToPath( new URL( '../..', import.meta.url ) );
const out = path.join( root, 'coverage' );
const raw = path.join( out, 'raw' );

const mode = await php( "return (string) ini_get( 'xdebug.mode' );" );
if ( ! mode.includes( 'coverage' ) ) {
	console.error( 'Xdebug coverage is off on the test site. Run: npx wp-env start --xdebug=coverage' );
	process.exit( 1 );
}
fs.rmSync( out, { recursive: true, force: true } );
fs.mkdirSync( raw, { recursive: true } );
const testFiles = process.argv.slice( 2 );
const status = spawnSync( 'node', [ '--test', '--test-concurrency=1', ...( testFiles.length ? testFiles : [ 'tests/behaviour/**/*.test.js' ] ) ], { cwd: root, stdio: 'inherit' } ).status;

// Merge every request: a line counts as covered if any request ran it.
const lines = {};
const functions = {};
const templates = { search: new Set(), single: new Set() };
const rawFiles = fs.readdirSync( raw ).filter( ( name ) => name.endsWith( '.json' ) );
for ( const name of rawFiles ) {
	const data = JSON.parse( fs.readFileSync( path.join( raw, name ), 'utf8' ) );
	for ( const [ file, fileLines ] of Object.entries( data.lines ) ) {
		lines[ file ] ??= {};
		for ( const [ line, state ] of Object.entries( fileLines ) ) {
			lines[ file ][ line ] = Math.max( lines[ file ][ line ] ?? -1, state );
		}
	}
	Object.assign( functions, data.functions );
	for ( const [ type, template ] of Object.entries( data.templates ) ) {
		templates[ type ].add( template );
	}
}

// Plugin files no request loaded at all.
const pluginFiles = [ 'sermon.php', ...fs.readdirSync( path.join( root, 'sb-includes' ) ).filter( ( name ) => name.endsWith( '.php' ) ).map( ( name ) => 'sb-includes/' + name ) ];
const neverLoaded = pluginFiles.filter( ( file ) => ! lines[ file ] );

function count( file, from = 0, to = Infinity ) {
	let total = 0;
	let covered = 0;
	for ( const [ line, state ] of Object.entries( lines[ file ] ?? {} ) ) {
		if ( line >= from && line <= to ) {
			total++;
			covered += state > 0 ? 1 : 0;
		}
	}
	return { total, covered };
}

const percent = ( { total, covered } ) => ( total ? Math.round( ( covered / total ) * 100 ) : 100 ) + '%';

const report = [ '# Coverage of the behaviour tests', '', `${ rawFiles.length } requests recorded.`, '', '## Lines by file', '', '| File | Lines | Covered | % |', '|---|---:|---:|---:|' ];
const all = { total: 0, covered: 0 };
for ( const file of Object.keys( lines ).sort() ) {
	const c = count( file );
	all.total += c.total;
	all.covered += c.covered;
	report.push( `| ${ file } | ${ c.total } | ${ c.covered } | ${ percent( c ) } |` );
}
report.push( `| **All** | ${ all.total } | ${ all.covered } | ${ percent( all ) } |` );
if ( neverLoaded.length ) {
	report.push( '', 'Never loaded: ' + neverLoaded.join( ', ' ) );
}

// Functions that never ran, then those partly run, by file.
const byFile = {};
for ( const [ name, [ file, start, end ] ] of Object.entries( functions ) ) {
	const c = count( file, start, end );
	( byFile[ file ] ??= [] ).push( { name, start, ...c } );
}
const listFunctions = ( title, test ) => {
	report.push( '', '## ' + title, '' );
	for ( const file of Object.keys( byFile ).sort() ) {
		const found = byFile[ file ].filter( test ).sort( ( a, b ) => a.start - b.start );
		if ( found.length ) {
			report.push( `**${ file }**: ` + found.map( ( f ) => `\`${ f.name }()\` (${ f.start }${ f.covered ? ', ' + percent( f ) : '' })` ).join( ', ' ), '' );
		}
	}
};
listFunctions( 'Functions never run', ( f ) => f.total && ! f.covered );
listFunctions( 'Functions partly run (under 75%)', ( f ) => f.covered && f.covered / f.total < 0.75 );

// Template tags: each tag's code runs inside eval(), which line coverage can't see,
// so list the dictionary tags that no rendered template contained.
// Read from the source rather than the site, so the report itself isn't recorded.
const dictionarySource = fs.readFileSync( path.join( root, 'sb-includes/dictionary.php' ), 'utf8' );
const dictionaryTags = ( name ) => [ ...dictionarySource.split( `function ${ name }(` )[ 1 ].split( /\nfunction / )[ 0 ].matchAll( /^\s*'(\[[^\]]+\])' =>/gm ) ].map( ( match ) => match[ 1 ] );
const dictionaries = { search: dictionaryTags( 'sb_search_results_dictionary' ), single: dictionaryTags( 'sb_sermon_page_dictionary' ) };
report.push( '', '## Template tags never rendered', '' );
for ( const [ type, tags ] of Object.entries( dictionaries ) ) {
	const rendered = [ ...templates[ type ] ].join( '\n' );
	const missing = tags.filter( ( tag ) => ! rendered.includes( tag ) );
	report.push( `**${ type }** (${ templates[ type ].size } template${ templates[ type ].size === 1 ? '' : 's' } rendered): ` + ( missing.length ? missing.map( ( tag ) => '`' + tag + '`' ).join( ', ' ) : 'none' ), '' );
}

fs.writeFileSync( path.join( out, 'summary.md' ), report.join( '\n' ) + '\n' );
// Stop recording, so that npm test doesn't go on filling the folder.
fs.rmSync( raw, { recursive: true, force: true } );

// Annotated source: "!!" marks a line that can run but never did.
for ( const file of Object.keys( lines ) ) {
	const source = fs.readFileSync( path.join( root, file ), 'utf8' ).split( '\n' );
	const annotated = source.map( ( text, i ) => {
		const state = lines[ file ][ i + 1 ];
		const mark = state === undefined ? '  ' : state > 0 ? '  ' : '!!';
		return `${ mark } ${ String( i + 1 ).padStart( 4 ) }  ${ text }`;
	} );
	const target = path.join( out, 'files', file + '.txt' );
	fs.mkdirSync( path.dirname( target ), { recursive: true } );
	fs.writeFileSync( target, annotated.join( '\n' ) );
}

console.log( `\nCoverage: ${ percent( all ) } of ${ all.total } lines. Report: coverage/summary.md` );
process.exit( status ?? 1 );
