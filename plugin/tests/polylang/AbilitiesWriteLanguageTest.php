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
}
