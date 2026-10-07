<?php
/**
 * Form submission storage CPT, persistence, admin columns, and retention.
 *
 * @package Pediment
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const PEDIMENT_FORM_RETENTION_OPTION = 'pediment_form_retention_days';

/**
 * Effective retention window in days (0 = keep forever). Saved setting first,
 * then the long-standing filter.
 */
function pediment_form_retention_days(): int {
	$days = (int) get_option( PEDIMENT_FORM_RETENTION_OPTION, 90 );
	return (int) apply_filters( 'pediment_form_retention_days', $days );
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
					'edit_item'     => __( 'Form submission', 'pediment' ),
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

	// update_post_meta() unslashes; without wp_slash() the JSON loses its
	// backslashes (\u00f6 → u00f6, \n → n, \" → " which breaks decoding).
	update_post_meta( $new_id, '_fields', wp_slash( wp_json_encode( $sanitized_fields ) ) );
	update_post_meta( $new_id, '_source_post_id', $post_id );
	update_post_meta( $new_id, '_destination', sanitize_text_field( $destination ) );
	update_post_meta( $new_id, '_delivery_status', 'pending' );

	/**
	 * Fires after a submission is stored, carrying the stored post id so delivery
	 * can record its result. Plan 2's delivery engine hooks this.
	 */
	do_action( 'pediment_form_stored', (int) $new_id, $submission );
}

add_filter(
	'manage_' . PEDIMENT_FORM_CPT . '_posts_columns',
	function ( array $cols ) {
		return array(
			'cb'          => $cols['cb'] ?? '',
			'title'       => __( 'Submission', 'pediment' ),
			'fields'      => __( 'Details', 'pediment' ),
			'destination' => __( 'Destination', 'pediment' ),
			'delivery'    => __( 'Delivery', 'pediment' ),
			'date'        => __( 'Submitted', 'pediment' ),
		);
	}
);

/**
 * Build a readable "label: value" summary of a submission's stored fields.
 *
 * @param int $post_id The form_submission post ID.
 * @return string Plain-text summary (unescaped); empty when no fields stored.
 */
function pediment_form_fields_summary( int $post_id ): string {
	$decoded = json_decode( (string) get_post_meta( $post_id, '_fields', true ), true );
	if ( ! is_array( $decoded ) ) {
		return '';
	}
	$parts = array();
	foreach ( $decoded as $row ) {
		if ( ! is_array( $row ) ) {
			continue;
		}
		$label = trim( (string) ( $row['label'] ?? '' ) );
		$value = trim( (string) ( $row['value'] ?? '' ) );
		if ( '' === $label && '' === $value ) {
			continue;
		}
		$parts[] = sprintf( '%s: %s', $label, $value );
	}
	return implode( ' · ', $parts );
}

/**
 * Human-readable delivery status of a submission, with the HTTP status appended
 * when delivery did not succeed.
 *
 * @param int $post_id The form_submission post ID.
 * @return string Plain-text label (unescaped).
 */
function pediment_form_delivery_label( int $post_id ): string {
	$status = (string) get_post_meta( $post_id, '_delivery_status', true );
	$http   = (string) get_post_meta( $post_id, '_delivery_http_status', true );
	$labels = array(
		'sent'           => __( 'Sent', 'pediment' ),
		'failed'         => __( 'Failed', 'pediment' ),
		'pending'        => __( 'Pending', 'pediment' ),
		'no_destination' => __( 'No destination', 'pediment' ),
	);
	$text   = $labels[ $status ] ?? ( '' !== $status ? $status : __( 'Pending', 'pediment' ) );
	if ( '' !== $http && 'sent' !== $status ) {
		$text .= ' (' . $http . ')';
	}
	return $text;
}

add_action(
	'manage_' . PEDIMENT_FORM_CPT . '_posts_custom_column',
	function ( $col, $post_id ) {
		if ( 'destination' === $col ) {
			$dest = (string) get_post_meta( $post_id, '_destination', true );
			echo esc_html( '' !== $dest ? $dest : __( '(default)', 'pediment' ) );
		} elseif ( 'fields' === $col ) {
			$summary = pediment_form_fields_summary( (int) $post_id );
			echo esc_html( '' !== $summary ? $summary : __( '—', 'pediment' ) );
		} elseif ( 'delivery' === $col ) {
			echo esc_html( pediment_form_delivery_label( (int) $post_id ) );
		}
	},
	10,
	2
);

add_action(
	'add_meta_boxes_' . PEDIMENT_FORM_CPT,
	function () {
		add_meta_box(
			'pediment-submission-details',
			__( 'Submission details', 'pediment' ),
			'pediment_form_render_submission_box',
			PEDIMENT_FORM_CPT,
			'normal',
			'high'
		);
	}
);

/**
 * Escape a submitted value for display: line breaks kept, email addresses
 * linked.
 *
 * @param string $value Raw stored value.
 * @return string Escaped HTML.
 */
function pediment_form_format_value( string $value ): string {
	$value = trim( $value );
	if ( '' === $value ) {
		return esc_html__( '—', 'pediment' );
	}
	if ( is_email( $value ) ) {
		return sprintf( '<a href="%s">%s</a>', esc_url( 'mailto:' . $value ), esc_html( $value ) );
	}
	return nl2br( esc_html( $value ) );
}

/**
 * Read-only "Submission details" meta box: every stored field, then the
 * source page, destination, delivery status, and submission date.
 *
 * @param WP_Post $post The form_submission post.
 */
function pediment_form_render_submission_box( WP_Post $post ): void {
	$decoded = json_decode( (string) get_post_meta( $post->ID, '_fields', true ), true );

	echo '<table class="widefat striped" style="margin-bottom:1em"><tbody>';
	if ( is_array( $decoded ) ) {
		foreach ( $decoded as $key => $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$label = trim( (string) ( $row['label'] ?? '' ) );
			printf(
				'<tr><th scope="row" style="width:25%%">%s</th><td>%s</td></tr>',
				esc_html( '' !== $label ? $label : (string) $key ),
				pediment_form_format_value( (string) ( $row['value'] ?? '' ) ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by pediment_form_format_value().
			);
		}
	} else {
		printf(
			'<tr><td>%s</td></tr>',
			pediment_form_format_value( (string) $post->post_content ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by pediment_form_format_value().
		);
	}
	echo '</tbody></table>';

	$source_id = (int) get_post_meta( $post->ID, '_source_post_id', true );
	$source    = esc_html__( '—', 'pediment' );
	if ( $source_id > 0 ) {
		$source_post = get_post( $source_id );
		if ( $source_post instanceof WP_Post ) {
			$title  = get_the_title( $source_post );
			$title  = '' !== $title ? $title : '#' . $source_id;
			$link   = get_edit_post_link( $source_post, 'raw' );
			$source = $link
				? sprintf( '<a href="%s">%s</a>', esc_url( $link ), esc_html( $title ) )
				: esc_html( $title );
		} else {
			/* translators: %d: ID of the deleted source page. */
			$source = esc_html( sprintf( __( 'Deleted page (#%d)', 'pediment' ), $source_id ) );
		}
	}

	$destination = (string) get_post_meta( $post->ID, '_destination', true );
	$rows        = array(
		__( 'Source page', 'pediment' ) => $source,
		__( 'Destination', 'pediment' ) => esc_html( '' !== $destination ? $destination : __( '(default)', 'pediment' ) ),
		__( 'Delivery', 'pediment' )    => esc_html( pediment_form_delivery_label( $post->ID ) ),
		__( 'Submitted', 'pediment' )   => esc_html( get_the_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $post ) ),
	);

	echo '<table class="widefat striped"><tbody>';
	foreach ( $rows as $label => $html ) {
		printf(
			'<tr><th scope="row" style="width:25%%">%s</th><td>%s</td></tr>',
			esc_html( $label ),
			$html // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
		);
	}
	echo '</tbody></table>';
}

add_action( PEDIMENT_FORM_CRON_HOOK, 'pediment_form_cleanup' );

function pediment_form_cleanup(): void {
	$days = pediment_form_retention_days();
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

add_filter(
	'post_row_actions',
	function ( array $actions, WP_Post $post ): array {
		if ( PEDIMENT_FORM_CPT !== $post->post_type || ! current_user_can( 'manage_options' ) ) {
			return $actions;
		}
		if ( 'sent' === (string) get_post_meta( $post->ID, '_delivery_status', true ) ) {
			return $actions;
		}
		$url                       = wp_nonce_url(
			admin_url( 'admin-post.php?action=pediment_form_retry&submission=' . $post->ID ),
			'pediment_form_retry_' . $post->ID
		);
		$actions['pediment_retry'] = sprintf(
			'<a href="%s">%s</a>',
			esc_url( $url ),
			esc_html__( 'Retry delivery', 'pediment' )
		);
		return $actions;
	},
	10,
	2
);

add_action( 'admin_post_pediment_form_retry', 'pediment_form_handle_retry' );

/**
 * Re-attempt delivery for a single submission from the admin list.
 */
function pediment_form_handle_retry(): void {
	$id = isset( $_GET['submission'] ) ? absint( wp_unslash( $_GET['submission'] ) ) : 0;
	if ( $id <= 0 || ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Not allowed.', 'pediment' ) );
	}
	check_admin_referer( 'pediment_form_retry_' . $id );
	pediment_form_deliver( $id );
	wp_safe_redirect( add_query_arg( array( 'post_type' => PEDIMENT_FORM_CPT ), admin_url( 'edit.php' ) ) );
	exit;
}
