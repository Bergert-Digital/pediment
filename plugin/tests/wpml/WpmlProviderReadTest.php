<?php

use Pediment\Language\WpmlProvider;

class WpmlProviderReadTest extends WpmlTestCase {

	public function test_is_active_when_wpml_configured() {
		$this->assertTrue( WpmlProvider::isActive() );
	}

	public function test_languages_lists_configured_codes_default_first() {
		$this->assertSame( [ 'en', 'de' ], ( new WpmlProvider() )->languages() );
	}

	public function test_default_language() {
		$this->assertSame( 'en', ( new WpmlProvider() )->defaultLanguage() );
	}

	public function test_translation_of_untranslated_post_is_itself_for_its_own_language() {
		$id = self::factory()->post->create( [ 'post_type' => 'page' ] );

		// A bare factory-created post carries no WPML language assignment at
		// all until something assigns one — WPML does not auto-tag it with
		// the default language. Make the post's own language explicit first,
		// the way a real create-path would, so the contract under test
		// (translationOf() returns the post itself for its own language) is
		// exercised against a post that actually has a language.
		$trid = apply_filters( 'wpml_element_trid', null, $id, 'post_page' );
		do_action(
			'wpml_set_element_language_details',
			[
				'element_id'    => $id,
				'element_type'  => 'post_page',
				'trid'          => $trid,
				'language_code' => 'en',
			]
		);

		$this->assertSame( $id, ( new WpmlProvider() )->translationOf( $id, 'en' ) );
	}

	public function test_translation_of_missing_language_is_zero() {
		$id = self::factory()->post->create( [ 'post_type' => 'page' ] );

		$this->assertSame( 0, ( new WpmlProvider() )->translationOf( $id, 'de' ) );
	}

	public function test_unscoped_query_sets_suppress_filters() {
		$args = ( new WpmlProvider() )->unscopedQuery( [ 'post_type' => 'page' ] );

		$this->assertTrue( $args['suppress_filters'] );
	}
}
