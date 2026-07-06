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
}
