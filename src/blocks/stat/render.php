<?php
/**
 * Server-side render for starter/stat.
 *
 * @var array $attributes
 */

$value   = isset( $attributes['value'] )   ? (string) $attributes['value']   : '';
$label   = isset( $attributes['label'] )   ? (string) $attributes['label']   : '';
$context = isset( $attributes['context'] ) ? (string) $attributes['context'] : '';

if ( '' === $value && '' === $label ) {
	return '';
}

$wrapper = get_block_wrapper_attributes( array( 'class' => 'starter-stat' ) );
ob_start();
?>
<div <?php echo $wrapper; // phpcs:ignore WordPress.Security.EscapeOutput ?>>
	<strong class="starter-stat__value"><?php echo wp_kses_post( $value ); ?></strong>
	<span    class="starter-stat__label"><?php echo wp_kses_post( $label ); ?></span>
	<?php if ( '' !== $context ) : ?>
		<span class="starter-stat__context"><?php echo wp_kses_post( $context ); ?></span>
	<?php endif; ?>
</div>
<?php
echo ob_get_clean();
