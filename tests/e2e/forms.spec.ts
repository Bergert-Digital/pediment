// tests/e2e/forms.spec.ts
import { test, expect } from '@playwright/test';
import { login, createPageWithContent, deletePageBySlug } from './utils';

const SLUG = 'e2e-form';

const FORM = `<!-- wp:pediment/form {"successMessage":"Thanks, got it."} -->
<!-- wp:pediment/form-field {"label":"Name","fieldName":"name","required":true} /-->
<!-- wp:pediment/form-field {"fieldType":"email","label":"Email","fieldName":"email","required":true} /-->
<!-- wp:pediment/form-field {"fieldType":"textarea","label":"Message","fieldName":"message"} /-->
<!-- /wp:pediment/form -->`;

test( 'generic form submits, shows success, and stores a submission', async ( { page } ) => {
	test.slow();
	deletePageBySlug( SLUG );
	const url = createPageWithContent( SLUG, 'Form test', FORM );

	await page.goto( url );
	await page.fill( 'input[name="name"]', 'Alice E2E' );
	await page.fill( 'input[name="email"]', 'alice-form-e2e@example.com' );
	await page.fill( 'textarea[name="message"]', 'Hello from Playwright.' );

	// Beat the time-trap (PEDIMENT_FORM_MIN_AGE seconds).
	await page.waitForTimeout( 4000 );
	await page.click( 'button.pediment-form__submit' );

	await expect( page.locator( '.pediment-form__status' ) ).toContainText( /thanks/i, {
		timeout: 10_000,
	} );

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
