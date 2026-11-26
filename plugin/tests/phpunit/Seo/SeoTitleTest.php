<?php

use Pediment\Seo\HeadTags;
use Pediment\Seo\SeoTitle;

/**
 * The SEO title: the whole <title> of a post's view, from post meta.
 */
class SeoTitleTest extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();
		// WP_UnitTestCase unregisters every meta key after each test.
		SeoTitle::registerMeta();
	}

	public function tear_down(): void {
		remove_all_filters( HeadTags::FILTER_ENABLED );
		remove_all_filters( SeoTitle::FILTER );
		delete_option( 'show_on_front' );
		delete_option( 'page_on_front' );
		delete_option( 'page_for_posts' );
		parent::tear_down();
	}

	private function page( string $seo_title = '', array $args = array() ): int {
		$id = self::factory()->post->create( array_merge( array( 'post_type' => 'page', 'post_title' => 'Netzwerk' ), $args ) );
		if ( '' !== $seo_title ) {
			update_post_meta( $id, SeoTitle::META, $seo_title );
		}
		return $id;
	}

	private function title( string $url ): string {
		$this->go_to( $url );
		return wp_get_document_title();
	}

	public function test_the_meta_is_registered_for_rest_and_revisions(): void {
		$meta = get_registered_meta_keys( 'post' );
		$this->assertArrayHasKey( SeoTitle::META, $meta );
		$this->assertTrue( $meta[ SeoTitle::META ]['show_in_rest'] );
		$this->assertTrue( $meta[ SeoTitle::META ]['revisions_enabled'] );
		$this->assertContains( SeoTitle::META, wp_post_revision_meta_keys( 'page' ) );
	}

	public function test_the_seo_title_is_the_whole_title_with_nothing_added(): void {
		$id = $this->page( 'Netzwerkbetreuung in München: Firewall, Server, Backup | softbox' );
		$this->assertSame( 'Netzwerkbetreuung in München: Firewall, Server, Backup | softbox', $this->title( get_permalink( $id ) ) );
	}

	public function test_the_title_is_escaped(): void {
		$id = $this->page( 'Server & Backup "24/7"' );
		$this->assertSame( 'Server &amp; Backup &quot;24/7&quot;', $this->title( get_permalink( $id ) ) );
	}

	public function test_without_an_seo_title_wordpress_builds_the_title(): void {
		$id    = $this->page();
		$title = $this->title( get_permalink( $id ) );
		$this->assertStringStartsWith( 'Netzwerk ', $title );
		$this->assertStringEndsWith( get_bloginfo( 'name' ), $title );
	}

	public function test_the_static_front_page_and_the_posts_page_use_their_own(): void {
		$front = $this->page( 'softbox | IT-Systemhaus München', array( 'post_title' => 'Start' ) );
		$blog  = $this->page( 'News aus der IT | softbox', array( 'post_title' => 'News' ) );
		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', $front );
		update_option( 'page_for_posts', $blog );

		$this->assertSame( 'softbox | IT-Systemhaus München', $this->title( home_url( '/' ) ) );
		$this->assertSame( 'News aus der IT | softbox', $this->title( get_permalink( $blog ) ) );
	}

	public function test_a_post_of_another_type_uses_its_own(): void {
		$id = self::factory()->post->create( array( 'post_title' => 'Release' ) );
		update_post_meta( $id, SeoTitle::META, 'Release notes | Site' );
		$this->assertSame( 'Release notes | Site', $this->title( get_permalink( $id ) ) );
	}

	public function test_the_filter_sets_a_title_for_any_view(): void {
		$term = self::factory()->category->create( array( 'name' => 'Tips' ) );
		self::factory()->post->create( array( 'post_category' => array( $term ) ) );
		add_filter(
			SeoTitle::FILTER,
			static fn( string $title, $queried ): string => $queried instanceof WP_Term ? "{$queried->name} for IT teams | Site" : $title,
			10,
			2
		);
		$this->assertSame( 'Tips for IT teams | Site', $this->title( get_term_link( $term ) ) );
	}

	public function test_an_empty_filter_value_keeps_the_wordpress_title(): void {
		$id = $this->page( 'Custom | Site' );
		add_filter( SeoTitle::FILTER, '__return_empty_string' );
		$this->assertStringStartsWith( 'Netzwerk ', $this->title( get_permalink( $id ) ) );
	}

	public function test_nothing_changes_while_an_seo_plugin_owns_the_tags(): void {
		$id = $this->page( 'Custom | Site' );
		add_filter( HeadTags::FILTER_ENABLED, '__return_false' );
		$this->assertStringStartsWith( 'Netzwerk ', $this->title( get_permalink( $id ) ) );
	}

	public function test_a_title_another_plugin_chose_stays(): void {
		$id = $this->page( 'Custom | Site' );
		$this->go_to( get_permalink( $id ) );
		$this->assertSame( 'Chosen elsewhere', SeoTitle::documentTitle( 'Chosen elsewhere' ) );
	}

	public function test_the_value_is_stored_as_one_line_of_plain_text(): void {
		$id = $this->page();
		update_post_meta( $id, SeoTitle::META, "  Lake <b>tours</b>\nand more <script>x</script> " );
		$this->assertSame( 'Lake tours and more', SeoTitle::of( $id ) );
	}

	public function test_an_editor_saves_it_over_rest_and_an_author_cannot_for_someone_elses_page(): void {
		$id     = $this->page();
		$editor = self::factory()->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $editor );
		$request = new WP_REST_Request( 'POST', "/wp/v2/pages/{$id}" );
		$request->set_body_params( array( 'meta' => array( SeoTitle::META => 'Saved | Site' ) ) );
		$response = rest_do_request( $request );
		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'Saved | Site', $response->get_data()['meta'][ SeoTitle::META ] );
		$this->assertSame( 'Saved | Site', SeoTitle::of( $id ) );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'author' ) ) );
		$request = new WP_REST_Request( 'POST', "/wp/v2/pages/{$id}" );
		$request->set_body_params( array( 'meta' => array( SeoTitle::META => 'Hijacked' ) ) );
		$this->assertGreaterThanOrEqual( 400, rest_do_request( $request )->get_status() );
		$this->assertSame( 'Saved | Site', SeoTitle::of( $id ) );
	}
}
