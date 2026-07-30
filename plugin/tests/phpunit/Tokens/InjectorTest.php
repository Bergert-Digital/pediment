<?php

use Pediment\Tokens\Injector;

/**
 * Unit coverage for Pediment\Tokens\Injector — the per-slug preset merge
 * (Task 6 of the plugin-absorbs-theme migration), productionized from
 * .context/spike-plugin-theme/spike-plugin/spike-plugin.php.
 */
class InjectorTest extends WP_UnitTestCase {

	/**
	 * A "client" WP_Theme_JSON_Data with no palette of its own — simulates the
	 * active theme having no theme.json (the pediment theme's own theme.json
	 * moved into the plugin in this task).
	 */
	private function empty_client(): WP_Theme_JSON_Data {
		return new WP_Theme_JSON_Data( array( 'version' => 2 ), 'theme' );
	}

	/**
	 * WP_Theme_JSON_Data::get_data() returns the raw internal structure, where
	 * WP_Theme_JSON keys every preset array by origin (here always "theme",
	 * since both the plugin base and the injected result are origin=theme) —
	 * see WP_Theme_JSON::__construct(), "Internally, presets are keyed by
	 * origin."
	 */
	private function preset( WP_Theme_JSON_Data $data, string $group, string $key ): array {
		return $data->get_data()['settings'][ $group ][ $key ]['theme'] ?? array();
	}

	public function test_client_palette_slug_overrides_plugins(): void {
		$client = new WP_Theme_JSON_Data(
			array(
				'version'  => 2,
				'settings' => array(
					'color' => array(
						'palette' => array(
							array( 'slug' => 'accent', 'color' => '#ABCDEF', 'name' => 'Client accent' ),
						),
					),
				),
			),
			'theme'
		);

		$result  = Injector::inject( $client );
		$palette = $this->preset( $result, 'color', 'palette' );

		$by_slug = array();
		foreach ( $palette as $entry ) {
			$by_slug[ $entry['slug'] ] = $entry;
		}

		$this->assertArrayHasKey( 'accent', $by_slug, 'client slug must be present' );
		$this->assertSame( '#ABCDEF', $by_slug['accent']['color'], 'client entry must win over the plugin default' );
	}

	public function test_plugin_only_slug_survives_when_client_has_no_palette(): void {
		$result  = Injector::inject( $this->empty_client() );
		$palette = $this->preset( $result, 'color', 'palette' );
		$slugs   = wp_list_pluck( $palette, 'slug' );

		// 'primary' ships only from plugin/tokens/theme.json; the client
		// declares no palette at all, so it must survive untouched.
		$this->assertContains( 'primary', $slugs );
	}

	public function test_plugin_only_slug_survives_alongside_a_client_override(): void {
		$client = new WP_Theme_JSON_Data(
			array(
				'version'  => 2,
				'settings' => array(
					'color' => array(
						'palette' => array(
							array( 'slug' => 'accent', 'color' => '#ABCDEF', 'name' => 'Client accent' ),
						),
					),
				),
			),
			'theme'
		);

		$result  = Injector::inject( $client );
		$palette = $this->preset( $result, 'color', 'palette' );
		$slugs   = wp_list_pluck( $palette, 'slug' );

		$this->assertContains( 'accent', $slugs );
		$this->assertContains( 'primary', $slugs, 'untouched plugin slugs must survive a partial client override' );
	}

	public function test_font_face_src_rewritten_to_plugin_url(): void {
		$result   = Injector::inject( $this->empty_client() );
		$families = $this->preset( $result, 'typography', 'fontFamilies' );

		$body = null;
		foreach ( $families as $family ) {
			if ( 'body' === $family['slug'] ) {
				$body = $family;
				break;
			}
		}

		$this->assertNotNull( $body, 'body font family must survive the merge' );
		$this->assertNotEmpty( $body['fontFace'] );
		foreach ( $body['fontFace'] as $face ) {
			foreach ( $face['src'] as $src ) {
				$this->assertStringStartsWith(
					PEDIMENT_AI_PLUGIN_URL . 'assets/fonts/',
					$src,
					'font-face src must be rewritten to the plugin asset URL'
				);
				$this->assertStringNotContainsString( 'file:./assets/', $src );
			}
		}
	}
}
