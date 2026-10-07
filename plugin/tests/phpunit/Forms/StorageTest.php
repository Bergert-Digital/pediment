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
		// Delivery runs immediately after storage (pediment_form_stored action); the
		// exact delivery status is covered by DeliveryTest — here we just confirm the
		// submission row exists with the correct destination.
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

	/**
	 * Create a stored submission row with the given fields meta.
	 *
	 * @param array<string,mixed>|string|null $fields  Fields array (JSON-encoded), raw string, or null for none.
	 * @param array<string,mixed>             $meta    Extra meta to set.
	 * @param string                          $content post_content.
	 */
	private function make_submission( $fields, array $meta = array(), string $content = '' ): int {
		$id = self::factory()->post->create(
			array(
				'post_type'    => PEDIMENT_FORM_CPT,
				'post_title'   => 'Feedback — 2026-10-05 14:12',
				'post_content' => $content,
				'post_date'    => '2026-10-05 14:12:00',
			)
		);
		if ( is_array( $fields ) ) {
			update_post_meta( $id, '_fields', wp_slash( wp_json_encode( $fields ) ) );
		} elseif ( is_string( $fields ) ) {
			update_post_meta( $id, '_fields', $fields );
		}
		foreach ( $meta as $key => $value ) {
			update_post_meta( $id, $key, $value );
		}
		return $id;
	}

	private function render_box( int $id ): string {
		ob_start();
		pediment_form_render_submission_box( get_post( $id ) );
		return (string) ob_get_clean();
	}

	public function test_details_box_is_registered_on_the_edit_screen() {
		global $wp_meta_boxes;
		do_action( 'init' );
		$id = $this->make_submission( array() );
		do_action( 'add_meta_boxes_' . PEDIMENT_FORM_CPT, get_post( $id ) );
		$this->assertArrayHasKey( 'pediment-submission-details', $wp_meta_boxes[ PEDIMENT_FORM_CPT ]['normal']['high'] );
	}

	public function test_details_box_renders_every_field_escaped() {
		$id   = $this->make_submission(
			array(
				'name'    => array(
					'label' => 'Name <b>',
					'value' => '<script>alert(1)</script>',
				),
				'email'   => array(
					'label' => 'Email',
					'value' => 'alice@example.com',
				),
				'message' => array(
					'label' => 'Message',
					'value' => "Line one\nLine & two",
				),
			)
		);
		$html = $this->render_box( $id );

		$this->assertStringContainsString( 'Name &lt;b&gt;', $html );
		$this->assertStringContainsString( '&lt;script&gt;alert(1)&lt;/script&gt;', $html );
		$this->assertStringNotContainsString( '<script>', $html );
		$this->assertStringNotContainsString( '<b>', $html );
		$this->assertStringContainsString( '<a href="mailto:alice@example.com">alice@example.com</a>', $html );
		$this->assertMatchesRegularExpression( '#Line one<br\s*/?>\s*Line &amp; two#', $html );
		$this->assertStringNotContainsString( '<input', $html );
		$this->assertStringNotContainsString( '<textarea', $html );
	}

	public function test_details_box_shows_meta_rows() {
		$source = self::factory()->post->create( array( 'post_title' => 'Contact page' ) );
		$id     = $this->make_submission(
			array(
				'name' => array(
					'label' => 'Name',
					'value' => 'Alice',
				),
			),
			array(
				'_source_post_id'       => $source,
				'_destination'          => 'sales',
				'_delivery_status'      => 'failed',
				'_delivery_http_status' => '500',
			)
		);
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$html = $this->render_box( $id );

		$this->assertStringContainsString( 'Contact page', $html );
		$this->assertStringContainsString( esc_url( get_edit_post_link( $source, 'raw' ) ), $html );
		$this->assertStringContainsString( 'sales', $html );
		$this->assertStringContainsString( 'Failed (500)', $html );
		$this->assertStringContainsString( '2026', $html );
	}

	public function test_details_box_defaults_destination_and_hides_http_when_sent() {
		$id   = $this->make_submission(
			array(),
			array(
				'_destination'          => '',
				'_delivery_status'      => 'sent',
				'_delivery_http_status' => '200',
			)
		);
		$html = $this->render_box( $id );
		$this->assertStringContainsString( '(default)', $html );
		$this->assertStringContainsString( 'Sent', $html );
		$this->assertStringNotContainsString( '(200)', $html );
	}

	public function test_details_box_falls_back_to_post_content() {
		$content = "Name: Bob <i>\nMessage: Hi\nthere";
		foreach ( array( null, 'not json', '"a string"' ) as $fields ) {
			$id   = $this->make_submission( $fields, array(), $content );
			$html = $this->render_box( $id );
			$this->assertStringContainsString( 'Name: Bob &lt;i&gt;', $html );
			$this->assertMatchesRegularExpression( '#Message: Hi<br\s*/?>\s*there#', $html );
			$this->assertStringNotContainsString( '<i>', $html );
		}
	}

	public function test_details_box_survives_a_deleted_source_post() {
		$source = self::factory()->post->create( array( 'post_title' => 'Gone page' ) );
		$id     = $this->make_submission( array(), array( '_source_post_id' => $source ) );
		wp_delete_post( $source, true );

		$html = $this->render_box( $id );
		$this->assertStringNotContainsString( 'Gone page', $html );
		$this->assertStringContainsString( '#' . $source, $html );
		$this->assertStringNotContainsString( 'post.php?post=' . $source, $html );
	}
}
