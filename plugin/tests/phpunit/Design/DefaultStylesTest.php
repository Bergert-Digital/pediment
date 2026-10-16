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
		$this->assertCount( 19, $specs );
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

	public function test_converter_styles_are_registered_for_their_blocks(): void {
		DefaultStyles::registerAll();
		$registry = WP_Block_Styles_Registry::get_instance();
		$expected = array(
			'core/group'      => array( 'stat', 'steps', 'panel', 'quote-card', 'logos', 'prose' ),
			'core/cover'      => array( 'media-card' ),
			'core/details'    => array( 'accordion' ),
			'core/media-text' => array( 'media-row' ),
			'core/quote'      => array( 'pull-quote' ),
			'core/image'      => array( 'framed' ),
		);
		foreach ( $expected as $block => $slugs ) {
			foreach ( $slugs as $slug ) {
				$this->assertArrayHasKey( $slug, $registry->get_registered_styles_for_block( $block ), "{$slug} on {$block}" );
			}
		}
	}

	public function test_media_row_css_reaches_the_media_text_image(): void {
		DefaultStyles::registerAll();
		$css = $this->render_variation_css( '<!-- wp:media-text {"className":"is-style-media-row"} --><div class="wp-block-media-text is-stacked-on-mobile is-style-media-row"><figure class="wp-block-media-text__media"></figure><div class="wp-block-media-text__content"></div></div><!-- /wp:media-text -->' );
		$this->assertMatchesRegularExpression( '/\.wp-block-media-text\.is-style-media-row--\d+>\.wp-block-media-text__media img\)\{border-radius:var\(--wp--custom--radius--md\)/', $css );
	}

	public function test_accordion_styles_the_open_details(): void {
		DefaultStyles::registerAll();
		$css = $this->render_variation_css( '<!-- wp:details {"className":"is-style-accordion"} --><details class="wp-block-details is-style-accordion"><summary>Q</summary></details><!-- /wp:details -->' );
		$this->assertStringContainsString( 'background-color: var(--wp--preset--color--surface-elevated)', $css );
		$this->assertMatchesRegularExpression( '/is-style-accordion--\d+\[open\]\)\{border-color:var\(--wp--preset--color--border-strong\)/', $css );
	}

	public function test_framed_puts_radius_and_shadow_on_the_img(): void {
		DefaultStyles::registerAll();
		$css = $this->render_variation_css( '<!-- wp:image {"className":"is-style-framed"} --><figure class="wp-block-image is-style-framed"><img src="x.jpg" alt=""/></figure><!-- /wp:image -->' );
		$this->assertMatchesRegularExpression( '/\.wp-block-image\.is-style-framed--\d+ img,[^{]*\)\{border-radius: var\(--wp--custom--radius--md\);box-shadow: var\(--wp--preset--shadow--medium\);/', $css );
	}

	public function test_tile_styles_the_icon_glyph_with_tokens(): void {
		DefaultStyles::registerAll();
		$css = $this->render_variation_css( '<!-- wp:pediment/icon {"icon":"cube","className":"is-style-tile"} /-->' );
		$this->assertMatchesRegularExpression( '/\.wp-block-pediment-icon\.is-style-tile--\d+>\.wp-block-pediment-icon__glyph\)\{width:46px;height:46px;/', $css );
		$this->assertStringContainsString( 'background:var(--wp--preset--color--accent-tint);color:var(--wp--preset--color--accent)', $css );
	}

	public function test_pill_text_uses_the_surface_token(): void {
		$pill = null;
		foreach ( DefaultStyles::specs() as $spec ) {
			if ( 'pill' === $spec['slug'] ) {
				$pill = $spec;
			}
		}
		$this->assertNotNull( $pill );
		$this->assertSame( 'var:preset|color|surface', $pill['styles']['color']['text'] );
	}

	public function test_radius_tokens_exist(): void {
		wp_clean_theme_json_cache();
		$this->assertSame( '12px', wp_get_global_settings( array( 'custom', 'radius', 'md' ) ) );
		$this->assertSame( '999px', wp_get_global_settings( array( 'custom', 'radius', 'pill' ) ) );
	}
}
