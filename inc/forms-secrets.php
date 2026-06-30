<?php
/**
 * Encrypted secret store for form destinations.
 *
 * Credential values referenced by destinations as {{ secret:NAME }} live here,
 * encrypted at rest with a wp_salt-derived key (mirrors pediment-ai OptionsStore).
 *
 * @package Pediment
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const PEDIMENT_FORM_SECRETS_OPTION = 'pediment_form_secrets';

/**
 * Normalise a secret name to a consistent slug used for storage.
 *
 * Replaces any run of non-alphanumeric characters with an underscore, strips
 * leading/trailing underscores, then passes through sanitize_key() for
 * lower-casing and final safety. Both set and get use this function so the
 * storage key is always identical regardless of how the caller spells the name.
 *
 * @param string $name Raw secret name.
 * @return string Normalised slug (may be empty if $name contained no alphanumerics).
 */
function pediment_form_secret_normalize_name( string $name ): string {
	return sanitize_key( trim( (string) preg_replace( '/[^a-z0-9]+/i', '_', $name ), '_' ) );
}

/**
 * Derive the symmetric cipher key from the site's auth salt.
 *
 * @internal
 */
function pediment_form_secret_cipher_key(): string {
	if ( ! defined( 'SODIUM_CRYPTO_SECRETBOX_KEYBYTES' ) ) {
		return '';
	}
	return substr( hash( 'sha256', wp_salt( 'auth' ) . '|pediment-form-secrets', true ), 0, SODIUM_CRYPTO_SECRETBOX_KEYBYTES );
}

/**
 * Encrypt a plaintext secret; base64-encodes nonce.ciphertext.
 *
 * @internal
 * @param string $plain Plaintext value.
 */
function pediment_form_secret_encrypt( string $plain ): string {
	if ( ! function_exists( 'sodium_crypto_secretbox' ) ) {
		return base64_encode( $plain ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- encoding cipher output, not obfuscating code.
	}
	$nonce = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
	$ct    = sodium_crypto_secretbox( $plain, $nonce, pediment_form_secret_cipher_key() );
	return base64_encode( $nonce . $ct ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- encoding cipher output, not obfuscating code.
}

/**
 * Decrypt a stored secret blob.
 *
 * @internal
 * @param string $blob base64(nonce.ciphertext).
 */
function pediment_form_secret_decrypt( string $blob ): string {
	$raw = base64_decode( $blob, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- decoding cipher output, not obfuscating code.
	if ( false === $raw ) {
		return '';
	}
	if ( ! function_exists( 'sodium_crypto_secretbox_open' ) ) {
		return $raw;
	}
	$nonce = substr( $raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
	$ct    = substr( $raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
	$plain = sodium_crypto_secretbox_open( $ct, $nonce, pediment_form_secret_cipher_key() );
	return false === $plain ? '' : (string) $plain;
}

/**
 * Store (or delete, when $plain is empty) a named secret.
 *
 * @param string $name  Secret name.
 * @param string $plain Plaintext value; '' removes the secret.
 */
function pediment_form_secret_set( string $name, string $plain ): void {
	$name = pediment_form_secret_normalize_name( $name );
	if ( '' === $name ) {
		return;
	}
	$all = get_option( PEDIMENT_FORM_SECRETS_OPTION, array() );
	$all = is_array( $all ) ? $all : array();
	if ( '' === $plain ) {
		unset( $all[ $name ] );
	} else {
		$all[ $name ] = pediment_form_secret_encrypt( $plain );
	}
	update_option( PEDIMENT_FORM_SECRETS_OPTION, $all );
}

/**
 * Retrieve a decrypted secret value.
 *
 * @param string $name Secret name.
 */
function pediment_form_secret_get( string $name ): string {
	$name = pediment_form_secret_normalize_name( $name );
	$all  = get_option( PEDIMENT_FORM_SECRETS_OPTION, array() );
	if ( ! is_array( $all ) || ! isset( $all[ $name ] ) ) {
		return '';
	}
	return pediment_form_secret_decrypt( (string) $all[ $name ] );
}

/**
 * Sorted list of stored secret names.
 *
 * @return array<int,string>
 */
function pediment_form_secret_names(): array {
	$all   = get_option( PEDIMENT_FORM_SECRETS_OPTION, array() );
	$names = is_array( $all ) ? array_keys( $all ) : array();
	sort( $names );
	return array_map( 'strval', $names );
}
