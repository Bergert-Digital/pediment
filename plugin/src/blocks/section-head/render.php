<?php
/**
 * Server-side render for pediment/section-head.
 *
 * @var array $attributes
 */

$eyebrow   = isset( $attributes['eyebrow'] ) ? (string) $attributes['eyebrow'] : '';
$headline  = isset( $attributes['headline'] ) ? (string) $attributes['headline'] : '';
$lead      = isset( $attributes['lead'] ) ? (string) $attributes['lead'] : '';
$alignment = isset( $attributes['alignment'] ) && 'center' === $attributes['alignment'] ? 'center' : 'start';
$level     = isset( $attributes['level'] ) && 3 === (int) $attributes['level'] ? 3 : 2;
$h_tag     = 'h' . $level;

// Optional per-instance width override. safecss_filter_attr drops anything that
// is not a legitimate CSS declaration, so a bad value yields an empty string.
$max_width   = isset( $attributes['maxWidth'] ) ? (string) $attributes['maxWidth'] : '';
$inner_style = '' !== $max_width ? safecss_filter_attr( 'max-width:' . $max_width ) : '';
$inner_attr  = '' !== $inner_style ? ' style="' . esc_attr( $inner_style ) . '"' : '';

$wrapper = get_block_wrapper_attributes(
	array(
		'class' => 'starter-section-head is-alignment-' . $alignment,
	)
);

ob_start();
?>
<div <?php echo $wrapper; // phpcs:ignore WordPress.Security.EscapeOutput ?>>
	<div class="starter-section-head__inner"<?php echo $inner_attr; // phpcs:ignore WordPress.Security.EscapeOutput -- value built from safecss_filter_attr + esc_attr ?>>
		<?php
		if ( '' !== $eyebrow ) :
			?>
			<p class="starter-section-head__eyebrow"><?php echo wp_kses_post( $eyebrow ); ?></p><?php endif; ?>
		<?php
		if ( '' !== $headline ) :
			?>
			<<?php echo $h_tag; // phpcs:ignore WordPress.Security.EscapeOutput ?> class="starter-section-head__headline"><?php echo wp_kses_post( $headline ); ?></<?php echo $h_tag; // phpcs:ignore WordPress.Security.EscapeOutput ?>><?php endif; ?>
		<?php
		if ( '' !== $lead ) :
			?>
			<p class="starter-section-head__lead"><?php echo wp_kses_post( $lead ); ?></p><?php endif; ?>
	</div>
</div>
<?php
echo ob_get_clean();
