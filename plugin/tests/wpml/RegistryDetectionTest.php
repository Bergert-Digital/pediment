<?php

use Pediment\Language\LanguageRegistry;
use Pediment\Language\WpmlProvider;
use Pediment\Language\WpmlSetup;

class RegistryDetectionTest extends WpmlTestCase {

	public function tear_down(): void {
		LanguageRegistry::reset();
		parent::tear_down();
	}

	public function test_provider_is_wpml_when_wpml_active_and_polylang_absent() {
		LanguageRegistry::reset();
		$this->assertInstanceOf( WpmlProvider::class, LanguageRegistry::provider() );
	}

	/**
	 * setup() must gate its WPML branch on "WPML is loaded", not "WPML is
	 * already configured" — configure() is what a fresh, zero-active-language
	 * WPML install (WPML seeds every language row with active=0) needs to
	 * reach in the first place. This suite's harness pre-activates en+de, so
	 * this assertion alone can't distinguish isLoaded() from isActive(); it
	 * documents the intended gate rather than exercising the zero-language
	 * case, which isLoaded() is what makes reachable.
	 */
	public function test_setup_is_wpml_setup_when_wpml_active_and_polylang_absent() {
		LanguageRegistry::reset();
		$this->assertInstanceOf( WpmlSetup::class, LanguageRegistry::setup() );
	}
}
