<?php

use Pediment\Seeder\Meta;

class NavBindingTest extends PolylangTestCase {

	private int $en;
	private int $de;

	/** @var PLL_Language|false */
	private $originalCurlang;

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
		PLL()->model->add_language( [ 'slug' => 'fr', 'name' => 'Français', 'locale' => 'fr_FR', 'flag' => 'fr', 'rtl' => 0, 'term_group' => 2 ] );
		PLL()->model->clean_languages_cache();

		$this->switchTo( 'fr' );

		// No French menu exists; rendering nothing would strip the header's
		// navigation outright, which is strictly worse than the wrong language.
		$this->assertSame( $this->en, (int) pediment_bind_navigation_ref( [ 'blockName' => 'core/navigation', 'attrs' => [] ] )['attrs']['ref'] );
	}
}
