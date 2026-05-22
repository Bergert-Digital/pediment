<?php
/**
 * Server-side render for pediment/logo-cloud.
 *
 * @var array  $attributes
 * @var string $content
 */

$caption = isset( $attributes['caption'] ) ? (string) $attributes['caption'] : '';
$wrapper = get_block_wrapper_attributes( array( 'class' => 'starter-logo-cloud' ) );
ob_start();
?>
<section <?php echo $wrapper; // phpcs:ignore WordPress.Security.EscapeOutput ?>>
	<?php if ( '' !== $caption ) : ?>
		<p class="starter-logo-cloud__caption"><?php echo wp_kses_post( $caption ); ?></p>
	<?php endif; ?>
	<div class="starter-logo-cloud__row">
		<?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput -- inner blocks pre-rendered ?>
	</div>
</section>
<?php
echo ob_get_clean();
