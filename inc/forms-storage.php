<?php
/**
 * Form submission storage CPT, persistence, admin columns, and retention.
 *
 * @package Pediment
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'init',
	function () {
		if ( post_type_exists( PEDIMENT_FORM_CPT ) ) {
			return;
		}
		register_post_type(
			PEDIMENT_FORM_CPT,
			array(
				'label'               => __( 'Form submissions', 'pediment' ),
				'labels'              => array(
					'name'          => __( 'Form submissions', 'pediment' ),
					'singular_name' => __( 'Form submission', 'pediment' ),
					'menu_name'     => __( 'Form submissions', 'pediment' ),
				),
				'public'              => false,
				'exclude_from_search' => true,
				'publicly_queryable'  => false,
				'show_ui'             => true,
				'show_in_menu'        => true,
				'show_in_rest'        => false,
				'menu_icon'           => 'dashicons-feedback',
				'capability_type'     => 'page',
				'capabilities'        => array( 'create_posts' => 'do_not_allow' ),
				'map_meta_cap'        => true,
				'supports'            => array( 'title' ),
				'has_archive'         => false,
				'rewrite'             => false,
			)
		);
	}
);

add_action( 'pediment_form_submitted', 'pediment_form_persist_submission', 10, 2 );

function pediment_form_persist_submission( array $submission, $request ): void {
	$post_id     = (int) ( $submission['post_id'] ?? 0 );
	$destination = (string) ( $submission['destination'] ?? '' );
	$fields      = isset( $submission['fields'] ) && is_array( $submission['fields'] ) ? $submission['fields'] : array();

	$source_title = $post_id > 0 ? get_the_title( $post_id ) : '';
	$title        = sprintf(
		/* translators: 1: source page title, 2: submission date */
		__( '%1$s — %2$s', 'pediment' ),
		'' !== $source_title ? $source_title : __( 'Form', 'pediment' ),
		wp_date( 'Y-m-d H:i' )
	);

	$sanitized_fields = array();
	foreach ( $fields as $key => $data ) {
		$row = is_array( $data ) ? $data : array();
		foreach ( $row as $field_key => $field_val ) {
			if ( 'label' === $field_key ) {
				$row[ $field_key ] = sanitize_text_field( (string) $field_val );
			} elseif ( 'value' === $field_key ) {
				$row[ $field_key ] = sanitize_textarea_field( (string) $field_val );
			}
		}
		$sanitized_fields[ $key ] = $row;
	}

	$lines = array();
	foreach ( $sanitized_fields as $data ) {
		$lines[] = sprintf( '%s: %s', (string) ( $data['label'] ?? '' ), (string) ( $data['value'] ?? '' ) );
	}

	$new_id = wp_insert_post(
		array(
			'post_type'    => PEDIMENT_FORM_CPT,
			'post_status'  => 'publish',
			'post_title'   => $title,
			'post_content' => implode( "\n", $lines ),
		),
		true
	);
	if ( is_wp_error( $new_id ) || ! $new_id ) {
		return;
	}

	update_post_meta( $new_id, '_fields', wp_json_encode( $sanitized_fields ) );
	update_post_meta( $new_id, '_source_post_id', $post_id );
	update_post_meta( $new_id, '_destination', sanitize_text_field( $destination ) );
	update_post_meta( $new_id, '_delivery_status', 'pending' );
}

add_filter(
	'manage_' . PEDIMENT_FORM_CPT . '_posts_columns',
	function ( array $cols ) {
		return array(
			'cb'          => $cols['cb'] ?? '',
			'title'       => __( 'Submission', 'pediment' ),
			'destination' => __( 'Destination', 'pediment' ),
			'date'        => __( 'Submitted', 'pediment' ),
		);
	}
);

add_action(
	'manage_' . PEDIMENT_FORM_CPT . '_posts_custom_column',
	function ( $col, $post_id ) {
		if ( 'destination' === $col ) {
			$dest = (string) get_post_meta( $post_id, '_destination', true );
			echo esc_html( '' !== $dest ? $dest : __( '(default)', 'pediment' ) );
		}
	},
	10,
	2
);

add_action( PEDIMENT_FORM_CRON_HOOK, 'pediment_form_cleanup' );

function pediment_form_cleanup(): void {
	$days = (int) apply_filters( 'pediment_form_retention_days', 90 );
	if ( $days <= 0 ) {
		return;
	}
	$ts = strtotime( '-' . $days . ' days' );
	if ( false === $ts ) {
		return;
	}
	$cutoff = gmdate( 'Y-m-d H:i:s', $ts );

	$stale = get_posts(
		array(
			'post_type'      => PEDIMENT_FORM_CPT,
			'post_status'    => 'any',
			'posts_per_page' => 200,
			'fields'         => 'ids',
			'date_query'     => array(
				array(
					'before'    => $cutoff,
					'column'    => 'post_date_gmt',
					'inclusive' => true,
				),
			),
		)
	);
	foreach ( $stale as $post_id ) {
		wp_delete_post( $post_id, true );
	}
}

function pediment_form_schedule_cleanup(): void {
	if ( ! wp_next_scheduled( PEDIMENT_FORM_CRON_HOOK ) ) {
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', PEDIMENT_FORM_CRON_HOOK );
	}
}

function pediment_form_unschedule_cleanup(): void {
	wp_clear_scheduled_hook( PEDIMENT_FORM_CRON_HOOK );
}
