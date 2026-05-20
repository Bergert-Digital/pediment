<?php
/**
 * Phosphor icon sprite + helper.
 *
 * @package Starter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Return an inline SVG that references a sprite symbol.
 *
 * @param string $name        Phosphor icon name (without the ph- prefix).
 * @param string $extra_class Optional extra CSS class.
 * @return string Safe HTML.
 */
function starter_icon( $name, $extra_class = '' ) {
	$slug  = preg_replace( '/[^a-z0-9-]/', '', strtolower( (string) $name ) );
	$class = 'i' . ( '' !== $extra_class ? ' ' . sanitize_html_class( $extra_class ) : '' );
	return sprintf(
		'<svg class="%s" aria-hidden="true" focusable="false"><use href="#ph-%s"></use></svg>',
		esc_attr( $class ),
		esc_attr( $slug )
	);
}

/**
 * Print the Phosphor sprite once, then deregister itself so it cannot
 * fire twice within a single request.
 */
function starter_print_icon_sprite() {
	remove_action( 'wp_body_open', 'starter_print_icon_sprite', 1 );
	$file = get_theme_file_path( 'assets/icons/phosphor-sprite.svg' );
	if ( is_readable( $file ) ) {
		// Static, theme-controlled SVG sprite; safe to output verbatim.
		echo file_get_contents( $file ); // phpcs:ignore WordPress.Security.EscapeOutput
	}
}
add_action( 'wp_body_open', 'starter_print_icon_sprite', 1 );

/**
 * Make the Phosphor sprite available inside the block-editor canvas iframe
 * so blocks can reference `#ph-…` symbols from their `edit.tsx`. The
 * front-end already gets the sprite via `wp_body_open`; the editor's iframe
 * runs in a separate document and has no equivalent hook, so we inject it
 * from the outer admin window once the canvas iframe appears.
 */
function starter_enqueue_editor_icon_sprite() {
	$sprite_path = get_theme_file_path( 'assets/icons/phosphor-sprite.svg' );
	if ( ! is_readable( $sprite_path ) ) {
		return;
	}
	$sprite = file_get_contents( $sprite_path );
	$script = sprintf(
		"(function(){var sprite=%s;function inject(doc){if(!doc||!doc.body||doc.getElementById('starter-icon-sprite'))return;var w=doc.createElement('div');w.id='starter-icon-sprite';w.style.cssText='position:absolute;width:0;height:0;overflow:hidden';w.innerHTML=sprite;doc.body.insertBefore(w,doc.body.firstChild);}function tryIframe(f){if(!f)return;var go=function(){inject(f.contentDocument);};if(f.contentDocument&&f.contentDocument.readyState!=='loading')go();f.addEventListener('load',go);}inject(document);var existing=document.querySelector('iframe[name=\"editor-canvas\"]');tryIframe(existing);var mo=new MutationObserver(function(){var f=document.querySelector('iframe[name=\"editor-canvas\"]');if(f)tryIframe(f);});mo.observe(document.body,{childList:true,subtree:true});})();",
		wp_json_encode( $sprite )
	);
	wp_register_script( 'starter-editor-icon-sprite', '', array(), null, true );
	wp_enqueue_script( 'starter-editor-icon-sprite' );
	wp_add_inline_script( 'starter-editor-icon-sprite', $script );
}
add_action( 'enqueue_block_editor_assets', 'starter_enqueue_editor_icon_sprite' );
