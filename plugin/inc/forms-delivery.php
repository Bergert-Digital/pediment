<?php
/**
 * Delivery engine: renders and sends a submission to its destination, records
 * the result back onto the submission.
 *
 * @package Pediment
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'pediment_form_stored', 'pediment_form_deliver', 10, 1 );

/**
 * Build the render context (field name => value, plus meta) for a submission.
 *
 * @param int $submission_id form_submission post id.
 * @return array{fields:array<string,string>,meta:array<string,string>}
 */
function pediment_form_build_context( int $submission_id ): array {
	$decoded = json_decode( (string) get_post_meta( $submission_id, '_fields', true ), true );
	$fields  = array();
	if ( is_array( $decoded ) ) {
		foreach ( $decoded as $name => $row ) {
			$fields[ (string) $name ] = is_array( $row ) ? (string) ( $row['value'] ?? '' ) : (string) $row;
		}
	}
	$source = (int) get_post_meta( $submission_id, '_source_post_id', true );
	return array(
		'fields' => $fields,
		'meta'   => array(
			'post_id'      => (string) $source,
			'page_url'     => $source > 0 ? (string) get_permalink( $source ) : '',
			'submitted_at' => (string) get_post_field( 'post_date_gmt', $submission_id ),
			'destination'  => (string) get_post_meta( $submission_id, '_destination', true ),
		),
	);
}

/**
 * Record a failed delivery and return the status array.
 *
 * @param int    $submission_id form_submission post id.
 * @param int    $code          HTTP status (0 for transport/guard errors).
 * @param string $detail        Response snippet or error message.
 * @return array{status:string,code:int}
 */
function pediment_form_record_failure( int $submission_id, int $code, string $detail ): array {
	update_post_meta( $submission_id, '_delivery_status', 'failed' );
	update_post_meta( $submission_id, '_delivery_http_status', $code );
	update_post_meta( $submission_id, '_delivery_response', substr( $detail, 0, 500 ) );
	return array(
		'status' => 'failed',
		'code'   => $code,
	);
}

/**
 * Resolve, render, and send a submission to its destination.
 *
 * @param int $submission_id form_submission post id.
 * @return array{status:string,code?:int}
 */
function pediment_form_deliver( int $submission_id ): array {
	$dest_id = pediment_form_resolve_destination_id( (string) get_post_meta( $submission_id, '_destination', true ) );
	if ( '' === $dest_id ) {
		update_post_meta( $submission_id, '_delivery_status', 'no_destination' );
		return array( 'status' => 'no_destination' );
	}

	$dest    = (array) pediment_form_get_destination( $dest_id );
	$context = pediment_form_build_context( $submission_id );

	$url = pediment_form_render_url( (string) ( $dest['url'] ?? '' ), $context );
	if ( ! pediment_form_url_is_safe( $url ) ) {
		return pediment_form_record_failure( $submission_id, 0, __( 'Destination URL failed the safety check.', 'pediment' ) );
	}

	$content_type = (string) ( $dest['content_type'] ?? 'application/json' );
	$headers      = array( 'Content-Type' => $content_type );
	foreach ( (array) ( $dest['headers'] ?? array() ) as $hk => $hv ) {
		$headers[ (string) $hk ] = pediment_form_render_header_value( (string) $hv, $context );
	}

	$args = apply_filters(
		'pediment_form_request_args',
		array(
			'method'  => strtoupper( (string) ( $dest['method'] ?? 'POST' ) ),
			'headers' => $headers,
			'body'    => pediment_form_render_template( (string) ( $dest['body_template'] ?? '' ), $context, $content_type ),
			'timeout' => 15,
		),
		$dest,
		$submission_id
	);

	$response = wp_remote_request( $url, $args );
	if ( is_wp_error( $response ) ) {
		return pediment_form_record_failure( $submission_id, 0, $response->get_error_message() );
	}

	$code    = (int) wp_remote_retrieve_response_code( $response );
	$snippet = substr( (string) wp_remote_retrieve_body( $response ), 0, 500 );
	if ( $code >= 200 && $code < 300 ) {
		update_post_meta( $submission_id, '_delivery_status', 'sent' );
		update_post_meta( $submission_id, '_delivery_http_status', $code );
		update_post_meta( $submission_id, '_delivery_response', $snippet );
		update_post_meta( $submission_id, '_delivered_at', time() );
		do_action( 'pediment_form_submission_received', $submission_id, $dest );
		return array(
			'status' => 'sent',
			'code'   => $code,
		);
	}

	return pediment_form_record_failure( $submission_id, $code, $snippet );
}
