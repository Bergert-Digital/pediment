#!/usr/bin/env node
/**
 * Validates Gutenberg block markup the way the block editor does, before an
 * agent writes it to a site (pediment-write-page) or a pattern file.
 *
 * Usage:
 *   node validate-blocks.mjs [--wp 6.9] [--file page.html] [--json]
 *   cat page.html | node validate-blocks.mjs --wp 7.0
 *
 * --wp     WordPress version or line of the target site (get-vocabulary reports it). Default 6.9.
 * --file   Markup file; a .php pattern file has its leading <?php … ?> header removed.
 * --json   Machine-readable report.
 *
 * Exit codes: 0 valid, 1 problems found, 2 the tool itself failed.
 * First run per WordPress line installs a pinned runtime into
 * ~/.cache/pediment/validator (override with PEDIMENT_VALIDATOR_CACHE).
 */
import { readFileSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { buildReport, formatReport } from './lib/block-check.mjs';
import { ensureRuntime, loadWordPress, wpLine } from './lib/wp-runtime.mjs';

/**
 * @param {string[]} argv
 * @return {{wp: string, file: string|null, json: boolean}}
 */
export function parseArgs( argv ) {
	const options = { wp: '6.9', file: null, json: false };
	for ( let i = 0; i < argv.length; i++ ) {
		const arg = argv[ i ];
		if ( arg === '--wp' || arg === '--file' ) {
			const value = argv[ ++i ];
			if ( value === undefined || value.startsWith( '--' ) ) {
				throw new Error( `missing value for ${ arg }` );
			}
			options[ arg.slice( 2 ) ] = value;
		} else if ( arg === '--json' ) {
			options.json = true;
		} else {
			throw new Error( `Unknown argument: ${ arg }` );
		}
	}
	return options;
}

function readMarkup( file ) {
	const raw = file ? readFileSync( file, 'utf8' ) : readFileSync( 0, 'utf8' );
	return file && file.endsWith( '.php' ) ? raw.replace( /^<\?php[\s\S]*?\?>\s*/, '' ) : raw;
}

function main() {
	const options = parseArgs( process.argv.slice( 2 ) );
	const line = wpLine( options.wp );
	const markup = readMarkup( options.file );
	const wordpress = loadWordPress( ensureRuntime( line ) );
	const report = buildReport( markup, wordpress.parse, ( block ) => wordpress.validateBlock( block )[ 0 ], line );
	process.stdout.write( options.json ? `${ JSON.stringify( report, null, 2 ) }\n` : formatReport( report ) );
	process.exitCode = report.ok ? 0 : 1;
}

if ( process.argv[ 1 ] && path.resolve( process.argv[ 1 ] ) === fileURLToPath( import.meta.url ) ) {
	try {
		main();
	} catch ( error ) {
		process.stderr.write( `validate-blocks: ${ error.message }\n` );
		process.exit( 2 );
	}
}
