<?php

/**
 * The default destination is set from the Form Destinations tab: a "Make
 * default" button per row and a "Use as default destination" checkbox in the
 * add / edit form. Deleting the default clears it.
 */
class DefaultDestinationTest extends WP_UnitTestCase {
	public function set_up(): void {
		parent::set_up();
		update_option(
			PEDIMENT_FORM_DESTINATIONS_OPTION,
			array(
				array(
					'id'       => '1',
					'type'     => 'email',
					'label'    => 'Office',
					'to'       => array( 'office@example.com' ),
					'seed_key' => 'contact-mail',
				),
				array(
					'id'    => '2',
					'type'  => 'email',
					'label' => 'Sales',
					'to'    => array( 'sales@example.com' ),
				),
			)
		);
		delete_option( PEDIMENT_FORM_DEFAULT_DEST_OPTION );
	}

	public function tear_down(): void {
		delete_option( PEDIMENT_FORM_DESTINATIONS_OPTION );
		delete_option( PEDIMENT_FORM_DEFAULT_DEST_OPTION );
		delete_transient( pediment_form_repopulate_key() );
		wp_set_current_user( 0 );
		parent::tear_down();
	}

	private function render( string $callback ): string {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		ob_start();
		$callback();
		return (string) ob_get_clean();
	}

	public function test_default_id_resolves_a_seed_key() {
		$this->assertSame( '', pediment_form_default_destination_id() );
		update_option( PEDIMENT_FORM_DEFAULT_DEST_OPTION, 'contact-mail' );
		$this->assertSame( '1', pediment_form_default_destination_id() );
	}

	public function test_checked_box_makes_the_destination_default() {
		update_option( PEDIMENT_FORM_DEFAULT_DEST_OPTION, '1' );
		pediment_form_apply_default_choice( '2', true );
		$this->assertSame( '2', get_option( PEDIMENT_FORM_DEFAULT_DEST_OPTION ) );
	}

	public function test_unchecked_box_on_the_default_clears_it() {
		update_option( PEDIMENT_FORM_DEFAULT_DEST_OPTION, 'contact-mail' );
		pediment_form_apply_default_choice( '1', false );
		$this->assertSame( '', get_option( PEDIMENT_FORM_DEFAULT_DEST_OPTION ) );
	}

	public function test_unchecked_box_on_another_destination_keeps_the_default() {
		update_option( PEDIMENT_FORM_DEFAULT_DEST_OPTION, '1' );
		pediment_form_apply_default_choice( '2', false );
		$this->assertSame( '1', get_option( PEDIMENT_FORM_DEFAULT_DEST_OPTION ) );
	}

	public function test_deleting_the_default_clears_it() {
		update_option( PEDIMENT_FORM_DEFAULT_DEST_OPTION, 'contact-mail' );
		pediment_form_delete_destination( '1' );
		$this->assertSame( '', get_option( PEDIMENT_FORM_DEFAULT_DEST_OPTION ) );
	}

	public function test_deleting_another_destination_keeps_the_default() {
		update_option( PEDIMENT_FORM_DEFAULT_DEST_OPTION, '1' );
		pediment_form_delete_destination( '2' );
		$this->assertSame( '1', get_option( PEDIMENT_FORM_DEFAULT_DEST_OPTION ) );
	}

	public function test_new_destination_box_starts_checked_only_without_a_default() {
		$this->assertTrue( pediment_form_destination_form_values( '' )['make_default'] );
		update_option( PEDIMENT_FORM_DEFAULT_DEST_OPTION, '1' );
		$this->assertFalse( pediment_form_destination_form_values( '' )['make_default'] );
	}

	public function test_edit_box_shows_whether_the_destination_is_default() {
		update_option( PEDIMENT_FORM_DEFAULT_DEST_OPTION, 'contact-mail' );
		$this->assertTrue( pediment_form_destination_form_values( '1' )['make_default'] );
		$this->assertFalse( pediment_form_destination_form_values( '2' )['make_default'] );
	}

	public function test_stash_keeps_the_box_state_across_a_test_send() {
		update_option( PEDIMENT_FORM_DEFAULT_DEST_OPTION, '1' );
		pediment_form_stash_destination(
			array(
				'id'           => '2',
				'type'         => 'email',
				'label'        => 'Sales',
				'make_default' => true,
			)
		);
		$this->assertTrue( pediment_form_destination_form_values( '2' )['make_default'] );
	}

	public function test_destinations_tab_marks_the_default_row() {
		update_option( PEDIMENT_FORM_DEFAULT_DEST_OPTION, '1' );
		$html = $this->render( 'pediment_form_render_destinations_tab' );

		$this->assertSame( 2, substr_count( $html, 'value="pediment_form_make_default_destination"' ) );
		$this->assertStringContainsString( 'name="_wpnonce"', $html );
		// The default row hides its button (space kept) and shows the label.
		$this->assertSame( 1, substr_count( $html, ' disabled tabindex="-1">Make default' ) );
		$this->assertSame( 1, substr_count( $html, '<strong>Default</strong>' ) );
		$this->assertStringContainsString( 'name="make_default"', $html );
	}

	public function test_general_tab_no_longer_offers_the_default() {
		$html = $this->render( 'pediment_form_render_general_tab' );
		$this->assertStringNotContainsString( 'default_destination', $html );
		$this->assertStringContainsString( 'retention_days', $html );
	}

	public function test_notice_links_to_the_destinations_tab() {
		self::factory()->post->create(
			array(
				'post_type'  => PEDIMENT_FORM_CPT,
				'meta_input' => array( '_delivery_status' => 'no_destination' ),
			)
		);
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		ob_start();
		do_action( 'admin_notices' );
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( esc_url( pediment_settings_page_url( 'destinations' ) ), $html );
		$this->assertStringContainsString( 'Set a default destination', $html );
	}
}
