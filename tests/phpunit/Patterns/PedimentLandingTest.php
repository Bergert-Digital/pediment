<?php

class PedimentLandingTest extends WP_UnitTestCase {

	private function pattern() {
		do_action( 'init' );
		return WP_Block_Patterns_Registry::get_instance()->get_registered( 'pediment/pediment-landing' );
	}

	public function test_pattern_is_registered_in_pediment_category() {
		$p = $this->pattern();
		$this->assertIsArray( $p, 'pediment/pediment-landing must be registered' );
		$this->assertContains( 'pediment', $p['categories'] );
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
				'wp:pediment/hero',
				'"variant":"stat-card"',
				'wp:pediment/feature-grid',
				'wp:pediment/feature ',
				'wp:pediment/steps',
				'wp:pediment/step ',
				'wp:pediment/stat ',
				'wp:pediment/pull-quote',
				'"variant":"testimonial"',
				'wp:pediment/faq ',
				'wp:pediment/faq-item',
				'wp:pediment/cta ',
				'wp:pediment/blog-index',
				'is-style-band-surface',
				'is-style-band-navy',
				'starter-band',
			) as $needle
		) {
			$this->assertStringContainsString( $needle, $content, "pattern must contain: $needle" );
		}
		$this->assertStringNotContainsString(
			'wp:pediment/logo-cloud',
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

	public function test_insights_band_starts_with_section_head() {
		$content = $this->pattern()['content'];
		$blocks  = parse_blocks( $content );
		$top     = array_values(
			array_filter(
				$blocks,
				static fn( $b ) => ! empty( $b['blockName'] )
			)
		);
		$insights_band = end( $top );
		$first_inner   = $insights_band['innerBlocks'][0];
		$this->assertSame( 'pediment/section-head', $first_inner['blockName'] );
		$this->assertSame( 'center', $first_inner['attrs']['alignment'] );
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
			array( 'pediment/section-head', 'pediment/feature-grid' ),
			$inner_names,
			'services band should be [section-head, feature-grid]'
		);
	}
}
