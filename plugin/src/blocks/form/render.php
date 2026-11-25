<?php
/**
 * Server-side render for pediment/form.
 *
 * @var array    $attributes
 * @var string   $content
 * @var WP_Block $block
 */

$pediment_success = isset( $attributes['successMessage'] ) && '' !== $attributes['successMessage']
	? (string) $attributes['successMessage']
	: __( 'Thanks — we will be in touch.', 'pediment' );
$pediment_heading = isset( $attributes['successHeading'] ) && '' !== $attributes['successHeading']
	? (string) $attributes['successHeading']
	: __( 'Message sent', 'pediment' );
$pediment_submit  = isset( $attributes['submitLabel'] ) && '' !== $attributes['submitLabel']
	? (string) $attributes['submitLabel']
	: __( 'Send', 'pediment' );

$pediment_inner    = isset( $block->parsed_block['innerBlocks'] ) && is_array( $block->parsed_block['innerBlocks'] )
	? $block->parsed_block['innerBlocks']
	: array();
$pediment_fields   = pediment_form_collect_fields( $pediment_inner );
$pediment_form_key = pediment_form_form_key( $pediment_fields );
$pediment_post_id  = (int) get_the_ID();

// Core serializes spacing padding/margin to inline styles automatically, but
// leaves blockGap for layout support — which this block doesn't use — so wire
// the chosen Block Spacing value to our own --pediment-form-gap custom property.
$pediment_gap = isset( $attributes['style']['spacing']['blockGap'] )
	? (string) $attributes['style']['spacing']['blockGap']
	: '';
if ( '' !== $pediment_gap && preg_match( '/^var:preset\|spacing\|(.+)$/', $pediment_gap, $pediment_gap_m ) ) {
	$pediment_gap = 'var(--wp--preset--spacing--' . $pediment_gap_m[1] . ')';
}

$pediment_wrapper_args = array(
	'class'         => 'pediment-form',
	'data-rest-url' => esc_url_raw( rest_url( PEDIMENT_FORM_NAMESPACE . PEDIMENT_FORM_ROUTE ) ),
	'data-post-id'  => (string) $pediment_post_id,
	'data-form-key' => $pediment_form_key,
);
if ( '' !== $pediment_gap ) {
	$pediment_wrapper_args['style'] = '--pediment-form-gap:' . $pediment_gap . ';';
}
if ( in_array( 'file', wp_list_pluck( $pediment_fields, 'type' ), true ) ) {
	// The browser checks the total upload against PHP's limit before it sends,
	// so a too-large request gets a clear message instead of an empty body.
	$pediment_max_upload                        = (int) wp_max_upload_size();
	$pediment_wrapper_args['data-max-upload']   = (string) $pediment_max_upload;
	$pediment_wrapper_args['data-upload-error'] = sprintf(
		/* translators: %s: size limit, e.g. "10 MB" */
		__( 'The files are too large together. The limit is %s.', 'pediment' ),
		pediment_form_file_size_label( $pediment_max_upload )
	);
}

$pediment_wrapper = get_block_wrapper_attributes( $pediment_wrapper_args );

$pediment_timestamp  = time();
$pediment_success_id = wp_unique_id( 'pediment-form-success-' );

ob_start();
?>
<form novalidate <?php echo $pediment_wrapper; // phpcs:ignore WordPress.Security.EscapeOutput ?>>
	<?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput -- inner field blocks escape their own output. ?>

	<div class="pediment-form__hp" aria-hidden="true">
		<label>Leave this empty <input type="text" name="hp_field" tabindex="-1" autocomplete="off" /></label>
	</div>
	<input type="hidden" name="_t" value="<?php echo esc_attr( (string) $pediment_timestamp ); ?>" />

	<button type="submit" class="pediment-form__submit"><?php echo esc_html( $pediment_submit ); ?></button>

	<p class="pediment-form__status" role="status" hidden></p>

	<div class="pediment-form__success" hidden>
		<span class="pediment-form__success-icon" aria-hidden="true">
			<svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" focusable="false"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg>
		</span>
		<p class="pediment-form__success-heading" tabindex="-1" aria-describedby="<?php echo esc_attr( $pediment_success_id ); ?>"><?php echo esc_html( $pediment_heading ); ?></p>
		<p class="pediment-form__success-message" id="<?php echo esc_attr( $pediment_success_id ); ?>"><?php echo esc_html( $pediment_success ); ?></p>
		<button type="button" class="pediment-form__reset"><?php esc_html_e( 'Send another message', 'pediment' ); ?></button>
	</div>
</form>
<?php
echo ob_get_clean(); // phpcs:ignore WordPress.Security.EscapeOutput
