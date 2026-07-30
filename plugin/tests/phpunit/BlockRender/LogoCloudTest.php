<?php

class LogoCloudTest extends WP_UnitTestCase {
	public function test_renders_caption_and_inner_images() {
		$html = do_blocks(
			'<!-- wp:pediment/logo-cloud {"caption":"Trusted by leaders"} -->' .
			'<!-- wp:image {"className":"x"} --><figure class="wp-block-image x"><img src="/a.png" alt="Acme"/></figure><!-- /wp:image -->' .
			'<!-- /wp:pediment/logo-cloud -->'
		);
		$this->assertStringContainsString( 'starter-logo-cloud', $html );
		$this->assertStringContainsString( 'starter-logo-cloud__caption', $html );
		$this->assertStringContainsString( 'Trusted by leaders', $html );
		$this->assertStringContainsString( '/a.png', $html );
	}

	public function test_omits_caption_when_empty() {
		$html = do_blocks( '<!-- wp:pediment/logo-cloud --><!-- /wp:pediment/logo-cloud -->' );
		$this->assertStringContainsString( 'starter-logo-cloud', $html );
		$this->assertStringNotContainsString( 'starter-logo-cloud__caption', $html );
	}
}
