<?php
/**
 * E-mail destinations: a destination with `type => email` sends the
 * submission with wp_mail() instead of an HTTP request.
 *
 * Record shape (next to the shared id/label):
 *   to             string|string[]  One or more addresses (comma, semicolon or space separated).
 *   subject        string           Template; same field/meta tokens as body_template.
 *   reply_to_field string           Form field whose value becomes Reply-To when it is an address.
 *   body_template  string           Plain-text template; empty = one "Label: value" line per field.
 *
 * Mail transport (SMTP, API relay) is not Pediment's concern: the site picks
 * it with a mail plugin or `phpmailer_init`. Delivery only calls wp_mail().
 *
 * @package Pediment
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const PEDIMENT_FORM_TYPES       = array( 'http', 'email' );
const PEDIMENT_FORM_SUBJECT_MAX = 200;

/**
 * The type of a destination record. A record without a type is an HTTP
 * destination, so every record saved before e-mail destinations existed stays valid.
 *
 * @param array<string,mixed> $dest Destination record.
 * @return string 'http' or 'email'.
 */
function pediment_form_destination_type( array $dest ): string {
	return 'email' === ( $dest['type'] ?? '' ) ? 'email' : 'http';
}

/**
 * Split a recipient value into trimmed, de-duplicated address strings. Invalid
 * addresses are kept, so validation can name them.
 *
 * @param mixed $to A string (comma, semicolon or whitespace separated) or a list.
 * @return array<int,string>
 */
function pediment_form_split_recipients( $to ): array {
	$parts = is_array( $to ) ? $to : preg_split( '/[\s,;]+/', (string) $to );
	$out   = array();
	foreach ( (array) $parts as $part ) {
		$part = trim( (string) $part );
		if ( '' !== $part && ! in_array( $part, $out, true ) ) {
			$out[] = $part;
		}
	}
	return $out;
}

/**
 * Make a visitor-supplied value safe to put into a mail header: no line
 * breaks or other control characters (header injection), whitespace collapsed.
 *
 * @param string $value Raw value.
 * @return string
 */
function pediment_form_mail_header_value( string $value ): string {
	$value = (string) preg_replace( '/[\x00-\x1F\x7F]+/u', ' ', $value );
	return trim( (string) preg_replace( '/\s{2,}/u', ' ', $value ) );
}

/**
 * Render a plain-text mail template. One pass, so a field value that looks like
 * a token is never expanded. Secrets never render into mail: a mail goes to
 * people, and validation rejects secret tokens in e-mail destinations.
 *
 * @param string              $template Raw template.
 * @param array<string,mixed> $context  Render context (pediment_form_build_context()).
 * @return string
 */
function pediment_form_render_text( string $template, array $context ): string {
	return (string) preg_replace_callback(
		'/\{\{\s*all_fields\s*\}\}|\{\{\s*(field|meta|secret)\s*:\s*([a-z0-9_]+)\s*\}\}/i',
		static function ( $m ) use ( $context ) {
			if ( ! isset( $m[1] ) || '' === $m[1] ) {
				return pediment_form_mail_field_lines( $context );
			}
			$type = strtolower( $m[1] );
			return 'secret' === $type ? '' : pediment_form_resolve_token( $type, strtolower( $m[2] ), $context );
		},
		$template
	);
}

/**
 * One "Label: value" line per submitted field. A value with line breaks
 * starts on its own line, below the label.
 *
 * @param array<string,mixed> $context Render context.
 * @return string
 */
function pediment_form_mail_field_lines( array $context ): string {
	$fields = isset( $context['fields'] ) && is_array( $context['fields'] ) ? $context['fields'] : array();
	$labels = isset( $context['labels'] ) && is_array( $context['labels'] ) ? $context['labels'] : array();
	$lines  = array();
	foreach ( $fields as $name => $value ) {
		$label = trim( (string) ( $labels[ $name ] ?? '' ) );
		$label = '' !== $label ? $label : (string) $name;
		$value = trim( (string) $value );
		$lines[] = false !== strpos( $value, "\n" ) ? $label . ":\n" . $value : $label . ': ' . $value;
	}
	return implode( "\n", $lines );
}

/**
 * The default plain-text body: every field, then the page, the time and a link
 * to the stored submission in wp-admin.
 *
 * @param array<string,mixed> $context       Render context.
 * @param int                 $submission_id form_submission post id (0 for a test send).
 * @return string
 */
function pediment_form_default_mail_body( array $context, int $submission_id ): string {
	$meta  = isset( $context['meta'] ) && is_array( $context['meta'] ) ? $context['meta'] : array();
	$lines = array( pediment_form_mail_field_lines( $context ), '', '--' );

	$page = (string) ( $meta['page_url'] ?? '' );
	if ( '' !== $page ) {
		/* translators: %s: URL of the page the form was sent from. */
		$lines[] = sprintf( __( 'Page: %s', 'pediment' ), $page );
	}
	$time = strtotime( (string) ( $meta['submitted_at'] ?? '' ) . ' UTC' );
	if ( false !== $time ) {
		/* translators: %s: date and time the form was sent. */
		$lines[] = sprintf( __( 'Sent: %s', 'pediment' ), wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $time ) );
	}
	if ( $submission_id > 0 ) {
		/* translators: %s: wp-admin URL of the stored submission. */
		$lines[] = sprintf( __( 'Submission: %s', 'pediment' ), admin_url( 'post.php?post=' . $submission_id . '&action=edit' ) );
	}
	return implode( "\n", $lines );
}

/**
 * The default subject: "New form submission – Site name".
 *
 * @return string
 */
function pediment_form_default_mail_subject(): string {
	/* translators: %s: site name. */
	return sprintf( __( 'New form submission – %s', 'pediment' ), wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES ) );
}

/**
 * Build the wp_mail() arguments for an e-mail destination.
 *
 * @param array<string,mixed> $dest          E-mail destination record.
 * @param array<string,mixed> $context       Render context.
 * @param int                 $submission_id form_submission post id (0 for a test send).
 * @return array{to:array<int,string>,subject:string,message:string,headers:array<int,string>}
 */
function pediment_form_mail_args( array $dest, array $context, int $submission_id ): array {
	$to = array_values( array_filter( pediment_form_split_recipients( $dest['to'] ?? '' ), 'is_email' ) );

	$subject_template = trim( (string) ( $dest['subject'] ?? '' ) );
	$subject          = '' !== $subject_template ? pediment_form_render_text( $subject_template, $context ) : pediment_form_default_mail_subject();
	$subject          = mb_substr( pediment_form_mail_header_value( $subject ), 0, PEDIMENT_FORM_SUBJECT_MAX );

	$body_template = trim( (string) ( $dest['body_template'] ?? '' ) );
	$message       = '' !== $body_template ? pediment_form_render_text( $body_template, $context ) : pediment_form_default_mail_body( $context, $submission_id );

	$headers  = array( 'Content-Type: text/plain; charset=UTF-8' );
	$reply_to = (string) ( $dest['reply_to_field'] ?? '' );
	if ( '' !== $reply_to ) {
		// is_email() rejects line breaks, spaces, commas and angle brackets, so the
		// value cannot add a header or a second address; the clean-up is a second fence.
		$address = pediment_form_mail_header_value( (string) ( $context['fields'][ $reply_to ] ?? '' ) );
		if ( '' !== $address && is_email( $address ) ) {
			$headers[] = 'Reply-To: ' . $address;
		}
	}

	return array(
		'to'      => $to,
		'subject' => $subject,
		'message' => $message,
		'headers' => $headers,
	);
}

/**
 * Send mail and return the transport's error text, or '' on success.
 *
 * @param array{to:array<int,string>,subject:string,message:string,headers:array<int,string>} $args wp_mail() arguments.
 * @return string
 */
function pediment_form_send_mail( array $args ): string {
	$error   = '';
	$capture = static function ( $wp_error ) use ( &$error ) {
		if ( is_wp_error( $wp_error ) ) {
			$error = $wp_error->get_error_message();
		}
	};
	add_action( 'wp_mail_failed', $capture );
	$sent = wp_mail( $args['to'], $args['subject'], $args['message'], $args['headers'] );
	remove_action( 'wp_mail_failed', $capture );

	if ( $sent ) {
		return '';
	}
	return '' !== $error ? $error : __( 'wp_mail() did not accept the message.', 'pediment' );
}

/**
 * Deliver a submission to an e-mail destination and record the result.
 *
 * @param int                 $submission_id form_submission post id.
 * @param array<string,mixed> $dest          E-mail destination record.
 * @param array<string,mixed> $context       Render context.
 * @return array{status:string,code?:int}
 */
function pediment_form_deliver_email( int $submission_id, array $dest, array $context ): array {
	$args = apply_filters( 'pediment_form_mail_args', pediment_form_mail_args( $dest, $context, $submission_id ), $dest, $submission_id );
	if ( empty( $args['to'] ) ) {
		return pediment_form_record_failure( $submission_id, 0, __( 'The e-mail destination has no valid recipient.', 'pediment' ) );
	}

	$error = pediment_form_send_mail( $args );
	if ( '' !== $error ) {
		return pediment_form_record_failure( $submission_id, 0, $error );
	}

	update_post_meta( $submission_id, '_delivery_status', 'sent' );
	delete_post_meta( $submission_id, '_delivery_http_status' );
	/* translators: %s: comma-separated recipient addresses. */
	update_post_meta( $submission_id, '_delivery_response', sprintf( __( 'Mail sent to %s', 'pediment' ), implode( ', ', (array) $args['to'] ) ) );
	update_post_meta( $submission_id, '_delivered_at', time() );
	do_action( 'pediment_form_submission_received', $submission_id, $dest );
	return array( 'status' => 'sent' );
}

/**
 * Validate an e-mail destination record. Returns field => error.
 *
 * @param array<string,mixed> $dest E-mail destination record.
 * @return array<string,string>
 */
function pediment_form_validate_email_destination( array $dest ): array {
	$errors = array();

	$to = pediment_form_split_recipients( $dest['to'] ?? '' );
	if ( array() === $to ) {
		$errors['to'] = __( 'Add at least one recipient address.', 'pediment' );
	} else {
		$bad = array_values( array_filter( $to, static fn( $a ) => ! is_email( $a ) ) );
		if ( array() !== $bad ) {
			$errors['to'] = sprintf(
				/* translators: %s: the invalid address(es). */
				__( 'Not a valid e-mail address: %s.', 'pediment' ),
				implode( ', ', $bad )
			);
		}
	}

	$reply_to = (string) ( $dest['reply_to_field'] ?? '' );
	if ( '' !== $reply_to && ! preg_match( '/^[a-z0-9_]+$/i', $reply_to ) ) {
		$errors['reply_to_field'] = __( 'Reply-To field must be a form field name (letters, digits, underscores).', 'pediment' );
	}

	foreach ( array( 'subject', 'body_template' ) as $key ) {
		foreach ( pediment_form_extract_tokens( (string) ( $dest[ $key ] ?? '' ) ) as $tok ) {
			if ( 'secret' === $tok['type'] ) {
				$errors[ $key ] = __( 'Secrets cannot be used in an e-mail: the recipients would read them.', 'pediment' );
			} elseif ( 'meta' === $tok['type'] && ! in_array( $tok['name'], PEDIMENT_FORM_META_KEYS, true ) ) {
				$errors[ $key ] = sprintf(
					/* translators: %s: token name */
					__( 'Unknown meta token: %s.', 'pediment' ),
					$tok['name']
				);
			}
		}
	}

	return $errors;
}
