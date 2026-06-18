<?php

class MegaMenuTest extends WP_UnitTestCase {
	private function render( string $attrs ): string {
		return do_blocks( '<!-- wp:pediment/mega-menu ' . $attrs . ' /-->' );
	}

	public function test_no_panel_when_no_columns() {
		$html = $this->render( '{"label":"Products","columns":[]}' );
		$this->assertStringContainsString( 'starter-mega-menu__trigger', $html );
		$this->assertStringNotContainsString( 'starter-mega-menu__panel', $html );
		$this->assertStringContainsString( 'Products', $html );
	}

	public function test_renders_columns_and_links() {
		$attrs = '{"label":"Products","columns":[{"heading":"Product","links":[{"label":"Pricing","url":"/pricing","description":"Plans","icon":"tag"},{"label":"Docs","url":"/docs","description":"","icon":""}]}]}';
		$html  = $this->render( $attrs );
		$this->assertStringContainsString( 'starter-mega-menu__panel', $html );
		$this->assertMatchesRegularExpression(
			'/<p class="starter-mega-column__heading">\s*Product\s*<\/p>/',
			$html
		);
		$this->assertSame( 2, substr_count( $html, 'class="starter-mega-link"' ) );
		$this->assertStringContainsString( 'href="/pricing"', $html );
		$this->assertStringContainsString( '<span class="starter-mega-link__desc">Plans</span>', $html );
	}

	public function test_column_with_icon_emits_icon_svg_in_heading() {
		$attrs = '{"label":"X","columns":[{"heading":"Section","icon":"tag","links":[{"label":"Pricing","url":"/pricing","description":""}]}]}';
		$html  = $this->render( $attrs );
		$this->assertStringContainsString( 'starter-mega-column__icon', $html );
		$this->assertStringContainsString( '<svg', $html );
		// The icon must live inside the heading paragraph, not in the link.
		$this->assertMatchesRegularExpression(
			'/<p class="starter-mega-column__heading">[^<]*<svg[^>]*data-icon="tag"/',
			$html
		);
	}

	public function test_skips_links_without_label_or_url_and_empty_columns() {
		$attrs = '{"label":"X","columns":[{"heading":"Empty","links":[{"label":"","url":"","description":"","icon":""}]},{"heading":"Real","links":[{"label":"Docs","url":"/docs","description":"","icon":""}]}]}';
		$html  = $this->render( $attrs );
		$this->assertStringNotContainsString( 'Empty', $html );
		$this->assertStringContainsString( 'Real', $html );
		$this->assertSame( 1, substr_count( $html, 'class="starter-mega-link"' ) );
	}

	public function test_trigger_aria_label_when_label_empty() {
		$html = $this->render( '{"label":"","columns":[]}' );
		$this->assertStringContainsString( 'aria-label="Menu"', $html );
	}

	public function test_applies_preset_text_color_to_wrapper() {
		$html = $this->render( '{"label":"Products","columns":[],"textColor":"accent"}' );
		$this->assertMatchesRegularExpression(
			'/<div class="[^"]*\bstarter-mega-menu\b[^"]*\bhas-text-color\b[^"]*\bhas-accent-color\b[^"]*"/',
			$html
		);
	}

	public function test_applies_custom_text_color_inline_style_to_wrapper() {
		$html = $this->render( '{"label":"Products","columns":[],"style":{"color":{"text":"#ff0000"}}}' );
		$this->assertMatchesRegularExpression(
			'/<div class="[^"]*\bstarter-mega-menu\b[^"]*"[^>]*\bstyle="[^"]*color:\s*#ff0000/',
			$html
		);
	}

	public function test_applies_preset_font_size_class_to_wrapper() {
		$html = $this->render( '{"label":"Products","columns":[],"fontSize":"sm"}' );
		$this->assertStringContainsString( 'has-sm-font-size', $html );
	}

	public function test_applies_custom_font_size_inline_style_to_wrapper() {
		// The wrapper carries the custom size as an inline font-size. WordPress
		// fluid typography may wrap the value in a clamp(), so assert the size
		// appears inside a font-size declaration rather than matching exactly.
		$html = $this->render( '{"label":"Products","columns":[],"style":{"typography":{"fontSize":"2rem"}}}' );
		$this->assertMatchesRegularExpression( '/\bstyle="[^"]*font-size:[^";]*2rem/', $html );
	}
}
