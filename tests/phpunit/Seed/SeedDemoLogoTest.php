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
				'meta_key'    => '_starter_seed_demo_logo',
				'meta_value'  => '1',
			)
		);
		foreach ( $existing as $id ) {
			wp_delete_attachment( (int) $id, true );
		}
	}

	public function test_seed_sideloads_demo_logo_and_sets_custom_logo_theme_mod() {
		$id = starter_seed_demo_logo();

		$this->assertGreaterThan( 0, $id, 'Seed must return a positive attachment ID.' );
		$attachment = get_post( $id );
		$this->assertInstanceOf( WP_Post::class, $attachment );
		$this->assertSame( 'image/svg+xml', $attachment->post_mime_type );
		$this->assertSame( '1', get_post_meta( $id, '_starter_seed_demo_logo', true ) );
		$this->assertSame( $id, (int) get_theme_mod( 'custom_logo', 0 ) );
	}

	public function test_seed_demo_logo_is_idempotent() {
		$first  = starter_seed_demo_logo();
		$second = starter_seed_demo_logo();

		$this->assertSame( $first, $second, 'Second call must return the same attachment.' );

		$attachments = get_posts(
			array(
				'post_type'   => 'attachment',
				'post_status' => 'inherit',
				'numberposts' => -1,
				'fields'      => 'ids',
				'meta_key'    => '_starter_seed_demo_logo',
				'meta_value'  => '1',
			)
		);
		$this->assertCount( 1, $attachments, 'Idempotent seed must not create a duplicate attachment.' );
	}

	public function test_idempotent_call_restores_drifted_theme_mod() {
		$id = starter_seed_demo_logo();
		set_theme_mod( 'custom_logo', 0 );

		$second = starter_seed_demo_logo();

		$this->assertSame( $id, $second, 'Second call must return the existing attachment, not create a new one.' );
		$this->assertSame( $id, (int) get_theme_mod( 'custom_logo', 0 ), 'Theme mod must be restored to the existing attachment.' );
	}

	public function test_seed_run_invokes_demo_logo_seed() {
		starter_seed_run();
		$this->assertGreaterThan( 0, (int) get_theme_mod( 'custom_logo', 0 ) );
	}
}
