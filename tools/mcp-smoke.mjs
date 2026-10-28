#!/usr/bin/env node
/**
 * MCP smoke test. Talks to the Pediment MCP server of the running root
 * wp-env exactly like Claude Code does (JSON-RPC over HTTP, Basic auth with
 * an application password) and checks: the tool list, get-vocabulary, a
 * write-page → check-page round trip, a blog post written and read through
 * post_type, that write-page rejects a legacy block,
 * that check-page reports a stored legacy page, and that a write
 * breaking two rules comes back as one tool error naming both. Removes every
 * post and the application password it created, even when a step fails.
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
	const pageIds = [];

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
		const raw = await response.text();
		let json;
		try {
			json = JSON.parse( raw );
		} catch {
			throw new Error( `${ method }: HTTP ${ response.status } did not return JSON: ${ raw.slice( 0, 300 ) }` );
		}
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
		const types = vocabulary.structuredContent.postTypes.map( ( t ) => t.slug );
		assert( types.includes( 'page' ) && types.includes( 'post' ), `get-vocabulary postTypes lacks page or post: ${ types }` );

		const written = await call( 'pediment-write-page', {
			title: 'MCP smoke',
			markup: '<!-- wp:paragraph -->\n<p>Hello from MCP.</p>\n<!-- /wp:paragraph -->',
		} );
		assert( ! written.isError, `write-page failed: ${ text( written ) }` );
		const pageId = written.structuredContent.id;
		pageIds.push( pageId );

		const checked = await call( 'pediment-check-page', { id: pageId } );
		assert( ! checked.isError && checked.structuredContent.errors.length === 0, `check-page reported errors: ${ text( checked ) }` );

		const post = await call( 'pediment-write-page', {
			post_type: 'post',
			title: 'MCP smoke post',
			markup: '<!-- wp:paragraph -->\n<p>A post from MCP.</p>\n<!-- /wp:paragraph -->',
		} );
		assert( ! post.isError, `write-page with post_type post failed: ${ text( post ) }` );
		const postId = post.structuredContent.id;
		pageIds.push( postId );
		const readPost = await call( 'pediment-read-page', { post_type: 'post', id: postId } );
		assert( ! readPost.isError && readPost.structuredContent.type === 'post', `read-page with post_type post failed: ${ text( readPost ) }` );
		const wrongType = await call( 'pediment-read-page', { id: postId } );
		assert( wrongType.isError && /pass post_type "post"/.test( text( wrongType ) ), `read-page without post_type did not name the type: ${ text( wrongType ) }` );

		// A legacy page: write-page rejects legacy blocks (Pediment 5), so WP-CLI
		// stores it; check-page reports it as an error and in legacyBlocks.
		const legacyMarkup = '<!-- wp:pediment/pull-quote {"quote":"Left over from Pediment 4."} /-->';
		const refused = await call( 'pediment-write-page', { title: 'MCP smoke legacy', markup: legacyMarkup } );
		if ( ! refused.isError && refused.structuredContent?.id ) {
			pageIds.push( refused.structuredContent.id );
		}
		assert( refused.isError && /was removed in Pediment 5/.test( text( refused ) ), `write-page accepted a legacy block: ${ text( refused ) }` );
		const legacyId = Number( wp( 'post', 'create', '--post_type=page', '--post_status=draft', '--post_title=MCP smoke legacy', `--post_content=${ legacyMarkup }`, '--porcelain' ) );
		assert( legacyId > 0, 'wp post create did not return the legacy page id' );
		pageIds.push( legacyId );
		const legacy = await call( 'pediment-check-page', { id: legacyId } );
		assert( ! legacy.isError && legacy.structuredContent.legacyBlocks.join() === 'pediment/pull-quote', `check-page does not list the legacy block: ${ text( legacy ) }` );
		assert( legacy.structuredContent.errors.some( ( e ) => /was removed in Pediment 5; rebuild it/.test( e ) ), `check-page does not report the legacy block as an error: ${ text( legacy ) }` );

		// Two rules broken at once: the error must list both (Policy reports every violation).
		const rejected = await call( 'pediment-write-page', {
			title: 'MCP smoke bad',
			markup:
				'<!-- wp:html -->\n<p>raw</p>\n<!-- /wp:html -->\n\n' +
				'<!-- wp:paragraph {"style":{"typography":{"fontSize":"78px"}}} -->\n<p style="font-size:78px">Big</p>\n<!-- /wp:paragraph -->',
		} );
		if ( ! rejected.isError && rejected.structuredContent?.id ) {
			pageIds.push( rejected.structuredContent.id );
		}
		const why = text( rejected );
		assert( rejected.isError, `write-page accepted core/html and a literal font size: ${ why }` );
		assert( /core\/html: block is not allowed/.test( why ), `write-page error does not name core/html: ${ why }` );
		assert( /fontSize has the literal value "78px"/.test( why ), `write-page error does not name the literal font size: ${ why }` );

		console.log( `MCP smoke OK — ${ names.length } tools, page ${ pageId } written and checked, post ${ postId } written and read, legacy page ${ legacyId } reported, both violations reported.` );
	} finally {
		// Each step on its own, so a failing cleanup neither hides the real
		// error nor skips the steps after it.
		const cleanup = ( label, step ) => {
			try {
				step();
			} catch ( error ) {
				console.error( `MCP smoke cleanup: ${ label } failed: ${ error.message }` );
			}
		};
		for ( const id of pageIds ) {
			cleanup( `delete page ${ id }`, () => wp( 'post', 'delete', String( id ), '--force' ) );
		}
		cleanup( 'delete the application password', () => {
			const uuids = wp( 'user', 'application-password', 'list', 'admin', '--name=mcp-smoke', '--field=uuid' );
			for ( const uuid of uuids.split( '\n' ).filter( Boolean ) ) {
				cleanup( `delete application password ${ uuid }`, () => wp( 'user', 'application-password', 'delete', 'admin', uuid ) );
			}
		} );
	}
}

main().catch( ( error ) => {
	console.error( `MCP smoke FAILED: ${ error.message }` );
	process.exit( 1 );
} );
