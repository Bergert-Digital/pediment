<?php
/**
 * Server-side render for pediment/slider.
 *
 * @var array    $attributes
 * @var string   $content    Pre-rendered inner blocks (the slides).
 * @var WP_Block $block
 */

if ( ! function_exists( 'pediment_slider_panel_fg' ) ) {
	/**
	 * Readable foreground CSS var for a given panel background hex.
	 *
	 * @param string $bg Background color (expects #rgb or #rrggbb).
	 * @return string A CSS var() reference for the text color.
	 */
	function pediment_slider_panel_fg( $bg ) {
		$light = 'var(--wp--preset--color--surface)';
		$dark  = 'var(--wp--preset--color--foreground)';
		$hex   = ltrim( (string) $bg, '#' );
		if ( 3 === strlen( $hex ) ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}
		if ( 6 !== strlen( $hex ) || ! ctype_xdigit( $hex ) ) {
			return $light;
		}
		$r = hexdec( substr( $hex, 0, 2 ) ) / 255;
		$g = hexdec( substr( $hex, 2, 2 ) ) / 255;
		$b = hexdec( substr( $hex, 4, 2 ) ) / 255;
		// Perceived luminance (Rec. 709 coefficients).
		$lum = ( 0.2126 * $r ) + ( 0.7152 * $g ) + ( 0.0722 * $b );
		return $lum < 0.55 ? $light : $dark;
	}
}

$position = ( isset( $attributes['mediaPosition'] ) && 'right' === $attributes['mediaPosition'] ) ? 'right' : 'left';
$bg       = isset( $attributes['panelColor'] ) ? (string) $attributes['panelColor'] : 'var(--wp--preset--color--primary)';

/**
 * Pick a readable foreground token for a panel background. Parses a #hex color,
 * computes relative luminance, and returns the surface (light) token for dark
 * backgrounds or the foreground (dark) token for light backgrounds. Falls back
 * to the light token for non-hex / unparseable values.
 */
$fg = pediment_slider_panel_fg( $bg );

// Count slides from the parsed inner block list.
$count = is_object( $block ) && ! empty( $block->parsed_block['innerBlocks'] )
	? count( $block->parsed_block['innerBlocks'] )
	: 0;

$style   = sprintf( '--slide-panel-bg:%s;--slide-panel-fg:%s;', esc_attr( $bg ), esc_attr( $fg ) );
$wrapper = get_block_wrapper_attributes(
	array(
		'class' => 'starter-slider is-media-' . $position,
		'style' => $style,
	)
);

$context = wp_json_encode(
	array(
		'active' => 0,
		'count'  => $count,
	)
);

ob_start();
?>
<section <?php echo $wrapper; // phpcs:ignore WordPress.Security.EscapeOutput ?>
	data-wp-interactive="pediment/slider"
	data-wp-context='<?php echo esc_attr( $context ); ?>'
	data-wp-init="callbacks.init"
	data-wp-watch="callbacks.render"
	data-wp-on--keydown="actions.onKeydown"
	role="group" aria-roledescription="carousel" tabindex="-1">
	<div class="starter-slider__track">
		<?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput -- inner blocks pre-rendered ?>
	</div>
	<?php if ( $count > 1 ) : ?>
	<button type="button" class="starter-slider__arrow starter-slider__arrow--prev" aria-label="<?php esc_attr_e( 'Vorherige Folie', 'pediment' ); ?>" data-wp-on--click="actions.prev">
		<span aria-hidden="true">&lsaquo;</span>
	</button>
	<button type="button" class="starter-slider__arrow starter-slider__arrow--next" aria-label="<?php esc_attr_e( 'Nächste Folie', 'pediment' ); ?>" data-wp-on--click="actions.next">
		<span aria-hidden="true">&rsaquo;</span>
	</button>
		<div class="starter-slider__pagination" role="group" aria-label="<?php esc_attr_e( 'Folien', 'pediment' ); ?>">
			<?php for ( $i = 0; $i < $count; $i++ ) : ?>
				<button type="button" class="starter-slider__dot" data-index="<?php echo esc_attr( (string) $i ); ?>" data-wp-on--click="actions.goTo" aria-label="<?php echo esc_attr( sprintf( /* translators: %d: slide number */ __( 'Gehe zu Folie %d', 'pediment' ), $i + 1 ) ); ?>"></button>
			<?php endfor; ?>
		</div>
	<?php endif; ?>
	<p class="starter-slider__live screen-reader-text" aria-live="polite"></p>
</section>
<?php
echo ob_get_clean();
