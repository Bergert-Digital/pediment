<?php

require_once __DIR__ . '/AbilityTestCase.php';

use Pediment\Abilities\PostTypes;
use Pediment\Content\InvalidBlockLog;

/**
 * The page abilities on posts and custom post types (post_type input).
 */
class PostTypeAbilitiesTest extends AbilityTestCase {

	private const GOOD = "<!-- wp:paragraph -->\n<p>Hello</p>\n<!-- /wp:paragraph -->";

	public function set_up(): void {
		parent::set_up();
		// A served CPT with post capabilities and revisions.
		register_post_type( 'guide', array( 'public' => true, 'show_in_rest' => true, 'supports' => array( 'title', 'editor', 'revisions' ) ) );
		// Hierarchical, without revisions.
		register_post_type( 'chapter', array( 'public' => true, 'show_in_rest' => true, 'hierarchical' => true, 'supports' => array( 'title', 'editor', 'page-attributes' ) ) );
		// Own capabilities: no default role has them.
		register_post_type( 'recipe', array( 'public' => true, 'show_in_rest' => true, 'capability_type' => array( 'recipe', 'recipes' ), 'map_meta_cap' => true, 'supports' => array( 'title', 'editor', 'revisions' ) ) );
		// Not in the REST API, so never served.
		register_post_type( 'hidden', array( 'public' => true, 'show_in_rest' => false, 'supports' => array( 'title', 'editor' ) ) );
	}

	public function tear_down(): void {
		foreach ( array( 'guide', 'chapter', 'recipe', 'hidden' ) as $type ) {
			unregister_post_type( $type );
		}
		remove_all_filters( PostTypes::FILTER );
		parent::tear_down();
	}

	private function stored( string $type, string $title ): int {
		return (int) ( new WP_Query( array( 'post_type' => $type, 'title' => $title, 'post_status' => 'any' ) ) )->found_posts;
	}

	public function test_served_types_follow_the_rules_and_the_filter_cannot_break_them(): void {
		$served = PostTypes::served();
		foreach ( array( 'page', 'post', 'guide', 'chapter', 'recipe' ) as $type ) {
			$this->assertContains( $type, $served, $type );
		}
		foreach ( array( 'hidden', 'wp_block', 'wp_template', 'wp_navigation', 'attachment', 'pediment_style' ) as $type ) {
			$this->assertNotContains( $type, $served, $type );
		}

		add_filter( PostTypes::FILTER, static fn( array $types ): array => array_merge( array_diff( $types, array( 'guide' ) ), array( 'hidden', 'wp_block', 'pediment_style', 'nope' ) ) );
		$filtered = PostTypes::served();
		$this->assertNotContains( 'guide', $filtered, 'the filter removes a type' );
		foreach ( array( 'hidden', 'wp_block', 'pediment_style', 'nope' ) as $type ) {
			$this->assertNotContains( $type, $filtered, "the filter cannot add {$type}" );
		}
	}

	public function test_get_vocabulary_lists_the_served_types(): void {
		$result = $this->run_as( $this->user( 'editor' ), 'pediment/get-vocabulary', array() );
		$types  = array_column( $result['postTypes'], null, 'slug' );

		$this->assertSame( array( 'slug' => 'guide', 'label' => 'Post', 'hierarchical' => false, 'revisions' => true, 'translated' => false ), $types['guide'] );
		$this->assertTrue( $types['chapter']['hierarchical'] );
		$this->assertFalse( $types['chapter']['revisions'] );
		$this->assertArrayNotHasKey( 'hidden', $types );
	}

	public function test_a_guide_is_written_listed_read_checked_and_rolled_back(): void {
		$editor = $this->user( 'editor' );
		$new    = $this->run_as( $editor, 'pediment/write-page', array( 'post_type' => 'guide', 'title' => 'Packing', 'markup' => self::GOOD ) );
		$this->assertIsArray( $new );
		$this->assertSame( 'guide', get_post_type( $new['id'] ) );
		$this->assertSame( 'draft', $new['status'] );
		$this->assertGreaterThan( 0, $new['revision'] );
		$this->assertSame( array(), $new['warnings'] );

		$updated = $this->run_as( $editor, 'pediment/write-page', array( 'post_type' => 'guide', 'id' => $new['id'], 'title' => 'Packing list', 'markup' => "<!-- wp:paragraph -->\n<p>Changed</p>\n<!-- /wp:paragraph -->" ) );
		$this->assertSame( $new['id'], $updated['id'] );

		$page   = self::factory()->post->create( array( 'post_type' => 'page', 'post_title' => 'A page' ) );
		$listed = $this->run_as( $editor, 'pediment/list-pages', array( 'post_type' => 'guide' ) );
		$this->assertSame( array( $new['id'] ), array_column( $listed['pages'], 'id' ) );
		$this->assertSame( 'guide', $listed['pages'][0]['type'] );
		$this->assertSame( array( $page ), array_column( $this->run_as( $editor, 'pediment/list-pages', array() )['pages'], 'id' ), 'the default lists pages only' );

		$read = $this->run_as( $editor, 'pediment/read-page', array( 'post_type' => 'guide', 'id' => $new['id'] ) );
		$this->assertSame( 'Packing list', $read['title'] );
		$this->assertSame( 'guide', $read['type'] );
		$this->assertStringContainsString( 'Changed', $read['markup'] );

		$check = $this->run_as( $editor, 'pediment/check-page', array( 'post_type' => 'guide', 'id' => $new['id'] ) );
		$this->assertSame( array(), $check['errors'] );

		$back = $this->run_as( $editor, 'pediment/rollback-page', array( 'post_type' => 'guide', 'id' => $new['id'], 'revision' => $new['revision'] ) );
		$this->assertSame( $new['revision'], $back['restoredRevision'] );
		$this->assertSame( self::GOOD, get_post( $new['id'] )->post_content );
	}

	public function test_content_rules_apply_to_every_type(): void {
		$result = $this->run_as( $this->user( 'administrator' ), 'pediment/write-page', array( 'post_type' => 'post', 'title' => 'Bad post', 'markup' => "<!-- wp:html -->\n<p>raw</p>\n<!-- /wp:html -->" ) );
		$this->assertWPError( $result );
		$this->assertSame( 'pediment_content_invalid', $result->get_error_code() );
		$this->assertSame( 0, $this->stored( 'post', 'Bad post' ) );
	}

	public function test_an_id_of_another_type_is_refused_and_the_error_names_its_type(): void {
		$admin = $this->user( 'administrator' );
		$guide = self::factory()->post->create( array( 'post_type' => 'guide', 'post_title' => 'Kept', 'post_content' => self::GOOD ) );

		foreach ( array( 'pediment/read-page', 'pediment/check-page' ) as $name ) {
			$result = $this->run_as( $admin, $name, array( 'id' => $guide ) );
			$this->assertWPError( $result, $name );
			$this->assertSame( 'pediment_page_not_found', $result->get_error_code(), $name );
			$this->assertStringContainsString( 'pass post_type "guide"', $result->get_error_message(), $name );
		}

		$write = $this->run_as( $admin, 'pediment/write-page', array( 'post_type' => 'post', 'id' => $guide, 'title' => 'Hijack', 'markup' => self::GOOD ) );
		$this->assertWPError( $write );
		$this->assertSame( 'pediment_page_not_found', $write->get_error_code() );
		$this->assertSame( 'Kept', get_the_title( $guide ) );

		$hidden = self::factory()->post->create( array( 'post_type' => 'hidden' ) );
		$result = $this->run_as( $admin, 'pediment/read-page', array( 'id' => $hidden ) );
		$this->assertStringNotContainsString( 'pass post_type', $result->get_error_message(), 'no hint towards a type that is not served' );
	}

	public function test_a_type_that_is_not_served_is_a_named_error(): void {
		$admin = $this->user( 'administrator' );
		foreach ( array( 'hidden', 'wp_block', 'pediment_style', 'nope' ) as $type ) {
			foreach ( array(
				'pediment/list-pages' => array( 'post_type' => $type ),
				'pediment/read-page'  => array( 'post_type' => $type, 'id' => 1 ),
				'pediment/write-page' => array( 'post_type' => $type, 'title' => 'Refused', 'markup' => self::GOOD ),
			) as $name => $input ) {
				$result = $this->run_as( $admin, $name, $input );
				$this->assertWPError( $result, "{$name} {$type}" );
				$this->assertSame( 'pediment_post_type_unavailable', $result->get_error_code(), "{$name} {$type}" );
				$this->assertStringContainsString( 'use one of: page, post', $result->get_error_message() );
			}
		}
		$this->assertSame( 0, $this->stored( 'any', 'Refused' ) );
	}

	public function test_capabilities_come_from_the_post_type(): void {
		$editor = $this->user( 'editor' );
		$this->assertFalse( user_can( $editor, 'edit_recipes' ) );
		foreach ( array(
			'pediment/list-pages' => array( 'post_type' => 'recipe' ),
			'pediment/write-page' => array( 'post_type' => 'recipe', 'title' => 'Soup', 'markup' => self::GOOD ),
		) as $name => $input ) {
			$result = $this->run_as( $editor, $name, $input );
			$this->assertWPError( $result, $name );
			$this->assertSame( 'ability_invalid_permissions', $result->get_error_code(), $name );
		}
		$this->assertSame( 0, $this->stored( 'recipe', 'Soup' ) );

		$cook = $this->user( 'subscriber' );
		get_userdata( $cook )->add_cap( 'edit_recipes' );
		$this->assertNotWPError( $this->run_as( $cook, 'pediment/get-vocabulary', array() ), 'edit rights on one served type are enough' );
		$draft = $this->run_as( $cook, 'pediment/write-page', array( 'post_type' => 'recipe', 'title' => 'Soup', 'markup' => self::GOOD ) );
		$this->assertIsArray( $draft );
		$this->assertSame( 'draft', $draft['status'] );

		$publish = $this->run_as( $cook, 'pediment/write-page', array( 'post_type' => 'recipe', 'id' => $draft['id'], 'title' => 'Soup', 'markup' => self::GOOD, 'status' => 'publish' ) );
		$this->assertWPError( $publish );
		$this->assertSame( 'ability_invalid_permissions', $publish->get_error_code() );
		$this->assertWPError( $this->run_as( $cook, 'pediment/list-pages', array() ), 'no page rights' );

		get_userdata( $cook )->add_cap( 'publish_recipes' );
		// wp_set_current_user() keeps the cached user object when the id is unchanged.
		wp_set_current_user( 0 );
		$published = $this->run_as( $cook, 'pediment/write-page', array( 'post_type' => 'recipe', 'id' => $draft['id'], 'title' => 'Soup', 'markup' => self::GOOD, 'status' => 'publish' ) );
		$this->assertNotWPError( $published, is_wp_error( $published ) ? $published->get_error_message() : '' );
		$this->assertSame( 'publish', $published['status'] );
	}

	public function test_an_author_can_write_posts_but_not_pages(): void {
		$author = $this->user( 'author' );
		$post   = $this->run_as( $author, 'pediment/write-page', array( 'post_type' => 'post', 'title' => 'News', 'markup' => self::GOOD, 'status' => 'publish' ) );
		$this->assertIsArray( $post );
		$this->assertSame( 'publish', $post['status'] );

		$page = $this->run_as( $author, 'pediment/write-page', array( 'title' => 'News', 'markup' => self::GOOD ) );
		$this->assertWPError( $page );
		$this->assertSame( 'ability_invalid_permissions', $page->get_error_code() );
	}

	public function test_parent_needs_a_hierarchical_type_and_a_parent_of_that_type(): void {
		$editor = $this->user( 'editor' );
		$guide  = self::factory()->post->create( array( 'post_type' => 'guide' ) );

		$flat = $this->run_as( $editor, 'pediment/write-page', array( 'post_type' => 'guide', 'title' => 'Child', 'markup' => self::GOOD, 'parent' => $guide ) );
		$this->assertWPError( $flat );
		$this->assertSame( 'pediment_parent_unsupported', $flat->get_error_code() );

		$page    = self::factory()->post->create( array( 'post_type' => 'page' ) );
		$foreign = $this->run_as( $editor, 'pediment/write-page', array( 'post_type' => 'chapter', 'title' => 'Child', 'markup' => self::GOOD, 'parent' => $page ) );
		$this->assertWPError( $foreign );
		$this->assertSame( 'pediment_page_not_found', $foreign->get_error_code() );
		$this->assertStringContainsString( "parent {$page} is not a chapter", $foreign->get_error_message() );
		$this->assertSame( 0, $this->stored( 'any', 'Child' ) );

		$chapter = self::factory()->post->create( array( 'post_type' => 'chapter' ) );
		$child   = $this->run_as( $editor, 'pediment/write-page', array( 'post_type' => 'chapter', 'title' => 'Child', 'markup' => self::GOOD, 'parent' => $chapter ) );
		$this->assertSame( $chapter, wp_get_post_parent_id( $child['id'] ) );
	}

	public function test_a_type_without_revisions_warns_that_the_write_cannot_be_rolled_back(): void {
		$result = $this->run_as( $this->user( 'editor' ), 'pediment/write-page', array( 'post_type' => 'chapter', 'title' => 'One', 'markup' => self::GOOD ) );
		$this->assertSame( 0, $result['revision'] );
		$this->assertContains( 'post_type "chapter" keeps no revisions on this site; this write cannot be rolled back', $result['warnings'] );
	}

	public function test_language_on_a_monolingual_site_is_still_an_unknown_language(): void {
		$result = $this->run_as( $this->user( 'editor' ), 'pediment/write-page', array( 'post_type' => 'guide', 'title' => 'Lang', 'markup' => self::GOOD, 'language' => 'de' ) );
		$this->assertWPError( $result );
		$this->assertSame( 'pediment_language_unknown', $result->get_error_code() );
	}

	public function test_a_write_clears_the_editor_report_of_a_guide(): void {
		$editor = $this->user( 'editor' );
		$guide  = $this->run_as( $editor, 'pediment/write-page', array( 'post_type' => 'guide', 'title' => 'Report', 'markup' => self::GOOD ) );
		InvalidBlockLog::record( $guide['id'], array( 'core/group' ) );
		$this->assertSame( array( 'core/group' ), $this->run_as( $editor, 'pediment/check-page', array( 'post_type' => 'guide', 'id' => $guide['id'] ) )['invalidBlocks']['blocks'] );

		$this->run_as( $editor, 'pediment/write-page', array( 'post_type' => 'guide', 'id' => $guide['id'], 'title' => 'Report', 'markup' => self::GOOD ) );
		$this->assertNull( InvalidBlockLog::get( $guide['id'] ) );
	}
}
