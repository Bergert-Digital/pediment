<?php

require_once dirname( __DIR__ ) . '/phpunit/Abilities/AbilityRegistryReset.php';

/**
 * list-pages must see every language, not only the request's current one.
 */
class AbilitiesLanguageTest extends PolylangTestCase {

	use AbilityRegistryReset;

	private $originalCurlang;

	public function set_up(): void {
		parent::set_up();
		$this->originalCurlang = PLL()->curlang;
		$this->reset_registries();
	}

	public function tear_down(): void {
		$this->reset_registries();
		PLL()->curlang = $this->originalCurlang;
		parent::tear_down();
	}

	public function test_list_pages_returns_every_language_with_its_code() {
		$provider = \Pediment\Language\LanguageRegistry::provider();
		$en       = self::factory()->post->create( [ 'post_type' => 'page', 'post_status' => 'publish' ] );
		$de       = self::factory()->post->create( [ 'post_type' => 'page', 'post_status' => 'publish' ] );
		$provider->setLanguage( $en, 'en' );
		$provider->setLanguage( $de, 'de' );
		$provider->linkTranslations( [ 'en' => $en, 'de' => $de ] );

		// A request scoped to one language: a plain page query would now hide the German page.
		PLL()->curlang = PLL()->model->get_language( 'en' );

		wp_set_current_user( self::factory()->user->create( [ 'role' => 'editor' ] ) );
		$result = wp_get_ability( 'pediment/list-pages' )->execute( [] );

		$languages = array_column( $result['pages'], 'language', 'id' );
		$this->assertSame( 'en', $languages[ $en ] );
		$this->assertSame( 'de', $languages[ $de ] );

		$page = wp_get_ability( 'pediment/read-page' )->execute( [ 'id' => $en ] );
		$this->assertSame( [ 'de' => $de ], (array) $page['translations'] );
	}
}
