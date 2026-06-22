/**
 * Readable foreground CSS-var token for a panel background color.
 *
 * Mirror of `pediment_slider_panel_fg()` in render.php — keep the 0.55
 * threshold and Rec. 709 coefficients in sync with the PHP source of truth so
 * the editor preview's text contrast matches the front end exactly.
 *
 * @param bg Background color (#rgb or #rrggbb). Non-hex values fall back to the light token.
 * @return CSS var() token for text color.
 */
export function panelFg( bg: string ): string {
	const light = 'var(--wp--preset--color--surface)';
	const dark = 'var(--wp--preset--color--foreground)';
	let hex = ( bg ?? '' ).replace( /^#/, '' );
	if ( hex.length === 3 ) {
		hex = hex[ 0 ] + hex[ 0 ] + hex[ 1 ] + hex[ 1 ] + hex[ 2 ] + hex[ 2 ];
	}
	if ( ! /^[0-9a-fA-F]{6}$/.test( hex ) ) {
		return light;
	}
	const r = parseInt( hex.slice( 0, 2 ), 16 ) / 255;
	const g = parseInt( hex.slice( 2, 4 ), 16 ) / 255;
	const b = parseInt( hex.slice( 4, 6 ), 16 ) / 255;
	const lum = 0.2126 * r + 0.7152 * g + 0.0722 * b;
	return lum < 0.55 ? light : dark;
}
