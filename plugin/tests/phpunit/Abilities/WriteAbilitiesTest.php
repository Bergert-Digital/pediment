<?php

require_once __DIR__ . '/AbilityTestCase.php';

use Pediment\Abilities\Registrar;
use Pediment\Content\InvalidBlockLog;
use Pediment\Design\SiteStyles;
use Pediment\Design\StyleValidator;

class WriteAbilitiesTest extends AbilityTestCase {

	private const GOOD = "<!-- wp:paragraph -->\n<p>Hello</p>\n<!-- /wp:paragraph -->";

	private function para( string $text ): string {
		return "<!-- wp:paragraph -->\n<p>{$text}</p>\n<!-- /wp:paragraph -->";
	}

	/**
	 * A user with edit_pages but without edit_others_pages and publish_pages
	 * (an author plus edit_pages), so permission() decides, not the role.
	 */
	private function page_author(): int {
		$user = $this->user( 'author' );
		get_userdata( $user )->add_cap( 'edit_pages' );
		return $user;
	}

	public function test_all_eight_abilities_are_registered(): void {
		$this->assertSame(
			array( 'pediment/get-vocabulary', 'pediment/list-pages', 'pediment/read-page', 'pediment/check-page', 'pediment/write-page', 'pediment/rollback-page', 'pediment/upsert-style', 'pediment/delete-style' ),
			Registrar::names()
		);
		foreach ( Registrar::names() as $name ) {
			$this->assertTrue( wp_has_ability( $name ), $name );
			$meta = wp_get_ability( $name )->get_meta();
			$this->assertArrayNotHasKey( 'mcp', $meta, $name );
			$this->assertArrayNotHasKey( 'public', $meta, $name );
		}
	}

	public function test_new_page_is_a_draft_and_update_keeps_a_revision(): void {
		$editor = $this->user( 'editor' );
		$new    = $this->run_as( $editor, 'pediment/write-page', array( 'title' => 'About', 'markup' => self::GOOD ) );
		$this->assertIsArray( $new );
		$this->assertSame( 'draft', get_post_status( $new['id'] ) );
		$this->assertGreaterThan( 0, $new['revision'], 'the first content has a revision' );
		$this->assertSame( 'revision', get_post_type( $new['revision'] ) );

		InvalidBlockLog::record( $new['id'], array( 'core/group' ) );
		$updated = $this->run_as( $editor, 'pediment/write-page', array( 'id' => $new['id'], 'title' => 'About us', 'markup' => $this->para( 'Changed' ) ) );

		$this->assertSame( $new['id'], $updated['id'] );
		$this->assertSame( 'About us', get_the_title( $new['id'] ) );
		$this->assertGreaterThan( $new['revision'], $updated['revision'] );
		$this->assertNull( InvalidBlockLog::get( $new['id'] ), 'a write clears the stale editor report' );
	}

	public function test_rule_violations_are_rejected_for_admins_with_every_problem_in_the_message(): void {
		$result = $this->run_as(
			$this->user( 'administrator' ),
			'pediment/write-page',
			array(
				'title'  => 'Bad',
				'markup' => '<!-- wp:html --><script>alert(1)</script><!-- /wp:html --><!-- wp:group {"style":{"spacing":{"padding":{"top":"78px"}}}} --><div class="wp-block-group" style="padding-top:78px"></div><!-- /wp:group -->',
			)
		);
		$this->assertWPError( $result );
		$this->assertSame( 'pediment_content_invalid', $result->get_error_code() );
		$this->assertStringContainsString( 'core/html: block is not allowed', $result->get_error_message() );
		$this->assertStringContainsString( '78px', $result->get_error_message() );
		$this->assertSame( 0, (int) ( new WP_Query( array( 'post_type' => 'page', 'title' => 'Bad', 'post_status' => 'any' ) ) )->found_posts );
	}

	public function test_admin_cannot_write_core_html_or_script_and_message_names_each_violation(): void {
		$admin = $this->user( 'administrator' );

		$html = $this->run_as( $admin, 'pediment/write-page', array( 'title' => 'Html', 'markup' => "<!-- wp:html -->\n<div>raw</div>\n<!-- /wp:html -->" ) );
		$this->assertWPError( $html );
		$this->assertSame( 'pediment_content_invalid', $html->get_error_code() );
		$this->assertStringContainsString( 'core/html: block is not allowed', $html->get_error_message() );

		$script = $this->run_as( $admin, 'pediment/write-page', array( 'title' => 'Script', 'markup' => "<!-- wp:paragraph -->\n<p>Hi<script>alert(1)</script> <a href=\"javascript:alert(2)\">x</a></p>\n<!-- /wp:paragraph -->" ) );
		$this->assertWPError( $script );
		$this->assertSame( 'pediment_content_invalid', $script->get_error_code() );
		$this->assertStringContainsString( '<script> is not allowed in block markup', $script->get_error_message() );
		$this->assertStringContainsString( 'javascript: URL is not allowed in block markup', $script->get_error_message() );

		foreach ( array( 'Html', 'Script' ) as $title ) {
			$this->assertSame( 0, (int) ( new WP_Query( array( 'post_type' => 'page', 'title' => $title, 'post_status' => 'any' ) ) )->found_posts, $title );
		}
	}

	public function test_write_page_rejects_a_legacy_block(): void {
		$admin  = $this->user( 'administrator' );
		$result = $this->run_as( $admin, 'pediment/write-page', array( 'title' => 'Legacy', 'markup' => '<!-- wp:pediment/pull-quote {"quote":"Q"} /-->' ) );

		$this->assertWPError( $result );
		$this->assertSame( 'pediment_content_invalid', $result->get_error_code() );
		$this->assertStringContainsString( 'pediment/pull-quote was removed in Pediment 5; rebuild it with core blocks and named styles (pediment-get-vocabulary)', $result->get_error_message() );
		$this->assertSame( 0, (int) ( new WP_Query( array( 'post_type' => 'page', 'title' => 'Legacy', 'post_status' => 'any' ) ) )->found_posts );

		$page   = $this->run_as( $admin, 'pediment/write-page', array( 'title' => 'Kept', 'markup' => self::GOOD ) );
		$update = $this->run_as( $admin, 'pediment/write-page', array( 'id' => $page['id'], 'title' => 'Kept', 'markup' => self::GOOD . '<!-- wp:pediment/pull-quote {"quote":"Q"} /-->' ) );
		$this->assertWPError( $update, 'an update cannot add a legacy block either' );
		$this->assertSame( self::GOOD, get_post( $page['id'] )->post_content );
	}

	public function test_admin_cannot_put_markup_in_a_page_title(): void {
		$admin = $this->user( 'administrator' );
		$this->assertTrue( user_can( $admin, 'unfiltered_html' ), 'kses does not filter this user' );

		foreach ( array( '<img src=x onerror=alert(1)>', 'About <b>us</b>', '<script>alert(1)</script>About', 'About<!-- x -->' ) as $title ) {
			$result = $this->run_as( $admin, 'pediment/write-page', array( 'title' => $title, 'markup' => self::GOOD ) );
			$this->assertWPError( $result, $title );
			$this->assertSame( 'pediment_content_invalid', $result->get_error_code(), $title );
			$this->assertStringContainsString( 'title: markup is not allowed in a page title', $result->get_error_message(), $title );
		}
		$this->assertSame( 0, (int) ( new WP_Query( array( 'post_type' => 'page', 'post_status' => 'any' ) ) )->found_posts );

		$page   = $this->run_as( $admin, 'pediment/write-page', array( 'title' => 'Fish & Chips < 5 €', 'markup' => self::GOOD ) );
		$this->assertIsArray( $page, 'plain text with & and < is a title, not markup' );
		$update = $this->run_as( $admin, 'pediment/write-page', array( 'id' => $page['id'], 'title' => '<svg onload=alert(1)>', 'markup' => '<!-- wp:html --><p>x</p><!-- /wp:html -->' ) );
		$this->assertWPError( $update );
		$this->assertStringContainsString( 'title: markup is not allowed in a page title', $update->get_error_message() );
		$this->assertStringContainsString( 'core/html: block is not allowed', $update->get_error_message(), 'title and markup errors come in one message' );
		$this->assertSame( 'Fish & Chips < 5 €', get_post( $page['id'] )->post_title );
	}

	public function test_every_ability_refuses_anonymous_users_and_subscribers(): void {
		$page     = self::factory()->post->create( array( 'post_type' => 'page', 'post_title' => 'Kept', 'post_content' => self::GOOD ) );
		$revision = (int) wp_save_post_revision( $page );
		$inputs   = array(
			'pediment/get-vocabulary' => array(),
			'pediment/list-pages'     => array(),
			'pediment/read-page'      => array( 'id' => $page ),
			'pediment/check-page'     => array( 'id' => $page ),
			'pediment/write-page'     => array( 'title' => 'Denied', 'markup' => self::GOOD ),
			'pediment/rollback-page'  => array( 'id' => $page, 'revision' => max( 1, $revision ) ),
			'pediment/upsert-style'   => array( 'slug' => 'denied', 'label' => 'Denied', 'blockTypes' => array( 'core/group' ), 'styles' => array() ),
			'pediment/delete-style'   => array( 'slug' => 'card' ),
		);
		$this->assertSame( Registrar::names(), array_keys( $inputs ), 'every ability has an input here' );
		$pages = (int) wp_count_posts( 'page' )->publish + (int) wp_count_posts( 'page' )->draft;

		foreach ( array( 'anonymous' => 0, 'subscriber' => $this->user( 'subscriber' ) ) as $who => $user ) {
			foreach ( Registrar::names() as $name ) {
				$result = $this->run_as( $user, $name, $inputs[ $name ] );
				$this->assertWPError( $result, "{$who} {$name}" );
				$this->assertSame( 'ability_invalid_permissions', $result->get_error_code(), "{$who} {$name}" );
			}
		}

		wp_cache_delete( _count_posts_cache_key( 'page' ), 'counts' );
		$this->assertSame( $pages, (int) wp_count_posts( 'page' )->publish + (int) wp_count_posts( 'page' )->draft, 'no page was written' );
		$this->assertSame( 'Kept', get_the_title( $page ) );
		$this->assertNull( ( new SiteStyles( new StyleValidator() ) )->get( 'denied' ), 'no style was written' );
	}

	public function test_write_page_rejects_input_that_breaks_its_schema(): void {
		$admin = $this->user( 'administrator' );
		foreach ( array(
			'missing title'  => array( 'markup' => self::GOOD ),
			'unknown key'    => array( 'title' => 'Schema', 'markup' => self::GOOD, 'content' => 'x' ),
			'unknown status' => array( 'title' => 'Schema', 'markup' => self::GOOD, 'status' => 'trash' ),
			'empty title'    => array( 'title' => '', 'markup' => self::GOOD ),
		) as $case => $input ) {
			$result = $this->run_as( $admin, 'pediment/write-page', $input );
			$this->assertWPError( $result, $case );
			$this->assertSame( 'ability_invalid_input', $result->get_error_code(), $case );
		}
		$this->assertSame( 0, (int) ( new WP_Query( array( 'post_type' => 'page', 'post_status' => 'any' ) ) )->found_posts );
	}

	public function test_returned_revision_is_never_an_autosave(): void {
		$admin = $this->user( 'administrator' );
		$page  = $this->run_as( $admin, 'pediment/write-page', array( 'title' => 'Auto', 'markup' => $this->para( 'Saved' ) ) );

		// A person's unsaved editor work, newer than every revision.
		wp_set_current_user( $admin );
		$autosave = wp_create_post_autosave( array( 'post_ID' => $page['id'], 'post_type' => 'page', 'post_title' => 'Auto', 'content' => $this->para( 'Unsaved' ), 'excerpt' => '' ) );
		$this->assertIsInt( $autosave );
		$this->assertSame( $autosave, (int) current( wp_get_post_revisions( $page['id'], array( 'fields' => 'ids' ) ) ), 'the autosave is the newest row' );

		// Unchanged content: WordPress saves no new revision.
		$same = $this->run_as( $admin, 'pediment/write-page', array( 'id' => $page['id'], 'title' => 'Auto', 'markup' => $this->para( 'Saved' ) ) );
		$this->assertSame( $page['revision'], $same['revision'] );
		$this->assertFalse( wp_is_post_autosave( $same['revision'] ) );

		$back = $this->run_as( $admin, 'pediment/rollback-page', array( 'id' => $page['id'], 'revision' => $page['revision'] ) );
		$this->assertNotSame( $autosave, $back['revision'] );
		$this->assertFalse( wp_is_post_autosave( $back['revision'] ) );
		$this->assertSame( $page['id'], wp_get_post_parent_id( $back['revision'] ) );
	}

	public function test_edit_pages_without_edit_others_pages_cannot_overwrite_someone_elses_page(): void {
		$page   = self::factory()->post->create( array( 'post_type' => 'page', 'post_author' => $this->user( 'administrator' ), 'post_title' => 'Theirs', 'post_content' => self::GOOD ) );
		$author = $this->page_author();
		$this->assertTrue( user_can( $author, 'edit_pages' ) );
		$this->assertFalse( user_can( $author, 'edit_others_pages' ) );

		$result = $this->run_as( $author, 'pediment/write-page', array( 'id' => $page, 'title' => 'Mine now', 'markup' => self::GOOD ) );
		$this->assertWPError( $result );
		$this->assertSame( 'ability_invalid_permissions', $result->get_error_code() );
		$this->assertSame( 'Theirs', get_the_title( $page ) );
	}

	public function test_edit_pages_without_publish_pages_cannot_publish_but_can_write_own_drafts(): void {
		$author = $this->page_author();
		$this->assertFalse( user_can( $author, 'publish_pages' ) );

		foreach ( array( 'publish', 'private' ) as $status ) {
			$refused = $this->run_as( $author, 'pediment/write-page', array( 'title' => 'X', 'markup' => self::GOOD, 'status' => $status ) );
			$this->assertWPError( $refused, $status );
			$this->assertSame( 'ability_invalid_permissions', $refused->get_error_code(), $status );
		}
		$this->assertSame( 0, (int) ( new WP_Query( array( 'post_type' => 'page', 'title' => 'X', 'post_status' => 'any' ) ) )->found_posts );

		$draft = $this->run_as( $author, 'pediment/write-page', array( 'title' => 'Mine', 'markup' => self::GOOD ) );
		$this->assertIsArray( $draft );
		$this->assertSame( 'draft', $draft['status'] );

		$again = $this->run_as( $author, 'pediment/write-page', array( 'id' => $draft['id'], 'title' => 'Mine v2', 'markup' => $this->para( 'Edited' ) ) );
		$this->assertIsArray( $again );
		$this->assertSame( 'Mine v2', get_the_title( $draft['id'] ) );

		$publish = $this->run_as( $author, 'pediment/write-page', array( 'id' => $draft['id'], 'title' => 'Mine v2', 'markup' => self::GOOD, 'status' => 'publish' ) );
		$this->assertWPError( $publish );
		$this->assertSame( 'draft', get_post_status( $draft['id'] ) );
	}

	public function test_unknown_language_is_rejected_before_writing(): void {
		$result = $this->run_as( $this->user( 'editor' ), 'pediment/write-page', array( 'title' => 'Lang', 'markup' => self::GOOD, 'language' => 'xx' ) );
		$this->assertWPError( $result );
		$this->assertSame( 'pediment_language_unknown', $result->get_error_code() );
		$this->assertSame( 0, (int) ( new WP_Query( array( 'post_type' => 'page', 'title' => 'Lang', 'post_status' => 'any' ) ) )->found_posts );
	}

	public function test_translation_of_needs_a_language(): void {
		$page   = self::factory()->post->create( array( 'post_type' => 'page' ) );
		$result = $this->run_as( $this->user( 'editor' ), 'pediment/write-page', array( 'title' => 'T', 'markup' => self::GOOD, 'translation_of' => $page ) );
		$this->assertWPError( $result );
		$this->assertSame( 'pediment_language_unknown', $result->get_error_code() );
		$this->assertStringContainsString( 'translation_of', $result->get_error_message() );
	}

	public function test_bad_parent_is_a_named_error(): void {
		$post   = self::factory()->post->create( array( 'post_title' => 'A post' ) );
		$editor = $this->user( 'editor' );
		foreach ( array( $post, 987654 ) as $value ) {
			$result = $this->run_as( $editor, 'pediment/write-page', array( 'title' => 'Child', 'markup' => self::GOOD, 'parent' => $value ) );
			$this->assertWPError( $result, (string) $value );
			$this->assertSame( 'pediment_page_not_found', $result->get_error_code() );
			$this->assertStringContainsString( "parent {$value}", $result->get_error_message() );
		}
		$this->assertSame( 0, (int) ( new WP_Query( array( 'post_type' => 'page', 'title' => 'Child', 'post_status' => 'any' ) ) )->found_posts );

		$parent = self::factory()->post->create( array( 'post_type' => 'page' ) );
		$ok     = $this->run_as( $editor, 'pediment/write-page', array( 'title' => 'Child', 'markup' => self::GOOD, 'parent' => $parent ) );
		$this->assertSame( $parent, (int) get_post( $ok['id'] )->post_parent );
	}

	public function test_translation_of_a_page_the_user_cannot_edit_is_refused(): void {
		$admins = self::factory()->post->create( array( 'post_type' => 'page', 'post_author' => $this->user( 'administrator' ), 'post_status' => 'publish' ) );
		$author = $this->page_author();
		$this->assertFalse( user_can( $author, 'edit_post', $admins ) );

		$result = $this->run_as( $author, 'pediment/write-page', array( 'title' => 'T', 'markup' => self::GOOD, 'language' => 'de', 'translation_of' => $admins ) );
		$this->assertWPError( $result );
		$this->assertSame( 'ability_invalid_permissions', $result->get_error_code() );
		$this->assertSame( 0, (int) ( new WP_Query( array( 'post_type' => 'page', 'title' => 'T', 'post_status' => 'any' ) ) )->found_posts );
	}

	public function test_write_page_refuses_an_id_that_is_not_a_page(): void {
		$post   = self::factory()->post->create( array( 'post_title' => 'A post' ) );
		$result = $this->run_as( $this->user( 'editor' ), 'pediment/write-page', array( 'id' => $post, 'title' => 'Hijack', 'markup' => self::GOOD ) );
		$this->assertWPError( $result );
		$this->assertSame( 'pediment_page_not_found', $result->get_error_code() );
		$this->assertSame( 'A post', get_the_title( $post ) );
	}

	public function test_rollback_restores_and_refuses_foreign_revisions(): void {
		$editor = $this->user( 'editor' );
		$first  = $this->run_as( $editor, 'pediment/write-page', array( 'title' => 'R', 'markup' => $this->para( 'One' ) ) );
		$second = $this->run_as( $editor, 'pediment/write-page', array( 'id' => $first['id'], 'title' => 'R', 'markup' => $this->para( 'Two' ) ) );
		$other  = $this->run_as( $editor, 'pediment/write-page', array( 'title' => 'Other', 'markup' => self::GOOD ) );
		$other2 = $this->run_as( $editor, 'pediment/write-page', array( 'id' => $other['id'], 'title' => 'Other', 'markup' => $this->para( 'Other two' ) ) );
		$this->assertStringContainsString( 'Two', get_post( $first['id'] )->post_content );
		$this->assertNotSame( $first['revision'], $second['revision'] );

		$revisions = array_keys( wp_get_post_revisions( $first['id'] ) );
		$oldest    = end( $revisions );
		$this->assertStringContainsString( 'One', get_post( $oldest )->post_content );

		InvalidBlockLog::record( $first['id'], array( 'core/group' ) );
		$restored = $this->run_as( $editor, 'pediment/rollback-page', array( 'id' => $first['id'], 'revision' => $oldest ) );
		$this->assertSame( $oldest, $restored['restoredRevision'] );
		$this->assertStringContainsString( 'One', get_post( $first['id'] )->post_content );
		$this->assertNull( InvalidBlockLog::get( $first['id'] ) );

		$foreign = $this->run_as( $editor, 'pediment/rollback-page', array( 'id' => $first['id'], 'revision' => $other2['revision'] ) );
		$this->assertWPError( $foreign );
		$this->assertSame( 'pediment_revision_mismatch', $foreign->get_error_code() );
		$this->assertStringContainsString( 'One', get_post( $first['id'] )->post_content );
		$this->assertStringContainsString( 'Other two', get_post( $other['id'] )->post_content );
	}

	public function test_rollback_refuses_a_post_that_is_not_a_page(): void {
		$post = self::factory()->post->create( array( 'post_content' => 'Original' ) );
		wp_update_post( array( 'ID' => $post, 'post_content' => 'Changed' ) );
		$revisions = array_keys( wp_get_post_revisions( $post ) );
		$this->assertNotEmpty( $revisions );

		$result = $this->run_as( $this->user( 'editor' ), 'pediment/rollback-page', array( 'id' => $post, 'revision' => end( $revisions ) ) );
		$this->assertWPError( $result );
		$this->assertSame( 'pediment_page_not_found', $result->get_error_code() );
		$this->assertSame( 'Changed', get_post( $post )->post_content );
	}

	public function test_rollback_needs_edit_rights_on_that_page(): void {
		$editor = $this->user( 'editor' );
		$page   = $this->run_as( $editor, 'pediment/write-page', array( 'title' => 'Own', 'markup' => $this->para( 'One' ) ) );
		$this->run_as( $editor, 'pediment/write-page', array( 'id' => $page['id'], 'title' => 'Own', 'markup' => $this->para( 'Two' ) ) );
		$revisions = array_keys( wp_get_post_revisions( $page['id'] ) );

		$result = $this->run_as( $this->page_author(), 'pediment/rollback-page', array( 'id' => $page['id'], 'revision' => end( $revisions ) ) );
		$this->assertWPError( $result );
		$this->assertSame( 'ability_invalid_permissions', $result->get_error_code() );
		$this->assertStringContainsString( 'Two', get_post( $page['id'] )->post_content );
	}

	public function test_upsert_and_delete_style(): void {
		$admin = $this->user( 'administrator' );
		$saved = $this->run_as(
			$admin,
			'pediment/upsert-style',
			array(
				'slug'       => 'promo',
				'label'      => 'Promo',
				'blockTypes' => array( 'core/group' ),
				'styles'     => array( 'color' => array( 'background' => 'var:preset|color|accent' ) ),
			)
		);
		$this->assertSame( 'promo', $saved['slug'] );
		$this->assertGreaterThan( 0, $saved['revision'], 'creating a style saves a first revision' );
		$this->assertSame( 'revision', get_post_type( $saved['revision'] ) );
		$this->assertNotNull( ( new SiteStyles( new StyleValidator() ) )->get( 'promo' ) );

		$bad = $this->run_as( $admin, 'pediment/upsert-style', array( 'slug' => 'bad', 'label' => 'Bad', 'blockTypes' => array( 'core/group' ), 'styles' => array( 'border' => array( 'radius' => '22px' ) ) ) );
		$this->assertWPError( $bad );
		$this->assertSame( 'pediment_style_invalid', $bad->get_error_code() );
		$this->assertStringContainsString( 'styles.border.radius', $bad->get_error_message() );

		$this->assertSame( array( 'slug' => 'promo', 'deleted' => true ), $this->run_as( $admin, 'pediment/delete-style', array( 'slug' => 'promo' ) ) );
		$missing = $this->run_as( $admin, 'pediment/delete-style', array( 'slug' => 'card' ) );
		$this->assertWPError( $missing );
		$this->assertSame( 'pediment_style_not_found', $missing->get_error_code() );
	}

	public function test_editor_without_theme_rights_cannot_change_styles(): void {
		$editor = $this->user( 'editor' );
		$this->assertFalse( user_can( $editor, 'edit_theme_options' ) );
		foreach ( array(
			'pediment/upsert-style' => array( 'slug' => 'promo', 'label' => 'Promo', 'blockTypes' => array( 'core/group' ), 'styles' => array() ),
			'pediment/delete-style' => array( 'slug' => 'promo' ),
		) as $name => $input ) {
			$result = $this->run_as( $editor, $name, $input );
			$this->assertWPError( $result, $name );
			$this->assertSame( 'ability_invalid_permissions', $result->get_error_code(), $name );
		}
	}
}
