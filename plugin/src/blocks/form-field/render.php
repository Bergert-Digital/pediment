<?php
/**
 * Server-side render for pediment/form-field.
 *
 * @var array $attributes
 */

$pediment_type  = isset( $attributes['fieldType'] ) ? (string) $attributes['fieldType'] : 'text';
$pediment_label = isset( $attributes['label'] ) ? (string) $attributes['label'] : '';
$pediment_name  = isset( $attributes['fieldName'] ) && '' !== $attributes['fieldName']
	? pediment_form_slug( (string) $attributes['fieldName'] )
	: pediment_form_slug( $pediment_label );
$pediment_req   = ! empty( $attributes['required'] );
$pediment_ph    = isset( $attributes['placeholder'] ) ? (string) $attributes['placeholder'] : '';
$pediment_help  = isset( $attributes['helpText'] ) ? (string) $attributes['helpText'] : '';
$pediment_opts  = isset( $attributes['options'] ) && is_array( $attributes['options'] ) ? $attributes['options'] : array();

$pediment_req_attr = $pediment_req ? ' required' : '';
$pediment_ph_attr  = '' !== $pediment_ph ? ' placeholder="' . esc_attr( $pediment_ph ) . '"' : '';

$pediment_field_class = 'pediment-form__field';
if ( 'checkbox' === $pediment_type ) {
	$pediment_field_class .= ' pediment-form__field--checkbox';
}

$pediment_file_hint = '';
if ( 'file' === $pediment_type ) {
	$pediment_field_class .= ' pediment-form__field--file';
	$pediment_file_exts    = pediment_form_file_extensions( $attributes['fileTypes'] ?? PEDIMENT_FORM_FILE_DEFAULT_TYPES );
	$pediment_file_max     = pediment_form_file_max_bytes( $attributes['maxSize'] ?? PEDIMENT_FORM_FILE_DEFAULT_MB );
	$pediment_file_hint    = pediment_form_file_hint( $pediment_file_exts, $pediment_file_max );
}

ob_start();
?>
<label class="<?php echo esc_attr( $pediment_field_class ); ?>">
	<span class="pediment-form__label"><?php echo esc_html( '' !== $pediment_label ? $pediment_label : $pediment_name ); ?><?php echo $pediment_req ? ' <span aria-hidden="true">*</span>' : ''; // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
	<?php
	switch ( $pediment_type ) :
		case 'textarea':
			?>
			<textarea data-pediment-field name="<?php echo esc_attr( $pediment_name ); ?>" rows="5"<?php echo $pediment_req_attr . $pediment_ph_attr; // phpcs:ignore WordPress.Security.EscapeOutput ?>></textarea>
			<?php
			break;
		case 'select':
			?>
			<select data-pediment-field name="<?php echo esc_attr( $pediment_name ); ?>"<?php echo $pediment_req_attr; // phpcs:ignore WordPress.Security.EscapeOutput ?>>
				<option value=""><?php esc_html_e( 'Choose…', 'pediment' ); ?></option>
				<?php foreach ( $pediment_opts as $pediment_opt ) : ?>
					<option value="<?php echo esc_attr( (string) ( $pediment_opt['value'] ?? '' ) ); ?>"><?php echo esc_html( (string) ( $pediment_opt['label'] ?? '' ) ); ?></option>
				<?php endforeach; ?>
			</select>
			<?php
			break;
		case 'checkbox':
			?>
			<input data-pediment-field type="checkbox" name="<?php echo esc_attr( $pediment_name ); ?>" value="1"<?php echo $pediment_req_attr; // phpcs:ignore WordPress.Security.EscapeOutput ?> />
			<?php
			break;
		case 'file':
			$pediment_size_error = sprintf(
				/* translators: %s: size limit, e.g. "10 MB" */
				__( 'The file is too large. The limit is %s.', 'pediment' ),
				pediment_form_file_size_label( $pediment_file_max )
			);
			?>
			<input data-pediment-field type="file" name="<?php echo esc_attr( $pediment_name ); ?>" accept="<?php echo esc_attr( '.' . implode( ',.', $pediment_file_exts ) ); ?>" data-max-size="<?php echo esc_attr( (string) $pediment_file_max ); ?>" data-size-error="<?php echo esc_attr( $pediment_size_error ); ?>"<?php echo ( ! empty( $attributes['multiple'] ) ? ' multiple data-max-files="' . esc_attr( (string) PEDIMENT_FORM_FILE_MAX_COUNT ) . '"' : '' ) . $pediment_req_attr; // phpcs:ignore WordPress.Security.EscapeOutput ?> />
			<?php
			break;
		case 'radio':
			foreach ( $pediment_opts as $pediment_opt ) :
				?>
				<label class="pediment-form__radio"><input data-pediment-field type="radio" name="<?php echo esc_attr( $pediment_name ); ?>" value="<?php echo esc_attr( (string) ( $pediment_opt['value'] ?? '' ) ); ?>"<?php echo $pediment_req_attr; // phpcs:ignore WordPress.Security.EscapeOutput ?> /> <?php echo esc_html( (string) ( $pediment_opt['label'] ?? '' ) ); ?></label>
				<?php
			endforeach;
			break;
		default:
			?>
			<input data-pediment-field type="<?php echo esc_attr( $pediment_type ); ?>" name="<?php echo esc_attr( $pediment_name ); ?>"<?php echo $pediment_req_attr . $pediment_ph_attr; // phpcs:ignore WordPress.Security.EscapeOutput ?> />
			<?php
	endswitch;
	?>
</label>
<?php if ( '' !== $pediment_help ) : ?>
	<small class="pediment-form__help"><?php echo esc_html( $pediment_help ); ?></small>
<?php endif; ?>
<?php if ( '' !== $pediment_file_hint ) : ?>
	<small class="pediment-form__help pediment-form__help--file"><?php echo esc_html( $pediment_file_hint ); ?></small>
<?php endif; ?>
<?php
echo ob_get_clean(); // phpcs:ignore WordPress.Security.EscapeOutput
