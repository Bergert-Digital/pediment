<?php

class FormTest extends WP_UnitTestCase {
	private function markup(): string {
		return '<!-- wp:pediment/form {"successMessage":"Thanks!"} -->'
			. '<!-- wp:pediment/form-field {"label":"Name","fieldName":"name","required":true} /-->'
			. '<!-- wp:pediment/form-field {"fieldType":"email","label":"Email","fieldName":"email","required":true} /-->'
			. '<!-- /wp:pediment/form -->';
	}

	public function test_form_wraps_fields_and_emits_metadata() {
		$html = do_blocks( $this->markup() );

		$this->assertStringContainsString( 'wp-block-pediment-form', $html );
		$this->assertStringContainsString( 'pediment-form', $html );
		$this->assertStringContainsString( 'data-form-key="', $html );
		$this->assertStringContainsString( 'data-rest-url="', $html );
		$this->assertStringContainsString( 'name="name"', $html );
		$this->assertStringContainsString( 'name="email"', $html );
		$this->assertStringContainsString( 'name="hp_field"', $html );
		$this->assertStringContainsString( 'name="_t"', $html );
	}

	public function test_form_key_matches_server_derivation() {
		$inner = parse_blocks( $this->markup() )[0]['innerBlocks'];
		$key   = pediment_form_form_key( pediment_form_collect_fields( $inner ) );
		$this->assertStringContainsString( 'data-form-key="' . $key . '"', do_blocks( $this->markup() ) );
	}

	public function test_form_renders_hidden_success_panel_with_defaults() {
		$html = do_blocks( $this->markup() );

		$this->assertMatchesRegularExpression( '/<div class="pediment-form__success" hidden>/', $html );
		$this->assertStringContainsString( '>Message sent</p>', $html );
		$this->assertStringContainsString( '>Thanks!</p>', $html );
		$this->assertStringContainsString( 'class="pediment-form__reset"', $html );
		$this->assertStringNotContainsString( 'data-success=', $html );

		// The heading takes focus and names the message as its description.
		preg_match( '/aria-describedby="([^"]+)"/', $html, $m );
		$this->assertNotEmpty( $m );
		$this->assertStringContainsString( 'id="' . $m[1] . '"', $html );
	}

	public function test_form_success_panel_uses_custom_heading_and_escapes() {
		$html = do_blocks(
			'<!-- wp:pediment/form {"successHeading":"Danke <b>!","successMessage":"We <script>reply</script>"} -->'
			. '<!-- wp:pediment/form-field {"label":"Name","fieldName":"name"} /-->'
			. '<!-- /wp:pediment/form -->'
		);

		$this->assertStringContainsString( '>Danke &lt;b&gt;!</p>', $html );
		$this->assertStringNotContainsString( '<script>', $html );
		$this->assertStringNotContainsString( 'Message sent', $html );
	}

	public function test_form_empty_success_attributes_fall_back() {
		$html = do_blocks(
			'<!-- wp:pediment/form {"successHeading":"","successMessage":""} -->'
			. '<!-- /wp:pediment/form -->'
		);

		$this->assertStringContainsString( '>Message sent</p>', $html );
		$this->assertStringContainsString( '>Thanks — we will be in touch.</p>', $html );
	}
}
