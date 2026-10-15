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

	public function test_grid_columns_follow_the_card_count(): void {
		$card  = '<!-- wp:pediment/feature {"title":"T"} /-->';
		$three = parse_blocks( $this->convert( '<!-- wp:pediment/feature-grid -->' . str_repeat( $card, 3 ) . '<!-- /wp:pediment/feature-grid -->' )->markup )[0];
		$four  = parse_blocks( $this->convert( '<!-- wp:pediment/feature-grid -->' . str_repeat( $card, 4 ) . '<!-- /wp:pediment/feature-grid -->' )->markup )[0];
		$this->assertSame( 3, $three['attrs']['layout']['columnCount'] );
		$this->assertSame( 2, $four['attrs']['layout']['columnCount'] );
		$this->assertSame( '16rem', $four['attrs']['layout']['minimumColumnWidth'] );
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

	public function test_stat_always_has_value_and_label_paragraphs(): void {
		$group = parse_blocks( $this->convert( '<!-- wp:pediment/stat {"label":"Only label"} /-->' )->markup )[0];
		$this->assertCount( 2, $group['innerBlocks'] );
		$this->assertStringContainsString( '<p></p>', $group['innerBlocks'][0]['innerHTML'] );
	}
}
