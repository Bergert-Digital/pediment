import { test, expect, Page } from '@playwright/test';
import { execSync } from 'node:child_process';
import { login } from './utils';

/**
 * Edit/render parity contract.
 *
 * Every server-rendered block has two independent code paths — render.php for the
 * front-end, edit.tsx for the block-editor canvas. WordPress provides no auto-
 * derivation between them, so the DOM trees drift the moment one side changes
 * without the other (see docs/WORDPRESS_TRAPS.md).
 *
 * This spec pins the contract: for every block we ship on the home page, a
 * curated list of BEM selectors must appear in BOTH the editor canvas iframe
 * AND the rendered front-end HTML. A test failure means edit.tsx and render.php
 * have diverged on that block — fix whichever is wrong.
 */

type BlockContract = {
	/** Display name for test reporting. */
	name: string;
	/** Selectors that should be present in both editor + front-end. */
	selectors: string[];
};

const CONTRACTS: BlockContract[] = [
	{
		name: 'pediment/hero (stat-card variant)',
		selectors: [
			'.starter-hero',
			'.starter-hero__col',
			'.starter-hero__eyebrow',
			'.starter-hero__headline',
			'.starter-hero__subheadline',
			'.starter-hero__fig',
			'.starter-hero__glass',
			'.starter-hero__stat-value',
			'.starter-hero__stat-text',
			'.starter-hero__metrics',
			'.starter-hero__metric',
		],
	},
	{
		name: 'pediment/section-head',
		selectors: [
			'.starter-section-head',
			'.starter-section-head__inner',
			'.starter-section-head__eyebrow',
			'.starter-section-head__headline',
		],
	},
	{
		name: 'pediment/feature-grid + pediment/feature',
		selectors: [
			'.starter-feature-grid',
			'.starter-feature',
			'.starter-feature__ic',
			'.starter-feature__title',
			'.starter-feature__text',
			'.starter-feature__more',
		],
	},
	{
		name: 'pediment/steps + pediment/step',
		selectors: [
			'.starter-steps',
			'.starter-step',
			'.starter-step__num',
			'.starter-step__title',
			'.starter-step__text',
		],
	},
	{
		name: 'pediment/cta',
		selectors: [
			'.starter-cta',
			'.starter-cta__title',
			'.starter-cta__body',
			'.starter-cta__actions',
			'.starter-cta__btn',
		],
	},
	{
		name: 'pediment/pull-quote (testimonial variant)',
		selectors: [
			'.starter-pull-quote',
			'.starter-pull-quote.is-variant-testimonial',
			'.starter-pull-quote__quote',
		],
	},
];

function homePageId(): number {
	const out = execSync(
		"npx wp-env run cli wp post list --post_type=page --name=home --field=ID 2>/dev/null",
		{ encoding: 'utf8' }
	);
	const id = out
		.split('\n')
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
	// Editor iframe + sprite injection settle within ~5s on a warm server;
	// add buffer for slower CI.
	await page.waitForFunction(
		() => {
			const f = document.querySelector(
				'iframe[name="editor-canvas"]'
			) as HTMLIFrameElement | null;
			return !! ( f && f.contentDocument && f.contentDocument.body && f.contentDocument.querySelector( '.starter-hero' ) );
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
	test( 'every contracted block selector is present in both the editor and the front-end', async ( {
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
					`[${ contract.name }] "${ sel }" missing in: ${ missing.join(
						' + '
					) }`
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
