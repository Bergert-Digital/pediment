<?php

require_once __DIR__ . '/FakeMedia.php';

use Pediment\Convert\Attrs;
use Pediment\Convert\Converter;
use Pediment\Convert\Mappers\PullQuoteMapper;
use Pediment\Convert\Result;

class ConverterTest extends WP_UnitTestCase {

	private function convert( string $markup ): Result {
		return Converter::withDefaults()->convert( $markup, new FakeMedia() );
	}

	public function test_markup_without_legacy_blocks_comes_back_byte_for_byte(): void {
		$markup = '<!-- wp:paragraph {"className":"x"} -->' . "\n" . '<p class="x">Café &amp; bar</p>' . "\n" . '<!-- /wp:paragraph -->' . "\n\nloose text";
		$result = $this->convert( $markup );
		$this->assertSame( $markup, $result->markup );
		$this->assertFalse( $result->changed() );
		$this->assertSame( array(), $result->converted );
	}

	public function test_legacy_blocks_convert_at_every_depth_and_removed_ones_take_their_slot(): void {
		$result = $this->convert( (string) file_get_contents( __DIR__ . '/fixtures/nested.input.html' ) );
		$this->assertSame( array( 'pediment/pull-quote', 'pediment/prose', 'pediment/section-head' ), $result->converted );
		$this->assertContains( 'pediment/pull-quote: rendered nothing, removed', $result->notes );

		$group = parse_blocks( $result->markup )[0];
		$this->assertSame( array( 'core/paragraph', 'core/group', 'core/paragraph' ), array_column( $group['innerBlocks'], 'blockName' ) );
		$this->assertCount( count( $group['innerBlocks'] ), array_filter( $group['innerContent'], 'is_null' ), 'one null chunk per inner block' );
		$this->assertSame( 'Section head', $group['innerBlocks'][1]['innerBlocks'][0]['attrs']['metadata']['name'], 'the section head inside the prose is converted too' );
	}

	public function test_a_legacy_block_without_a_mapper_stays_and_is_reported(): void {
		$converter = new Converter( array( new PullQuoteMapper() ) );
		$markup    = '<!-- wp:pediment/prose -->' . "\n" . '<!-- wp:pediment/pull-quote {"quote":"Q"} /-->' . "\n" . '<!-- /wp:pediment/prose -->';
		$result    = $converter->convert( $markup, new FakeMedia() );
		$this->assertSame( array( 'pediment/prose' ), $result->unmapped );
		$this->assertSame( array( 'pediment/pull-quote' ), $result->converted );
		$this->assertStringStartsWith( '<!-- wp:pediment/prose -->', $result->markup );
		$this->assertStringContainsString( 'is-style-pull-quote', $result->markup );
	}

	public function test_class_names_keep_plain_classes_and_registered_styles_only(): void {
		register_block_style(
			'core/quote',
			array(
				'name'  => 'converter-test',
				'label' => 'Converter test',
			)
		);
		$result = $this->convert( '<!-- wp:pediment/pull-quote {"quote":"Q","className":"keep-me is-style-converter-test is-style-align-center"} /-->' );
		unregister_block_style( 'core/quote', 'converter-test' );

		$attrs = parse_blocks( $result->markup )[0]['attrs'];
		$this->assertSame( 'keep-me is-style-pull-quote is-style-converter-test', $attrs['className'] );
		$this->assertStringContainsString( 'class="wp-block-quote keep-me is-style-pull-quote is-style-converter-test"', $result->markup );
		$this->assertSame( array( 'dropped style is-style-align-center on pediment/pull-quote → core/quote' ), $result->notes );
	}

	public function test_attribute_html_is_kept_but_cannot_become_live_markup_or_a_block(): void {
		$out = Attrs::html( '<strong>Bold</strong> &amp; <a href="/x">link</a><script>alert(1)</script><!-- wp:paragraph -->' );
		$this->assertStringStartsWith( '<strong>Bold</strong> &amp; <a href="/x">link</a>', $out );
		$this->assertStringNotContainsString( '<script', $out );
		$this->assertStringNotContainsString( '<!--', $out );

		$attrs = serialize_block_attributes( array( 'quote' => 'Hi <script>alert(1)</script> <!-- wp:html -->' ) );
		$page  = $this->convert( "<!-- wp:pediment/pull-quote {$attrs} /-->" );
		$this->assertSame( array( 'core/quote' ), array_column( parse_blocks( $page->markup ), 'blockName' ) );
		$this->assertStringNotContainsString( '<script', $page->markup );
	}

	public function test_notes_are_counted_not_repeated(): void {
		$result = $this->convert( str_repeat( '<!-- wp:pediment/pull-quote /-->', 3 ) );
		$this->assertSame( array( 'pediment/pull-quote: rendered nothing, removed (3 times)' ), $result->notes );
		$this->assertSame( '', $result->markup );
	}

	/**
	 * Every legacy container whose render.php printed $content, with one inner block.
	 *
	 * @return array<string,array{string,string}>
	 */
	public function containers(): array {
		$out = array();
		$inner = array(
			'pediment/prose'            => "<!-- wp:paragraph -->\n<p>Block text</p>\n<!-- /wp:paragraph -->",
			'pediment/media-text'       => "<!-- wp:paragraph -->\n<p>Block text</p>\n<!-- /wp:paragraph -->",
			'pediment/logo-cloud'       => "<!-- wp:paragraph -->\n<p>Block text</p>\n<!-- /wp:paragraph -->",
			'pediment/faq'              => '<!-- wp:pediment/faq-item {"question":"Q","answer":"A"} /-->',
			'pediment/feature-grid'     => '<!-- wp:pediment/feature {"title":"T"} /-->',
			'pediment/stat-grid'        => '<!-- wp:pediment/stat {"value":"1","label":"L"} /-->',
			'pediment/steps'            => '<!-- wp:pediment/step {"title":"T"} /-->',
			'pediment/testimonial-grid' => '<!-- wp:pediment/testimonial {"quote":"Q"} /-->',
		);
		foreach ( $inner as $name => $child ) {
			$out[ "{$name} raw only" ] = array( $name, "<!-- wp:{$name} -->\n<p>Raw paragraph</p>\n<!-- /wp:{$name} -->" );
			$out[ "{$name} mixed" ]    = array( $name, "<!-- wp:{$name} -->\n{$child}\n<p>Raw tail</p>\n<!-- /wp:{$name} -->" );
		}
		return $out;
	}

	/**
	 * @dataProvider containers
	 */
	public function test_raw_html_in_a_legacy_container_refuses_the_conversion( string $name, string $markup ): void {
		$page   = "<!-- wp:paragraph -->\n<p>Before</p>\n<!-- /wp:paragraph -->\n\n{$markup}";
		$result = $this->convert( $page );
		$this->assertCount( 1, $result->problems );
		$this->assertStringStartsWith( "{$name} holds HTML outside blocks; edit it into blocks first", $result->problems[0] );
		$this->assertStringContainsString( 'Raw', $result->problems[0], 'the problem quotes the HTML' );
		$this->assertSame( $page, $result->markup, 'a refused conversion returns the input unchanged' );
		foreach ( $result->notes as $note ) {
			$this->assertStringNotContainsString( 'rendered nothing', $note );
		}
	}

	public function test_whitespace_between_raw_blocks_is_not_raw_html(): void {
		$result = $this->convert( "<!-- wp:pediment/prose -->\n\n\t<!-- wp:paragraph -->\n<p>x</p>\n<!-- /wp:paragraph -->\n \n<!-- /wp:pediment/prose -->" );
		$this->assertSame( array(), $result->problems );
		$this->assertTrue( $result->changed() );
	}

	public function test_a_delimiter_with_other_whitespace_still_converts(): void {
		$result = $this->convert( "<!--\n\twp:pediment/pull-quote {\"quote\":\"Q\"} /-->" );
		$this->assertSame( array( 'pediment/pull-quote' ), $result->converted );
		$this->assertStringContainsString( 'is-style-pull-quote', $result->markup );
	}

	public function test_a_mapped_legacy_block_left_in_the_output_is_a_problem(): void {
		$markup = '<!-- wp:pediment/pull-quote __PEDIMENT_PHP_0__ /-->';
		$result = $this->convert( $markup );
		$this->assertSame( array( 'pediment/pull-quote is still in the markup after conversion (its block comment does not parse); convert it by hand' ), $result->problems );
		$this->assertSame( $markup, $result->markup );
	}

	public function test_a_legacy_block_without_a_mapper_is_not_a_problem(): void {
		$converter = new Converter( array( new PullQuoteMapper() ) );
		$result    = $converter->convert( "<!-- wp:pediment/prose -->\n<!-- wp:pediment/pull-quote {\"quote\":\"Q\"} /-->\n<!-- /wp:pediment/prose -->", new FakeMedia() );
		$this->assertSame( array(), $result->problems );
		$this->assertSame( array( 'pediment/prose' ), $result->unmapped );
	}

}
