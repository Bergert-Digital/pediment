<?php
// tests/phpunit/Bootstrap/BootstrapTest.php
class BootstrapTest extends WP_UnitTestCase {

	public function test_bootstrap_seeds_header_template_part(): void {
		pediment_bootstrap();

		$parts = get_posts(
			array(
				'post_type'   => 'wp_template_part',
				'name'        => 'header',
				'post_status' => 'publish',
				'numberposts' => 1,
				'fields'      => 'ids',
			)
		);
		$this->assertNotEmpty( $parts, 'header template part should exist after bootstrap' );
	}

	public function test_bootstrap_leaves_permalink_structure_untouched(): void {
		// Bootstrap must not force pretty permalinks: in containerized installs
		// that breaks REST (rest_url() -> /wp-json/ 404s). See pediment#47.
		update_option( 'permalink_structure', '' );
		pediment_bootstrap();
		$this->assertSame(
			'',
			(string) get_option( 'permalink_structure' ),
			'bootstrap must not change the site permalink structure'
		);
	}

	public function test_bootstrap_is_idempotent(): void {
		pediment_bootstrap();
		pediment_bootstrap();
		$parts = get_posts(
			array(
				'post_type'   => 'wp_template_part',
				'name'        => 'header',
				'post_status' => 'publish',
				'numberposts' => -1,
				'fields'      => 'ids',
			)
		);
		$this->assertCount( 1, $parts, 'bootstrap must not duplicate the header part' );
	}

	/**
	 * Reproduces the real wp-env sequence: the plugin's bootstrap seeds a header
	 * part while one theme is active (e.g. the core default, active before the
	 * client theme is switched to), then the client theme becomes active and
	 * bootstrap runs again. Each theme must end up with its own header part
	 * whose stored post_name is the literal "header" -- the client theme's
	 * templates/index.html hard-codes {"slug":"header"}, so anything else
	 * (a missing part, or one suffixed "header-2") renders as a broken header.
	 */
	public function test_bootstrap_scopes_header_part_to_each_active_theme(): void {
		$original_theme = get_stylesheet();

		try {
			switch_theme( 'twentytwentyfive' );
			pediment_bootstrap_header_template_part();

			switch_theme( 'pediment-fixture' );
			pediment_bootstrap_header_template_part();

			foreach ( array( 'twentytwentyfive', 'pediment-fixture' ) as $theme ) {
				$parts = get_posts(
					array(
						'post_type'   => 'wp_template_part',
						'post_status' => 'publish',
						'numberposts' => -1,
						'tax_query'   => array(
							array(
								'taxonomy' => 'wp_theme',
								'field'    => 'name',
								'terms'    => $theme,
							),
						),
					)
				);

				$this->assertCount( 1, $parts, "theme \"$theme\" should have exactly one header part" );
				$this->assertSame(
					'header',
					$parts[0]->post_name,
					"the header part scoped to \"$theme\" must keep the literal slug \"header\", not a suffixed or missing one"
				);
			}
		} finally {
			switch_theme( $original_theme );
		}
	}
}
