<?php
/**
 * Server-side render for starter/mega-menu.
 *
 * @var array  $attributes
 * @var string $content
 */

$label     = isset( $attributes['label'] ) ? trim( (string) $attributes['label'] ) : '';
$has_panel = '' !== trim( (string) $content );
$panel_id  = wp_unique_id( 'starter-mega-' );

$wrapper = get_block_wrapper_attributes(
	array(
		'class'                  => 'starter-mega-menu',
		'data-wp-interactive'    => 'starter/mega-menu',
		'data-wp-context'        => '{ "isOpen": false }',
		'data-wp-init'           => 'callbacks.init',
		'data-wp-on--keydown'    => 'actions.onKeydown',
		'data-wp-on--focusin'    => 'actions.open',
		'data-wp-on--focusout'   => 'actions.onFocusOut',
		'data-wp-on--mouseenter' => 'actions.onPointerEnter',
		'data-wp-on--mouseleave' => 'actions.onPointerLeave',
	)
);
ob_start();
?>
<div <?php echo $wrapper; // phpcs:ignore WordPress.Security.EscapeOutput ?>>
	<button
		type="button"
		class="starter-mega-menu__trigger"
		aria-expanded="false"
		<?php if ( $has_panel ) : ?>aria-controls="<?php echo esc_attr( $panel_id ); ?>"<?php endif; ?>
		<?php if ( '' === $label ) : ?>aria-label="<?php echo esc_attr__( 'Menu', 'starter' ); ?>"<?php endif; ?>
		data-wp-bind--aria-expanded="context.isOpen"
		data-wp-on--click="actions.toggle"
	><?php echo wp_kses_post( $label ); ?></button>
	<?php if ( $has_panel ) : ?>
		<div
			id="<?php echo esc_attr( $panel_id ); ?>"
			class="starter-mega-menu__panel"
			data-wp-bind--hidden="!context.isOpen"
			data-wp-class--is-open="context.isOpen"
		>
			<?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput -- inner blocks pre-rendered ?>
		</div>
	<?php endif; ?>
</div>
<?php
echo ob_get_clean();
