import { test, expect, type Page } from '@playwright/test';
import { execSync } from 'node:child_process';
import { login } from './utils';

/**
 * Spec §5: a page written through the pediment/write-page ability opens in
 * the block editor with zero invalid blocks, and the editor's invalid-block
 * reporter (assets/js/invalid-blocks.js) posts that result, so check-page
 * shows `invalidBlocks.blocks: []` instead of `invalidBlocks: null`.
 *
 * The ability runs over the Abilities API REST route, the same callbacks the
 * MCP server calls. `?rest_route=` works with plain and pretty permalinks.
 */

const WP_ENV_CWD = process.env.WP_ENV_CWD || process.cwd();

// Checked with client-kit/scripts/validate-blocks.mjs: every block matches save().
const MARKUP = `<!-- wp:group {"metadata":{"name":"Intro"},"className":"is-style-card","layout":{"type":"constrained"}} -->
<div class="wp-block-group is-style-card"><!-- wp:heading -->
<h2 class="wp-block-heading">Written through the ability</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>This page came in through <strong>pediment/write-page</strong>.</p>
<!-- /wp:paragraph -->

<!-- wp:list -->
<ul class="wp-block-list"><!-- wp:list-item -->
<li>One</li>
<!-- /wp:list-item -->

<!-- wp:list-item -->
<li>Two</li>
<!-- /wp:list-item --></ul>
<!-- /wp:list -->

<!-- wp:buttons -->
<div class="wp-block-buttons"><!-- wp:button -->
<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="/contact">Contact us</a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div>
<!-- /wp:group -->`;

const ability = ( name: string ) =>
	`/?rest_route=/wp-abilities/v1/abilities/${ name }/run`;

async function restNonce( page: Page ): Promise< string > {
	const response = await page.request.get(
		'/wp-admin/admin-ajax.php?action=rest-nonce'
	);
	expect( response.ok() ).toBe( true );
	return ( await response.text() ).trim();
}

test( 'a page written through write-page opens with zero invalid blocks', async ( {
	page,
} ) => {
	await login( page );
	const headers = { 'X-WP-Nonce': await restNonce( page ) };
	let id = 0;

	try {
		const written = await page.request.post(
			ability( 'pediment/write-page' ),
			{
				headers,
				data: {
					input: { title: 'E2E ability page', markup: MARKUP },
				},
			}
		);
		const result = await written.json();
		expect( written.ok(), JSON.stringify( result ) ).toBe( true );
		id = result.id;
		expect( id ).toBeGreaterThan( 0 );

		const report = page.waitForRequest(
			( request ) =>
				request.method() === 'POST' &&
				decodeURIComponent( request.url() ).includes(
					`/pediment/v1/invalid-blocks/${ id }`
				)
		);
		await page.goto( `/wp-admin/post.php?post=${ id }&action=edit` );
		const posted = await report;
		expect( posted.postDataJSON() ).toEqual( { blocks: [] } );
		const stored = await posted.response();
		expect( stored?.ok() ).toBe( true );

		const counts = await page.evaluate( () => {
			const wp = ( window as any ).wp;
			let total = 0;
			let invalid = 0;
			const walk = ( blocks: any[] ) =>
				blocks.forEach( ( block ) => {
					total++;
					if ( block.isValid === false ) {
						invalid++;
					}
					walk( block.innerBlocks || [] );
				} );
			walk( wp.data.select( 'core/block-editor' ).getBlocks() );
			return { total, invalid };
		} );
		expect( counts ).toEqual( { total: 8, invalid: 0 } );

		const checked = await page.request.get(
			`${ ability( 'pediment/check-page' ) }&input[id]=${ id }`,
			{ headers }
		);
		const check = await checked.json();
		expect( checked.ok(), JSON.stringify( check ) ).toBe( true );
		expect( check.errors ).toEqual( [] );
		expect( check.invalidBlocks.blocks ).toEqual( [] );
	} finally {
		if ( id ) {
			execSync( `npx wp-env run cli wp post delete ${ id } --force`, {
				stdio: 'ignore',
				cwd: WP_ENV_CWD,
			} );
		}
	}
} );
