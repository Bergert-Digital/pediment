<?php

class SliderTest extends WP_UnitTestCase {
	/**
	 * Render the slider from an attributes array (no inner blocks).
	 *
	 * @param array $attrs Block attributes (e.g. slides, panelColor).
	 * @return string
	 */
	private function slider( array $attrs ): string {
		return do_blocks( '<!-- wp:pediment/slider ' . wp_json_encode( $attrs ) . ' /-->' );
	}

	public function test_renders_one_panel_and_dot_per_slide() {
		$html = $this->slider(
			array(
				'slides' => array(
					array( 'heading' => 'One' ),
					array( 'heading' => 'Two' ),
					array( 'heading' => 'Three' ),
				),
			)
		);
		$this->assertSame( 3, substr_count( $html, 'starter-slide__panel' ) );
		$this->assertSame( 3, substr_count( $html, 'starter-slider__dot' ) );
		$this->assertStringContainsString( 'One', $html );
		$this->assertStringContainsString( 'Three', $html );
	}

	public function test_renders_eyebrow_heading_body_with_line_breaks() {
		$html = $this->slider(
			array(
				'slides' => array(
					array(
						'eyebrow' => 'Kicker',
						'heading' => 'Title',
						'body'    => "Line A\nLine B",
					),
				),
			)
		);
		$this->assertStringContainsString( '<p class="starter-slide__eyebrow">Kicker</p>', $html );
		$this->assertStringContainsString( '<h2 class="starter-slide__heading">Title</h2>', $html );
		$this->assertStringContainsString( 'Line A<br', $html );
	}

	public function test_eyebrow_omitted_when_empty() {
		$html = $this->slider( array( 'slides' => array( array( 'heading' => 'H' ) ) ) );
		$this->assertStringNotContainsString( 'starter-slide__eyebrow', $html );
	}

	public function test_button_requires_both_text_and_url() {
		$only_text = $this->slider(
			array( 'slides' => array( array( 'heading' => 'H', 'buttonText' => 'Go' ) ) )
		);
		$this->assertStringNotContainsString( 'starter-slide__button', $only_text );

		$both = $this->slider(
			array(
				'slides' => array(
					array( 'heading' => 'H', 'buttonText' => 'Go', 'buttonUrl' => '/x' ),
				),
			)
		);
		$this->assertStringContainsString( '<a class="starter-slide__button" href="/x">Go</a>', $both );
	}

	public function test_placeholder_when_no_media() {
		$html = $this->slider(
			array( 'slides' => array( array( 'heading' => 'H', 'mediaId' => 0 ) ) )
		);
		$this->assertStringContainsString( 'starter-slide__placeholder', $html );
		$this->assertStringNotContainsString( '<img', $html );
	}

	public function test_media_renders_img_with_alt_override() {
		$id   = $this->factory->attachment->create_upload_object( DIR_TESTDATA . '/images/canola.jpg' );
		$html = $this->slider(
			array( 'slides' => array( array( 'mediaId' => $id, 'altOverride' => 'Yellow' ) ) )
		);
		$this->assertStringContainsString( 'starter-slide__img', $html );
		$this->assertStringContainsString( 'alt="Yellow"', $html );
		wp_delete_attachment( $id, true );
	}

	public function test_media_position_left_and_right() {
		$l = $this->slider( array( 'slides' => array( array( 'heading' => 'H' ) ) ) );
		$this->assertStringContainsString( 'is-media-left', $l );

		$r = $this->slider(
			array( 'mediaPosition' => 'right', 'slides' => array( array( 'heading' => 'H' ) ) )
		);
		$this->assertStringContainsString( 'is-media-right', $r );
		$this->assertStringNotContainsString( 'is-media-left', $r );
	}

	public function test_panel_color_and_luminance_tokens() {
		$dark = $this->slider(
			array( 'panelColor' => '#0A1B33', 'slides' => array( array( 'heading' => 'H' ) ) )
		);
		$this->assertStringContainsString( '--slide-panel-bg:#0A1B33', $dark );
		$this->assertStringContainsString( '--slide-panel-fg:var(--wp--preset--color--surface)', $dark );

		$light = $this->slider(
			array( 'panelColor' => '#E1F1F6', 'slides' => array( array( 'heading' => 'H' ) ) )
		);
		$this->assertStringContainsString( '--slide-panel-fg:var(--wp--preset--color--foreground)', $light );
	}

	public function test_single_slide_hides_arrows_and_dots() {
		$html = $this->slider( array( 'slides' => array( array( 'heading' => 'Only' ) ) ) );
		$this->assertStringNotContainsString( 'starter-slider__arrow', $html );
		$this->assertStringNotContainsString( 'starter-slider__dot', $html );
	}

	public function test_empty_slides_renders_chrome_but_no_panels() {
		$html = $this->slider( array( 'slides' => array() ) );
		$this->assertStringContainsString( 'starter-slider', $html );
		$this->assertStringNotContainsString( 'starter-slide__panel', $html );
	}

	public function test_whitespace_only_fields_are_omitted() {
		$html = $this->slider(
			array(
				'slides' => array(
					array( 'eyebrow' => '   ', 'heading' => 'H', 'body' => "  \n ", 'buttonText' => ' ', 'buttonUrl' => '/x' ),
				),
			)
		);
		$this->assertStringNotContainsString( 'starter-slide__eyebrow', $html );
		$this->assertStringNotContainsString( 'starter-slide__body', $html );
		$this->assertStringNotContainsString( 'starter-slide__button', $html );
		$this->assertStringContainsString( '<h2 class="starter-slide__heading">H</h2>', $html );
	}
}
