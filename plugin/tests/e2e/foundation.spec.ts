import { test, expect } from '@playwright/test';

test.describe( 'Pediment foundation', () => {
	test( 'front page renders its feature cards as core blocks, with no icon sprite', async ( {
		page,
	} ) => {
		await page.goto( '/' );
		// The seeded landing page holds core blocks only. Its feature cards are
		// is-style-card groups; they carry no inline icon (core blocks cannot
		// show the icon set).
		const cards = page.locator( '.wp-block-group.is-style-card' );
		await expect( cards.first() ).toBeVisible();
		// The old sprite approach must be fully gone.
		await expect( page.locator( 'svg symbol[id^="ph-"]' ) ).toHaveCount(
			0
		);
		await expect( page.locator( 'svg use' ) ).toHaveCount( 0 );
	} );

	test( 'converted cards reveal on scroll like the legacy feature cards', async ( {
		page,
	} ) => {
		await page.goto( '/' );
		const card = page.locator( '.wp-block-group.is-style-card' ).first();
		await expect( card ).toHaveAttribute( 'data-reveal', '' );
		await card.scrollIntoViewIfNeeded();
		await expect( card ).toHaveClass( /\bis-in\b/ );
	} );

	test( 'anim gate class is set on <html>', async ( { page } ) => {
		await page.goto( '/' );
		await expect( page.locator( 'html' ) ).toHaveClass( /\banim\b/ );
	} );

	test( 'global stylesheet is loaded', async ( { page } ) => {
		await page.goto( '/' );
		const hrefs = await page
			.locator( 'link[rel="stylesheet"]' )
			.evaluateAll( ( ls ) =>
				ls.map( ( l ) => ( l as HTMLLinkElement ).href )
			);
		expect(
			hrefs.some( ( h ) => h.includes( '/assets/css/theme.css' ) )
		).toBe( true );
	} );

	test( 'reduced-motion users get no reveal transition', async ( {
		browser,
	} ) => {
		const ctx = await browser.newContext( { reducedMotion: 'reduce' } );
		const page = await ctx.newPage();
		await page.goto( '/' );
		// Inject a probe element carrying the reveal contract.
		await page.evaluate( () => {
			const d = document.createElement( 'div' );
			d.setAttribute( 'data-reveal', '' );
			d.id = 'rm-probe';
			document.body.appendChild( d );
		} );
		const probe = page.locator( '#rm-probe' );
		await expect( probe ).toHaveCSS( 'opacity', '1' );
		await ctx.close();
	} );
} );
