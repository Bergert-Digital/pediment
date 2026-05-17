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
		$this->assertSame( '#0B1B33', $p['text'] );
		$this->assertSame( '#0A1B33', $p['primary'] );
		$this->assertSame( '#5C6B82', $p['text-muted'] );
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
}
