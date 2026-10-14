<?php

use Pediment\Design\DefaultStyles;
use Pediment\Design\SiteLayer;
use Pediment\Design\SiteStyles;
use Pediment\Design\StyleValidator;

class SiteLayerTest extends WP_UnitTestCase {

	private SiteStyles $store;
	private SiteLayer $layer;

	public function set_up(): void {
		parent::set_up();
		$this->store = new SiteStyles( new StyleValidator() );
		$this->layer = new SiteLayer( $this->store );
		add_filter( 'wp_theme_json_data_theme', array( $this->layer, 'injectSiteData' ) );
	}

	public function tear_down(): void {
		remove_filter( 'wp_theme_json_data_theme', array( $this->layer, 'injectSiteData' ) );
		wp_clean_theme_json_cache();
		parent::tear_down();
	}

	private function render_variation_css( string $markup ): string {
		wp_deregister_style( 'block-style-variation-styles' );
		wp_clean_theme_json_cache();
		do_blocks( $markup );
		$after = wp_styles()->get_data( 'block-style-variation-styles', 'after' );
		return is_array( $after ) ? implode( "\n", $after ) : '';
	}

	public function test_new_site_style_is_registered_and_renders(): void {
		$this->store->save(
			array(
				'slug'       => 'promo',
				'label'      => 'Promo',
				'blockTypes' => array( 'core/group' ),
				'styles'     => array( 'color' => array( 'background' => 'var:preset|color|accent' ) ),
			)
		);
		$this->layer->registerNames();

		$registered = WP_Block_Styles_Registry::get_instance()->get_registered_styles_for_block( 'core/group' );
		$this->assertArrayHasKey( 'promo', $registered );
		$this->assertSame( 'Promo', $registered['promo']['label'] );

		$css = $this->render_variation_css( '<!-- wp:group {"className":"is-style-promo"} --><div class="wp-block-group is-style-promo"><p>x</p></div><!-- /wp:group -->' );
		$this->assertStringContainsString( 'background-color: var(--wp--preset--color--accent)', $css );
	}

	public function test_site_style_overrides_plugin_default_and_keeps_other_properties(): void {
		DefaultStyles::registerAll();
		$this->store->save(
			array(
				'slug'       => 'card',
				'label'      => 'Card',
				'blockTypes' => array( 'core/group' ),
				'styles'     => array( 'border' => array( 'radius' => 'var(--wp--custom--radius--sm)' ) ),
			)
		);
		$this->layer->registerNames();

		$css = $this->render_variation_css( '<!-- wp:group {"className":"is-style-card"} --><div class="wp-block-group is-style-card"><p>x</p></div><!-- /wp:group -->' );
		$this->assertStringContainsString( 'border-radius: var(--wp--custom--radius--sm)', $css );
		$this->assertStringNotContainsString( 'radius--md', $css );
		$this->assertStringContainsString( 'var(--wp--preset--shadow--subtle)', $css );
	}

	public function test_promoted_styles_stop_loading(): void {
		$this->store->save(
			array(
				'slug'       => 'promo',
				'label'      => 'Promo',
				'blockTypes' => array( 'core/group' ),
				'styles'     => array( 'color' => array( 'background' => 'var:preset|color|accent' ) ),
			)
		);
		$theme = array(
			'promo'     => array(
				'blockTypes' => array( 'core/group', 'core/column' ),
				'styles'     => array(
					'color'   => array( 'background' => 'var:preset|color|accent' ),
					'spacing' => array( 'padding' => array( 'top' => 'var:preset|spacing|50' ) ),
				),
			),
			'unrelated' => array( 'blockTypes' => array( 'core/group' ), 'styles' => array() ),
		);
		$this->assertSame( array( 'promo' ), $this->layer->promoteShippedStyles( $theme ) );
		$this->assertSame( SiteStyles::STATE_PROMOTED, $this->store->get( 'promo' )['state'] );

		$data = $this->layer->injectSiteData( new WP_Theme_JSON_Data( array( 'version' => 3 ), 'theme' ) );
		$this->assertArrayNotHasKey( 'blocks', $data->get_data()['styles'] ?? array() );
	}

	public function test_site_override_that_differs_from_the_theme_stays_active(): void {
		$this->store->save(
			array(
				'slug'       => 'promo',
				'label'      => 'Promo',
				'blockTypes' => array( 'core/group' ),
				'styles'     => array( 'color' => array( 'background' => 'var:preset|color|accent' ) ),
			)
		);
		$this->store->save(
			array(
				'slug'       => 'wide',
				'label'      => 'Wide',
				'blockTypes' => array( 'core/group', 'core/columns' ),
				'styles'     => array( 'color' => array( 'background' => 'var:preset|color|accent' ) ),
			)
		);
		$theme = array(
			'promo' => array(
				'blockTypes' => array( 'core/group' ),
				'styles'     => array( 'color' => array( 'background' => 'var:preset|color|surface' ) ),
			),
			'wide'  => array(
				'blockTypes' => array( 'core/group' ),
				'styles'     => array( 'color' => array( 'background' => 'var:preset|color|accent' ) ),
			),
		);

		$this->assertSame( array(), $this->layer->promoteShippedStyles( $theme ) );
		$this->assertSame( SiteStyles::STATE_ACTIVE, $this->store->get( 'promo' )['state'] );
		$this->assertSame( SiteStyles::STATE_ACTIVE, $this->store->get( 'wide' )['state'] );
	}

	public function test_theme_variations_read_the_active_theme_partials_by_slug(): void {
		$dir = get_temp_dir() . 'pediment-theme-' . wp_rand();
		wp_mkdir_p( $dir . '/styles/blocks' );
		$partial = array(
			'version'    => 3,
			'title'      => 'Panel',
			'slug'       => 'panel',
			'blockTypes' => array( 'core/group' ),
			'styles'     => array( 'color' => array( 'background' => 'var:preset|color|accent' ) ),
		);
		file_put_contents( $dir . '/styles/blocks/panel.json', wp_json_encode( $partial ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		$to_dir = static fn(): string => $dir;
		add_filter( 'stylesheet_directory', $to_dir );
		add_filter( 'template_directory', $to_dir );

		try {
			$this->assertSame(
				array(
					'panel' => array(
						'blockTypes' => array( 'core/group' ),
						// Core resolves preset references when it reads a partial.
						'styles'     => array( 'color' => array( 'background' => 'var(--wp--preset--color--accent)' ) ),
					),
				),
				SiteLayer::themeVariations()
			);

			$this->store->save(
				array(
					'slug'       => 'panel',
					'label'      => 'Panel',
					'blockTypes' => array( 'core/group' ),
					'styles'     => array( 'color' => array( 'background' => 'var:preset|color|accent' ) ),
				)
			);
			$this->assertSame( array( 'panel' ), $this->layer->promoteShippedStyles() );
		} finally {
			remove_filter( 'stylesheet_directory', $to_dir );
			remove_filter( 'template_directory', $to_dir );
			wp_delete_file( $dir . '/styles/blocks/panel.json' );
		}
	}

	public function test_admin_init_hook_tolerates_wordpress_hook_arguments(): void {
		// Core's own admin_init callbacks send headers; keep only ours.
		remove_all_actions( 'admin_init' );
		SiteLayer::register( $this->layer );
		$this->assertNotFalse( has_action( 'admin_init', array( $this->layer, 'onAdminInit' ) ) );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$this->store->save(
			array(
				'slug'       => 'promo',
				'label'      => 'Promo',
				'blockTypes' => array( 'core/group' ),
				'styles'     => array( 'color' => array( 'background' => 'var:preset|color|accent' ) ),
			)
		);
		// WordPress invokes admin_init callbacks with an empty string argument.
		do_action( 'admin_init' );
		$this->assertSame( SiteStyles::STATE_ACTIVE, $this->store->get( 'promo' )['state'] );
	}

	public function test_style_saved_mid_request_applies_without_reregistering(): void {
		$this->store->save(
			array(
				'slug'       => 'fresh',
				'label'      => 'Fresh',
				'blockTypes' => array( 'core/group' ),
				'styles'     => array( 'color' => array( 'background' => 'var:preset|color|accent' ) ),
			)
		);

		$css = $this->render_variation_css( '<!-- wp:group {"className":"is-style-fresh"} --><div class="wp-block-group is-style-fresh"><p>x</p></div><!-- /wp:group -->' );
		$this->assertStringContainsString( 'background-color: var(--wp--preset--color--accent)', $css );
	}

	public function test_site_style_reaches_the_editor_via_the_theme_rest_endpoint(): void {
		$this->store->save(
			array(
				'slug'       => 'promo',
				'label'      => 'Promo',
				'blockTypes' => array( 'core/group' ),
				'styles'     => array( 'color' => array( 'background' => 'var:preset|color|accent' ) ),
			)
		);
		$this->layer->registerNames();
		wp_clean_theme_json_cache();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$response = rest_do_request( new WP_REST_Request( 'GET', '/wp/v2/global-styles/themes/' . get_stylesheet() ) );
		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertContains(
			$data['styles']['blocks']['core/group']['variations']['promo']['color']['background'] ?? null,
			array( 'var(--wp--preset--color--accent)', 'var:preset|color|accent' )
		);
	}
}
