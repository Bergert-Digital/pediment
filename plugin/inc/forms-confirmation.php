<?php
/**
 * Confirmation mail to the sender of a form.
 *
 * An e-mail destination may carry a `confirmation` record:
 *   to_field  string  Form field with the sender's address. Empty = no confirmation.
 *   reply_to  string  Fixed address that gets the sender's reply (optional).
 *   subject   string  Plain-text template.
 *   body      string  Plain-text template.
 *
 * A public form that mails an address a visitor types can be abused to send
 * mail to strangers. So the mail carries only the owner's text: visitor values
 * enter only through {{ field:… }} tokens, each on one line, without tags and
 * cut to PEDIMENT_FORM_CONFIRM_VALUE_MAX characters. {{ all_fields }} and
 * secrets are refused, files are never attached, and every header except `To`
 * comes from the owner's settings. A rate limit per recipient and per IP
 * address stops a flood.
 *
 * The confirmation goes out once, when the submission is stored. Its result is
 * kept in `_confirmation_status`; it never changes `_delivery_status`.
 *
 * @package Pediment
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const PEDIMENT_FORM_CONFIRM_KEYS          = array( 'to_field', 'reply_to', 'subject', 'body' );
const PEDIMENT_FORM_CONFIRM_VALUE_MAX     = 80;
const PEDIMENT_FORM_CONFIRM_PER_RECIPIENT = 3;
const PEDIMENT_FORM_CONFIRM_PER_IP        = 10;

/**
 * The destination's confirmation record, cleaned, or null when it has none.
 *
 * @param array<string,mixed> $dest Destination record.
 * @return array{to_field:string,reply_to:string,subject:string,body:string}|null
 */
function pediment_form_confirmation( array $dest ): ?array {
	if ( 'email' !== pediment_form_destination_type( $dest ) || ! is_array( $dest['confirmation'] ?? null ) ) {
		return null;
	}
	$conf = array();
	foreach ( PEDIMENT_FORM_CONFIRM_KEYS as $key ) {
		$conf[ $key ] = trim( (string) ( $dest['confirmation'][ $key ] ?? '' ) );
	}
	return '' !== $conf['to_field'] ? $conf : null;
}

/**
 * Clean a confirmation record posted from the settings form or declared in a
 * manifest. Returns an empty array when no sender field is set.
 *
 * @param mixed $raw Raw record.
 * @return array<string,string>
 */
function pediment_form_sanitize_confirmation( $raw ): array {
	$raw = is_array( $raw ) ? $raw : array();
	$out = array(
		'to_field' => sanitize_key( (string) ( $raw['to_field'] ?? '' ) ),
		'reply_to' => sanitize_email( (string) ( $raw['reply_to'] ?? '' ) ),
		'subject'  => sanitize_text_field( (string) ( $raw['subject'] ?? '' ) ),
		'body'     => sanitize_textarea_field( (string) ( $raw['body'] ?? '' ) ),
	);
	return '' !== $out['to_field'] ? $out : array();
}

/**
 * Check a confirmation record. Returns key => error, with keys prefixed
 * `confirmation_`.
 *
 * @param mixed $conf The `confirmation` value of a destination.
 * @return array<string,string>
 */
function pediment_form_validate_confirmation( $conf ): array {
	if ( null === $conf || array() === $conf ) {
		return array();
	}
	if ( ! is_array( $conf ) ) {
		return array( 'confirmation' => __( 'The confirmation must be a list of to_field, reply_to, subject and body.', 'pediment' ) );
	}
	$errors = array();
	foreach ( array_keys( $conf ) as $key ) {
		if ( ! in_array( (string) $key, PEDIMENT_FORM_CONFIRM_KEYS, true ) ) {
			/* translators: 1: unknown key, 2: allowed keys */
			$errors['confirmation'] = sprintf( __( 'Unknown confirmation key: %1$s. Allowed: %2$s.', 'pediment' ), (string) $key, implode( ', ', PEDIMENT_FORM_CONFIRM_KEYS ) );
		}
	}
	$to_field = (string) ( $conf['to_field'] ?? '' );
	if ( '' === $to_field ) {
		return $errors;
	}
	if ( ! preg_match( '/^[a-z0-9_]+$/i', $to_field ) ) {
		$errors['confirmation_to_field'] = __( 'The sender field must be a form field name (letters, digits, underscores).', 'pediment' );
	}
	$reply_to = (string) ( $conf['reply_to'] ?? '' );
	if ( '' !== $reply_to && ! is_email( $reply_to ) ) {
		/* translators: %s: the invalid address */
		$errors['confirmation_reply_to'] = sprintf( __( 'Not a valid e-mail address: %s.', 'pediment' ), $reply_to );
	}
	foreach ( array( 'subject', 'body' ) as $key ) {
		$text = (string) ( $conf[ $key ] ?? '' );
		if ( '' === trim( $text ) ) {
			$errors[ 'confirmation_' . $key ] = 'subject' === $key
				? __( 'The confirmation needs a subject.', 'pediment' )
				: __( 'The confirmation needs a message.', 'pediment' );
			continue;
		}
		foreach ( pediment_form_extract_tokens( $text ) as $tok ) {
			if ( 'secret' === $tok['type'] || 'all_fields' === $tok['type'] ) {
				$errors[ 'confirmation_' . $key ] = __( 'The confirmation may use {{ field:NAME }} and {{ meta:… }} tokens only. It goes to the address a visitor typed, so it never repeats the whole submission or a secret.', 'pediment' );
			} elseif ( 'meta' === $tok['type'] && ! in_array( $tok['name'], PEDIMENT_FORM_META_KEYS, true ) ) {
				/* translators: %s: token name */
				$errors[ 'confirmation_' . $key ] = sprintf( __( 'Unknown meta token: %s.', 'pediment' ), $tok['name'] );
			}
		}
	}
	return $errors;
}

/**
 * A visitor value as it may appear in a confirmation: one line, no tags,
 * short.
 *
 * @param string $value Raw value.
 * @return string
 */
function pediment_form_confirmation_value( string $value ): string {
	$value = pediment_form_mail_header_value( wp_strip_all_tags( $value ) );
	return mb_strlen( $value ) > PEDIMENT_FORM_CONFIRM_VALUE_MAX
		? rtrim( mb_substr( $value, 0, PEDIMENT_FORM_CONFIRM_VALUE_MAX - 1 ) ) . '…'
		: $value;
}

/**
 * Render a confirmation template: field tokens get the guarded value, meta
 * tokens their value, everything else renders empty.
 *
 * @param string              $template Owner's template.
 * @param array<string,mixed> $context  Render context (pediment_form_build_context()).
 * @return string
 */
function pediment_form_render_confirmation( string $template, array $context ): string {
	return (string) preg_replace_callback(
		'/\{\{\s*all_fields\s*\}\}|\{\{\s*(field|meta|secret)\s*:\s*([a-z0-9_]+)\s*\}\}/i',
		static function ( $m ) use ( $context ) {
			$type = strtolower( $m[1] ?? '' );
			$name = strtolower( $m[2] ?? '' );
			if ( 'field' === $type ) {
				return pediment_form_confirmation_value( (string) ( $context['fields'][ $name ] ?? '' ) );
			}
			return 'meta' === $type ? (string) ( $context['meta'][ $name ] ?? '' ) : '';
		},
		$template
	);
}

/**
 * The visitor's IP address for the rate limit, or '' when unknown.
 *
 * @return string
 */
function pediment_form_client_ip(): string {
	$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	return (string) apply_filters( 'pediment_form_client_ip', false !== filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '' );
}

/**
 * Count one confirmation against the limits. Returns false when a limit is
 * reached (and counts nothing then).
 *
 * @param string $to Recipient address.
 * @return bool
 */
function pediment_form_confirmation_allowed( string $to ): bool {
	$buckets = array(
		'pediment_fc_to_' . md5( strtolower( $to ) ) => (int) apply_filters( 'pediment_form_confirmation_limit_recipient', PEDIMENT_FORM_CONFIRM_PER_RECIPIENT ),
	);
	$ip      = pediment_form_client_ip();
	if ( '' !== $ip ) {
		$buckets[ 'pediment_fc_ip_' . md5( $ip ) ] = (int) apply_filters( 'pediment_form_confirmation_limit_ip', PEDIMENT_FORM_CONFIRM_PER_IP );
	}
	foreach ( $buckets as $key => $limit ) {
		if ( (int) get_transient( $key ) >= $limit ) {
			return false;
		}
	}
	foreach ( $buckets as $key => $limit ) {
		$entry = get_option( '_transient_timeout_' . $key );
		$ttl   = $entry ? max( 1, (int) $entry - time() ) : HOUR_IN_SECONDS;
		set_transient( $key, (int) get_transient( $key ) + 1, $ttl );
	}
	return true;
}

/**
 * Record the confirmation result on the submission.
 *
 * @param int    $submission_id form_submission post id.
 * @param string $status        sent, failed, rate_limited or no_address.
 * @param string $detail        Error text or recipient.
 * @return string The status.
 */
function pediment_form_record_confirmation( int $submission_id, string $status, string $detail = '' ): string {
	update_post_meta( $submission_id, '_confirmation_status', $status );
	update_post_meta( $submission_id, '_confirmation_response', mb_substr( $detail, 0, 500 ) );
	return $status;
}

add_action( 'pediment_form_stored', 'pediment_form_send_confirmation', 20, 1 );

/**
 * Send the confirmation of a stored submission, when its destination has one.
 *
 * @param int $submission_id form_submission post id.
 * @return string Status: '' (no confirmation), sent, failed, rate_limited, no_address.
 */
function pediment_form_send_confirmation( int $submission_id ): string {
	$dest_id = pediment_form_resolve_destination_id( (string) get_post_meta( $submission_id, '_destination', true ) );
	$dest    = '' !== $dest_id ? (array) pediment_form_get_destination( $dest_id ) : array();
	$conf    = pediment_form_confirmation( $dest );
	if ( null === $conf || array() !== pediment_form_validate_confirmation( $conf ) ) {
		return '';
	}

	$context = pediment_form_build_context( $submission_id );
	$raw_to  = (string) ( $context['fields'][ $conf['to_field'] ] ?? '' );
	$to      = pediment_form_mail_header_value( $raw_to );
	if ( '' === $to || $to !== trim( $raw_to ) || ! is_email( $to ) ) {
		return pediment_form_record_confirmation( $submission_id, 'no_address' );
	}
	if ( ! pediment_form_confirmation_allowed( $to ) ) {
		return pediment_form_record_confirmation( $submission_id, 'rate_limited', $to );
	}

	$headers = array( 'Content-Type: text/plain; charset=UTF-8' );
	if ( '' !== $conf['reply_to'] && is_email( $conf['reply_to'] ) ) {
		$headers[] = 'Reply-To: ' . $conf['reply_to'];
	}
	$args  = array(
		'to'          => array( $to ),
		'subject'     => mb_substr( pediment_form_mail_header_value( pediment_form_render_confirmation( $conf['subject'], $context ) ), 0, PEDIMENT_FORM_SUBJECT_MAX ),
		'message'     => pediment_form_render_confirmation( $conf['body'], $context ),
		'headers'     => $headers,
		'attachments' => array(),
	);
	$error = pediment_form_send_mail( $args );
	return '' === $error
		? pediment_form_record_confirmation( $submission_id, 'sent', $to )
		: pediment_form_record_confirmation( $submission_id, 'failed', $error );
}

/**
 * Human-readable confirmation status, or '' when none was due.
 *
 * @param int $submission_id form_submission post id.
 * @return string
 */
function pediment_form_confirmation_label( int $submission_id ): string {
	$status = (string) get_post_meta( $submission_id, '_confirmation_status', true );
	$labels = array(
		'sent'         => __( 'Sent', 'pediment' ),
		'failed'       => __( 'Failed', 'pediment' ),
		'rate_limited' => __( 'Not sent: too many confirmations to this address or from this visitor in the last hour', 'pediment' ),
		'no_address'   => __( 'Not sent: no valid sender address', 'pediment' ),
	);
	if ( '' === $status ) {
		return '';
	}
	$text   = $labels[ $status ] ?? $status;
	$detail = (string) get_post_meta( $submission_id, '_confirmation_response', true );
	return 'failed' === $status && '' !== $detail ? $text . ' (' . $detail . ')' : $text;
}
