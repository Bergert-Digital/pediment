<?php

require_once __DIR__ . '/FakeMedia.php';

use Pediment\Content\InvalidBlockLog;
use Pediment\Content\Policy;
use Pediment\Convert\Converter;
use Pediment\Convert\PostConverter;

class PostConverterTest extends WP_UnitTestCase {

	private const LEGACY = '<!-- wp:pediment/pull-quote {"quote":"Hi","citation":"Me"} /-->';

	private const RED = '<!-- wp:paragraph {"style":{"color":{"text":"#ff0000"}}} -->' . "\n" . '<p class="has-text-color" style="color:#ff0000">Red</p>' . "\n" . '<!-- /wp:paragraph -->';

	private const IFRAME = '<!-- wp:html -->\n<iframe src="https://example.com/embed"></iframe>\n<!-- /wp:html -->';

	private function service(): PostConverter {
		return new PostConverter( Converter::withDefaults(), new Policy(), new FakeMedia() );
	}

	private function page( string $content, string $type = 'page' ): int {
		return self::factory()->post->create(
			array(
				'post_type'    => $type,
				'post_status'  => 'publish',
				'post_content' => $content,
			)
		);
	}

	public function test_dry_run_writes_nothing(): void {
		$id = $this->page( self::LEGACY );
		InvalidBlockLog::record( $id, array( 'core/group' ) );
		$modified  = get_post_field( 'post_modified_gmt', $id );
		$revisions = count( wp_get_post_revisions( $id ) );

		$report = $this->service()->run( $id, true );

		$this->assertSame( 'preview', $report['status'] );
		$this->assertStringContainsString( "-<!-- wp:pediment/pull-quote", $report['diff'] );
		$this->assertStringContainsString( '+<!-- wp:quote {"className":"is-style-pull-quote"} -->', $report['diff'] );
		$this->assertSame( 0, $report['revision'] );
		$this->assertSame( self::LEGACY, get_post_field( 'post_content', $id ) );
		$this->assertSame( $modified, get_post_field( 'post_modified_gmt', $id ) );
		$this->assertCount( $revisions, wp_get_post_revisions( $id ) );
		$this->assertNotNull( InvalidBlockLog::get( $id ), 'the editor report stays' );
	}

	public function test_conversion_keeps_the_old_content_in_a_revision(): void {
		$id = $this->page( self::LEGACY );
		InvalidBlockLog::record( $id, array( 'core/group' ) );

		$report = $this->service()->run( $id, false );

		$this->assertSame( 'converted', $report['status'] );
		$this->assertSame( array( 'pediment/pull-quote' ), $report['converted'] );
		$this->assertSame( '', $report['diff'] );
		$this->assertSame( self::LEGACY, get_post_field( 'post_content', $report['restoreRevision'] ) );
		$this->assertSame( 'revision', get_post_type( $report['revision'] ) );
		$this->assertFalse( wp_is_post_autosave( $report['revision'] ) );
		$this->assertStringContainsString( 'is-style-pull-quote', get_post_field( 'post_content', $id ) );
		$this->assertNull( InvalidBlockLog::get( $id ) );
		$this->assertSame( 'unchanged', $this->service()->run( $id, false )['status'], 'a second run finds nothing' );
	}

	public function test_existing_rule_errors_are_reported_but_do_not_block(): void {
		$id     = $this->page( self::RED . "\n\n" . self::LEGACY );
		$report = $this->service()->run( $id, false );
		$this->assertSame( 'converted', $report['status'] );
		$this->assertSame( array(), $report['errors'] );
		$this->assertNotEmpty( array_filter( $report['existingErrors'], static fn( string $e ): bool => str_contains( $e, '#ff0000' ) ) );
	}

	public function test_a_conversion_that_adds_a_rule_error_is_not_written(): void {
		$content = '<!-- wp:pediment/pull-quote {"quote":"Type javascript:void(0) here"} /-->';
		$id      = $this->page( $content );
		$report  = $this->service()->run( $id, false );
		$this->assertSame( 'failed', $report['status'] );
		$this->assertStringContainsString( 'javascript: URL is not allowed', $report['errors'][0] );
		$this->assertSame( $content, get_post_field( 'post_content', $id ) );
	}

	public function test_synced_patterns_and_template_parts_are_found_and_converted(): void {
		$pattern = $this->page( '<!-- wp:pediment/stat {"value":"1","label":"One"} /-->', 'wp_block' );
		$part    = self::factory()->post->create(
			array(
				'post_type'    => 'wp_template_part',
				'post_status'  => 'publish',
				'post_name'    => 'footer-legacy',
				'post_content' => '<!-- wp:pediment/section-head {"headline":"Footer"} /-->',
			)
		);
		wp_set_post_terms( $part, get_stylesheet(), 'wp_theme' );
		$page = $this->page( '<!-- wp:block {"ref":' . $pattern . '} /-->' );
		$skip = $this->page( self::LEGACY, 'attachment' );

		$service = $this->service();
		$this->assertSame( array( $pattern, $part ), array_values( array_intersect( $service->candidates(), array( $pattern, $part, $page, $skip ) ) ) );

		$report = $service->runMany( array( $pattern, $part, $page, $skip, 999999 ), false );
		$this->assertSame( array( 'converted', 'converted', 'unchanged', 'skipped', 'skipped' ), array_column( $report['posts'], 'status' ) );
		$this->assertSame( array( 'wp_block', 'wp_template_part', 'page', 'attachment', '' ), array_column( $report['posts'], 'type' ) );
		$this->assertSame( 2, $report['summary']['converted'] );
		$this->assertSame( 2, $report['summary']['blocks'] );
		$this->assertStringContainsString( 'is-style-stat', get_post_field( 'post_content', $pattern ) );
		$this->assertSame( '<!-- wp:block {"ref":' . $pattern . '} /-->', get_post_field( 'post_content', $page ) );
	}

	public function test_posts_the_caller_may_not_edit_are_skipped(): void {
		$id     = $this->page( self::LEGACY );
		$report = $this->service()->runMany( array( $id ), false, static fn( int $post ): bool => false );
		$this->assertSame( 'skipped', $report['posts'][0]['status'] );
		$this->assertSame( array( 'you cannot edit this post' ), $report['posts'][0]['notes'] );
		$this->assertSame( '', $report['posts'][0]['title'], 'the title of a post the caller may not edit is not shown' );
		$this->assertSame( self::LEGACY, get_post_field( 'post_content', $id ) );
	}

	public function test_raw_html_in_a_legacy_container_fails_the_post_and_writes_nothing(): void {
		$content = "<!-- wp:pediment/prose -->\n<!-- wp:paragraph -->\n<p>Block</p>\n<!-- /wp:paragraph -->\n<p>Raw tail</p>\n<!-- /wp:pediment/prose -->";
		$id      = $this->page( $content );
		foreach ( array( true, false ) as $dry_run ) {
			$report = $this->service()->run( $id, $dry_run );
			$this->assertSame( 'failed', $report['status'] );
			$this->assertSame( array( 'pediment/prose holds HTML outside blocks; edit it into blocks first ("<p>Raw tail</p>")' ), $report['errors'] );
			$this->assertSame( '', $report['diff'] );
			$this->assertSame( 0, $report['restoreRevision'] );
		}
		$this->assertSame( $content, get_post_field( 'post_content', $id ) );
		$this->assertCount( 0, wp_get_post_revisions( $id ) );
	}

	public function test_a_legacy_block_left_after_conversion_fails_the_post(): void {
		$content = self::LEGACY . "\n" . '<!-- wp:pediment/pull-quote __ATTRS__ /-->';
		$id      = $this->page( $content );
		$report  = $this->service()->run( $id, false );
		$this->assertSame( 'failed', $report['status'] );
		$this->assertSame( array( 'pediment/pull-quote is still in the markup after conversion (its block comment does not parse); convert it by hand' ), $report['errors'] );
		$this->assertSame( $content, get_post_field( 'post_content', $id ) );
	}

	public function test_candidates_find_delimiters_with_other_whitespace(): void {
		global $wpdb;
		$newline = $this->page( 'x' );
		$tab     = $this->page( 'x' );
		// Saved directly: kses re-serializes block comments on wp_insert_post.
		$wpdb->update( $wpdb->posts, array( 'post_content' => "<!--\nwp:pediment/pull-quote {\"quote\":\"Hi\"} /-->" ), array( 'ID' => $newline ) );
		$wpdb->update( $wpdb->posts, array( 'post_content' => "<!--\twp:pediment/pull-quote {\"quote\":\"Hi\"} /-->" ), array( 'ID' => $tab ) );
		clean_post_cache( $newline );
		clean_post_cache( $tab );
		$service = $this->service();
		$this->assertContains( $newline, $service->candidates() );
		$this->assertContains( $tab, $service->candidates() );
		$this->assertSame( 'converted', $service->run( $tab, false )['status'] );
		$this->assertStringContainsString( 'is-style-pull-quote', get_post_field( 'post_content', $tab ) );
	}

	private function revisions_limited_to( int $limit ): void {
		add_filter( 'wp_revisions_to_keep', static fn(): int => $limit );
	}

	/**
	 * @dataProvider revision_limits
	 */
	public function test_a_limit_below_two_revisions_refuses_the_write( int $limit ): void {
		$id = $this->page( self::LEGACY );
		$this->revisions_limited_to( $limit );

		foreach ( array( false, true ) as $dry_run ) {
			$report = $this->service()->run( $id, $dry_run );
			$this->assertSame( 'failed', $report['status'] );
			$this->assertStringContainsString( 'revisions are disabled or limited to fewer than 2', $report['errors'][0] );
			$this->assertSame( self::LEGACY, get_post_field( 'post_content', $id ) );
		}
	}

	public function revision_limits(): array {
		return array(
			'disabled' => array( 0 ),
			'one'      => array( 1 ),
		);
	}

	public function test_a_limit_of_two_revisions_still_converts_and_keeps_the_backup(): void {
		$id = $this->page( self::LEGACY );
		$this->revisions_limited_to( 2 );

		$report = $this->service()->run( $id, false );

		$this->assertSame( 'converted', $report['status'] );
		$this->assertSame( self::LEGACY, get_post_field( 'post_content', $report['restoreRevision'] ) );
		$this->assertSame( $id, wp_is_post_revision( $report['restoreRevision'] ) );
	}

	public function test_a_post_type_without_revision_support_is_refused(): void {
		$id = $this->page( self::LEGACY );
		remove_post_type_support( 'page', 'revisions' );
		try {
			$report = $this->service()->run( $id, false );
		} finally {
			add_post_type_support( 'page', 'revisions' );
		}
		$this->assertSame( 'failed', $report['status'] );
		$this->assertStringContainsString( 'revisions are disabled or limited to fewer than 2', $report['errors'][0] );
		$this->assertSame( self::LEGACY, get_post_field( 'post_content', $id ) );
	}

	private function as_editor_without_unfiltered_html(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		add_filter( 'map_meta_cap', static fn( $caps, $cap ) => 'unfiltered_html' === $cap ? array( 'do_not_allow' ) : $caps, 10, 2 );
		kses_init();
		$this->assertNotFalse( has_filter( 'content_save_pre', 'wp_filter_post_kses' ) );
	}

	private function page_with_iframe_as_admin( string $legacy ): int {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		kses_init();
		return $this->page( $legacy . "\n\n" . self::IFRAME );
	}

	public function test_a_user_who_would_lose_content_to_kses_is_refused(): void {
		$id      = $this->page_with_iframe_as_admin( self::LEGACY );
		$content = get_post_field( 'post_content', $id );
		$this->assertStringContainsString( '<iframe', $content );
		$this->as_editor_without_unfiltered_html();

		foreach ( array( true, false ) as $dry_run ) {
			$report = $this->service()->run( $id, $dry_run );
			$this->assertSame( 'failed', $report['status'] );
			$this->assertStringContainsString( 'saving as this user would remove content outside the converted blocks', $report['errors'][0] );
			$this->assertStringContainsString( 'iframe', $report['errors'][0] );
			$this->assertSame( $content, get_post_field( 'post_content', $id ) );
		}
	}

	public function test_a_trusted_run_keeps_unrelated_content_that_kses_would_strip(): void {
		$id = $this->page_with_iframe_as_admin( self::LEGACY );
		$this->as_editor_without_unfiltered_html();

		$service = new PostConverter( Converter::withDefaults(), new Policy(), new FakeMedia(), true );
		$report  = $service->run( $id, false );

		$this->assertSame( 'converted', $report['status'] );
		$this->assertStringContainsString( '<iframe', get_post_field( 'post_content', $id ) );
		$this->assertStringContainsString( 'is-style-pull-quote', get_post_field( 'post_content', $id ) );
		$this->assertNotFalse( has_filter( 'content_save_pre', 'wp_filter_post_kses' ), 'kses is back on afterwards' );
	}

	public function test_a_user_without_unfiltered_html_converts_a_page_kses_leaves_alone(): void {
		$id = $this->page( self::LEGACY );
		$this->as_editor_without_unfiltered_html();

		$report = $this->service()->run( $id, false );

		$this->assertSame( 'converted', $report['status'] );
		$this->assertStringContainsString( 'is-style-pull-quote', get_post_field( 'post_content', $id ) );
	}

	public function test_a_repeated_error_counts_as_added(): void {
		$this->assertSame(
			array( 'blocks[1] core/html: bad' ),
			PostConverter::addedErrors( array( 'blocks[0] core/html: bad' ), array( 'blocks[0] core/html: bad', 'blocks[1] core/html: bad' ) )
		);
		$this->assertSame( array(), PostConverter::addedErrors( array( 'blocks[2] core/html: bad' ), array( 'blocks[0] core/html: bad' ) ) );
	}

	public function test_candidates_cover_drafts_but_not_trash_or_plain_posts(): void {
		$draft = self::factory()->post->create( array( 'post_status' => 'draft', 'post_content' => self::LEGACY ) );
		$trash = self::factory()->post->create( array( 'post_status' => 'trash', 'post_content' => self::LEGACY ) );
		$plain = $this->page( '<!-- wp:paragraph --><p>Hi</p><!-- /wp:paragraph -->' );
		$core  = $this->page( '<!-- wp:pediment/hero-and-friends-that-are-not-legacy /-->' );

		$candidates = $this->service()->candidates();

		$this->assertContains( $draft, $candidates );
		$this->assertNotContains( $trash, $candidates );
		$this->assertNotContains( $plain, $candidates );
		$this->assertNotContains( $core, $candidates );
	}

	public function test_a_trusted_backup_keeps_content_kses_would_strip(): void {
		$id = $this->page_with_iframe_as_admin( self::LEGACY );
		$this->as_editor_without_unfiltered_html();

		$service = new PostConverter( Converter::withDefaults(), new Policy(), new FakeMedia(), true );
		$report  = $service->run( $id, false );

		$this->assertSame( 'converted', $report['status'] );
		$this->assertStringContainsString( '<iframe', get_post_field( 'post_content', $report['restoreRevision'] ) );
		$this->assertNotFalse( has_filter( 'content_save_pre', 'wp_filter_post_kses' ), 'kses is back on afterwards' );
	}

	public function test_core_blocks_that_kses_only_reformats_do_not_block_an_editor(): void {
		$core = '<!-- wp:separator {"className":"is-style-wide"} -->' . "\n" . '<hr class="wp-block-separator has-alpha-channel-opacity is-style-wide"/>' . "\n" . '<!-- /wp:separator -->'
			. "\n\n" . '<!-- wp:paragraph { "align" : "center" } -->' . "\n" . '<p class="has-text-align-center">Hi<br/>there</p>' . "\n" . '<!-- /wp:paragraph -->';
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		kses_init();
		$id = $this->page( self::LEGACY . "\n\n" . $core );
		$this->assertStringContainsString( '{ "align" : "center" }', get_post_field( 'post_content', $id ), 'stored unfiltered' );
		$this->as_editor_without_unfiltered_html();

		$report = $this->service()->run( $id, false );

		$this->assertSame( 'converted', $report['status'], implode( '; ', $report['errors'] ) );
		$this->assertStringContainsString( 'is-style-pull-quote', get_post_field( 'post_content', $id ) );
	}

	public function test_a_negative_revision_limit_means_unlimited(): void {
		$id = $this->page( self::LEGACY );
		$this->revisions_limited_to( -5 );
		$this->assertSame( 'converted', $this->service()->run( $id, false )['status'] );
	}

	public function test_the_editor_report_is_cleared_when_the_backup_is_pruned_after_the_write(): void {
		$id = $this->page( self::LEGACY );
		InvalidBlockLog::record( $id, array( 'core/group' ) );
		// Two revisions are allowed before the write; after it, only the newest survives.
		$calls = 0;
		add_filter(
			'wp_revisions_to_keep',
			static function () use ( &$calls ): int {
				return 1 < ++$calls ? 1 : 2;
			}
		);
		$report = $this->service()->run( $id, false );
		$this->assertSame( 'failed', $report['status'] );
		$this->assertStringContainsString( 'backup revision was removed', $report['errors'][0] );
		$this->assertStringContainsString( 'is-style-pull-quote', get_post_field( 'post_content', $id ) );
		$this->assertNull( InvalidBlockLog::get( $id ) );
	}
}
