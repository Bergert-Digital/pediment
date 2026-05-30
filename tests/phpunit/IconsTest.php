<?php

class IconsTest extends WP_UnitTestCase {
	public function test_pediment_icon_returns_inline_svg_with_data_icon() {
		$html = pediment_icon( 'arrow-right' );
		$this->assertStringContainsString( '<svg class="i"', $html );
		$this->assertStringContainsString( 'data-icon="arrow-right"', $html );
		$this->assertStringContainsString( 'viewBox="0 0 256 256"', $html );
		// Inner markup from the catalog is inlined (no sprite <use> reference).
		$this->assertStringContainsString( '<path', $html );
		$this->assertStringNotContainsString( '<use', $html );
	}

	public function test_pediment_icon_accepts_extra_class() {
		$html = pediment_icon( 'bank', 'brand-mark' );
		$this->assertStringContainsString( 'class="i brand-mark"', $html );
	}

	public function test_pediment_icon_sanitizes_name() {
		$html = pediment_icon( 'arrow-right"/><script>' );
		// Sanitized to "arrow-rightscript" (non [a-z0-9-] stripped), which is
		// not a real slug, so the helper returns nothing rather than injecting.
		$this->assertSame( '', $html );
		$this->assertStringNotContainsString( '<script>', $html );
	}

	public function test_pediment_icon_returns_empty_for_unknown_slug() {
		$this->assertSame( '', pediment_icon( 'definitely-not-a-real-icon' ) );
	}

	public function test_pediment_icon_returns_empty_for_empty_name() {
		$this->assertSame( '', pediment_icon( '' ) );
	}

	public function test_icon_map_contains_expected_slugs() {
		$map = pediment_icon_map();
		$this->assertIsArray( $map );
		$this->assertArrayHasKey( 'trend-up', $map );
		$this->assertArrayHasKey( 'gear', $map );
	}
}
