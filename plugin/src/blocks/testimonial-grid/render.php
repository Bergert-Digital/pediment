<?php
/**
 * Server-side render for pediment/testimonial-grid.
 *
 * @var array  $attributes
 * @var string $content
 */

$wrapper = get_block_wrapper_attributes( array( 'class' => 'starter-testimonial-grid' ) );
?>
<section <?php echo $wrapper; // phpcs:ignore WordPress.Security.EscapeOutput ?>>
	<?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput -- inner blocks pre-rendered ?>
</section>
