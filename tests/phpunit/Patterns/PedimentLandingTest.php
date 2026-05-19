<?php

class PedimentLandingTest extends WP_UnitTestCase {

	private function pattern() {
		do_action( 'init' );
		return WP_Block_Patterns_Registry::get_instance()->get_registered( 'starter/pediment-landing' );
	}

	public function test_pattern_is_registered_in_starter_category() {
		$p = $this->pattern();
		$this->assertIsArray( $p, 'starter/pediment-landing must be registered' );
		$this->assertContains( 'starter', $p['categories'] );
	}

	public function test_pattern_content_parses_cleanly() {
		$content = $this->pattern()['content'];
		$blocks  = parse_blocks( $content );
		$top     = array_values(
			array_filter(
				$blocks,
				static function ( $b ) {
					return ! empty( $b['blockName'] );
				}
			)
		);
		$this->assertNotEmpty( $top, 'pattern must contain real blocks' );
		foreach ( $top as $b ) {
			$this->assertSame(
				'core/group',
				$b['blockName'],
				'every top-level block must be a band group'
			);
		}
		$this->assertCount( 8, $top, 'exactly 8 full-bleed bands' );
	}

	public function test_pattern_composition_blocks_present() {
		$content = $this->pattern()['content'];
		foreach (
			array(
				'wp:starter/hero',
				'"variant":"stat-card"',
				'wp:starter/feature-grid',
				'wp:starter/feature ',
				'wp:starter/steps',
				'wp:starter/step ',
				'wp:starter/stat ',
				'wp:starter/pull-quote',
				'"variant":"testimonial"',
				'wp:starter/faq ',
				'wp:starter/faq-item',
				'wp:starter/cta ',
				'wp:starter/blog-index',
				'is-style-band-surface',
				'is-style-band-navy',
				'starter-band',
			) as $needle
		) {
			$this->assertStringContainsString( $needle, $content, "pattern must contain: $needle" );
		}
		$this->assertStringNotContainsString(
			'wp:starter/logo-cloud',
			$content,
			'image-only logo-cloud band is intentionally omitted'
		);
	}

	public function test_pattern_renders_without_block_errors() {
		$html = do_blocks( $this->pattern()['content'] );
		$this->assertStringNotContainsString( 'block-editor-block-list', $html );
		$this->assertStringNotContainsString( 'is not registered', $html );
		$this->assertStringContainsString( 'is-variant-stat-card', $html );
		$this->assertStringContainsString( 'is-style-band-navy', $html );
		$this->assertStringContainsString( 'starter-blog-index', $html );
	}

	public function test_pattern_copy_is_rebrandable_no_pediment() {
		$content = $this->pattern()['content'];
		$this->assertFalse(
			stripos( $content, 'pediment' ),
			'pattern content must not ship the fictional Pediment brand voice'
		);
		$this->assertFalse( stripos( $content, 'consultanc' ) );
	}
}
