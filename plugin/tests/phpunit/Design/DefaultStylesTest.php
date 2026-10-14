<?php

use Pediment\Design\DefaultStyles;
use Pediment\Design\StyleValidator;

class DefaultStylesTest extends WP_UnitTestCase {

	private function render_variation_css( string $markup ): string {
		wp_deregister_style( 'block-style-variation-styles' );
		wp_clean_theme_json_cache();
		do_blocks( $markup );
		$after = wp_styles()->get_data( 'block-style-variation-styles', 'after' );
		return is_array( $after ) ? implode( "\n", $after ) : '';
	}

	public function test_every_default_style_passes_the_validator(): void {
		$specs = DefaultStyles::specs();
		$this->assertCount( 7, $specs );
		foreach ( $specs as $spec ) {
			$this->assertSame( array(), ( new StyleValidator() )->validate( $spec ), $spec['slug'] );
		}
	}

	public function test_defaults_are_registered_with_style_data(): void {
		DefaultStyles::registerAll();
		$styles = WP_Block_Styles_Registry::get_instance()->get_registered_styles_for_block( 'core/group' );
		$this->assertArrayHasKey( 'card', $styles );
		$this->assertNotEmpty( $styles['card']['style_data'] );
	}

	public function test_card_renders_token_css(): void {
		DefaultStyles::registerAll();
		$css = $this->render_variation_css( '<!-- wp:group {"className":"is-style-card"} --><div class="wp-block-group is-style-card"><p>x</p></div><!-- /wp:group -->' );
		$this->assertStringContainsString( 'is-style-card--', $css );
		$this->assertStringContainsString( 'border-radius: var(--wp--custom--radius--md)', $css );
	}

	public function test_radius_tokens_exist(): void {
		wp_clean_theme_json_cache();
		$this->assertSame( '12px', wp_get_global_settings( array( 'custom', 'radius', 'md' ) ) );
		$this->assertSame( '999px', wp_get_global_settings( array( 'custom', 'radius', 'pill' ) ) );
	}
}
