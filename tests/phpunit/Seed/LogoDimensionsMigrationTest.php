<?php

class LogoDimensionsMigrationTest extends WP_UnitTestCase {
	public function set_up(): void {
		parent::set_up();
		remove_theme_mod( 'custom_logo' );
		delete_option( 'pediment_logo_svg_dims_migrated' );
		$existing = get_posts(
			array(
				'post_type'   => 'attachment',
				'post_status' => 'inherit',
				'numberposts' => -1,
				'fields'      => 'ids',
				'meta_key'    => '_pediment_seed_demo_logo',
				'meta_value'  => '1',
			)
		);
		foreach ( $existing as $id ) {
			wp_delete_attachment( (int) $id, true );
		}
	}

	/** Build the broken state: an SVG custom_logo whose metadata has no dimensions. */
	private function seed_dimensionless_logo(): int {
		$id = pediment_seed_demo_logo();
		wp_update_attachment_metadata( $id, array() );
		return $id;
	}

	public function test_migration_backfills_dimensionless_svg_logo() {
		$id = $this->seed_dimensionless_logo();

		pediment_migrate_logo_svg_dimensions();

		$meta = wp_get_attachment_metadata( $id );
		$this->assertSame( 150, (int) $meta['width'], 'Migration must backfill the SVG width on the live logo.' );
		$this->assertSame( 48, (int) $meta['height'], 'Migration must backfill the SVG height on the live logo.' );
	}

	public function test_migration_is_one_shot() {
		$id = $this->seed_dimensionless_logo();
		pediment_migrate_logo_svg_dimensions();
		$this->assertSame( '1', get_option( 'pediment_logo_svg_dims_migrated' ), 'Migration must record a guard option.' );

		// Strip dimensions again; a second run must NOT touch the attachment.
		wp_update_attachment_metadata( $id, array() );
		pediment_migrate_logo_svg_dimensions();
		$meta = wp_get_attachment_metadata( $id );

		$this->assertEmpty( $meta, 'Guarded migration must not run a second time.' );
	}

	public function test_migration_handles_missing_logo_without_error() {
		// No custom_logo set.
		pediment_migrate_logo_svg_dimensions();

		$this->assertSame( '1', get_option( 'pediment_logo_svg_dims_migrated' ), 'Migration must still record the guard so it does not re-query every request.' );
	}

	public function test_migration_runs_on_admin_init() {
		$this->assertSame( 10, has_action( 'admin_init', 'pediment_migrate_logo_svg_dimensions' ) );
	}
}
