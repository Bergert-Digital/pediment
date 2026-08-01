<?php

class HarnessTest extends WP_UnitTestCase {

	public function test_polylang_is_loaded() {
		$this->assertTrue( function_exists( 'pll_languages_list' ), 'Polylang did not load.' );
		$this->assertInstanceOf( 'PLL_Base', PLL() );
	}

	public function test_two_languages_are_configured_default_first() {
		$this->assertSame( [ 'en', 'de' ], pll_languages_list() );
		$this->assertSame( 'en', pll_default_language() );
	}

	public function test_a_post_can_be_tagged_and_read_back() {
		$id = self::factory()->post->create( [ 'post_type' => 'page' ] );
		pll_set_post_language( $id, 'de' );

		$this->assertSame( 'de', pll_get_post_language( $id ) );
	}

	public function test_a_language_scoped_query_hides_the_other_language() {
		$en = self::factory()->post->create( [ 'post_type' => 'page' ] );
		$de = self::factory()->post->create( [ 'post_type' => 'page' ] );
		pll_set_post_language( $en, 'en' );
		pll_set_post_language( $de, 'de' );

		$scoped = get_posts( [ 'post_type' => 'page', 'numberposts' => -1, 'fields' => 'ids', 'lang' => 'en' ] );

		$this->assertContains( $en, $scoped );
		$this->assertNotContains( $de, $scoped, 'Polylang is not scoping queries — the adapter has nothing to escape.' );
	}
}
