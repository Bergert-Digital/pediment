<?php

class BlogIndexTest extends WP_UnitTestCase {
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
}
