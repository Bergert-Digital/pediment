import { test, expect, type Page } from '@playwright/test';
import { login, createPageWithContent, deletePageBySlug } from './utils';

// Opens the existing /mega-demo/ page (built from the "Mega Menu Demo
// Header" pattern) in the block editor and asserts the mega-menu edits as
// a visual approximation: panel expanded, link label not collapsed to ~1ch,
// icon cell present, url/icon controls in the inspector.
async function openMegaDemoInEditor( page: Page ) {
	await login( page );
	// Reach the page editor via the front-end admin-bar "Edit Page" link.
	// (wp.apiFetch is not enqueued on the wp-admin dashboard, so it cannot
	// be used to resolve the post id from there.)
	await page.goto( '/mega-demo/' );
	await page.click( '#wp-admin-bar-edit a' );
	await page.waitForURL( /post\.php\?post=\d+&action=edit/ );
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

	test( 'a fresh mega-menu defaults to one column with an Add column button', async ( {
		page,
	} ) => {
		const slug = 'mega-add-col-fixture';
		deletePageBySlug( slug );
		const url = createPageWithContent(
			slug,
			'Mega Add Col Fixture',
			'<!-- wp:navigation {"overlayMenu":"never"} -->' +
				'<!-- wp:starter/mega-menu {"label":"Test"} /-->' +
				'<!-- /wp:navigation -->'
		);
		const id = url.replace( /[^0-9]/g, '' );
		await login( page );
		await page.goto( `/wp-admin/post.php?post=${ id }&action=edit` );
		const canvas = page.frameLocator(
			'iframe[name="editor-canvas"]'
		);
		const columns = canvas.locator( '.starter-mega-column' );
		await expect( columns ).toHaveCount( 1 );
		const addBtn = canvas.getByRole( 'button', {
			name: 'Add column',
		} );
		await expect( addBtn ).toBeVisible();
		await addBtn.click();
		await expect( columns ).toHaveCount( 2 );
		deletePageBySlug( slug );
	} );
} );
