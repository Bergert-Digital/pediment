<?php

class ContactFormBlockTest extends WP_UnitTestCase {
	public function test_renders_required_fields() {
		$html = do_blocks( '<!-- wp:pediment/contact-form /-->' );
		$this->assertStringContainsString( 'name="name"', $html );
		$this->assertStringContainsString( 'name="email"', $html );
		$this->assertStringContainsString( 'name="message"', $html );
	}

	public function test_includes_honeypot_and_timestamp() {
		$html = do_blocks( '<!-- wp:pediment/contact-form /-->' );
		$this->assertStringContainsString( 'name="hp_field"', $html );
		$this->assertStringContainsString( 'name="_t"', $html );
	}

	public function test_honeypot_field_is_visually_hidden() {
		$html = do_blocks( '<!-- wp:pediment/contact-form /-->' );
		$this->assertMatchesRegularExpression( '/aria-hidden="true"[^>]*>[^<]*<label[^>]*>[^<]*<input[^>]*name="hp_field"/', $html );
	}

	public function test_optionally_includes_phone() {
		$html = do_blocks( '<!-- wp:pediment/contact-form {"includePhone":true} /-->' );
		$this->assertStringContainsString( 'name="phone"', $html );
	}

	public function test_omits_phone_by_default() {
		$html = do_blocks( '<!-- wp:pediment/contact-form /-->' );
		$this->assertStringNotContainsString( 'name="phone"', $html );
	}
}
