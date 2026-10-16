<?php

require_once __DIR__ . '/FakeMedia.php';

use Pediment\Content\Policy;
use Pediment\Convert\Converter;

/**
 * Every fixture pair: fixtures/<name>.input.html (legacy markup) converts to
 * fixtures/<name>.expected.html. CI also runs every expected file through the
 * block validator (tools/validate-convert-fixtures.mjs, WordPress 6.9 and 7.0).
 */
class MapperFixturesTest extends WP_UnitTestCase {

	public function fixtures(): array {
		$out = array();
		foreach ( glob( __DIR__ . '/fixtures/*.input.html' ) as $input ) {
			$name         = basename( $input, '.input.html' );
			$out[ $name ] = array( $input, __DIR__ . "/fixtures/{$name}.expected.html" );
		}
		return $out;
	}

	/**
	 * @dataProvider fixtures
	 */
	public function test_fixture_converts_to_the_expected_markup( string $input, string $expected ): void {
		$this->assertFileExists( $expected );
		$result = Converter::withDefaults()->convert( (string) file_get_contents( $input ), new FakeMedia() );
		$this->assertSame( (string) file_get_contents( $expected ), $result->markup );
		$this->assertSame( array(), $result->unmapped );
	}

	/**
	 * @dataProvider fixtures
	 */
	public function test_expected_markup_passes_the_policy_without_legacy_blocks( string $input, string $expected ): void {
		$markup = (string) file_get_contents( $expected );
		$policy = new Policy();
		$this->assertSame( array(), $policy->check( $markup )['errors'] );
		$this->assertSame( array(), $policy->legacyBlocksIn( $markup ) );
	}
}
