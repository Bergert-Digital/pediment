<?php

require_once dirname( __DIR__ ) . '/phpunit/Abilities/AbilityRegistryReset.php';

use Pediment\Language\LanguageRegistry;

/**
 * write-page with language and translation_of, against real Polylang.
 */
class AbilitiesWriteLanguageTest extends PolylangTestCase {

	use AbilityRegistryReset;

	private const GOOD = "<!-- wp:paragraph -->\n<p>Hello</p>\n<!-- /wp:paragraph -->";

	public function set_up(): void {
		parent::set_up();
		$this->reset_registries();
	}

	public function tear_down(): void {
		$this->reset_registries();
		parent::tear_down();
	}

	/**
	 * An author with edit_pages but without edit_others_pages and publish_pages.
	 */
	private function page_author(): int {
		$user = self::factory()->user->create( array( 'role' => 'author' ) );
		get_userdata( $user )->add_cap( 'edit_pages' );
		return $user;
	}

	private function write( int $user, array $input ) {
		wp_set_current_user( $user );
		return wp_get_ability( 'pediment/write-page' )->execute( $input );
	}

	private function page( string $language, int $author ): int {
		$id = self::factory()->post->create( array( 'post_type' => 'page', 'post_status' => 'draft', 'post_author' => $author ) );
		LanguageRegistry::provider()->setLanguage( $id, $language );
		return $id;
	}

	public function test_a_german_page_is_created_and_linked_into_the_english_group(): void {
		$user = $this->page_author();
		$en   = $this->page( 'en', $user );

		$result = $this->write( $user, array( 'title' => 'Ueber uns', 'markup' => self::GOOD, 'language' => 'de', 'translation_of' => $en ) );

		$this->assertIsArray( $result );
		$provider = LanguageRegistry::provider();
		$this->assertSame( 'de', $provider->languageOf( $result['id'] ) );
		$this->assertSame( $result['id'], $provider->translationOf( $en, 'de' ) );
		$this->assertSame( $en, $provider->translationOf( $result['id'], 'en' ) );
	}

	public function test_replacing_a_translation_the_user_cannot_edit_is_refused_and_the_group_stays(): void {
		$admin    = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$user     = $this->page_author();
		$provider = LanguageRegistry::provider();
		$en       = $this->page( 'en', $user );
		$de       = $this->page( 'de', $admin );
		$provider->linkTranslations( array( 'en' => $en, 'de' => $de ) );
		$this->assertFalse( user_can( $user, 'edit_post', $de ) );

		$result = $this->write( $user, array( 'title' => 'Eigene', 'markup' => self::GOOD, 'language' => 'de', 'translation_of' => $en ) );

		$this->assertWPError( $result );
		$this->assertStringContainsString( "translation_of {$en}", $result->get_error_message() );
		$this->assertStringContainsString( "page {$de}", $result->get_error_message() );
		$this->assertStringContainsString( 'cannot edit', $result->get_error_message() );
		$this->assertSame( $de, $provider->translationOf( $en, 'de' ), 'the admin page stays linked' );
		$this->assertSame( $en, $provider->translationOf( $de, 'en' ) );
		$this->assertSame( 0, (int) ( new WP_Query( $provider->unscopedQuery( array( 'post_type' => 'page', 'title' => 'Eigene', 'post_status' => 'any' ) ) ) )->found_posts );
	}

	public function test_replacing_a_translation_the_user_can_edit_is_allowed(): void {
		$user     = $this->page_author();
		$provider = LanguageRegistry::provider();
		$en       = $this->page( 'en', $user );
		$de       = $this->page( 'de', $user );
		$provider->linkTranslations( array( 'en' => $en, 'de' => $de ) );

		$result = $this->write( $user, array( 'title' => 'Neu', 'markup' => self::GOOD, 'language' => 'de', 'translation_of' => $en ) );

		$this->assertIsArray( $result );
		$this->assertSame( $result['id'], $provider->translationOf( $en, 'de' ) );
	}

	public function test_translation_of_a_page_the_user_cannot_edit_is_refused_and_the_group_stays(): void {
		$admin    = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$provider = LanguageRegistry::provider();
		$en       = $this->page( 'en', $admin );
		$de       = $this->page( 'de', $admin );
		$provider->linkTranslations( array( 'en' => $en, 'de' => $de ) );

		$result = $this->write( $this->page_author(), array( 'title' => 'Fremd', 'markup' => self::GOOD, 'language' => 'de', 'translation_of' => $en ) );

		$this->assertWPError( $result );
		$this->assertSame( 'ability_invalid_permissions', $result->get_error_code() );
		$this->assertSame( $de, $provider->translationOf( $en, 'de' ) );
	}

	public function test_translation_of_must_be_a_page_and_the_message_names_field_and_value(): void {
		$editor = self::factory()->user->create( array( 'role' => 'editor' ) );
		$post   = self::factory()->post->create( array( 'post_title' => 'A post' ) );
		foreach ( array( $post, 987654 ) as $value ) {
			$result = $this->write( $editor, array( 'title' => 'T', 'markup' => self::GOOD, 'language' => 'de', 'translation_of' => $value ) );
			$this->assertWPError( $result, (string) $value );
			$this->assertSame( 'pediment_page_not_found', $result->get_error_code() );
			$this->assertStringContainsString( "translation_of {$value}", $result->get_error_message() );
		}
	}

	public function test_language_on_a_type_polylang_does_not_translate_is_refused(): void {
		register_post_type( 'guide', array( 'public' => true, 'show_in_rest' => true, 'supports' => array( 'title', 'editor', 'revisions' ) ) );
		$this->assertFalse( LanguageRegistry::provider()->translatesPostType( 'guide' ) );
		$this->assertTrue( LanguageRegistry::provider()->translatesPostType( 'page' ) );

		$editor = self::factory()->user->create( array( 'role' => 'editor' ) );
		$result = $this->write( $editor, array( 'post_type' => 'guide', 'title' => 'Guide', 'markup' => self::GOOD, 'language' => 'de' ) );
		$this->assertWPError( $result );
		$this->assertSame( 'pediment_language_unsupported', $result->get_error_code() );
		$this->assertStringContainsString( 'post_type "guide" is not translated', $result->get_error_message() );
		$this->assertSame( 0, (int) ( new WP_Query( array( 'post_type' => 'guide', 'post_status' => 'any', 'lang' => '' ) ) )->found_posts );

		$plain = $this->write( $editor, array( 'post_type' => 'guide', 'title' => 'Guide', 'markup' => self::GOOD ) );
		$this->assertIsArray( $plain, 'without a language the guide is written' );
		unregister_post_type( 'guide' );
	}

	public function test_translations_share_the_imported_image_and_keep_their_own_alt_text(): void {
		register_post_type( 'activity', array( 'public' => true, 'show_in_rest' => true, 'supports' => array( 'title', 'editor', 'thumbnail', 'excerpt', 'page-attributes' ) ) );
		$translated = static fn( $types ) => array_merge( (array) $types, array( 'activity' => 'activity' ) );
		add_filter( 'pll_get_post_types', $translated );
		PLL()->model->clean_languages_cache();
		$this->assertTrue( LanguageRegistry::provider()->translatesPostType( 'activity' ) );
		$this->assertFalse( LanguageRegistry::provider()->translatesPostType( 'attachment' ), 'Pediment keeps media translation off' );

		$fetched = 0;
		$serve   = static function ( $pre, $args ) use ( &$fetched ) {
			++$fetched;
			copy( DIR_TESTDATA . '/images/canola.jpg', $args['filename'] );
			return array( 'headers' => array(), 'body' => '', 'response' => array( 'code' => 200, 'message' => 'OK' ), 'cookies' => array(), 'filename' => $args['filename'] );
		};
		add_filter( 'pre_http_request', $serve, 10, 2 );

		$editor = self::factory()->user->create( array( 'role' => 'editor' ) );
		$url    = 'https://images.example.test/lake.jpg';
		$en     = $this->write( $editor, array( 'post_type' => 'activity', 'title' => 'Swimming', 'markup' => self::GOOD, 'language' => 'en', 'excerpt' => 'Swim.', 'menu_order' => 110, 'featured_image' => array( 'url' => $url, 'alt' => 'A lake' ) ) );
		$de     = $this->write( $editor, array( 'post_type' => 'activity', 'title' => 'Schwimmen', 'markup' => self::GOOD, 'language' => 'de', 'translation_of' => $en['id'], 'excerpt' => 'Schwimmen.', 'menu_order' => 110, 'featured_image' => array( 'url' => $url, 'alt' => 'Ein See' ) ) );

		remove_filter( 'pre_http_request', $serve, 10 );
		remove_filter( 'pll_get_post_types', $translated );
		unregister_post_type( 'activity' );

		$this->assertIsArray( $en );
		$this->assertIsArray( $de );
		$this->assertSame( 1, $fetched, 'one download for both languages' );
		$this->assertSame( $en['featured_image'], $de['featured_image'] );
		$this->assertSame( $de['id'], LanguageRegistry::provider()->translationOf( $en['id'], 'de' ) );
		$this->assertStringContainsString( 'alt="A lake"', get_the_post_thumbnail( $en['id'] ) );
		$this->assertStringContainsString( 'alt="Ein See"', get_the_post_thumbnail( $de['id'] ) );
	}
}
