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
		unset( $GLOBALS['pediment_settings_tabs'], $_POST['ids'], $_POST['after'], $_GET['after'], $_POST['_wpnonce'], $_REQUEST['_wpnonce'] );
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
		$this->assertStringNotContainsString( 'was removed in Pediment 5', $html, 'the Policy error for the legacy block is not a reason shown in the preview' );
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

	private function legacyPages( int $count ): array {
		$ids = array();
		for ( $i = 1; $i <= $count; $i++ ) {
			$ids[] = self::factory()->post->create( array( 'post_type' => 'page', 'post_title' => "Batch page $i", 'post_content' => self::LEGACY ) );
		}
		return $ids;
	}

	private function renderTab(): string {
		ob_start();
		AdminTab::render();
		return (string) ob_get_clean();
	}

	public function test_the_preview_and_the_run_work_in_batches(): void {
		$this->admin();
		add_filter( 'pediment_convert_batch_size', static fn(): int => 2 );
		list( $a, $b, $c ) = $this->legacyPages( 3 );

		$first = $this->renderTab();
		$this->assertStringContainsString( 'Batch page 1', $first );
		$this->assertStringContainsString( 'Batch page 2', $first );
		$this->assertStringNotContainsString( 'Batch page 3', $first );
		$this->assertStringContainsString( 'Convert 2 posts', $first );
		$this->assertStringContainsString( '3 posts with legacy blocks in total', $first );
		$this->assertStringContainsString( '1 more post with legacy blocks after this batch', $first );
		$this->assertLessThan( strpos( $first, 'name="ids[]"' ), strpos( $first, 'name="_wpnonce"' ), 'the nonce comes before the ids so max_input_vars cannot drop it' );

		$report = AdminTab::process( array( $a, $b, $c ) );
		$this->assertCount( 2, $report['posts'], 'extra ids beyond the batch size are ignored' );
		$this->assertSame( self::LEGACY, get_post_field( 'post_content', $c ) );

		$next = $this->renderTab();
		$this->assertStringContainsString( '<h2>Result</h2>', $next );
		$this->assertStringContainsString( 'name="ids[]" value="' . $c . '"', $next );
		$this->assertStringContainsString( 'Convert 1 post', $next );
		$this->assertStringNotContainsString( 'after this batch', $next );
	}

	public function test_the_cursor_moves_past_posts_that_are_not_ready(): void {
		$this->admin();
		add_filter( 'pediment_convert_batch_size', static fn(): int => 2 );
		list( $a, $b, $c ) = $this->legacyPages( 3 );
		$stuck = static fn( $keep, $post ) => in_array( $post->ID, array( $a, $b ), true ) ? 0 : $keep;
		add_filter( 'wp_revisions_to_keep', $stuck, 10, 2 );

		$first = $this->renderTab();
		$this->assertSame( 2, substr_count( $first, '<td>failed</td>' ) );
		$this->assertStringContainsString( 'Convert 0 posts', $first );
		$this->assertStringContainsString( 'Next batch', $first );
		$this->assertStringContainsString( 'after=' . $b, $first );
		$this->assertStringNotContainsString( 'Start from the beginning', $first );

		$_GET['after'] = (string) $b;
		$second = $this->renderTab();
		$this->assertStringContainsString( 'Batch page 3', $second );
		$this->assertStringNotContainsString( 'Batch page 1', $second );
		$this->assertStringContainsString( 'name="ids[]" value="' . $c . '"', $second );
		$this->assertStringContainsString( 'name="after" value="' . $b . '"', $second );
		$this->assertStringContainsString( 'Start from the beginning', $second );
		$this->assertStringNotContainsString( 'Next batch', $second );

		$report = AdminTab::process( array( $c ) );
		$this->assertSame( 'converted', $report['posts'][0]['status'] );
		remove_filter( 'wp_revisions_to_keep', $stuck, 10 );
	}

	public function test_a_cursor_past_the_last_candidate_offers_the_start(): void {
		$this->admin();
		list( $a ) = $this->legacyPages( 1 );
		$_GET['after'] = (string) ( $a + 100 );
		$html          = $this->renderTab();
		$this->assertStringContainsString( 'No more posts with legacy blocks after this point', $html );
		$this->assertStringContainsString( 'Start from the beginning', $html );
	}

	public function test_a_tampered_id_list_fails_the_nonce_check(): void {
		$this->admin();
		list( $a, $b ) = $this->legacyPages( 2 );
		$_REQUEST['_wpnonce'] = wp_create_nonce( AdminTab::nonceAction( array( $a ) ) );
		$_POST['ids']         = array( (string) $a, (string) $b );
		try {
			AdminTab::handle();
			$this->fail( 'an id that was not previewed must be refused' );
		} catch ( WPDieException $e ) {
			$this->assertSame( self::LEGACY, get_post_field( 'post_content', $a ) );
			$this->assertSame( self::LEGACY, get_post_field( 'post_content', $b ) );
		}
		$this->assertSame( AdminTab::nonceAction( array( $a, $b ) ), AdminTab::nonceAction( array( $b, $a ) ), 'the order of ids does not matter' );
		$this->assertNotSame( AdminTab::nonceAction( array( $a ) ), AdminTab::nonceAction( array( $a, $b ) ) );
	}

	public function test_process_skips_a_post_the_user_cannot_edit(): void {
		$this->admin();
		list( $a, $b ) = $this->legacyPages( 2 );
		add_filter(
			'map_meta_cap',
			static function ( array $caps, string $cap, int $user, array $args ) use ( $b ): array {
				return 'edit_post' === $cap && ( $args[0] ?? 0 ) === $b ? array( 'do_not_allow' ) : $caps;
			},
			10,
			4
		);
		$report = AdminTab::process( array( $a, $b ) );
		$this->assertSame( 'converted', $report['posts'][0]['status'] );
		$this->assertSame( 'skipped', $report['posts'][1]['status'] );
		$this->assertContains( 'you cannot edit this post', $report['posts'][1]['notes'] );
		$this->assertSame( self::LEGACY, get_post_field( 'post_content', $b ) );
	}

	public function test_the_preview_warns_to_style_the_new_blocks_before_converting_production(): void {
		$this->admin();
		self::factory()->post->create( array( 'post_type' => 'page', 'post_content' => self::LEGACY ) );
		$html = $this->renderTab();
		$this->assertStringContainsString( 'Convert production only after your client theme has styles for the looks your theme gave the legacy blocks', $html );
		$this->assertStringContainsString( 'href="https://github.com/Bergert-Digital/pediment/blob/main/docs/convert.md#before-you-convert-production"', $html );
		$this->assertLessThan( strpos( $html, '<table' ), strpos( $html, 'Convert production only after' ), 'the warning sits above the preview' );
	}

	public function test_the_result_links_each_converted_post_to_its_restore_revision(): void {
		$this->admin();
		$id     = self::factory()->post->create( array( 'post_type' => 'page', 'post_content' => self::LEGACY ) );
		$report = AdminTab::process( array( $id ) );
		$html   = $this->renderTab();
		$this->assertStringContainsString( '<a href="' . esc_url( admin_url( 'revision.php?revision=' . $report['posts'][0]['restoreRevision'] ) ) . '">Restore</a>', $html );
	}

	public function test_the_result_hides_the_title_of_a_post_the_user_cannot_edit(): void {
		$this->admin();
		list( $a, $b ) = $this->legacyPages( 2 );
		wp_update_post( array( 'ID' => $b, 'post_title' => 'Secret page' ) );
		add_filter(
			'map_meta_cap',
			static function ( array $caps, string $cap, int $user, array $args ) use ( $b ): array {
				return 'edit_post' === $cap && ( $args[0] ?? 0 ) === $b ? array( 'do_not_allow' ) : $caps;
			},
			10,
			4
		);
		AdminTab::process( array( $a, $b ) );
		$html = $this->renderTab();
		$this->assertStringNotContainsString( 'Secret page', $html );
		$this->assertStringContainsString( '(no access)', $html );
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
