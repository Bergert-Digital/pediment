<?php
/**
 * Tests for the inline icon helper.
 *
 * @package Pediment
 */

/**
 * @covers ::pediment_icon
 */
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

	public function test_pediment_icon_applies_manifest_svg_attrs() {
		// Phosphor manifest carries fill="currentColor" on the wrapper.
		$html = pediment_icon( 'arrow-right' );
		$this->assertStringContainsString( 'fill="currentColor"', $html );
	}

	public function test_pediment_icon_renders_a_swapped_stroke_set() {
		// A different icon set (e.g. Lucide) is expressed purely through the
		// manifest: a 24x24 viewBox and stroke-based svgAttrs. No code change.
		$filter = static function () {
			return array(
				'viewBox'  => '0 0 24 24',
				'svgAttrs' => array(
					'fill'           => 'none',
					'stroke'         => 'currentColor',
					'stroke-width'   => '2',
					'stroke-linecap' => 'round',
				),
			);
		};
		add_filter( 'pediment_icon_set', $filter );
		$html = pediment_icon( 'arrow-right' );
		remove_filter( 'pediment_icon_set', $filter );

		$this->assertStringContainsString( 'viewBox="0 0 24 24"', $html );
		$this->assertStringContainsString( 'fill="none"', $html );
		$this->assertStringContainsString( 'stroke="currentColor"', $html );
		$this->assertStringContainsString( 'stroke-width="2"', $html );
		$this->assertStringContainsString( 'stroke-linecap="round"', $html );
	}
}
