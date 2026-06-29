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

	public function test_bootstrap_sets_brand_defaults(): void {
		pediment_bootstrap();
		$this->assertNotSame( '', (string) \Pediment\Brand::get( 'brand_tagline', '' ) );
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
}
