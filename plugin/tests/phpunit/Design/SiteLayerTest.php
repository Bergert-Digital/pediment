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
		add_filter( 'wp_theme_json_data_user', array( $this->layer, 'injectUserData' ) );
	}

	public function tear_down(): void {
		remove_filter( 'wp_theme_json_data_user', array( $this->layer, 'injectUserData' ) );
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
		$this->assertSame( array( 'promo' ), $this->layer->promoteShippedStyles( array( 'promo', 'unrelated' ) ) );
		$this->assertSame( SiteStyles::STATE_PROMOTED, $this->store->get( 'promo' )['state'] );

		$data = $this->layer->injectUserData( new WP_Theme_JSON_Data( array( 'version' => 3 ), 'custom' ) );
		$this->assertArrayNotHasKey( 'blocks', $data->get_data()['styles'] ?? array() );
	}

	public function test_admin_init_hook_tolerates_wordpress_hook_arguments(): void {
		SiteLayer::register( $this->layer );
		$this->assertNotFalse( has_action( 'admin_init', array( $this->layer, 'onAdminInit' ) ) );

		$this->store->save(
			array(
				'slug'       => 'promo',
				'label'      => 'Promo',
				'blockTypes' => array( 'core/group' ),
				'styles'     => array( 'color' => array( 'background' => 'var:preset|color|accent' ) ),
			)
		);
		// WordPress invokes admin_init callbacks with an empty string argument.
		call_user_func_array( array( $this->layer, 'onAdminInit' ), array( '' ) );
		$this->assertSame( SiteStyles::STATE_ACTIVE, $this->store->get( 'promo' )['state'] );
	}
}
