import { getCatalog, __resetCatalogForTests } from './catalog';

type FetchMap = Record< string, unknown >;

function mockFetch( map: FetchMap, failUrls: string[] = [] ) {
	( global as unknown as { fetch: unknown } ).fetch = jest.fn(
		( url: string ) => {
			if ( failUrls.includes( url ) ) {
				return Promise.resolve( { ok: false, status: 500 } );
			}
			return Promise.resolve( {
				ok: true,
				status: 200,
				json: () => Promise.resolve( map[ url ] ),
			} );
		}
	);
}

const URLS = {
	markupUrl: '/markup.json',
	metaUrl: '/meta.json',
	setUrl: '/set.json',
};

beforeEach( () => {
	__resetCatalogForTests();
	( window as unknown as { pedimentIcons?: unknown } ).pedimentIcons = URLS;
} );

it( 'returns markup, meta and set when all fetches succeed', async () => {
	mockFetch( {
		'/markup.json': { gear: '<path/>' },
		'/meta.json': { gear: { c: [ 'system' ], t: [ 'settings' ] } },
		'/set.json': { viewBox: '0 0 24 24', svgAttrs: { fill: 'none' } },
	} );
	const data = await getCatalog();
	expect( data.markup.gear ).toBe( '<path/>' );
	expect( data.meta?.gear.t ).toEqual( [ 'settings' ] );
	expect( data.set.viewBox ).toBe( '0 0 24 24' );
} );

it( 'degrades to null meta when the meta fetch fails', async () => {
	mockFetch(
		{
			'/markup.json': { gear: '<path/>' },
			'/set.json': {
				viewBox: '0 0 256 256',
				svgAttrs: { fill: 'currentColor' },
			},
		},
		[ '/meta.json' ]
	);
	const data = await getCatalog();
	expect( data.markup.gear ).toBe( '<path/>' );
	expect( data.meta ).toBeNull();
} );

it( 'rejects when the markup fetch fails', async () => {
	mockFetch( {}, [ '/markup.json' ] );
	await expect( getCatalog() ).rejects.toThrow();
} );

it( 'allows a retry after the markup fetch fails', async () => {
	mockFetch( {}, [ '/markup.json' ] );
	await expect( getCatalog() ).rejects.toThrow();

	mockFetch( {
		'/markup.json': { gear: '<path/>' },
		'/meta.json': { gear: { c: [ 'system' ], t: [ 'settings' ] } },
		'/set.json': {
			viewBox: '0 0 256 256',
			svgAttrs: { fill: 'currentColor' },
		},
	} );
	const data = await getCatalog();
	expect( data.markup.gear ).toBe( '<path/>' );
} );
