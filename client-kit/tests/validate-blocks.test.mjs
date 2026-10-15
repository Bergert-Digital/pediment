import { test } from 'node:test';
import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import path from 'node:path';
import {
	commentJsonErrors,
	looseContent,
	walkBlocks,
	issueMessages,
	buildReport,
	formatReport,
} from '../scripts/lib/block-check.mjs';
import { wpLine, supportedLines } from '../scripts/lib/wp-runtime.mjs';
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
