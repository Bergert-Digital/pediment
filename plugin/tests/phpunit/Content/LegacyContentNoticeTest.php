<?php

use Pediment\Content\LegacyContentNotice;
use Pediment\Convert\PostConverter;

class LegacyContentNoticeTest extends WP_UnitTestCase {

	private const LEGACY = '<!-- wp:pediment/pull-quote {"quote":"Hi"} /-->';

	private const PLAIN = "<!-- wp:paragraph -->\n<p>Plain</p>\n<!-- /wp:paragraph -->";

	public function set_up(): void {
		parent::set_up();
		$GLOBALS['pediment_settings_tabs'] = array();
		do_action( 'admin_menu' );
		LegacyContentNotice::clear();
	}

	public function tear_down(): void {
		unset( $GLOBALS['pediment_settings_tabs'], $_GET['page'], $_GET['tab'] );
		parent::tear_down();
	}

	private function as_role( string $role ): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => $role ) ) );
	}

	private function page( string $content, string $status = 'publish' ): int {
		return self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_status'  => $status,
				'post_content' => $content,
			)
		);
	}

	private function notice(): string {
		ob_start();
		LegacyContentNotice::render();
		return (string) ob_get_clean();
	}

	public function test_hooks_are_registered(): void {
		$this->assertNotFalse( has_action( 'admin_notices', array( LegacyContentNotice::class, 'render' ) ) );
		$this->assertNotFalse( has_action( 'save_post', array( LegacyContentNotice::class, 'clearFor' ) ) );
		$this->assertNotFalse( has_action( 'deleted_post', array( LegacyContentNotice::class, 'clearFor' ) ) );
	}

	public function test_no_legacy_posts_show_no_notice(): void {
		$this->as_role( 'administrator' );
		$this->page( self::PLAIN );
		$this->page( '<!-- wp:pediment/icon {"name":"key"} /-->' );

		$this->assertSame( '', $this->notice() );
		$this->assertSame( 0, LegacyContentNotice::count() );
	}

	public function test_two_legacy_posts_show_the_count_and_link_the_convert_tab(): void {
		$this->as_role( 'administrator' );
		$this->page( self::LEGACY );
		$this->page( "<!-- wp:group -->\n<div class=\"wp-block-group\">" . self::LEGACY . "</div>\n<!-- /wp:group -->", 'draft' );
		$this->page( self::PLAIN );
		$this->page( self::LEGACY, 'trash' );

		$html = $this->notice();

		$this->assertStringContainsString( 'notice-warning', $html );
		$this->assertStringContainsString( '2 posts still contain blocks that Pediment 5 removed. They no longer render. Convert them:', $html );
		$this->assertStringContainsString( 'href="' . esc_url( pediment_settings_page_url( 'convert' ) ) . '"', $html );
		$this->assertStringContainsString( 'Settings → Pediment Theme → Convert legacy blocks', $html );
	}

	public function test_one_legacy_post_reads_in_the_singular(): void {
		$this->as_role( 'administrator' );
		$this->page( self::LEGACY );

		$this->assertStringContainsString( '1 post still contains blocks that Pediment 5 removed.', $this->notice() );
	}

	public function test_a_user_without_manage_options_sees_no_notice_and_counts_nothing(): void {
		$this->page( self::LEGACY );
		$this->page( self::LEGACY );
		$this->as_role( 'editor' );

		$this->assertSame( '', $this->notice() );
		$this->assertFalse( get_transient( LegacyContentNotice::TRANSIENT ), 'the count is not computed for this user' );
	}

	public function test_the_convert_tab_shows_no_notice(): void {
		$this->as_role( 'administrator' );
		$this->page( self::LEGACY );
		$_GET['page'] = PEDIMENT_SETTINGS_PAGE;

		$_GET['tab'] = 'convert';
		$this->assertSame( '', $this->notice() );

		$_GET['tab'] = 'design';
		$this->assertStringContainsString( '1 post still contains', $this->notice(), 'the other tabs show it' );
	}

	public function test_the_count_is_cached_for_twelve_hours(): void {
		global $wpdb;
		$this->as_role( 'administrator' );
		$this->page( self::LEGACY );

		$this->assertSame( 1, LegacyContentNotice::count() );
		$this->assertSame( '1', (string) get_transient( LegacyContentNotice::TRANSIENT ) );
		$expires = (int) get_option( '_transient_timeout_' . LegacyContentNotice::TRANSIENT );
		$this->assertEqualsWithDelta( time() + 12 * HOUR_IN_SECONDS, $expires, 5 );

		// A write that bypasses save_post does not reach the cached count.
		$wpdb->insert( $wpdb->posts, array( 'post_type' => 'page', 'post_status' => 'publish', 'post_content' => self::LEGACY, 'post_title' => 'Raw' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$this->assertSame( 1, LegacyContentNotice::count() );
	}

	public function test_saving_or_deleting_a_post_clears_the_count(): void {
		$this->as_role( 'administrator' );
		$id = $this->page( self::LEGACY );
		$this->assertSame( 1, LegacyContentNotice::count() );

		wp_update_post( array( 'ID' => $id, 'post_content' => self::PLAIN ) );
		$this->assertFalse( get_transient( LegacyContentNotice::TRANSIENT ) );
		$this->assertSame( 0, LegacyContentNotice::count() );

		$other = $this->page( self::LEGACY );
		$this->assertSame( 1, LegacyContentNotice::count() );
		wp_delete_post( $other, true );
		$this->assertSame( 0, LegacyContentNotice::count() );
	}

	public function test_a_form_entry_or_other_post_type_keeps_the_count(): void {
		$this->as_role( 'administrator' );
		$this->page( self::LEGACY );
		$this->assertSame( 1, LegacyContentNotice::count() );

		self::factory()->post->create( array( 'post_type' => PEDIMENT_FORM_CPT, 'post_status' => 'private' ) );
		self::factory()->post->create( array( 'post_type' => 'attachment', 'post_status' => 'inherit' ) );

		$this->assertSame( '1', (string) get_transient( LegacyContentNotice::TRANSIENT ) );
	}

	public function test_a_conversion_and_a_restore_clear_the_count(): void {
		$this->as_role( 'administrator' );
		$id = $this->page( self::LEGACY );
		$this->page( self::LEGACY );
		$this->assertSame( 2, LegacyContentNotice::count() );

		// The converter clears the count itself, not only through save_post.
		remove_action( 'save_post', array( LegacyContentNotice::class, 'clearFor' ) );
		$report = PostConverter::create()->run( $id, false );
		add_action( 'save_post', array( LegacyContentNotice::class, 'clearFor' ), 10, 2 );
		$this->assertSame( 'converted', $report['status'] );
		$this->assertFalse( get_transient( LegacyContentNotice::TRANSIENT ), 'the conversion cleared the cached count' );
		$this->assertStringContainsString( '1 post still contains', $this->notice() );

		$this->assertNotFalse( wp_restore_post_revision( $report['restoreRevision'] ) );
		$this->assertFalse( get_transient( LegacyContentNotice::TRANSIENT ), 'the restore cleared the cached count' );
		$this->assertStringContainsString( '2 posts still contain', $this->notice() );
	}
}
