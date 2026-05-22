<?php
/**
 * Server-side render for starter/mega-menu.
 *
 * @var array $attributes
 */

$label   = isset( $attributes['label'] ) ? trim( (string) $attributes['label'] ) : '';
$columns = isset( $attributes['columns'] ) && is_array( $attributes['columns'] )
	? $attributes['columns']
	: array();

// Panel renders only if at least one link has a label or url.
$has_panel = false;
foreach ( $columns as $col ) {
	$links = isset( $col['links'] ) && is_array( $col['links'] ) ? $col['links'] : array();
	foreach ( $links as $lnk ) {
		$l = isset( $lnk['label'] ) ? trim( (string) $lnk['label'] ) : '';
		$u = isset( $lnk['url'] ) ? trim( (string) $lnk['url'] ) : '';
		if ( '' !== $l || '' !== $u ) {
			$has_panel = true;
			break 2;
		}
	}
}

$panel_id = wp_unique_id( 'starter-mega-' );

// Manually constructed wrapper. get_block_wrapper_attributes() hits
// WP_Block_Supports's static-state machine, which crashes (null-attrs
// TypeError in custom-classname support) when this block is rendered
// inside a wp_navigation entity from the Site Editor admin path. The
// block declares no spacing/color/border/layout supports, so the only
// thing get_block_wrapper_attributes would add beyond these is the
// user's customClassName — merged explicitly below.
$wrapper_classes = array( 'wp-block-starter-mega-menu', 'starter-mega-menu' );
if ( ! empty( $attributes['className'] ) ) {
	$wrapper_classes[] = (string) $attributes['className'];
}
$wrapper = sprintf(
	'class="%s" data-wp-interactive="starter/mega-menu" data-wp-context="%s" data-wp-init="callbacks.init" data-wp-on--focusout="actions.onFocusOut" data-wp-on--mouseenter="actions.onPointerEnter" data-wp-on--mouseleave="actions.onPointerLeave"',
	esc_attr( implode( ' ', $wrapper_classes ) ),
	esc_attr( '{ "isOpen": false }' )
);

ob_start();
?>
<div <?php echo $wrapper; // phpcs:ignore WordPress.Security.EscapeOutput ?>>
	<button
		type="button"
		class="starter-mega-menu__trigger"
		aria-expanded="false"
		<?php
		if ( $has_panel ) :
			?>
			aria-controls="<?php echo esc_attr( $panel_id ); ?>"<?php endif; ?>
		<?php
		if ( '' === $label ) :
			?>
			aria-label="<?php echo esc_attr__( 'Menu', 'starter' ); ?>"<?php endif; ?>
		data-wp-bind--aria-expanded="context.isOpen"
		data-wp-on--focus="actions.onTriggerFocus"
		data-wp-on--click="actions.toggle"
	><?php echo wp_kses_post( $label ); ?></button>
	<?php if ( $has_panel ) : ?>
		<div
			id="<?php echo esc_attr( $panel_id ); ?>"
			class="starter-mega-menu__panel"
			hidden
			data-wp-bind--hidden="!context.isOpen"
			data-wp-class--is-open="context.isOpen"
		>
			<?php
			foreach ( $columns as $col ) :
				$heading = isset( $col['heading'] ) ? trim( (string) $col['heading'] ) : '';
				$c_icon  = isset( $col['icon'] ) ? trim( (string) $col['icon'] ) : '';
				$links   = isset( $col['links'] ) && is_array( $col['links'] ) ? $col['links'] : array();

				$renderable = array();
				foreach ( $links as $lnk ) {
					$l = isset( $lnk['label'] ) ? trim( (string) $lnk['label'] ) : '';
					$u = isset( $lnk['url'] ) ? trim( (string) $lnk['url'] ) : '';
					if ( '' !== $l || '' !== $u ) {
						$renderable[] = $lnk;
					}
				}
				if ( empty( $renderable ) ) {
					continue;
				}
				$has_icon    = ( '' !== $c_icon ) && function_exists( 'starter_icon' );
				$has_heading = '' !== $heading;
				?>
				<div class="starter-mega-column">
					<?php if ( $has_heading || $has_icon ) : ?>
						<p class="starter-mega-column__heading">
						<?php
						if ( $has_icon ) {
							echo starter_icon( $c_icon, 'starter-mega-column__icon' ); // phpcs:ignore WordPress.Security.EscapeOutput -- theme-controlled sprite SVG
						}
							echo wp_kses_post( $heading );
						?>
						</p>
					<?php endif; ?>
					<div class="starter-mega-column__links">
						<?php
						foreach ( $renderable as $lnk ) :
							$l_label = isset( $lnk['label'] ) ? trim( (string) $lnk['label'] ) : '';
							$l_url   = isset( $lnk['url'] ) ? trim( (string) $lnk['url'] ) : '';
							$l_desc  = isset( $lnk['description'] ) ? trim( (string) $lnk['description'] ) : '';
							?>
							<a class="starter-mega-link" href="<?php echo esc_url( $l_url ); ?>">
								<span class="starter-mega-link__label"><?php echo wp_kses_post( $l_label ); ?></span>
								<?php if ( '' !== $l_desc ) : ?>
									<span class="starter-mega-link__desc"><?php echo wp_kses_post( $l_desc ); ?></span>
								<?php endif; ?>
							</a>
						<?php endforeach; ?>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
</div>
<?php
echo ob_get_clean();
