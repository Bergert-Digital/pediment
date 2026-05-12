<?php

class CleanupCronTest extends WP_UnitTestCase {
	public function set_up(): void {
		parent::set_up();
		do_action( 'init' );
	}

	public function test_cleanup_deletes_submissions_older_than_90_days() {
		$old_date = gmdate( 'Y-m-d H:i:s', strtotime( '-91 days' ) );
		$new_date = gmdate( 'Y-m-d H:i:s', strtotime( '-1 day' ) );

		$old_id = wp_insert_post(
			array(
				'post_type'     => STARTER_CONTACT_CPT,
				'post_status'   => 'publish',
				'post_title'    => 'old',
				'post_date'     => $old_date,
				'post_date_gmt' => $old_date,
			)
		);
		$new_id = wp_insert_post(
			array(
				'post_type'     => STARTER_CONTACT_CPT,
				'post_status'   => 'publish',
				'post_title'    => 'new',
				'post_date'     => $new_date,
				'post_date_gmt' => $new_date,
			)
		);

		starter_contact_cleanup();

		$this->assertNull( get_post( $old_id ) );
		$this->assertNotNull( get_post( $new_id ) );

		wp_delete_post( $new_id, true );
	}

	public function test_cron_is_scheduled_after_activation_hook() {
		starter_contact_schedule_cleanup();
		$this->assertNotFalse( wp_next_scheduled( STARTER_CONTACT_CRON_HOOK ) );
		wp_clear_scheduled_hook( STARTER_CONTACT_CRON_HOOK );
	}
}
