<?php
/**
 * Shared base for every WP_UnitTestCase in tests/polylang/.
 *
 * WordPress core's WP_UnitTestCase_Base::tear_down_after_class() runs once
 * per test CLASS, after all of that class's own tests finish, and calls
 * `_delete_all_data()` — which deletes every taxonomy term except term_id 1
 * — then `self::commit_transaction()`, so the deletion is NOT undone by the
 * usual per-test rollback. That permanently wipes the en/de language terms
 * bootstrap.php seeds, for every class except whichever one PHPUnit happens
 * to run first (test classes are discovered alphabetically by default).
 * Polylang's own in-memory language cache survives the wipe — it is plain
 * PHP state, untouched by a DB DELETE — so pll_languages_list() keeps
 * reporting en/de as if nothing happened, while pll_set_post_language()
 * silently no-ops against term IDs that no longer exist in the database.
 *
 * Every class in this directory must extend this instead of extending
 * WP_UnitTestCase directly, so each class reseeds itself when it isn't the
 * one that happened to run first. This is a different problem from
 * bootstrap.php's pre-`init` seeding (which exists so Polylang sees
 * languages before it picks its context on `plugins_loaded`, once, for the
 * whole process) — the two are not interchangeable and this class does not
 * replace that seeding, only repairs what a later class's predecessor wiped.
 */

require_once __DIR__ . '/language-definitions.php';

abstract class PolylangTestCase extends WP_UnitTestCase {
	/**
	 * @param WP_UnitTest_Factory $factory
	 */
	public static function wpSetUpBeforeClass( $factory ): void {
		if ( [] !== get_terms( [ 'taxonomy' => 'language', 'hide_empty' => false, 'fields' => 'ids' ] ) ) {
			return;
		}

		$seed_model = new PLL_Model( new \WP_Syntex\Polylang\Options\Options() );

		foreach ( pediment_test_language_definitions() as $language ) {
			$seed_model->add_language( $language );
		}

		PLL()->options->merge( [ 'default_lang' => 'en', 'hide_default' => 1, 'force_lang' => 1, 'media_support' => 0 ] );
		PLL()->options->save();
		PLL()->model->clean_languages_cache();
	}
}
