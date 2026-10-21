<?php

require_once __DIR__ . '/AbilityTestCase.php';

class ConvertLegacyTest extends AbilityTestCase {

	private const LEGACY = '<!-- wp:pediment/pull-quote {"quote":"Hi"} /-->';

	private function page( int $author, string $content = self::LEGACY ): int {
		return self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_author'  => $author,
				'post_content' => $content,
			)
		);
	}

	public function test_dry_run_is_the_default(): void {
		$editor = $this->user( 'editor' );
		$id     = $this->page( $editor );
		$result = $this->run_as( $editor, 'pediment/convert-legacy', array( 'ids' => array( $id ) ) );
		$this->assertTrue( $result['dryRun'] );
		$this->assertSame( 'preview', $result['posts'][0]['status'] );
		$this->assertNotSame( '', $result['posts'][0]['diff'] );
		$this->assertSame( array(), $result['posts'][0]['errors'] );
		$this->assertSame( array( 'blocks[0] pediment/pull-quote: pediment/pull-quote was removed in Pediment 5; rebuild it with core blocks and named styles (pediment-get-vocabulary)' ), $result['posts'][0]['existingErrors'] );
		$this->assertSame( self::LEGACY, get_post_field( 'post_content', $id ) );
	}

	public function test_convert_writes_and_rollback_restores_the_legacy_version(): void {
		$editor = $this->user( 'editor' );
		$id     = $this->page( $editor );
		$result = $this->run_as(
			$editor,
			'pediment/convert-legacy',
			array(
				'ids'     => array( $id ),
				'dry_run' => false,
			)
		);
		$post = $result['posts'][0];
		$this->assertSame( 'converted', $post['status'] );
		$this->assertSame( 1, $result['summary']['converted'] );
		$this->assertSame( array(), $this->run_as( $editor, 'pediment/check-page', array( 'id' => $id ) )['legacyBlocks'] );

		$this->run_as(
			$editor,
			'pediment/rollback-page',
			array(
				'id'       => $id,
				'revision' => $post['restoreRevision'],
			)
		);
		$this->assertSame( self::LEGACY, get_post_field( 'post_content', $id ) );
	}

	public function test_all_converts_every_page_the_user_may_edit(): void {
		$admin  = $this->user( 'administrator' );
		$author = $this->user( 'author' );
		get_userdata( $author )->add_cap( 'edit_pages' );
		get_userdata( $author )->add_cap( 'edit_published_pages' );
		$own    = $this->page( $author );
		$other  = $this->page( $admin );

		$result = $this->run_as(
			$author,
			'pediment/convert-legacy',
			array(
				'all'     => true,
				'dry_run' => false,
			)
		);
		$status = array_column( $result['posts'], 'status', 'id' );
		$this->assertSame( 'converted', $status[ $own ] );
		$this->assertSame( 'skipped', $status[ $other ] );
		$this->assertSame( self::LEGACY, get_post_field( 'post_content', $other ) );
	}

	public function test_all_works_in_batches_of_25_and_reports_what_remains(): void {
		$editor = $this->user( 'editor' );
		$ids    = array();
		for ( $i = 0; $i < 27; $i++ ) {
			$ids[] = $this->page( $editor );
		}
		$first = $this->run_as( $editor, 'pediment/convert-legacy', array( 'all' => true ) );
		$this->assertCount( 25, $first['posts'] );
		$this->assertSame( array_slice( $ids, 0, 25 ), array_column( $first['posts'], 'id' ) );
		$this->assertSame( 2, $first['remaining'] );

		$next = $this->run_as(
			$editor,
			'pediment/convert-legacy',
			array(
				'all'   => true,
				'after' => $ids[24],
			)
		);
		$this->assertSame( array_slice( $ids, 25 ), array_column( $next['posts'], 'id' ) );
		$this->assertSame( 0, $next['remaining'] );

		$real = $this->run_as(
			$editor,
			'pediment/convert-legacy',
			array(
				'all'     => true,
				'dry_run' => false,
			)
		);
		$this->assertSame( 25, $real['summary']['converted'] );
		$this->assertSame( 2, $real['remaining'] );
		$rest = $this->run_as(
			$editor,
			'pediment/convert-legacy',
			array(
				'all'     => true,
				'dry_run' => false,
			)
		);
		$this->assertSame( array_slice( $ids, 25 ), array_column( $rest['posts'], 'id' ), 'converted posts drop out of the next call' );
		$this->assertSame( 0, $rest['remaining'] );
	}

	public function test_ids_are_not_batched_and_nothing_remains(): void {
		$editor = $this->user( 'editor' );
		$result = $this->run_as( $editor, 'pediment/convert-legacy', array( 'ids' => array( $this->page( $editor ) ) ) );
		$this->assertSame( 0, $result['remaining'] );
	}

	public function test_ids_the_user_cannot_edit_are_refused_before_anything_runs(): void {
		$admin  = $this->user( 'administrator' );
		$author = $this->user( 'author' );
		get_userdata( $author )->add_cap( 'edit_pages' );
		$other  = $this->page( $admin );
		$result = $this->run_as(
			$author,
			'pediment/convert-legacy',
			array(
				'ids'     => array( $other ),
				'dry_run' => false,
			)
		);
		$this->assertWPError( $result );
		$this->assertSame( 'ability_invalid_permissions', $result->get_error_code() );
		$this->assertSame( self::LEGACY, get_post_field( 'post_content', $other ) );
	}

	public function test_templates_need_theme_rights(): void {
		$part = self::factory()->post->create(
			array(
				'post_type'    => 'wp_template_part',
				'post_status'  => 'publish',
				'post_name'    => 'legacy-part',
				'post_content' => self::LEGACY,
			)
		);
		$result = $this->run_as( $this->user( 'editor' ), 'pediment/convert-legacy', array( 'ids' => array( $part ) ) );
		$this->assertWPError( $result );
		$this->assertSame( 'ability_invalid_permissions', $result->get_error_code() );
	}

	public function test_scope_must_be_ids_or_all(): void {
		$editor = $this->user( 'editor' );
		foreach ( array( array(), array( 'all' => true, 'ids' => array( 1 ) ), array( 'all' => false ) ) as $input ) {
			$result = $this->run_as( $editor, 'pediment/convert-legacy', $input );
			$this->assertWPError( $result, wp_json_encode( $input ) );
			$this->assertSame( 'pediment_convert_scope', $result->get_error_code() );
			$this->assertStringContainsString( 'pass either ids', $result->get_error_message() );
		}
	}

	public function test_string_booleans_from_the_rest_route_are_read_as_booleans(): void {
		$editor = $this->user( 'editor' );
		$id     = $this->page( $editor );
		$result = $this->run_as(
			$editor,
			'pediment/convert-legacy',
			array(
				'ids'     => array( (string) $id ),
				'dry_run' => 'false',
			)
		);
		$this->assertFalse( $result['dryRun'] );
		$this->assertSame( 'converted', $result['posts'][0]['status'] );
	}

	public function test_failed_and_skipped_posts_pass_the_output_schema_with_their_reasons(): void {
		$editor = $this->user( 'editor' );
		$id     = $this->page( $editor );
		add_filter( 'wp_revisions_to_keep', '__return_zero' );
		$result = $this->run_as(
			$editor,
			'pediment/convert-legacy',
			array(
				'ids'     => array( $id, 99999999 ),
				'dry_run' => false,
			)
		);
		remove_filter( 'wp_revisions_to_keep', '__return_zero' );

		$this->assertNotWPError( $result );
		$status = array_column( $result['posts'], 'status', 'id' );
		$this->assertSame( 'failed', $status[ $id ] );
		$this->assertSame( 'skipped', $status[99999999] );
		$this->assertSame( 1, $result['summary']['failed'] );
		$this->assertSame( 1, $result['summary']['skipped'] );
		$failed = $result['posts'][0];
		$this->assertStringContainsString( 'revisions are disabled', $failed['errors'][0] );
		$this->assertSame( self::LEGACY, get_post_field( 'post_content', $id ) );
	}

	public function test_annotations_say_destructive(): void {
		$annotations = wp_get_ability( 'pediment/convert-legacy' )->get_meta()['annotations'];
		$this->assertFalse( $annotations['readonly'] );
		$this->assertTrue( $annotations['destructive'] );
		$this->assertTrue( $annotations['idempotent'] );
	}
}
