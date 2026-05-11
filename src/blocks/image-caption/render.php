<?php
/**
 * Server-side render for starter/image-caption.
 *
 * @var array $attributes
 */

$media_id     = isset( $attributes['mediaId'] )     ? (int) $attributes['mediaId']        : 0;
$caption      = isset( $attributes['caption'] )     ? (string) $attributes['caption']     : '';
$alt_override = isset( $attributes['altOverride'] ) ? (string) $attributes['altOverride'] : '';

if ( ! $media_id ) {
	return '';
}

$alt      = '' !== $alt_override ? $alt_override : (string) get_post_meta( $media_id, '_wp_attachment_image_alt', true );
$img_html = wp_get_attachment_image( $media_id, 'large', false, array( 'alt' => $alt, 'class' => 'starter-image-caption__img' ) );

if ( ! $img_html ) {
	return '';
}

$wrapper = get_block_wrapper_attributes( array( 'class' => 'starter-image-caption' ) );
ob_start();
?>
<figure <?php echo $wrapper; // phpcs:ignore WordPress.Security.EscapeOutput ?>>
	<?php echo $img_html; // phpcs:ignore WordPress.Security.EscapeOutput -- pre-escaped by wp_get_attachment_image ?>
	<?php if ( '' !== $caption ) : ?>
		<figcaption class="starter-image-caption__caption"><?php echo wp_kses_post( $caption ); ?></figcaption>
	<?php endif; ?>
</figure>
<?php
echo ob_get_clean();
