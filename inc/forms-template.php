<?php
/**
 * Token templating for destination requests.
 *
 * Tokens: {{ field:NAME }} {{ all_fields }} {{ meta:KEY }} {{ secret:NAME }}.
 * JSON bodies get structural escaping; headers are CRLF-stripped; url/form
 * contexts rawurlencode each value.
 *
 * @package Pediment
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const PEDIMENT_FORM_SCALAR_TOKEN_RE = '/\{\{\s*(field|meta|secret)\s*:\s*([a-z0-9_]+)\s*\}\}/i';
const PEDIMENT_FORM_ALL_FIELDS_RE   = '/\{\{\s*all_fields\s*\}\}/';

/**
 * Resolve a single scalar token to its string value.
 *
 * @param string              $type    field|meta|secret.
 * @param string              $name    Token name.
 * @param array<string,mixed> $context Render context.
 * @return string
 */
function pediment_form_resolve_token( string $type, string $name, array $context ): string {
	switch ( $type ) {
		case 'field':
			return isset( $context['fields'][ $name ] ) ? (string) $context['fields'][ $name ] : '';
		case 'meta':
			return isset( $context['meta'][ $name ] ) ? (string) $context['meta'][ $name ] : '';
		case 'secret':
			return pediment_form_secret_get( $name );
	}
	$custom = (array) apply_filters( 'pediment_form_template_tokens', array(), $context );
	$key    = $type . ':' . $name;
	return isset( $custom[ $key ] ) ? (string) $custom[ $key ] : '';
}

/**
 * Render a body template against the context for the given content type.
 *
 * @param string              $template     Raw template.
 * @param array<string,mixed> $context      Render context.
 * @param string              $content_type Destination content type.
 * @return string
 */
function pediment_form_render_template( string $template, array $context, string $content_type ): string {
	$is_json = false !== stripos( $content_type, 'json' );
	$fields  = isset( $context['fields'] ) && is_array( $context['fields'] ) ? $context['fields'] : array();

	if ( $is_json ) {
		// Single pass: match "{{ all_fields }}" OR a scalar token. Because this is
		// one pass, replacement text (including field values that look like tokens)
		// is never re-scanned — preventing field-value token injection.
		return (string) preg_replace_callback(
			'/"\{\{\s*all_fields\s*\}\}"|\{\{\s*(field|meta|secret)\s*:\s*([a-z0-9_]+)\s*\}\}/i',
			static function ( $m ) use ( $fields, $context ) {
				if ( '"' === $m[0][0] ) {
					// Matched "{{ all_fields }}" — emit the JSON object.
					return (string) wp_json_encode( $fields );
				}
				// Matched a scalar token — JSON-string-escape the value and strip
				// the surrounding quotes (already inside the template's own quotes).
				$value   = pediment_form_resolve_token( strtolower( $m[1] ), strtolower( $m[2] ), $context );
				$encoded = (string) wp_json_encode( $value );
				return substr( $encoded, 1, -1 );
			},
			$template
		);
	}

	// Non-JSON (form-encoded / plain): rawurlencode scalars and all_fields pairs.
	$pairs = array();
	foreach ( $fields as $k => $v ) {
		$pairs[] = rawurlencode( (string) $k ) . '=' . rawurlencode( (string) $v );
	}
	$template = (string) preg_replace( PEDIMENT_FORM_ALL_FIELDS_RE, implode( '&', $pairs ), $template );
	return (string) preg_replace_callback(
		PEDIMENT_FORM_SCALAR_TOKEN_RE,
		static function ( $m ) use ( $context ) {
			return rawurlencode( pediment_form_resolve_token( strtolower( $m[1] ), strtolower( $m[2] ), $context ) );
		},
		$template
	);
}

/**
 * Render a header value: raw substitution, then CRLF-strip to block injection.
 *
 * @param string              $template Header template.
 * @param array<string,mixed> $context  Render context.
 * @return string
 */
function pediment_form_render_header_value( string $template, array $context ): string {
	$out = (string) preg_replace_callback(
		PEDIMENT_FORM_SCALAR_TOKEN_RE,
		static function ( $m ) use ( $context ) {
			return pediment_form_resolve_token( strtolower( $m[1] ), strtolower( $m[2] ), $context );
		},
		$template
	);
	return str_replace( array( "\r", "\n" ), '', $out );
}

/**
 * Render a URL template: rawurlencode each substituted value.
 *
 * @param string              $template URL template.
 * @param array<string,mixed> $context  Render context.
 * @return string
 */
function pediment_form_render_url( string $template, array $context ): string {
	return (string) preg_replace_callback(
		PEDIMENT_FORM_SCALAR_TOKEN_RE,
		static function ( $m ) use ( $context ) {
			return rawurlencode( pediment_form_resolve_token( strtolower( $m[1] ), strtolower( $m[2] ), $context ) );
		},
		$template
	);
}

/**
 * List every token referenced in a template (for save-time validation).
 *
 * @param string $template Raw template.
 * @return array<int,array{type:string,name:string}>
 */
function pediment_form_extract_tokens( string $template ): array {
	$tokens = array();
	if ( preg_match_all( PEDIMENT_FORM_SCALAR_TOKEN_RE, $template, $m, PREG_SET_ORDER ) ) {
		foreach ( $m as $match ) {
			$tokens[] = array(
				'type' => strtolower( $match[1] ),
				'name' => strtolower( $match[2] ),
			);
		}
	}
	if ( preg_match( PEDIMENT_FORM_ALL_FIELDS_RE, $template ) ) {
		$tokens[] = array(
			'type' => 'all_fields',
			'name' => '',
		);
	}
	return $tokens;
}
