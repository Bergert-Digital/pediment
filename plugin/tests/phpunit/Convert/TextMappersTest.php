<?php

require_once __DIR__ . '/FakeMedia.php';

use Pediment\Convert\Converter;
use Pediment\Convert\Result;

/**
 * section-head, prose and pull-quote with valid and empty attributes.
 */
class TextMappersTest extends WP_UnitTestCase {

	private function convert( string $markup ): Result {
		return Converter::withDefaults()->convert( $markup, new FakeMedia() );
	}

	public function test_section_head_defaults_to_centre_and_h2(): void {
		$block = parse_blocks( $this->convert( '<!-- wp:pediment/section-head {"headline":"Hi"} /-->' )->markup )[0];
		$this->assertSame( 'core/group', $block['blockName'] );
		$this->assertSame( 'var:preset|spacing|60', $block['attrs']['style']['spacing']['margin']['bottom'] );
		$heading = $block['innerBlocks'][0];
		$this->assertSame( array( 'textAlign' => 'center' ), $heading['attrs'], 'h2 is core default, so no level attribute' );
		$this->assertStringContainsString( '<h2 class="wp-block-heading has-text-align-center">Hi</h2>', $heading['innerHTML'] );
	}

	public function test_empty_section_head_prose_and_pull_quote_are_removed(): void {
		$result = $this->convert( "<!-- wp:pediment/section-head /-->\n\n<!-- wp:pediment/prose -->\n<!-- /wp:pediment/prose -->\n\n<!-- wp:pediment/pull-quote {\"citation\":\"Only a citation\"} /-->" );
		$this->assertSame( "\n\n\n\n", $result->markup );
		$this->assertSame(
			array( 'pediment/section-head: rendered nothing, removed', 'pediment/prose: rendered nothing, removed', 'pediment/pull-quote: rendered nothing, removed' ),
			$result->notes
		);
		$this->assertSame( array( 'pediment/section-head', 'pediment/prose', 'pediment/pull-quote' ), $result->converted );
	}

	public function test_pull_quote_without_citation_has_no_cite(): void {
		$markup = $this->convert( '<!-- wp:pediment/pull-quote {"quote":"Q"} /-->' )->markup;
		$this->assertStringContainsString( '<!-- /wp:paragraph --></blockquote>', $markup );
		$this->assertStringNotContainsString( '<cite>', $markup );
	}

	public function test_list_view_name_and_lock_are_carried_over(): void {
		$markup = '<!-- wp:pediment/prose {"lock":{"move":true,"remove":true},"metadata":{"name":"Imprint text"}} -->' . "\n" . '<!-- wp:paragraph -->' . "\n" . '<p>x</p>' . "\n" . '<!-- /wp:paragraph -->' . "\n" . '<!-- /wp:pediment/prose -->';
		$attrs  = parse_blocks( $this->convert( $markup )->markup )[0]['attrs'];
		$this->assertSame(
			array(
				'move'   => true,
				'remove' => true,
			),
			$attrs['lock']
		);
		$this->assertSame( array( 'name' => 'Imprint text' ), $attrs['metadata'] );
	}

	public function test_a_hidden_legacy_block_stays_hidden_and_keeps_its_metadata(): void {
		$result = $this->convert( '<!-- wp:pediment/cta {"title":"Hidden offer","metadata":{"blockVisibility":false,"patternName":"site/offer"}} /-->' );
		$attrs  = parse_blocks( $result->markup )[0]['attrs'];
		$this->assertSame(
			array(
				'name'            => 'Call to action',
				'blockVisibility' => false,
				'patternName'     => 'site/offer',
			),
			$attrs['metadata']
		);
		$this->assertSame( '', trim( do_blocks( $result->markup ) ), 'WordPress 6.9 hides the converted block too' );
	}

	public function test_block_bindings_are_dropped_and_noted(): void {
		$result = $this->convert( '<!-- wp:pediment/pull-quote {"quote":"Q","metadata":{"name":"Motto","bindings":{"quote":{"source":"core/post-meta","args":{"key":"motto"}}}}} /-->' );
		$this->assertSame( array( 'name' => 'Motto' ), parse_blocks( $result->markup )[0]['attrs']['metadata'] );
		$this->assertContains( 'pediment/pull-quote: block bindings dropped, they named legacy attributes', $result->notes );
	}
}
