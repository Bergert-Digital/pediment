import { test, expect, type Page } from '@playwright/test';
import { createPageWithContent, deletePageBySlug } from './utils';

const SLUG = 'e2e-slider';

const SLIDE = ( n: number ) =>
	`<!-- wp:pediment/slide --><!-- wp:heading --><h2>Slide ${ n }</h2><!-- /wp:heading --><!-- wp:paragraph --><p>Body ${ n }</p><!-- /wp:paragraph --><!-- /wp:pediment/slide -->`;

const MARKUP = `<!-- wp:pediment/slider -->${ SLIDE( 1 ) }${ SLIDE( 2 ) }${ SLIDE( 3 ) }<!-- /wp:pediment/slider -->`;

const slider = ( page: Page ) => page.locator( '.starter-slider' );
const activeHeading = ( page: Page ) =>
	page.locator( '.starter-slide.is-active h2' );

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
		const markup = `<!-- wp:pediment/slider {"mediaPosition":"right","panelColor":"#0E7490"} -->${ SLIDE(
			1
		) }${ SLIDE( 2 ) }<!-- /wp:pediment/slider -->`;
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
	} );
} );
