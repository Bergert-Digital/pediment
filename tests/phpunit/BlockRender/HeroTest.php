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

	public function test_block_json_variant_enum_is_exact_and_renderable() {
		$path = dirname( __DIR__, 3 ) . '/src/blocks/hero/block.json';
		$this->assertFileIsReadable( $path );
		$data = json_decode( file_get_contents( $path ), true );
		$this->assertIsArray( $data );
		$this->assertSame(
			array( 'default', 'centered', 'media-bg', 'stat-card' ),
			$data['attributes']['variant']['enum'],
			'block.json variant enum must list exactly the variants the renderer ships'
		);
		$html = $this->render( array( 'variant' => 'stat-card', 'headline' => 'X' ) );
		$this->assertStringContainsString( 'is-variant-stat-card', $html );
	}

	public function test_block_json_description_does_not_mention_split() {
		$path = dirname( __DIR__, 3 ) . '/src/blocks/hero/block.json';
		$this->assertFileIsReadable( $path );
		$data = json_decode( file_get_contents( $path ), true );
		$this->assertIsArray( $data );
		$this->assertStringNotContainsStringIgnoringCase( 'split', $data['description'] );
	}

	public function test_stat_card_renders_eyebrow_secondary_and_ticks() {
		$html = $this->render(
			array(
				'variant'       => 'stat-card',
				'headline'      => 'We help leaders',
				'subheadline'   => 'Senior-led work.',
				'eyebrow'       => 'Strategy Consulting',
				'ctaText'       => 'Start',
				'ctaUrl'        => '/start',
				'secondaryText' => 'Our work',
				'secondaryUrl'  => '/work',
				'ticks'         => array( '120+ engagements', 'Global delivery' ),
			)
		);
		$this->assertStringContainsString( 'starter-hero__eyebrow', $html );
		$this->assertStringContainsString( 'Strategy Consulting', $html );
		$this->assertStringContainsString( 'href="/start"', $html );
		$this->assertStringContainsString( 'href="/work"', $html );
		$this->assertStringContainsString( 'Our work', $html );
		$this->assertStringContainsString( 'starter-hero__tick', $html );
		$this->assertStringContainsString( '120+ engagements', $html );
		$this->assertStringContainsString( 'Global delivery', $html );
	}

	public function test_stat_card_renders_glass_stat_and_metrics() {
		$html = $this->render(
			array(
				'variant'   => 'stat-card',
				'headline'  => 'H',
				'statValue' => '+34%',
				'statText'  => 'margin improvement',
				'metrics'   => array(
					array( 'value' => '18', 'label' => 'countries' ),
					array( 'value' => '94%', 'label' => 'repeat clients' ),
				),
			)
		);
		$this->assertStringContainsString( 'starter-hero__glass', $html );
		$this->assertStringContainsString( '+34%', $html );
		$this->assertStringContainsString( 'margin improvement', $html );
		$this->assertStringContainsString( 'starter-hero__metric', $html );
		$this->assertStringContainsString( '18', $html );
		$this->assertStringContainsString( 'countries', $html );
		$this->assertStringContainsString( '94%', $html );
		$this->assertStringContainsString( 'repeat clients', $html );
	}

	public function test_stat_card_omits_secondary_when_url_missing() {
		$html = $this->render(
			array(
				'variant'       => 'stat-card',
				'headline'      => 'H',
				'secondaryText' => 'Our work',
				'secondaryUrl'  => '',
			)
		);
		$this->assertStringNotContainsString( 'starter-hero__cta--secondary', $html );
	}

	public function test_default_variant_markup_unchanged() {
		$html = $this->render(
			array( 'variant' => 'default', 'headline' => 'D', 'subheadline' => 'S' )
		);
		$this->assertStringContainsString( 'is-variant-default', $html );
		$this->assertStringContainsString( 'starter-hero__headline', $html );
		$this->assertStringNotContainsString( 'starter-hero__glass', $html );
		$this->assertStringNotContainsString( 'starter-hero__eyebrow', $html );
	}

	public function test_starter_hero_variants_filter_is_default_superset() {
		$this->assertTrue( function_exists( 'starter_hero_variants' ) );
		$this->assertSame(
			array( 'default', 'centered', 'media-bg', 'stat-card' ),
			starter_hero_variants()
		);
	}

	public function test_filter_removing_stat_card_falls_back_to_default() {
		$cb = static function ( $variants ) {
			return array_values( array_diff( $variants, array( 'stat-card' ) ) );
		};
		add_filter( 'starter_hero_variants', $cb );
		try {
			$html = $this->render(
				array(
					'variant'   => 'stat-card',
					'headline'  => 'H',
					'statValue' => '+34%',
				)
			);
			$this->assertStringContainsString( 'is-variant-default', $html );
			$this->assertStringNotContainsString( 'is-variant-stat-card', $html );
			$this->assertStringNotContainsString( 'starter-hero__glass', $html );
		} finally {
			remove_filter( 'starter_hero_variants', $cb );
		}
	}
}
