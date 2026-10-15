import { test } from 'node:test';
import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import path from 'node:path';
import { existsSync, mkdirSync, mkdtempSync, readdirSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import {
	commentJsonErrors,
	looseContent,
	walkBlocks,
	issueMessages,
	buildReport,
	formatReport,
} from '../scripts/lib/block-check.mjs';
import { wpLine, supportedLines, ensureRuntime, npmInstall } from '../scripts/lib/wp-runtime.mjs';
import { parseArgs } from '../scripts/validate-blocks.mjs';

const here = path.dirname( fileURLToPath( import.meta.url ) );
const cli = path.join( here, '..', 'scripts', 'validate-blocks.mjs' );

test( 'comment JSON errors name the block', () => {
	assert.deepEqual( commentJsonErrors( '<!-- wp:group {"a":} --><div></div><!-- /wp:group -->' ), [ 'core/group: block comment attributes are not valid JSON' ] );
	assert.deepEqual( commentJsonErrors( '<!-- wp:group {"a":{"b":1}} --><div></div><!-- /wp:group --><!-- wp:acme/x {"y":2} /-->' ), [] );
} );

test( 'loose content is only text outside top-level blocks', () => {
	assert.deepEqual( looseContent( '<!-- wp:paragraph --><p>x</p><!-- /wp:paragraph -->\n\n' ), [] );
	assert.deepEqual( looseContent( '<p>loose</p><!-- wp:paragraph --><p>x</p><!-- /wp:paragraph -->tail' ), [ '<p>loose</p>', 'tail' ] );
} );

test( 'walkBlocks separates invalid, migrated and unknown blocks', () => {
	const blocks = [
		{ name: 'core/group', isValid: true, innerBlocks: [ { name: 'core/paragraph', isValid: true, innerBlocks: [] } ] },
		{ name: 'core/paragraph', isValid: true, innerBlocks: [] },
		{ name: 'core/cover', isValid: false, validationIssues: [ { args: [ 'Expected attribute `class` of value `%s`, saw `%s`.', 'a', 'b' ] }, { args: [ 'Block validation failed for `%s` (%o).', 'core/cover', { name: 'core/cover' } ] } ], innerBlocks: [] },
		{ name: 'core/missing', isValid: true, attributes: { originalName: 'pediment/form' }, innerBlocks: [] },
	];
	const migrated = new Set( [ blocks[ 1 ] ] );
	const result = walkBlocks( blocks, ( block ) => ! migrated.has( block ) );
	assert.equal( result.checked, 5 );
	assert.deepEqual( result.invalid, [ { path: '[2] core/cover', name: 'core/cover', issues: [ 'Expected attribute `class` of value `a`, saw `b`.' ] } ] );
	assert.deepEqual( result.migrated, [ { path: '[1] core/paragraph', name: 'core/paragraph' } ] );
	assert.deepEqual( result.notChecked, [ 'pediment/form' ] );
} );

test( 'issueMessages drops the summary line', () => {
	assert.deepEqual( issueMessages( [ { args: [ 'Block validation failed for `%s` (%o).', 'x', {} ] } ] ), [] );
} );

test( 'buildReport and formatReport', () => {
	const parse = () => [ { name: 'core/paragraph', isValid: true, innerBlocks: [] } ];
	const report = buildReport( '<!-- wp:paragraph --><p>x</p><!-- /wp:paragraph -->', parse, () => true, '6.9' );
	assert.equal( report.ok, true );
	assert.match( formatReport( report ), /valid/i );

	const bad = buildReport( 'loose<!-- wp:paragraph --><p>x</p><!-- /wp:paragraph -->', parse, () => true, '6.9' );
	assert.equal( bad.ok, false );
	assert.match( formatReport( bad ), /outside/i );
} );

test( 'version lines and arguments', () => {
	assert.equal( wpLine( '6.9.4' ), '6.9' );
	assert.equal( wpLine( '7.0' ), '7.0' );
	assert.throws( () => wpLine( 'trunk' ) );
	assert.deepEqual( supportedLines(), [ '6.9', '7.0' ] );
	assert.deepEqual( parseArgs( [ '--wp', '7.0', '--file', 'a.html', '--json' ] ), { wp: '7.0', file: 'a.html', json: true } );
	assert.deepEqual( parseArgs( [] ), { wp: '6.9', file: null, json: false } );
} );

test( 'unknown core blocks are problems, third-party unknown blocks are not checked', () => {
	const blocks = [
		{ name: 'core/missing', isValid: true, attributes: { originalName: 'core/paragrph' }, innerBlocks: [] },
		{ name: 'core/missing', isValid: true, attributes: { originalName: 'pediment/form' }, innerBlocks: [] },
	];
	const result = walkBlocks( blocks, () => true );
	assert.equal( result.invalid.length, 1 );
	assert.equal( result.invalid[ 0 ].name, 'core/paragrph' );
	assert.match( result.invalid[ 0 ].issues[ 0 ], /unknown core block/i );
	assert.deepEqual( result.notChecked, [ 'pediment/form' ] );
	const report = buildReport( '<!-- wp:paragrph /-->', () => blocks.slice( 0, 1 ), () => true, '6.9' );
	assert.equal( report.ok, false );
} );

test( 'a report with unchecked blocks is not announced as plainly valid', () => {
	const parse = () => [ { name: 'core/missing', isValid: true, attributes: { originalName: 'pediment/form' }, innerBlocks: [] } ];
	const report = buildReport( '<!-- wp:pediment/form /-->', parse, () => true, '6.9' );
	assert.equal( report.ok, true );
	const text = formatReport( report );
	assert.doesNotMatch( text, /✔ \d+ block\(s\) valid/ );
	assert.match( text, /not checked/i );
	assert.match( text, /pediment\/form/ );
} );

test( 'nameless core/missing (loose text) is not listed as not checked', () => {
	const parse = () => [ { name: 'core/missing', isValid: true, attributes: {}, innerBlocks: [] } ];
	assert.deepEqual( buildReport( 'loose', parse, () => true, '6.9' ).notChecked, [] );
} );

test( 'options without a value are errors', () => {
	assert.throws( () => parseArgs( [ '--file' ] ), /missing value for --file/ );
	assert.throws( () => parseArgs( [ '--wp' ] ), /missing value for --wp/ );
	assert.throws( () => parseArgs( [ '--file', '--json' ] ), /missing value for --file/ );
} );

const fakeInstall = ( dir ) => {
	mkdirSync( path.join( dir, 'node_modules', '@wordpress', 'blocks' ), { recursive: true } );
	writeFileSync( path.join( dir, 'node_modules', '@wordpress', 'blocks', 'package.json' ), '{}' );
};
const cacheRoot = () => mkdtempSync( path.join( tmpdir(), 'pediment-validator-test-' ) );
const quiet = { log() {} };

test( 'ensureRuntime installs into a temp dir and renames it into place', () => {
	const root = cacheRoot();
	let installedIn;
	const dir = ensureRuntime( '6.9', { cacheRoot: root, ...quiet, install: ( target ) => { installedIn = target; fakeInstall( target ); } } );
	assert.notEqual( installedIn, dir );
	assert.match( path.basename( installedIn ), /\.tmp-/ );
	assert.ok( existsSync( path.join( dir, '.ok' ) ) );
	assert.deepEqual( readdirSync( root ), [ path.basename( dir ) ] );
	let again = false;
	assert.equal( ensureRuntime( '6.9', { cacheRoot: root, ...quiet, install: () => { again = true; } } ), dir );
	assert.equal( again, false );
	rmSync( root, { recursive: true } );
} );

test( 'ensureRuntime keeps the winner when another process installs first', () => {
	const root = cacheRoot();
	const dir = ensureRuntime( '6.9', { cacheRoot: root, ...quiet, install: fakeInstall } );
	rmSync( dir, { recursive: true } );
	let winner;
	const result = ensureRuntime( '6.9', {
		cacheRoot: root,
		...quiet,
		install: ( target ) => {
			fakeInstall( target );
			winner = dir;
			fakeInstall( winner );
			writeFileSync( path.join( winner, '.ok' ), 'winner' );
		},
	} );
	assert.equal( result, dir );
	assert.equal( readdirSync( root ).length, 1, 'temp dir is removed' );
	assert.equal( readdirSync( dir ).includes( '.ok' ), true );
	rmSync( root, { recursive: true } );
} );

test( 'ensureRuntime reinstalls a cache that has .ok but an empty node_modules', () => {
	const root = cacheRoot();
	const dir = ensureRuntime( '6.9', { cacheRoot: root, ...quiet, install: fakeInstall } );
	rmSync( path.join( dir, 'node_modules' ), { recursive: true } );
	let calls = 0;
	assert.equal( ensureRuntime( '6.9', { cacheRoot: root, ...quiet, install: ( target ) => { calls++; fakeInstall( target ); } } ), dir );
	assert.equal( calls, 1 );
	assert.ok( existsSync( path.join( dir, 'node_modules', '@wordpress', 'blocks', 'package.json' ) ) );
	assert.equal( readdirSync( root ).length, 1 );
	rmSync( root, { recursive: true } );
} );

test( 'a failed install leaves no cache dir behind', () => {
	const root = cacheRoot();
	assert.throws( () => ensureRuntime( '6.9', { cacheRoot: root, ...quiet, install: () => { throw new Error( 'boom' ); } } ), /boom/ );
	assert.deepEqual( readdirSync( root ), [] );
	rmSync( root, { recursive: true } );
} );

test( 'npmInstall explains a missing npm and a failed install', () => {
	const dir = cacheRoot();
	assert.throws( () => npmInstall( dir, { npm: 'pediment-no-such-npm' } ), /npm is required on PATH/ );
	assert.throws( () => npmInstall( dir, { npm: process.execPath, args: [ '-e', 'process.exit(1)' ] } ), /network access.*pediment-validator-test-/s );
	rmSync( dir, { recursive: true } );
} );

const e2e = process.env.PEDIMENT_VALIDATOR_E2E === '1';

test( 'end to end against the real WordPress 6.9 runtime', { skip: ! e2e }, () => {
	const run = ( markup ) => {
		try {
			return { code: 0, out: execFileSync( 'node', [ cli, '--json' ], { input: markup, encoding: 'utf8' } ) };
		} catch ( error ) {
			return { code: error.status, out: error.stdout };
		}
	};
	const valid = run( '<!-- wp:group {"className":"is-style-card","layout":{"type":"constrained"}} --><div class="wp-block-group is-style-card"><!-- wp:paragraph --><p>x</p><!-- /wp:paragraph --></div><!-- /wp:group -->' );
	assert.equal( valid.code, 0, valid.out );

	const migrated = run( '<!-- wp:paragraph --><div>Hi</div><!-- /wp:paragraph -->' );
	assert.equal( migrated.code, 1 );
	assert.equal( JSON.parse( migrated.out ).migrated.length, 1 );

	const invalid = run( '<!-- wp:group --><div class="wp-block-nope"><!-- wp:paragraph --><p>x</p><!-- /wp:paragraph --></div><!-- /wp:group -->' );
	assert.equal( invalid.code, 1 );
	assert.equal( JSON.parse( invalid.out ).invalid[ 0 ].name, 'core/group' );

	const unknown = run( '<!-- wp:pediment/form {"id":"x"} /-->' );
	assert.equal( unknown.code, 0 );
	assert.deepEqual( JSON.parse( unknown.out ).notChecked, [ 'pediment/form' ] );
} );
