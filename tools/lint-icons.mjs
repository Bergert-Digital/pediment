#!/usr/bin/env node
// Validates the committed icon data against the swap contract. Offline; no network.
//   - icon-meta.json keys must be a subset of icon-markup.json keys
//   - icon-set.json must have a viewBox string and an svgAttrs object
import { readFileSync } from 'node:fs';

const dir = new URL( '../assets/icons/', import.meta.url );
const read = ( name ) => JSON.parse( readFileSync( new URL( name, dir ), 'utf8' ) );

let markup, meta, set;
try {
	markup = read( 'icon-markup.json' );
	meta = read( 'icon-meta.json' );
	set = read( 'icon-set.json' );
} catch ( err ) {
	console.error( `✗ cannot read icon data: ${ err.message }` );
	process.exit( 1 );
}

const markupKeys = new Set( Object.keys( markup ) );
const stray = Object.keys( meta ).filter( ( k ) => ! markupKeys.has( k ) );
if ( stray.length ) {
	console.error(
		`✗ icon-meta.json has ${ stray.length } slug(s) absent from icon-markup.json, e.g. ${ stray
			.slice( 0, 5 )
			.join( ', ' ) }`
	);
	process.exit( 1 );
}

if ( typeof set.viewBox !== 'string' || typeof set.svgAttrs !== 'object' || set.svgAttrs === null ) {
	console.error( '✗ icon-set.json must have a string viewBox and an object svgAttrs' );
	process.exit( 1 );
}

console.log(
	`✓ icons ok: ${ markupKeys.size } markup, ${ Object.keys( meta ).length } meta, set ${ set.name ?? 'set' }@${ set.version ?? '?' }`
);
