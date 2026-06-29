<?php
/**
 * Server-side render for pediment/form.
 *
 * @var array    $attributes
 * @var string   $content
 * @var WP_Block $block
 */

$pediment_success = isset( $attributes['successMessage'] ) ? (string) $attributes['successMessage'] : '';
$pediment_submit  = isset( $attributes['submitLabel'] ) && '' !== $attributes['submitLabel']
	? (string) $attributes['submitLabel']
	: __( 'Send', 'pediment' );

$pediment_inner    = isset( $block->parsed_block['innerBlocks'] ) && is_array( $block->parsed_block['innerBlocks'] )
	? $block->parsed_block['innerBlocks']
	: array();
$pediment_fields   = pediment_form_collect_fields( $pediment_inner );
$pediment_form_key = pediment_form_form_key( $pediment_fields );
$pediment_post_id  = (int) get_the_ID();

$pediment_wrapper = get_block_wrapper_attributes(
	array(
		'class'         => 'pediment-form',
		'data-success'  => $pediment_success,
		'data-rest-url' => esc_url_raw( rest_url( PEDIMENT_FORM_NAMESPACE . PEDIMENT_FORM_ROUTE ) ),
		'data-post-id'  => (string) $pediment_post_id,
		'data-form-key' => $pediment_form_key,
	)
);

$pediment_timestamp = time();

ob_start();
?>
<form <?php echo $pediment_wrapper; // phpcs:ignore WordPress.Security.EscapeOutput ?>>
	<?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput -- inner field blocks escape their own output. ?>

	<div class="pediment-form__hp" aria-hidden="true">
		<label>Leave this empty <input type="text" name="hp_field" tabindex="-1" autocomplete="off" /></label>
	</div>
	<input type="hidden" name="_t" value="<?php echo esc_attr( (string) $pediment_timestamp ); ?>" />

	<button type="submit" class="pediment-form__submit"><?php echo esc_html( $pediment_submit ); ?></button>

	<p class="pediment-form__status" role="status" hidden></p>
</form>
<?php
echo ob_get_clean(); // phpcs:ignore WordPress.Security.EscapeOutput
