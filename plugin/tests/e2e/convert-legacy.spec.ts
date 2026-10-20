import { test, expect, type Page } from '@playwright/test';
import { execSync } from 'node:child_process';
import { createPageWithContent, login } from './utils';

/**
 * Pediment 5 removed the legacy blocks, so the editor no longer registers
 * them, and a page converted by the pediment/convert-legacy ability opens in
 * the block editor with zero invalid blocks. The abilities run over the
 * Abilities REST route, the same callbacks the MCP server calls.
 * convert-legacy is destructive and idempotent, so core's route wants DELETE
 * with the input in the query.
 * write-page rejects legacy blocks since Pediment 5, so WP-CLI seeds the
 * legacy page.
 */

const WP_ENV_CWD = process.env.WP_ENV_CWD || process.cwd();

// Legacy markup in the shapes the client sites use (see the converter fixtures).
const LEGACY = [
	'<!-- wp:pediment/hero {"variant":"centered","headline":"About","subheadline":"Who we are."} /-->',
	'<!-- wp:pediment/section-head {"alignment":"start","eyebrow":"How we work","headline":"Steps"} /-->',
	'<!-- wp:pediment/steps -->\n<!-- wp:pediment/step {"title":"One","text":"First."} /-->\n<!-- wp:pediment/step {"title":"Two","text":"Second."} /-->\n<!-- /wp:pediment/steps -->',
	'<!-- wp:pediment/faq -->\n<!-- wp:pediment/faq-item {"question":"Parking?","answer":"Yes, at the gate."} /-->\n<!-- /wp:pediment/faq -->',
	'<!-- wp:pediment/cta {"title":"Ready?","primaryText":"Contact","primaryUrl":"/contact"} /-->',
	'<!-- wp:pediment/feature-grid -->\n<!-- wp:pediment/feature {"icon":"key","title":"Check-in","text":"Codes by email.","linkText":"How","linkUrl":"/check-in/"} /-->\n<!-- wp:pediment/feature {"title":"Growth","text":"Default icon."} /-->\n<!-- /wp:pediment/feature-grid -->',
].join( '\n\n' );

// Functional Pediment blocks the converter writes (not legacy).
const FUNCTIONAL = [ 'pediment/icon' ];

const ability = ( name: string ) =>
	`/?rest_route=/wp-abilities/v1/abilities/${ name }/run`;

async function restNonce( page: Page ): Promise< string > {
	const response = await page.request.get(
		'/wp-admin/admin-ajax.php?action=rest-nonce'
	);
	expect( response.ok() ).toBe( true );
	return ( await response.text() ).trim();
}

test( 'a legacy block is not registered', async ( { page } ) => {
	await login( page );
	await page.goto( '/wp-admin/post-new.php?post_type=page' );
	// Wait on a block that stays registered, never on the removed one.
	await page.waitForFunction(
		() => !! ( window as any ).wp?.blocks?.getBlockType?.( 'pediment/form' )
	);
	const unregistered = await page.evaluate( () =>
		[ 'pediment/hero', 'pediment/faq', 'pediment/prose' ].map(
			( name ) =>
				( window as any ).wp.blocks.getBlockType( name ) === undefined
		)
	);
	expect( unregistered ).toEqual( [ true, true, true ] );
} );

test( 'a converted page opens with zero invalid blocks', async ( { page } ) => {
	await login( page );
	const headers = { 'X-WP-Nonce': await restNonce( page ) };
	let id = 0;

	try {
		const rejected = await page.request.post(
			ability( 'pediment/write-page' ),
			{ headers, data: { input: { title: 'E2E legacy page', markup: LEGACY } } }
		);
		expect( rejected.ok() ).toBe( false );
		expect( await rejected.text() ).toContain(
			'was removed in Pediment 5; convert it with pediment-convert-legacy'
		);

		const url = createPageWithContent(
			`e2e-legacy-${ Date.now() }`,
			'E2E legacy page',
			LEGACY
		);
		id = Number( new URL( url, 'http://localhost' ).searchParams.get( 'page_id' ) );
		expect( id ).toBeGreaterThan( 0 );

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

		const counts = await page.evaluate( ( functional ) => {
			const wp = ( window as any ).wp;
			let total = 0;
			let invalid = 0;
			let legacy = 0;
			let icons = 0;
			const walk = ( blocks: any[] ) =>
				blocks.forEach( ( block ) => {
					total++;
					invalid += block.isValid === false ? 1 : 0;
					legacy +=
						block.name.startsWith( 'pediment/' ) &&
						! functional.includes( block.name )
							? 1
							: 0;
					icons += block.name === 'pediment/icon' ? 1 : 0;
					walk( block.innerBlocks || [] );
				} );
			walk( wp.data.select( 'core/block-editor' ).getBlocks() );
			return { total, invalid, legacy, icons };
		}, FUNCTIONAL );
		expect( counts.invalid ).toBe( 0 );
		expect( counts.legacy ).toBe( 0 );
		expect( counts.icons ).toBe( 2 );
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
