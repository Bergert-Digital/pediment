import { test, expect } from '@playwright/test';
import { execSync } from 'node:child_process';

// WPML parity with multilingual.spec.ts (which drives Polylang). This suite is
// the ultimate integration test for the WPML support feature: it proves, on a
// real WPML site, the production chain the unit tests can only stub —
// inc/wpml-compat.php's `wpml_config_array` filter -> WPML treats wp_navigation
// as translatable -> NavSeeder::linkTranslations builds the nav translation
// group -> inc/nav-language.php's per-language binding resolves DISTINCT menus.
//
// It runs against the WPML wp-env (.wp-env.override.json), which replaces
// Polylang entirely. Point Playwright and wp-env at that env:
//
//   WP_ENV_PORT=8920 WP_ENV_TESTS_PORT=8921 \
//   PLAYWRIGHT_BASE_URL=http://localhost:8920 \
//   npx playwright test tests/e2e/multilingual-wpml.spec.ts
//
// global-setup.ts detects the WPML env and configures languages, triggers
// WPML's config parse, and seeds (see setupWpml there). Where the Polylang spec
// used pll_*, this one uses WPML's `wpml_*` filters via `wp eval`.

const WP_ENV_CWD = process.env.WP_ENV_CWD || process.cwd();
const wp = ( cmd: string ) =>
	execSync( `npx wp-env run cli wp ${ cmd }`, { cwd: WP_ENV_CWD, stdio: 'pipe' } )
		.toString()
		.trim();

test.describe( 'multilingual seeding (WPML)', () => {
	// Skip the whole suite when the running env is not WPML with en+de active,
	// so a stray run against the Polylang env (or an unconfigured WPML env)
	// no-ops instead of failing with confusing errors. This is the WPML
	// analogue of a plugin/language precondition; it never fabricates state.
	test.beforeAll( () => {
		const active = wp(
			`eval 'echo implode(",", array_keys( (array) apply_filters( "wpml_active_languages", null ) ) );'`
		);
		const languages = new Set( active.split( ',' ).filter( Boolean ) );
		test.skip(
			! languages.has( 'en' ) || ! languages.has( 'de' ),
			'requires the WPML env with en+de active (see .wp-env.override.json)'
		);
	} );

	test( 'both languages exist and are active', () => {
		const active = wp(
			`eval 'echo implode(",", array_keys( (array) apply_filters( "wpml_active_languages", null ) ) );'`
		);
		const languages = new Set( active.split( ',' ).filter( Boolean ) );

		expect( [ ...languages ].sort() ).toEqual( [ 'de', 'en' ] );
		expect(
			wp( `eval 'echo apply_filters( "wpml_default_language", null );'` )
		).toBe( 'en' );
	} );

	test( 'wp_navigation is translatable through WPML — inc/wpml-compat.php was consumed', () => {
		// The linchpin of the whole feature. If this is `false`, WPML never
		// parsed inc/wpml-compat.php's `wpml_config_array` filter, every nav
		// translation lookup collapses to the default language, and the two
		// languages render the same header (the outage this feature prevents).
		// It is made translatable through WPML's real config path in
		// global-setup (WPML_Config::load_config_run()), never a hand-set option.
		const translated = wp(
			`eval 'global $sitepress; echo $sitepress->is_translated_post_type( "wp_navigation" ) ? "yes" : "no";'`
		);
		expect( translated ).toBe( 'yes' );

		// The companion declaration: template parts stay shared (one header for
		// every language), so they must remain NOT translated.
		const sharedPart = wp(
			`eval 'global $sitepress; echo $sitepress->is_translated_post_type( "wp_template_part" ) ? "yes" : "no";'`
		);
		expect( sharedPart ).toBe( 'no' );
	} );

	test( 'the German page is reachable at its own slug', async ( { page } ) => {
		const response = await page.goto( '/de/ueber-uns/' );

		expect( response?.status() ).toBe( 200 );
		await expect( page.locator( 'body' ) ).toContainText( 'Deutschsprachige Fassung' );
	} );

	test( 'the German language root serves the front page, not a redirect chain', async ( {
		page,
	} ) => {
		// Same guard as the Polylang spec: assert on the actual response, not
		// the final pathname, so a 301 /de -> /de/ chain cannot pass silently.
		const response = await page.goto( '/de/' );

		expect( response?.status() ).toBe( 200 );
		expect( response?.request().redirectedFrom() ).toBeNull();
	} );

	// THE MANDATORY ASSERTION. The English page and the German page must render
	// DISTINCT, language-appropriate header menus — not the same menu on both.
	// Identical menus would mean the production chain is broken (WPML never made
	// wp_navigation translatable, so the binding fell back to the default menu).
	// Scoped to `header .wp-block-navigation` because the header template part
	// also carries a static "Contact" CTA button outside the seeded nav.
	test( 'the header renders a distinct German menu on a German page', async ( { page } ) => {
		await page.goto( '/de/ueber-uns/' );
		const nav = page.locator( 'header .wp-block-navigation' ).first();

		// The German menu is present…
		await expect( nav.getByRole( 'link', { name: 'Über uns', exact: true } ) ).toHaveCount( 1 );
		await expect( nav.getByRole( 'link', { name: 'Journal', exact: true } ) ).toHaveCount( 1 );
		await expect( nav.getByRole( 'link', { name: 'Kontakt', exact: true } ) ).toHaveCount( 1 );
		// …and the English menu's distinctive labels are NOT — this is the
		// "not the same menu on both" half.
		await expect( nav.getByRole( 'link', { name: 'About', exact: true } ) ).toHaveCount( 0 );
		await expect( nav.getByRole( 'link', { name: 'Contact', exact: true } ) ).toHaveCount( 0 );
	} );

	test( 'the header renders a distinct English menu on an English page', async ( { page } ) => {
		await page.goto( '/about/' );
		const nav = page.locator( 'header .wp-block-navigation' ).first();

		await expect( nav.getByRole( 'link', { name: 'About', exact: true } ) ).toHaveCount( 1 );
		await expect( nav.getByRole( 'link', { name: 'Blog', exact: true } ) ).toHaveCount( 1 );
		await expect( nav.getByRole( 'link', { name: 'Contact', exact: true } ) ).toHaveCount( 1 );
		await expect( nav.getByRole( 'link', { name: 'Über uns', exact: true } ) ).toHaveCount( 0 );
		await expect( nav.getByRole( 'link', { name: 'Kontakt', exact: true } ) ).toHaveCount( 0 );
	} );

	test( 'the two header menus belong to one WPML translation group', () => {
		// Directly proves NavSeeder::linkTranslations built the nav group over a
		// translatable wp_navigation: the seeded English nav resolves, via
		// `wpml_object_id`, to a DIFFERENT post for `de` (its German sibling).
		// Before the config parse this returned the English nav for `de` too.
		const enNav = wp(
			`eval '$ids = get_posts( [ "post_type" => "wp_navigation", "posts_per_page" => -1, "fields" => "ids", "meta_key" => "_pediment_seed_key", "meta_value" => "primary", "suppress_filters" => true ] ); foreach ( $ids as $id ) { if ( "en" === apply_filters( "wpml_element_language_code", null, [ "element_id" => $id, "element_type" => "post_wp_navigation" ] ) ) { echo $id; break; } }'`
		);
		expect( Number( enNav ) ).toBeGreaterThan( 0 );

		const deNav = wp(
			`eval 'echo (int) apply_filters( "wpml_object_id", ${ enNav }, "wp_navigation", false, "de" );'`
		);
		expect( Number( deNav ) ).toBeGreaterThan( 0 );
		expect( deNav ).not.toBe( enNav );

		// And that German nav really is `de`, not an accidental duplicate.
		const deNavLang = wp(
			`eval 'echo apply_filters( "wpml_element_language_code", null, [ "element_id" => ${ deNav }, "element_type" => "post_wp_navigation" ] );'`
		);
		expect( deNavLang ).toBe( 'de' );
	} );

	test( 'the two About pages are one translation group', () => {
		// `wp post list --name=ueber-uns` returns nothing under WPML (CLI is
		// scoped to the current language), so resolve the pair through the seam
		// the runtime uses: `wpml_object_id`.
		const en = wp( `post list --post_type=page --name=about --field=ID` );
		expect( Number( en ) ).toBeGreaterThan( 0 );

		const de = wp(
			`eval 'echo (int) apply_filters( "wpml_object_id", ${ en }, "page", false, "de" );'`
		);
		expect( Number( de ) ).toBeGreaterThan( 0 );

		const deSlug = wp( `post get ${ de } --field=post_name` );
		expect( deSlug ).toBe( 'ueber-uns' );
	} );

	test( "WPML's front-end language layer is active on the seeded pages", async ( { page } ) => {
		// The switcher block itself is covered by the fixme below (it does not
		// render as Pediment currently emits it). This proves the surrounding
		// WPML front-end integration works end-to-end on the seeded site: the
		// German URL declares German, and both languages are advertised as
		// hreflang alternates in the head.
		await page.goto( '/de/ueber-uns/' );
		await expect( page.locator( 'html' ) ).toHaveAttribute( 'lang', /^de/ );

		await expect(
			page.locator( 'head link[rel="alternate"][hreflang="de"]' )
		).toHaveCount( 1 );
		await expect(
			page.locator( 'head link[rel="alternate"][hreflang="en"]' )
		).toHaveCount( 1 );
	} );

	// KNOWN DEFECT #1 (see task-11-report.md, "Concerns"). WpmlProvider::
	// languageSwitcherBlock() emits a bare `<!-- wp:wpml/language-switcher /-->`.
	// WPML's Parser::parse() returns null for a block with empty saved HTML, and
	// Render.php:39 then fatals (`getCurrentLanguageItemTemplate() on null`) with
	// two active languages. So the switcher block, as emitted, cannot render on
	// the front end. The fixture seeds no switcher, so the live site is
	// unaffected — but any manifest that adds a `language_switcher` item would
	// crash the front end. Unblock this once Task 6's WpmlProvider emits the
	// switcher's saved template markup instead of the bare comment.
	test.fixme(
		'the seeded WPML language-switcher block renders language links on the front end',
		async ( { page } ) => {
			await page.goto( '/de/ueber-uns/' );
			await expect(
				page.locator( 'header .wpml-ls, header .wpml-language-switcher' )
			).toBeVisible();
		}
	);

	// KNOWN DEFECT #2 (see task-11-report.md, "Concerns"). NavSeeder::linkAttrs()
	// stores `get_permalink( $germanPostId )` resolved in the DEFAULT-language
	// (en) context during the CLI seed, so the German menu items store the
	// ENGLISH page URLs (Kontakt -> /contact/ instead of /de/kontakt/). Core's
	// navigation-link render uses the stored `url` verbatim, so the German
	// header links to English pages. get_permalink() only returns the /de/ URL
	// when WPML's language context is switched to the post's language — a switch
	// the seeder does not perform. Unblock once the seeder resolves per-language
	// permalinks in the item's language context under WPML.
	test.fixme(
		'the German header links point at German URLs',
		async ( { page } ) => {
			await page.goto( '/de/ueber-uns/' );
			const nav = page.locator( 'header .wp-block-navigation' ).first();
			await expect(
				nav.getByRole( 'link', { name: 'Kontakt', exact: true } )
			).toHaveAttribute( 'href', /\/de\/kontakt\/?$/ );
		}
	);
} );
