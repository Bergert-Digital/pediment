<?php
/**
 * Tests for form submission storage CPT and persistence.
 *
 * @package Pediment
 */

namespace Pediment\Tests\Forms;

class StorageTest extends \WP_UnitTestCase {
	public function test_cpt_registered() {
		do_action( 'init' );
		$this->assertTrue( post_type_exists( PEDIMENT_FORM_CPT ) );
		$pt = get_post_type_object( PEDIMENT_FORM_CPT );
		$this->assertFalse( $pt->public );
		$this->assertTrue( $pt->show_ui );
	}

	public function test_submission_persists_row_with_meta() {
		do_action( 'init' );

		$submission = array(
			'post_id'     => 0,
			'form_key'    => 'abc123abc123',
			'destination' => 'sales',
			'fields'      => array(
				'name'  => array(
					'label' => 'Name',
					'value' => 'Alice',
				),
				'email' => array(
					'label' => 'Email',
					'value' => 'alice@example.com',
				),
			),
		);
		do_action( 'pediment_form_submitted', $submission, null );

		$posts = get_posts(
			array(
				'post_type'   => PEDIMENT_FORM_CPT,
				'numberposts' => -1,
				'post_status' => 'any',
			)
		);
		$this->assertCount( 1, $posts );

		$id = $posts[0]->ID;
		$this->assertSame( 'sales', get_post_meta( $id, '_destination', true ) );
		$this->assertSame( 'pending', get_post_meta( $id, '_delivery_status', true ) );
		$this->assertStringContainsString( 'alice@example.com', $posts[0]->post_content );

		$stored = json_decode( (string) get_post_meta( $id, '_fields', true ), true );
		$this->assertSame( 'Alice', $stored['name']['value'] );

		// The admin "Details" column surfaces the field values so submissions
		// are readable in the list (and the e2e flow can find the email there).
		$summary = pediment_form_fields_summary( $id );
		$this->assertStringContainsString( 'Email: alice@example.com', $summary );
		$this->assertStringContainsString( 'Name: Alice', $summary );

		ob_start();
		do_action( 'manage_' . PEDIMENT_FORM_CPT . '_posts_custom_column', 'fields', $id );
		$column = ob_get_clean();
		$this->assertStringContainsString( 'alice@example.com', $column );

		wp_delete_post( $id, true );
	}

	public function test_fields_summary_is_empty_without_stored_fields() {
		$id = self::factory()->post->create( array( 'post_type' => PEDIMENT_FORM_CPT ) );
		$this->assertSame( '', pediment_form_fields_summary( $id ) );
		wp_delete_post( $id, true );
	}
}
