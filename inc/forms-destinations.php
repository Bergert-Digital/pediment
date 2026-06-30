<?php
/**
 * Destinations registry + validation gate.
 *
 * A destination is a templated outbound HTTP request referenced by id. Admin
 * destinations live in an option; code can register more via filter.
 *
 * @package Pediment
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const PEDIMENT_FORM_DESTINATIONS_OPTION = 'pediment_form_destinations';
const PEDIMENT_FORM_DEFAULT_DEST_OPTION = 'pediment_form_default_destination';
const PEDIMENT_FORM_META_KEYS           = array( 'post_id', 'page_url', 'submitted_at', 'destination' );
const PEDIMENT_FORM_METHODS             = array( 'GET', 'POST', 'PUT', 'PATCH' );
const PEDIMENT_FORM_CONTENT_TYPES       = array( 'application/json', 'application/x-www-form-urlencoded' );

/**
 * All destinations, keyed by id. Admin option wins over code registration.
 *
 * @return array<string,array<string,mixed>>
 */
function pediment_form_destinations(): array {
	$out    = array();
	$stored = get_option( PEDIMENT_FORM_DESTINATIONS_OPTION, array() );
	if ( is_array( $stored ) ) {
		foreach ( $stored as $d ) {
			if ( is_array( $d ) && '' !== (string) ( $d['id'] ?? '' ) ) {
				$out[ (string) $d['id'] ] = $d;
			}
		}
	}
	$registered = (array) apply_filters( 'pediment_form_destinations', array() );
	foreach ( $registered as $d ) {
		$id = is_array( $d ) ? (string) ( $d['id'] ?? '' ) : '';
		if ( '' !== $id && ! isset( $out[ $id ] ) ) {
			$out[ $id ] = $d;
		}
	}
	return $out;
}

/**
 * Look up one destination by id.
 *
 * @param string $id Destination id.
 * @return array<string,mixed>|null
 */
function pediment_form_get_destination( string $id ): ?array {
	$all = pediment_form_destinations();
	return $all[ $id ] ?? null;
}

/**
 * Resolve the effective destination id for a submission, with default fallback.
 *
 * @param string $id The submission's stored destination id (may be empty).
 * @return string Resolved destination id, or empty string when nothing resolves.
 */
function pediment_form_resolve_destination_id( string $id ): string {
	if ( '' !== $id && null !== pediment_form_get_destination( $id ) ) {
		return $id;
	}
	$default = (string) get_option( PEDIMENT_FORM_DEFAULT_DEST_OPTION, '' );
	return ( '' !== $default && null !== pediment_form_get_destination( $default ) ) ? $default : '';
}

/**
 * Validate a destination record. Returns field => error (empty when valid).
 *
 * @param array<string,mixed> $dest Destination record.
 * @return array<string,string>
 */
function pediment_form_validate_destination( array $dest ): array {
	$errors = array();

	if ( '' === sanitize_key( (string) ( $dest['id'] ?? '' ) ) ) {
		$errors['id'] = __( 'A machine id is required.', 'pediment' );
	}
	if ( ! in_array( strtoupper( (string) ( $dest['method'] ?? '' ) ), PEDIMENT_FORM_METHODS, true ) ) {
		$errors['method'] = __( 'Method must be GET, POST, PUT, or PATCH.', 'pediment' );
	}

	$content_type = (string) ( $dest['content_type'] ?? '' );
	if ( ! in_array( $content_type, PEDIMENT_FORM_CONTENT_TYPES, true ) ) {
		$errors['content_type'] = __( 'Unsupported content type.', 'pediment' );
	}

	// URL: must be HTTPS + SSRF-safe with field/secret tokens neutralised first
	// (so a {{ field:x }} in the path does not break parsing).
	$probe_url = preg_replace( '/\{\{[^}]+\}\}/', 'x', (string) ( $dest['url'] ?? '' ) );
	if ( ! pediment_form_url_is_safe( (string) $probe_url ) ) {
		$errors['url'] = __( 'URL must be HTTPS and resolve to a public host.', 'pediment' );
	}

	// Body: for JSON content type, the skeleton (tokens replaced by neutral
	// literals) must parse as JSON.
	$body = (string) ( $dest['body_template'] ?? '' );
	if ( false !== stripos( $content_type, 'json' ) && '' !== $body ) {
		$skeleton = preg_replace( '/"\{\{\s*all_fields\s*\}\}"/', '{}', $body );
		$skeleton = preg_replace( '/\{\{[^}]+\}\}/', 'x', (string) $skeleton );
		json_decode( (string) $skeleton );
		if ( JSON_ERROR_NONE !== json_last_error() ) {
			$errors['body_template'] = __( 'Body is not valid JSON once tokens are filled.', 'pediment' );
		}
	}

	// Token sanity across url + headers + body.
	// Each haystack carries the field key to use when reporting a bad meta token.
	$haystacks = array(
		array(
			'key' => 'url',
			'hay' => (string) ( $dest['url'] ?? '' ),
		),
		array(
			'key' => 'body_template',
			'hay' => $body,
		),
	);
	foreach ( (array) ( $dest['headers'] ?? array() ) as $hk => $hv ) {
		$haystacks[] = array(
			'key' => 'headers',
			'hay' => (string) $hk,
		);
		$haystacks[] = array(
			'key' => 'headers',
			'hay' => (string) $hv,
		);
	}
	$known_secrets = pediment_form_secret_names();
	foreach ( $haystacks as $entry ) {
		foreach ( pediment_form_extract_tokens( $entry['hay'] ) as $tok ) {
			if ( 'meta' === $tok['type'] && ! in_array( $tok['name'], PEDIMENT_FORM_META_KEYS, true ) ) {
				$errors[ $entry['key'] ] = sprintf(
					/* translators: %s: token name */
					__( 'Unknown meta token: %s.', 'pediment' ),
					$tok['name']
				);
			}
			if ( 'secret' === $tok['type'] && ! in_array( $tok['name'], $known_secrets, true ) ) {
				$errors['secret_refs'] = sprintf(
					/* translators: %s: secret name */
					__( 'Secret "%s" is referenced but not stored. Add it under Secrets first.', 'pediment' ),
					$tok['name']
				);
			}
		}
	}

	return $errors;
}
