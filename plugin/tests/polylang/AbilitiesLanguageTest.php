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

	public function test_language_filter_applies_before_the_limit() {
		$provider = \Pediment\Language\LanguageRegistry::provider();
		// Untagged filler pages, inserted in one query: the factory runs every
		// Polylang save hook per page and takes about 20 seconds for 500.
		global $wpdb;
		$now  = current_time( 'mysql', true );
		$rows = array_fill( 0, \Pediment\Abilities\ListPages::LIMIT, $wpdb->prepare( "('Aa other', 'page', 'publish', %s, %s, '', '', '', '', '')", $now, $now ) );
		$wpdb->query( "INSERT INTO {$wpdb->posts} (post_title, post_type, post_status, post_date_gmt, post_modified_gmt, post_content, post_excerpt, to_ping, pinged, post_content_filtered) VALUES " . implode( ',', $rows ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- rows are prepared above.
		// Sorted after every English page, so a cap applied first would cut it.
		$de = self::factory()->post->create( [ 'post_type' => 'page', 'post_title' => 'Zz de' ] );
		$provider->setLanguage( $de, 'de' );

		wp_set_current_user( self::factory()->user->create( [ 'role' => 'editor' ] ) );
		$german = wp_get_ability( 'pediment/list-pages' )->execute( [ 'language' => 'de' ] );
		$this->assertSame( [ $de ], array_column( $german['pages'], 'id' ) );
		$this->assertFalse( $german['truncated'] );

		$all = wp_get_ability( 'pediment/list-pages' )->execute( [] );
		$this->assertTrue( $all['truncated'] );
		$this->assertNotContains( $de, array_column( $all['pages'], 'id' ) );
	}
}
