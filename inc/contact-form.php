<?php
/**
 * Contact form REST endpoint + CPT + cron cleanup.
 *
 * @package Starter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const STARTER_CONTACT_NAMESPACE = 'starter/v1';
const STARTER_CONTACT_ROUTE     = '/contact';
const STARTER_CONTACT_CPT       = 'contact_submission';
const STARTER_CONTACT_MIN_AGE   = 5;
const STARTER_CONTACT_CRON_HOOK = 'starter_contact_cleanup';

add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			STARTER_CONTACT_NAMESPACE,
			STARTER_CONTACT_ROUTE,
			array(
				'methods'             => 'POST',
				'permission_callback' => '__return_true',
				'callback'            => 'starter_contact_handle_submission',
			)
		);
	}
);

function starter_contact_handle_submission( WP_REST_Request $request ) {
	$name     = trim( (string) $request->get_param( 'name' ) );
	$email    = trim( (string) $request->get_param( 'email' ) );
	$phone    = trim( (string) $request->get_param( 'phone' ) );
	$message  = trim( (string) $request->get_param( 'message' ) );
	$hp_field = (string) $request->get_param( 'hp_field' );
	$t_raw    = $request->get_param( '_t' );
	$t        = is_numeric( $t_raw ) ? (int) $t_raw : 0;

	if ( '' !== $hp_field ) {
		return new WP_Error( 'starter_spam', __( 'Submission rejected.', 'starter' ), array( 'status' => 400 ) );
	}
	$now = time();
	if ( $t <= 0 || $t > $now || ( $now - $t ) < STARTER_CONTACT_MIN_AGE ) {
		return new WP_Error( 'starter_spam', __( 'Submission rejected.', 'starter' ), array( 'status' => 400 ) );
	}

	$errors = array();
	if ( '' === $name ) {
		$errors['name'] = __( 'Name is required.', 'starter' );
	}
	if ( '' === $email ) {
		$errors['email'] = __( 'Email is required.', 'starter' );
	} elseif ( ! is_email( $email ) ) {
		$errors['email'] = __( 'Email is invalid.', 'starter' );
	}
	if ( '' === $message ) {
		$errors['message'] = __( 'Message is required.', 'starter' );
	}

	if ( ! empty( $errors ) ) {
		return new WP_Error( 'starter_validation', __( 'Validation failed.', 'starter' ), array( 'status' => 400, 'errors' => $errors ) );
	}

	do_action(
		'starter_contact_submitted',
		array(
			'name'    => $name,
			'email'   => $email,
			'phone'   => $phone,
			'message' => $message,
		),
		$request
	);

	return new WP_REST_Response( array( 'ok' => true ), 200 );
}

add_action(
	'init',
	function () {
		if ( post_type_exists( STARTER_CONTACT_CPT ) ) {
			return;
		}
		register_post_type(
			STARTER_CONTACT_CPT,
			array(
				'label'               => __( 'Contact submissions', 'starter' ),
				'labels'              => array(
					'name'          => __( 'Contact submissions', 'starter' ),
					'singular_name' => __( 'Contact submission', 'starter' ),
					'menu_name'     => __( 'Contact submissions', 'starter' ),
				),
				'public'              => false,
				'exclude_from_search' => true,
				'publicly_queryable'  => false,
				'show_ui'             => true,
				'show_in_menu'        => true,
				'show_in_rest'        => false,
				'menu_icon'           => 'dashicons-email',
				'capability_type'     => 'page',
				'capabilities'        => array(
					'create_posts' => 'do_not_allow',
				),
				'map_meta_cap'        => true,
				'supports'            => array( 'title' ),
				'has_archive'         => false,
				'rewrite'             => false,
			)
		);
	}
);

add_action( 'starter_contact_submitted', 'starter_contact_persist_submission', 10, 2 );

function starter_contact_persist_submission( array $payload, $request ): void {
	$name    = (string) ( $payload['name']    ?? '' );
	$email   = (string) ( $payload['email']   ?? '' );
	$phone   = (string) ( $payload['phone']   ?? '' );
	$message = (string) ( $payload['message'] ?? '' );

	$post_id = wp_insert_post(
		array(
			'post_type'    => STARTER_CONTACT_CPT,
			'post_status'  => 'publish',
			'post_title'   => sprintf( '%s <%s>', $name, $email ),
			'post_content' => $message,
		),
		true
	);

	if ( is_wp_error( $post_id ) || ! $post_id ) {
		return;
	}

	update_post_meta( $post_id, '_email', sanitize_email( $email ) );
	if ( '' !== $phone ) {
		update_post_meta( $post_id, '_phone', sanitize_text_field( $phone ) );
	}
}

add_filter(
	'manage_' . STARTER_CONTACT_CPT . '_posts_columns',
	function ( array $cols ) {
		$cols = array(
			'cb'    => $cols['cb'] ?? '',
			'title' => __( 'From', 'starter' ),
			'email' => __( 'Email', 'starter' ),
			'date'  => __( 'Submitted', 'starter' ),
		);
		return $cols;
	}
);

add_action(
	'manage_' . STARTER_CONTACT_CPT . '_posts_custom_column',
	function ( $col, $post_id ) {
		if ( 'email' === $col ) {
			echo esc_html( (string) get_post_meta( $post_id, '_email', true ) );
		}
	},
	10,
	2
);

add_action( 'starter_contact_submitted', 'starter_contact_send_notification', 20, 2 );

function starter_contact_send_notification( array $payload, $request ): void {
	$brand_name = (string) \Starter\Brand::get( 'brand_name', get_bloginfo( 'name' ) );

	$recipient = '';
	if ( $request instanceof WP_REST_Request ) {
		$override = (string) $request->get_param( '_recipient_override' );
		if ( '' !== $override && is_email( $override ) ) {
			$recipient = $override;
		}
	}
	if ( '' === $recipient ) {
		$recipient = (string) \Starter\Brand::get( 'contact_email', get_option( 'admin_email' ) );
	}
	if ( '' === $recipient ) {
		return;
	}

	$name    = (string) ( $payload['name']    ?? '' );
	$email   = (string) ( $payload['email']   ?? '' );
	$phone   = (string) ( $payload['phone']   ?? '' );
	$message = (string) ( $payload['message'] ?? '' );

	$subject = sprintf(
		/* translators: %s: brand name */
		__( '[%s] New contact form submission', 'starter' ),
		$brand_name
	);

	$body  = sprintf( "Name: %s\n", $name );
	$body .= sprintf( "Email: %s\n", $email );
	if ( '' !== $phone ) {
		$body .= sprintf( "Phone: %s\n", $phone );
	}
	$body .= "\n" . $message . "\n";

	$headers = array(
		'Reply-To: ' . $email,
		'Content-Type: text/plain; charset=UTF-8',
	);

	wp_mail( $recipient, $subject, $body, $headers );
}

add_action( STARTER_CONTACT_CRON_HOOK, 'starter_contact_cleanup' );

function starter_contact_cleanup(): void {
	$cutoff_gmt = gmdate( 'Y-m-d H:i:s', strtotime( '-90 days' ) );

	$stale = get_posts(
		array(
			'post_type'      => STARTER_CONTACT_CPT,
			'post_status'    => 'any',
			'posts_per_page' => 200,
			'fields'         => 'ids',
			'date_query'     => array(
				'before'    => $cutoff_gmt,
				'column'    => 'post_date_gmt',
				'inclusive' => true,
			),
		)
	);

	foreach ( $stale as $post_id ) {
		wp_delete_post( $post_id, true );
	}
}

function starter_contact_schedule_cleanup(): void {
	if ( ! wp_next_scheduled( STARTER_CONTACT_CRON_HOOK ) ) {
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', STARTER_CONTACT_CRON_HOOK );
	}
}

function starter_contact_unschedule_cleanup(): void {
	wp_clear_scheduled_hook( STARTER_CONTACT_CRON_HOOK );
}
