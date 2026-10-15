/**
 * Installs (once, into a cache) and loads the WordPress block runtime used
 * to validate markup in Node. The runtime is pinned per WordPress line by a
 * committed package-lock.json under client-kit/validator/wp-<line>/.
 */
import { createHash } from 'node:crypto';
import { execFileSync } from 'node:child_process';
import { copyFileSync, existsSync, mkdirSync, readdirSync, readFileSync, writeFileSync } from 'node:fs';
import { createRequire } from 'node:module';
import { homedir } from 'node:os';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const VALIDATOR_DIR = path.join( path.dirname( fileURLToPath( import.meta.url ) ), '..', '..', 'validator' );

/**
 * "6.9.4" → "6.9".
 *
 * @param {string} version
 */
export function wpLine( version ) {
	const match = /^(\d+)\.(\d+)/.exec( String( version ) );
	if ( ! match ) {
		throw new Error( `Not a WordPress version: ${ version }` );
	}
	return `${ match[ 1 ] }.${ match[ 2 ] }`;
}

/** WordPress lines with a pinned runtime, sorted. */
export function supportedLines() {
	return readdirSync( VALIDATOR_DIR )
		.filter( ( entry ) => entry.startsWith( 'wp-' ) && existsSync( path.join( VALIDATOR_DIR, entry, 'package-lock.json' ) ) )
		.map( ( entry ) => entry.slice( 3 ) )
		.sort();
}

/**
 * Returns the cache dir holding an installed runtime for a WP line,
 * installing it on first use.
 *
 * @param {string} line
 * @param {{cacheRoot?: string, log?: (message: string) => void}} options
 */
export function ensureRuntime( line, options = {} ) {
	const source = path.join( VALIDATOR_DIR, `wp-${ line }` );
	if ( ! existsSync( path.join( source, 'package-lock.json' ) ) ) {
		throw new Error( `No validator runtime for WordPress ${ line }. Supported: ${ supportedLines().join( ', ' ) }` );
	}
	const cacheRoot =
		options.cacheRoot ??
		process.env.PEDIMENT_VALIDATOR_CACHE ??
		path.join( process.env.XDG_CACHE_HOME ?? path.join( homedir(), '.cache' ), 'pediment', 'validator' );
	const log = options.log ?? ( ( message ) => process.stderr.write( `${ message }\n` ) );
	const hash = createHash( 'sha256' ).update( readFileSync( path.join( source, 'package-lock.json' ) ) ).digest( 'hex' ).slice( 0, 8 );
	const dir = path.join( cacheRoot, `wp-${ line }-${ hash }` );
	if ( ! existsSync( path.join( dir, '.ok' ) ) ) {
		log( `Installing the block validator runtime for WordPress ${ line } (one time, about 700 MB)…` );
		mkdirSync( dir, { recursive: true } );
		copyFileSync( path.join( source, 'package.json' ), path.join( dir, 'package.json' ) );
		copyFileSync( path.join( source, 'package-lock.json' ), path.join( dir, 'package-lock.json' ) );
		execFileSync( 'npm', [ 'ci', '--ignore-scripts', '--no-audit', '--no-fund' ], { cwd: dir, stdio: [ 'ignore', 'ignore', 'inherit' ] } );
		writeFileSync( path.join( dir, '.ok' ), new Date().toISOString() );
	}
	return dir;
}

/**
 * Loads @wordpress/blocks with core blocks registered, from a runtime dir.
 * Globals must exist before the first @wordpress require; the CJS builds
 * are used because the ESM builds use directory imports Node rejects.
 *
 * @param {string} dir
 * @return {{parse: Function, validateBlock: Function}}
 */
export function loadWordPress( dir ) {
	const require = createRequire( path.join( dir, 'index.js' ) );
	const { JSDOM, VirtualConsole } = require( 'jsdom' );
	const { window } = new JSDOM( '<!doctype html><html><body></body></html>', {
		url: 'http://localhost/',
		virtualConsole: new VirtualConsole(),
	} );
	Object.defineProperty( globalThis, 'navigator', { value: window.navigator, configurable: true } );
	for ( const key of Object.getOwnPropertyNames( window ) ) {
		if ( key in globalThis ) {
			continue;
		}
		Object.defineProperty( globalThis, key, { configurable: true, get: () => window[ key ] } );
	}
	window.matchMedia ??= () => ( { matches: false, addListener() {}, removeListener() {}, addEventListener() {}, removeEventListener() {} } );
	globalThis.requestAnimationFrame ??= ( callback ) => setTimeout( callback, 0 );

	const { registerCoreBlocks } = require( '@wordpress/block-library' );
	const { parse, validateBlock } = require( '@wordpress/blocks' );
	const quiet = { ...console };
	Object.assign( console, { error() {}, warn() {}, info() {} } );
	try {
		registerCoreBlocks();
	} finally {
		Object.assign( console, quiet );
	}
	return { parse, validateBlock };
}
