<?php
/**
 * Phosphor icon sprite + helper.
 *
 * @package Pediment
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
function pediment_icon( $name, $extra_class = '' ) {
	$slug  = preg_replace( '/[^a-z0-9-]/', '', strtolower( (string) $name ) );
	$class = 'i' . ( '' !== $extra_class ? ' ' . sanitize_html_class( $extra_class ) : '' );
	return sprintf(
		'<svg class="%s" aria-hidden="true" focusable="false"><use href="#ph-%s"></use></svg>',
		esc_attr( $class ),
		esc_attr( $slug )
	);
}

/**
 * Read the Phosphor sprite once per request. Cached in a static so any
 * combined front-end + editor-asset flow within a single request hits
 * memory after the first read.
 *
 * @return string Raw SVG markup, or '' if the file is missing.
 */
function pediment_icon_sprite_contents(): string {
	static $contents = null;
	if ( null === $contents ) {
		$file     = get_theme_file_path( 'assets/icons/phosphor-sprite.svg' );
		$contents = is_readable( $file ) ? (string) file_get_contents( $file ) : '';
	}
	return $contents;
}

/**
 * Print the Phosphor sprite once, then deregister itself so it cannot
 * fire twice within a single request.
 */
function pediment_print_icon_sprite() {
	remove_action( 'wp_body_open', 'pediment_print_icon_sprite', 1 );
	$sprite = pediment_icon_sprite_contents();
	if ( '' !== $sprite ) {
		// Static, theme-controlled SVG sprite; safe to output verbatim.
		echo $sprite; // phpcs:ignore WordPress.Security.EscapeOutput
	}
}
add_action( 'wp_body_open', 'pediment_print_icon_sprite', 1 );

/**
 * Make the Phosphor sprite available inside the block-editor canvas iframe
 * so blocks can reference `#ph-…` symbols from their `edit.tsx`. The
 * front-end already gets the sprite via `wp_body_open`; the editor's iframe
 * runs in a separate document and has no equivalent hook, so we inject it
 * from the outer admin window once the canvas iframe appears.
 */
function pediment_enqueue_editor_icon_sprite() {
	$sprite = pediment_icon_sprite_contents();
	if ( '' === $sprite ) {
		return;
	}
	// Inject the sprite into the outer admin document AND into every iframe
	// we can reach (post editor, site editor, template-part editor all use
	// their own iframes; the Site Editor in particular swaps iframes when
	// navigating between Navigation/Templates/Patterns screens, so a static
	// one-shot selector is not enough). Same-origin in wp-admin → no CORS.
	$script = sprintf(
		"(function(){var sprite=%s;function inject(doc){try{if(!doc||!doc.body||doc.getElementById('pediment-icon-sprite'))return;var w=doc.createElement('div');w.id='pediment-icon-sprite';w.style.cssText='position:absolute;width:0;height:0;overflow:hidden';w.setAttribute('aria-hidden','true');w.innerHTML=sprite;doc.body.insertBefore(w,doc.body.firstChild);}catch(e){}}function tryAll(){var frames=document.querySelectorAll('iframe');for(var i=0;i<frames.length;i++){(function(f){try{if(f.contentDocument&&f.contentDocument.readyState!=='loading')inject(f.contentDocument);}catch(e){}if(!f.dataset.pedimentIconBound){f.dataset.pedimentIconBound='1';f.addEventListener('load',function(){try{inject(f.contentDocument);}catch(e){}});}})(frames[i]);}}inject(document);tryAll();var mo=new MutationObserver(tryAll);mo.observe(document.body,{childList:true,subtree:true});})();",
		wp_json_encode( $sprite )
	);
	wp_register_script( 'pediment-editor-icon-sprite', '', array(), wp_get_theme()->get( 'Version' ) ?: '0', true );
	wp_enqueue_script( 'pediment-editor-icon-sprite' );
	wp_add_inline_script( 'pediment-editor-icon-sprite', $script );
}
add_action( 'enqueue_block_editor_assets', 'pediment_enqueue_editor_icon_sprite' );
