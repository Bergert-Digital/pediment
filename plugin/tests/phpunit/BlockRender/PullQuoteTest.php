<?php

class PullQuoteTest extends WP_UnitTestCase {
	private function render( array $attrs ): string {
		return do_blocks( '<!-- wp:pediment/pull-quote ' . wp_json_encode( $attrs ) . ' /-->' );
	}

	public function test_renders_quote_and_citation() {
		$html = $this->render( array( 'quote' => 'To be or not to be', 'citation' => 'Hamlet' ) );
		$this->assertStringContainsString( '<blockquote', $html );
		$this->assertStringContainsString( 'To be or not to be', $html );
		$this->assertStringContainsString( '<cite', $html );
		$this->assertStringContainsString( 'Hamlet', $html );
	}

	public function test_omits_cite_when_citation_empty() {
		$html = $this->render( array( 'quote' => 'Just a quote', 'citation' => '' ) );
		$this->assertStringContainsString( 'Just a quote', $html );
		$this->assertStringNotContainsString( '<cite', $html );
	}

	public function test_returns_empty_when_quote_missing() {
		$html = $this->render( array( 'quote' => '' ) );
		$this->assertStringNotContainsString( 'starter-pull-quote', $html );
	}

	public function test_block_json_has_no_testimonial_variant() {
		$path = dirname( __DIR__, 3 ) . '/src/blocks/pull-quote/block.json';
		$data = json_decode( file_get_contents( $path ), true );
		$this->assertArrayNotHasKey( 'variant', $data['attributes'] );
		$this->assertArrayNotHasKey( 'authorName', $data['attributes'] );
		$this->assertArrayNotHasKey( 'avatarId', $data['attributes'] );
		$this->assertStringNotContainsStringIgnoringCase( 'testimonial', (string) $data['description'] );
	}

	public function test_stale_testimonial_attrs_render_as_plain_quote() {
		// Content authored before the cleanup must not error and must show the quote.
		$html = $this->render( array(
			'variant'    => 'testimonial',
			'quote'      => 'Legacy quote survives.',
			'authorName' => 'Old Author',
		) );
		$this->assertStringContainsString( 'Legacy quote survives.', $html );
		$this->assertStringNotContainsString( '<figure', $html );
		$this->assertStringNotContainsString( 'starter-pull-quote__by', $html );
	}

	public function test_variants_helper_is_gone() {
		$this->assertFalse( function_exists( 'pediment_pull_quote_variants' ) );
	}
}
