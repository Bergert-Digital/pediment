<?php
/**
 * Server-side render for starter/cta.
 *
 * @var array $attributes
 */

$cta_title     = isset( $attributes['title'] ) ? (string) $attributes['title'] : '';
$body          = isset( $attributes['body'] ) ? (string) $attributes['body'] : '';
$primary_text  = isset( $attributes['primaryText'] ) ? (string) $attributes['primaryText'] : '';
$primary_url   = isset( $attributes['primaryUrl'] ) ? (string) $attributes['primaryUrl'] : '';
$secondary_t   = isset( $attributes['secondaryText'] ) ? (string) $attributes['secondaryText'] : '';
$secondary_url = isset( $attributes['secondaryUrl'] ) ? (string) $attributes['secondaryUrl'] : '';

$wrapper = get_block_wrapper_attributes( array( 'class' => 'starter-cta' ) );

ob_start();
?>
<section <?php echo $wrapper; // phpcs:ignore WordPress.Security.EscapeOutput ?>>
	<?php if ( $cta_title ) : ?>
		<h2 class="starter-cta__title"><?php echo wp_kses_post( $cta_title ); ?></h2>
	<?php endif; ?>
	<?php if ( $body ) : ?>
		<p class="starter-cta__body"><?php echo wp_kses_post( $body ); ?></p>
	<?php endif; ?>
	<div class="starter-cta__actions">
		<?php if ( $primary_text && $primary_url ) : ?>
			<a class="starter-cta__btn starter-cta__btn--primary" href="<?php echo esc_url( $primary_url ); ?>"><?php echo wp_kses_post( $primary_text ); ?></a>
		<?php endif; ?>
		<?php if ( $secondary_t && $secondary_url ) : ?>
			<a class="starter-cta__btn starter-cta__btn--secondary" href="<?php echo esc_url( $secondary_url ); ?>"><?php echo wp_kses_post( $secondary_t ); ?></a>
		<?php endif; ?>
	</div>
</section>
<?php
echo ob_get_clean();
