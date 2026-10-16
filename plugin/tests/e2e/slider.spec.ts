import { test, expect, type Page } from '@playwright/test';
import { createPageWithContent, deletePageBySlug, login } from './utils';

const SLUG = 'e2e-slider';

const slideObj = ( n: number ) => ( {
	heading: `Slide ${ n }`,
	body: `Body ${ n }`,
} );

const sliderMarkup = (
	slides: Array< Record< string, unknown > >,
	attrs: Record< string, unknown > = {}
) => `<!-- wp:pediment/slider ${ JSON.stringify( { ...attrs, slides } ) } /-->`;

const MARKUP = sliderMarkup( [ slideObj( 1 ), slideObj( 2 ), slideObj( 3 ) ] );

const slider = ( page: Page ) => page.locator( '.starter-slider' );
const activeHeading = ( page: Page ) =>
	page.locator( '.starter-slide.is-active h2' );

// Left edges of the active slide's image column and panel — used to assert the
// image-side toggle actually flips the layout (not just sets a class).
async function activeMediaPanelX(
	page: Page
): Promise< { mediaX: number; panelX: number } > {
	const media = await page
		.locator( '.starter-slide.is-active .starter-slide__media' )
		.first()
		.boundingBox();
	const panel = await page
		.locator( '.starter-slide.is-active .starter-slide__panel' )
		.first()
		.boundingBox();
	if ( ! media || ! panel ) {
		throw new Error( 'active slide media/panel not found' );
	}
	return { mediaX: media.x, panelX: panel.x };
}

// A light palette slug, a dark palette slug, a hex, and an unknown value
// (the default panel): the editor preview
// must paint each panel exactly like the front end does.
const PARITY_COLORS = [ 'accent-tint', 'primary', '#0E7490', 'no-such-colour' ];
const paritySlug = ( value: string ) =>
	`${ SLUG }-parity-${ value.replace( /[^a-z0-9]/gi, '' ).toLowerCase() }`;

type PanelLook = { bg: string; fg: string };

async function frontPanelLook( page: Page, url: string ): Promise< PanelLook > {
	await page.goto( url );
	const panel = page
		.locator( '.starter-slide.is-active .starter-slide__panel' )
		.first();
	await panel.waitFor();
	return panel.evaluate( ( el ) => {
		const style = getComputedStyle( el );
		return { bg: style.backgroundColor, fg: style.color };
	} );
}

async function editorPanelLook(
	page: Page,
	url: string
): Promise< PanelLook > {
	const postId = new URL( url, 'http://localhost' ).searchParams.get(
		'page_id'
	);
	await page.goto( `/wp-admin/post.php?post=${ postId }&action=edit`, {
		waitUntil: 'domcontentloaded',
	} );
	const handle = await page.waitForFunction(
		() => {
			const f = document.querySelector(
				'iframe[name="editor-canvas"]'
			) as HTMLIFrameElement | null;
			const el = f?.contentDocument?.querySelector(
				'.starter-slider .starter-slide__panel'
			);
			if ( ! el ) {
				return null;
			}
			const style = f!.contentWindow!.getComputedStyle( el );
			return { bg: style.backgroundColor, fg: style.color };
		},
		undefined,
		{ timeout: 30_000 }
	);
	return ( await handle.jsonValue() ) as PanelLook;
}

test.describe( 'image/content slider', () => {
	test.beforeAll( () => {
		deletePageBySlug( SLUG );
		deletePageBySlug( SLUG + '-color' );
		for ( const value of [ ...PARITY_COLORS, 'picker' ] ) {
			deletePageBySlug( paritySlug( value ) );
		}
	} );
	test.afterAll( () => {
		deletePageBySlug( SLUG );
		deletePageBySlug( SLUG + '-color' );
		for ( const value of [ ...PARITY_COLORS, 'picker' ] ) {
			deletePageBySlug( paritySlug( value ) );
		}
	} );

	test( 'enhances, shows first slide, and next/prev wrap around', async ( {
		page,
	} ) => {
		const url = createPageWithContent( SLUG, 'Slider', MARKUP );
		await page.goto( url );

		await expect( slider( page ) ).toHaveClass( /is-enhanced/ );
		await expect( activeHeading( page ) ).toHaveText( 'Slide 1' );

		// Default (is-media-left): image column sits to the left of the panel.
		const { mediaX, panelX } = await activeMediaPanelX( page );
		expect( mediaX ).toBeLessThan( panelX );

		await slider( page ).locator( '.starter-slider__arrow--next' ).click();
		await expect( activeHeading( page ) ).toHaveText( 'Slide 2' );

		// Wrap forward: 3 -> 1
		await slider( page ).locator( '.starter-slider__arrow--next' ).click();
		await expect( activeHeading( page ) ).toHaveText( 'Slide 3' );
		await slider( page ).locator( '.starter-slider__arrow--next' ).click();
		await expect( activeHeading( page ) ).toHaveText( 'Slide 1' );

		// Wrap backward: 1 -> 3
		await slider( page ).locator( '.starter-slider__arrow--prev' ).click();
		await expect( activeHeading( page ) ).toHaveText( 'Slide 3' );
	} );

	test( 'dots jump to a slide and reflect the current one', async ( {
		page,
	} ) => {
		const url = createPageWithContent( SLUG, 'Slider', MARKUP );
		await page.goto( url );

		const dots = slider( page ).locator( '.starter-slider__dot' );
		await expect( dots ).toHaveCount( 3 );

		await dots.nth( 2 ).click();
		await expect( activeHeading( page ) ).toHaveText( 'Slide 3' );
		await expect( dots.nth( 2 ) ).toHaveClass( /is-current/ );
		await expect( dots.nth( 2 ) ).toHaveAttribute( 'aria-current', 'true' );
	} );

	test( 'arrow keys navigate when focus is in the slider', async ( {
		page,
	} ) => {
		const url = createPageWithContent( SLUG, 'Slider', MARKUP );
		await page.goto( url );

		await slider( page ).locator( '.starter-slider__arrow--next' ).focus();
		await page.keyboard.press( 'ArrowRight' );
		await expect( activeHeading( page ) ).toHaveText( 'Slide 2' );
		await page.keyboard.press( 'ArrowLeft' );
		await expect( activeHeading( page ) ).toHaveText( 'Slide 1' );
	} );

	test( 'panel color and image side apply on the front end', async ( {
		page,
	} ) => {
		const markup = sliderMarkup( [ slideObj( 1 ), slideObj( 2 ) ], {
			mediaPosition: 'right',
			panelColor: '#0E7490',
		} );
		const url = createPageWithContent( SLUG + '-color', 'Slider', markup );
		await page.goto( url );

		await expect( slider( page ) ).toHaveClass( /is-media-right/ );
		const panel = page
			.locator( '.starter-slide.is-active .starter-slide__panel' )
			.first();
		// --slide-panel-bg cascades to the panel background.
		await expect( panel ).toHaveCSS(
			'background-color',
			'rgb(14, 116, 144)'
		);

		// is-media-right must actually flip the layout: the image column sits to
		// the RIGHT of the panel (regression guard — a class alone is not enough).
		const { mediaX, panelX } = await activeMediaPanelX( page );
		expect( mediaX ).toBeGreaterThan( panelX );
	} );

	for ( const value of PARITY_COLORS ) {
		test( `panel colour "${ value }" looks the same in the editor and on the front end`, async ( {
			page,
		} ) => {
			const url = createPageWithContent(
				paritySlug( value ),
				'Slider parity',
				sliderMarkup( [ slideObj( 1 ) ], { panelColor: value } )
			);

			const front = await frontPanelLook( page, url );
			await login( page );
			const editor = await editorPanelLook( page, url );

			// A slug that did not resolve would leave the panel transparent.
			expect( front.bg ).not.toBe( 'rgba(0, 0, 0, 0)' );
			expect( editor ).toEqual( front );
		} );
	}

	test( 'picking a palette swatch stores its slug', async ( { page } ) => {
		const url = createPageWithContent(
			paritySlug( 'picker' ),
			'Slider picker',
			sliderMarkup( [ slideObj( 1 ) ] )
		);
		const postId = new URL( url, 'http://localhost' ).searchParams.get(
			'page_id'
		);
		await login( page );
		await page.goto( `/wp-admin/post.php?post=${ postId }&action=edit` );
		await page.waitForFunction(
			() =>
				( window as any ).wp?.data
					?.select( 'core/block-editor' )
					?.getBlocks()
					?.some( ( b: any ) => b.name === 'pediment/slider' ),
			undefined,
			{ timeout: 30_000 }
		);
		const panelColor = () =>
			page.evaluate(
				() =>
					( window as any ).wp.data
						.select( 'core/block-editor' )
						.getBlocks()
						.find( ( b: any ) => b.name === 'pediment/slider' )
						.attributes.panelColor
			);
		await page.evaluate( () => {
			const { select, dispatch } = ( window as any ).wp.data;
			const block = select( 'core/block-editor' )
				.getBlocks()
				.find( ( b: any ) => b.name === 'pediment/slider' );
			dispatch( 'core/block-editor' ).selectBlock( block.clientId );
			dispatch( 'core/preferences' )?.set(
				'core',
				'welcomeGuide',
				false
			);
		} );

		await page.getByRole( 'tab', { name: 'Styles' } ).click();
		await page.getByRole( 'button', { name: /Panel background/ } ).click();
		await page
			.getByRole( 'option', { name: /^Accent tint$/ } )
			.or( page.getByRole( 'button', { name: /^Accent tint$/ } ) )
			.first()
			.click();
		await expect.poll( panelColor ).toBe( 'accent-tint' );

		// Pediment's tokens turn custom colours off (color.custom: false), so
		// the picker offers palette swatches only; that a custom colour stays
		// hex is covered by storedPanelColor() in panel-fg.test.ts.
		await page
			.getByRole( 'option', { name: /^Primary$/ } )
			.or( page.getByRole( 'button', { name: /^Primary$/ } ) )
			.first()
			.click();
		await expect.poll( panelColor ).toBe( 'primary' );
	} );
} );
