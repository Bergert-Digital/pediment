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

	/**
	 * The fix for Finding 2: `get_permalink()` alone resolves against WPML's
	 * CURRENT language, so a default-language (en) seed writes English URLs into
	 * German nav items. `permalinkInLanguage()` must switch WPML's context to
	 * the target language for the duration of the get_permalink() call, then
	 * restore it.
	 *
	 * The switch is observed through WPML's own `wpml_switch_language` action,
	 * which `permalinkInLanguage()` drives: record the language switched to right
	 * before the permalink is built, and confirm the sequence switches to `de`
	 * and then restores the ambient `en` (the default-language seed context).
	 * The literal `/de/` URL is proven end to end by the multilingual-wpml e2e
	 * (the front end is where WPML's URL converter runs); this test pins the
	 * adapter contract the e2e depends on.
	 */
	public function test_permalink_in_language_switches_wpml_to_the_target_language_and_restores() {
		$provider = new WpmlProvider();
		$de       = self::factory()->post->create( [ 'post_type' => 'page', 'post_name' => 'kontakt' ] );
		$provider->setLanguage( $de, 'de' );

		$switches   = [];
		$langAtLink = 'unset';

		add_action(
			'wpml_switch_language',
			static function ( $code ) use ( &$switches ) {
				$switches[] = $code;
			},
			5,
			1
		);
		add_filter(
			'page_link',
			static function ( $url ) use ( &$switches, &$langAtLink ) {
				// get_permalink() applies this filter; capture the language WPML
				// was switched to at that exact moment.
				$langAtLink = empty( $switches ) ? null : end( $switches );
				return $url;
			},
			10,
			1
		);

		$url = $provider->permalinkInLanguage( $de, 'de' );

		$this->assertSame( 'de', $langAtLink, 'get_permalink() must run with WPML switched to the target language.' );
		$this->assertNotEmpty( $switches, 'permalinkInLanguage() must switch WPML at all.' );
		$this->assertSame( 'de', $switches[0], 'The first switch is to the requested language.' );
		$this->assertSame( 'en', end( $switches ), 'The last switch restores WPML to the ambient (en) seed language.' );
		$this->assertNotEmpty( $url );
	}
}
