<?php

class ProseTest extends WP_UnitTestCase {
	public function test_prose_wraps_inner_content() {
		$html = do_blocks(
			'<!-- wp:starter/prose -->' .
			'<!-- wp:paragraph --><p>Hello world.</p><!-- /wp:paragraph -->' .
			'<!-- /wp:starter/prose -->'
		);
		$this->assertStringContainsString( 'starter-prose', $html );
		$this->assertStringContainsString( 'Hello world.', $html );
	}

	public function test_prose_accepts_headings() {
		$html = do_blocks(
			'<!-- wp:starter/prose -->' .
			'<!-- wp:heading --><h2>Section</h2><!-- /wp:heading -->' .
			'<!-- /wp:starter/prose -->'
		);
		$this->assertMatchesRegularExpression( '/<h2[^>]*>Section<\/h2>/', $html );
	}
}
