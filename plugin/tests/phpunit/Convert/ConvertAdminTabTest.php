<?php

use Pediment\Convert\AdminTab;

class ConvertAdminTabTest extends WP_UnitTestCase {

	private const LEGACY = '<!-- wp:pediment/pull-quote {"quote":"Hi"} /-->';

	public function set_up(): void {
		parent::set_up();
		$GLOBALS['pediment_settings_tabs'] = array();
		do_action( 'admin_menu' );
	}

	public function tear_down(): void {
		unset( $GLOBALS['pediment_settings_tabs'], $_POST['ids'], $_POST['_wpnonce'], $_REQUEST['_wpnonce'] );
		parent::tear_down();
	}

	private function admin(): int {
		$id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $id );
		return $id;
	}

	public function test_tab_is_registered_and_the_handler_hooked(): void {
		$tabs = pediment_settings_get_tabs();
		$this->assertSame( 'Convert legacy blocks', $tabs['convert']['label'] );
		$this->assertNotFalse( has_action( 'admin_post_' . AdminTab::ACTION, array( AdminTab::class, 'handle' ) ) );
	}

	public function test_preview_lists_the_post_and_changes_nothing(): void {
		$this->admin();
		$id = self::factory()->post->create( array( 'post_type' => 'page', 'post_title' => 'Legacy page', 'post_content' => self::LEGACY ) );
		ob_start();
		AdminTab::render();
		$html = (string) ob_get_clean();
		$this->assertStringContainsString( 'Legacy page', $html );
		$this->assertStringContainsString( 'name="ids[]" value="' . $id . '"', $html );
		$this->assertStringContainsString( 'Convert 1 post', $html );
		$this->assertStringContainsString( '&lt;!-- wp:quote', $html, 'the diff is escaped' );
		$this->assertSame( self::LEGACY, get_post_field( 'post_content', $id ) );
	}

	public function test_process_converts_and_the_next_render_shows_the_result_once(): void {
		$this->admin();
		$id     = self::factory()->post->create( array( 'post_type' => 'page', 'post_title' => 'Legacy page', 'post_content' => self::LEGACY ) );
		$report = AdminTab::process( array( $id ) );
		$this->assertSame( 'converted', $report['posts'][0]['status'] );
		$this->assertStringContainsString( 'is-style-pull-quote', get_post_field( 'post_content', $id ) );

		ob_start();
		AdminTab::render();
		$first = (string) ob_get_clean();
		ob_start();
		AdminTab::render();
		$second = (string) ob_get_clean();
		$this->assertStringContainsString( '<h2>Result</h2>', $first );
		$this->assertStringNotContainsString( '<h2>Result</h2>', $second );
		$this->assertStringContainsString( 'No legacy blocks found', $second );
	}

	public function test_failed_posts_show_their_reason_and_are_not_submitted(): void {
		$this->admin();
		$id = self::factory()->post->create( array( 'post_type' => 'page', 'post_title' => 'No backup page', 'post_content' => self::LEGACY ) );
		add_filter( 'wp_revisions_to_keep', '__return_zero' );
		ob_start();
		AdminTab::render();
		$html = (string) ob_get_clean();
		remove_filter( 'wp_revisions_to_keep', '__return_zero' );
		$this->assertStringContainsString( 'No backup page', $html );
		$this->assertStringContainsString( '<td>failed</td>', $html );
		$this->assertStringContainsString( 'conversion needs a backup revision', $html );
		$this->assertStringNotContainsString( 'name="ids[]" value="' . $id . '"', $html );
		$this->assertStringContainsString( 'Convert 0 posts', $html );
		$this->assertSame( self::LEGACY, get_post_field( 'post_content', $id ) );
	}

	public function test_result_table_shows_the_reason_of_a_failed_post(): void {
		$this->admin();
		$id = self::factory()->post->create( array( 'post_type' => 'page', 'post_content' => self::LEGACY ) );
		add_filter( 'wp_revisions_to_keep', '__return_zero' );
		$report = AdminTab::process( array( $id ) );
		remove_filter( 'wp_revisions_to_keep', '__return_zero' );
		$this->assertSame( 'failed', $report['posts'][0]['status'] );
		ob_start();
		AdminTab::render();
		$html = (string) ob_get_clean();
		$this->assertStringContainsString( '<h2>Result</h2>', $html );
		$this->assertStringContainsString( 'conversion needs a backup revision', $html );
	}

	public function test_handler_refuses_editors_and_missing_nonces(): void {
		$id = self::factory()->post->create( array( 'post_type' => 'page', 'post_content' => self::LEGACY ) );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$_POST['ids'] = array( (string) $id );
		try {
			AdminTab::handle();
			$this->fail( 'editors must be refused' );
		} catch ( WPDieException $e ) {
			$this->assertStringContainsString( 'not allowed', $e->getMessage() );
		}

		$this->admin();
		try {
			AdminTab::handle();
			$this->fail( 'a missing nonce must be refused' );
		} catch ( WPDieException $e ) {
			$this->assertSame( self::LEGACY, get_post_field( 'post_content', $id ) );
		}
	}
}
