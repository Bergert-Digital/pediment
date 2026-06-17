<?php

class SeedDemoLogoTest extends WP_UnitTestCase {
	public function set_up(): void {
		parent::set_up();
		remove_theme_mod( 'custom_logo' );
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

	public function test_seed_sideloads_demo_logo_and_sets_custom_logo_theme_mod() {
		$id = pediment_seed_demo_logo();

		$this->assertGreaterThan( 0, $id, 'Seed must return a positive attachment ID.' );
		$attachment = get_post( $id );
		$this->assertInstanceOf( WP_Post::class, $attachment );
		$this->assertSame( 'image/svg+xml', $attachment->post_mime_type );
		$this->assertSame( '1', get_post_meta( $id, '_pediment_seed_demo_logo', true ) );
		$this->assertSame( $id, (int) get_theme_mod( 'custom_logo', 0 ) );
	}

	public function test_seed_demo_logo_is_idempotent() {
		$first  = pediment_seed_demo_logo();
		$second = pediment_seed_demo_logo();

		$this->assertSame( $first, $second, 'Second call must return the same attachment.' );

		$attachments = get_posts(
			array(
				'post_type'   => 'attachment',
				'post_status' => 'inherit',
				'numberposts' => -1,
				'fields'      => 'ids',
				'meta_key'    => '_pediment_seed_demo_logo',
				'meta_value'  => '1',
			)
		);
		$this->assertCount( 1, $attachments, 'Idempotent seed must not create a duplicate attachment.' );
	}

	public function test_idempotent_call_restores_drifted_theme_mod() {
		$id = pediment_seed_demo_logo();
		set_theme_mod( 'custom_logo', 0 );

		$second = pediment_seed_demo_logo();

		$this->assertSame( $id, $second, 'Second call must return the existing attachment, not create a new one.' );
		$this->assertSame( $id, (int) get_theme_mod( 'custom_logo', 0 ), 'Theme mod must be restored to the existing attachment.' );
	}

	public function test_seed_run_invokes_demo_logo_seed() {
		pediment_seed_run();
		$this->assertGreaterThan( 0, (int) get_theme_mod( 'custom_logo', 0 ) );
	}

	public function test_seeded_logo_stores_svg_dimensions_for_editor() {
		$id   = pediment_seed_demo_logo();
		$meta = wp_get_attachment_metadata( $id );

		// The Site Logo block sizes the image in the editor from the attachment's
		// width/height (exposed via REST media_details). SVGs carry no intrinsic
		// dimensions unless we store them, which is why the logo renders on the
		// front end but collapses to 0x0 (invisible) in the Site Editor.
		$this->assertIsArray( $meta, 'SVG attachment must carry metadata.' );
		$this->assertArrayHasKey( 'width', $meta );
		$this->assertArrayHasKey( 'height', $meta );
		$this->assertSame( 150, (int) $meta['width'], 'Width must match the SVG intrinsic width.' );
		$this->assertSame( 48, (int) $meta['height'], 'Height must match the SVG intrinsic height.' );
	}

	public function test_idempotent_call_backfills_missing_dimensions() {
		$id = pediment_seed_demo_logo();
		// Simulate a logo seeded before dimension metadata was stored (e.g. a
		// production DB imported from an older seed).
		wp_update_attachment_metadata( $id, array() );

		$second = pediment_seed_demo_logo();
		$meta   = wp_get_attachment_metadata( $second );

		$this->assertSame( $id, $second );
		$this->assertSame( 150, (int) $meta['width'], 'Re-seed must backfill the missing width.' );
		$this->assertSame( 48, (int) $meta['height'], 'Re-seed must backfill the missing height.' );
	}
}
