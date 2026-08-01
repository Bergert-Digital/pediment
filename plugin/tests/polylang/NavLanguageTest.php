<?php

use Pediment\Language\PolylangProvider;
use Pediment\Seeder\Manifest;
use Pediment\Seeder\NavSeeder;

class NavLanguageTest extends PolylangTestCase {

	public function test_wp_navigation_is_translatable_outside_the_settings_screen() {
		$this->assertContains( 'wp_navigation', (array) apply_filters( 'pll_get_post_types', [], false ) );
	}

	public function test_the_settings_screen_list_is_left_alone() {
		// Polylang's settings screen offers only public, non-builtin post types,
		// so wp_navigation can never appear there — adding it would render a
		// checkbox a site owner could untick and lose every translated menu to.
		$this->assertNotContains( 'wp_navigation', (array) apply_filters( 'pll_get_post_types', [], true ) );
	}

	public function test_one_navigation_entity_per_language() {
		$manifest = $this->manifest();
		$lang     = new PolylangProvider();
		$seeder   = new NavSeeder( $lang );

		$entryIds = [ 'about|en' => $this->page( 'en' ), 'about|de' => $this->page( 'de' ) ];
		$plan     = $seeder->plan( $manifest, $entryIds );
		$ids      = $seeder->apply( $plan, $manifest, $entryIds );

		$this->assertSame( [], $seeder->errors() );
		$this->assertArrayHasKey( 'primary|en', $ids );
		$this->assertArrayHasKey( 'primary|de', $ids );
		$this->assertNotSame( $ids['primary|en'], $ids['primary|de'] );
	}

	public function test_the_navigation_entities_are_one_translation_group() {
		$manifest = $this->manifest();
		$lang     = new PolylangProvider();
		$seeder   = new NavSeeder( $lang );

		$entryIds = [ 'about|en' => $this->page( 'en' ), 'about|de' => $this->page( 'de' ) ];
		$ids      = $seeder->apply( $seeder->plan( $manifest, $entryIds ), $manifest, $entryIds );

		$this->assertSame( $ids['primary|de'], $lang->translationOf( $ids['primary|en'], 'de' ) );
	}

	public function test_each_language_links_to_its_own_page() {
		$manifest = $this->manifest();
		$lang     = new PolylangProvider();
		$seeder   = new NavSeeder( $lang );

		$en       = $this->page( 'en' );
		$de       = $this->page( 'de' );
		$entryIds = [ 'about|en' => $en, 'about|de' => $de ];
		$ids      = $seeder->apply( $seeder->plan( $manifest, $entryIds ), $manifest, $entryIds );

		$this->assertStringContainsString( '"id":' . $de, get_post( $ids['primary|de'] )->post_content );
		$this->assertStringNotContainsString( '"id":' . $en, get_post( $ids['primary|de'] )->post_content );
	}

	private function page( string $language ): int {
		$id = self::factory()->post->create( [ 'post_type' => 'page', 'post_title' => 'About ' . $language ] );
		pll_set_post_language( $id, $language );
		update_post_meta( $id, \Pediment\Seeder\Meta::KEY, 'about' );
		return $id;
	}

	private function manifest(): Manifest {
		return Manifest::fromArray(
			[
				'languages' => [ 'en' => [ 'name' => 'English', 'locale' => 'en_US', 'default' => true ], 'de' => [ 'name' => 'Deutsch', 'locale' => 'de_DE' ] ],
				'pages'     => [ 'about' => [ 'title' => 'About', 'content' => '' ] ],
				'navs'      => [ 'primary' => [ 'title' => 'Primary', 'items' => [ [ 'entry' => 'about' ] ] ] ],
			],
			get_stylesheet_directory()
		);
	}
}
