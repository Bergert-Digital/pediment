import { test, expect } from '@playwright/test';

test.describe( 'Pediment parts', () => {
	test( 'header renders site logo + navigation', async ( { page } ) => {
		await page.goto( '/' );
		const header = page.locator( 'header.site-header' ).first();
		await expect( header ).toBeVisible();
		await expect(
			header.locator( '.wp-block-site-logo' ).first()
		).toBeVisible();
		await expect(
			header.locator( '.wp-block-navigation' ).first()
		).toBeVisible();
	} );

	test( 'footer renders the seeded footer part', async ( { page } ) => {
		await page.goto( '/' );
		const footer = page.locator( 'footer.wp-block-template-part' ).last();
		await expect( footer ).toBeVisible();
		await expect(
			footer.locator( '.wp-block-site-logo' ).first()
		).toBeVisible();
		await expect( footer.locator( '.wp-block-site-title' ) ).toBeVisible();
		await expect( footer ).not.toContainText( 'example.com' );
	} );
} );
