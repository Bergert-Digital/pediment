#!/usr/bin/env node
/**
 * Validates the core block trees in client-kit/tests/fixtures/core-trees/
 * with the client-kit block validator, once per supported WordPress line.
 * The trees are the outputs of the legacy-block converter (Pediment 4-5):
 * core blocks in every default named style. They guard the validator when
 * a new WordPress line arrives.
 *
 * Usage: node tools/validate-core-trees.mjs [--wp 6.9]
 * Without --wp it runs itself once per line (each line needs its own process,
 * because the validator loads one WordPress runtime into globals).
 * Exit codes: 0 all valid, 1 a fixture is invalid, 2 the tool failed.
 */
import { execFileSync } from 'node:child_process';
import { readFileSync, readdirSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { buildReport, formatReport } from '../client-kit/scripts/lib/block-check.mjs';
import { ensureRuntime, loadWordPress, supportedLines, wpLine } from '../client-kit/scripts/lib/wp-runtime.mjs';

const self = fileURLToPath( import.meta.url );
const dir = path.join( path.dirname( self ), '..', 'client-kit', 'tests', 'fixtures', 'core-trees' );
const at = process.argv.indexOf( '--wp' );

if ( at === -1 ) {
	let failed = false;
	for ( const line of supportedLines() ) {
		try {
			execFileSync( process.execPath, [ self, '--wp', line ], { stdio: 'inherit' } );
		} catch ( error ) {
			if ( error.status !== 1 ) {
				throw error;
			}
			failed = true;
		}
	}
	process.exit( failed ? 1 : 0 );
}

const line = wpLine( process.argv[ at + 1 ] ?? '' );
const files = readdirSync( dir ).filter( ( name ) => name.endsWith( '.expected.html' ) ).sort();
if ( files.length === 0 ) {
	process.stderr.write( `validate-core-trees: no *.expected.html in ${ dir }\n` );
	process.exit( 2 );
}
const wordpress = loadWordPress( ensureRuntime( line ) );
let failed = 0;
for ( const file of files ) {
	const report = buildReport( readFileSync( path.join( dir, file ), 'utf8' ), wordpress.parse, ( block ) => wordpress.validateBlock( block )[ 0 ], line );
	if ( report.ok ) {
		process.stdout.write( `ok   ${ line } ${ file }\n` );
	} else {
		failed++;
		process.stdout.write( `FAIL ${ line } ${ file }\n${ formatReport( report ) }\n` );
	}
}
process.stdout.write( `${ files.length - failed }/${ files.length } trees valid on WordPress ${ line }.\n` );
process.exit( failed > 0 ? 1 : 0 );
