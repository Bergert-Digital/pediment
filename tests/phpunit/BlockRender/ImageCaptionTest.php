<?php

class ImageCaptionTest extends WP_UnitTestCase {
	public function test_renders_figure_with_caption() {
		$attachment_id = $this->factory->attachment->create_upload_object(
			DIR_TESTDATA . '/images/canola.jpg'
		);

		$html = do_blocks(
			sprintf(
				'<!-- wp:starter/image-caption {"mediaId":%d,"caption":"Beautiful canola","altOverride":"Yellow flowers"} /-->',
				$attachment_id
			)
		);

		$this->assertStringContainsString( '<figure', $html );
		$this->assertStringContainsString( 'Beautiful canola', $html );
		$this->assertStringContainsString( 'alt="Yellow flowers"', $html );
		wp_delete_attachment( $attachment_id, true );
	}

	public function test_returns_empty_when_no_media() {
		$html = do_blocks( '<!-- wp:starter/image-caption {"mediaId":0,"caption":"x"} /-->' );
		$this->assertStringNotContainsString( '<figure', $html );
	}
}
