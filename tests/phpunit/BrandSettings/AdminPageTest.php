<?php

class AdminPageTest extends WP_UnitTestCase {
	public function test_settings_are_registered() {
		$this->setExpectedIncorrectUsage( 'wp_add_privacy_policy_content' );
		ob_start();
		@do_action( 'admin_init' );
		ob_end_clean();
		global $allowed_options, $new_allowed_options;
		$registered = isset( $allowed_options['pediment_brand_group'] ) ? $allowed_options['pediment_brand_group'] : ( $new_allowed_options['pediment_brand_group'] ?? array() );
		$this->assertContains( \Pediment\Brand::OPTION, (array) $registered );
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
			if ( 'pediment-brand' === $item[2] ) {
				$found = true;
				break;
			}
		}
		$this->assertTrue( $found, 'Brand Settings submenu should be registered under Settings.' );
	}

	public function test_sanitize_callback_coerces_social_links_into_clean_array() {
		$sanitized = pediment_brand_sanitize(
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

	public function test_filter_added_field_registers_a_settings_field() {
		$cb = static function ( $fields ) {
			$fields['newsletter_form_id'] = array(
				'label'    => 'Newsletter form ID',
				'section'  => 'contact',
				'type'     => 'integer',
				'default'  => 0,
				'sanitize' => 'absint',
			);
			return $fields;
		};
		add_filter( 'pediment_brand_fields', $cb );

		$this->setExpectedIncorrectUsage( 'wp_add_privacy_policy_content' );
		ob_start();
		@do_action( 'admin_init' );
		ob_end_clean();

		global $wp_settings_fields;
		$this->assertArrayHasKey(
			'newsletter_form_id',
			$wp_settings_fields[ PEDIMENT_BRAND_PAGE ]['contact'] ?? array(),
			'Filter-added field should appear in $wp_settings_fields under its section.'
		);

		remove_filter( 'pediment_brand_fields', $cb );
	}

	public function test_sanitize_runs_field_custom_sanitize_callable() {
		$cb = static function ( $fields ) {
			$fields['newsletter_form_id'] = array(
				'label'    => 'Newsletter form ID',
				'section'  => 'contact',
				'type'     => 'integer',
				'default'  => 0,
				'sanitize' => 'absint',
			);
			return $fields;
		};
		add_filter( 'pediment_brand_fields', $cb );

		$clean = pediment_brand_sanitize( array( 'newsletter_form_id' => '-42abc' ) );
		$this->assertSame( 42, $clean['newsletter_form_id'] );

		remove_filter( 'pediment_brand_fields', $cb );
	}

	public function test_sanitize_applies_type_default_sanitize_when_callable_is_null() {
		$cb = static function ( $fields ) {
			$fields['legal_text'] = array(
				'label'   => 'Legal text',
				'section' => 'identity',
				'type'    => 'textarea',
				'default' => '',
			);
			return $fields;
		};
		add_filter( 'pediment_brand_fields', $cb );

		$clean = pediment_brand_sanitize( array( 'legal_text' => "  hello\n<script>alert(1)</script>  " ) );
		$this->assertStringContainsString( 'hello', $clean['legal_text'] );
		$this->assertStringNotContainsString( '<script>', $clean['legal_text'] );

		remove_filter( 'pediment_brand_fields', $cb );
	}
}
