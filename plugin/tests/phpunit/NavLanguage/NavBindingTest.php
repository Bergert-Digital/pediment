<?php

use Pediment\Seeder\Meta;

/**
 * Monolingual coverage for pediment_bind_navigation_ref().
 *
 * Polylang is not loaded in this suite, so pll_current_language() and
 * pll_default_language() do not exist and both resolve to ''. That is
 * exactly the candidate-list edge case the brief's own draft got wrong:
 * array_filter( [ '', '', '' ] ) empties the list and the lookup never
 * runs, leaving a single-language site's header without a bound nav.
 */
class NavBindingTest extends WP_UnitTestCase {

	public function test_a_monolingual_site_binds_to_its_seeded_navigation() {
		$id = self::factory()->post->create( [ 'post_type' => 'wp_navigation', 'post_title' => 'Primary', 'post_status' => 'publish' ] );
		update_post_meta( $id, Meta::KEY, 'primary' );

		$block = pediment_bind_navigation_ref( [ 'blockName' => 'core/navigation', 'attrs' => [] ] );

		$this->assertSame( $id, (int) $block['attrs']['ref'] );
	}

	public function test_an_explicit_ref_is_never_overridden() {
		$block = pediment_bind_navigation_ref( [ 'blockName' => 'core/navigation', 'attrs' => [ 'ref' => 4242 ] ] );

		$this->assertSame( 4242, $block['attrs']['ref'] );
	}

	public function test_nothing_seeded_leaves_the_block_alone() {
		$block = [ 'blockName' => 'core/navigation', 'attrs' => [] ];

		$this->assertSame( $block, pediment_bind_navigation_ref( $block ) );
	}

	public function test_other_blocks_are_untouched() {
		$block = [ 'blockName' => 'core/paragraph', 'attrs' => [] ];

		$this->assertSame( $block, pediment_bind_navigation_ref( $block ) );
	}
}
