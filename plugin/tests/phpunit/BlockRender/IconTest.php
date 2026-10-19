<?php

use Pediment\Content\Policy;
use Pediment\Design\DefaultStyles;

class IconTest extends WP_UnitTestCase {

	private function render( array $attrs = array() ): string {
		$json = array() === $attrs ? '' : ' ' . wp_json_encode( $attrs );
		return trim( do_blocks( '<!-- wp:pediment/icon' . $json . ' /-->' ) );
	}

	public function test_empty_attributes_render_nothing(): void {
		$this->assertSame( '', $this->render() );
		$this->assertSame( '', $this->render( array( 'icon' => '', 'label' => 'Ignored' ) ) );
	}

	public function test_unknown_or_hostile_icon_renders_nothing(): void {
		$this->assertSame( '', $this->render( array( 'icon' => 'no-such-icon' ) ) );
		$this->assertSame( '', $this->render( array( 'icon' => '"><script>' ) ) );
	}

	public function test_icon_without_label_is_decorative(): void {
		$html = $this->render( array( 'icon' => 'cube' ) );
		$this->assertMatchesRegularExpression( '#^<div class="wp-block-pediment-icon"><span class="wp-block-pediment-icon__glyph" aria-hidden="true"><svg [^>]*data-icon="cube"[^>]*>.*</svg></span></div>$#s', $html );
		$this->assertStringNotContainsString( 'role="img"', $html );
		$this->assertStringNotContainsString( 'aria-label', $html );
	}

	public function test_label_gives_the_glyph_an_accessible_name(): void {
		$html = $this->render( array( 'icon' => 'key', 'label' => 'Check-in' ) );
		$this->assertStringContainsString( '<span class="wp-block-pediment-icon__glyph" role="img" aria-label="Check-in">', $html );
		$this->assertStringNotContainsString( '<span class="wp-block-pediment-icon__glyph" aria-hidden="true"', $html );
	}

	public function test_whitespace_label_is_decorative(): void {
		$html = $this->render( array( 'icon' => 'key', 'label' => '   ' ) );
		$this->assertStringContainsString( 'aria-hidden="true"', $html );
		$this->assertStringNotContainsString( 'role="img"', $html );
	}

	public function test_label_is_escaped(): void {
		$html = $this->render( array( 'icon' => 'key', 'label' => '"><script>alert(1)</script>' ) );
		$this->assertStringNotContainsString( '<script>', $html );
		$this->assertStringContainsString( 'aria-label="&quot;&gt;&lt;script&gt;alert(1)&lt;/script&gt;"', $html );
	}

	public function test_non_string_attributes_render_nothing_without_notices(): void {
		$this->assertSame( '', trim( do_blocks( '<!-- wp:pediment/icon {"icon":["cube"],"label":{"a":1}} /-->' ) ) );
	}

	public function test_class_name_reaches_the_wrapper(): void {
		$html = $this->render( array( 'icon' => 'cube', 'className' => 'is-style-tile' ) );
		$this->assertMatchesRegularExpression( '#^<div class="[^"]*\bis-style-tile\b[^"]*\bwp-block-pediment-icon\b[^"]*">#', $html );
	}

	public function test_block_is_a_functional_block_in_the_inserter(): void {
		$type = WP_Block_Type_Registry::get_instance()->get_registered( 'pediment/icon' );
		$this->assertNotNull( $type );
		$this->assertSame( 'pediment', $type->category );
		$this->assertNotFalse( $type->supports['inserter'] ?? true );
		$this->assertFalse( $type->supports['html'] );
		$this->assertContains( 'pediment/icon', Policy::allowedBlocks() );
		$this->assertNotContains( 'pediment/icon', Policy::LEGACY );
	}

	public function test_tile_style_is_registered_and_passes_the_policy(): void {
		DefaultStyles::registerAll();
		$this->assertArrayHasKey( 'tile', WP_Block_Styles_Registry::get_instance()->get_registered_styles_for_block( 'pediment/icon' ) );
		$result = ( new Policy() )->check( '<!-- wp:pediment/icon {"icon":"key","className":"is-style-tile"} /-->' );
		$this->assertSame( array(), $result['errors'] );
		$this->assertSame( array(), $result['warnings'] );
	}
}
