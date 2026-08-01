<?php

use Pediment\Seeder\Meta;

class NavBindingTest extends PolylangTestCase {

	private int $en;
	private int $de;

	/** @var PLL_Language|false */
	private $originalCurlang;

	/** term_id of a language added mid-test, so tear_down() can remove it again. */
	private ?int $addedLanguageId = null;

	public function set_up(): void {
		parent::set_up();

		$this->originalCurlang = PLL()->curlang;

		$this->en = $this->nav( 'en', 'Primary EN' );
		// German is created LAST on purpose: core's fallback picks the most
		// recently created wp_navigation, so an unbound block renders this one
		// in every language. That is the bug under test.
		$this->de = $this->nav( 'de', 'Primary DE' );
	}

	private function nav( string $language, string $title ): int {
		$id = self::factory()->post->create( [ 'post_type' => 'wp_navigation', 'post_title' => $title, 'post_status' => 'publish' ] );
		pll_set_post_language( $id, $language );
		update_post_meta( $id, Meta::KEY, 'primary' );
		return $id;
	}

	/**
	 * Switch Polylang's notion of the current language.
	 *
	 * `add_filter( 'pll_current_language', ... )` looks like the obvious way
	 * to do this, but does nothing on the Polylang version this suite runs
	 * against: pll_current_language() reads `PLL()->curlang` directly and
	 * never routes it through apply_filters(). Setting the property is what
	 * every real request path (frontend, REST, admin) does to establish it,
	 * so it is also the correct thing for a test to do.
	 */
	private function switchTo( string $language ): void {
		PLL()->curlang = PLL()->model->get_language( $language );
	}

	private function bind( string $currentLanguage ): array {
		$this->switchTo( $currentLanguage );

		return pediment_bind_navigation_ref( [ 'blockName' => 'core/navigation', 'attrs' => [] ] );
	}

	public function tear_down(): void {
		// The per-test DB transaction rolls back the language TERM a test adds,
		// but PLL_Model's language list is a plain PHP object cache, untouched
		// by that rollback (the same class of leak PolylangTestCase's own
		// docblock warns about, in the opposite direction: there it is the DB
		// being wiped out from under the cache, here it would be the cache
		// outliving the DB). delete_language() purges that cache itself, so a
		// language added mid-test never survives into a later test class in
		// the same process.
		if ( null !== $this->addedLanguageId ) {
			PLL()->model->delete_language( $this->addedLanguageId );
			$this->addedLanguageId = null;
		}

		PLL()->curlang = $this->originalCurlang;
		parent::tear_down();
	}

	public function test_english_gets_the_english_menu() {
		$this->assertSame( $this->en, (int) $this->bind( 'en' )['attrs']['ref'] );
	}

	public function test_german_gets_the_german_menu() {
		$this->assertSame( $this->de, (int) $this->bind( 'de' )['attrs']['ref'] );
	}

	public function test_an_explicit_ref_is_never_overridden() {
		$block = pediment_bind_navigation_ref( [ 'blockName' => 'core/navigation', 'attrs' => [ 'ref' => 4242 ] ] );

		$this->assertSame( 4242, $block['attrs']['ref'] );
	}

	public function test_other_blocks_are_untouched() {
		$block = [ 'blockName' => 'core/paragraph', 'attrs' => [] ];

		$this->assertSame( $block, pediment_bind_navigation_ref( $block ) );
	}

	public function test_a_language_with_no_menu_falls_back_to_the_default() {
		// A real third language, properly configured in Polylang, but with no
		// navigation seeded for it yet (e.g. just added in Settings, seeding not
		// re-run) — not merely an unrecognised slug, which pll_current_language()
		// would already normalise away before this function ever sees it.
		$fr = PLL()->model->add_language( [ 'slug' => 'fr', 'name' => 'Français', 'locale' => 'fr_FR', 'flag' => 'fr', 'rtl' => 0, 'term_group' => 2 ] );
		$this->addedLanguageId = $fr instanceof WP_Error ? null : (int) $fr->term_id;
		PLL()->model->clean_languages_cache();

		$this->switchTo( 'fr' );

		// No French menu exists; rendering nothing would strip the header's
		// navigation outright, which is strictly worse than the wrong language.
		$this->assertSame( $this->en, (int) pediment_bind_navigation_ref( [ 'blockName' => 'core/navigation', 'attrs' => [] ] )['attrs']['ref'] );
	}

	public function test_an_untagged_legacy_nav_is_found_by_the_unscoped_fallback() {
		// Model a site where NEITHER the current nor the default language has
		// a correctly language-tagged 'primary' nav — remove this class's
		// usual en/de fixtures so candidates 1 and 2 both come up empty — but
		// an untagged 'primary'-keyed nav exists: mid-migration from a
		// pre-Task-11 single nav, or one created by hand and never assigned a
		// language. The unscoped ('') candidate is the safety net for exactly
		// this gap.
		wp_delete_post( $this->en, true );
		wp_delete_post( $this->de, true );

		$legacy = self::factory()->post->create( [ 'post_type' => 'wp_navigation', 'post_title' => 'Legacy Primary', 'post_status' => 'publish' ] );
		update_post_meta( $legacy, Meta::KEY, 'primary' );

		// `wp_navigation` is a translated post type (inc/polylang-compat.php),
		// so PLL_CRUD_Posts::save_post() auto-assigns the default language to
		// ANY post of a translated type saved without one — the factory's
		// create() call above already left $legacy tagged 'en', not untagged.
		// A genuinely pre-Polylang legacy nav has no language term at all, so
		// the auto-assignment has to be undone explicitly to model that state;
		// without this line the scoped 'en' candidate finds the post first and
		// the unscoped ('') candidate this test means to exercise is never
		// reached — which is why earlier attempts at this test passed against
		// both the fixed and the pre-fix code and proved nothing.
		wp_delete_object_term_relationships( $legacy, 'language' );

		$this->switchTo( 'en' );

		$this->assertSame( $legacy, (int) pediment_bind_navigation_ref( [ 'blockName' => 'core/navigation', 'attrs' => [] ] )['attrs']['ref'] );
	}

	/**
	 * White-box companion to the test above.
	 *
	 * Polylang's `PLL_Query::is_already_filtered()` (src/query.php) decides
	 * whether a query is already language-scoped by `isset( $qvars['lang'] )`
	 * alone — an empty string counts, an absent key does not. Omitting the
	 * key for the unscoped candidate therefore hands Polylang's own
	 * current-language auto-scoping a query it thinks nobody has claimed yet,
	 * which is what test_an_untagged_legacy_nav_is_found_by_the_unscoped_fallback()
	 * above exercises end to end. This is a narrower, supplementary check of
	 * the same fix: it asserts pediment_seeded_nav_id( '' ) sends `lang => ''`
	 * explicitly rather than omitting the key, which is the literal condition
	 * the source-level reasoning above turns on, independent of any particular
	 * fixture's data.
	 */
	public function test_the_unscoped_candidate_queries_with_lang_set_not_omitted() {
		$seen = null;

		$capture = static function ( WP_Query $query ) use ( &$seen ) {
			if ( 'wp_navigation' === ( $query->query_vars['post_type'] ?? null ) ) {
				$seen = array_key_exists( 'lang', $query->query_vars ) ? $query->query_vars['lang'] : '__OMITTED__';
			}
		};
		add_action( 'pre_get_posts', $capture );

		pediment_seeded_nav_id( '' );

		remove_action( 'pre_get_posts', $capture );

		$this->assertSame( '', $seen, "pediment_seeded_nav_id( '' ) must pass lang => '' explicitly; '__OMITTED__' means the key was left unset." );
	}
}
