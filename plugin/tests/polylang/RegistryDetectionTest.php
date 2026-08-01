<?php

use Pediment\Language\LanguageRegistry;
use Pediment\Language\NullProvider;
use Pediment\Language\PolylangProvider;

class RegistryDetectionTest extends PolylangTestCase {

	public function tear_down(): void {
		remove_all_filters( 'pediment_language_provider' );
		LanguageRegistry::reset();
		parent::tear_down();
	}

	public function test_polylang_is_detected() {
		LanguageRegistry::reset();
		$this->assertInstanceOf( PolylangProvider::class, LanguageRegistry::provider() );
	}

	public function test_the_filter_still_wins() {
		add_filter( 'pediment_language_provider', static fn() => new NullProvider() );
		LanguageRegistry::reset();

		$this->assertInstanceOf( NullProvider::class, LanguageRegistry::provider() );
	}
}
