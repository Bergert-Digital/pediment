<?php

class FormsTabsTest extends WP_UnitTestCase {
	public function set_up(): void {
		parent::set_up();
		$GLOBALS['pediment_settings_tabs'] = array();
		do_action( 'admin_menu' );
	}

	public function tear_down(): void {
		unset( $GLOBALS['pediment_settings_tabs'] );
		delete_option( PEDIMENT_FORM_DESTINATIONS_OPTION );
		delete_transient( pediment_form_repopulate_key() );
		parent::tear_down();
	}

	public function test_forms_registers_three_tabs_in_order() {
		// Since the forms engine, the seeding tab, and the AI settings tab now
		// ship in the same plugin, admin_menu also mounts 'seed' (priority 20,
		// so it sorts between 'general' and 'destinations') and 'ai' via
		// Pediment\Settings\Page::addMenu() (priority 100, so it sorts last).
		$tabs = pediment_settings_get_tabs();
		$this->assertSame( array( 'general', 'seed', 'destinations', 'secrets', 'ai' ), array_keys( $tabs ) );
	}

	public function test_forms_tab_labels() {
		$tabs = pediment_settings_get_tabs();
		$this->assertSame( 'General', $tabs['general']['label'] );
		$this->assertSame( 'Form Destinations', $tabs['destinations']['label'] );
		$this->assertSame( 'Secrets', $tabs['secrets']['label'] );
	}

	public function test_each_tab_render_is_callable() {
		$tabs = pediment_settings_get_tabs();
		foreach ( array( 'general', 'destinations', 'secrets' ) as $id ) {
			$this->assertTrue( is_callable( $tabs[ $id ]['render'] ), "$id render not callable" );
		}
	}

	public function test_form_values_blank_for_empty_id() {
		$v = pediment_form_destination_form_values( '' );
		$this->assertFalse( $v['is_edit'] );
		$this->assertSame( '', $v['id'] );
		$this->assertSame( 'POST', $v['method'] );
		$this->assertSame( 'application/json', $v['content_type'] );
		$this->assertSame( array(), $v['headers'] );
	}

	public function test_form_values_blank_for_unknown_id() {
		$v = pediment_form_destination_form_values( 'does_not_exist' );
		$this->assertFalse( $v['is_edit'] );
		$this->assertSame( '', $v['id'] );
	}

	public function test_form_values_prefills_stored_destination() {
		update_option(
			PEDIMENT_FORM_DESTINATIONS_OPTION,
			array(
				array(
					'id'            => 'brevo',
					'label'         => 'Brevo main',
					'method'        => 'POST',
					'url'           => 'https://api.brevo.com/v3/smtp/email',
					'content_type'  => 'application/json',
					'headers'       => array( 'api-key' => '{{ secret:brevo_api_key }}' ),
					'body_template' => '{"x":"{{ all_fields }}"}',
					'secret_refs'   => array( 'brevo_api_key' ),
				),
			)
		);
		$v = pediment_form_destination_form_values( 'brevo' );
		$this->assertTrue( $v['is_edit'] );
		$this->assertSame( 'brevo', $v['id'] );
		$this->assertSame( 'Brevo main', $v['label'] );
		$this->assertSame( 'https://api.brevo.com/v3/smtp/email', $v['url'] );
		$this->assertSame( array( 'api-key' => '{{ secret:brevo_api_key }}' ), $v['headers'] );
		delete_option( PEDIMENT_FORM_DESTINATIONS_OPTION );
	}

	public function test_form_values_repopulates_from_stash_and_consumes_it() {
		pediment_form_stash_destination(
			array(
				'id'            => '',
				'label'         => 'Half typed',
				'method'        => 'PUT',
				'url'           => 'https://example.com/hook',
				'content_type'  => 'application/json',
				'headers'       => array( 'X-Test' => 'v' ),
				'body_template' => '{"a":1}',
			)
		);
		$v = pediment_form_destination_form_values( '' );
		$this->assertFalse( $v['is_edit'] );
		$this->assertSame( 'Half typed', $v['label'] );
		$this->assertSame( 'PUT', $v['method'] );
		$this->assertSame( array( 'X-Test' => 'v' ), $v['headers'] );

		// Stash is one-shot: a second read falls back to blank defaults.
		$again = pediment_form_destination_form_values( '' );
		$this->assertSame( '', $again['label'] );
	}

	public function test_form_values_stash_is_edit_when_id_is_stored() {
		update_option(
			PEDIMENT_FORM_DESTINATIONS_OPTION,
			array( array( 'id' => '2', 'label' => 'Stored two' ) )
		);
		pediment_form_stash_destination( array( 'id' => '2', 'label' => 'Edited two' ) );
		$v = pediment_form_destination_form_values( '2' );
		$this->assertTrue( $v['is_edit'] );
		$this->assertSame( 'Edited two', $v['label'] );
	}
}
