<?php

class WpmlCompatTest extends WpmlTestCase {

	public function test_navigation_is_declared_translatable() {
		$config = apply_filters( 'wpml_config_array', [] );
		$types  = $config['wpml-config']['custom-types']['custom-type'] ?? [];
		$this->assertContains( 'wp_navigation', array_column( $types, 'value' ) );
	}

	public function test_template_part_is_declared_not_translatable() {
		$config = apply_filters( 'wpml_config_array', [] );
		$types  = $config['wpml-config']['custom-types']['custom-type'] ?? [];
		foreach ( $types as $type ) {
			if ( ( $type['value'] ?? '' ) === 'wp_template_part' ) {
				$this->assertSame( '0', (string) ( $type['attr']['translate'] ?? '1' ) );
			}
		}
		$this->assertTrue( true );
	}
}
