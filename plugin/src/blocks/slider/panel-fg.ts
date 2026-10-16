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

/** The block.json default for `panelColor`. */
export const DEFAULT_PANEL_COLOR = '#0A1B33';

/** The slug shape render.php accepts as a palette reference. */
const PALETTE_SLUG = /^[a-z0-9-]+$/;

export type PaletteColor = { slug: string; color: string };

/** The hex forms render.php accepts: #rgb and #rrggbb. */
const HEX = /^#(?:[0-9a-fA-F]{3}){1,2}$/;

/**
 * Editor-side mirror of `pediment_slider_panel_colors()` in render.php.
 *
 * `panelColor` is a hex (#rgb / #rrggbb), used as it is, or a palette slug,
 * which resolves to that palette colour. Anything else gets the default
 * panel: the palette's `primary` (the preset the front end paints), or the
 * block default when the palette has none. The text colour follows the
 * resolved value. The preview paints the resolved colour itself rather than
 * the preset var: it is the value the var holds, and it spares the editor
 * from re-deriving WordPress's kebab-cased var name.
 *
 * @param value   The `panelColor` attribute.
 * @param palette Merged palette, highest origin first (custom, theme, default).
 * @return Panel background, text colour, and the value the colour picker shows.
 */
export function panelColors(
	value: string,
	palette: PaletteColor[]
): { bg: string; fg: string; picker: string } {
	const bySlug = ( slug: string ) =>
		PALETTE_SLUG.test( slug )
			? palette.find( ( c ) => c.slug === slug )?.color
			: undefined;
	let color: string;
	if ( HEX.test( value ?? '' ) ) {
		color = value;
	} else {
		color =
			bySlug( value ?? '' ) ?? bySlug( 'primary' ) ?? DEFAULT_PANEL_COLOR;
	}
	return { bg: color, fg: panelFg( color ), picker: color };
}

/**
 * What to store when the user picks a colour: the palette slug for a
 * palette colour, so the panel follows the palette; the colour itself for a
 * custom one. Same lookup as `getColorObjectByColorValue()`.
 *
 * @param picked  The colour the picker returned; undefined when cleared.
 * @param palette Merged palette, highest origin first (custom, theme, default).
 */
export function storedPanelColor(
	picked: string | undefined,
	palette: PaletteColor[]
): string {
	if ( ! picked ) {
		return DEFAULT_PANEL_COLOR;
	}
	const entry = palette.find( ( c ) => c.color === picked );
	return entry && PALETTE_SLUG.test( entry.slug ) ? entry.slug : picked;
}
