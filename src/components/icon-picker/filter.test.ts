import { filterIcons } from './filter';

describe( 'filterIcons', () => {
	const slugs = [ 'arrow-right', 'gear', 'gear-six', 'trend-up', 'bank' ];

	it( 'returns all slugs when the query is empty', () => {
		expect( filterIcons( slugs, '' ) ).toEqual( slugs );
	} );

	it( 'trims and lowercases the query', () => {
		expect( filterIcons( slugs, '  GEAR ' ) ).toEqual( [
			'gear',
			'gear-six',
		] );
	} );

	it( 'matches a substring anywhere in the slug', () => {
		expect( filterIcons( slugs, 'up' ) ).toEqual( [ 'trend-up' ] );
	} );

	it( 'returns an empty array when nothing matches', () => {
		expect( filterIcons( slugs, 'zzz' ) ).toEqual( [] );
	} );
} );
