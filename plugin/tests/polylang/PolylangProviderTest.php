<?php

use Pediment\Language\PolylangProvider;

class PolylangProviderTest extends PolylangTestCase {

	private PolylangProvider $provider;

	public function set_up(): void {
		parent::set_up();
		$this->provider = new PolylangProvider();
	}

	public function test_is_active_when_languages_are_configured() {
		$this->assertTrue( PolylangProvider::isActive() );
	}

	public function test_languages_are_listed_default_first() {
		$this->assertSame( [ 'en', 'de' ], $this->provider->languages() );
		$this->assertSame( 'en', $this->provider->defaultLanguage() );
	}

	/**
	 * Not in the brief verbatim, added because it is otherwise untested: the
	 * fixture's term_group ordering (en=0, de=1) happens to already agree
	 * with the default language, so the assertion above would pass even if
	 * languages() just relayed pll_languages_list() unmodified. Flip the
	 * default to 'de' — Polylang's own list still comes back en-first — and
	 * check languages() actually reorders around it.
	 */
	public function test_languages_reorders_around_the_default_when_polylang_does_not() {
		PLL()->options->set( 'default_lang', 'de' );
		PLL()->options->save();
		PLL()->model->clean_languages_cache();

		try {
			$this->assertSame( [ 'en', 'de' ], pll_languages_list(), "Precondition failed: Polylang's own order was expected to stay en-first." );
			$this->assertSame( 'de', $this->provider->defaultLanguage() );
			$this->assertSame( [ 'de', 'en' ], $this->provider->languages(), 'languages() must reorder around the configured default.' );
		} finally {
			PLL()->options->set( 'default_lang', 'en' );
			PLL()->options->save();
			PLL()->model->clean_languages_cache();
		}
	}

	public function test_set_language_tags_a_post() {
		$id = self::factory()->post->create( [ 'post_type' => 'page' ] );
		$this->provider->setLanguage( $id, 'de' );

		$this->assertSame( 'de', pll_get_post_language( $id ) );
	}

	public function test_translation_of_returns_the_post_itself_for_its_own_language() {
		$id = self::factory()->post->create( [ 'post_type' => 'page' ] );
		$this->provider->setLanguage( $id, 'de' );

		$this->assertSame( $id, $this->provider->translationOf( $id, 'de' ) );
	}

	public function test_has_language_is_true_once_tagged() {
		$id = self::factory()->post->create( [ 'post_type' => 'page' ] );
		$this->provider->setLanguage( $id, 'de' );

		$this->assertTrue( $this->provider->hasLanguage( $id ) );
	}

	/**
	 * `pll_get_post_language()` returns `false` for an untagged post — the
	 * signal this method is built on. A post created straight through
	 * `wp_insert_post()` outside this suite's factories would already be
	 * auto-tagged by Polylang's own save hook (WORDPRESS_TRAPS.md), so
	 * modelling "genuinely untagged" means stripping the language term back
	 * off after creation, the same way NavBindingTest does for a legacy nav.
	 */
	public function test_has_language_is_false_for_an_untagged_post() {
		$id = self::factory()->post->create( [ 'post_type' => 'page' ] );
		wp_delete_object_term_relationships( $id, 'language' );

		$this->assertFalse( $this->provider->hasLanguage( $id ) );
	}

	public function test_translation_of_returns_zero_when_there_is_none() {
		$id = self::factory()->post->create( [ 'post_type' => 'page' ] );
		$this->provider->setLanguage( $id, 'en' );

		$this->assertSame( 0, $this->provider->translationOf( $id, 'de' ) );
	}

	public function test_link_translations_makes_each_side_findable_from_the_other() {
		$en = self::factory()->post->create( [ 'post_type' => 'page' ] );
		$de = self::factory()->post->create( [ 'post_type' => 'page' ] );
		$this->provider->setLanguage( $en, 'en' );
		$this->provider->setLanguage( $de, 'de' );

		$this->provider->linkTranslations( [ 'en' => $en, 'de' => $de ] );

		$this->assertSame( $de, $this->provider->translationOf( $en, 'de' ) );
		$this->assertSame( $en, $this->provider->translationOf( $de, 'en' ) );
	}

	/**
	 * Not "the 0 is ignored" — Polylang's own sanitize_int_ids_list() already
	 * drops a 0 regardless of any guard here, so
	 * assertSame( 0, translationOf( $en, 'de' ) ) would pass identically with
	 * the count($clean) < 2 guard deleted, and this test could never fail.
	 * The guard exists to stop a map with fewer than 2 real IDs from ever
	 * reaching pll_save_post_translations() at all — otherwise a single
	 * resolved ID would still be handed off, and Polylang's own
	 * save_translations() would replace whatever group that post already
	 * belonged to with a "group of one," unlinking every language that
	 * really was there. Assert on that: link a real two-language group
	 * first, then call linkTranslations() again with one ID unresolved, and
	 * check the EXISTING link survives.
	 */
	public function test_link_translations_does_not_unlink_an_existing_group_when_only_one_id_resolves() {
		$en = self::factory()->post->create( [ 'post_type' => 'page' ] );
		$de = self::factory()->post->create( [ 'post_type' => 'page' ] );
		$this->provider->setLanguage( $en, 'en' );
		$this->provider->setLanguage( $de, 'de' );
		$this->provider->linkTranslations( [ 'en' => $en, 'de' => $de ] );

		// Simulate a later run where German's ID failed to resolve (e.g. its
		// write failed this time).
		$this->provider->linkTranslations( [ 'en' => $en, 'de' => 0 ] );

		$this->assertSame(
			$de,
			$this->provider->translationOf( $en, 'de' ),
			'A single resolved ID must not replace an existing translation group with a group of one.'
		);
	}

	public function test_unscoped_query_sees_every_language() {
		$en = self::factory()->post->create( [ 'post_type' => 'page' ] );
		$de = self::factory()->post->create( [ 'post_type' => 'page' ] );
		$this->provider->setLanguage( $en, 'en' );
		$this->provider->setLanguage( $de, 'de' );

		$found = get_posts(
			$this->provider->unscopedQuery(
				[ 'post_type' => 'page', 'numberposts' => -1, 'fields' => 'ids', 'lang' => 'en' ]
			)
		);

		$this->assertContains( $en, $found );
		$this->assertContains( $de, $found, 'unscopedQuery() did not escape the language scoping.' );
	}

	public function test_suppress_filters_alone_does_not_escape_the_scoping() {
		// The regression that cost dd23712. If this ever starts passing with
		// suppress_filters alone, Polylang changed and the comment in
		// unscopedQuery() is stale — but do NOT remove the `lang` key on that
		// basis; WPML still needs suppress_filters.
		$en = self::factory()->post->create( [ 'post_type' => 'page' ] );
		$de = self::factory()->post->create( [ 'post_type' => 'page' ] );
		$this->provider->setLanguage( $en, 'en' );
		$this->provider->setLanguage( $de, 'de' );

		$found = get_posts(
			[ 'post_type' => 'page', 'numberposts' => -1, 'fields' => 'ids', 'lang' => 'en', 'suppress_filters' => true ]
		);

		$this->assertNotContains( $de, $found );
	}
}
