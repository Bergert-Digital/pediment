<?php

use Starter\Brand;

class SeedCommandTest extends WP_UnitTestCase {
	public function set_up(): void {
		parent::set_up();
		delete_option( Brand::OPTION );
		foreach ( get_posts( array( 'post_type' => 'page', 'numberposts' => -1 ) ) as $p ) {
			wp_delete_post( $p->ID, true );
		}
	}

	public function test_seed_creates_four_pages() {
		starter_seed_run();

		$slugs = wp_list_pluck(
			get_posts( array( 'post_type' => 'page', 'numberposts' => -1, 'post_status' => 'publish' ) ),
			'post_name'
		);
		$this->assertContains( 'home', $slugs );
		$this->assertContains( 'about', $slugs );
		$this->assertContains( 'contact', $slugs );
		$this->assertContains( 'blog', $slugs );
	}

	public function test_seed_sets_brand_defaults() {
		starter_seed_run();
		$this->assertNotEmpty( Brand::get( 'brand_name' ) );
		$this->assertNotEmpty( Brand::get( 'voice_tone' ) );
	}

	public function test_seed_is_idempotent() {
		starter_seed_run();
		starter_seed_run();
		$count = count(
			get_posts(
				array(
					'post_type'   => 'page',
					'numberposts' => -1,
					'post_status' => 'publish',
					'name'        => 'home',
				)
			)
		);
		$this->assertSame( 1, $count, 'Running seed twice should not duplicate the Home page.' );
	}

	public function test_seed_sets_static_front_page() {
		starter_seed_run();
		$front_id = (int) get_option( 'page_on_front' );
		$this->assertGreaterThan( 0, $front_id );
		$front = get_post( $front_id );
		$this->assertSame( 'home', $front->post_name );
	}
}
