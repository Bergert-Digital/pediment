<?php
/**
 * Generic form submission endpoint, field derivation, and validation.
 *
 * @package Pediment
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const PEDIMENT_FORM_NAMESPACE = 'pediment/v1';
const PEDIMENT_FORM_ROUTE     = '/forms';
const PEDIMENT_FORM_CPT       = 'form_submission';
const PEDIMENT_FORM_MIN_AGE   = 3;
const PEDIMENT_FORM_CRON_HOOK = 'pediment_form_cleanup';

/**
 * Normalize a label into a stable machine field name.
 */
function pediment_form_slug( string $label ): string {
	$slug = str_replace( '-', '_', sanitize_title( $label ) );
	return '' === $slug ? 'field' : $slug;
}

/**
 * Build the ordered field list from a form's direct child blocks.
 *
 * @param array<int,array<string,mixed>> $blocks Parsed inner blocks.
 * @return array<int,array<string,mixed>>
 */
function pediment_form_collect_fields( array $blocks ): array {
	$fields = array();
	foreach ( $blocks as $block ) {
		if ( ! is_array( $block ) || ( $block['blockName'] ?? '' ) !== 'pediment/form-field' ) {
			continue;
		}
		$attrs = isset( $block['attrs'] ) && is_array( $block['attrs'] ) ? $block['attrs'] : array();
		$label = isset( $attrs['label'] ) ? (string) $attrs['label'] : '';
		$name  = isset( $attrs['fieldName'] ) && '' !== $attrs['fieldName']
			? pediment_form_slug( (string) $attrs['fieldName'] )
			: pediment_form_slug( $label );

		$options = array();
		if ( isset( $attrs['options'] ) && is_array( $attrs['options'] ) ) {
			foreach ( $attrs['options'] as $opt ) {
				if ( is_array( $opt ) && isset( $opt['value'] ) ) {
					$options[] = (string) $opt['value'];
				}
			}
		}

		$fields[] = array(
			'name'     => $name,
			'type'     => isset( $attrs['fieldType'] ) ? (string) $attrs['fieldType'] : 'text',
			'label'    => '' !== $label ? $label : $name,
			'required' => ! empty( $attrs['required'] ),
			'options'  => $options,
		);
	}
	return $fields;
}

/**
 * Stable 12-char key identifying a form by its field-name set.
 *
 * @param array<int,array<string,mixed>> $fields
 */
function pediment_form_form_key( array $fields ): string {
	$names = wp_list_pluck( $fields, 'name' );
	return substr( md5( (string) wp_json_encode( $names ) ), 0, 12 );
}

/**
 * Recursively collect every pediment/form parsed block.
 *
 * @param array<int,array<string,mixed>> $blocks
 * @return array<int,array<string,mixed>>
 */
function pediment_form_find_forms( array $blocks ): array {
	$found = array();
	foreach ( $blocks as $block ) {
		if ( ! is_array( $block ) ) {
			continue;
		}
		if ( ( $block['blockName'] ?? '' ) === 'pediment/form' ) {
			$found[] = $block;
		}
		if ( ! empty( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
			$found = array_merge( $found, pediment_form_find_forms( $block['innerBlocks'] ) );
		}
	}
	return $found;
}

/**
 * Validate submitted values against the derived field list.
 *
 * @param array<int,array<string,mixed>> $fields
 * @param array<string,mixed>            $values
 * @return array<string,string> name => error message
 */
function pediment_form_validate( array $fields, array $values ): array {
	$errors = array();
	foreach ( $fields as $field ) {
		$name  = (string) $field['name'];
		$value = isset( $values[ $name ] ) ? trim( (string) $values[ $name ] ) : '';

		if ( $field['required'] && '' === $value ) {
			/* translators: %s: field label */
			$errors[ $name ] = sprintf( __( '%s is required.', 'pediment' ), $field['label'] );
			continue;
		}
		if ( '' === $value ) {
			continue;
		}

		switch ( $field['type'] ) {
			case 'email':
				if ( ! is_email( $value ) ) {
					$errors[ $name ] = __( 'Enter a valid email address.', 'pediment' );
				}
				break;
			case 'number':
				if ( ! is_numeric( $value ) ) {
					$errors[ $name ] = __( 'Enter a number.', 'pediment' );
				}
				break;
			case 'date':
				$d = DateTime::createFromFormat( 'Y-m-d', $value );
				if ( ! $d || $d->format( 'Y-m-d' ) !== $value ) {
					$errors[ $name ] = __( 'Enter a valid date.', 'pediment' );
				}
				break;
			case 'select':
			case 'radio':
				if ( ! empty( $field['options'] ) && ! in_array( $value, $field['options'], true ) ) {
					$errors[ $name ] = __( 'Choose a valid option.', 'pediment' );
				}
				break;
		}
	}
	return $errors;
}

/**
 * Find the form in a post that matches the submitted key.
 *
 * @return array{fields:array<int,array<string,mixed>>,destination:string}|null
 */
function pediment_form_locate( int $post_id, string $form_key ) {
	$post = get_post( $post_id );
	if ( ! $post instanceof WP_Post ) {
		return null;
	}
	foreach ( pediment_form_find_forms( parse_blocks( (string) $post->post_content ) ) as $form ) {
		$inner  = isset( $form['innerBlocks'] ) && is_array( $form['innerBlocks'] ) ? $form['innerBlocks'] : array();
		$fields = pediment_form_collect_fields( $inner );
		if ( pediment_form_form_key( $fields ) === $form_key ) {
			$attrs = isset( $form['attrs'] ) && is_array( $form['attrs'] ) ? $form['attrs'] : array();
			return array(
				'fields'      => $fields,
				'destination' => isset( $attrs['destination'] ) ? (string) $attrs['destination'] : '',
			);
		}
	}
	return null;
}

add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			PEDIMENT_FORM_NAMESPACE,
			PEDIMENT_FORM_ROUTE,
			array(
				'methods'             => 'POST',
				// Public by design (anonymous form). Anti-spam = honeypot + time-trap.
				'permission_callback' => '__return_true',
				'callback'            => 'pediment_form_handle_submission',
			)
		);
	}
);

/**
 * Handle a form submission via the REST API.
 *
 * @param WP_REST_Request $request Full request object.
 * @return WP_REST_Response|WP_Error
 */
function pediment_form_handle_submission( WP_REST_Request $request ) {
	$hp_field = (string) $request->get_param( 'hp_field' );
	$t_raw    = $request->get_param( '_t' );
	$t        = is_numeric( $t_raw ) ? (int) $t_raw : 0;
	$post_id  = (int) $request->get_param( 'post_id' );
	$form_key = (string) $request->get_param( 'form_key' );
	$values   = $request->get_param( 'fields' );
	$values   = is_array( $values ) ? $values : array();

	if ( '' !== $hp_field ) {
		return new WP_Error( 'pediment_spam', __( 'Submission rejected.', 'pediment' ), array( 'status' => 400 ) );
	}
	$now = time();
	if ( $t <= 0 || $t > $now || ( $now - $t ) < PEDIMENT_FORM_MIN_AGE ) {
		return new WP_Error( 'pediment_spam', __( 'Submission rejected.', 'pediment' ), array( 'status' => 400 ) );
	}

	$form = $post_id > 0 ? pediment_form_locate( $post_id, $form_key ) : null;
	if ( null === $form ) {
		return new WP_Error( 'pediment_unknown_form', __( 'Form not found.', 'pediment' ), array( 'status' => 400 ) );
	}

	$allowed = wp_list_pluck( $form['fields'], 'name' );
	foreach ( array_keys( $values ) as $key ) {
		if ( ! in_array( $key, $allowed, true ) ) {
			return new WP_Error( 'pediment_unknown_field', __( 'Unknown field.', 'pediment' ), array( 'status' => 400 ) );
		}
	}

	$errors = pediment_form_validate( $form['fields'], $values );
	if ( ! empty( $errors ) ) {
		return new WP_Error(
			'pediment_validation',
			__( 'Validation failed.', 'pediment' ),
			array(
				'status' => 400,
				'errors' => $errors,
			)
		);
	}

	$collected = array();
	foreach ( $form['fields'] as $field ) {
		$name               = (string) $field['name'];
		$collected[ $name ] = array(
			'label' => (string) $field['label'],
			'value' => isset( $values[ $name ] ) ? sanitize_textarea_field( (string) $values[ $name ] ) : '',
		);
	}

	$submission = array(
		'post_id'     => $post_id,
		'form_key'    => $form_key,
		'destination' => $form['destination'],
		'fields'      => $collected,
	);

	do_action( 'pediment_form_submitted', $submission, $request );

	return new WP_REST_Response( array( 'ok' => true ), 200 );
}
