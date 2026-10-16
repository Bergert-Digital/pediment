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

	/*
	 * Palette slugs. `panelColor` may name a palette colour instead of a hex:
	 * the panel then uses the preset var, and the text colour follows the
	 * slug's resolved hex. Each test pins its own palette, so the result does
	 * not depend on which theme or tokens the suite runs with.
	 */

	public function tear_down(): void {
		remove_all_filters( 'wp_theme_json_data_user' );
		remove_filter( 'wp_theme_json_data_theme', array( $this, 'pin_theme_palette' ), 100 );
		wp_clean_theme_json_cache();
		parent::tear_down();
	}

	/** @var array<int,array{slug:string,color:string,name:string}> */
	private array $theme_palette = array();

	/** @param array<string,string> $colors slug => colour */
	private function theme_palette( array $colors ): void {
		$this->theme_palette = self::palette( $colors );
		add_filter( 'wp_theme_json_data_theme', array( $this, 'pin_theme_palette' ), 100 );
		wp_clean_theme_json_cache();
	}

	public function pin_theme_palette( $theme_json ) {
		return $theme_json->update_with(
			array(
				'version'  => 3,
				'settings' => array( 'color' => array( 'palette' => $this->theme_palette ) ),
			)
		);
	}

	/** @param array<string,string> $colors slug => colour */
	private function user_palette( array $colors ): void {
		$palette = self::palette( $colors );
		add_filter(
			'wp_theme_json_data_user',
			static fn( $theme_json ) => $theme_json->update_with(
				array(
					'version'  => 3,
					'settings' => array( 'color' => array( 'palette' => $palette ) ),
				)
			)
		);
		wp_clean_theme_json_cache();
	}

	/** @param array<string,string> $colors slug => colour */
	private static function palette( array $colors ): array {
		$out = array();
		foreach ( $colors as $slug => $color ) {
			$out[] = array(
				'slug'  => $slug,
				'color' => $color,
				'name'  => ucfirst( $slug ),
			);
		}
		return $out;
	}

	public function test_a_dark_palette_slug_uses_the_preset_and_light_text() {
		$this->theme_palette( array( 'navy' => '#0A1B33' ) );

		$html = $this->slider( array( 'panelColor' => 'navy', 'slides' => array( array( 'heading' => 'H' ) ) ) );

		$this->assertStringContainsString( '--slide-panel-bg:var(--wp--preset--color--navy)', $html );
		$this->assertStringContainsString( '--slide-panel-fg:var(--wp--preset--color--surface)', $html );
		$this->assertStringNotContainsString( '#0A1B33', $html, 'the panel carries the token, not the hex' );
	}

	/** The straight-solutions career pages: accent is #F5E716, a light yellow. */
	public function test_a_light_palette_slug_uses_the_preset_and_dark_text() {
		$this->theme_palette( array( 'accent' => '#F5E716', 'surface-elevated' => '#EBEBEB' ) );

		$accent = $this->slider( array( 'panelColor' => 'accent', 'slides' => array( array( 'heading' => 'H' ) ) ) );
		$this->assertStringContainsString( '--slide-panel-bg:var(--wp--preset--color--accent)', $accent );
		$this->assertStringContainsString( '--slide-panel-fg:var(--wp--preset--color--foreground)', $accent );

		$elevated = $this->slider( array( 'panelColor' => 'surface-elevated', 'slides' => array( array( 'heading' => 'H' ) ) ) );
		$this->assertStringContainsString( '--slide-panel-bg:var(--wp--preset--color--surface-elevated)', $elevated );
		$this->assertStringContainsString( '--slide-panel-fg:var(--wp--preset--color--foreground)', $elevated );
	}

	/**
	 * WordPress names a preset var after the kebab-cased slug
	 * (WP_Theme_JSON, `_wp_to_kebab_case()`): slug `accent2` is
	 * `--wp--preset--color--accent-2`. The panel must use that same name.
	 */
	public function test_a_slug_with_a_digit_uses_the_var_name_wordpress_generates() {
		$this->theme_palette( array( 'accent2' => '#F5E716' ) );

		$html = $this->slider( array( 'panelColor' => 'accent2', 'slides' => array( array( 'heading' => 'H' ) ) ) );

		$this->assertStringContainsString( '--slide-panel-bg:var(--wp--preset--color--accent-2)', $html );
		$this->assertStringContainsString( '--slide-panel-fg:var(--wp--preset--color--foreground)', $html );
	}

	public function test_a_slug_from_the_default_palette_resolves() {
		$html = $this->slider( array( 'panelColor' => 'white', 'slides' => array( array( 'heading' => 'H' ) ) ) );

		$this->assertStringContainsString( '--slide-panel-bg:var(--wp--preset--color--white)', $html );
		$this->assertStringContainsString( '--slide-panel-fg:var(--wp--preset--color--foreground)', $html );
	}

	/**
	 * A colour a person set in Global Styles (the custom origin) is what the
	 * preset var renders, so it is also what the text colour must follow.
	 */
	public function test_a_custom_palette_colour_wins_over_the_theme_colour_of_the_same_slug() {
		$this->theme_palette( array( 'accent' => '#F5E716' ) );
		$this->user_palette( array( 'accent' => '#0A1B33' ) );

		$html = $this->slider( array( 'panelColor' => 'accent', 'slides' => array( array( 'heading' => 'H' ) ) ) );

		$this->assertStringContainsString( '--slide-panel-bg:var(--wp--preset--color--accent)', $html );
		$this->assertStringContainsString( '--slide-panel-fg:var(--wp--preset--color--surface)', $html );
	}

	public function test_a_hex_panel_colour_is_unchanged_when_a_palette_colour_has_that_value() {
		$this->theme_palette( array( 'accent' => '#F5E716' ) );

		$html = $this->slider( array( 'panelColor' => '#F5E716', 'slides' => array( array( 'heading' => 'H' ) ) ) );

		$this->assertStringContainsString( '--slide-panel-bg:#F5E716', $html );
		$this->assertStringContainsString( '--slide-panel-fg:var(--wp--preset--color--foreground)', $html );
	}

	public function test_an_unknown_slug_falls_back_like_any_invalid_value() {
		$this->theme_palette( array( 'accent' => '#F5E716' ) );

		$html = $this->slider( array( 'panelColor' => 'no-such-colour', 'slides' => array( array( 'heading' => 'H' ) ) ) );

		$this->assertStringNotContainsString( 'var(--wp--preset--color--no-such-colour)', $html );
		$this->assertStringContainsString( '--slide-panel-bg:no-such-colour', $html );
		$this->assertStringContainsString( '--slide-panel-fg:var(--wp--preset--color--surface)', $html );
	}

	public function test_empty_panel_colour_still_uses_the_primary_preset() {
		$html = $this->slider( array( 'panelColor' => '', 'slides' => array( array( 'heading' => 'H' ) ) ) );

		$this->assertStringContainsString( '--slide-panel-bg:var(--wp--preset--color--primary)', $html );
	}
}
