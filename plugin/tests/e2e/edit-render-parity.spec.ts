import { test, expect, Page } from '@playwright/test';
import { execSync } from 'node:child_process';
import { login } from './utils';

/**
 * Edit/render parity contract.
 *
 * The home page is built from core blocks and named block styles. The editor
 * canvas and the front end must both show the same named styles (is-style-*
 * classes) and bands, or a style registration or a block's saved markup has
 * drifted between the two (see docs/WORDPRESS_TRAPS.md).
 *
 * This spec pins the contract: for every named style the landing pattern uses,
 * a curated selector must appear in BOTH the editor canvas iframe AND the
 * rendered front-end HTML. A failure means the editor and the front end
 * disagree on that style — fix whichever is wrong.
 */

type BlockContract = {
	/** Display name for test reporting. */
	name: string;
	/** Selectors that should be present in both editor + front-end. */
	selectors: string[];
};

const CONTRACTS: BlockContract[] = [
	{
		name: 'hero (columns + media-card cover, eyebrow, lead, pill buttons)',
		selectors: [
			'.wp-block-columns',
			'.wp-block-cover.is-style-media-card',
			'.is-style-eyebrow',
			'.is-style-lead',
			'.wp-block-button.is-style-pill',
			'.wp-block-button.is-style-pill-ghost',
		],
	},
	{
		name: 'features (card groups in a grid)',
		selectors: [ '.wp-block-group.is-style-card' ],
	},
	{
		name: 'steps',
		selectors: [ '.wp-block-group.is-style-steps' ],
	},
	{
		name: 'stats',
		selectors: [ '.wp-block-group.is-style-stat' ],
	},
	{
		name: 'testimonials',
		selectors: [ '.wp-block-group.is-style-quote-card' ],
	},
	{
		name: 'faq (details accordion)',
		selectors: [ '.wp-block-details.is-style-accordion' ],
	},
	{
		name: 'call to action (panel)',
		selectors: [ '.wp-block-group.is-style-panel' ],
	},
	{
		name: 'bands',
		selectors: [
			'.wp-block-group.starter-band.is-style-band-surface',
			'.wp-block-group.starter-band.is-style-band-elevated',
			'.wp-block-group.starter-band.is-style-band-navy',
		],
	},
];

function homePageId(): number {
	const out = execSync(
		'npx wp-env run cli wp post list --post_type=page --name=home --field=ID 2>/dev/null',
		{ encoding: 'utf8' }
	);
	const id = out
		.split( '\n' )
		.map( ( line ) => line.trim() )
		.find( ( line ) => /^\d+$/.test( line ) );
	if ( ! id ) {
		throw new Error( 'Could not resolve home page ID via wp-cli' );
	}
	return Number( id );
}

async function collectEditorSelectors(
	page: Page,
	postId: number,
	selectors: string[]
): Promise< Record< string, boolean > > {
	await page.goto( `/wp-admin/post.php?post=${ postId }&action=edit`, {
		waitUntil: 'domcontentloaded',
	} );
	// Editor iframe + block hydration settle within ~5s on a warm server;
	// add buffer for slower CI.
	await page.waitForFunction(
		() => {
			const f = document.querySelector(
				'iframe[name="editor-canvas"]'
			) as HTMLIFrameElement | null;
			return !! (
				f &&
				f.contentDocument &&
				f.contentDocument.body &&
				f.contentDocument.querySelector( '.is-style-media-card' )
			);
		},
		undefined,
		{ timeout: 30_000 }
	);
	return page.evaluate( ( sels ) => {
		const f = document.querySelector(
			'iframe[name="editor-canvas"]'
		) as HTMLIFrameElement | null;
		const doc = f?.contentDocument;
		const out: Record< string, boolean > = {};
		for ( const sel of sels ) {
			out[ sel ] = !! doc?.querySelector( sel );
		}
		return out;
	}, selectors );
}

async function collectFrontSelectors(
	page: Page,
	selectors: string[]
): Promise< Record< string, boolean > > {
	await page.goto( '/', { waitUntil: 'domcontentloaded' } );
	return page.evaluate( ( sels ) => {
		const out: Record< string, boolean > = {};
		for ( const sel of sels ) {
			out[ sel ] = !! document.querySelector( sel );
		}
		return out;
	}, selectors );
}

test.describe( 'edit ↔ render parity', () => {
	test( 'every contracted style selector is present in both the editor and the front-end', async ( {
		page,
	} ) => {
		await login( page );
		const postId = homePageId();
		const allSelectors = Array.from(
			new Set( CONTRACTS.flatMap( ( c ) => c.selectors ) )
		);

		const editorPresent = await collectEditorSelectors(
			page,
			postId,
			allSelectors
		);
		const frontPresent = await collectFrontSelectors( page, allSelectors );

		const failures: string[] = [];
		for ( const contract of CONTRACTS ) {
			for ( const sel of contract.selectors ) {
				const inEditor = editorPresent[ sel ];
				const inFront = frontPresent[ sel ];
				if ( inEditor && inFront ) continue;
				const missing: string[] = [];
				if ( ! inEditor ) missing.push( 'editor' );
				if ( ! inFront ) missing.push( 'front-end' );
				failures.push(
					`[${
						contract.name
					}] "${ sel }" missing in: ${ missing.join( ' + ' ) }`
				);
			}
		}

		if ( failures.length > 0 ) {
			throw new Error(
				`Edit/render parity broken (${ failures.length } selectors):\n  - ` +
					failures.join( '\n  - ' )
			);
		}
	} );
} );
