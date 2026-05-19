<?php

class BlogIndexTest extends WP_UnitTestCase {
	private function render( string $json ): string {
		return do_blocks( '<!-- wp:starter/blog-index ' . $json . ' /-->' );
	}

	public function test_renders_recent_posts() {
		$post_ids = array();
		foreach ( array( 'First post', 'Second post', 'Third post' ) as $title ) {
			$post_ids[] = $this->factory->post->create(
				array(
					'post_title'  => $title,
					'post_status' => 'publish',
					'post_type'   => 'post',
				)
			);
		}

		$html = do_blocks( '<!-- wp:starter/blog-index {"count":3} /-->' );

		$this->assertStringContainsString( 'First post', $html );
		$this->assertStringContainsString( 'Second post', $html );
		$this->assertStringContainsString( 'Third post', $html );

		foreach ( $post_ids as $id ) {
			wp_delete_post( $id, true );
		}
	}

	public function test_filters_by_category_slug() {
		$cat_id = $this->factory->category->create( array( 'slug' => 'news', 'name' => 'News' ) );
		$in_id  = $this->factory->post->create( array( 'post_title' => 'News one',  'post_status' => 'publish', 'post_category' => array( $cat_id ) ) );
		$out_id = $this->factory->post->create( array( 'post_title' => 'Other one', 'post_status' => 'publish' ) );

		$html = do_blocks( '<!-- wp:starter/blog-index {"count":10,"categorySlug":"news"} /-->' );

		$this->assertStringContainsString( 'News one', $html );
		$this->assertStringNotContainsString( 'Other one', $html );

		wp_delete_post( $in_id, true );
		wp_delete_post( $out_id, true );
		wp_delete_category( $cat_id );
	}

	public function test_renders_empty_state_when_no_posts() {
		$html = do_blocks( '<!-- wp:starter/blog-index {"count":3} /-->' );
		$this->assertStringContainsString( 'starter-blog-index__empty', $html );
	}

	public function test_block_json_has_viewscript_and_showfilter_default() {
		$path = dirname( __DIR__, 3 ) . '/src/blocks/blog-index/block.json';
		$this->assertFileIsReadable( $path );
		$data = json_decode( file_get_contents( $path ), true );
		$this->assertIsArray( $data );
		$this->assertSame( 'file:./view.js', $data['viewScript'] );
		$this->assertTrue( $data['attributes']['showFilter']['default'] );
	}

	public function test_card_has_featured_image_and_category_badge() {
		$cat_id = $this->factory->category->create( array( 'slug' => 'briefing', 'name' => 'Briefing' ) );
		$att_id = self::factory()->attachment->create_upload_object( DIR_TESTDATA . '/images/canola.jpg' );
		$post_id = $this->factory->post->create(
			array(
				'post_title'    => 'Imaged post',
				'post_status'   => 'publish',
				'post_category' => array( $cat_id ),
			)
		);
		set_post_thumbnail( $post_id, $att_id );

		$html = $this->render( '{"count":5}' );

		$this->assertStringContainsString( 'starter-blog-index__media', $html );
		$this->assertStringContainsString( 'starter-blog-index__img', $html );
		$this->assertStringContainsString( '<img', $html );
		$this->assertStringContainsString( 'starter-blog-index__badge', $html );
		$this->assertStringContainsString( 'starter-blog-index__badge--briefing', $html );
		$this->assertStringContainsString( 'Briefing', $html );
		$this->assertStringContainsString( 'data-cat="briefing"', $html );

		wp_delete_post( $post_id, true );
		wp_delete_attachment( $att_id, true );
		wp_delete_category( $cat_id );
	}

	public function test_filter_bar_lists_categories_when_multiple_and_enabled() {
		$a = $this->factory->category->create( array( 'slug' => 'article', 'name' => 'Article' ) );
		$b = $this->factory->category->create( array( 'slug' => 'podcast', 'name' => 'Podcast' ) );
		$pa = $this->factory->post->create( array( 'post_title' => 'A one', 'post_status' => 'publish', 'post_category' => array( $a ) ) );
		$pb = $this->factory->post->create( array( 'post_title' => 'B one', 'post_status' => 'publish', 'post_category' => array( $b ) ) );

		$html = $this->render( '{"count":10}' );

		$this->assertStringContainsString( 'starter-blog-index__filter', $html );
		$this->assertStringContainsString( 'data-filter="all"', $html );
		$this->assertStringContainsString( 'data-filter="article"', $html );
		$this->assertStringContainsString( 'data-filter="podcast"', $html );
		$this->assertMatchesRegularExpression( '/class="is-active"[^>]*data-filter="all"/', $html );

		wp_delete_post( $pa, true );
		wp_delete_post( $pb, true );
		wp_delete_category( $a );
		wp_delete_category( $b );
	}

	public function test_filter_bar_omitted_when_showfilter_false() {
		$a = $this->factory->category->create( array( 'slug' => 'cata', 'name' => 'Cat A' ) );
		$b = $this->factory->category->create( array( 'slug' => 'catb', 'name' => 'Cat B' ) );
		$pa = $this->factory->post->create( array( 'post_title' => 'PA', 'post_status' => 'publish', 'post_category' => array( $a ) ) );
		$pb = $this->factory->post->create( array( 'post_title' => 'PB', 'post_status' => 'publish', 'post_category' => array( $b ) ) );

		$html = $this->render( '{"count":10,"showFilter":false}' );

		$this->assertStringNotContainsString( 'starter-blog-index__filter', $html );
		$this->assertStringContainsString( 'PA', $html );

		wp_delete_post( $pa, true );
		wp_delete_post( $pb, true );
		wp_delete_category( $a );
		wp_delete_category( $b );
	}

	public function test_filter_bar_omitted_for_single_category() {
		$a = $this->factory->category->create( array( 'slug' => 'solo', 'name' => 'Solo' ) );
		$p1 = $this->factory->post->create( array( 'post_title' => 'One', 'post_status' => 'publish', 'post_category' => array( $a ) ) );
		$p2 = $this->factory->post->create( array( 'post_title' => 'Two', 'post_status' => 'publish', 'post_category' => array( $a ) ) );

		$html = $this->render( '{"count":10}' );

		$this->assertStringNotContainsString( 'starter-blog-index__filter', $html );
		$this->assertStringContainsString( 'data-cat="solo"', $html );

		wp_delete_post( $p1, true );
		wp_delete_post( $p2, true );
		wp_delete_category( $a );
	}

	public function test_card_has_permalink_link_meta_and_readmore() {
		$post_id = $this->factory->post->create(
			array(
				'post_title'   => 'Linkable',
				'post_status'  => 'publish',
				'post_excerpt' => 'Short summary here.',
			)
		);

		$html = $this->render( '{"count":3}' );

		$this->assertStringContainsString( 'starter-blog-index__item', $html );
		$this->assertStringContainsString( 'starter-blog-index__title', $html );
		$this->assertStringContainsString( 'Linkable', $html );
		$this->assertStringContainsString( 'Short summary here.', $html );
		$this->assertStringContainsString( 'starter-blog-index__readmore', $html );
		$this->assertStringContainsString( esc_url( get_permalink( $post_id ) ), $html );
		$this->assertStringContainsString( '#ph-arrow-right', $html );

		wp_delete_post( $post_id, true );
	}
}
