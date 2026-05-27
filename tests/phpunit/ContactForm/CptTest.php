<?php

class CptTest extends WP_UnitTestCase {
	public function test_cpt_is_registered_after_init() {
		do_action( 'init' );
		$this->assertTrue( post_type_exists( PEDIMENT_CONTACT_CPT ) );
		$pt = get_post_type_object( PEDIMENT_CONTACT_CPT );
		$this->assertFalse( $pt->public );
		$this->assertTrue( $pt->show_ui );
	}

	public function test_submission_creates_cpt_row() {
		do_action( 'init' );

		do_action(
			'pediment_contact_submitted',
			array(
				'name'    => 'Alice',
				'email'   => 'alice@example.com',
				'phone'   => '555-1234',
				'message' => 'Hello there.',
			),
			null
		);

		$posts = get_posts(
			array(
				'post_type'   => PEDIMENT_CONTACT_CPT,
				'numberposts' => -1,
				'post_status' => 'any',
			)
		);
		$this->assertCount( 1, $posts );

		$post = $posts[0];
		$this->assertStringContainsString( 'Alice', $post->post_title );
		$this->assertSame( 'alice@example.com', get_post_meta( $post->ID, '_email', true ) );
		$this->assertSame( '555-1234', get_post_meta( $post->ID, '_phone', true ) );
		$this->assertStringContainsString( 'Hello there.', $post->post_content );

		wp_delete_post( $post->ID, true );
	}
}
