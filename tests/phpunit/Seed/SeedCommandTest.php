<?php

use Pediment\Brand;

class SeedCommandTest extends WP_UnitTestCase {
	public function set_up(): void {
		parent::set_up();
		delete_option( Brand::OPTION );
		foreach ( get_posts( array( 'post_type' => 'page', 'numberposts' => -1 ) ) as $p ) {
			wp_delete_post( $p->ID, true );
		}
	}

	public function test_seed_creates_four_pages() {
		pediment_seed_run();

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
		pediment_seed_run();
		$this->assertNotEmpty( Brand::get( 'brand_name' ) );
		$this->assertNotEmpty( Brand::get( 'voice_tone' ) );
	}

	public function test_seed_is_idempotent() {
		pediment_seed_run();
		pediment_seed_run();
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
		pediment_seed_run();
		$front_id = (int) get_option( 'page_on_front' );
		$this->assertGreaterThan( 0, $front_id );
		$front = get_post( $front_id );
		$this->assertSame( 'home', $front->post_name );
	}

	public function test_blog_page_has_empty_content_so_home_template_renders_listing() {
		pediment_seed_run();
		$blog = get_page_by_path( 'blog' );
		$this->assertInstanceOf( WP_Post::class, $blog );
		$this->assertSame( '', trim( $blog->post_content ), 'Blog page content must be empty; home.html renders the listing.' );
	}
}
