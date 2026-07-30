import { panelFg } from './panel-fg';

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
