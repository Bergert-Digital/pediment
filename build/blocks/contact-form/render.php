<?php
/**
 * Server-side render for pediment/contact-form.
 *
 * @var array $attributes
 */

$include_phone   = ! empty( $attributes['includePhone'] );
$recipient       = isset( $attributes['recipientOverride'] ) ? (string) $attributes['recipientOverride'] : '';
$success_message = isset( $attributes['successMessage'] ) ? (string) $attributes['successMessage'] : '';

$wrapper = get_block_wrapper_attributes(
	array(
		'class'           => 'starter-contact-form',
		'data-success'    => $success_message,
		'data-recipient'  => $recipient,
		'data-rest-url'   => esc_url_raw( rest_url( 'pediment/v1/contact' ) ),
		'data-rest-nonce' => wp_create_nonce( 'wp_rest' ),
	)
);

$timestamp = time();

ob_start();
?>
<form <?php echo $wrapper; // phpcs:ignore WordPress.Security.EscapeOutput ?>>
	<label class="starter-contact-form__field">
		<span><?php esc_html_e( 'Name', 'pediment' ); ?></span>
		<input type="text" name="name" required />
	</label>
	<label class="starter-contact-form__field">
		<span><?php esc_html_e( 'Email', 'pediment' ); ?></span>
		<input type="email" name="email" required />
	</label>
	<?php if ( $include_phone ) : ?>
		<label class="starter-contact-form__field">
			<span><?php esc_html_e( 'Phone', 'pediment' ); ?></span>
			<input type="tel" name="phone" />
		</label>
	<?php endif; ?>
	<label class="starter-contact-form__field">
		<span><?php esc_html_e( 'Message', 'pediment' ); ?></span>
		<textarea name="message" rows="5" required></textarea>
	</label>

	<div class="starter-contact-form__hp" aria-hidden="true">
		<label>Leave this empty <input type="text" name="hp_field" tabindex="-1" autocomplete="off" /></label>
	</div>
	<input type="hidden" name="_t" value="<?php echo esc_attr( (string) $timestamp ); ?>" />

	<button type="submit" class="starter-contact-form__submit"><?php esc_html_e( 'Send', 'pediment' ); ?></button>

	<p class="starter-contact-form__status" role="status" hidden></p>
</form>
<?php
echo ob_get_clean();
