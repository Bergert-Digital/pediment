<?php
/**
 * Server render for the example client block.
 *
 * @package __PEDIMENT_SLUG__
 *
 * @var array $attributes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$message = isset( $attributes['message'] ) ? (string) $attributes['message'] : '';

if ( '' === $message ) {
	return;
}
?>
<p <?php echo wp_kses_data( get_block_wrapper_attributes() ); ?>><?php echo esc_html( $message ); ?></p>
