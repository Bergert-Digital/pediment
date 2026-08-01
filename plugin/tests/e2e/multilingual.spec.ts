import { test, expect } from '@playwright/test';
import { execSync } from 'node:child_process';
import { createNavigationEntityWithContent, deleteNavigationEntityById } from './utils';

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

	// Mirrors the German assertion above: a plain correctness canary, not a
	// regression detector on its own. Deleting pediment_bind_navigation_ref()
	// entirely does NOT break either language on today's fixture — Polylang's
	// own query scoping already restricts core's ref-less-navigation fallback
	// to the current language, so a bare "is the binding still there" check
	// can't observe its absence. The test below ('the English header keeps
	// the seeded menu…') is the one that actually discriminates: it
	// reproduces the specific failure the binding exists to prevent and is
	// verified RED without the binding, GREEN with it (see task-15-report.md).
	// Keep both — they check different things.
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

	test.describe( 'nav identity survives a newer same-language decoy', () => {
		// The realistic failure Task 12's binding exists to prevent: a client
		// opens the Site Editor and creates a menu. That post becomes the
		// newest `wp_navigation` in its language; core's ref-less-navigation
		// fallback (WP_Navigation_Fallback::get_most_recently_published_navigation(),
		// `orderby => date, order => DESC`) picks it over the seeded one; the
		// header changes without anyone touching the theme. This reproduces
		// that: a same-language `wp_navigation` post, no seed-key meta,
		// created after seeding, with content that appears nowhere in the
		// seeded menu — then asserts the seeded menu still wins.
		let decoyId = 0;

		test.afterEach( () => {
			// Force-delete, not trash: NavSeeder's keyed() lookup (NavSeeder.php)
			// treats a trashed nav as still holding its identity, and this suite
			// runs against a persistent wp-env database — a leaked decoy would
			// sit here for every later run and could distort other specs.
			// Runs even if the assertion above failed.
			if ( decoyId ) {
				deleteNavigationEntityById( decoyId );
				decoyId = 0;
			}
		} );

		test( 'the English header keeps the seeded menu, not a newer un-seeded one', async ( {
			page,
		} ) => {
			decoyId = createNavigationEntityWithContent(
				`decoy-nav-${ Date.now() }`,
				'Decoy Nav',
				'<!-- wp:navigation-link {"label":"ZZZ-DECOY-LINK","url":"https://example.invalid/decoy","kind":"custom"} /-->'
			);
			wp( `eval 'pll_set_post_language( ${ decoyId }, "en" );'` );

			await page.goto( '/about/' );
			const nav = page.locator( 'header .wp-block-navigation' ).first();

			// Positive AND negative, per review: the seeded label is still
			// there, and the decoy's distinctive label is nowhere in the nav —
			// not "no navigation-link count changed", but "this specific menu".
			await expect(
				nav.getByRole( 'link', { name: 'Contact', exact: true } )
			).toHaveAttribute( 'href', /\/contact\/?$/ );
			await expect( nav.getByRole( 'link', { name: 'ZZZ-DECOY-LINK' } ) ).toHaveCount( 0 );
		} );
	} );

	test( 'the two About pages are one translation group', () => {
		const en = wp( `post list --post_type=page --name=about --field=ID` );
		const de = wp( `post list --post_type=page --name=ueber-uns --field=ID` );

		const linked = wp( `eval 'echo (int) pll_get_post( ${ en }, "de" );'` );

		expect( linked ).toBe( de );
	} );
} );
