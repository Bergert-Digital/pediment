<?php

use Pediment\Language\PolylangProvider;
use Pediment\Seeder\Manifest;
use Pediment\Seeder\NavSeeder;

class NavLanguageTest extends PolylangTestCase {

	/** term_id of a language added mid-test, so tear_down() can remove it again. */
	private ?int $addedLanguageId = null;

	public function tear_down(): void {
		if ( null !== $this->addedLanguageId ) {
			PLL()->model->delete_language( $this->addedLanguageId );
			$this->addedLanguageId = null;
		}
		parent::tear_down();
	}

	public function test_wp_navigation_is_translatable_outside_the_settings_screen() {
		$this->assertContains( 'wp_navigation', (array) apply_filters( 'pll_get_post_types', [], false ) );
	}

	public function test_the_settings_screen_list_is_left_alone() {
		// Polylang's settings screen offers only public, non-builtin post types,
		// so wp_navigation can never appear there — adding it would render a
		// checkbox a site owner could untick and lose every translated menu to.
		$this->assertNotContains( 'wp_navigation', (array) apply_filters( 'pll_get_post_types', [], true ) );
	}

	/**
	 * Polylang Pro's full-site-editing module adds wp_template_part as a
	 * translated post type (PLL_FSE_Post_Types::add_post_types(), on
	 * `pll_get_post_types` at priority 10), which language-scopes it. Pediment
	 * seeds ONE header and one footer, shared across every language and tagged
	 * with no language — under Pro the language-less parts then match no
	 * language, and the `wp:template-part` block resolves to nothing, taking the
	 * whole navigation with it. Pro is not installed in this Free-backed suite,
	 * so a stand-in adds wp_template_part exactly as Pro does; the assertion is
	 * that this plugin removes it again while leaving wp_navigation translated.
	 */
	public function test_template_parts_are_kept_out_of_translation_even_when_pro_adds_them() {
		$pro = static function ( $post_types ) {
			$post_types['wp_template_part'] = 'wp_template_part';
			return $post_types;
		};
		add_filter( 'pll_get_post_types', $pro, 10, 1 );
		try {
			$types = (array) apply_filters( 'pll_get_post_types', [], false );
		} finally {
			remove_filter( 'pll_get_post_types', $pro, 10 );
		}

		$this->assertNotContains( 'wp_template_part', $types, 'The shared header/footer parts must stay language-agnostic, or Pro hides them.' );
		$this->assertContains( 'wp_navigation', $types, 'Removing template parts must not disturb the translated navigation.' );
	}

	public function test_one_navigation_entity_per_language() {
		$manifest = $this->manifest();
		$lang     = new PolylangProvider();
		$seeder   = new NavSeeder( $lang );

		$entryIds = [ 'about|en' => $this->page( 'en' ), 'about|de' => $this->page( 'de' ) ];
		$plan     = $seeder->plan( $manifest, $entryIds );
		$ids      = $seeder->apply( $plan, $manifest, $entryIds );

		$this->assertSame( [], $seeder->errors() );
		$this->assertArrayHasKey( 'primary|en', $ids );
		$this->assertArrayHasKey( 'primary|de', $ids );
		$this->assertNotSame( $ids['primary|en'], $ids['primary|de'] );
	}

	public function test_the_navigation_entities_are_one_translation_group() {
		$manifest = $this->manifest();
		$lang     = new PolylangProvider();
		$seeder   = new NavSeeder( $lang );

		$entryIds = [ 'about|en' => $this->page( 'en' ), 'about|de' => $this->page( 'de' ) ];
		$ids      = $seeder->apply( $seeder->plan( $manifest, $entryIds ), $manifest, $entryIds );

		$this->assertSame( $ids['primary|de'], $lang->translationOf( $ids['primary|en'], 'de' ) );
	}

	public function test_each_language_links_to_its_own_page() {
		$manifest = $this->manifest();
		$lang     = new PolylangProvider();
		$seeder   = new NavSeeder( $lang );

		$en       = $this->page( 'en' );
		$de       = $this->page( 'de' );
		$entryIds = [ 'about|en' => $en, 'about|de' => $de ];
		$ids      = $seeder->apply( $seeder->plan( $manifest, $entryIds ), $manifest, $entryIds );

		$this->assertStringContainsString( '"id":' . $de, get_post( $ids['primary|de'] )->post_content );
		$this->assertStringNotContainsString( '"id":' . $en, get_post( $ids['primary|de'] )->post_content );
	}

	/**
	 * NavSeeder::linkTranslationGroups() has the same partial-map guard as
	 * Applier's, fixed for the same reason, in its own method by explicit
	 * decision (see NavSeeder.php's docblock). Force the French nav's create
	 * to fail while English and German succeed, and assert the 2-of-3 map is
	 * not linked — mirrors ApplierTranslationTest's equivalent for entries.
	 */
	public function test_a_failed_create_does_not_partially_link_the_nav_group() {
		$fr                    = PLL()->model->add_language( [ 'slug' => 'fr', 'name' => 'Français', 'locale' => 'fr_FR', 'flag' => 'fr', 'rtl' => 0, 'term_group' => 2 ] );
		$this->addedLanguageId = $fr instanceof WP_Error ? null : (int) $fr->term_id;
		PLL()->model->clean_languages_cache();

		$manifest = Manifest::fromArray(
			[
				'languages' => [
					'en' => [ 'name' => 'English', 'locale' => 'en_US', 'default' => true ],
					'de' => [ 'name' => 'Deutsch', 'locale' => 'de_DE' ],
					'fr' => [ 'name' => 'Français', 'locale' => 'fr_FR' ],
				],
				'pages'     => [ 'about' => [ 'title' => 'About', 'content' => '' ] ],
				'navs'      => [ 'primary' => [ 'title' => 'Primary', 'items' => [ [ 'entry' => 'about' ] ] ] ],
			],
			get_stylesheet_directory()
		);

		$lang     = new PolylangProvider();
		$seeder   = new NavSeeder( $lang );
		$entryIds = [
			'about|en' => $this->page( 'en' ),
			'about|de' => $this->page( 'de' ),
			'about|fr' => $this->page( 'fr' ),
		];

		// NavSeeder::slugFor() appends the language suffix unconditionally
		// (`docs/WORDPRESS_TRAPS.md`), so 'primary-fr' names the French nav's
		// insert uniquely regardless of default-language status.
		$fail = static function ( $maybe_empty, $postarr ) {
			return ( $postarr['post_name'] ?? '' ) === 'primary-fr' ? true : $maybe_empty;
		};
		add_filter( 'wp_insert_post_empty_content', $fail, 10, 2 );
		$ids = $seeder->apply( $seeder->plan( $manifest, $entryIds ), $manifest, $entryIds );
		remove_filter( 'wp_insert_post_empty_content', $fail, 10 );

		$this->assertArrayNotHasKey( 'primary|fr', $ids, 'Precondition: the French nav create must actually have failed.' );
		$this->assertNotEmpty( $seeder->errors() );

		$this->assertSame(
			0,
			$lang->translationOf( $ids['primary|en'], 'de' ),
			'A 2-of-3 map must not be linked just because the failure left English and German behind.'
		);
	}

	/**
	 * Pins the forced consequence of the untagged-nav fix in
	 * `NavSeeder::languageOf()`.
	 *
	 * Polylang's own delete path removes the language TERM
	 * (`Languages::delete()` -> `TranslatableObject::delete_language()` ->
	 * `wp_delete_object_term_relationships()`), so a nav in a dropped language
	 * is left genuinely untagged — indistinguishable in the database from the
	 * legacy nav the fix exists for. It therefore lands in the default-language
	 * bucket, collides with the real default-language nav, and `duplicates()`
	 * reports it. That is the safe outcome: an errored plan writes nothing
	 * (`NavSeeder::apply()`'s first guard), so the operator is told to delete or
	 * re-key the orphan instead of the seed silently unlinking it. Recorded in
	 * docs/BACKLOG.md as a design question, because the previous behaviour was a
	 * silent `key|''` bucket.
	 */
	public function test_a_nav_orphaned_by_a_dropped_language_is_reported_not_ignored() {
		$fr                    = PLL()->model->add_language( [ 'slug' => 'fr', 'name' => 'Français', 'locale' => 'fr_FR', 'flag' => 'fr', 'rtl' => 0, 'term_group' => 2 ] );
		$this->addedLanguageId = $fr instanceof WP_Error ? null : (int) $fr->term_id;
		PLL()->model->clean_languages_cache();

		$threeLanguages = Manifest::fromArray(
			[
				'languages' => [
					'en' => [ 'name' => 'English', 'locale' => 'en_US', 'default' => true ],
					'de' => [ 'name' => 'Deutsch', 'locale' => 'de_DE' ],
					'fr' => [ 'name' => 'Français', 'locale' => 'fr_FR' ],
				],
				'pages'     => [ 'about' => [ 'title' => 'About', 'content' => '' ] ],
				'navs'      => [ 'primary' => [ 'title' => 'Primary', 'items' => [ [ 'entry' => 'about' ] ] ] ],
			],
			get_stylesheet_directory()
		);

		$lang     = new PolylangProvider();
		$seeder   = new NavSeeder( $lang );
		$entryIds = [
			'about|en' => $this->page( 'en' ),
			'about|de' => $this->page( 'de' ),
			'about|fr' => $this->page( 'fr' ),
		];
		$ids      = $seeder->apply( $seeder->plan( $threeLanguages, $entryIds ), $threeLanguages, $entryIds );
		$this->assertArrayHasKey( 'primary|fr', $ids, 'Precondition: a French nav must exist before the language is dropped.' );

		PLL()->model->delete_language( (int) $this->addedLanguageId );
		$this->addedLanguageId = null;
		PLL()->model->clean_languages_cache();
		clean_post_cache( $ids['primary|fr'] );
		wp_cache_flush();

		$this->assertFalse(
			pll_get_post_language( $ids['primary|fr'] ),
			'Precondition: dropping the language must leave the nav untagged, not tagged with an unconfigured language.'
		);

		$plan = ( new NavSeeder( new PolylangProvider() ) )->plan(
			$this->manifest(),
			[ 'about|en' => $entryIds['about|en'], 'about|de' => $entryIds['about|de'] ]
		);

		$this->assertTrue( $plan->hasErrors() );
		$this->assertStringContainsString( (string) $ids['primary|en'], $plan->errors()[0] );
		$this->assertStringContainsString( (string) $ids['primary|fr'], $plan->errors()[0] );
	}

	public function test_a_submenu_is_written_per_language_with_translated_child_titles() {
		$manifest = Manifest::fromArray(
			[
				'languages' => [
					'en' => [ 'name' => 'English', 'locale' => 'en_US', 'default' => true ],
					'de' => [ 'name' => 'Deutsch', 'locale' => 'de_DE' ],
				],
				'pages'     => [
					'guide' => [ 'title' => 'Guide', 'content' => '' ],
					'faq'   => [ 'title' => 'FAQ', 'content' => '', 'languages' => [ 'de' => [ 'title' => 'Häufige Fragen' ] ] ],
				],
				'navs'      => [
					'primary' => [
						'title' => 'Primary',
						'items' => [ [ 'entry' => 'guide', 'children' => [ [ 'entry' => 'faq' ] ] ] ],
					],
				],
			],
			get_stylesheet_directory()
		);

		$ids = [
			'guide|en' => $this->titledPage( 'Guide', 'en' ),
			'faq|en'   => $this->titledPage( 'FAQ', 'en' ),
			'guide|de' => $this->titledPage( 'Guide', 'de' ),
			'faq|de'   => $this->titledPage( 'Häufige Fragen', 'de' ),
		];

		$seeder = new NavSeeder( new PolylangProvider() );
		$navIds = $seeder->apply( $seeder->plan( $manifest, $ids ), $manifest, $ids );

		$this->assertSame( [], $seeder->errors() );

		$en = get_post( $navIds['primary|en'] )->post_content;
		$de = get_post( $navIds['primary|de'] )->post_content;

		$this->assertSame( 1, substr_count( $en, '<!-- wp:navigation-submenu ' ) );
		$this->assertSame( 1, substr_count( $de, '<!-- wp:navigation-submenu ' ) );
		$this->assertStringContainsString( '"label":"FAQ"', $en );

		// Stored escaped: wp_json_encode() is called without JSON_UNESCAPED_UNICODE,
		// so a non-ASCII label lands in post_content as its \uXXXX form. Asserting
		// on the escaped bytes is asserting on what a re-serialization must match.
		$this->assertStringContainsString( '"label":"H\\u00e4ufige Fragen"', $de, 'the child label comes from the German title, not the English one' );
		$this->assertStringNotContainsString( '"label":"FAQ"', $de );
		$this->assertStringContainsString( '"id":' . $ids['faq|de'], $de );
	}

	/** A page with a chosen title, tagged into one language and carrying no seed key. */
	private function titledPage( string $title, string $language ): int {
		$id = self::factory()->post->create( [ 'post_type' => 'page', 'post_title' => $title ] );
		pll_set_post_language( $id, $language );
		return $id;
	}

	private function page( string $language ): int {
		$id = self::factory()->post->create( [ 'post_type' => 'page', 'post_title' => 'About ' . $language ] );
		pll_set_post_language( $id, $language );
		update_post_meta( $id, \Pediment\Seeder\Meta::KEY, 'about' );
		return $id;
	}

	private function manifest(): Manifest {
		return Manifest::fromArray(
			[
				'languages' => [ 'en' => [ 'name' => 'English', 'locale' => 'en_US', 'default' => true ], 'de' => [ 'name' => 'Deutsch', 'locale' => 'de_DE' ] ],
				'pages'     => [ 'about' => [ 'title' => 'About', 'content' => '' ] ],
				'navs'      => [ 'primary' => [ 'title' => 'Primary', 'items' => [ [ 'entry' => 'about' ] ] ] ],
			],
			get_stylesheet_directory()
		);
	}
}
