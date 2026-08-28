<?php

use Pediment\Language\WpmlProvider;
use Pediment\Seeder\Meta;

class NavBindingTest extends WpmlTestCase {

	private int $en;
	private int $de;

	public function set_up(): void {
		parent::set_up();

		// Precondition of the seam: WPML resolves a translation group
		// (wpml_object_id) only for a post type it treats as translatable, and
		// wp_navigation is not translatable by default. In production
		// inc/wpml-compat.php injects exactly this via the wpml_config_array
		// filter (custom-type wp_navigation, translate=1); WPML persists the
		// parsed result into the custom_posts_sync_option setting. The test
		// reproduces that persisted result directly so the group lookup behaves
		// as it does on a real seeded WPML site.
		global $sitepress;
		$sync                  = $sitepress->get_setting( 'custom_posts_sync_option', [] );
		$sync['wp_navigation'] = 1;
		$sitepress->set_setting( 'custom_posts_sync_option', $sync, true );

		$provider = new WpmlProvider();
		$this->en = self::factory()->post->create( [ 'post_type' => 'wp_navigation', 'post_title' => 'Primary EN', 'post_status' => 'publish' ] );
		$this->de = self::factory()->post->create( [ 'post_type' => 'wp_navigation', 'post_title' => 'Primary DE', 'post_status' => 'publish' ] );
		update_post_meta( $this->en, Meta::KEY, 'primary' );
		update_post_meta( $this->de, Meta::KEY, 'primary' );
		$provider->setLanguage( $this->en, 'en' );
		$provider->setLanguage( $this->de, 'de' );
		$provider->linkTranslations( [ 'en' => $this->en, 'de' => $this->de ] );
	}

	public function tear_down(): void {
		remove_all_filters( 'wpml_current_language' );
		parent::tear_down();
	}

	private function bind( string $current ): array {
		add_filter( 'wpml_current_language', static fn() => $current, 99 );
		$out = pediment_bind_navigation_ref( [ 'blockName' => 'core/navigation', 'attrs' => [] ] );
		remove_all_filters( 'wpml_current_language' );

		return $out;
	}

	public function test_english_current_binds_the_english_menu() {
		$this->assertSame( $this->en, $this->bind( 'en' )['attrs']['ref'] ?? 0 );
	}

	public function test_german_current_binds_the_german_menu() {
		$this->assertSame( $this->de, $this->bind( 'de' )['attrs']['ref'] ?? 0 );
	}
}
