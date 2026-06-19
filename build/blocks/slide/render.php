<?php
/**
 * Server-side render for pediment/slide.
 *
 * @var array  $attributes
 * @var string $content    Pre-rendered inner blocks (the panel content).
 */

$media_id     = isset( $attributes['mediaId'] ) ? (int) $attributes['mediaId'] : 0;
$alt_override = isset( $attributes['altOverride'] ) ? (string) $attributes['altOverride'] : '';

$img_html = '';
if ( $media_id ) {
	$alt      = '' !== $alt_override ? $alt_override : (string) get_post_meta( $media_id, '_wp_attachment_image_alt', true );
	$img_html = wp_get_attachment_image(
		$media_id,
		'large',
		false,
		array(
			'alt'   => $alt,
			'class' => 'starter-slide__img',
		)
	);
}

$wrapper = get_block_wrapper_attributes( array( 'class' => 'starter-slide' ) );
ob_start();
?>
<div <?php echo $wrapper; // phpcs:ignore WordPress.Security.EscapeOutput ?>>
	<figure class="starter-slide__media">
		<?php if ( '' !== $img_html ) : ?>
			<?php echo $img_html; // phpcs:ignore WordPress.Security.EscapeOutput -- pre-escaped by wp_get_attachment_image ?>
		<?php else : ?>
			<span class="starter-slide__placeholder" aria-hidden="true">
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
					<rect x="3" y="3" width="18" height="18" rx="2" />
					<circle cx="8.5" cy="8.5" r="1.5" />
					<path d="M21 15l-5-5L5 21" />
				</svg>
			</span>
		<?php endif; ?>
	</figure>
	<div class="starter-slide__panel">
		<?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput -- inner blocks pre-rendered ?>
	</div>
</div>
<?php
echo ob_get_clean();
