<?php

class HeroTest extends WP_UnitTestCase {
	private function render( array $attrs ): string {
		$block_markup = '<!-- wp:starter/hero ' . wp_json_encode( $attrs ) . ' /-->';
		return do_blocks( $block_markup );
	}

	public function test_renders_headline_and_subheadline() {
		$html = $this->render(
			array(
				'variant'     => 'default',
				'headline'    => 'Welcome',
				'subheadline' => 'We help you grow',
				'ctaText'     => 'Get Started',
				'ctaUrl'      => '/start',
			)
		);

		$this->assertStringContainsString( 'Welcome', $html );
		$this->assertStringContainsString( 'We help you grow', $html );
		$this->assertStringContainsString( 'href="/start"', $html );
		$this->assertStringContainsString( 'Get Started', $html );
	}

	public function test_renders_variant_class() {
		$html = $this->render( array( 'variant' => 'centered', 'headline' => 'Hi' ) );
		$this->assertStringContainsString( 'is-variant-centered', $html );
	}

	public function test_omits_cta_when_url_is_empty() {
		$html = $this->render(
			array(
				'variant'  => 'default',
				'headline' => 'No CTA here',
				'ctaText'  => 'Go',
				'ctaUrl'   => '',
			)
		);
		$this->assertStringNotContainsString( '<a', $html );
	}

	public function test_block_json_variant_enum_excludes_split() {
		$path = dirname( __DIR__, 3 ) . '/src/blocks/hero/block.json';
		$this->assertFileIsReadable( $path );
		$data = json_decode( file_get_contents( $path ), true );
		$this->assertIsArray( $data );
		$this->assertSame(
			array( 'default', 'centered', 'media-bg' ),
			$data['attributes']['variant']['enum'],
			'block.json variant enum should not include "split" — UI advertised a variant the renderer never produced'
		);
	}

	public function test_block_json_description_does_not_mention_split() {
		$path = dirname( __DIR__, 3 ) . '/src/blocks/hero/block.json';
		$data = json_decode( file_get_contents( $path ), true );
		$this->assertIsArray( $data );
		$this->assertStringNotContainsStringIgnoringCase( 'split', $data['description'] );
	}
}
