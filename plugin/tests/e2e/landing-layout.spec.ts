import { test, expect } from '@playwright/test';

test.describe( 'landing layout (1440×900)', () => {
	test.use( { viewport: { width: 1440, height: 900 } } );

	test( 'cta: panel is alignwide and its box stays within the wide size', async ( {
		page,
	} ) => {
		await page.goto( '/' );
		const cta = page.locator( '.wp-block-group.is-style-panel' ).first();
		await cta.scrollIntoViewIfNeeded();
		const { box, maxWidth } = await cta.evaluate( ( el ) => {
			const r = el.getBoundingClientRect();
			return {
				box: { w: Math.round( r.width ) },
				maxWidth: window.getComputedStyle( el ).maxWidth,
			};
		} );
		// alignwide → max-width: 1200px (wide-size). With border-box, the outer box
		// is exactly that. Without border-box, padding-inline would swell it.
		expect( maxWidth ).toBe( '1200px' );
		expect( box.w ).toBeLessThanOrEqual( 1200 );
	} );

	test( 'testimonial: grid is alignwide and shows two quote cards', async ( {
		page,
	} ) => {
		await page.goto( '/' );
		const cards = page.locator( '.wp-block-group.is-style-quote-card' );
		await expect( cards ).toHaveCount( 2 );
		const grid = cards.first().locator( 'xpath=..' );
		await grid.scrollIntoViewIfNeeded();
		const gw = await grid.evaluate( ( el ) =>
			Math.round( el.getBoundingClientRect().width )
		);
		// alignwide → ≤ wide-size (1200px).
		expect( gw ).toBeLessThanOrEqual( 1200 );
		// 2-up grid: each card is narrower than the whole grid.
		const c0 = await cards
			.nth( 0 )
			.evaluate( ( el ) =>
				Math.round( el.getBoundingClientRect().width )
			);
		expect( c0 ).toBeLessThan( gw );
	} );

	test( 'services: section head and feature grid share the same left edge', async ( {
		page,
	} ) => {
		await page.goto( '/' );
		// Services band is the 2nd starter-band (index 1) in pediment-landing.
		const band = page.locator( '.entry-content > .starter-band' ).nth( 1 );
		await band.scrollIntoViewIfNeeded();
		const [ head, grid ] = await band.locator( '> .alignwide' ).all();
		const headX = await head.evaluate( ( el ) =>
			Math.round( el.getBoundingClientRect().left )
		);
		const gridX = await grid.evaluate( ( el ) =>
			Math.round( el.getBoundingClientRect().left )
		);
		// Head and grid sit on the same alignwide left edge. Allow 1px rounding slack.
		expect( Math.abs( headX - gridX ) ).toBeLessThanOrEqual( 1 );
		// The grid holds the three card groups.
		await expect(
			grid.locator( '> .wp-block-group.is-style-card' )
		).toHaveCount( 3 );
	} );

	test( 'insights: section head is centered', async ( { page } ) => {
		await page.goto( '/' );
		// Insights is the last starter-band on the landing page.
		const insightsBand = page
			.locator( '.entry-content > .starter-band' )
			.last();
		await insightsBand.scrollIntoViewIfNeeded();
		const head = insightsBand.locator( '> .alignwide' ).first();
		await expect( head ).toBeVisible();
		const heading = head.locator( 'h2' );
		await expect( heading ).toHaveClass( /has-text-align-center/ );
		const { textAlign, boxLeft, trailing } = await heading.evaluate(
			( el ) => {
				const parent = (
					el.parentElement as HTMLElement
				 ).getBoundingClientRect();
				const r = el.getBoundingClientRect();
				return {
					textAlign: window.getComputedStyle( el ).textAlign,
					boxLeft: Math.round( r.left - parent.left ),
					trailing: Math.round( parent.right - r.right ),
				};
			}
		);
		expect( textAlign ).toBe( 'center' );
		// Constrained layout centres the heading: symmetric leading/trailing space.
		expect( Math.abs( boxLeft - trailing ) ).toBeLessThanOrEqual( 2 );
	} );
} );
