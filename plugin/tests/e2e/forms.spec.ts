// tests/e2e/forms.spec.ts
import { test, expect } from '@playwright/test';
import { login, createPageWithContent, deletePageBySlug } from './utils';

const SLUG = 'e2e-form';

const FORM = `<!-- wp:pediment/form {"successMessage":"Thanks, got it."} -->
<!-- wp:pediment/form-field {"label":"Name","fieldName":"name","required":true} /-->
<!-- wp:pediment/form-field {"fieldType":"email","label":"Email","fieldName":"email","required":true} /-->
<!-- wp:pediment/form-field {"fieldType":"textarea","label":"Message","fieldName":"message"} /-->
<!-- /wp:pediment/form -->`;

test( 'generic form submits, shows success, and stores a submission', async ( {
	page,
} ) => {
	test.slow();
	deletePageBySlug( SLUG );
	const url = createPageWithContent( SLUG, 'Form test', FORM );

	await page.goto( url );
	await page.fill( 'input[name="name"]', 'Alice E2E' );
	await page.fill( 'input[name="email"]', 'alice-form-e2e@example.com' );
	await page.fill( 'textarea[name="message"]', 'Hello from Playwright.' );

	// Beat the time-trap (PEDIMENT_FORM_MIN_AGE seconds).
	await page.waitForTimeout( 4000 );
	const form = page.locator( 'form.pediment-form' );
	const before = await form.boundingBox();
	await page.click( 'button.pediment-form__submit' );

	// The fields give way to a confirmation panel that holds the focus.
	const panel = page.locator( '.pediment-form__success' );
	await expect( panel ).toBeVisible( { timeout: 10_000 } );
	await expect( panel ).toContainText( 'Message sent' );
	await expect( panel ).toContainText( /thanks, got it/i );
	await expect( page.locator( 'input[name="name"]' ) ).toBeHidden();
	await expect(
		page.locator( '.pediment-form__success-heading' )
	).toBeFocused();

	// No layout shift: the form keeps its height.
	const after = await form.boundingBox();
	expect(
		Math.abs( ( after?.height ?? 0 ) - ( before?.height ?? 0 ) )
	).toBeLessThan( 1 );

	// "Send another message" brings back an empty form.
	await page.click( 'button.pediment-form__reset' );
	await expect( panel ).toBeHidden();
	await expect( page.locator( 'input[name="name"]' ) ).toHaveValue( '' );
	await expect( page.locator( 'input[name="name"]' ) ).toBeFocused();

	await login( page );
	await page.goto( '/wp-admin/edit.php?post_type=form_submission' );
	await expect(
		page.locator( 'text=alice-form-e2e@example.com' ).first()
	).toBeVisible();

	deletePageBySlug( SLUG );
} );

test( 'required validation blocks an empty submit', async ( { page } ) => {
	test.slow();
	const slug = 'e2e-form-required';
	deletePageBySlug( slug );
	const url = createPageWithContent( slug, 'Form required test', FORM );

	await page.goto( url );
	await page.fill( 'input[name="name"]', 'Bob' );
	// Leave required email empty.
	await page.waitForTimeout( 4000 );
	await page.click( 'button.pediment-form__submit' );

	await expect( page.locator( '.pediment-form__status' ) ).toContainText(
		/validation failed|required/i
	);

	deletePageBySlug( slug );
} );

const FILE_FORM = `<!-- wp:pediment/form {"successMessage":"Application received."} -->
<!-- wp:pediment/form-field {"label":"Name","fieldName":"name","required":true} /-->
<!-- wp:pediment/form-field {"fieldType":"file","label":"CV","fieldName":"cv","required":true,"maxSize":1} /-->
<!-- /wp:pediment/form -->`;

const PDF = Buffer.from(
	'%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF\n'
);

test( 'file field uploads a PDF that wp-admin can download', async ( {
	page,
} ) => {
	test.slow();
	const slug = 'e2e-form-file';
	deletePageBySlug( slug );
	const url = createPageWithContent( slug, 'Form file test', FILE_FORM );

	await page.goto( url );
	await page.fill( 'input[name="name"]', 'Carol File E2E' );

	// Too large for the field: the browser refuses before it sends.
	await page.setInputFiles( 'input[name="cv"]', {
		name: 'big.pdf',
		mimeType: 'application/pdf',
		buffer: Buffer.alloc( 2 * 1024 * 1024 ),
	} );
	await page.waitForTimeout( 4000 );
	await page.click( 'button.pediment-form__submit' );
	await expect( page.locator( '.pediment-form__field-error' ) ).toContainText(
		/too large/i
	);

	await page.setInputFiles( 'input[name="cv"]', {
		name: 'Carol CV.pdf',
		mimeType: 'application/pdf',
		buffer: PDF,
	} );
	await page.click( 'button.pediment-form__submit' );
	await expect( page.locator( '.pediment-form__success' ) ).toContainText(
		/application received/i,
		{ timeout: 10_000 }
	);

	await login( page );
	await page.goto( '/wp-admin/edit.php?post_type=form_submission' );
	await page
		.locator( 'tr', { hasText: 'Carol File E2E' } )
		.first()
		.locator( 'a.row-title' )
		.click();
	const link = page.locator( 'a', { hasText: 'Carol-CV.pdf' } );
	await expect( link ).toBeVisible();
	const download = await page.request.get(
		( await link.getAttribute( 'href' ) ) as string
	);
	expect( download.status() ).toBe( 200 );
	expect( ( await download.body() ).subarray( 0, 5 ).toString() ).toBe(
		'%PDF-'
	);

	deletePageBySlug( slug );
} );
