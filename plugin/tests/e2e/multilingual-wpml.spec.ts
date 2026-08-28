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
// global-setup.ts detects the WPML env, runs `wp pediment languages` (whose
// WpmlSetup now also fires WPML's config parse — the headless-deploy path this
// suite proves), then seeds (see setupWpml there). Where the Polylang spec used
// pll_*, this one uses WPML's `wpml_*` filters via `wp eval`.

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
		// It is made translatable through WPML's real config path fired by
		// `wp pediment languages` (WpmlSetup::configure -> load_config_run),
		// never a manual call in global-setup and never a hand-set option.
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

	// FIXED (was KNOWN DEFECT #1, task-11-report.md). WpmlProvider::
	// languageSwitcherBlock() used to emit a bare `<!-- wp:wpml/language-switcher /-->`.
	// WPML's Parser::parse() returns null for a block with empty saved HTML, and
	// Render.php:39 then fataled (`getCurrentLanguageItemTemplate() on null`), so
	// any manifest that added a `language_switcher` item crashed the WPML front
	// end. The provider now emits the block WITH the `data-wpml` item template
	// WPML clones per active language, so it renders. This test proves it end to
	// end: seed a manifest that DOES carry a `language_switcher` nav item (the
	// exact case that used to fatal), then assert the header responds 200, does
	// not fatal, and shows a working switcher control with a real language link.
	test.describe( 'language switcher renders', () => {
		test.beforeAll( () => {
			// Re-seed with a `language_switcher` appended to the primary nav,
			// through the real engine (Runner + the pediment_seed_manifest filter),
			// so the header the browser loads is the one the seeder actually wrote.
			wp(
				`eval 'add_filter("pediment_seed_manifest", function($m){ $m["navs"]["primary"]["items"][] = ["language_switcher"=>true]; return $m; }); (new \\Pediment\\Seeder\\Runner())->run();'`
			);
		} );

		test( 'the seeded WPML language-switcher block renders language links on the front end', async ( {
			page,
		} ) => {
			const response = await page.goto( '/de/ueber-uns/' );

			// The header renders — no fatal (the old bare-comment form 500'd here).
			expect( response?.status() ).toBe( 200 );
			await expect( page.locator( 'body' ) ).not.toContainText(
				/Fatal error|critical error/i
			);

			// A working switcher control is present in the header: a compact
			// toggle showing the CURRENT language (Deutsch on the German page),
			// with a hover/focus dropdown panel of the languages you can switch to.
			const switcher = page
				.locator( 'header .wpml-language-switcher-block' )
				.first();
			await expect( switcher ).toBeVisible();

			// The toggle is the current language, always visible, and links to the
			// current (German) page.
			const toggle = switcher.locator( '.wpml-ls-toggle' );
			await expect( toggle ).toBeVisible();
			await expect( toggle ).toContainText( 'Deutsch' );
			await expect(
				toggle.getByRole( 'link', { name: 'Deutsch' } )
			).toHaveAttribute( 'href', /\/de\// );

			// The panel lists the OTHER languages (English), each with the real
			// per-language href WPML filled in, hidden until the toggle opens.
			const panel = switcher.locator( '.wpml-ls-panel' );
			const english = panel.getByRole( 'link', {
				name: 'English',
				includeHidden: true,
			} );
			await expect( english ).toHaveAttribute( 'href', /\/about\/?$/ );
			await expect( english ).toBeHidden();

			// HOVER-TO-REVEAL — the feature the user asked for. Hovering the
			// toggle reveals the panel; the state CHANGES from hidden to visible.
			await toggle.hover();
			await expect( english ).toBeVisible();

			// …and the panel opens BELOW the toggle, not over it (the earlier bug).
			const tb = await toggle.boundingBox();
			const pb = await panel.boundingBox();
			expect( pb ).not.toBeNull();
			expect( tb ).not.toBeNull();
			expect( pb!.y ).toBeGreaterThanOrEqual( tb!.y + tb!.height - 2 );
		} );
	} );

	// FIXED (Task 16). NavSeeder::linkAttrs() now resolves the item's url via
	// LanguageProvider::permalinkInLanguage( $postId, $language ), which under
	// WPML switches the language context to the item's language around
	// get_permalink() — so a German menu item stores its /de/ URL instead of the
	// ambient English one. Core's navigation-link render uses the stored `url`
	// verbatim, so the German header now links to German pages.
	test(
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
