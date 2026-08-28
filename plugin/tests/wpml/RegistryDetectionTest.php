<?php

use Pediment\Language\LanguageRegistry;
use Pediment\Language\WpmlProvider;

class RegistryDetectionTest extends WpmlTestCase {

	public function tear_down(): void {
		LanguageRegistry::reset();
		parent::tear_down();
	}

	public function test_provider_is_wpml_when_wpml_active_and_polylang_absent() {
		LanguageRegistry::reset();
		$this->assertInstanceOf( WpmlProvider::class, LanguageRegistry::provider() );
	}
}
