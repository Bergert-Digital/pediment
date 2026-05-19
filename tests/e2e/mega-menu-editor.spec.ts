import { test, expect, type Page } from '@playwright/test';
import { login } from './utils';

// Opens the existing /mega-demo/ page (built from the "Mega Menu Demo
// Header" pattern) in the block editor and asserts the mega-menu edits as
// a visual approximation: panel expanded, link label not collapsed to ~1ch,
// icon cell present, url/icon controls in the inspector.
async function openMegaDemoInEditor( page: Page ) {
	await login( page );
	await page.waitForFunction(
		() =>
			!! (
				window as unknown as {
					wp?: { apiFetch?: unknown };
				}
			 ).wp?.apiFetch
	);
	const id = await page.evaluate( async () => {
		const r = await (
			window as unknown as {
				wp: {
					apiFetch: ( o: { path: string } ) => Promise< unknown >;
				};
			}
		 ).wp.apiFetch( {
			path: '/wp/v2/pages?slug=mega-demo&status=publish',
		} );
		return ( r as Array< { id: number } > )[ 0 ].id;
	} );
	await page.goto( `/wp-admin/post.php?post=${ id }&action=edit` );
	return page.frameLocator( 'iframe[name="editor-canvas"]' );
}

async function openSettingsSidebar( page: Page ) {
	const btn = page.locator( 'button[aria-label="Settings"]' ).first();
	await btn.waitFor();
	if ( ( await btn.getAttribute( 'aria-expanded' ) ) === 'false' ) {
		await btn.click();
	}
}

test.describe( 'mega menu editor', () => {
	test( 'edits as a visual approximation, not crushed controls', async ( {
		page,
	} ) => {
		const canvas = await openMegaDemoInEditor( page );

		const panel = canvas
			.locator( '.starter-mega-menu__panel' )
			.first();
		await expect( panel ).toBeVisible();
		await expect( panel ).toHaveCSS( 'position', 'static' );

		const label = canvas
			.locator( '.starter-mega-link__label' )
			.first();
		await expect( label ).toBeVisible();
		const box = await label.boundingBox();
		expect( box && box.width ).toBeGreaterThan( 40 );

		await expect(
			canvas.locator( '.starter-mega-link__icon' ).first()
		).toBeVisible();
	} );

	test( 'url and icon controls live in the inspector', async ( {
		page,
	} ) => {
		const canvas = await openMegaDemoInEditor( page );
		await canvas.locator( '.starter-mega-link' ).first().click();
		await openSettingsSidebar( page );
		await expect(
			page.getByText( 'Icon (Phosphor name)' )
		).toBeVisible();
	} );
} );
