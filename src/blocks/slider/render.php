<?php
/**
 * Server-side render for pediment/slider.
 *
 * @var array $attributes
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
$bg       = ( isset( $attributes['panelColor'] ) && '' !== $attributes['panelColor'] )
	? (string) $attributes['panelColor']
	: 'var(--wp--preset--color--primary)';
$fg       = pediment_slider_panel_fg( $bg );
$slides   = ( isset( $attributes['slides'] ) && is_array( $attributes['slides'] ) ) ? $attributes['slides'] : array();
$count    = count( $slides );

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
		<div class="starter-slider__rail">
		<?php
		foreach ( $slides as $slide ) :
			$media_id     = isset( $slide['mediaId'] ) ? (int) $slide['mediaId'] : 0;
			$alt_override = isset( $slide['altOverride'] ) ? (string) $slide['altOverride'] : '';
			$eyebrow      = isset( $slide['eyebrow'] ) ? (string) $slide['eyebrow'] : '';
			$heading      = isset( $slide['heading'] ) ? (string) $slide['heading'] : '';
			$body         = isset( $slide['body'] ) ? (string) $slide['body'] : '';
			$btn_text     = isset( $slide['buttonText'] ) ? (string) $slide['buttonText'] : '';
			$btn_url      = isset( $slide['buttonUrl'] ) ? (string) $slide['buttonUrl'] : '';

			$img_html = '';
			if ( $media_id ) {
				$alt      = '' !== $alt_override ? $alt_override : (string) get_post_meta( $media_id, '_wp_attachment_image_alt', true );
				$img_html = wp_get_attachment_image(
					$media_id,
					'large',
					false,
					array(
						'alt'     => $alt,
						'class'   => 'starter-slide__img',
						// Eager-load so sliding to a slide never reveals a blank
						// frame while a lazy image fetches.
						'loading' => 'eager',
					)
				);
			}
			?>
			<div class="starter-slide">
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
					<?php if ( '' !== trim( $eyebrow ) ) : ?>
						<p class="starter-slide__eyebrow"><?php echo esc_html( $eyebrow ); ?></p>
					<?php endif; ?>
					<?php if ( '' !== trim( $heading ) ) : ?>
						<h2 class="starter-slide__heading"><?php echo esc_html( $heading ); ?></h2>
					<?php endif; ?>
					<?php if ( '' !== trim( $body ) ) : ?>
						<p class="starter-slide__body"><?php echo nl2br( esc_html( $body ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- esc_html escaped; nl2br only inserts <br /> ?></p>
					<?php endif; ?>
					<?php if ( '' !== trim( $btn_text ) && '' !== trim( $btn_url ) ) : ?>
						<a class="starter-slide__button" href="<?php echo esc_url( $btn_url ); ?>"><?php echo esc_html( $btn_text ); ?></a>
					<?php endif; ?>
				</div>
			</div>
			<?php
		endforeach;
		?>
		</div>
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
