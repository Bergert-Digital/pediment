<?php
/**
 * Shared base for every WP_UnitTestCase in tests/wpml/. Reseeds the WPML
 * language activation per class, because WP core's tear_down_after_class()
 * commits a _delete_all_data() that can wipe rows a later class relies on.
 */

require_once __DIR__ . '/language-definitions.php';

abstract class WpmlTestCase extends WP_UnitTestCase {
	public static function wpSetUpBeforeClass( $factory ): void {
		pediment_wpml_activate_languages();
	}

	public function set_up(): void {
		if ( defined( 'PEDIMENT_WPML_MISSING' ) ) {
			$this->markTestSkipped( 'WPML zip not provided; skipping the WPML suite.' );
		}
		parent::set_up();
	}
}
