<?php

class FieldsTest extends WP_UnitTestCase {
	private function markup(): string {
		return '<!-- wp:pediment/form -->'
			. '<!-- wp:pediment/form-field {"label":"Full Name","fieldName":"name","required":true} /-->'
			. '<!-- wp:pediment/form-field {"fieldType":"email","label":"Email","fieldName":"email","required":true} /-->'
			. '<!-- wp:pediment/form-field {"fieldType":"select","label":"Plan","fieldName":"plan","options":[{"label":"Basic","value":"basic"},{"label":"Pro","value":"pro"}]} /-->'
			. '<!-- /wp:pediment/form -->';
	}

	public function test_slug_normalizes_and_falls_back() {
		$this->assertSame( 'full_name', pediment_form_slug( 'Full Name!' ) );
		$this->assertSame( 'field', pediment_form_slug( '—' ) );
	}

	public function test_collect_fields_reads_children() {
		$blocks = parse_blocks( $this->markup() );
		$inner  = $blocks[0]['innerBlocks'];
		$fields = pediment_form_collect_fields( $inner );

		$this->assertCount( 3, $fields );
		$this->assertSame( 'name', $fields[0]['name'] );
		$this->assertTrue( $fields[0]['required'] );
		$this->assertSame( 'email', $fields[1]['type'] );
		$this->assertSame( array( 'basic', 'pro' ), $fields[2]['options'] );
	}

	public function test_form_key_is_stable_and_name_derived() {
		$inner  = parse_blocks( $this->markup() )[0]['innerBlocks'];
		$key1   = pediment_form_form_key( pediment_form_collect_fields( $inner ) );
		$key2   = pediment_form_form_key( pediment_form_collect_fields( $inner ) );
		$this->assertSame( $key1, $key2 );
		$this->assertSame( 12, strlen( $key1 ) );
	}

	public function test_find_forms_recurses_into_groups() {
		$wrapped = '<!-- wp:group --><div class="wp-block-group">' . $this->markup() . '</div><!-- /wp:group -->';
		$forms   = pediment_form_find_forms( parse_blocks( $wrapped ) );
		$this->assertCount( 1, $forms );
		$this->assertSame( 'pediment/form', $forms[0]['blockName'] );
	}

	public function test_validate_reports_required_and_type_errors() {
		$fields = pediment_form_collect_fields( parse_blocks( $this->markup() )[0]['innerBlocks'] );

		$errors = pediment_form_validate( $fields, array( 'name' => '', 'email' => 'nope', 'plan' => 'gold' ) );
		$this->assertArrayHasKey( 'name', $errors );  // required
		$this->assertArrayHasKey( 'email', $errors ); // bad email
		$this->assertArrayHasKey( 'plan', $errors );  // not an allowed option

		$ok = pediment_form_validate( $fields, array( 'name' => 'A', 'email' => 'a@b.com', 'plan' => 'pro' ) );
		$this->assertSame( array(), $ok );
	}
}
