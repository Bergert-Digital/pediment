<?php
/**
 * Server-side render for starter/feature.
 *
 * @var array $attributes
 */

$icon      = isset( $attributes['icon'] ) ? (string) $attributes['icon'] : '';
$title     = isset( $attributes['title'] ) ? (string) $attributes['title'] : '';
$text      = isset( $attributes['text'] ) ? (string) $attributes['text'] : '';
$link_text = isset( $attributes['linkText'] ) ? (string) $attributes['linkText'] : '';
$link_url  = isset( $attributes['linkUrl'] ) ? (string) $attributes['linkUrl'] : '';

if ( '' === $title && '' === $text ) {
	return '';
}

$wrapper = get_block_wrapper_attributes( array( 'class' => 'starter-feature' ) );
ob_start();
?>
<div <?php echo $wrapper; // phpcs:ignore WordPress.Security.EscapeOutput ?>>
	<?php if ( '' !== $icon && function_exists( 'starter_icon' ) ) : ?>
		<span class="starter-feature__ic"><?php echo starter_icon( $icon ); // phpcs:ignore WordPress.Security.EscapeOutput -- theme-controlled sprite ?></span>
	<?php endif; ?>
	<?php if ( '' !== $title ) : ?>
		<h3 class="starter-feature__title"><?php echo wp_kses_post( $title ); ?></h3>
	<?php endif; ?>
	<?php if ( '' !== $text ) : ?>
		<p class="starter-feature__text"><?php echo wp_kses_post( $text ); ?></p>
	<?php endif; ?>
	<?php if ( '' !== $link_text && '' !== $link_url ) : ?>
		<a class="starter-feature__more" href="<?php echo esc_url( $link_url ); ?>"><?php echo wp_kses_post( $link_text ); ?></a>
	<?php endif; ?>
</div>
<?php
echo ob_get_clean();
