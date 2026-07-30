<?php

class RetentionTest extends WP_UnitTestCase {
	public function test_cleanup_deletes_old_keeps_recent() {
		do_action( 'init' );

		$old = self::factory()->post->create(
			array(
				'post_type'     => PEDIMENT_FORM_CPT,
				'post_date_gmt' => gmdate( 'Y-m-d H:i:s', strtotime( '-200 days' ) ),
				'post_date'     => gmdate( 'Y-m-d H:i:s', strtotime( '-200 days' ) ),
			)
		);
		$new = self::factory()->post->create( array( 'post_type' => PEDIMENT_FORM_CPT ) );

		pediment_form_cleanup();

		$this->assertNull( get_post( $old ) );
		$this->assertNotNull( get_post( $new ) );
		wp_delete_post( $new, true );
	}

	public function test_retention_filter_zero_disables_purge() {
		do_action( 'init' );
		$old = self::factory()->post->create(
			array(
				'post_type'     => PEDIMENT_FORM_CPT,
				'post_date_gmt' => gmdate( 'Y-m-d H:i:s', strtotime( '-200 days' ) ),
				'post_date'     => gmdate( 'Y-m-d H:i:s', strtotime( '-200 days' ) ),
			)
		);
		add_filter( 'pediment_form_retention_days', '__return_zero' );

		pediment_form_cleanup();

		$this->assertNotNull( get_post( $old ) );
		remove_filter( 'pediment_form_retention_days', '__return_zero' );
		wp_delete_post( $old, true );
	}
}
