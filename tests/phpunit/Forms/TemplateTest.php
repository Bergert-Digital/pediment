<?php

class TemplateTest extends WP_UnitTestCase {
	private function context(): array {
		return array(
			'fields' => array(
				'name'    => 'Ada',
				'message' => "Line1\nLine2 \"quoted\"",
			),
			'meta'   => array(
				'post_id'  => '42',
				'page_url' => 'https://example.com/contact',
			),
		);
	}

	public function test_json_scalar_token_is_escaped_and_stays_valid() {
		$tpl  = '{"subject":"From {{ field:name }}","body":"{{ field:message }}"}';
		$out  = pediment_form_render_template( $tpl, $this->context(), 'application/json' );
		$data = json_decode( $out, true );
		$this->assertIsArray( $data, 'rendered body must be valid JSON' );
		$this->assertSame( 'From Ada', $data['subject'] );
		$this->assertSame( "Line1\nLine2 \"quoted\"", $data['body'] );
	}

	public function test_all_fields_becomes_json_object() {
		$tpl  = '{"data":"{{ all_fields }}"}';
		$out  = pediment_form_render_template( $tpl, $this->context(), 'application/json' );
		$data = json_decode( $out, true );
		$this->assertSame( array( 'name' => 'Ada', 'message' => "Line1\nLine2 \"quoted\"" ), $data['data'] );
	}

	public function test_meta_token_resolves() {
		$tpl  = '{"src":"{{ meta:page_url }}"}';
		$out  = pediment_form_render_template( $tpl, $this->context(), 'application/json' );
		$this->assertSame( 'https://example.com/contact', json_decode( $out, true )['src'] );
	}

	public function test_secret_token_resolves_from_store() {
		pediment_form_secret_set( 'api', 'sk-123' );
		$tpl = '{"key":"{{ secret:api }}"}';
		$out = pediment_form_render_template( $tpl, $this->context(), 'application/json' );
		$this->assertSame( 'sk-123', json_decode( $out, true )['key'] );
		pediment_form_secret_set( 'api', '' );
	}

	public function test_form_urlencoded_encodes_values_and_all_fields() {
		$tpl = 'who={{ field:name }}&dump={{ all_fields }}';
		$out = pediment_form_render_template( $tpl, $this->context(), 'application/x-www-form-urlencoded' );
		$this->assertStringContainsString( 'who=Ada', $out );
		$this->assertStringContainsString( 'name=Ada', $out );
		$this->assertStringContainsString( 'message=Line1%0ALine2', $out );
	}

	public function test_header_value_strips_crlf() {
		$ctx = array( 'fields' => array( 'x' => "abc\r\nInjected: bad" ), 'meta' => array() );
		$out = pediment_form_render_header_value( 'Bearer {{ field:x }}', $ctx );
		$this->assertSame( 'Bearer abcInjected: bad', $out );
		$this->assertStringNotContainsString( "\n", $out );
	}

	public function test_unknown_field_resolves_to_empty() {
		$tpl = '{"v":"{{ field:missing }}"}';
		$out = pediment_form_render_template( $tpl, $this->context(), 'application/json' );
		$this->assertSame( '', json_decode( $out, true )['v'] );
	}

	public function test_extract_tokens_lists_each_reference() {
		$tokens = pediment_form_extract_tokens( '{{ field:name }} {{ secret:api }} {{ all_fields }}' );
		$this->assertContains( array( 'type' => 'field', 'name' => 'name' ), $tokens );
		$this->assertContains( array( 'type' => 'secret', 'name' => 'api' ), $tokens );
		$this->assertContains( array( 'type' => 'all_fields', 'name' => '' ), $tokens );
	}
}
