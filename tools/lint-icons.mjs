// Validates the committed icon data against the swap contract. Offline; no network.
//   - icon-meta.json keys must be a subset of icon-markup.json keys
//   - icon-set.json must have a viewBox string and an svgAttrs object
import { readFileSync } from 'node:fs';

const dir = 'assets/icons/';
const read = ( name ) => JSON.parse( readFileSync( dir + name, 'utf8' ) );

const markup = read( 'icon-markup.json' );
const meta = read( 'icon-meta.json' );
const set = read( 'icon-set.json' );

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
	`✓ icons ok: ${ markupKeys.size } markup, ${ Object.keys( meta ).length } meta, set ${ set.name }@${ set.version }`
);
