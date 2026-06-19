<?php

class SliderTest extends WP_UnitTestCase {
	private function slide( string $attrs = '{}', string $inner = '' ): string {
		return do_blocks( '<!-- wp:pediment/slide ' . $attrs . ' -->' . $inner . '<!-- /wp:pediment/slide -->' );
	}

	public function test_slide_renders_panel_with_inner_content() {
		$html = $this->slide( '{}', '<!-- wp:paragraph --><p>Hello slide</p><!-- /wp:paragraph -->' );
		$this->assertStringContainsString( 'starter-slide', $html );
		$this->assertStringContainsString( 'starter-slide__panel', $html );
		$this->assertStringContainsString( 'Hello slide', $html );
	}

	public function test_slide_without_media_renders_placeholder_not_img() {
		$html = $this->slide( '{"mediaId":0}', '<!-- wp:paragraph --><p>x</p><!-- /wp:paragraph -->' );
		$this->assertStringContainsString( 'starter-slide__placeholder', $html );
		$this->assertStringNotContainsString( '<img', $html );
	}

	public function test_slide_with_media_renders_img_with_alt_override() {
		$attachment_id = $this->factory->attachment->create_upload_object(
			DIR_TESTDATA . '/images/canola.jpg'
		);
		$html = $this->slide(
			sprintf( '{"mediaId":%d,"altOverride":"Yellow flowers"}', $attachment_id ),
			'<!-- wp:paragraph --><p>x</p><!-- /wp:paragraph -->'
		);
		$this->assertStringContainsString( 'starter-slide__img', $html );
		$this->assertStringContainsString( 'alt="Yellow flowers"', $html );
		$this->assertStringNotContainsString( 'starter-slide__placeholder', $html );
		wp_delete_attachment( $attachment_id, true );
	}
}
