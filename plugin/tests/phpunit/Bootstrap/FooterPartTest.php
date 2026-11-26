<?php
/**
 * The footer is a DB-backed template part, seeded like the header.
 */
class FooterPartTest extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();
		// The plugin's own `init` run seeded a footer part when the suite booted.
		foreach ( $this->footerParts() as $part ) {
			wp_delete_post( $part->ID, true );
		}
		delete_option( PEDIMENT_FOOTER_SEEDED_OPTION );
	}

	public function tear_down(): void {
		foreach ( array( get_stylesheet() . '/footer' ) as $slug ) {
			if ( WP_Block_Patterns_Registry::get_instance()->is_registered( $slug ) ) {
				unregister_block_pattern( $slug );
			}
		}
		parent::tear_down();
	}

	/** @return WP_Post[] */
	private function footerParts(): array {
		return get_posts(
			array(
				'post_type'     => 'wp_template_part',
				'post_name__in' => array( 'footer' ),
				'post_status'   => 'publish',
				'numberposts'   => -1,
				// phpcs:ignore WordPress.DB.SlowDBQuery -- test lookup.
				'tax_query'     => array(
					array(
						'taxonomy' => 'wp_theme',
						'field'    => 'name',
						'terms'    => get_stylesheet(),
					),
				),
			)
		);
	}

	private function footerPart(): WP_Post {
		$parts = $this->footerParts();
		$this->assertCount( 1, $parts, 'exactly one footer part should exist' );
		return $parts[0];
	}

	/**
	 * Replaces the registered `pediment/footer` with a theme's own pattern
	 * of that slug for one callback, then restores the plugin's.
	 */
	private function withThemeOverride( string $content, callable $run ): void {
		$registry = WP_Block_Patterns_Registry::get_instance();
		$plugin   = $registry->get_registered( 'pediment/footer' );
		unregister_block_pattern( 'pediment/footer' );
		register_block_pattern( 'pediment/footer', array( 'title' => 'Footer', 'content' => $content ) );
		try {
			$run();
		} finally {
			unregister_block_pattern( 'pediment/footer' );
			register_block_pattern( 'pediment/footer', $plugin );
		}
	}

	public function test_bootstrap_seeds_the_default_footer_with_the_legal_links(): void {
		pediment_bootstrap();

		$part = $this->footerPart();
		$this->assertSame( 'Footer', $part->post_title );
		$this->assertSame( array( 'footer' ), wp_get_object_terms( $part->ID, 'wp_template_part_area', array( 'fields' => 'names' ) ) );
		$this->assertStringContainsString( '<!-- wp:pediment/legal-links', $part->post_content );
		$this->assertStringContainsString( '<!-- wp:site-title', $part->post_content );
		$this->assertStringNotContainsString( 'example.com', $part->post_content );
		$this->assertStringNotContainsString( 'Senior-led work', $part->post_content );
		$this->assertSame( get_stylesheet(), get_option( PEDIMENT_FOOTER_SEEDED_OPTION ) );
	}

	public function test_a_theme_footer_pattern_supplies_the_initial_markup(): void {
		register_block_pattern(
			get_stylesheet() . '/footer',
			array(
				'title'   => 'Footer',
				'content' => '<!-- wp:paragraph --><p>Branded footer</p><!-- /wp:paragraph -->',
			)
		);

		pediment_bootstrap_footer_template_part();

		$this->assertStringContainsString( 'Branded footer', $this->footerPart()->post_content );
	}

	public function test_a_theme_that_replaced_pediment_footer_keeps_its_footer(): void {
		$this->withThemeOverride(
			'<!-- wp:paragraph --><p>Footer from the theme override</p><!-- /wp:paragraph -->',
			static function (): void {
				pediment_bootstrap_footer_template_part();
			}
		);

		$this->assertStringContainsString( 'Footer from the theme override', $this->footerPart()->post_content );
	}

	public function test_a_footer_pattern_that_points_back_at_the_part_is_skipped(): void {
		register_block_pattern(
			get_stylesheet() . '/footer',
			array(
				'title'   => 'Footer',
				'content' => '<!-- wp:pattern {"slug":"pediment/footer"} /-->',
			)
		);

		pediment_bootstrap_footer_template_part();

		$this->assertStringContainsString( '<!-- wp:pediment/legal-links', $this->footerPart()->post_content );
	}

	public function test_an_existing_footer_part_is_never_overwritten(): void {
		pediment_bootstrap_footer_template_part();
		wp_update_post(
			array(
				'ID'           => $this->footerPart()->ID,
				'post_content' => '<!-- wp:paragraph --><p>Edited footer</p><!-- /wp:paragraph -->',
			)
		);
		delete_option( PEDIMENT_FOOTER_SEEDED_OPTION );

		pediment_bootstrap();
		pediment_bootstrap_maybe_seed_footer();

		$this->assertStringContainsString( 'Edited footer', $this->footerPart()->post_content );
	}

	public function test_an_updated_site_gets_its_footer_part_on_the_next_init(): void {
		$this->assertSame( 100, has_action( 'init', 'pediment_bootstrap_maybe_seed_footer' ) );
		$this->assertSame( array(), $this->footerParts() );

		pediment_bootstrap_maybe_seed_footer();

		$this->assertStringContainsString( '<!-- wp:pediment/legal-links', $this->footerPart()->post_content );
	}

	public function test_the_seeded_check_costs_nothing_once_done(): void {
		pediment_bootstrap_maybe_seed_footer();
		$part = $this->footerPart();
		wp_delete_post( $part->ID, true );

		// Already seeded for this theme: a deleted part is the person's choice.
		pediment_bootstrap_maybe_seed_footer();

		$this->assertSame( array(), $this->footerParts() );
	}

	public function test_the_compat_pattern_renders_the_part(): void {
		pediment_bootstrap_footer_template_part();
		wp_update_post(
			array(
				'ID'           => $this->footerPart()->ID,
				'post_content' => '<!-- wp:paragraph --><p>Footer via the old pattern</p><!-- /wp:paragraph -->',
			)
		);

		$html = do_blocks( '<!-- wp:pattern {"slug":"pediment/footer"} /-->' );

		$this->assertStringContainsString( 'Footer via the old pattern', $html );
		$this->assertStringContainsString( '<footer', $html );
	}
}
