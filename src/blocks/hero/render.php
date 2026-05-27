<?php
/**
 * Server-side render for pediment/hero.
 *
 * @var array $attributes
 */

$variant     = isset( $attributes['variant'] ) ? (string) $attributes['variant'] : 'default';
$headline    = isset( $attributes['headline'] ) ? (string) $attributes['headline'] : '';
$subheadline = isset( $attributes['subheadline'] ) ? (string) $attributes['subheadline'] : '';
$cta_text    = isset( $attributes['ctaText'] ) ? (string) $attributes['ctaText'] : '';
$cta_url     = isset( $attributes['ctaUrl'] ) ? (string) $attributes['ctaUrl'] : '';
$media_id    = isset( $attributes['mediaId'] ) ? (int) $attributes['mediaId'] : 0;

$allowed = function_exists( 'pediment_hero_variants' )
	? pediment_hero_variants()
	: array( 'default', 'centered', 'media-bg', 'stat-card' );
if ( ! in_array( $variant, $allowed, true ) ) {
	$variant = 'default';
}

$wrapper_attributes = get_block_wrapper_attributes(
	array(
		'class' => 'starter-hero is-variant-' . sanitize_html_class( $variant ),
	)
);

if ( 'stat-card' === $variant ) {
	$eyebrow    = isset( $attributes['eyebrow'] ) ? (string) $attributes['eyebrow'] : '';
	$sec_text   = isset( $attributes['secondaryText'] ) ? (string) $attributes['secondaryText'] : '';
	$sec_url    = isset( $attributes['secondaryUrl'] ) ? (string) $attributes['secondaryUrl'] : '';
	$stat_value = isset( $attributes['statValue'] ) ? (string) $attributes['statValue'] : '';
	$stat_text  = isset( $attributes['statText'] ) ? (string) $attributes['statText'] : '';
	$ticks      = ( isset( $attributes['ticks'] ) && is_array( $attributes['ticks'] ) ) ? $attributes['ticks'] : array();
	$metrics    = ( isset( $attributes['metrics'] ) && is_array( $attributes['metrics'] ) ) ? $attributes['metrics'] : array();

	ob_start();
	?>
	<section <?php echo $wrapper_attributes; // phpcs:ignore WordPress.Security.EscapeOutput ?>>
		<div class="starter-hero__col">
			<?php if ( '' !== $eyebrow ) : ?>
				<span class="starter-hero__eyebrow"><?php echo wp_kses_post( $eyebrow ); ?></span>
			<?php endif; ?>
			<?php if ( '' !== $headline ) : ?>
				<h1 class="starter-hero__headline"><?php echo wp_kses_post( $headline ); ?></h1>
			<?php endif; ?>
			<?php if ( '' !== $subheadline ) : ?>
				<p class="starter-hero__subheadline"><?php echo wp_kses_post( $subheadline ); ?></p>
			<?php endif; ?>
			<div class="starter-hero__actions">
				<?php if ( '' !== $cta_text && '' !== $cta_url ) : ?>
					<a class="starter-hero__cta" href="<?php echo esc_url( $cta_url ); ?>"><?php echo wp_kses_post( $cta_text ); ?></a>
				<?php endif; ?>
				<?php if ( '' !== $sec_text && '' !== $sec_url ) : ?>
					<a class="starter-hero__cta starter-hero__cta--secondary" href="<?php echo esc_url( $sec_url ); ?>"><?php echo wp_kses_post( $sec_text ); ?></a>
				<?php endif; ?>
			</div>
			<?php if ( ! empty( $ticks ) ) : ?>
				<ul class="starter-hero__ticks">
					<?php foreach ( $ticks as $tick ) : ?>
						<li class="starter-hero__tick"><svg class="starter-hero__tick-icon" aria-hidden="true" focusable="false"><use href="#ph-check-circle"></use></svg><?php echo wp_kses_post( (string) $tick ); ?></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>
		<figure class="starter-hero__fig">
			<?php
			if ( $media_id ) {
				echo wp_get_attachment_image( $media_id, 'large', false, array( 'class' => 'starter-hero__img' ) ); // phpcs:ignore WordPress.Security.EscapeOutput
			}
			?>
			<?php if ( '' !== $stat_value || '' !== $stat_text || ! empty( $metrics ) ) : ?>
				<div class="starter-hero__glass">
					<?php if ( '' !== $stat_value ) : ?>
						<div class="starter-hero__stat-value"><?php echo wp_kses_post( $stat_value ); ?></div>
					<?php endif; ?>
					<?php if ( '' !== $stat_text ) : ?>
						<div class="starter-hero__stat-text"><?php echo wp_kses_post( $stat_text ); ?></div>
					<?php endif; ?>
					<?php if ( ! empty( $metrics ) ) : ?>
						<div class="starter-hero__metrics">
							<?php foreach ( $metrics as $metric ) : ?>
								<?php
								$mv = is_array( $metric ) && isset( $metric['value'] ) ? (string) $metric['value'] : '';
								$ml = is_array( $metric ) && isset( $metric['label'] ) ? (string) $metric['label'] : '';
								?>
								<div class="starter-hero__metric">
									<b><?php echo wp_kses_post( $mv ); ?></b>
									<span><?php echo wp_kses_post( $ml ); ?></span>
								</div>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
				</div>
			<?php endif; ?>
		</figure>
	</section>
	<?php
	echo ob_get_clean();
	return;
}

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
