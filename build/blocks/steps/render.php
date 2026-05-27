<?php
/**
 * Server-side render for pediment/steps.
 *
 * @var array  $attributes
 * @var string $content
 */

$wrapper = get_block_wrapper_attributes( array( 'class' => 'starter-steps' ) );
?>
<div <?php echo $wrapper; // phpcs:ignore WordPress.Security.EscapeOutput ?>>
	<?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput -- inner blocks pre-rendered ?>
</div>
