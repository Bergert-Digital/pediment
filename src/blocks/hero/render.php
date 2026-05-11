<?php
/**
 * Server-side render for starter/hero.
 *
 * @var array $attributes
 */

$variant     = isset( $attributes['variant'] )     ? (string) $attributes['variant']     : 'default';
$headline    = isset( $attributes['headline'] )    ? (string) $attributes['headline']    : '';
$subheadline = isset( $attributes['subheadline'] ) ? (string) $attributes['subheadline'] : '';
$cta_text    = isset( $attributes['ctaText'] )     ? (string) $attributes['ctaText']     : '';
$cta_url     = isset( $attributes['ctaUrl'] )      ? (string) $attributes['ctaUrl']      : '';
$media_id    = isset( $attributes['mediaId'] )     ? (int) $attributes['mediaId']        : 0;

$wrapper_attributes = get_block_wrapper_attributes(
	array(
		'class' => 'starter-hero is-variant-' . sanitize_html_class( $variant ),
	)
);

$bg_style = '';
if ( 'media-bg' === $variant && $media_id ) {
	$url = wp_get_attachment_image_url( $media_id, 'full' );
	if ( $url ) {
		$bg_style = ' style="background-image:url(' . esc_url( $url ) . ');"';
	}
}

ob_start();
?>
<section <?php echo $wrapper_attributes; // phpcs:ignore WordPress.Security.EscapeOutput ?><?php echo $bg_style; // phpcs:ignore WordPress.Security.EscapeOutput ?>>
	<?php if ( $headline ) : ?>
		<h1 class="starter-hero__headline"><?php echo wp_kses_post( $headline ); ?></h1>
	<?php endif; ?>
	<?php if ( $subheadline ) : ?>
		<p class="starter-hero__subheadline"><?php echo wp_kses_post( $subheadline ); ?></p>
	<?php endif; ?>
	<?php if ( $cta_text && $cta_url ) : ?>
		<a class="starter-hero__cta" href="<?php echo esc_url( $cta_url ); ?>">
			<?php echo wp_kses_post( $cta_text ); ?>
		</a>
	<?php endif; ?>
</section>
<?php
echo ob_get_clean();
