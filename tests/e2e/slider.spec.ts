import { test, expect, type Page } from '@playwright/test';
import { createPageWithContent, deletePageBySlug } from './utils';

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

test.describe( 'image/content slider', () => {
	test.beforeAll( () => {
		deletePageBySlug( SLUG );
		deletePageBySlug( SLUG + '-color' );
	} );
	test.afterAll( () => {
		deletePageBySlug( SLUG );
		deletePageBySlug( SLUG + '-color' );
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
		const markup = sliderMarkup(
			[ slideObj( 1 ), slideObj( 2 ) ],
			{ mediaPosition: 'right', panelColor: '#0E7490' }
		);
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
} );
