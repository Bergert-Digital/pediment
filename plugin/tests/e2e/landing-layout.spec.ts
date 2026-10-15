import { test, expect, Locator } from '@playwright/test';

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

	test( 'services: section head text and feature cards share the same left edge', async ( {
		page,
	} ) => {
		await page.goto( '/' );
		// Services band is the 2nd starter-band (index 1) in pediment-landing.
		const band = page.locator( '.entry-content > .starter-band' ).nth( 1 );
		await band.scrollIntoViewIfNeeded();
		const wide = band.locator( '> .alignwide' );
		await expect( wide ).toHaveCount( 2 );
		const left = ( locator: Locator ) =>
			locator.evaluate( ( el ) =>
				Math.round( el.getBoundingClientRect().left )
			);
		// The visible head text (eyebrow, then headline) starts where the cards
		// start, not at the 720px centred measure of the head group.
		const headText = wide.nth( 0 ).locator( '> *' );
		const gridLeft = await left( wide.nth( 1 ).locator( '> *' ).first() );
		for ( let i = 0; i < ( await headText.count() ); i++ ) {
			expect(
				Math.abs( ( await left( headText.nth( i ) ) ) - gridLeft )
			).toBeLessThanOrEqual( 1 );
		}
		// The grid holds the three card groups.
		await expect(
			wide.nth( 1 ).locator( '> .wp-block-group.is-style-card' )
		).toHaveCount( 3 );
	} );

	test( 'stats: four equal columns span the wide area; figures read on navy', async ( {
		page,
	} ) => {
		await page.goto( '/' );
		const band = page.locator( '.entry-content > .is-style-band-navy' );
		await band.scrollIntoViewIfNeeded();
		const cells = band.locator( '.is-style-stat' );
		await expect( cells ).toHaveCount( 4 );
		const grid = cells.first().locator( 'xpath=..' );
		const [ g, first, last ] = await Promise.all( [
			grid.evaluate( ( el ) => el.getBoundingClientRect().toJSON() ),
			cells
				.first()
				.evaluate( ( el ) => el.getBoundingClientRect().toJSON() ),
			cells
				.last()
				.evaluate( ( el ) => el.getBoundingClientRect().toJSON() ),
		] );
		expect( Math.abs( first.left - g.left ) ).toBeLessThanOrEqual( 1 );
		expect( Math.abs( last.right - g.right ) ).toBeLessThanOrEqual( 1 );
		const contrast = await cells.first().evaluate( ( el ) => {
			const lum = ( css: string ) => {
				const [ r, g2, b ] = ( css.match( /[\d.]+/g ) as string[] )
					.slice( 0, 3 )
					.map( ( v ) => {
						const c = Number( v ) / 255;
						return c <= 0.03928
							? c / 12.92
							: ( ( c + 0.055 ) / 1.055 ) ** 2.4;
					} );
				return 0.2126 * r + 0.7152 * g2 + 0.0722 * b;
			};
			const bg = lum(
				getComputedStyle(
					el.closest( '.is-style-band-navy' ) as Element
				).backgroundColor
			);
			return Array.from( el.children ).map( ( child ) => {
				const fg = lum( getComputedStyle( child ).color );
				return (
					( Math.max( fg, bg ) + 0.05 ) /
					( Math.min( fg, bg ) + 0.05 )
				);
			} );
		} );
		for ( const ratio of contrast ) {
			expect( ratio ).toBeGreaterThanOrEqual( 4.5 );
		}
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

test.describe( 'landing layout (375×800)', () => {
	test.use( { viewport: { width: 375, height: 800 } } );

	test( 'grids stack on a phone: every card is nearly the full content width', async ( {
		page,
	} ) => {
		await page.goto( '/' );
		const groups = [
			'.is-style-card',
			'.is-style-quote-card',
			'.is-style-stat',
			'.is-style-steps > *',
		];
		for ( const selector of groups ) {
			const cards = page.locator( `.entry-content ${ selector }` );
			expect( await cards.count(), selector ).toBeGreaterThan( 1 );
			const widths = await cards.evaluateAll( ( els ) =>
				els.map( ( el ) => {
					const parent = (
						el.parentElement as HTMLElement
					 ).getBoundingClientRect().width;
					return el.getBoundingClientRect().width / parent;
				} )
			);
			for ( const share of widths ) {
				expect( share, selector ).toBeGreaterThan( 0.8 );
			}
		}
		// No sideways scroll.
		const overflow = await page.evaluate(
			() =>
				document.documentElement.scrollWidth -
				document.documentElement.clientWidth
		);
		expect( overflow ).toBeLessThanOrEqual( 0 );
	} );
} );
