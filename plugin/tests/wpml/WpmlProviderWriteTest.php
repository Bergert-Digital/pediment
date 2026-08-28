<?php

use Pediment\Language\WpmlProvider;

class WpmlProviderWriteTest extends WpmlTestCase {

	public function test_set_language_assigns_the_language() {
		$id       = self::factory()->post->create( [ 'post_type' => 'page' ] );
		$provider = new WpmlProvider();

		$provider->setLanguage( $id, 'de' );

		$this->assertSame(
			'de',
			apply_filters( 'wpml_element_language_code', null, [ 'element_id' => $id, 'element_type' => 'post_page' ] )
		);
	}

	public function test_link_translations_makes_posts_find_each_other() {
		$en = self::factory()->post->create( [ 'post_type' => 'page' ] );
		$de = self::factory()->post->create( [ 'post_type' => 'page' ] );
		$provider = new WpmlProvider();

		$provider->setLanguage( $en, 'en' );
		$provider->setLanguage( $de, 'de' );
		$provider->linkTranslations( [ 'en' => $en, 'de' => $de ] );

		$this->assertSame( $de, $provider->translationOf( $en, 'de' ) );
		$this->assertSame( $en, $provider->translationOf( $de, 'en' ) );
	}

	public function test_link_translations_ignores_a_group_smaller_than_two() {
		$en = self::factory()->post->create( [ 'post_type' => 'page' ] );
		$provider = new WpmlProvider();
		$provider->setLanguage( $en, 'en' );

		$provider->linkTranslations( [ 'en' => $en ] ); // no-op, no fatal.

		$this->assertSame( $en, $provider->translationOf( $en, 'en' ) );
	}
}
