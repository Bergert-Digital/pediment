<?php
/**
 * HTTPS + SSRF guard for outbound destination requests.
 *
 * @package Pediment
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * True when $ip is a routable public address (not private/reserved/loopback).
 *
 * @param string $ip IPv4 or IPv6 literal.
 */
function pediment_form_ip_is_public( string $ip ): bool {
	return (bool) filter_var(
		$ip,
		FILTER_VALIDATE_IP,
		FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
	);
}

/**
 * Gate every outbound destination URL: HTTPS only, and every resolved IP public.
 *
 * @param string $url Candidate URL.
 */
function pediment_form_url_is_safe( string $url ): bool {
	$parts = wp_parse_url( $url );
	if ( ! is_array( $parts ) || empty( $parts['scheme'] ) || empty( $parts['host'] ) ) {
		return false;
	}
	if ( 'https' !== strtolower( (string) $parts['scheme'] ) ) {
		return false;
	}

	$host = trim( (string) $parts['host'], '[]' );

	$allowed = (array) apply_filters( 'pediment_form_allowed_hosts', array() );
	if ( in_array( $host, $allowed, true ) ) {
		return true;
	}

	$ips = array();
	if ( filter_var( $host, FILTER_VALIDATE_IP ) ) {
		$ips[] = $host;
	} else {
		$resolved = gethostbynamel( $host );
		$ips      = is_array( $resolved ) ? $resolved : array();
		if ( empty( $ips ) ) {
			// Cannot prove the host is public — reject.
			return false;
		}
	}

	foreach ( $ips as $ip ) {
		if ( ! pediment_form_ip_is_public( (string) $ip ) ) {
			return false;
		}
	}
	return true;
}
