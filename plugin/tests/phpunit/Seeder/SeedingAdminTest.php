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

	public function test_a_rejected_submission_says_so_instead_of_looking_untouched() {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$_POST['pediment_seed_action'] = 'apply';
		$_POST['_wpnonce']             = 'nope';

		ob_start();
		pediment_seed_admin_render_tab();
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'notice-error', $html );
		$this->assertStringContainsString( 'expired', $html );
	}

	public function test_an_untouched_page_shows_no_error() {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );

		ob_start();
		pediment_seed_admin_render_tab();
		$html = (string) ob_get_clean();

		$this->assertStringNotContainsString( 'notice-error', $html );
	}

	public function test_both_buttons_submit_their_own_action_with_a_nonce() {
		// The button wiring is the whole feature on admin-only hosting, and a
		// dropped hidden input would otherwise pass the entire suite.
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );

		ob_start();
		pediment_seed_admin_render_tab();
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'name="pediment_seed_action" value="preview"', $html );
		$this->assertStringContainsString( 'name="pediment_seed_action" value="apply"', $html );
		$this->assertStringContainsString( 'name="pediment_seed_action" value="claim-preview"', $html );
		$this->assertStringContainsString( 'name="pediment_seed_action" value="claim-apply"', $html );
		$this->assertSame( 4, substr_count( $html, 'name="_wpnonce"' ), 'each form carries its own nonce' );
		$this->assertSame( 4, substr_count( $html, '<form' ), 'four forms, one per action' );
	}

	private function legacyHome(): int {
		return self::factory()->post->create(
			[ 'post_type' => 'page', 'post_name' => 'home', 'post_title' => 'Legacy home', 'post_status' => 'publish' ]
		);
	}

	public function test_a_claim_preview_reports_without_writing() {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$id                            = $this->legacyHome();
		$_POST['pediment_seed_action'] = 'claim-preview';
		$_POST['_wpnonce']             = wp_create_nonce( 'pediment_seed' );

		$report = pediment_seed_admin_handle_post();

		$this->assertNotNull( $report );
		$this->assertStringContainsString( 'Pediment claim — dry run', $report );
		$this->assertSame( '', get_post_meta( $id, \Pediment\Seeder\Meta::KEY, true ) );
	}

	public function test_a_claim_apply_writes_the_key_and_nothing_else() {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$id                            = $this->legacyHome();
		$_POST['pediment_seed_action'] = 'claim-apply';
		$_POST['_wpnonce']             = wp_create_nonce( 'pediment_seed' );

		$report = pediment_seed_admin_handle_post();

		$this->assertStringContainsString( 'Pediment claim', $report );
		$this->assertSame( 'home', get_post_meta( $id, \Pediment\Seeder\Meta::KEY, true ) );
		$this->assertSame( '', get_post_meta( $id, \Pediment\Seeder\Meta::HASH, true ) );
		$this->assertSame( 'Legacy home', get_post( $id )->post_title );
	}

	public function test_a_subscriber_cannot_claim() {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'subscriber' ] ) );
		$id                            = $this->legacyHome();
		$_POST['pediment_seed_action'] = 'claim-apply';
		$_POST['_wpnonce']             = wp_create_nonce( 'pediment_seed' );

		$report = pediment_seed_admin_handle_post();

		$this->assertNull( $report );
		$this->assertSame( '', get_post_meta( $id, \Pediment\Seeder\Meta::KEY, true ) );
	}
}
