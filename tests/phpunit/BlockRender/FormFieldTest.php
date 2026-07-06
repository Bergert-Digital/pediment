<?php

class FormFieldTest extends WP_UnitTestCase {
	public function test_text_field_renders_named_input() {
		$html = do_blocks( '<!-- wp:pediment/form-field {"label":"Full Name","fieldName":"name","required":true} /-->' );
		$this->assertStringContainsString( 'name="name"', $html );
		$this->assertStringContainsString( 'data-pediment-field', $html );
		$this->assertStringContainsString( 'required', $html );
		$this->assertStringContainsString( 'Full Name', $html );
	}

	public function test_name_falls_back_to_slugified_label() {
		$html = do_blocks( '<!-- wp:pediment/form-field {"label":"Full Name"} /-->' );
		$this->assertStringContainsString( 'name="full_name"', $html );
	}

	public function test_select_renders_options() {
		$html = do_blocks( '<!-- wp:pediment/form-field {"fieldType":"select","label":"Plan","fieldName":"plan","options":[{"label":"Pro","value":"pro"}]} /-->' );
		$this->assertStringContainsString( '<select', $html );
		$this->assertStringContainsString( 'value="pro"', $html );
		$this->assertStringContainsString( 'Pro', $html );
	}
}
