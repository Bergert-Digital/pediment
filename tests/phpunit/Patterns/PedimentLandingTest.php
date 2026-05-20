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

	public function test_pattern_renders_all_blocks_server_side() {
		$html = do_blocks( $this->pattern()['content'] );
		// An unregistered/failed dynamic block leaves its raw wp-comment with
		// no rendered wrapper class, so asserting every block's real class is
		// a true server-side render guard (the editor-only "block-list"
		// string never appears in PHP output and would be a vacuous check).
		$this->assertStringNotContainsString( 'is not registered', $html );
		foreach (
			array(
				'starter-hero',
				'is-variant-stat-card',
				'starter-feature-grid',
				'starter-steps',
				'starter-stat',
				'starter-pull-quote',
				'is-variant-testimonial',
				'starter-faq',
				'starter-cta',
				'starter-blog-index',
				'is-style-band-navy',
				'is-style-band-surface',
			) as $needle
		) {
			$this->assertStringContainsString( $needle, $html, "rendered HTML must contain: $needle" );
		}
	}

	public function test_pattern_copy_is_rebrandable_no_pediment() {
		$content = $this->pattern()['content'];
		$this->assertFalse(
			stripos( $content, 'pediment' ),
			'pattern content must not ship the fictional Pediment brand voice'
		);
		$this->assertFalse( stripos( $content, 'consultanc' ) );
	}

	public function test_services_band_uses_section_head_block() {
		$content = $this->pattern()['content'];
		$blocks  = parse_blocks( $content );
		$top     = array_values(
			array_filter(
				$blocks,
				static fn( $b ) => ! empty( $b['blockName'] )
			)
		);
		// 2nd top-level band (index 1) is the Services band.
		$services_band = $top[1];
		$inner_names   = array_values(
			array_filter(
				array_map( static fn( $b ) => $b['blockName'], $services_band['innerBlocks'] )
			)
		);
		$this->assertSame(
			array( 'starter/section-head', 'starter/feature-grid' ),
			$inner_names,
			'services band should be [section-head, feature-grid]'
		);
	}
}
