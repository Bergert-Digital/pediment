<?php

class RetentionSettingTest extends WP_UnitTestCase {
	public function tear_down(): void {
		delete_option( PEDIMENT_FORM_RETENTION_OPTION );
		remove_all_filters( 'pediment_form_retention_days' );
		parent::tear_down();
	}

	public function test_default_is_ninety_days() {
		$this->assertSame( 90, pediment_form_retention_days() );
	}

	public function test_saved_option_overrides_default() {
		update_option( PEDIMENT_FORM_RETENTION_OPTION, 30 );
		$this->assertSame( 30, pediment_form_retention_days() );
	}

	public function test_filter_overrides_option() {
		update_option( PEDIMENT_FORM_RETENTION_OPTION, 30 );
		add_filter( 'pediment_form_retention_days', fn() => 7 );
		$this->assertSame( 7, pediment_form_retention_days() );
	}

	public function test_zero_keeps_forever_and_cleanup_is_noop() {
		update_option( PEDIMENT_FORM_RETENTION_OPTION, 0 );
		$old = self::factory()->post->create(
			array(
				'post_type' => PEDIMENT_FORM_CPT,
				'post_date' => '2000-01-01 00:00:00',
			)
		);
		pediment_form_cleanup();
		$this->assertNotNull( get_post( $old ) );
	}
}
