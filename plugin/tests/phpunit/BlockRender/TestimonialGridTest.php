<?php

class TestimonialGridTest extends WP_UnitTestCase {
	private function child( array $attrs ): string {
		return do_blocks( '<!-- wp:pediment/testimonial ' . wp_json_encode( $attrs ) . ' /-->' );
	}

	public function test_child_renders_quote_name_and_role() {
		$html = $this->child( array(
			'quote'      => 'They stayed until it worked.',
			'authorName' => 'Sarah Klein',
			'authorRole' => 'Group COO, Vantage Industries',
		) );
		$this->assertStringContainsString( 'starter-testimonial', $html );
		$this->assertStringContainsString( '<figure', $html );
		$this->assertStringContainsString( 'starter-testimonial__quote', $html );
		$this->assertStringContainsString( 'They stayed until it worked.', $html );
		$this->assertStringContainsString( 'starter-testimonial__name', $html );
		$this->assertStringContainsString( 'Sarah Klein', $html );
		$this->assertStringContainsString( 'starter-testimonial__role', $html );
		$this->assertStringContainsString( 'Group COO, Vantage Industries', $html );
	}

	public function test_child_renders_nothing_when_quote_empty() {
		$html = $this->child( array( 'quote' => '', 'authorName' => 'No One' ) );
		$this->assertStringNotContainsString( 'starter-testimonial', $html );
	}

	public function test_child_shows_initials_when_no_avatar() {
		$html = $this->child( array(
			'quote'      => 'Great partner.',
			'authorName' => 'Markus Roth',
		) );
		$this->assertStringContainsString( 'starter-testimonial__initials', $html );
		$this->assertStringContainsString( '>MR<', $html );
		$this->assertStringNotContainsString( 'starter-testimonial__avatar', $html );
	}

	public function test_child_single_word_name_yields_one_initial() {
		$html = $this->child( array( 'quote' => 'Solo.', 'authorName' => 'Cher' ) );
		$this->assertStringContainsString( 'starter-testimonial__initials', $html );
		$this->assertStringContainsString( '>C<', $html );
	}

	public function test_child_renders_avatar_when_id_set() {
		$att = self::factory()->attachment->create_upload_object( DIR_TESTDATA . '/images/canola.jpg' );
		$html = $this->child( array(
			'quote'      => 'With a face.',
			'authorName' => 'Has Pic',
			'avatarId'   => $att,
		) );
		$this->assertStringContainsString( 'starter-testimonial__avatar', $html );
		$this->assertStringContainsString( '<img', $html );
		$this->assertStringNotContainsString( 'starter-testimonial__initials', $html );
		wp_delete_attachment( $att, true );
	}

	public function test_child_omits_byline_when_no_name_or_role() {
		$html = $this->child( array( 'quote' => 'Anonymous but mighty.' ) );
		$this->assertStringContainsString( 'starter-testimonial__quote', $html );
		$this->assertStringNotContainsString( 'starter-testimonial__by', $html );
	}

	public function test_child_block_json_has_parent_and_attributes() {
		$path = dirname( __DIR__, 3 ) . '/src/blocks/testimonial/block.json';
		$this->assertFileIsReadable( $path );
		$data = json_decode( file_get_contents( $path ), true );
		$this->assertSame( array( 'pediment/testimonial-grid' ), $data['parent'] );
		$this->assertFalse( $data['supports']['inserter'] );
		foreach ( array( 'quote', 'authorName', 'authorRole', 'avatarId' ) as $attr ) {
			$this->assertArrayHasKey( $attr, $data['attributes'] );
		}
	}

	public function test_grid_wraps_three_testimonial_cards() {
		$html = do_blocks(
			'<!-- wp:pediment/testimonial-grid -->' .
			'<!-- wp:pediment/testimonial {"quote":"First quote.","authorName":"Aa Bb","authorRole":"CEO, One"} /-->' .
			'<!-- wp:pediment/testimonial {"quote":"Second quote.","authorName":"Cc Dd","authorRole":"CTO, Two"} /-->' .
			'<!-- wp:pediment/testimonial {"quote":"Third quote.","authorName":"Ee Ff","authorRole":"COO, Three"} /-->' .
			'<!-- /wp:pediment/testimonial-grid -->'
		);
		$this->assertStringContainsString( 'starter-testimonial-grid', $html );
		$this->assertSame( 3, substr_count( $html, 'starter-testimonial__quote' ) );
		$this->assertStringContainsString( 'First quote.', $html );
		$this->assertStringContainsString( 'Second quote.', $html );
		$this->assertStringContainsString( 'Third quote.', $html );
	}

	public function test_grid_skips_empty_quote_child() {
		$html = do_blocks(
			'<!-- wp:pediment/testimonial-grid -->' .
			'<!-- wp:pediment/testimonial {"quote":"Kept."} /-->' .
			'<!-- wp:pediment/testimonial {"quote":""} /-->' .
			'<!-- /wp:pediment/testimonial-grid -->'
		);
		$this->assertSame( 1, substr_count( $html, 'starter-testimonial__quote' ) );
		$this->assertStringContainsString( 'Kept.', $html );
	}

	public function test_grid_block_json_description_mentions_testimonial() {
		$path = dirname( __DIR__, 3 ) . '/src/blocks/testimonial-grid/block.json';
		$data = json_decode( file_get_contents( $path ), true );
		$this->assertStringContainsStringIgnoringCase( 'testimonial', (string) $data['description'] );
		$this->assertContains( 'wide', $data['supports']['align'] );
	}
}
