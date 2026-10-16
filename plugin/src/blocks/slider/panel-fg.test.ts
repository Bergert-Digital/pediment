import { panelFg, panelColors, storedPanelColor } from './panel-fg';

describe( 'panelFg', () => {
	it( 'returns the light token for a dark background', () => {
		expect( panelFg( '#0A1B33' ) ).toBe(
			'var(--wp--preset--color--surface)'
		);
	} );

	it( 'returns the dark token for a light background', () => {
		expect( panelFg( '#E1F1F6' ) ).toBe(
			'var(--wp--preset--color--foreground)'
		);
	} );

	it( 'expands 3-digit hex', () => {
		expect( panelFg( '#000' ) ).toBe( 'var(--wp--preset--color--surface)' );
		expect( panelFg( '#fff' ) ).toBe(
			'var(--wp--preset--color--foreground)'
		);
	} );

	it( 'falls back to the light token for non-hex input', () => {
		expect( panelFg( 'var(--wp--preset--color--primary)' ) ).toBe(
			'var(--wp--preset--color--surface)'
		);
	} );
} );

describe( 'panelColors', () => {
	// Merged palette, highest origin first: custom, theme, default.
	const palette = [
		{ slug: 'accent', color: '#0A1B33' },
		{ slug: 'accent', color: '#F5E716' },
		{ slug: 'surface-elevated', color: '#EBEBEB' },
		{ slug: 'navy', color: '#0A1B33' },
	];

	it( 'resolves a light palette slug to its colour and dark text', () => {
		expect( panelColors( 'surface-elevated', palette ) ).toEqual( {
			bg: '#EBEBEB',
			fg: 'var(--wp--preset--color--foreground)',
			picker: '#EBEBEB',
		} );
	} );

	it( 'resolves a dark palette slug to light text', () => {
		expect( panelColors( 'navy', palette ).fg ).toBe(
			'var(--wp--preset--color--surface)'
		);
	} );

	it( 'takes the first, highest-origin entry for a slug', () => {
		expect( panelColors( 'accent', palette ).picker ).toBe( '#0A1B33' );
	} );

	it( 'leaves a hex value as it is', () => {
		expect( panelColors( '#F5E716', palette ) ).toEqual( {
			bg: '#F5E716',
			fg: 'var(--wp--preset--color--foreground)',
			picker: '#F5E716',
		} );
	} );

	it( 'gives an unknown slug the default panel colour, like the front end', () => {
		expect( panelColors( 'no-such-colour', palette ) ).toEqual( {
			bg: '#0A1B33',
			fg: 'var(--wp--preset--color--surface)',
			picker: '#0A1B33',
		} );
	} );

	it( 'never passes a hostile value through', () => {
		expect( panelColors( 'red;position:fixed', palette ).bg ).toBe(
			'#0A1B33'
		);
		expect( panelColors( '#0A1B33FF', palette ).bg ).toBe( '#0A1B33' );
	} );

	it( 'keeps a three-digit hex', () => {
		expect( panelColors( '#abc', palette ).bg ).toBe( '#abc' );
	} );

	it( 'paints the default panel in the palette primary when there is one', () => {
		const withPrimary = [ { slug: 'primary', color: '#F5E716' } ];
		expect( panelColors( '', withPrimary ) ).toEqual( {
			bg: '#F5E716',
			fg: 'var(--wp--preset--color--foreground)',
			picker: '#F5E716',
		} );
		expect( panelColors( 'bogus!', withPrimary ).bg ).toBe( '#F5E716' );
	} );
} );

describe( 'storedPanelColor', () => {
	const palette = [
		{ slug: 'accent', color: '#F5E716' },
		{ slug: 'Bad Slug', color: '#123456' },
	];

	it( 'stores the slug of a palette colour', () => {
		expect( storedPanelColor( '#F5E716', palette ) ).toBe( 'accent' );
	} );

	it( 'stores a custom colour as hex', () => {
		expect( storedPanelColor( '#ABCDEF', palette ) ).toBe( '#ABCDEF' );
	} );

	it( 'stores hex when the slug is not one render.php accepts', () => {
		expect( storedPanelColor( '#123456', palette ) ).toBe( '#123456' );
	} );

	it( 'falls back to the default when the colour is cleared', () => {
		expect( storedPanelColor( undefined, palette ) ).toBe( '#0A1B33' );
	} );
} );
