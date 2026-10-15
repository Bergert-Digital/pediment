/**
 * Dependency-free checks and report logic for validate-blocks.mjs. The
 * WordPress-backed parse/validate functions are injected, so this module
 * runs under `npm run test:kit` without the editor stack.
 */

const TOKEN = /<!--\s+(\/)?wp:([a-z][a-z0-9_-]*(?:\/[a-z][a-z0-9_-]*)?)(?:\s+(\{[\s\S]*?\}))?\s+(\/)?-->/g;

const fullName = ( name ) => ( name.includes( '/' ) ? name : `core/${ name }` );

const MEDIA_PLACEHOLDER = /\{\{media_(url|id):([a-z0-9_\-/]+)\}\}/gi;
const FIRST_STAND_IN_ID = 900001;

/**
 * Replaces the seeder's media placeholders (`{{media_id:<key>}}`,
 * `{{media_url:<key>}}`, see ContentResolver::rewriteMarkup) in a pattern with
 * stand-ins, so the markup can be parsed. Each distinct key gets one numeric
 * id and one URL, used everywhere it occurs. `wp adopt` leaves the concrete
 * `wp-image-<id>` class of an image untouched, so that class is moved to the
 * stand-in id in the text that follows a block comment carrying the stand-in.
 *
 * @param {string} markup
 * @return {string}
 */
export function resolveMediaPlaceholders( markup ) {
	const ids = new Map();
	const resolved = markup.replace( MEDIA_PLACEHOLDER, ( _match, kind, key ) => {
		if ( kind.toLowerCase() === 'url' ) {
			return `https://media.invalid/${ key }`;
		}
		if ( ! ids.has( key ) ) {
			ids.set( key, FIRST_STAND_IN_ID + ids.size );
		}
		return String( ids.get( key ) );
	} );
	if ( ids.size === 0 ) {
		return resolved;
	}
	const standIns = new Set( ids.values() );
	let out = '';
	let last = 0;
	let current = null;
	const text = ( from, to ) => {
		const piece = resolved.slice( from, to );
		out += current === null ? piece : piece.replace( /wp-image-\d+/g, `wp-image-${ current }` );
	};
	for ( const match of resolved.matchAll( TOKEN ) ) {
		text( last, match.index );
		out += match[ 0 ];
		const id = ! match[ 1 ] && match[ 3 ] ? Number( /"id":(\d+)/.exec( match[ 3 ] )?.[ 1 ] ) : NaN;
		current = standIns.has( id ) ? id : null;
		last = match.index + match[ 0 ].length;
	}
	text( last, resolved.length );
	return out;
}

/**
 * Block comments whose attribute JSON does not parse (WordPress drops it silently).
 *
 * @param {string} markup
 * @return {string[]}
 */
export function commentJsonErrors( markup ) {
	const errors = [];
	for ( const match of markup.matchAll( TOKEN ) ) {
		if ( match[ 1 ] || ! match[ 3 ] ) {
			continue;
		}
		try {
			JSON.parse( match[ 3 ] );
		} catch {
			errors.push( `${ fullName( match[ 2 ] ) }: block comment attributes are not valid JSON` );
		}
	}
	return errors;
}

/**
 * Non-whitespace text outside top-level block comments (first 80 chars each).
 *
 * @param {string} markup
 * @return {string[]}
 */
export function looseContent( markup ) {
	const loose = [];
	let depth = 0;
	let last = 0;
	const take = ( from, to ) => {
		const piece = markup.slice( from, to ).trim();
		if ( piece ) {
			loose.push( piece.slice( 0, 80 ) );
		}
	};
	for ( const match of markup.matchAll( TOKEN ) ) {
		if ( depth === 0 ) {
			take( last, match.index );
		}
		if ( match[ 1 ] ) {
			depth = Math.max( 0, depth - 1 );
		} else if ( ! match[ 4 ] ) {
			depth++;
		}
		last = match.index + match[ 0 ].length;
	}
	if ( depth === 0 ) {
		take( last, markup.length );
	}
	return loose;
}

function format( template, values ) {
	const rest = [ ...values ];
	return String( template ).replace( /%[sdo]/g, () => {
		const value = rest.shift();
		return value && typeof value === 'object' ? value.name ?? '[object]' : String( value );
	} );
}

/**
 * Readable messages from a block's validationIssues ({log, args} entries).
 *
 * @param {Array<{args: any[]}>} issues
 * @return {string[]}
 */
export function issueMessages( issues = [] ) {
	return issues
		.map( ( { args = [] } ) => format( args[ 0 ], args.slice( 1 ) ).split( '\n' )[ 0 ] )
		.filter( ( message ) => ! message.startsWith( 'Block validation failed' ) );
}

/**
 * Walks parsed blocks. `validate( block )` is the strict re-check
 * (@wordpress/blocks validateBlock(...)[0]): false means parse() only got a
 * valid block by migrating it through a deprecation.
 *
 * @param {object[]} blocks
 * @param {(block: object) => boolean} validate
 */
export function walkBlocks( blocks, validate ) {
	const result = { checked: 0, invalid: [], migrated: [], notChecked: [] };
	const visit = ( list, prefix ) => {
		list.forEach( ( block, index ) => {
			const missing = block.name === 'core/missing';
			const label = missing ? block.attributes?.originalName ?? 'core/missing' : block.name;
			const here = `${ prefix }[${ index }] ${ label }`;
			result.checked++;
			if ( missing ) {
				const original = block.attributes?.originalName;
				if ( original?.startsWith( 'core/' ) ) {
					// The pinned runtime knows every core block, so this is a typo.
					result.invalid.push( { path: here, name: original, issues: [ `Unknown core block \`${ original }\` (typo?)` ] } );
				} else if ( original ) {
					result.notChecked.push( original );
				}
			} else if ( block.isValid === false ) {
				result.invalid.push( { path: here, name: block.name, issues: issueMessages( block.validationIssues ) } );
			} else if ( validate( block ) === false ) {
				result.migrated.push( { path: here, name: block.name } );
			}
			visit( block.innerBlocks ?? [], `${ here } > ` );
		} );
	};
	visit( blocks, '' );
	return result;
}

/**
 * Runs fn with console output captured and discarded (block validation logs
 * hundreds of lines per invalid block).
 *
 * @template T
 * @param {() => T} fn
 * @return {T}
 */
export function quietly( fn ) {
	const saved = { ...console };
	const noop = () => {};
	Object.assign( console, { error: noop, warn: noop, info: noop, log: noop, groupCollapsed: noop, group: noop, groupEnd: noop } );
	try {
		return fn();
	} finally {
		Object.assign( console, saved );
	}
}

/**
 * The full report.
 *
 * @param {string} markup
 * @param {(markup: string) => object[]} parse
 * @param {(block: object) => boolean} validate
 * @param {string} wordpress WP line used.
 */
export function buildReport( markup, parse, validate, wordpress ) {
	const jsonErrors = commentJsonErrors( markup );
	const loose = looseContent( markup );
	const walked = quietly( () => walkBlocks( parse( markup ), validate ) );
	const ok = jsonErrors.length === 0 && loose.length === 0 && walked.invalid.length === 0 && walked.migrated.length === 0;
	return { ok, wordpress, ...walked, jsonErrors, loose };
}

/**
 * Human-readable report.
 *
 * @param {ReturnType<typeof buildReport>} report
 */
export function formatReport( report ) {
	const lines = [];
	if ( ! report.ok ) {
		lines.push( `✖ Problems found (WordPress ${ report.wordpress }):` );
	} else if ( report.notChecked.length ) {
		lines.push( `No problems found in the blocks WordPress ${ report.wordpress } knows (${ report.checked } block(s) seen); some blocks were not checked:` );
	} else {
		lines.push( `✔ ${ report.checked } block(s) valid for WordPress ${ report.wordpress }.` );
	}
	for ( const error of report.jsonErrors ) {
		lines.push( `  JSON  ${ error }` );
	}
	for ( const piece of report.loose ) {
		lines.push( `  LOOSE content outside a block: ${ piece }` );
	}
	for ( const block of report.invalid ) {
		lines.push( `  INVALID ${ block.path }` );
		for ( const issue of block.issues ) {
			lines.push( `      ${ issue }` );
		}
	}
	for ( const block of report.migrated ) {
		lines.push( `  STALE ${ block.path } — only valid through a deprecation; re-save it in its current form` );
	}
	if ( report.notChecked.length ) {
		lines.push( `  not checked (unknown to the validator): ${ [ ...new Set( report.notChecked ) ].join( ', ' ) }` );
	}
	return lines.join( '\n' ) + '\n';
}
