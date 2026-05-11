<?php

class PullQuoteTest extends WP_UnitTestCase {
	public function test_renders_quote_and_citation() {
		$html = do_blocks( '<!-- wp:starter/pull-quote {"quote":"To be or not to be","citation":"Hamlet"} /-->' );
		$this->assertStringContainsString( '<blockquote', $html );
		$this->assertStringContainsString( 'To be or not to be', $html );
		$this->assertStringContainsString( '<cite', $html );
		$this->assertStringContainsString( 'Hamlet', $html );
	}

	public function test_omits_cite_when_citation_empty() {
		$html = do_blocks( '<!-- wp:starter/pull-quote {"quote":"Just a quote","citation":""} /-->' );
		$this->assertStringContainsString( 'Just a quote', $html );
		$this->assertStringNotContainsString( '<cite', $html );
	}
}
