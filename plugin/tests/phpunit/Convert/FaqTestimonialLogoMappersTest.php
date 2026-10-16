<?php

require_once __DIR__ . '/FakeMedia.php';

use Pediment\Convert\Converter;
use Pediment\Convert\Result;

/**
 * faq/faq-item, testimonial-grid/testimonial and logo-cloud with valid and empty attributes.
 */
class FaqTestimonialLogoMappersTest extends WP_UnitTestCase {

	private function convert( string $markup ): Result {
		return Converter::withDefaults()->convert( $markup, new FakeMedia() );
	}

	public function test_a_lone_faq_item_inside_a_column_becomes_a_details_block(): void {
		$markup = '<!-- wp:column -->' . "\n" . '<div class="wp-block-column"><!-- wp:pediment/faq-item {"question":"Lost?","answer":"Ask at the desk."} /--></div>' . "\n" . '<!-- /wp:column -->';
		$column = parse_blocks( $this->convert( $markup )->markup )[0];
		$this->assertSame( 'core/details', $column['innerBlocks'][0]['blockName'] );
		$this->assertSame( 'is-style-accordion', $column['innerBlocks'][0]['attrs']['className'] );
	}

	public function test_answer_html_survives_as_markup(): void {
		$markup = $this->convert( (string) file_get_contents( __DIR__ . '/fixtures/faq.input.html' ) )->markup;
		$this->assertStringContainsString( '<p>Yes, see <a href="/guide/arrival/#by-car">the arrival guide</a> &amp; the map.</p>', $markup );
	}

	public function test_question_without_answer_keeps_an_empty_details_body(): void {
		$markup = $this->convert( '<!-- wp:pediment/faq-item {"question":"Q"} /-->' )->markup;
		$this->assertStringContainsString( '<details class="wp-block-details is-style-accordion"><summary>Q</summary></details>', $markup );
	}

	public function test_empty_faq_testimonial_and_logo_cloud_are_removed(): void {
		$result = $this->convert(
			"<!-- wp:pediment/faq -->\n<!-- wp:pediment/faq-item /-->\n<!-- /wp:pediment/faq -->"
			. '<!-- wp:pediment/testimonial {"authorName":"No quote"} /-->'
			. "<!-- wp:pediment/logo-cloud -->\n<!-- /wp:pediment/logo-cloud -->"
		);
		$this->assertSame( '', $result->markup );
	}

	public function test_byline_parts_and_missing_avatar(): void {
		$role_only = $this->convert( '<!-- wp:pediment/testimonial {"quote":"Q","authorRole":"Guest"} /-->' )->markup;
		$this->assertStringContainsString( '<cite>Guest</cite>', $role_only );
		$missing = $this->convert( '<!-- wp:pediment/testimonial {"quote":"Q","authorName":"Ann","avatarId":4040} /-->' );
		$this->assertStringContainsString( '<cite><strong>Ann</strong></cite>', $missing->markup );
		$this->assertSame( array( 'pediment/testimonial: avatar 4040 not found, left out' ), $missing->notes );
	}

	public function test_logo_cloud_caption_only_has_no_logo_row(): void {
		$group = parse_blocks( $this->convert( '<!-- wp:pediment/logo-cloud {"caption":"Clients"} -->' . "\n" . '<!-- /wp:pediment/logo-cloud -->' )->markup )[0];
		$this->assertSame( array( 'core/paragraph' ), array_column( $group['innerBlocks'], 'blockName' ) );
		$this->assertSame( 'is-style-logos', $group['attrs']['className'] );
	}

	public function test_an_answer_of_paragraphs_becomes_several_paragraph_blocks(): void {
		$attrs  = serialize_block_attributes(
			array(
				'question' => 'Q',
				'answer'   => 'Intro <p class="x">One</p><p>Two <a href="/x">link</a></p><p></p><p>Three',
			)
		);
		$result = $this->convert( "<!-- wp:pediment/faq-item {$attrs} /-->" );
		$inner  = parse_blocks( $result->markup )[0]['innerBlocks'];
		$this->assertSame( array( 'core/paragraph', 'core/paragraph', 'core/paragraph', 'core/paragraph' ), array_column( $inner, 'blockName' ) );
		$this->assertSame(
			array( '<p>Intro</p>', '<p>One</p>', '<p>Two <a href="/x">link</a></p>', '<p>Three</p>' ),
			array_map( static fn( array $block ): string => trim( $block['innerHTML'] ), $inner )
		);
		$this->assertStringNotContainsString( '<p><p>', $result->markup );
		$this->assertContains( 'pediment/faq-item answer: attributes of a <p> dropped', $result->notes );
		$this->assertSame( array(), $result->problems );
	}

	public function test_a_quote_of_paragraphs_becomes_several_paragraph_blocks(): void {
		$result = $this->convert( '<!-- wp:pediment/testimonial {"quote":"<p>One</p><p>Two</p>","authorName":"Ann"} /-->' );
		$quote  = parse_blocks( $result->markup )[0]['innerBlocks'][0];
		$this->assertSame( 'core/quote', $quote['blockName'] );
		$this->assertSame( array( '<p>One</p>', '<p>Two</p>' ), array_map( static fn( array $block ): string => trim( $block['innerHTML'] ), $quote['innerBlocks'] ) );
		$this->assertSame( array(), $result->problems );
	}

	public function test_block_html_other_than_paragraphs_refuses_the_conversion(): void {
		$answer = '<!-- wp:pediment/faq-item {"question":"Q","answer":"<ul><li>A</li></ul>"} /-->';
		$quote  = '<!-- wp:pediment/testimonial {"quote":"<h3>Wow</h3>"} /-->';
		$result = $this->convert( $answer . "\n" . $quote );
		$this->assertSame(
			array(
				'pediment/faq-item answer holds block HTML (<ul>); edit it by hand first',
				'pediment/testimonial quote holds block HTML (<h3>); edit it by hand first',
			),
			$result->problems
		);
		$this->assertSame( $answer . "\n" . $quote, $result->markup );
	}
}
