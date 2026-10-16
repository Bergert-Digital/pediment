import { test, expect, type Page } from '@playwright/test';
import { execSync } from 'node:child_process';
import { login } from './utils';

/**
 * Plan 4a: legacy blocks are hidden from the inserter, and a page converted
 * by the pediment/convert-legacy ability opens in the block editor with zero
 * invalid blocks. The abilities run over the Abilities REST route, the same
 * callbacks the MCP server calls. convert-legacy is destructive and
 * idempotent, so core's route wants DELETE with the input in the query.
 */

const WP_ENV_CWD = process.env.WP_ENV_CWD || process.cwd();

// Legacy markup in the shapes the client sites use (see the converter fixtures).
const LEGACY = [
	'<!-- wp:pediment/hero {"variant":"centered","headline":"About","subheadline":"Who we are."} /-->',
	'<!-- wp:pediment/section-head {"alignment":"start","eyebrow":"How we work","headline":"Steps"} /-->',
	'<!-- wp:pediment/steps -->\n<!-- wp:pediment/step {"title":"One","text":"First."} /-->\n<!-- wp:pediment/step {"title":"Two","text":"Second."} /-->\n<!-- /wp:pediment/steps -->',
	'<!-- wp:pediment/faq -->\n<!-- wp:pediment/faq-item {"question":"Parking?","answer":"Yes, at the gate."} /-->\n<!-- /wp:pediment/faq -->',
	'<!-- wp:pediment/cta {"title":"Ready?","primaryText":"Contact","primaryUrl":"/contact"} /-->',
].join( '\n\n' );

const ability = ( name: string ) =>
	`/?rest_route=/wp-abilities/v1/abilities/${ name }/run`;

async function restNonce( page: Page ): Promise< string > {
	const response = await page.request.get(
		'/wp-admin/admin-ajax.php?action=rest-nonce'
	);
	expect( response.ok() ).toBe( true );
	return ( await response.text() ).trim();
}

test( 'legacy blocks are hidden from the inserter', async ( { page } ) => {
	await login( page );
	await page.goto( '/wp-admin/post-new.php?post_type=page' );
	await page.waitForFunction(
		() => !! ( window as any ).wp?.blocks?.getBlockType?.( 'pediment/hero' )
	);
	const inserter = await page.evaluate( () =>
		[ 'pediment/hero', 'pediment/faq', 'pediment/prose', 'pediment/form' ].map(
			( name ) =>
				( window as any ).wp.blocks.getBlockType( name )?.supports?.inserter
		)
	);
	expect( inserter.slice( 0, 3 ) ).toEqual( [ false, false, false ] );
	expect( inserter[ 3 ] ).not.toBe( false );
} );

test( 'a converted page opens with zero invalid blocks', async ( { page } ) => {
	await login( page );
	const headers = { 'X-WP-Nonce': await restNonce( page ) };
	let id = 0;

	try {
		const written = await page.request.post(
			ability( 'pediment/write-page' ),
			{ headers, data: { input: { title: 'E2E legacy page', markup: LEGACY } } }
		);
		const created = await written.json();
		expect( written.ok(), JSON.stringify( created ) ).toBe( true );
		id = created.id;

		const converted = await page.request.delete(
			`${ ability( 'pediment/convert-legacy' ) }&input[ids][0]=${ id }&input[dry_run]=false`,
			{ headers }
		);
		const report = await converted.json();
		expect( converted.ok(), JSON.stringify( report ) ).toBe( true );
		expect( report.posts[ 0 ].status ).toBe( 'converted' );

		const posted = page.waitForRequest(
			( request ) =>
				request.method() === 'POST' &&
				decodeURIComponent( request.url() ).includes(
					`/pediment/v1/invalid-blocks/${ id }`
				)
		);
		await page.goto( `/wp-admin/post.php?post=${ id }&action=edit` );
		expect( ( await posted ).postDataJSON() ).toEqual( { blocks: [] } );

		const counts = await page.evaluate( () => {
			const wp = ( window as any ).wp;
			let total = 0;
			let invalid = 0;
			let legacy = 0;
			const walk = ( blocks: any[] ) =>
				blocks.forEach( ( block ) => {
					total++;
					invalid += block.isValid === false ? 1 : 0;
					legacy += block.name.startsWith( 'pediment/' ) ? 1 : 0;
					walk( block.innerBlocks || [] );
				} );
			walk( wp.data.select( 'core/block-editor' ).getBlocks() );
			return { total, invalid, legacy };
		} );
		expect( counts.invalid ).toBe( 0 );
		expect( counts.legacy ).toBe( 0 );
		expect( counts.total ).toBeGreaterThan( 10 );
	} finally {
		if ( id ) {
			execSync( `npx wp-env run cli wp post delete ${ id } --force`, {
				stdio: 'ignore',
				cwd: WP_ENV_CWD,
			} );
		}
	}
} );
