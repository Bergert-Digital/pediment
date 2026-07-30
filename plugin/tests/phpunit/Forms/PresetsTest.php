<?php

class PresetsTest extends WP_UnitTestCase {
	public function test_ships_core_providers() {
		$presets = pediment_form_presets();
		foreach ( array( 'brevo', 'resend', 'mailgun', 'n8n', 'slack', 'custom' ) as $id ) {
			$this->assertArrayHasKey( $id, $presets, "missing preset: {$id}" );
		}
	}

	public function test_each_preset_has_required_shape() {
		foreach ( pediment_form_presets() as $id => $p ) {
			$this->assertArrayHasKey( 'label', $p, $id );
			$this->assertArrayHasKey( 'method', $p, $id );
			$this->assertArrayHasKey( 'content_type', $p, $id );
			$this->assertArrayHasKey( 'body_template', $p, $id );
			$this->assertArrayHasKey( 'headers', $p, $id );
			$this->assertIsArray( $p['headers'], $id );
		}
	}

	public function test_provider_presets_use_https_urls() {
		foreach ( pediment_form_presets() as $id => $p ) {
			if ( 'custom' === $id || '' === (string) $p['url'] ) {
				continue;
			}
			$this->assertStringStartsWith( 'https://', (string) $p['url'], $id );
		}
	}

	public function test_filter_can_add_a_preset() {
		add_filter(
			'pediment_form_presets',
			fn( $p ) => $p + array( 'mine' => array( 'label' => 'Mine', 'method' => 'POST', 'url' => 'https://x.test', 'headers' => array(), 'content_type' => 'application/json', 'body_template' => '{}', 'secret_refs' => array() ) )
		);
		$this->assertArrayHasKey( 'mine', pediment_form_presets() );
		remove_all_filters( 'pediment_form_presets' );
	}
}
