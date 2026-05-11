<?php

class AdminPageTest extends WP_UnitTestCase {
	public function test_settings_are_registered() {
		$this->setExpectedIncorrectUsage( 'wp_add_privacy_policy_content' );
		ob_start();
		@do_action( 'admin_init' );
		ob_end_clean();
		global $allowed_options, $new_allowed_options;
		$registered = isset( $allowed_options['starter_brand_group'] ) ? $allowed_options['starter_brand_group'] : ( $new_allowed_options['starter_brand_group'] ?? array() );
		$this->assertContains( \Starter\Brand::OPTION, (array) $registered );
	}

	public function test_admin_menu_is_registered() {
		wp_set_current_user( $this->factory->user->create( array( 'role' => 'administrator' ) ) );
		global $menu, $submenu, $_wp_submenu_nopriv, $_wp_menu_nopriv;
		$menu                = array();
		$submenu             = array( 'options-general.php' => array() );
		$_wp_submenu_nopriv  = array();
		$_wp_menu_nopriv     = array();
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		do_action( 'admin_menu' );
		$found = false;
		foreach ( $submenu['options-general.php'] ?? array() as $item ) {
			if ( 'starter-brand' === $item[2] ) {
				$found = true;
				break;
			}
		}
		$this->assertTrue( $found, 'Brand Settings submenu should be registered under Settings.' );
	}

	public function test_sanitize_callback_coerces_social_links_into_clean_array() {
		$sanitized = starter_brand_sanitize(
			array(
				'brand_name'   => '  Acme  ',
				'social_links' => array(
					array( 'platform' => 'twitter', 'url' => 'https://x.com/acme' ),
					array( 'platform' => '',        'url' => '' ),
					array( 'platform' => 'github',  'url' => 'not a url' ),
				),
			)
		);
		$this->assertSame( 'Acme', $sanitized['brand_name'] );
		$this->assertCount( 1, $sanitized['social_links'] );
		$this->assertSame( 'twitter', $sanitized['social_links'][0]['platform'] );
	}
}
