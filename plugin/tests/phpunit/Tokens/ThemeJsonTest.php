<?php

/**
 * Design-token assertions, now read via wp_get_global_settings() instead of a
 * theme file: the tokens ship from plugin/tokens/theme.json and are merged
 * into the active theme's data by Pediment\Tokens\Injector, so the resolved
 * global settings are the real source of truth (Task 6 of the
 * plugin-absorbs-theme migration).
 */
class ThemeJsonTest extends WP_UnitTestCase {
	private function palette(): array {
		$out = array();
		foreach ( wp_get_global_settings( array( 'color', 'palette', 'theme' ) ) as $c ) {
			$out[ $c['slug'] ] = strtoupper( $c['color'] );
		}
		return $out;
	}

	public function test_accent_is_deep_cyan() {
		$p = $this->palette();
		$this->assertSame( '#0E7490', $p['accent'] );
		$this->assertSame( '#155E75', $p['accent-hover'] );
		$this->assertSame( '#E1F1F6', $p['accent-tint'] );
	}

	public function test_navy_ink_and_surfaces() {
		$p = $this->palette();
		$this->assertSame( '#0B1B33', $p['foreground'] );
		$this->assertSame( '#0A1B33', $p['primary'] );
		$this->assertSame( '#5C6B82', $p['foreground-muted'] );
		$this->assertSame( '#FFFFFF', $p['surface'] );
		$this->assertSame( '#F5F8FC', $p['surface-elevated'] );
		$this->assertSame( '#E4EAF2', $p['border'] );
		$this->assertSame( '#CDD9EC', $p['border-strong'] );
	}

	public function test_primary_font_is_plus_jakarta_sans() {
		$fam = array();
		foreach ( wp_get_global_settings( array( 'typography', 'fontFamilies', 'theme' ) ) as $f ) {
			$fam[ $f['slug'] ] = $f['fontFamily'];
		}
		$this->assertStringContainsString( 'Plus Jakarta Sans', $fam['body'] );
		$this->assertStringContainsString( 'Plus Jakarta Sans', $fam['heading'] );
	}

	public function test_global_assets_enqueue() {
		do_action( 'wp_enqueue_scripts' );
		$this->assertTrue( wp_style_is( 'pediment-theme', 'enqueued' ) );
		$this->assertTrue( wp_script_is( 'pediment-reveal', 'enqueued' ) );
	}

	public function test_focus_shadow_uses_accent() {
		$focus = '';
		foreach ( wp_get_global_settings( array( 'shadow', 'presets', 'theme' ) ) as $p ) {
			if ( 'focus' === $p['slug'] ) {
				$focus = $p['shadow'];
			}
		}
		$this->assertStringContainsString( '14,116,144', $focus );
		$this->assertStringNotContainsString( '79,70,229', $focus );
	}
}
