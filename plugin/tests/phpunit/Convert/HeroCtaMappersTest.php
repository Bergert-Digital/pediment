<?php

require_once __DIR__ . '/FakeMedia.php';

use Pediment\Convert\Converter;
use Pediment\Convert\Result;

/**
 * hero (four variants) and cta with valid and empty attributes.
 */
class HeroCtaMappersTest extends WP_UnitTestCase {

	private function convert( string $markup ): Result {
		return Converter::withDefaults()->convert( $markup, new FakeMedia() );
	}

	public function test_unknown_variant_falls_back_to_default(): void {
		$block = parse_blocks( $this->convert( '<!-- wp:pediment/hero {"variant":"bogus","headline":"H"} /-->' )->markup )[0];
		$this->assertSame( 'core/group', $block['blockName'] );
		$this->assertSame( array( 'level' => 1 ), $block['innerBlocks'][0]['attrs'] );
	}

	public function test_a_site_variant_without_a_mapping_is_noted(): void {
		$add = static fn( array $variants ): array => array_merge( $variants, array( 'split' ) );
		add_filter( 'pediment_hero_variants', $add );
		$result = $this->convert( '<!-- wp:pediment/hero {"variant":"split","headline":"H"} /-->' );
		remove_filter( 'pediment_hero_variants', $add );
		$this->assertSame( array( 'pediment/hero: variant split has no mapping, converted as default' ), $result->notes );
		$this->assertStringContainsString( '<h1 class="wp-block-heading">H</h1>', $result->markup );
	}

	public function test_pediment_hero_variants_returns_the_four_mapped_variants(): void {
		$this->assertSame( array( 'default', 'centered', 'media-bg', 'stat-card' ), pediment_hero_variants() );
	}

	public function test_a_site_variant_added_by_the_filter_keeps_the_mapped_variants(): void {
		$add = static fn( array $variants ): array => array_merge( $variants, array( 'split' ) );
		add_filter( 'pediment_hero_variants', $add );
		$result = $this->convert( '<!-- wp:pediment/hero {"variant":"stat-card","headline":"H","statValue":"98%"} /-->' );
		remove_filter( 'pediment_hero_variants', $add );
		$this->assertSame( array(), $result->notes );
		$this->assertSame( 'core/columns', parse_blocks( $result->markup )[0]['blockName'] );
	}

	public function test_a_variant_removed_by_the_filter_converts_as_default(): void {
		$remove = static fn( array $variants ): array => array_values( array_diff( $variants, array( 'stat-card' ) ) );
		add_filter( 'pediment_hero_variants', $remove );
		$result = $this->convert( '<!-- wp:pediment/hero {"variant":"stat-card","headline":"H","statValue":"98%"} /-->' );
		remove_filter( 'pediment_hero_variants', $remove );
		$block = parse_blocks( $result->markup )[0];
		$this->assertSame( 'core/group', $block['blockName'] );
		$this->assertSame( 'section', $block['attrs']['tagName'] );
		$this->assertStringNotContainsString( '98%', $result->markup );
	}

	public function test_button_needs_text_and_a_safe_url(): void {
		$markup = $this->convert( '<!-- wp:pediment/hero {"headline":"H","ctaText":"Go"} /--><!-- wp:pediment/cta {"title":"T","primaryText":"Go","primaryUrl":"javascript:alert(1)","secondaryText":"Read","secondaryUrl":"/read"} /-->' )->markup;
		$this->assertSame( 1, substr_count( $markup, '<!-- wp:button ' ) );
		$this->assertStringContainsString( 'is-style-pill-ghost', $markup );
		$this->assertStringNotContainsString( 'javascript', $markup );
	}

	public function test_empty_hero_and_cta_are_removed(): void {
		$result = $this->convert( '<!-- wp:pediment/hero /--><!-- wp:pediment/hero {"variant":"media-bg","mediaId":4040} /--><!-- wp:pediment/cta {"primaryText":"No URL"} /-->' );
		$this->assertSame( '', $result->markup );
		$this->assertContains( 'pediment/hero: image 4040 not found, left out', $result->notes );
	}

	public function test_stat_card_without_stats_has_an_empty_media_card(): void {
		$columns = parse_blocks( $this->convert( '<!-- wp:pediment/hero {"variant":"stat-card","headline":"H","mediaId":77} /-->' )->markup )[0];
		$cover   = $columns['innerBlocks'][1]['innerBlocks'][0];
		$this->assertSame( 'core/cover', $cover['blockName'] );
		$this->assertSame( array(), $cover['innerBlocks'] );
		$this->assertSame( 'The team at work', $cover['attrs']['alt'] );
		$this->assertFalse( $cover['attrs']['isDark'] );
	}

	public function test_stat_card_paragraphs_exist_only_when_set(): void {
		$value_only = parse_blocks( $this->convert( '<!-- wp:pediment/hero {"variant":"stat-card","headline":"H","statValue":"98%"} /-->' )->markup )[0];
		$stat       = $value_only['innerBlocks'][1]['innerBlocks'][0]['innerBlocks'][0]['innerBlocks'][0];
		$this->assertSame( 'Stat', $stat['attrs']['metadata']['name'] );
		$this->assertSame( array( '<p>98%</p>' ), array_map( static fn( array $block ): string => trim( $block['innerHTML'] ), $stat['innerBlocks'] ) );

		$text_only = parse_blocks( $this->convert( '<!-- wp:pediment/hero {"variant":"stat-card","headline":"H","statText":"happy guests"} /-->' )->markup )[0];
		$stat      = $text_only['innerBlocks'][1]['innerBlocks'][0]['innerBlocks'][0]['innerBlocks'][0];
		$this->assertSame( array( '<p class="pediment-stat-label">happy guests</p>' ), array_map( static fn( array $block ): string => trim( $block['innerHTML'] ), $stat['innerBlocks'] ) );
	}

	/**
	 * The card style centres its content; the legacy metrics were
	 * start-aligned (value over label), so each metric paragraph keeps
	 * that alignment while the stat stays centred.
	 */
	public function test_stat_card_metrics_stay_start_aligned(): void {
		$columns = parse_blocks( $this->convert( '<!-- wp:pediment/hero {"variant":"stat-card","headline":"H","statValue":"98%","metrics":[{"value":"12+","label":"Metric one"},{"label":"Label only"}]} /-->' )->markup )[0];
		$card    = $columns['innerBlocks'][1]['innerBlocks'][0]['innerBlocks'][0];
		$this->assertSame( 'Hero stat', $card['attrs']['metadata']['name'] );
		$stat    = $card['innerBlocks'][0];
		$metrics = $card['innerBlocks'][1];
		$this->assertSame( 'Metrics', $metrics['attrs']['metadata']['name'] );
		$this->assertSame( array( '<p>98%</p>' ), array_map( static fn( array $block ): string => trim( $block['innerHTML'] ), $stat['innerBlocks'] ) );
		$this->assertSame(
			array( '<p class="has-text-align-left"><strong>12+</strong><br>Metric one</p>', '<p class="has-text-align-left">Label only</p>' ),
			array_map( static fn( array $block ): string => trim( $block['innerHTML'] ), $metrics['innerBlocks'] )
		);
		foreach ( $metrics['innerBlocks'] as $metric ) {
			$this->assertSame( array( 'align' => 'left' ), $metric['attrs'] );
		}
	}

	public function test_an_empty_stat_card_hero_is_removed(): void {
		$result = $this->convert( '<!-- wp:pediment/hero {"variant":"stat-card"} /-->' . '<!-- wp:pediment/hero {"variant":"stat-card","mediaId":4040} /-->' );
		$this->assertSame( '', $result->markup );
		$this->assertContains( 'pediment/hero: rendered nothing, removed (2 times)', $result->notes );
	}

	public function test_media_background_uses_the_full_image_with_an_overlay(): void {
		$cover = parse_blocks( $this->convert( '<!-- wp:pediment/hero {"variant":"media-bg","mediaId":77,"headline":"H"} /-->' )->markup )[0];
		$this->assertSame( 'https://site.test/wp-content/uploads/2026/10/photo-77-full.jpg', $cover['attrs']['url'] );
		$this->assertSame( 60, $cover['attrs']['dimRatio'] );
		$this->assertSame( 'primary', $cover['attrs']['overlayColor'] );
	}
}
