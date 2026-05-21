<?php

class MegaMenuTest extends WP_UnitTestCase {
	private function render( string $attrs ): string {
		return do_blocks( '<!-- wp:starter/mega-menu ' . $attrs . ' /-->' );
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
		$this->assertStringContainsString( '<p class="starter-mega-column__heading">Product</p>', $html );
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
			'/<p class="starter-mega-column__heading">[^<]*<svg[^<]*<use[^<]*ph-tag/',
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
}
