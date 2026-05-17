<?php
/**
 * Server-side render for starter/mega-link.
 *
 * @var array $attributes
 */

$label = isset( $attributes['label'] ) ? trim( (string) $attributes['label'] ) : '';
$url   = isset( $attributes['url'] ) ? trim( (string) $attributes['url'] ) : '';
$desc  = isset( $attributes['description'] ) ? trim( (string) $attributes['description'] ) : '';
$icon  = isset( $attributes['icon'] ) ? trim( (string) $attributes['icon'] ) : '';

if ( '' === $label && '' === $url ) {
	return '';
}

$wrapper = get_block_wrapper_attributes( array( 'class' => 'starter-mega-link' ) );
ob_start();
?>
<a <?php echo $wrapper; // phpcs:ignore WordPress.Security.EscapeOutput ?> href="<?php echo esc_url( $url ); ?>">
	<?php
	if ( '' !== $icon && function_exists( 'starter_icon' ) ) {
		echo starter_icon( $icon, 'starter-mega-link__icon' ); // phpcs:ignore WordPress.Security.EscapeOutput -- theme-controlled sprite SVG
	}
	?>
	<span class="starter-mega-link__label"><?php echo wp_kses_post( $label ); ?></span>
	<?php if ( '' !== $desc ) : ?>
		<span class="starter-mega-link__desc"><?php echo wp_kses_post( $desc ); ?></span>
	<?php endif; ?>
</a>
<?php
echo ob_get_clean();
