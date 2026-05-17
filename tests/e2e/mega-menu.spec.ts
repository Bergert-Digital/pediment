import { test, expect } from '@playwright/test';

// Assumes a page at /mega-demo/ built from the "Mega Menu Demo Header" pattern.
test.describe( 'mega menu', () => {
	test( 'opens on hover and closes on Escape, returning focus to trigger', async ( {
		page,
	} ) => {
		await page.goto( '/mega-demo/' );
		const trigger = page.getByRole( 'button', { name: 'Products' } );
		const panel = page.locator( '.starter-mega-menu__panel' ).first();
		await expect( panel ).toBeHidden();
		await trigger.hover();
		await expect( panel ).toBeVisible();
		await expect( trigger ).toHaveAttribute( 'aria-expanded', 'true' );
		await page.keyboard.press( 'Escape' );
		await expect( panel ).toBeHidden();
		await expect( trigger ).toBeFocused();
	} );

	test( 'opens on keyboard focus and closes on click-outside', async ( {
		page,
	} ) => {
		await page.goto( '/mega-demo/' );
		const trigger = page.getByRole( 'button', { name: 'Products' } );
		const panel = page.locator( '.starter-mega-menu__panel' ).first();
		await trigger.focus();
		await expect( panel ).toBeVisible();
		await page.mouse.click( 5, 5 );
		await expect( panel ).toBeHidden();
	} );

	test( 'mobile overlay: trigger expands the columns inline (accordion)', async ( {
		page,
	} ) => {
		await page.setViewportSize( { width: 375, height: 800 } );
		await page.goto( '/mega-demo/' );
		await page
			.locator( '.wp-block-navigation__responsive-container-open' )
			.first()
			.click();
		const trigger = page.getByRole( 'button', { name: 'Products' } );
		const panel = page.locator( '.starter-mega-menu__panel' ).first();
		await expect( panel ).toBeHidden();
		await trigger.click();
		await expect( panel ).toBeVisible();
	} );
} );
