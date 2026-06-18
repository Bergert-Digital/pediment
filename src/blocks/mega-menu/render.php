<?php
/**
 * Server-side render for pediment/mega-menu.
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

// Apply the color/typography block supports the user sets in the editor.
// get_block_wrapper_attributes() can't be used here (see note above), but the
// per-feature helpers take explicit args and don't touch the WP_Block_Supports
// static-state machine that crashes on the wp_navigation admin path. The
// serialized classes/styles land on the wrapper; style.scss makes the trigger
// button inherit them.
$wrapper_styles = '';
if ( isset( $block ) && $block instanceof WP_Block && $block->block_type ) {
	$color = wp_apply_colors_support( $block->block_type, $attributes );
	$typo  = wp_apply_typography_support( $block->block_type, $attributes );
	foreach ( array( $color, $typo ) as $support ) {
		if ( ! empty( $support['class'] ) ) {
			$wrapper_classes[] = (string) $support['class'];
		}
		if ( ! empty( $support['style'] ) ) {
			$wrapper_styles .= (string) $support['style'];
		}
	}
}

$wrapper = sprintf(
	'class="%s"%s data-wp-interactive="pediment/mega-menu" data-wp-context="%s" data-wp-init="callbacks.init" data-wp-on--focusout="actions.onFocusOut" data-wp-on--mouseenter="actions.onPointerEnter" data-wp-on--mouseleave="actions.onPointerLeave"',
	esc_attr( implode( ' ', $wrapper_classes ) ),
	'' !== $wrapper_styles ? ' style="' . esc_attr( $wrapper_styles ) . '"' : '',
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
			aria-label="<?php echo esc_attr__( 'Menu', 'pediment' ); ?>"<?php endif; ?>
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
				$has_icon    = ( '' !== $c_icon ) && function_exists( 'pediment_icon' );
				$has_heading = '' !== $heading;
				?>
				<div class="starter-mega-column">
					<?php if ( $has_heading || $has_icon ) : ?>
						<p class="starter-mega-column__heading">
						<?php
						if ( $has_icon ) {
							echo pediment_icon( $c_icon, 'starter-mega-column__icon' ); // phpcs:ignore WordPress.Security.EscapeOutput -- theme-controlled icon markup
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
