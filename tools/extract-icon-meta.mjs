#!/usr/bin/env node
// Reads Phosphor core's icon metadata and emits the icon-meta.json contract:
//   { "<slug>": { "c": ["<category>", …], "t": ["<tag>", …] }, … }
// Only slugs that have a regular-weight SVG are included (set intersection),
// so meta can never drift from the markup map. The "*new*" marker tag is dropped.
//
// Usage: node tools/extract-icon-meta.mjs <packageDir> <regularSvgDir>
import { readdirSync } from 'node:fs';
import { pathToFileURL } from 'node:url';

const [ , , pkgDir, svgDir ] = process.argv;
if ( ! pkgDir || ! svgDir ) {
	console.error( 'usage: extract-icon-meta.mjs <packageDir> <regularSvgDir>' );
	process.exit( 1 );
}

const valid = new Set(
	readdirSync( svgDir )
		.filter( ( f ) => f.endsWith( '.svg' ) )
		.map( ( f ) => f.slice( 0, -4 ) )
);

const { icons } = await import(
	pathToFileURL( `${ pkgDir }/dist/index.mjs` ).href
);

const out = {};
for ( const icon of icons ) {
	if ( ! valid.has( icon.name ) ) {
		continue;
	}
	out[ icon.name ] = {
		c: ( icon.categories || [] ),
		t: ( icon.tags || [] ).filter( ( t ) => t !== '*new*' ),
	};
}

process.stdout.write( JSON.stringify( out ) );
