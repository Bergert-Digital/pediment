<?php

class SeedSampleContentTest extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();
		foreach ( get_posts( array( 'post_type' => array( 'page', 'post' ), 'numberposts' => -1, 'post_status' => 'any' ) ) as $p ) {
			wp_delete_post( $p->ID, true );
		}
		foreach ( get_terms( array( 'taxonomy' => 'category', 'hide_empty' => false ) ) as $t ) {
			if ( 'uncategorized' !== $t->slug ) {
				wp_delete_term( $t->term_id, 'category' );
			}
		}
	}

	public function test_home_content_is_the_pattern() {
		do_action( 'init' );
		starter_seed_run();
		$home = get_page_by_path( 'home' );
		$this->assertInstanceOf( WP_Post::class, $home );
		$expected = starter_pediment_landing_content();
		$this->assertNotEmpty( $expected );
		$this->assertSame( $expected, $home->post_content );
		$this->assertStringContainsString( 'wp:starter/hero', $home->post_content );
	}

	public function test_static_front_page_is_home() {
		do_action( 'init' );
		starter_seed_run();
		$this->assertSame( 'page', get_option( 'show_on_front' ) );
		$this->assertSame(
			(int) get_page_by_path( 'home' )->ID,
			(int) get_option( 'page_on_front' )
		);
	}

	public function test_helper_falls_back_when_pattern_unregistered() {
		$registry = WP_Block_Patterns_Registry::get_instance();
		if ( $registry->is_registered( 'starter/pediment-landing' ) ) {
			$registry->unregister( 'starter/pediment-landing' );
		}
		$fallback = starter_pediment_landing_content();
		$this->assertNotEmpty( $fallback );
		$this->assertStringContainsString( 'wp:starter/hero', $fallback );
		$this->assertStringContainsString( 'wp:starter/blog-index', $fallback );
		// Fallback is the minimal stub, NOT the 8-band landing pattern.
		$this->assertStringNotContainsString( 'is-style-band-navy', $fallback );

		// Re-register so later tests/classes see the real pattern again.
		do_action( 'init' );
		$this->assertStringContainsString(
			'is-style-band-navy',
			starter_pediment_landing_content(),
			'pattern must be restored after re-init'
		);
	}
}
