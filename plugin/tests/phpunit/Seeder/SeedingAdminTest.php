<?php
// plugin/tests/phpunit/Seeder/SeedingAdminTest.php

class SeedingAdminTest extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();
		$GLOBALS['pediment_settings_tabs'] = [];
		add_filter(
			'pediment_seed_manifest',
			static fn() => [ 'pages' => [ 'home' => [ 'title' => 'Home', 'content' => '<p>hi</p>' ] ] ]
		);
	}

	public function tear_down(): void {
		remove_all_filters( 'pediment_seed_manifest' );
		unset( $_POST['pediment_seed_action'], $_POST['_wpnonce'] );
		parent::tear_down();
	}

	public function test_the_seeding_tab_is_registered() {
		do_action( 'admin_menu' );

		$this->assertArrayHasKey( 'seed', pediment_settings_get_tabs() );
	}

	public function test_a_preview_submission_returns_a_plan_without_writing() {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$_POST['pediment_seed_action'] = 'preview';
		$_POST['_wpnonce']             = wp_create_nonce( 'pediment_seed' );

		$report = pediment_seed_admin_handle_post();

		$this->assertStringContainsString( 'dry run', $report );
		$this->assertSame( [], get_posts( [ 'post_type' => 'page', 'fields' => 'ids' ] ) );
	}

	public function test_an_apply_submission_writes() {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$_POST['pediment_seed_action'] = 'apply';
		$_POST['_wpnonce']             = wp_create_nonce( 'pediment_seed' );

		pediment_seed_admin_handle_post();

		$this->assertCount( 1, get_posts( [ 'post_type' => 'page', 'fields' => 'ids' ] ) );
	}

	public function test_a_subscriber_cannot_seed() {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'subscriber' ] ) );
		$_POST['pediment_seed_action'] = 'apply';
		$_POST['_wpnonce']             = wp_create_nonce( 'pediment_seed' );

		$this->assertNull( pediment_seed_admin_handle_post() );
		$this->assertSame( [], get_posts( [ 'post_type' => 'page', 'fields' => 'ids' ] ) );
	}

	public function test_a_bad_nonce_is_rejected() {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$_POST['pediment_seed_action'] = 'apply';
		$_POST['_wpnonce']             = 'nope';

		$this->assertNull( pediment_seed_admin_handle_post() );
		$this->assertSame( [], get_posts( [ 'post_type' => 'page', 'fields' => 'ids' ] ) );
	}
}
