<?php
/**
 * Server-side render for starter/mega-column.
 *
 * @var array  $attributes
 * @var string $content
 */

$heading = isset( $attributes['heading'] ) ? trim( (string) $attributes['heading'] ) : '';
$wrapper = get_block_wrapper_attributes( array( 'class' => 'starter-mega-column' ) );
ob_start();
?>
<div <?php echo $wrapper; // phpcs:ignore WordPress.Security.EscapeOutput ?>>
	<?php if ( '' !== $heading ) : ?>
		<p class="starter-mega-column__heading"><?php echo wp_kses_post( $heading ); ?></p>
	<?php endif; ?>
	<div class="starter-mega-column__links">
		<?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput -- inner blocks pre-rendered ?>
	</div>
</div>
<?php
echo ob_get_clean();
