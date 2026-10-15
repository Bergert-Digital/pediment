<?php

require_once __DIR__ . '/AbilityTestCase.php';

use Pediment\Abilities\Registrar;
use Pediment\Content\InvalidBlockLog;

class ReadAbilitiesTest extends AbilityTestCase {

	public function test_category_and_read_abilities_are_registered_without_public_mcp_flag(): void {
		$this->assertTrue( wp_has_ability_category( 'pediment' ) );
		foreach ( array( 'pediment/get-vocabulary', 'pediment/list-pages', 'pediment/read-page', 'pediment/check-page' ) as $name ) {
			$this->assertTrue( wp_has_ability( $name ), $name );
			$meta = wp_get_ability( $name )->get_meta();
			$this->assertTrue( $meta['show_in_rest'] );
			$this->assertTrue( $meta['annotations']['readonly'] );
			$this->assertArrayNotHasKey( 'mcp', $meta );
			$this->assertArrayNotHasKey( 'public', $meta );
		}
		$this->assertContains( 'pediment/check-page', Registrar::names() );
	}

	public function test_get_vocabulary(): void {
		$result = $this->run_as( $this->user( 'editor' ), 'pediment/get-vocabulary', array() );

		$this->assertIsArray( $result );
		$this->assertSame( get_bloginfo( 'version' ), $result['wordpress'] );
		$this->assertContains( 'card', array_column( $result['styles'], 'slug' ) );
		$this->assertContains( 'core/paragraph', $result['allowedBlocks'] );
		$this->assertNotEmpty( $result['tokens']['colors'] );
		$this->assertSame( '12px', $result['tokens']['radius']['md'] );
		$this->assertNotEmpty( $result['rules'] );
	}

	public function test_subscriber_cannot_read(): void {
		$result = $this->run_as( $this->user( 'subscriber' ), 'pediment/get-vocabulary', array() );
		$this->assertWPError( $result );
		$this->assertSame( 'ability_invalid_permissions', $result->get_error_code() );
	}

	public function test_list_and_read_pages(): void {
		$parent = self::factory()->post->create( array( 'post_type' => 'page', 'post_title' => 'Parent', 'post_status' => 'publish' ) );
		$child  = self::factory()->post->create( array( 'post_type' => 'page', 'post_title' => 'Child', 'post_parent' => $parent, 'post_status' => 'draft', 'post_content' => '<!-- wp:paragraph --><p>x</p><!-- /wp:paragraph -->' ) );
		$editor = $this->user( 'editor' );

		$all = $this->run_as( $editor, 'pediment/list-pages', array() );
		$this->assertEqualsCanonicalizing( array( $parent, $child ), array_column( $all['pages'], 'id' ) );

		$children = $this->run_as( $editor, 'pediment/list-pages', array( 'parent' => $parent ) );
		$this->assertSame( array( $child ), array_column( $children['pages'], 'id' ) );

		$page = $this->run_as( $editor, 'pediment/read-page', array( 'id' => $child ) );
		$this->assertSame( 'Child', $page['title'] );
		$this->assertSame( $parent, $page['parent'] );
		$this->assertSame( 'draft', $page['status'] );
		$this->assertStringContainsString( '<p>x</p>', $page['markup'] );
	}

	public function test_read_and_check_page_refuse_posts_and_missing_ids(): void {
		$post  = self::factory()->post->create( array( 'post_type' => 'post' ) );
		$admin = $this->user( 'administrator' );
		foreach ( array( 'pediment/read-page', 'pediment/check-page' ) as $name ) {
			foreach ( array( $post, 999999 ) as $id ) {
				$result = $this->run_as( $admin, $name, array( 'id' => $id ) );
				$this->assertWPError( $result, $name );
				$this->assertSame( 'pediment_page_not_found', $result->get_error_code(), $name );
			}
		}
	}

	public function test_read_and_check_page_need_edit_rights_on_that_page(): void {
		$page   = self::factory()->post->create( array( 'post_type' => 'page', 'post_status' => 'publish' ) );
		$author = $this->user( 'author' );
		foreach ( array( 'pediment/read-page', 'pediment/check-page' ) as $name ) {
			$result = $this->run_as( $author, $name, array( 'id' => $page ) );
			$this->assertWPError( $result, $name );
			$this->assertSame( 'ability_invalid_permissions', $result->get_error_code(), $name );
		}
	}

	public function test_check_page_reports_rules_invalid_blocks_and_legacy_blocks(): void {
		$page = self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_content' => '<!-- wp:group {"style":{"spacing":{"padding":{"top":"78px"}}}} --><div class="wp-block-group" style="padding-top:78px"></div><!-- /wp:group --><!-- wp:pediment/hero /-->',
			)
		);
		InvalidBlockLog::record( $page, array( 'core/group' ) );

		$result = $this->run_as( $this->user( 'editor' ), 'pediment/check-page', array( 'id' => $page ) );

		$this->assertNotEmpty( array_filter( $result['errors'], static fn( string $e ): bool => str_contains( $e, '78px' ) ) );
		$this->assertSame( array( 'core/group' ), $result['invalidBlocks']['blocks'] );
		$this->assertSame( array( 'pediment/hero' ), $result['legacyBlocks'] );
	}

	public function test_check_page_reports_null_invalid_blocks_without_a_report(): void {
		$page   = self::factory()->post->create( array( 'post_type' => 'page' ) );
		$result = $this->run_as( $this->user( 'editor' ), 'pediment/check-page', array( 'id' => $page ) );

		$this->assertNull( $result['invalidBlocks'] );
		$this->assertSame( array(), $result['legacyBlocks'] );
	}
}
