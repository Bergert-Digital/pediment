import { test, expect, type Page } from '@playwright/test';

// /mega-demo/ is built from the "Mega Menu Demo Header" pattern, whose root
// wrapper is `.mega-demo`. The page renders inside the full theme (which has
// its own header Navigation block), so every locator is scoped to `.mega-demo`
// to target the fixture's nav, never the theme header.
const root = ( page: Page ) => page.locator( '.mega-demo' );
const trigger = ( page: Page ) =>
	root( page ).getByRole( 'button', { name: 'Products' } );
const panel = ( page: Page ) =>
	root( page ).locator( '.starter-mega-menu__panel' );

test.describe( 'mega menu', () => {
	test( 'opens on hover and closes on Escape, returning focus to trigger', async ( {
		page,
	} ) => {
		await page.goto( '/mega-demo/' );
		await expect( panel( page ) ).toBeHidden();
		await trigger( page ).hover();
		await expect( panel( page ) ).toBeVisible();
		await expect( trigger( page ) ).toHaveAttribute(
			'aria-expanded',
			'true'
		);
		await page.keyboard.press( 'Escape' );
		await expect( panel( page ) ).toBeHidden();
		await expect( trigger( page ) ).toBeFocused();
	} );

	test( 'opens on keyboard focus and closes on click-outside', async ( {
		page,
	} ) => {
		await page.goto( '/mega-demo/' );
		await trigger( page ).focus();
		await expect( panel( page ) ).toBeVisible();
		await page.mouse.click( 5, 5 );
		await expect( panel( page ) ).toBeHidden();
	} );

	test( 'mobile overlay: trigger expands the columns inline (accordion)', async ( {
		page,
	} ) => {
		await page.setViewportSize( { width: 375, height: 800 } );
		await page.goto( '/mega-demo/' );
		await root( page )
			.locator( '.wp-block-navigation__responsive-container-open' )
			.click();
		await expect( panel( page ) ).toBeHidden();
		await trigger( page ).click();
		await expect( panel( page ) ).toBeVisible();
	} );

	test( 'desktop panel is an absolutely-positioned dropdown; mobile is inline', async ( {
		page,
	} ) => {
		await page.goto( '/mega-demo/' );
		await trigger( page ).hover();
		await expect( panel( page ) ).toHaveCSS( 'position', 'absolute' );

		await page.setViewportSize( { width: 375, height: 800 } );
		await page.goto( '/mega-demo/' );
		await root( page )
			.locator( '.wp-block-navigation__responsive-container-open' )
			.click();
		await trigger( page ).click();
		await expect( panel( page ) ).toHaveCSS( 'position', 'static' );
	} );
} );
