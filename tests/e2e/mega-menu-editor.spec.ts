import { test, expect } from '@playwright/test';
import { login, createPageWithContent, deletePageBySlug } from './utils';

const SEED =
	'<!-- wp:navigation {"overlayMenu":"never"} -->' +
	'<!-- wp:starter/mega-menu {"label":"Products","columns":[{"heading":"Product","links":[{"label":"Pricing","url":"/pricing","description":"Plans","icon":"tag"}]}]} /-->' +
	'<!-- /wp:navigation -->';

test.describe( 'mega menu editor (sidebar form)', () => {
	test( 'sidebar form exposes label/columns/links and preview reflects data', async ( {
		page,
	} ) => {
		const slug = 'mega-form-fixture';
		deletePageBySlug( slug );
		const url = createPageWithContent(
			slug,
			'Mega Form Fixture',
			SEED
		);
		const id = url.replace( /[^0-9]/g, '' );
		await login( page );
		await page.goto(
			`/wp-admin/post.php?post=${ id }&action=edit`
		);
		const canvas = page.frameLocator(
			'iframe[name="editor-canvas"]'
		);

		// Select the block (its ServerSideRender preview) to load the sidebar.
		await canvas.locator( '.starter-mega-menu' ).first().click();

		// Sidebar form reflects seeded attributes.
		await expect( page.getByLabel( 'Menu label' ) ).toHaveValue(
			'Products'
		);

		// Preview renders the seeded link via render.php.
		await expect(
			canvas
				.locator( '.starter-mega-link__label', {
					hasText: 'Pricing',
				} )
				.first()
		).toBeVisible();

		// Adding a column via the form is possible.
		await page
			.getByRole( 'button', { name: 'Add column' } )
			.click();
		await expect(
			page.getByRole( 'button', { name: /^Column 2/ } )
		).toBeVisible();

		deletePageBySlug( slug );
	} );

	test( 'editor CSS reveals the panel on trigger hover', async ( {
		page,
	} ) => {
		const slug = 'mega-hover-fixture';
		deletePageBySlug( slug );
		const url = createPageWithContent(
			slug,
			'Mega Hover Fixture',
			SEED
		);
		const id = url.replace( /[^0-9]/g, '' );
		await login( page );
		await page.goto(
			`/wp-admin/post.php?post=${ id }&action=edit`
		);
		const canvas = page.frameLocator(
			'iframe[name="editor-canvas"]'
		);
		const panel = canvas
			.locator( '.starter-mega-menu__panel' )
			.first();
		await expect( panel ).toBeHidden();
		await canvas
			.locator( '.starter-mega-menu__trigger' )
			.first()
			.hover();
		await expect( panel ).toBeVisible();
		deletePageBySlug( slug );
	} );
} );
