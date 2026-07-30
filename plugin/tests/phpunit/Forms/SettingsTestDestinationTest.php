<?php

class SettingsTestDestinationTest extends WP_UnitTestCase {
	/** @var array<string,mixed> */
	private array $captured = array();
	/** @var bool */
	private bool $http_called = false;

	public function tear_down(): void {
		remove_all_filters( 'pre_http_request' );
		delete_option( PEDIMENT_FORM_DESTINATIONS_OPTION );
		delete_option( PEDIMENT_FORM_SECRETS_OPTION );
		parent::tear_down();
	}

	private function make_dest( array $overrides = array() ): array {
		return array_merge(
			array(
				'id'            => 'brevo_test',
				'label'         => 'Brevo Test',
				'method'        => 'POST',
				'url'           => 'https://api.brevo.com/v3/smtp/email',
				'content_type'  => 'application/json',
				'headers'       => array( 'api-key' => 'k' ),
				'body_template' => '{"name":"{{ field:name }}"}',
				'secret_refs'   => array(),
			),
			$overrides
		);
	}

	private function stub_http( int $code, string $body = '{"ok":true}' ): void {
		add_filter(
			'pre_http_request',
			function ( $pre, $args, $url ) use ( $code, $body ) {
				$this->captured   = array( 'args' => $args, 'url' => $url );
				$this->http_called = true;
				return array(
					'response' => array( 'code' => $code, 'message' => 'x' ),
					'body'     => $body,
					'headers'  => array(),
				);
			},
			10,
			3
		);
	}

	public function test_test_destination_sends_rendered_request_and_reports_2xx(): void {
		$this->stub_http( 200, '{"ok":true}' );

		$dest   = $this->make_dest();
		$result = pediment_form_test_destination( $dest );

		$this->assertTrue( $result['ok'] );
		$this->assertStringContainsString( '200', $result['message'] );
		$this->assertTrue( $this->http_called, 'HTTP request must be made for safe URLs.' );

		$sent = json_decode( (string) $this->captured['args']['body'], true );
		$this->assertSame( array( 'name' => 'sample' ), $sent, 'field:name token must be substituted with "sample".' );
	}

	public function test_test_destination_blocks_unsafe_url(): void {
		$http_called = false;
		add_filter(
			'pre_http_request',
			static function () use ( &$http_called ) {
				$http_called = true;
				return array(
					'response' => array( 'code' => 200, 'message' => 'OK' ),
					'body'     => '',
					'headers'  => array(),
				);
			},
			10,
			3
		);

		$dest   = $this->make_dest( array( 'url' => 'https://127.0.0.1/hook' ) );
		$result = pediment_form_test_destination( $dest );

		$this->assertFalse( $result['ok'] );
		$this->assertMatchesRegularExpression( '/blocked|private/i', $result['message'] );
		$this->assertFalse( $http_called, 'HTTP must NOT be called for unsafe URLs.' );
	}

	public function test_test_destination_reports_wp_error(): void {
		add_filter(
			'pre_http_request',
			static function () {
				return new WP_Error( 'http_request_failed', 'boom' );
			},
			10,
			3
		);

		$dest   = $this->make_dest();
		$result = pediment_form_test_destination( $dest );

		$this->assertFalse( $result['ok'] );
		$this->assertStringContainsString( 'boom', $result['message'] );
	}

	public function test_test_destination_makes_no_db_writes(): void {
		$this->stub_http( 200 );

		$before_option = get_option( PEDIMENT_FORM_DESTINATIONS_OPTION, array() );
		$before_posts  = get_posts( array( 'post_type' => 'form_submission', 'posts_per_page' => -1, 'fields' => 'ids' ) );

		$dest   = $this->make_dest();
		$result = pediment_form_test_destination( $dest );

		$this->assertTrue( $result['ok'] );

		$after_option = get_option( PEDIMENT_FORM_DESTINATIONS_OPTION, array() );
		$after_posts  = get_posts( array( 'post_type' => 'form_submission', 'posts_per_page' => -1, 'fields' => 'ids' ) );

		$this->assertSame( $before_option, $after_option, 'destinations option must not be changed by test.' );
		$this->assertSame( $before_posts, $after_posts, 'no form_submission posts must be created by test.' );
	}
}
