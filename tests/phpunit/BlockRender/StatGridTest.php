<?php

class StatGridTest extends WP_UnitTestCase {

	public function test_stat_block_json_has_parent_and_inserter_false() {
		$path = dirname( __DIR__, 3 ) . '/src/blocks/stat/block.json';
		$this->assertFileIsReadable( $path );
		$data = json_decode( file_get_contents( $path ), true );
		$this->assertSame( array( 'pediment/stat-grid' ), $data['parent'] );
		$this->assertFalse( $data['supports']['inserter'] );
	}

	public function test_grid_block_json_description_mentions_stats_and_is_wide() {
		$path = dirname( __DIR__, 3 ) . '/src/blocks/stat-grid/block.json';
		$this->assertFileIsReadable( $path );
		$data = json_decode( file_get_contents( $path ), true );
		$this->assertStringContainsStringIgnoringCase( 'stat', (string) $data['description'] );
		$this->assertContains( 'wide', $data['supports']['align'] );
	}

	public function test_grid_wraps_three_stat_cards_side_by_side() {
		$html = do_blocks(
			'<!-- wp:pediment/stat-grid -->' .
			'<!-- wp:pediment/stat {"value":"25+","label":"Years"} /-->' .
			'<!-- wp:pediment/stat {"value":"335+","label":"Combined"} /-->' .
			'<!-- wp:pediment/stat {"value":"100%","label":"Confidential"} /-->' .
			'<!-- /wp:pediment/stat-grid -->'
		);
		$this->assertStringContainsString( 'starter-stat-grid', $html );
		$this->assertSame( 3, substr_count( $html, 'starter-stat__value' ) );
		$this->assertStringContainsString( '25+', $html );
		$this->assertStringContainsString( '335+', $html );
		$this->assertStringContainsString( '100%', $html );
	}

	public function test_grid_skips_empty_stat_child() {
		$html = do_blocks(
			'<!-- wp:pediment/stat-grid -->' .
			'<!-- wp:pediment/stat {"value":"42","label":"Kept"} /-->' .
			'<!-- wp:pediment/stat {"value":"","label":""} /-->' .
			'<!-- /wp:pediment/stat-grid -->'
		);
		$this->assertSame( 1, substr_count( $html, 'starter-stat__value' ) );
		$this->assertStringContainsString( '42', $html );
	}
}
