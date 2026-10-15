<?php

use Pediment\Content\Policy;
use Pediment\Convert\Converter;
use Pediment\Design\DefaultStyles;

class MapperRegistryTest extends WP_UnitTestCase {

	public function test_every_legacy_block_has_a_mapper(): void {
		$legacy = Policy::LEGACY;
		sort( $legacy );
		$this->assertSame( $legacy, Converter::withDefaults()->mappedNames() );
	}

	public function test_every_converter_style_appears_in_a_validated_fixture(): void {
		$expected = '';
		foreach ( glob( __DIR__ . '/fixtures/*.expected.html' ) as $file ) {
			$expected .= (string) file_get_contents( $file );
		}
		$existing = array( 'band', 'band-alt', 'card', 'eyebrow', 'lead', 'pill', 'pill-ghost' );
		foreach ( DefaultStyles::specs() as $spec ) {
			if ( in_array( $spec['slug'], $existing, true ) ) {
				continue;
			}
			$this->assertStringContainsString( 'is-style-' . $spec['slug'] . '"', $expected, $spec['slug'] );
		}
	}
}
