<?php

use Pediment\Language\LanguageProvider;
use Pediment\Language\LanguageRegistry;
use Pediment\Language\NullProvider;

class LanguageProviderTest extends WP_UnitTestCase {

	public function tear_down(): void {
		remove_all_filters( 'pediment_language_provider' );
		LanguageRegistry::reset();
		parent::tear_down();
	}

	public function test_default_provider_is_monolingual() {
		$provider = LanguageRegistry::provider();
		$this->assertInstanceOf( NullProvider::class, $provider );
		$this->assertSame( [ '' ], $provider->languages() );
		$this->assertSame( '', $provider->defaultLanguage() );
	}

	public function test_null_provider_language_writes_are_no_ops() {
		$id       = self::factory()->post->create( [ 'post_type' => 'page' ] );
		$provider = new NullProvider();

		$provider->setLanguage( $id, '' );
		$provider->linkTranslations( [ '' => $id ] );

		$this->assertSame( 0, $provider->translationOf( $id, 'de' ) );
		$this->assertSame( $id, $provider->translationOf( $id, '' ) );
	}

	/**
	 * Always true, on any post ID, including one that never existed: a
	 * monolingual site has no language taxonomy to check, so "does this post
	 * have a language" is vacuously satisfied. This is what keeps Applier's
	 * untagged-post repair a no-op on every monolingual site — hasLanguage()
	 * reporting true means setLanguage() is never called for the repair.
	 */
	public function test_null_provider_has_language_is_always_true() {
		$provider = new NullProvider();

		$this->assertTrue( $provider->hasLanguage( 0 ) );
		$this->assertTrue( $provider->hasLanguage( 12345 ) );
	}

	public function test_unscoped_query_is_identity_for_the_null_provider() {
		$args = [ 'post_type' => 'page', 'posts_per_page' => -1 ];
		$this->assertSame( $args, ( new NullProvider() )->unscopedQuery( $args ) );
	}

	public function test_filter_swaps_the_provider() {
		$fake = new class() extends NullProvider {
			public function languages(): array {
				return [ 'en', 'de' ];
			}
		};
		add_filter( 'pediment_language_provider', static fn() => $fake );
		LanguageRegistry::reset();

		$this->assertSame( [ 'en', 'de' ], LanguageRegistry::provider()->languages() );
	}

	public function test_registry_memoizes() {
		$this->assertSame( LanguageRegistry::provider(), LanguageRegistry::provider() );
	}

	public function test_non_provider_filter_return_is_ignored() {
		add_filter( 'pediment_language_provider', static fn() => 'nonsense' );
		LanguageRegistry::reset();

		$this->assertInstanceOf( LanguageProvider::class, LanguageRegistry::provider() );
	}

	public function test_null_provider_when_polylang_is_absent() {
		LanguageRegistry::reset();
		$this->assertInstanceOf( NullProvider::class, LanguageRegistry::provider() );
	}

	public function test_null_provider_current_language_is_empty() {
		$this->assertSame( '', ( new NullProvider() )->currentLanguage() );
	}

	public function test_null_provider_emits_no_switcher() {
		$this->assertSame( '', ( new NullProvider() )->languageSwitcherBlock( true ) );
	}
}
