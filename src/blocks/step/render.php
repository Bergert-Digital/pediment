<?php
/**
 * Server-side render for starter/step. Number is generated via CSS counter.
 *
 * @var array $attributes
 */

$title = isset( $attributes['title'] ) ? (string) $attributes['title'] : '';
$text  = isset( $attributes['text'] ) ? (string) $attributes['text'] : '';

if ( '' === $title && '' === $text ) {
	return '';
}

$wrapper = get_block_wrapper_attributes( array( 'class' => 'starter-step' ) );
ob_start();
?>
<div <?php echo $wrapper; // phpcs:ignore WordPress.Security.EscapeOutput ?>>
	<span class="starter-step__num" aria-hidden="true"></span>
	<div class="starter-step__body">
		<?php if ( '' !== $title ) : ?>
			<h3 class="starter-step__title"><?php echo wp_kses_post( $title ); ?></h3>
		<?php endif; ?>
		<?php if ( '' !== $text ) : ?>
			<p class="starter-step__text"><?php echo wp_kses_post( $text ); ?></p>
		<?php endif; ?>
	</div>
</div>
<?php
echo ob_get_clean();
