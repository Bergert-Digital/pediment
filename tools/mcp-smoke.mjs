#!/usr/bin/env node
/**
 * MCP smoke test. Talks to the Pediment MCP server of the running root
 * wp-env exactly like Claude Code does (JSON-RPC over HTTP, Basic auth with
 * an application password) and checks: the tool list, get-vocabulary, a
 * write-page → check-page round trip, and that a rule violation comes back
 * as a tool error naming the violation. Removes the page and the
 * application password it created.
 *
 * Usage: node tools/mcp-smoke.mjs   (wp-env must be running)
 */
import { execFileSync } from 'node:child_process';

const EXPECTED = [
	'pediment-get-vocabulary',
	'pediment-list-pages',
	'pediment-read-page',
	'pediment-check-page',
	'pediment-write-page',
	'pediment-rollback-page',
	'pediment-upsert-style',
	'pediment-delete-style',
];

function wp( ...args ) {
	const out = execFileSync( 'npx', [ 'wp-env', 'run', 'cli', 'wp', ...args ], {
		encoding: 'utf8',
		stdio: [ 'ignore', 'pipe', 'pipe' ],
	} );
	return out
		.split( '\n' )
		.filter( ( line ) => ! /^\s*(ℹ|✔)/.test( line ) )
		.join( '\n' )
		.trim();
}

function assert( condition, message ) {
	if ( ! condition ) {
		throw new Error( message );
	}
}

const text = ( result ) => ( result.content || [] ).map( ( c ) => c.text ).join( ' ' );

async function main() {
	const site = wp( 'option', 'get', 'siteurl' );
	const password = wp( 'user', 'application-password', 'create', 'admin', 'mcp-smoke', '--porcelain' );
	const endpoint = `${ site }/?rest_route=/pediment/v1/mcp`;
	const auth = 'Basic ' + Buffer.from( `admin:${ password }` ).toString( 'base64' );
	let session = null;
	let nextId = 0;
	let pageId = 0;

	const rpc = async ( method, params ) => {
		const headers = { 'Content-Type': 'application/json', Authorization: auth };
		if ( session ) {
			headers[ 'Mcp-Session-Id' ] = session;
			headers[ 'MCP-Protocol-Version' ] = '2025-11-25';
		}
		const notification = method.startsWith( 'notifications/' );
		const body = { jsonrpc: '2.0', method, ...( params ? { params } : {} ) };
		if ( ! notification ) {
			body.id = ++nextId;
		}
		const response = await fetch( endpoint, { method: 'POST', headers, body: JSON.stringify( body ) } );
		session = session || response.headers.get( 'mcp-session-id' );
		if ( notification ) {
			return null;
		}
		const json = await response.json();
		if ( json.error ) {
			throw new Error( `${ method }: ${ json.error.message }` );
		}
		return json.result;
	};
	const call = ( name, args ) => rpc( 'tools/call', { name, arguments: args } );

	try {
		await rpc( 'initialize', {
			protocolVersion: '2025-11-25',
			capabilities: {},
			clientInfo: { name: 'pediment-mcp-smoke', version: '1' },
		} );
		await rpc( 'notifications/initialized' );

		const { tools } = await rpc( 'tools/list' );
		const names = tools.map( ( tool ) => tool.name );
		for ( const name of EXPECTED ) {
			assert( names.includes( name ), `tools/list lacks ${ name }` );
		}

		const vocabulary = await call( 'pediment-get-vocabulary', {} );
		assert( ! vocabulary.isError, `get-vocabulary failed: ${ text( vocabulary ) }` );
		assert( vocabulary.structuredContent.styles.some( ( s ) => s.slug === 'card' ), 'get-vocabulary has no card style' );

		const written = await call( 'pediment-write-page', {
			title: 'MCP smoke',
			markup: '<!-- wp:paragraph -->\n<p>Hello from MCP.</p>\n<!-- /wp:paragraph -->',
		} );
		assert( ! written.isError, `write-page failed: ${ text( written ) }` );
		pageId = written.structuredContent.id;

		const checked = await call( 'pediment-check-page', { id: pageId } );
		assert( ! checked.isError && checked.structuredContent.errors.length === 0, `check-page reported errors: ${ text( checked ) }` );

		const rejected = await call( 'pediment-write-page', {
			title: 'MCP smoke bad',
			markup: '<!-- wp:group {"style":{"spacing":{"padding":{"top":"78px"}}}} -->\n<div class="wp-block-group" style="padding-top:78px"></div>\n<!-- /wp:group -->',
		} );
		assert( rejected.isError && /78px/.test( text( rejected ) ), `write-page accepted or hid a literal padding: ${ text( rejected ) }` );

		console.log( `MCP smoke OK — ${ names.length } tools, page ${ pageId } written and checked, violation reported.` );
	} finally {
		if ( pageId ) {
			wp( 'post', 'delete', String( pageId ), '--force' );
		}
		const uuids = wp( 'user', 'application-password', 'list', 'admin', '--name=mcp-smoke', '--field=uuid' );
		for ( const uuid of uuids.split( '\n' ).filter( Boolean ) ) {
			wp( 'user', 'application-password', 'delete', 'admin', uuid );
		}
	}
}

main().catch( ( error ) => {
	console.error( `MCP smoke FAILED: ${ error.message }` );
	process.exit( 1 );
} );
