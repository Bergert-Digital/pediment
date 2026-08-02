<?php

use Pediment\Language\PolylangSetup;
use Pediment\Seeder\LanguageSpec;

/**
 * Extends PolylangTestCase, not WP_UnitTestCase: this class is the first in
 * the suite to create languages beyond the harness's en/de (it adds 'it'
 * permanently, via a real add_language() call), and PolylangTestCase's
 * wpSetUpBeforeClass() is what makes en/de survive WP core's
 * tear_down_after_class() wiping every language term for whichever class
 * PHPUnit does not happen to run first.
 */
class PolylangSetupTest extends PolylangTestCase {

	/**
	 * Every test in this class either creates a language ('it') that
	 * outlives WP_UnitTestCase's per-test rollback (Polylang's language
	 * cache is plain PHP state, not a DB row, so a rolled-back transaction
	 * does not un-teach PLL() about it) or writes PLL()->options, which is
	 * flushed to the DB on `shutdown` and is therefore also not undone by a
	 * per-test rollback. Left alone, either kind of leftover changes what
	 * "already configured" means for whichever test method — or whichever
	 * later test class in this directory — runs next.
	 *
	 * Rather than lean on test order to dodge that, every test that adds a
	 * language cleans it back up here, and every test that writes options
	 * restores the harness's own baseline. That is what makes it safe to
	 * run this class in a forced non-default order (see the report for the
	 * `--order-by=reverse` run) and get the same result.
	 */
	public function tear_down(): void {
		$model = PLL()->model;

		foreach ( $model->get_languages_list() as $language ) {
			if ( ! in_array( $language->slug, pediment_test_languages(), true ) ) {
				$model->delete_language( $language->term_id );
			}
		}

		// Matches PolylangTestCase::wpSetUpBeforeClass() exactly, plus an
		// explicit reset of the options configure() writes and that call
		// does not touch — post_types/taxonomies/redirect_lang have no
		// fixed "harness default" of their own, so reset() (Polylang's own
		// schema default) is the only meaningful restore.
		PLL()->options->reset( 'post_types' );
		PLL()->options->reset( 'taxonomies' );
		PLL()->options->reset( 'redirect_lang' );
		PLL()->options->merge(
			[
				'default_lang'  => 'en',
				'hide_default'  => 1,
				'force_lang'    => 1,
				'media_support' => 0,
			]
		);
		PLL()->options->save();
		$model->clean_languages_cache();

		parent::tear_down();
	}

	/** @return array<string,LanguageSpec> */
	private function specs(): array {
		return [
			'en' => new LanguageSpec( 'en', 'English', 'en_US', 'gb', true ),
			'de' => new LanguageSpec( 'de', 'Deutsch', 'de_DE', 'de', false ),
		];
	}

	/**
	 * Not "assert the harness's ambient state produces no changes" — that
	 * depends on which test ran before it in this class AND on every other
	 * class in the directory, none of which this test controls. Instead it
	 * configures the site itself, then configures it again, and checks the
	 * second call is a no-op. That is the actual property that makes
	 * `wp pediment languages` safe to run on every `npm run env:setup`, and
	 * it holds regardless of what ran before it.
	 */
	public function test_an_already_configured_site_reports_no_changes() {
		$setup = new PolylangSetup();

		$first = $setup->configure( $this->specs(), 'en' );
		$this->assertSame( [], $first['errors'] );

		$second = $setup->configure( $this->specs(), 'en' );

		$this->assertSame( [], $second['errors'] );
		$this->assertSame( [], $second['changes'], 'A second configure() call against an already-configured site must write nothing.' );
	}

	public function test_a_dry_run_reports_a_missing_language_without_creating_it() {
		$specs       = $this->specs();
		$specs['fr'] = new LanguageSpec( 'fr', 'Français', 'fr_FR', 'fr', false );

		$result = ( new PolylangSetup() )->configure( $specs, 'en', true );

		$this->assertNotEmpty( $result['changes'] );
		$this->assertStringContainsString( 'fr', implode( "\n", $result['changes'] ) );
		$this->assertNotContains( 'fr', pll_languages_list(), 'A dry run wrote a language.' );
	}

	public function test_wp_navigation_is_translatable() {
		( new PolylangSetup() )->configure( $this->specs(), 'en' );

		$this->assertContains( 'wp_navigation', (array) PLL()->options['post_types'] );
	}

	public function test_media_and_taxonomies_are_not_translated() {
		( new PolylangSetup() )->configure( $this->specs(), 'en' );

		$this->assertSame( 0, (int) PLL()->options['media_support'] );
		$this->assertSame( [], (array) PLL()->options['taxonomies'] );
	}

	public function test_language_roots_serve_the_front_page() {
		( new PolylangSetup() )->configure( $this->specs(), 'en' );

		$this->assertSame( 1, (int) PLL()->options['redirect_lang'] );
	}

	public function test_a_missing_language_is_created() {
		$specs       = $this->specs();
		$specs['it'] = new LanguageSpec( 'it', 'Italiano', 'it_IT', 'it', false );

		$result = ( new PolylangSetup() )->configure( $specs, 'en' );

		$this->assertSame( [], $result['errors'] );
		$this->assertContains( 'it', pll_languages_list() );
	}

	public function test_it_refuses_to_run_without_polylang_configured_state() {
		$result = ( new PolylangSetup() )->configure( [], 'en' );

		$this->assertNotEmpty( $result['errors'] );
	}
}
