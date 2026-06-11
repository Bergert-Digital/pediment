import { filterIcons } from './filter';

describe( 'filterIcons', () => {
	const slugs = [ 'arrow-right', 'gear', 'gear-six', 'trend-up', 'trash' ];
	const meta = {
		'arrow-right': { c: [ 'arrows' ], t: [ 'east' ] },
		gear: { c: [ 'system' ], t: [ 'settings', 'preferences' ] },
		'gear-six': { c: [ 'system' ], t: [ 'settings' ] },
		'trend-up': {
			c: [ 'finances', 'office' ],
			t: [ 'charts', 'analysis' ],
		},
		trash: { c: [ 'office', 'system' ], t: [ 'delete', 'garbage' ] },
	};

	it( 'returns all slugs when query is empty and category is "all"', () => {
		expect( filterIcons( slugs, '', '', meta ) ).toEqual( slugs );
	} );

	it( 'trims and lowercases the query', () => {
		expect( filterIcons( slugs, '  GEAR ', '', meta ) ).toEqual( [
			'gear',
			'gear-six',
		] );
	} );

	it( 'narrows by category', () => {
		expect( filterIcons( slugs, '', 'system', meta ) ).toEqual( [
			'gear',
			'gear-six',
			'trash',
		] );
	} );

	it( 'matches a tag the slug does not contain', () => {
		// "trash" has no "delete" in its slug, but it is a tag.
		expect( filterIcons( slugs, 'delete', '', meta ) ).toEqual( [
			'trash',
		] );
	} );

	it( 'combines category and query', () => {
		expect( filterIcons( slugs, 'chart', 'office', meta ) ).toEqual( [
			'trend-up',
		] );
	} );

	it( 'falls back to slug-only search when meta is null', () => {
		expect( filterIcons( slugs, 'delete', '', null ) ).toEqual( [] );
		expect( filterIcons( slugs, 'gear', '', null ) ).toEqual( [
			'gear',
			'gear-six',
		] );
	} );

	it( 'ignores category filtering when meta is null', () => {
		expect( filterIcons( slugs, '', 'system', null ) ).toEqual( slugs );
	} );
} );
