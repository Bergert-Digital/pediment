<?php

class FaqTest extends WP_UnitTestCase {
	public function test_faq_container_wraps_items() {
		$html = do_blocks(
			'<!-- wp:starter/faq -->' .
			'<!-- wp:starter/faq-item {"question":"Q1","answer":"A1"} /-->' .
			'<!-- wp:starter/faq-item {"question":"Q2","answer":"A2"} /-->' .
			'<!-- /wp:starter/faq -->'
		);
		$this->assertStringContainsString( 'starter-faq', $html );
		$this->assertStringContainsString( 'Q1', $html );
		$this->assertStringContainsString( 'A1', $html );
		$this->assertStringContainsString( 'Q2', $html );
	}

	public function test_faq_item_uses_details_summary() {
		$html = do_blocks( '<!-- wp:starter/faq-item {"question":"Hi","answer":"Hey"} /-->' );
		$this->assertStringContainsString( '<details', $html );
		$this->assertStringContainsString( '<summary', $html );
		$this->assertStringContainsString( 'Hi', $html );
		$this->assertStringContainsString( 'Hey', $html );
	}
}
