<?php

class FeatureGridTest extends WP_UnitTestCase {
	public function test_grid_wraps_features_with_icon_title_link() {
		$html = do_blocks(
			'<!-- wp:pediment/feature-grid -->' .
			'<!-- wp:pediment/feature {"icon":"gear","title":"Ops","text":"Run it","linkText":"More","linkUrl":"/ops"} /-->' .
			'<!-- wp:pediment/feature {"icon":"stack","title":"Digital","text":"Ship it"} /-->' .
			'<!-- /wp:pediment/feature-grid -->'
		);
		$this->assertStringContainsString( 'starter-feature-grid', $html );
		$this->assertStringContainsString( 'starter-feature', $html );
		$this->assertStringContainsString( 'Ops', $html );
		$this->assertStringContainsString( 'Digital', $html );
		$this->assertStringContainsString( 'href="/ops"', $html );
		$this->assertStringContainsString( 'data-icon="gear"', $html );
		$this->assertStringContainsString( 'data-icon="stack"', $html );
	}

	public function test_feature_omits_link_when_url_missing() {
		$html = do_blocks(
			'<!-- wp:pediment/feature {"title":"T","text":"D","linkText":"More","linkUrl":""} /-->'
		);
		$this->assertStringNotContainsString( 'starter-feature__more', $html );
	}

	public function test_feature_renders_image_instead_of_icon_when_image_id_set() {
		$att  = self::factory()->attachment->create_upload_object( DIR_TESTDATA . '/images/canola.jpg' );
		$html = do_blocks(
			'<!-- wp:pediment/feature {"icon":"gear","imageId":' . $att . ',"title":"Pic","text":"Card"} /-->'
		);
		$this->assertStringContainsString( 'starter-feature__img', $html );
		$this->assertStringContainsString( '<img', $html );
		$this->assertStringNotContainsString( 'data-icon="gear"', $html );
		wp_delete_attachment( $att, true );
	}

	public function test_feature_falls_back_to_icon_when_image_id_invalid() {
		$html = do_blocks(
			'<!-- wp:pediment/feature {"icon":"gear","imageId":999999,"title":"Broken","text":"Card"} /-->'
		);
		$this->assertStringContainsString( 'data-icon="gear"', $html );
		$this->assertStringNotContainsString( 'starter-feature__img', $html );
	}

	public function test_feature_block_json_has_image_id_attribute() {
		$path = dirname( __DIR__, 3 ) . '/src/blocks/feature/block.json';
		$this->assertFileIsReadable( $path );
		$data = json_decode( file_get_contents( $path ), true );
		$this->assertArrayHasKey( 'imageId', $data['attributes'] );
		$this->assertSame( 'integer', $data['attributes']['imageId']['type'] );
	}
}
