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
