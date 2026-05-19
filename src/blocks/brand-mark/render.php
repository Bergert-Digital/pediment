<?php
/**
 * Server-side render for starter/brand-mark.
 *
 * Emits the brand badge glyph as inline SVG. Because the block is
 * dynamic (save() returns null), the editor renders this via
 * ServerSideRender and there is no stored block content to validate —
 * which is exactly why inline SVG is safe here, unlike in core/html.
 *
 * The glyph uses fill:currentColor (see `.i` in assets/css/theme.css);
 * `.brand .mark` supplies the accent-gradient badge and white colour,
 * so the mark stays fully theme-token aware.
 */

$wrapper = get_block_wrapper_attributes( array( 'class' => 'mark' ) );

// Phosphor "bank" glyph, viewBox 0 0 256 256.
$path = 'M24,104H48v64H32a8,8,0,0,0,0,16H224a8,8,0,0,0,0-16H208V104h24a8,8,0,0,0,4.19-14.81l-104-64a8,8,0,0,0-8.38,0l-104,64A8,8,0,0,0,24,104Zm40,0H96v64H64Zm80,0v64H112V104Zm48,64H160V104h32ZM128,41.39,203.74,88H52.26ZM248,208a8,8,0,0,1-8,8H16a8,8,0,0,1,0-16H240A8,8,0,0,1,248,208Z';

printf(
	'<span %s><svg class="i" viewBox="0 0 256 256" aria-hidden="true" focusable="false"><path d="%s"></path></svg></span>',
	$wrapper, // phpcs:ignore WordPress.Security.EscapeOutput -- get_block_wrapper_attributes() is pre-escaped.
	esc_attr( $path )
);
