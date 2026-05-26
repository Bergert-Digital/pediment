<?php

class ThemeJsonTest extends WP_UnitTestCase {
	private function theme_json(): array {
		$path = get_theme_file_path( 'theme.json' );
		return json_decode( file_get_contents( $path ), true );
	}

	private function palette(): array {
		$out = array();
		foreach ( $this->theme_json()['settings']['color']['palette'] as $c ) {
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
		$tj = $this->theme_json();
		$fam = array();
		foreach ( $tj['settings']['typography']['fontFamilies'] as $f ) {
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
		$tj = $this->theme_json();
		$focus = '';
		foreach ( $tj['settings']['shadow']['presets'] as $p ) {
			if ( 'focus' === $p['slug'] ) {
				$focus = $p['shadow'];
			}
		}
		$this->assertStringContainsString( '14,116,144', $focus );
		$this->assertStringNotContainsString( '79,70,229', $focus );
	}
}
