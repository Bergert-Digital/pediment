<?php
/**
 * Server-side render for pediment/legal-links.
 *
 * @var array $attributes
 *
 * @package Pediment
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$pediment_keys = $attributes['keys'] ?? array( 'legal-notice', 'privacy' );
$pediment_keys = is_array( $pediment_keys ) ? $pediment_keys : array();

$pediment_items = '';
$pediment_seen  = array();
foreach ( $pediment_keys as $pediment_key ) {
	$pediment_id = is_string( $pediment_key ) ? \Pediment\Seeder\SeededPage::id( sanitize_key( $pediment_key ) ) : 0;
	if ( $pediment_id <= 0 || isset( $pediment_seen[ $pediment_id ] ) ) {
		continue;
	}
	$pediment_seen[ $pediment_id ] = true;
	$pediment_url                  = get_permalink( $pediment_id );
	$pediment_title                = get_the_title( $pediment_id );
	if ( ! $pediment_url || '' === trim( wp_strip_all_tags( $pediment_title ) ) ) {
		continue;
	}
	$pediment_current = is_page( $pediment_id ) ? ' aria-current="page"' : '';
	$pediment_items  .= sprintf(
		'<li><a href="%s"%s>%s</a></li>',
		esc_url( $pediment_url ),
		$pediment_current,
		esc_html( wp_strip_all_tags( $pediment_title ) )
	);
}

if ( '' === $pediment_items ) {
	return;
}

printf(
	'<nav %s><ul class="wp-block-pediment-legal-links__list">%s</ul></nav>',
	get_block_wrapper_attributes( array( 'aria-label' => __( 'Legal', 'pediment' ) ) ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core escapes the wrapper attributes.
	$pediment_items // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- every value is escaped above.
);
