<?php

class RenderTest extends WP_UnitTestCase {
	public function test_mega_link_renders_icon_label_description_href() {
		$html = do_blocks(
			'<!-- wp:starter/mega-link {"label":"Pricing","url":"/pricing","description":"Plans & costs","icon":"tag"} /-->'
		);
		$this->assertStringContainsString( 'href="/pricing"', $html );
		$this->assertStringContainsString( 'Pricing', $html );
		$this->assertStringContainsString( 'Plans &amp; costs', $html );
		$this->assertStringContainsString( '#ph-tag', $html );
		$this->assertStringContainsString( 'starter-mega-link', $html );
	}

	public function test_mega_link_omits_empty_icon_and_description() {
		$html = do_blocks( '<!-- wp:starter/mega-link {"label":"Docs","url":"/docs"} /-->' );
		$this->assertStringContainsString( 'href="/docs"', $html );
		$this->assertStringNotContainsString( '<svg', $html );
		$this->assertStringNotContainsString( 'starter-mega-link__desc', $html );
	}

	public function test_mega_link_renders_nothing_without_label_and_url() {
		$html = do_blocks( '<!-- wp:starter/mega-link /-->' );
		$this->assertStringNotContainsString( 'starter-mega-link', $html );
	}

	public function test_mega_column_renders_heading_and_inner_links() {
		$html = do_blocks(
			'<!-- wp:starter/mega-column {"heading":"Product"} -->' .
			'<!-- wp:starter/mega-link {"label":"Pricing","url":"/pricing"} /-->' .
			'<!-- /wp:starter/mega-column -->'
		);
		$this->assertStringContainsString( 'starter-mega-column', $html );
		$this->assertStringContainsString( 'Product', $html );
		$this->assertStringContainsString( 'href="/pricing"', $html );
	}

	public function test_mega_column_omits_empty_heading() {
		$html = do_blocks(
			'<!-- wp:starter/mega-column -->' .
			'<!-- wp:starter/mega-link {"label":"Docs","url":"/docs"} /-->' .
			'<!-- /wp:starter/mega-column -->'
		);
		$this->assertStringNotContainsString( 'starter-mega-column__heading', $html );
		$this->assertStringContainsString( 'href="/docs"', $html );
	}
}
