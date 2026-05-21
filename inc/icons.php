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
	// Inject the sprite into the outer admin document AND into every iframe
	// we can reach (post editor, site editor, template-part editor all use
	// their own iframes; the Site Editor in particular swaps iframes when
	// navigating between Navigation/Templates/Patterns screens, so a static
	// one-shot selector is not enough). Same-origin in wp-admin → no CORS.
	$script = sprintf(
		"(function(){var sprite=%s;function inject(doc){try{if(!doc||!doc.body||doc.getElementById('starter-icon-sprite'))return;var w=doc.createElement('div');w.id='starter-icon-sprite';w.style.cssText='position:absolute;width:0;height:0;overflow:hidden';w.setAttribute('aria-hidden','true');w.innerHTML=sprite;doc.body.insertBefore(w,doc.body.firstChild);}catch(e){}}function tryAll(){var frames=document.querySelectorAll('iframe');for(var i=0;i<frames.length;i++){(function(f){try{if(f.contentDocument&&f.contentDocument.readyState!=='loading')inject(f.contentDocument);}catch(e){}if(!f.dataset.starterIconBound){f.dataset.starterIconBound='1';f.addEventListener('load',function(){try{inject(f.contentDocument);}catch(e){}});}})(frames[i]);}}inject(document);tryAll();var mo=new MutationObserver(tryAll);mo.observe(document.body,{childList:true,subtree:true});})();",
		wp_json_encode( $sprite )
	);
	wp_register_script( 'starter-editor-icon-sprite', '', array(), null, true );
	wp_enqueue_script( 'starter-editor-icon-sprite' );
	wp_add_inline_script( 'starter-editor-icon-sprite', $script );
}
add_action( 'enqueue_block_editor_assets', 'starter_enqueue_editor_icon_sprite' );
