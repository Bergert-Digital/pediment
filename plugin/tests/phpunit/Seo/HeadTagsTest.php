<?php

use Pediment\Seo\HeadTags;

/**
 * The meta description, Open Graph and Twitter tags in <head>.
 */
class HeadTagsTest extends WP_UnitTestCase {

	public function tear_down(): void {
		remove_all_filters( HeadTags::FILTER_ENABLED );
		remove_all_filters( HeadTags::FILTER_DESCRIPTION );
		remove_all_filters( HeadTags::FILTER_IMAGE );
		remove_all_filters( HeadTags::FILTER_TAGS );
		delete_option( HeadTags::SHARE_IMAGE_OPTION );
		delete_option( 'show_on_front' );
		delete_option( 'page_on_front' );
		delete_option( 'page_for_posts' );
		parent::tear_down();
	}

	private function head( string $url ): string {
		$this->go_to( $url );
		ob_start();
		HeadTags::print();
		return (string) ob_get_clean();
	}

	private function page( array $args = array() ): int {
		return self::factory()->post->create(
			array_merge(
				array(
					'post_type'    => 'page',
					'post_title'   => 'Lake tours',
					'post_excerpt' => '',
					'post_content' => "<!-- wp:paragraph -->\n<p>We row across the lake every morning.</p>\n<!-- /wp:paragraph -->",
				),
				$args
			)
		);
	}

	private function image(): int {
		$id = self::factory()->attachment->create_upload_object( DIR_TESTDATA . '/images/canola.jpg' );
		update_post_meta( $id, '_wp_attachment_image_alt', 'Yellow field' );
		return $id;
	}

	public function test_pages_support_the_excerpt(): void {
		$this->assertTrue( post_type_supports( 'page', 'excerpt' ) );
	}

	public function test_a_page_prints_its_excerpt_and_the_share_tags(): void {
		$id   = $this->page( array( 'post_excerpt' => 'Morning tours "for" families & friends.' ) );
		$html = $this->head( get_permalink( $id ) );

		$this->assertStringContainsString( '<meta name="description" content="Morning tours &quot;for&quot; families &amp; friends." />', $html );
		$this->assertStringContainsString( '<meta property="og:title" content="Lake tours" />', $html );
		$this->assertStringContainsString( '<meta property="og:description" content="Morning tours &quot;for&quot; families &amp; friends." />', $html );
		$this->assertStringContainsString( '<meta property="og:url" content="' . esc_url( get_permalink( $id ) ) . '" />', $html );
		$this->assertStringContainsString( '<meta property="og:type" content="website" />', $html );
		$this->assertStringContainsString( '<meta property="og:site_name" content="' . esc_attr( get_bloginfo( 'name' ) ) . '" />', $html );
		$this->assertStringContainsString( '<meta property="og:locale" content="en_US" />', $html );
		$this->assertStringContainsString( '<meta name="twitter:card" content="summary" />', $html );
		$this->assertStringNotContainsString( 'og:image', $html );
	}

	public function test_without_an_excerpt_only_og_description_falls_back_to_the_text(): void {
		$long = str_repeat( 'Paddle along the quiet shore. ', 10 );
		$id   = $this->page( array( 'post_content' => "<!-- wp:paragraph -->\n<p>{$long}</p>\n<!-- /wp:paragraph -->" ) );
		$html = $this->head( get_permalink( $id ) );

		$this->assertStringNotContainsString( 'name="description"', $html );
		$this->assertMatchesRegularExpression( '/<meta property="og:description" content="Paddle along the quiet shore\. [^"]*…" \/>/u', $html );
		preg_match( '/og:description" content="([^"]*)"/u', $html, $match );
		$this->assertLessThanOrEqual( 156, mb_strlen( $match[1] ) );
	}

	public function test_a_post_is_an_article(): void {
		$id = self::factory()->post->create( array( 'post_title' => 'News' ) );
		$this->assertStringContainsString( '<meta property="og:type" content="article" />', $this->head( get_permalink( $id ) ) );
	}

	public function test_the_featured_image_is_the_share_image(): void {
		$image = $this->image();
		$id    = $this->page();
		set_post_thumbnail( $id, $image );
		$html = $this->head( get_permalink( $id ) );
		$src  = wp_get_attachment_image_src( $image, 'full' );

		$this->assertStringContainsString( '<meta property="og:image" content="' . esc_url( $src[0] ) . '" />', $html );
		$this->assertStringContainsString( '<meta property="og:image:width" content="' . $src[1] . '" />', $html );
		$this->assertStringContainsString( '<meta property="og:image:alt" content="Yellow field" />', $html );
		$this->assertStringContainsString( '<meta name="twitter:card" content="summary_large_image" />', $html );
	}

	public function test_the_site_share_image_is_the_fallback(): void {
		$image = $this->image();
		update_option( HeadTags::SHARE_IMAGE_OPTION, $image );
		$html = $this->head( get_permalink( $this->page() ) );

		$this->assertStringContainsString( '<meta property="og:image" content="' . esc_url( wp_get_attachment_url( $image ) ) . '" />', $html );

		wp_trash_post( $image );
		$this->assertStringNotContainsString( 'og:image', $this->head( get_permalink( $this->page() ) ) );
	}

	public function test_a_theme_can_name_a_share_image_by_url(): void {
		add_filter( HeadTags::FILTER_IMAGE, static fn() => 'https://example.test/share.jpg' );
		$html = $this->head( get_permalink( $this->page() ) );

		$this->assertStringContainsString( '<meta property="og:image" content="https://example.test/share.jpg" />', $html );
		$this->assertStringContainsString( 'summary_large_image', $html );
	}

	public function test_the_filters_change_and_remove_tags(): void {
		add_filter( HeadTags::FILTER_DESCRIPTION, static fn( $text, $object ) => $object instanceof WP_Post ? 'From the theme.' : $text, 10, 2 );
		add_filter(
			HeadTags::FILTER_TAGS,
			static function ( array $tags ): array {
				$tags['og:type']      = '';
				$tags['twitter:site'] = '@softbox';
				return $tags;
			}
		);
		$html = $this->head( get_permalink( $this->page() ) );

		$this->assertStringContainsString( '<meta name="description" content="From the theme." />', $html );
		$this->assertStringNotContainsString( 'og:type', $html );
		$this->assertStringContainsString( '<meta name="twitter:site" content="@softbox" />', $html );
	}

	public function test_markup_in_values_is_stripped_and_escaped(): void {
		$id = $this->page();
		// Written straight to the row, past kses, as a migration would.
		global $wpdb;
		$wpdb->update( $wpdb->posts, array( 'post_excerpt' => '<script>alert(1)</script><b>Safe</b> "text"' ), array( 'ID' => $id ) );
		clean_post_cache( $id );
		$html = $this->head( get_permalink( $id ) );

		$this->assertStringNotContainsString( '<script', $html );
		$this->assertStringNotContainsString( 'alert', $html );
		$this->assertStringContainsString( '<meta name="description" content="Safe &quot;text&quot;" />', $html );
	}

	public function test_a_protected_page_shows_no_text(): void {
		$id   = $this->page( array( 'post_excerpt' => 'Secret summary.', 'post_password' => 'pw' ) );
		$html = $this->head( get_permalink( $id ) );

		$this->assertStringNotContainsString( 'Secret summary', $html );
		$this->assertStringNotContainsString( 'We row across', $html );
	}

	public function test_the_static_front_page_uses_its_own_excerpt_and_the_home_url(): void {
		$id = $this->page( array( 'post_excerpt' => 'Welcome to the lake.' ) );
		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', $id );
		$html = $this->head( home_url( '/' ) );

		$this->assertStringContainsString( '<meta name="description" content="Welcome to the lake." />', $html );
		$this->assertStringContainsString( '<meta property="og:url" content="' . esc_url( home_url( '/' ) ) . '" />', $html );
		$this->assertStringContainsString( '<meta property="og:title" content="' . esc_attr( get_bloginfo( 'name' ) ) . '" />', $html );
	}

	public function test_the_posts_page_uses_its_page(): void {
		$front = $this->page();
		$blog  = $this->page( array( 'post_title' => 'Journal', 'post_excerpt' => 'Notes from the lake.' ) );
		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', $front );
		update_option( 'page_for_posts', $blog );
		$html = $this->head( get_permalink( $blog ) );

		$this->assertStringContainsString( '<meta name="description" content="Notes from the lake." />', $html );
		$this->assertStringContainsString( '<meta property="og:title" content="Journal" />', $html );
	}

	public function test_a_blog_front_page_uses_the_tagline(): void {
		update_option( 'blogdescription', 'Tours on the lake' );
		$html = $this->head( home_url( '/' ) );

		$this->assertStringContainsString( '<meta name="description" content="Tours on the lake" />', $html );
		$this->assertStringContainsString( '<meta property="og:title" content="' . esc_attr( get_bloginfo( 'name' ) ) . '" />', $html );
	}

	public function test_a_category_uses_its_description(): void {
		$term = self::factory()->category->create( array( 'name' => 'Tours', 'description' => 'All our tours.' ) );
		self::factory()->post->create( array( 'post_category' => array( $term ) ) );
		$html = $this->head( get_term_link( $term ) );

		$this->assertStringContainsString( '<meta name="description" content="All our tours." />', $html );
		$this->assertStringContainsString( '<meta property="og:title" content="Tours" />', $html );
	}

	public function test_nothing_is_printed_when_turned_off_or_on_a_404(): void {
		$id = $this->page( array( 'post_excerpt' => 'Summary.' ) );
		add_filter( HeadTags::FILTER_ENABLED, '__return_false' );
		$this->assertSame( '', $this->head( get_permalink( $id ) ) );

		remove_all_filters( HeadTags::FILTER_ENABLED );
		$this->assertSame( '', $this->head( home_url( '/?p=999999' ) ) );
	}

	public function test_the_tags_are_hooked_on_wp_head(): void {
		$this->assertSame( 5, has_action( 'wp_head', array( HeadTags::class, 'print' ) ) );
	}
}
