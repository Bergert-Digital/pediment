import { test, expect } from '@playwright/test';
import { execSync } from 'node:child_process';

const WP_ENV_CWD = process.env.WP_ENV_CWD || process.cwd();
const wp = ( cmd: string ) =>
	execSync( `npx wp-env run cli wp ${ cmd }`, { cwd: WP_ENV_CWD, stdio: 'pipe' } )
		.toString()
		.trim();

test.describe( 'multilingual seeding', () => {
	test( 'both languages exist and are configured', () => {
		// Not `pediment seed --dry-run --json`: WP-CLI 2.12 rewrites ANY
		// `--json` assoc-arg to `--format=json` before a command's own
		// synopsis is validated (wp-cli core, the `--json -> --format=json`
		// shorthand in Runner::run_command()) — confirmed by `wp post list
		// --json` and `wp post list --format=json` producing identical output.
		// SeedCommand declares its own `--json` flag but no `--format`, so the
		// rewritten arg always fails validation with "unknown --format
		// parameter". This is a pre-existing bug in wp-cli/SeedCommand.php
		// (predates step 4 — see commit e7ea6ce), reported rather than fixed
		// here per this task's constraints. `wp eval` calls the exact same
		// render() path the CLI flag would, without going through WP-CLI's
		// own arg-rewriting layer where the collision lives — which also means
		// this deliberately bypasses SeedCommand::__invoke()'s own
		// `isset($assocArgs['json'])` / `isset($assocArgs['dry-run'])` wiring.
		// A regression there, independent of the --format collision above,
		// would not be caught by this test.
		const json = JSON.parse(
			wp(
				`eval '$r = ( new \\Pediment\\Seeder\\Runner() )->run( [ "dry_run" => true ] ); echo \\Pediment\\Cli\\SeedCommand::render( $r, true );'`
			)
		);
		const languages = new Set(
			json.items.filter( ( i ) => i.kind === 'entry' ).map( ( i ) => i.language )
		);

		expect( [ ...languages ].sort() ).toEqual( [ 'de', 'en' ] );
	} );

	test( 'a re-seed plans no writes', () => {
		expect( wp( `pediment seed --dry-run` ) ).toContain( '0 to write' );
	} );

	test( 'the untranslated posts are reported, not failed', () => {
		const plan = wp( `pediment seed --dry-run` );

		expect( plan ).toContain( 'TRANSLATIONS' );
		expect( plan ).toContain( 'sample-insight-one' );
	} );

	test( 'the German page is reachable at its own slug', async ( { page } ) => {
		const response = await page.goto( '/de/ueber-uns/' );

		expect( response?.status() ).toBe( 200 );
		await expect( page.locator( 'body' ) ).toContainText( 'Deutschsprachige Fassung' );
	} );

	test( 'the German language root serves the front page, not a redirect chain', async ( { page } ) => {
		// page.goto() follows redirects transparently, so a final pathname of
		// '/de/' alone would also pass a 301 /de -> /de/ chain. Assert on the
		// actual response instead: a 200 on this exact request, and no prior
		// request in the chain (request().redirectedFrom() is non-null only
		// when THIS request is the target of a redirect).
		const response = await page.goto( '/de/' );

		expect( response?.status() ).toBe( 200 );
		expect( response?.request().redirectedFrom() ).toBeNull();
	} );

	test( 'the header navigation links to German pages on a German page', async ( { page } ) => {
		await page.goto( '/de/ueber-uns/' );

		const header = page.locator( 'header.site-header' );
		await expect( header.getByRole( 'link', { name: 'Kontakt' } ) ).toHaveAttribute(
			'href',
			/\/de\/kontakt\/?$/
		);
	} );

	// Mirrors the German assertion above so this file is self-discriminating:
	// with the seed key -> language binding gone, deleting just the German
	// half would still pass (Polylang's own query scoping happens to resolve
	// core's ref-less-navigation fallback to the right language in today's
	// simple two-nav fixture — verified by temporarily removing
	// pediment_bind_navigation_ref() at runtime and hitting both language
	// pages, no failure either way). What the binding actually guards against,
	// confirmed the same way with a decoy English wp_navigation post created
	// after the seeded one: WITHOUT the binding, the English page's header
	// silently switched to the newer, un-seeded post; WITH it restored, it
	// switched back. That is Task 12's own point (nav identity is the seed
	// key, never "most recently created") and only an English-side assertion
	// can observe an English-side regression of it. Don't delete either half
	// as "redundant" with the German one — they check different failures.
	test( 'the header navigation links to English pages on an English page', async ( { page } ) => {
		await page.goto( '/about/' );

		// Scoped to the nav block itself, not the whole header: the header
		// template part also carries its own static "Contact" CTA button
		// (`.wp-block-button__link`, hardcoded href="/contact", not part of
		// the seeded nav entity and not localized) — `header.site-header`
		// alone resolves both and fails Playwright's strict-mode check.
		const nav = page.locator( 'header .wp-block-navigation' ).first();
		await expect(
			nav.getByRole( 'link', { name: 'Contact', exact: true } )
		).toHaveAttribute( 'href', /\/contact\/?$/ );
	} );

	test( 'the two About pages are one translation group', () => {
		const en = wp( `post list --post_type=page --name=about --field=ID` );
		const de = wp( `post list --post_type=page --name=ueber-uns --field=ID` );

		const linked = wp( `eval 'echo (int) pll_get_post( ${ en }, "de" );'` );

		expect( linked ).toBe( de );
	} );
} );
