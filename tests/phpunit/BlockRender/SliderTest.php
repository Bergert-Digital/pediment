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

	private function slider( string $attrs = '{}', int $slides = 2 ): string {
		$inner = '';
		for ( $i = 0; $i < $slides; $i++ ) {
			$inner .= '<!-- wp:pediment/slide --><!-- wp:paragraph --><p>Slide ' . $i . '</p><!-- /wp:paragraph --><!-- /wp:pediment/slide -->';
		}
		return do_blocks( '<!-- wp:pediment/slider ' . $attrs . ' -->' . $inner . '<!-- /wp:pediment/slider -->' );
	}

	public function test_slider_renders_track_with_slides() {
		$html = $this->slider( '{}', 3 );
		$this->assertStringContainsString( 'class="starter-slider', $html );
		$this->assertStringContainsString( 'starter-slider__track', $html );
		$this->assertSame( 3, substr_count( $html, 'starter-slide__panel' ) );
	}

	public function test_slider_renders_one_dot_per_slide() {
		$html = $this->slider( '{}', 4 );
		$this->assertSame( 4, substr_count( $html, 'starter-slider__dot' ) );
		$this->assertStringContainsString( 'data-index="0"', $html );
		$this->assertStringContainsString( 'data-index="3"', $html );
	}

	public function test_slider_has_prev_next_arrows_and_live_region() {
		$html = $this->slider();
		$this->assertStringContainsString( 'starter-slider__arrow--prev', $html );
		$this->assertStringContainsString( 'starter-slider__arrow--next', $html );
		$this->assertStringContainsString( 'starter-slider__live', $html );
	}

	public function test_slider_default_media_position_is_left() {
		$html = $this->slider( '{}' );
		$this->assertStringContainsString( 'is-media-left', $html );
	}

	public function test_slider_media_position_right() {
		$html = $this->slider( '{"mediaPosition":"right"}' );
		$this->assertStringContainsString( 'is-media-right', $html );
		$this->assertStringNotContainsString( 'is-media-left', $html );
	}
}
