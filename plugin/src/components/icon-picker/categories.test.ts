import { categoriesFromMeta, categoryLabel } from './categories';

describe( 'categoriesFromMeta', () => {
	it( 'returns sorted unique categories', () => {
		const meta = {
			gear: { c: [ 'system' ], t: [] },
			trash: { c: [ 'office', 'system' ], t: [] },
			'trend-up': { c: [ 'finances', 'office' ], t: [] },
		};
		expect( categoriesFromMeta( meta ) ).toEqual( [
			'finances',
			'office',
			'system',
		] );
	} );

	it( 'returns an empty array when meta is null', () => {
		expect( categoriesFromMeta( null ) ).toEqual( [] );
	} );
} );

describe( 'categoryLabel', () => {
	it( 'capitalises the first letter', () => {
		expect( categoryLabel( 'maps & travel' ) ).toBe( 'Maps & travel' );
		expect( categoryLabel( 'system' ) ).toBe( 'System' );
	} );
} );
