<?php

class SsrfTest extends WP_UnitTestCase {
	public function test_public_https_literal_ip_is_safe() {
		$this->assertTrue( pediment_form_url_is_safe( 'https://93.184.216.34/v3/send' ) );
	}

	public function test_non_https_is_rejected() {
		$this->assertFalse( pediment_form_url_is_safe( 'http://93.184.216.34/' ) );
	}

	/**
	 * @dataProvider private_urls
	 */
	public function test_private_and_reserved_ranges_are_rejected( string $url ) {
		$this->assertFalse( pediment_form_url_is_safe( $url ) );
	}

	public function private_urls(): array {
		return array(
			'loopback'    => array( 'https://127.0.0.1/' ),
			'rfc1918_10'  => array( 'https://10.0.0.5/' ),
			'rfc1918_192' => array( 'https://192.168.1.1/' ),
			'link_local'  => array( 'https://169.254.169.254/latest/meta-data/' ),
			'ipv6_local'  => array( 'https://[::1]/' ),
		);
	}

	public function test_garbage_url_is_rejected() {
		$this->assertFalse( pediment_form_url_is_safe( 'not a url' ) );
		$this->assertFalse( pediment_form_url_is_safe( 'https://' ) );
	}

	public function test_allowed_hosts_filter_bypasses_ip_check() {
		add_filter( 'pediment_form_allowed_hosts', fn() => array( 'internal.test' ) );
		$this->assertTrue( pediment_form_url_is_safe( 'https://internal.test/hook' ) );
		remove_all_filters( 'pediment_form_allowed_hosts' );
	}
}
