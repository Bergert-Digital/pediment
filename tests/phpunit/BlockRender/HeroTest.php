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
}
