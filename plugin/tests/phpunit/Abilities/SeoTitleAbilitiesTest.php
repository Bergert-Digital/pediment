<?php

require_once __DIR__ . '/AbilityTestCase.php';

use Pediment\Seo\SeoTitle;

/**
 * write-page and read-page carry the SEO title; rollback restores it.
 */
class SeoTitleAbilitiesTest extends AbilityTestCase {

	private const GOOD = "<!-- wp:paragraph -->\n<p>Hello</p>\n<!-- /wp:paragraph -->";

	private int $editor = 0;

	public function set_up(): void {
		parent::set_up();
		// WP_UnitTestCase unregisters every meta key after each test; the
		// revision of a write keeps the SEO title only while it is registered.
		SeoTitle::registerMeta();
		$this->editor = $this->user( 'editor' );
	}

	private function write( array $input ) {
		return $this->run_as( $this->editor, 'pediment/write-page', array_merge( array( 'title' => 'Netzwerk', 'markup' => self::GOOD ), $input ) );
	}

	private function read( int $id ): array {
		return $this->run_as( $this->editor, 'pediment/read-page', array( 'id' => $id ) );
	}

	public function test_write_sets_it_and_read_returns_it(): void {
		$page = $this->write( array( 'seo_title' => "  Netzwerkbetreuung in München\n| softbox " ) );
		$this->assertIsArray( $page );
		$this->assertSame( 'Netzwerkbetreuung in München | softbox', $this->read( $page['id'] )['seo_title'] );
		$this->assertSame( 'Netzwerk', $this->read( $page['id'] )['title'] );
	}

	public function test_read_returns_an_empty_string_without_one(): void {
		$page = $this->write( array() );
		$this->assertSame( '', $this->read( $page['id'] )['seo_title'] );
	}

	public function test_an_update_without_it_keeps_it_and_an_empty_string_clears_it(): void {
		$page = $this->write( array( 'seo_title' => 'Kept | Site' ) );
		$this->write( array( 'id' => $page['id'], 'title' => 'Netz' ) );
		$this->assertSame( 'Kept | Site', SeoTitle::of( $page['id'] ) );

		$this->write( array( 'id' => $page['id'], 'seo_title' => '' ) );
		$this->assertSame( '', SeoTitle::of( $page['id'] ) );
	}

	public function test_markup_is_refused_with_the_other_violations(): void {
		$result = $this->write( array( 'seo_title' => 'Net <b>work</b>', 'markup' => "<!-- wp:html -->\n<p>raw</p>\n<!-- /wp:html -->" ) );
		$this->assertWPError( $result );
		$this->assertSame( 'pediment_content_invalid', $result->get_error_code() );
		$this->assertStringContainsString( 'seo_title: markup is not allowed', $result->get_error_message() );
		$this->assertStringContainsString( 'core/html: block is not allowed', $result->get_error_message() );
	}

	public function test_a_write_that_changes_only_the_seo_title_keeps_a_revision_and_rollback_restores_it(): void {
		$first  = $this->write( array( 'seo_title' => 'First | Site' ) );
		$second = $this->write( array( 'id' => $first['id'], 'seo_title' => 'Second | Site' ) );
		$this->assertNotSame( $first['revision'], $second['revision'], 'a changed SEO title alone saves a revision' );

		$back = $this->run_as( $this->editor, 'pediment/rollback-page', array( 'id' => $first['id'], 'revision' => $first['revision'] ) );
		$this->assertIsArray( $back );
		$this->assertSame( 'First | Site', SeoTitle::of( $first['id'] ) );
	}

	public function test_a_post_of_another_served_type_takes_it(): void {
		$post = $this->write( array( 'post_type' => 'post', 'title' => 'News', 'seo_title' => 'News | Site' ) );
		$this->assertIsArray( $post );
		$this->assertSame( 'News | Site', SeoTitle::of( $post['id'] ) );
	}
}
