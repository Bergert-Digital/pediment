<?php

require_once __DIR__ . '/FakeMedia.php';

use Pediment\Convert\Converter;
use Pediment\Convert\Result;

/**
 * feature-grid/feature, stat-grid/stat and steps/step with valid and empty attributes.
 */
class GridMappersTest extends WP_UnitTestCase {

	private function convert( string $markup ): Result {
		return Converter::withDefaults()->convert( $markup, new FakeMedia() );
	}

	public function test_empty_children_and_empty_grids_are_removed(): void {
		$result = $this->convert(
			"<!-- wp:pediment/feature-grid -->\n<!-- wp:pediment/feature {\"icon\":\"cube\"} /-->\n<!-- /wp:pediment/feature-grid -->"
			. "<!-- wp:pediment/stat-grid -->\n<!-- wp:pediment/stat {\"context\":\"only context\"} /-->\n<!-- /wp:pediment/stat-grid -->"
			. "<!-- wp:pediment/steps -->\n<!-- wp:pediment/step /-->\n<!-- /wp:pediment/steps -->"
		);
		$this->assertSame( '', $result->markup );
		$this->assertCount( 6, $result->converted );
	}

	/**
	 * Core ignores minimumColumnWidth when columnCount is set and never
	 * collapses such a grid, so grids carry minimumColumnWidth alone:
	 * max(floor, share of the width).
	 */
	public function test_grids_cap_the_columns_and_collapse_on_narrow_screens(): void {
		$feature = '<!-- wp:pediment/feature {"title":"T"} /-->';
		$stat    = '<!-- wp:pediment/stat {"value":"1","label":"L"} /-->';
		$step    = '<!-- wp:pediment/step {"title":"S"} /-->';
		$cases   = array(
			array( 'feature-grid', $feature, 3, 'max(16rem, 30%)' ),
			array( 'feature-grid', $feature, 4, 'max(16rem, 45%)' ),
			array( 'stat-grid', $stat, 2, 'max(10rem, 45%)' ),
			array( 'stat-grid', $stat, 4, 'max(10rem, 22%)' ),
			array( 'stat-grid', $stat, 6, 'max(10rem, 22%)' ),
			array( 'steps', $step, 3, 'max(12.5rem, 30%)' ),
			array( 'steps', $step, 4, 'max(12.5rem, 45%)' ),
		);
		foreach ( $cases as list( $grid, $child, $count, $expected ) ) {
			$block  = parse_blocks( $this->convert( "<!-- wp:pediment/{$grid} -->" . str_repeat( $child, $count ) . "<!-- /wp:pediment/{$grid} -->" )->markup )[0];
			$layout = $block['attrs']['layout'];
			$this->assertSame( 'grid', $layout['type'] );
			$this->assertArrayNotHasKey( 'columnCount', $layout, "{$grid} x {$count}" );
			$this->assertSame( $expected, $layout['minimumColumnWidth'], "{$grid} x {$count}" );
		}
	}

	public function test_lone_children_convert_without_their_parent(): void {
		$result = $this->convert( '<!-- wp:pediment/feature {"title":"F"} /--><!-- wp:pediment/stat {"value":"1","label":"L"} /--><!-- wp:pediment/step {"text":"S"} /-->' );
		$blocks = parse_blocks( $result->markup );
		$this->assertSame( array( 'core/group', 'core/group', 'core/group' ), array_column( $blocks, 'blockName' ) );
		$this->assertSame( 'is-style-card', $blocks[0]['attrs']['className'] );
		$this->assertSame( 'is-style-stat', $blocks[1]['attrs']['className'] );
		$this->assertSame( 'Step', $blocks[2]['attrs']['metadata']['name'] );
	}

	public function test_feature_image_link_and_missing_media(): void {
		$result = $this->convert( '<!-- wp:pediment/feature {"imageId":4040,"title":"T","linkText":"Go","linkUrl":"javascript:alert(1)"} /-->' );
		$this->assertStringNotContainsString( 'wp:image', $result->markup );
		$this->assertStringNotContainsString( 'javascript', $result->markup, 'an unsafe link is dropped with its paragraph' );
		$this->assertContains( 'pediment/feature: image 4040 not found, left out', $result->notes );
		$this->assertContains( 'pediment/feature: icon dropped, core blocks cannot show the icon set', $result->notes );

		$with = $this->convert( '<!-- wp:pediment/feature {"imageId":55,"title":"T"} /-->' );
		$this->assertStringContainsString( 'photo-55-medium.jpg', $with->markup );
		$this->assertSame( array(), $with->notes, 'no icon note when an image replaces the icon' );
	}

	/**
	 * @return array<string,array{string,list<string>}>
	 */
	public function stats(): array {
		return array(
			'all three'          => array( '{"value":"40","label":"Guests","context":"since 2020"}', array( '<p>40</p>', '<p>Guests</p>', '<p>since 2020</p>' ) ),
			'value only'         => array( '{"value":"40"}', array( '<p>40</p>' ) ),
			'value and context'  => array( '{"value":"40","context":"since 2020"}', array( '<p>40</p>', '<p class="pediment-stat-context">since 2020</p>' ) ),
			'label only'         => array( '{"label":"Guests"}', array( '<p class="pediment-stat-label">Guests</p>' ) ),
			'label and context'  => array( '{"label":"Guests","context":"since 2020"}', array( '<p class="pediment-stat-label">Guests</p>', '<p class="pediment-stat-context">since 2020</p>' ) ),
		);
	}

	/**
	 * The stat style styles children by position (value, label, context);
	 * a paragraph that is not in its position carries its role class.
	 *
	 * @dataProvider stats
	 */
	public function test_stat_paragraphs_exist_only_when_set_and_keep_their_role( string $attrs, array $paragraphs ): void {
		$markup = $this->convert( "<!-- wp:pediment/stat {$attrs} /-->" )->markup;
		$group  = parse_blocks( $markup )[0];
		$this->assertSame( $paragraphs, array_map( static fn( array $block ): string => trim( $block['innerHTML'] ), $group['innerBlocks'] ) );
		$this->assertStringNotContainsString( '<p></p>', $markup );
	}

	public function test_the_stat_style_styles_role_classes_like_their_position(): void {
		$css = json_decode( (string) file_get_contents( dirname( __DIR__, 3 ) . '/styles/stat.json' ), true )['styles']['css'];
		$this->assertStringContainsString( '&>:first-child:not(.pediment-stat-label){', $css, 'a lone label is not styled as the number' );
		$this->assertStringContainsString( '&>:nth-child(2):not(.pediment-stat-context){font-weight:600}', $css );
		$this->assertStringContainsString( '&>.pediment-stat-label{font-weight:600}', $css );
		$this->assertMatchesRegularExpression( '/&>\.pediment-stat-context\{font-size:var\(--wp--preset--font-size--sm\);color:var\(--wp--preset--color--foreground-muted\)\}/', $css );
	}
}
