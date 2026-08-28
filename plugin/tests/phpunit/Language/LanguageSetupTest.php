<?php

use Pediment\Language\LanguageRegistry;
use Pediment\Language\LanguageSetup;
use Pediment\Language\PolylangSetup;

class LanguageSetupTest extends WP_UnitTestCase {

	public function tear_down(): void {
		remove_all_filters( 'pediment_language_setup' );
		LanguageRegistry::reset();
		parent::tear_down();
	}

	public function test_default_setup_is_polylang() {
		$this->assertInstanceOf( PolylangSetup::class, LanguageRegistry::setup() );
	}

	public function test_polylang_setup_satisfies_the_interface() {
		$this->assertInstanceOf( LanguageSetup::class, new PolylangSetup() );
	}

	public function test_setup_is_memoized() {
		$this->assertSame( LanguageRegistry::setup(), LanguageRegistry::setup() );
	}

	public function test_filter_swaps_the_setup() {
		$fake = new class() implements LanguageSetup {
			public function configure( array $languages, string $default, bool $dryRun = false ): array {
				return [ 'changes' => [ 'faked' ], 'errors' => [] ];
			}
		};
		add_filter( 'pediment_language_setup', static fn() => $fake );
		LanguageRegistry::reset();

		$this->assertSame( [ 'faked' ], LanguageRegistry::setup()->configure( [], '' )['changes'] );
	}

	public function test_non_setup_filter_return_is_ignored() {
		add_filter( 'pediment_language_setup', static fn() => 'nonsense' );
		LanguageRegistry::reset();

		$this->assertInstanceOf( LanguageSetup::class, LanguageRegistry::setup() );
	}
}
