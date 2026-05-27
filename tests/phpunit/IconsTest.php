<?php

class IconsTest extends WP_UnitTestCase {
	public function set_up(): void {
		parent::set_up();
		remove_action( 'wp_body_open', 'pediment_print_icon_sprite', 1 );
		add_action( 'wp_body_open', 'pediment_print_icon_sprite', 1 );
	}

	public function test_pediment_icon_returns_use_reference() {
		$html = pediment_icon( 'arrow-right' );
		$this->assertSame(
			'<svg class="i" aria-hidden="true" focusable="false"><use href="#ph-arrow-right"></use></svg>',
			$html
		);
	}

	public function test_pediment_icon_accepts_extra_class() {
		$html = pediment_icon( 'bank', 'brand-mark' );
		$this->assertStringContainsString( 'class="i brand-mark"', $html );
	}

	public function test_pediment_icon_sanitizes_name() {
		$html = pediment_icon( 'arrow right"/><script>' );
		$this->assertStringContainsString( '#ph-arrowright', $html );
		$this->assertStringNotContainsString( '<script>', $html );
	}

	public function test_sprite_is_printed_on_wp_body_open() {
		ob_start();
		do_action( 'wp_body_open' );
		$out = ob_get_clean();
		$this->assertStringContainsString( '<symbol id="ph-bank"', $out );
		$this->assertStringContainsString( 'id="ph-arrow-right"', $out );
	}

	public function test_sprite_printed_only_once() {
		ob_start();
		do_action( 'wp_body_open' );
		do_action( 'wp_body_open' );
		$out = ob_get_clean();
		$this->assertSame( 1, substr_count( $out, 'id="ph-bank"' ) );
	}
}
