<?php

require_once __DIR__ . '/FakeMedia.php';

use Pediment\Content\InvalidBlockLog;
use Pediment\Content\Policy;
use Pediment\Convert\Converter;
use Pediment\Convert\PostConverter;

class PostConverterTest extends WP_UnitTestCase {

	private const LEGACY = '<!-- wp:pediment/pull-quote {"quote":"Hi","citation":"Me"} /-->';

	private const RED = '<!-- wp:paragraph {"style":{"color":{"text":"#ff0000"}}} -->' . "\n" . '<p class="has-text-color" style="color:#ff0000">Red</p>' . "\n" . '<!-- /wp:paragraph -->';

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
		$this->assertSame( self::LEGACY, get_post_field( 'post_content', $id ) );
	}
}
