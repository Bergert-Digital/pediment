<?php
/**
 * Server-side render for starter/section-head.
 *
 * @var array $attributes
 */

$wrapper = get_block_wrapper_attributes( array( 'class' => 'starter-section-head' ) );
?>
<div <?php echo $wrapper; // phpcs:ignore WordPress.Security.EscapeOutput ?>></div>
