<?php
// plugin/tests/phpunit/Seeder/ManifestTest.php

use Pediment\Seeder\EntrySpec;
use Pediment\Seeder\Manifest;
use Pediment\Seeder\ManifestError;

class ManifestTest extends WP_UnitTestCase {

	private function raw(): array {
		return [
			'version' => 1,
			'pages'   => [
				'home'      => [ 'title' => 'Home', 'pattern' => 'pediment/pediment-landing', 'front_page' => true ],
				'blog'      => [ 'title' => 'Blog', 'content' => '', 'posts_page' => true ],
				'guide'     => [ 'title' => 'Guide', 'content' => '<p>g</p>' ],
				'guide/faq' => [ 'title' => 'FAQ', 'content' => '<p>f</p>', 'parent' => 'guide' ],
			],
			'posts'   => [
				'sample-one' => [ 'title' => 'Sample one', 'content' => '<p>s</p>', 'terms' => [ 'category' => [ 'insights' ] ] ],
			],
		];
	}

	public function test_defaults_are_derived_from_the_key() {
		$m   = Manifest::fromArray( $this->raw(), '/tmp/theme' );
		$faq = $m->entries()['guide/faq'];

		$this->assertSame( 'faq', $faq->slug, 'slug defaults to the last key segment' );
		$this->assertSame( 'guide', $faq->parent );
		$this->assertSame( 'page', $faq->postType );
		$this->assertSame( 0, $faq->menuOrder );
		$this->assertSame( 'post', $m->entries()['sample-one']->postType );
		$this->assertSame( [ 'category' => [ 'insights' ] ], $m->entries()['sample-one']->terms );
	}

	public function test_explicit_slug_wins_so_a_page_can_be_renamed_without_losing_identity() {
		$raw                            = $this->raw();
		$raw['pages']['guide']['slug']  = 'handbook';
		$this->assertSame( 'handbook', Manifest::fromArray( $raw, '/tmp/theme' )->entries()['guide']->slug );
	}

	public function test_invalid_slug_is_a_validation_error() {
		$raw                           = $this->raw();
		$raw['pages']['guide']['slug'] = 'Not A Slug';

		$this->expectException( ManifestError::class );
		$this->expectExceptionMessageMatches( '/slug/i' );
		Manifest::fromArray( $raw, '/tmp/theme' );
	}

	public function test_an_empty_slug_is_a_validation_error() {
		// sanitize_title('') === '' passes the round-trip check, WordPress then
		// invents a slug from the title, and the Verifier reports a mismatch on
		// every run forever with no fix that converges.
		$raw                  = $this->raw();
		$raw['pages']['faq/'] = [ 'title' => 'FAQ', 'content' => '' ];

		$this->expectException( ManifestError::class );
		$this->expectExceptionMessageMatches( '/slug/i' );
		Manifest::fromArray( $raw, '/tmp/theme' );
	}

	public function test_an_unknown_top_level_section_is_a_validation_error() {
		// `page` instead of `pages` used to seed nothing and report nothing.
		$raw         = $this->raw();
		$raw['page'] = [ 'ghost' => [ 'title' => 'Ghost', 'content' => '' ] ];

		$this->expectException( ManifestError::class );
		$this->expectExceptionMessageMatches( '/page/' );
		Manifest::fromArray( $raw, '/tmp/theme' );
	}

	public function test_an_unknown_entry_key_is_a_validation_error() {
		$raw                                 = $this->raw();
		$raw['pages']['guide']['front-page'] = true;

		$this->expectException( ManifestError::class );
		$this->expectExceptionMessageMatches( '/front-page/' );
		Manifest::fromArray( $raw, '/tmp/theme' );
	}

	public function test_the_documented_manifest_shape_still_validates() {
		// The fixture manifest and docs/seeding.md use `version`; rejecting
		// unknown sections must not reject the ones the product itself ships.
		$this->assertNotNull( Manifest::fromArray( $this->raw(), '/tmp/theme' ) );
	}

	public function test_dependency_order_puts_parents_first() {
		$keys = array_map(
			static fn( $e ) => $e->key,
			Manifest::fromArray( $this->raw(), '/tmp/theme' )->entriesInDependencyOrder()
		);
		$this->assertLessThan( array_search( 'guide/faq', $keys, true ), array_search( 'guide', $keys, true ) );
	}

	public function test_missing_title_is_a_validation_error() {
		$raw = $this->raw();
		unset( $raw['pages']['home']['title'] );

		$this->expectException( ManifestError::class );
		$this->expectExceptionMessageMatches( '/pages\.home.*title/' );
		Manifest::fromArray( $raw, '/tmp/theme' );
	}

	public function test_both_pattern_and_content_is_a_validation_error() {
		$raw                            = $this->raw();
		$raw['pages']['guide']['pattern'] = 'pediment/prose-article';

		$this->expectException( ManifestError::class );
		$this->expectExceptionMessageMatches( '/pattern.*content/i' );
		Manifest::fromArray( $raw, '/tmp/theme' );
	}

	public function test_neither_pattern_nor_content_is_a_validation_error() {
		$raw = $this->raw();
		unset( $raw['pages']['guide']['content'] );

		$this->expectException( ManifestError::class );
		$this->expectExceptionMessageMatches( '/pattern.*content/i' );
		Manifest::fromArray( $raw, '/tmp/theme' );
	}

	public function test_unknown_parent_is_a_validation_error() {
		$raw                                = $this->raw();
		$raw['pages']['guide/faq']['parent'] = 'nope';

		$this->expectException( ManifestError::class );
		$this->expectExceptionMessageMatches( '/nope/' );
		Manifest::fromArray( $raw, '/tmp/theme' );
	}

	public function test_parent_cycle_is_a_validation_error() {
		$raw = [
			'pages' => [
				'a' => [ 'title' => 'A', 'content' => '', 'parent' => 'b' ],
				'b' => [ 'title' => 'B', 'content' => '', 'parent' => 'a' ],
			],
		];

		$this->expectException( ManifestError::class );
		$this->expectExceptionMessageMatches( '/cycle/i' );
		Manifest::fromArray( $raw, '/tmp/theme' );
	}

	public function test_self_parent_cycle_is_a_validation_error() {
		$raw = [
			'pages' => [
				'a' => [ 'title' => 'A', 'content' => '', 'parent' => 'a' ],
			],
		];

		$this->expectException( ManifestError::class );
		$this->expectExceptionMessageMatches( '/cycle/i' );
		Manifest::fromArray( $raw, '/tmp/theme' );
	}

	public function test_a_key_reused_across_sections_is_a_validation_error() {
		$raw                    = $this->raw();
		$raw['posts']['home']   = [ 'title' => 'Clash', 'content' => '' ];

		$this->expectException( ManifestError::class );
		$this->expectExceptionMessageMatches( '/duplicate seed key/i' );
		Manifest::fromArray( $raw, '/tmp/theme' );
	}

	public function test_more_than_one_front_page_is_a_validation_error() {
		$raw                                = $this->raw();
		$raw['pages']['guide']['front_page'] = true;

		$this->expectException( ManifestError::class );
		$this->expectExceptionMessageMatches( '/front_page/i' );
		Manifest::fromArray( $raw, '/tmp/theme' );
	}

	public function test_more_than_one_posts_page_is_a_validation_error() {
		$raw                               = $this->raw();
		$raw['pages']['guide']['posts_page'] = true;

		$this->expectException( ManifestError::class );
		$this->expectExceptionMessageMatches( '/posts_page/i' );
		Manifest::fromArray( $raw, '/tmp/theme' );
	}

	public function test_media_paths_resolve_against_the_manifest_directory() {
		$dir = get_temp_dir() . 'pediment-manifest-test';
		wp_mkdir_p( $dir . '/seed/media' );
		file_put_contents( $dir . '/seed/media/logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"/>' );

		$m = Manifest::fromArray(
			[ 'media' => [ 'logo' => [ 'file' => 'seed/media/logo.svg', 'title' => 'Logo' ] ], 'site' => [ 'logo' => 'logo' ] ],
			$dir
		);

		$this->assertSame( $dir . '/seed/media/logo.svg', $m->media()['logo']->file );
		$this->assertSame( 'logo', $m->siteLogo() );
	}

	public function test_missing_media_file_is_a_validation_error() {
		$this->expectException( ManifestError::class );
		$this->expectExceptionMessageMatches( '/gone\.jpg/' );
		Manifest::fromArray( [ 'media' => [ 'x' => [ 'file' => 'seed/media/gone.jpg' ] ] ], '/tmp/theme' );
	}

	public function test_site_logo_must_reference_a_declared_media_key() {
		$this->expectException( ManifestError::class );
		$this->expectExceptionMessageMatches( '/logo/i' );
		Manifest::fromArray( [ 'site' => [ 'logo' => 'nope' ] ], '/tmp/theme' );
	}

	public function test_nav_items_must_reference_declared_entries() {
		$raw         = $this->raw();
		$raw['navs'] = [ 'primary' => [ 'title' => 'Primary', 'items' => [ [ 'entry' => 'ghost' ] ] ] ];

		$this->expectException( ManifestError::class );
		$this->expectExceptionMessageMatches( '/ghost/' );
		Manifest::fromArray( $raw, '/tmp/theme' );
	}

	public function test_post_types_are_parsed_with_sane_defaults() {
		$m = Manifest::fromArray(
			[ 'post_types' => [ 'listing' => [ 'label' => 'Listings', 'has_archive' => true ] ] ],
			'/tmp/theme'
		);
		$spec = $m->postTypes()['listing'];

		$this->assertSame( 'listing', $spec->slug );
		$this->assertTrue( $spec->args['public'] );
		$this->assertTrue( $spec->args['show_in_rest'], 'CPT entries must be block-editable' );
		$this->assertSame( 'Listings', $spec->args['label'] );
	}

	public function test_load_returns_null_without_a_theme_manifest_and_honours_the_filter() {
		// The active theme in this shared test install is not this test's to
		// control — another theme in the environment (e.g. the e2e fixture
		// theme) may legitimately ship a real seed/manifest.php. Point at an
		// empty temp directory so "no manifest" is guaranteed, not incidental.
		$dir = get_temp_dir() . 'pediment-manifest-none';
		wp_mkdir_p( $dir );
		add_filter( 'stylesheet_directory', static fn() => $dir );
		Manifest::resetCache();

		$this->assertNull( Manifest::load() );

		Manifest::resetCache();
		add_filter( 'pediment_seed_manifest', fn() => $this->raw() );
		$m = Manifest::load();
		remove_all_filters( 'pediment_seed_manifest' );
		remove_all_filters( 'stylesheet_directory' );

		$this->assertNotNull( $m );
		$this->assertCount( 5, $m->entries() );
	}

	public function test_languages_default_to_none() {
		$manifest = Manifest::fromArray(
			[ 'pages' => [ 'home' => [ 'title' => 'Home', 'content' => '' ] ] ],
			get_stylesheet_directory()
		);

		$this->assertSame( [], $manifest->languages() );
		$this->assertSame( '', $manifest->defaultLanguage() );
	}

	public function test_languages_are_parsed_default_first() {
		$manifest = Manifest::fromArray(
			[
				'languages' => [
					'de' => [ 'name' => 'Deutsch', 'locale' => 'de_DE', 'flag' => 'de' ],
					'en' => [ 'name' => 'English', 'locale' => 'en_US', 'flag' => 'gb', 'default' => true ],
				],
				'pages'     => [ 'home' => [ 'title' => 'Home', 'content' => '' ] ],
			],
			get_stylesheet_directory()
		);

		$this->assertSame( [ 'en', 'de' ], array_keys( $manifest->languages() ) );
		$this->assertSame( 'en', $manifest->defaultLanguage() );
		$this->assertSame( 'Deutsch', $manifest->languages()['de']->name );
		$this->assertSame( 'de_DE', $manifest->languages()['de']->locale );
		$this->assertTrue( $manifest->languages()['en']->isDefault );
	}

	public function test_the_first_declared_language_is_the_default_when_none_says_so() {
		$manifest = Manifest::fromArray(
			[
				'languages' => [ 'nl' => [ 'name' => 'Nederlands', 'locale' => 'nl_NL' ], 'fr' => [ 'name' => 'Français', 'locale' => 'fr_FR' ] ],
				'pages'     => [ 'home' => [ 'title' => 'Home', 'content' => '' ] ],
			],
			get_stylesheet_directory()
		);

		$this->assertSame( 'nl', $manifest->defaultLanguage() );
	}

	public function test_two_defaults_are_rejected() {
		$this->expectException( ManifestError::class );
		$this->expectExceptionMessageMatches( '/only one language may be the default/i' );

		Manifest::fromArray(
			[
				'languages' => [
					'en' => [ 'name' => 'English', 'locale' => 'en_US', 'default' => true ],
					'de' => [ 'name' => 'Deutsch', 'locale' => 'de_DE', 'default' => true ],
				],
			],
			get_stylesheet_directory()
		);
	}

	public function test_a_language_needs_a_locale() {
		$this->expectException( ManifestError::class );
		$this->expectExceptionMessageMatches( "/languages\.de: 'locale' is required/" );

		Manifest::fromArray(
			[ 'languages' => [ 'de' => [ 'name' => 'Deutsch' ] ] ],
			get_stylesheet_directory()
		);
	}

	public function test_an_unknown_language_key_is_rejected() {
		$this->expectException( ManifestError::class );
		$this->expectExceptionMessageMatches( "/languages\.de: unknown key 'lokale'/" );

		Manifest::fromArray(
			[ 'languages' => [ 'de' => [ 'name' => 'Deutsch', 'locale' => 'de_DE', 'lokale' => 'x' ] ] ],
			get_stylesheet_directory()
		);
	}

	public function test_a_language_slug_must_be_a_slug() {
		$this->expectException( ManifestError::class );
		$this->expectExceptionMessageMatches( '/is not a valid language code/' );

		Manifest::fromArray(
			[ 'languages' => [ 'de DE' => [ 'name' => 'Deutsch', 'locale' => 'de_DE' ] ] ],
			get_stylesheet_directory()
		);
	}

	private function bilingual( array $pageDeclaration ): EntrySpec {
		$manifest = Manifest::fromArray(
			[
				'languages' => [ 'en' => [ 'name' => 'English', 'locale' => 'en_US', 'default' => true ], 'de' => [ 'name' => 'Deutsch', 'locale' => 'de_DE' ] ],
				'pages'     => [ 'about' => $pageDeclaration ],
			],
			get_stylesheet_directory()
		);
		return $manifest->entries()['about'];
	}

	public function test_language_overrides_are_parsed() {
		$spec = $this->bilingual(
			[
				'title'     => 'About',
				'pattern'   => 'x/about',
				'languages' => [ 'de' => [ 'title' => 'Über uns', 'slug' => 'ueber-uns' ] ],
			]
		);

		$this->assertSame( 'Über uns', $spec->titleFor( 'de', 'en' ) );
		$this->assertSame( 'ueber-uns', $spec->slugFor( 'de', 'en' ) );
		$this->assertSame( 'About', $spec->titleFor( 'en', 'en' ) );
		$this->assertSame( 'about', $spec->slugFor( 'en', 'en' ) );
	}

	public function test_a_missing_title_falls_back_to_the_default_language() {
		$spec = $this->bilingual( [ 'title' => 'About', 'pattern' => 'x/about' ] );

		$this->assertSame( 'About', $spec->titleFor( 'de', 'en' ) );
	}

	public function test_a_missing_slug_derives_a_distinct_one() {
		// Polylang does not hook wp_unique_post_slug: two languages sharing a
		// slug land as `about` and `about-2`, and the Verifier then reports a
		// mismatch on every run forever with no fix that converges.
		$spec = $this->bilingual( [ 'title' => 'About', 'pattern' => 'x/about' ] );

		$this->assertSame( 'about-de', $spec->slugFor( 'de', 'en' ) );
	}

	public function test_a_pattern_override_is_used_verbatim() {
		$spec = $this->bilingual(
			[ 'title' => 'About', 'pattern' => 'x/about', 'languages' => [ 'de' => [ 'pattern' => 'x/about-german' ] ] ]
		);

		$this->assertSame( 'x/about-german', $spec->patternFor( 'de', 'en' ) );
		$this->assertSame( 'x/about', $spec->patternFor( 'en', 'en' ) );
	}

	public function test_pattern_for_returns_null_for_a_content_entry() {
		$spec = $this->bilingual( [ 'title' => 'About', 'content' => '' ] );

		$this->assertNull( $spec->patternFor( 'de', 'en' ) );
	}

	public function test_an_unknown_language_override_key_is_rejected() {
		$this->expectException( ManifestError::class );
		$this->expectExceptionMessageMatches( "/pages\.about\.languages\.de: unknown key 'titel'/" );

		$this->bilingual( [ 'title' => 'About', 'content' => '', 'languages' => [ 'de' => [ 'titel' => 'x' ] ] ] );
	}

	public function test_an_override_slug_must_be_a_valid_slug() {
		$this->expectException( ManifestError::class );
		$this->expectExceptionMessageMatches( "/pages\.about\.languages\.de: slug 'Über Uns' is not a valid post slug/" );

		$this->bilingual( [ 'title' => 'About', 'content' => '', 'languages' => [ 'de' => [ 'slug' => 'Über Uns' ] ] ] );
	}

	public function test_an_override_for_an_undeclared_language_is_rejected() {
		$this->expectException( ManifestError::class );
		$this->expectExceptionMessageMatches( "/pages\.about\.languages\.fr: 'fr' is not a declared site language/" );

		$this->bilingual( [ 'title' => 'About', 'content' => '', 'languages' => [ 'fr' => [ 'title' => 'À propos' ] ] ] );
	}

	public function test_an_empty_title_override_is_rejected() {
		// titleFor() falls back with `??`, which only catches null. A stored ''
		// would pass straight through as the post title with no error — the
		// same silent no-op the top-level 'title' required-check exists to stop.
		$this->expectException( ManifestError::class );
		$this->expectExceptionMessageMatches( "/pages\.about\.languages\.de: 'title' may not be empty/" );

		$this->bilingual( [ 'title' => 'About', 'content' => '', 'languages' => [ 'de' => [ 'title' => '' ] ] ] );
	}

	public function test_a_pattern_override_on_a_content_entry_is_rejected() {
		// content-declared entries have no pattern to translate; patternFor()
		// would discard the override unconditionally with no feedback.
		$this->expectException( ManifestError::class );
		$this->expectExceptionMessageMatches( "/pages\.about\.languages\.de: 'pattern' cannot be overridden/" );

		$this->bilingual( [ 'title' => 'About', 'content' => '', 'languages' => [ 'de' => [ 'pattern' => 'x/about-de' ] ] ] );
	}

	public function test_the_resolvers_work_for_the_monolingual_empty_language() {
		// The path every single-language site runs: no 'languages' section
		// declared at all, so Manifest::defaultLanguage() is '' and every
		// resolver is called with $language === $default === ''.
		$manifest = Manifest::fromArray(
			[ 'pages' => [ 'about' => [ 'title' => 'About', 'pattern' => 'x/about' ] ] ],
			get_stylesheet_directory()
		);
		$spec = $manifest->entries()['about'];

		$this->assertSame( 'About', $spec->titleFor( '', '' ) );
		$this->assertSame( 'about', $spec->slugFor( '', '' ) );
		$this->assertSame( 'x/about', $spec->patternFor( '', '' ) );
	}
}
