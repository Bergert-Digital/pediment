<?php

class PedimentLandingTest extends WP_UnitTestCase {

	private function pattern() {
		do_action( 'init' );
		return WP_Block_Patterns_Registry::get_instance()->get_registered( 'pediment/pediment-landing' );
	}

	private function bands(): array {
		return array_values(
			array_filter(
				parse_blocks( $this->pattern()['content'] ),
				static fn( $b ) => ! empty( $b['blockName'] )
			)
		);
	}

	public function test_pattern_is_registered_in_pediment_category() {
		$p = $this->pattern();
		$this->assertIsArray( $p, 'pediment/pediment-landing must be registered' );
		$this->assertContains( 'pediment', $p['categories'] );
	}

	public function test_pattern_is_eight_full_bleed_bands() {
		$top = $this->bands();
		$this->assertCount( 8, $top, 'exactly 8 full-bleed bands' );
		foreach ( $top as $b ) {
			$this->assertSame( 'core/group', $b['blockName'], 'every top-level block must be a band group' );
		}
	}

	public function test_pattern_uses_core_blocks_and_named_styles_only() {
		$content = $this->pattern()['content'];
		$policy  = new \Pediment\Content\Policy();
		$this->assertSame( array(), $policy->legacyBlocksIn( $content ) );
		$this->assertSame( array(), $policy->check( $content )['errors'] );
		foreach (
			array(
				'is-style-media-card',
				'is-style-card',
				'is-style-stat',
				'is-style-steps',
				'is-style-quote-card',
				'is-style-accordion',
				'is-style-panel',
				'is-style-eyebrow',
				'wp:pediment/blog-index',
				'is-style-band-surface',
				'is-style-band-navy',
				'starter-band',
			) as $needle
		) {
			$this->assertStringContainsString( $needle, $content, "pattern must contain: $needle" );
		}
	}

	public function test_pattern_copy_is_rebrandable_no_pediment() {
		// Check user-visible copy only: block markup and class names legitimately
		// contain the `pediment` namespace, so render the pattern and strip tags
		// before asserting the fictional brand voice never reaches readers.
		$text = wp_strip_all_tags( do_blocks( $this->pattern()['content'] ) );
		$this->assertFalse(
			stripos( $text, 'pediment' ),
			'pattern copy must not ship the fictional Pediment brand voice'
		);
		$this->assertFalse( stripos( $text, 'consultanc' ) );
	}

	public function test_services_band_is_section_head_then_features() {
		$names = array_map(
			static fn( $b ) => $b['attrs']['metadata']['name'] ?? '',
			array_values( array_filter( $this->bands()[1]['innerBlocks'], static fn( $b ) => ! empty( $b['blockName'] ) ) )
		);
		$this->assertSame( array( 'Section head', 'Features' ), $names );
	}

	public function test_insights_band_starts_with_a_centred_section_head() {
		$bands = $this->bands();
		$first = end( $bands )['innerBlocks'][0];
		$this->assertSame( 'Section head', $first['attrs']['metadata']['name'] );
		$this->assertSame( array( 'textAlign' => 'center' ), $first['innerBlocks'][1]['attrs'] );
	}
}
