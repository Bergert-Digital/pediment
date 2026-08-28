import {
	planSections,
	normalizeSections,
	type BlockLike,
} from '../normalizeSections';

const b = ( name: string, className?: string ): BlockLike => ( {
	name,
	attributes: className ? { className } : {},
} );

describe( 'planSections', () => {
	it( 'wraps a separator-delimited run into one section, separators dropped', () => {
		const blocks = [
			b( 'pediment/hero' ),
			b( 'core/separator' ),
			b( 'core/heading' ),
			b( 'core/paragraph' ),
		];
		expect( planSections( blocks ) ).toEqual( [
			{ kind: 'wrap', indices: [ 0 ] },
			{ kind: 'wrap', indices: [ 2, 3 ] },
		] );
	} );

	it( 'keeps an existing starter-band group untouched (idempotent)', () => {
		const blocks = [
			b( 'core/group', 'starter-band is-style-band-surface' ),
			b( 'core/separator' ),
			b( 'pediment/faq' ),
		];
		expect( planSections( blocks ) ).toEqual( [
			{ kind: 'keep', index: 0 },
			{ kind: 'wrap', indices: [ 2 ] },
		] );
	} );

	it( 'no separators, no bands → single section', () => {
		expect(
			planSections( [ b( 'core/heading' ), b( 'core/paragraph' ) ] )
		).toEqual( [ { kind: 'wrap', indices: [ 0, 1 ] } ] );
	} );

	it( 'all already bands → unchanged', () => {
		const blocks = [
			b( 'core/group', 'starter-band is-style-band-surface' ),
			b( 'core/group', 'x starter-band is-style-band-navy y' ),
		];
		expect( planSections( blocks ) ).toEqual( [
			{ kind: 'keep', index: 0 },
			{ kind: 'keep', index: 1 },
		] );
	} );

	it( 'bands are self-delimiting boundaries: hero + adjacent bands, no separators', () => {
		const blocks = [
			b( 'pediment/hero' ),
			b( 'core/group', 'starter-band is-style-band-surface' ),
			b( 'core/group', 'starter-band is-style-band-surface' ),
			b( 'core/group', 'starter-band is-style-band-surface' ),
		];
		expect( planSections( blocks ) ).toEqual( [
			{ kind: 'wrap', indices: [ 0 ] },
			{ kind: 'keep', index: 1 },
			{ kind: 'keep', index: 2 },
			{ kind: 'keep', index: 3 },
		] );
	} );

	it( 'a band ends a preceding ungrouped run without a separator', () => {
		const blocks = [
			b( 'core/heading' ),
			b( 'core/paragraph' ),
			b( 'core/group', 'starter-band is-style-band-surface' ),
			b( 'pediment/cta' ),
		];
		expect( planSections( blocks ) ).toEqual( [
			{ kind: 'wrap', indices: [ 0, 1 ] },
			{ kind: 'keep', index: 2 },
			{ kind: 'wrap', indices: [ 3 ] },
		] );
	} );

	it( 'ignores empty segments from leading/consecutive/trailing separators', () => {
		const blocks = [
			b( 'core/separator' ),
			b( 'pediment/hero' ),
			b( 'core/separator' ),
			b( 'core/separator' ),
			b( 'pediment/cta' ),
			b( 'core/separator' ),
		];
		expect( planSections( blocks ) ).toEqual( [
			{ kind: 'wrap', indices: [ 1 ] },
			{ kind: 'wrap', indices: [ 4 ] },
		] );
	} );

	it( 'leaves a theme section block untouched and self-delimiting (not swallowed)', () => {
		// The /activities/ bug: a page of full-bleed theme section blocks with no
		// band or separator was lumped into ONE wrap → a cream band swallowing
		// the hero. With the theme declaring these as section blocks, each stays
		// its own passthrough section.
		const isSection = ( name: string ) =>
			name === 'workation/page-hero' ||
			name === 'workation/activity-list';
		const blocks = [
			b( 'workation/page-hero' ),
			b( 'workation/activity-list' ),
		];
		expect( planSections( blocks, isSection ) ).toEqual( [
			{ kind: 'passthrough', index: 0 },
			{ kind: 'passthrough', index: 1 },
		] );
	} );

	it( 'a section block flushes a preceding loose run without a separator', () => {
		const isSection = ( name: string ) => name === 'workation/page-hero';
		const blocks = [
			b( 'core/heading' ),
			b( 'core/paragraph' ),
			b( 'workation/page-hero' ),
			b( 'pediment/cta' ),
		];
		expect( planSections( blocks, isSection ) ).toEqual( [
			{ kind: 'wrap', indices: [ 0, 1 ] },
			{ kind: 'passthrough', index: 2 },
			{ kind: 'wrap', indices: [ 3 ] },
		] );
	} );

	it( 'a legacy starter-section (without starter-band) is NOT a boundary — it gets wrapped as loose content', () => {
		// After flattenLegacyWrappers these are unwrapped, but planSections on
		// its own treats a bare starter-section as ordinary content.
		const blocks = [
			b( 'core/group', 'starter-band is-style-band-surface' ),
			b( 'core/group', 'starter-section' ),
		];
		expect( planSections( blocks ) ).toEqual( [
			{ kind: 'keep', index: 0 },
			{ kind: 'wrap', indices: [ 1 ] },
		] );
	} );
} );

type Node = {
	clientId: string;
	name: string;
	attributes: any;
	innerBlocks: Node[];
};

const node = (
	clientId: string,
	name: string,
	attributes: any = {},
	innerBlocks: Node[] = []
): Node => ( { clientId, name, attributes, innerBlocks } );

const band = (
	clientId: string,
	style: string,
	innerBlocks: Node[] = []
): Node =>
	node(
		clientId,
		'core/group',
		{ className: `starter-band ${ style }` },
		innerBlocks
	);

/**
 * Run normalizeSections against an in-memory root, returning the new blocks.
 * @param root
 * @param isSectionBlock
 */
function run(
	root: Node[],
	isSectionBlock?: ( name: string ) => boolean
): { ids: string[]; blocks: any[] } | null {
	const replaceBlocks = jest.fn();
	let n = 0;
	normalizeSections(
		{ getBlocks: () => root, replaceBlocks },
		( name, attributes, innerBlocks ) => ( {
			name,
			attributes,
			innerBlocks,
			clientId: 'fresh-' + n++,
		} ),
		isSectionBlock
	);
	if ( replaceBlocks.mock.calls.length === 0 ) {
		return null;
	}
	const [ ids, blocks ] = replaceBlocks.mock.calls[ 0 ];
	return { ids, blocks };
}

describe( 'normalizeSections applier — band model', () => {
	it( 'wraps loose top-level content into a starter-band and drops separators', () => {
		const root = [
			node( 'h', 'pediment/hero' ),
			node( 's', 'core/separator' ),
			node( 'g', 'core/heading' ),
			node( 'p', 'core/paragraph' ),
		];
		const res = run( root )!;
		expect( res.ids ).toEqual( [ 'h', 's', 'g', 'p' ] );
		expect( res.blocks ).toHaveLength( 2 );
		expect( res.blocks[ 0 ] ).toMatchObject( {
			name: 'core/group',
			attributes: {
				align: 'full',
				className: 'starter-band is-style-band-surface',
				layout: { type: 'constrained' },
				style: { spacing: { margin: { top: '0', bottom: '0' } } },
			},
		} );
		// No legacy section shape.
		expect( res.blocks[ 0 ].attributes.tagName ).toBeUndefined();
		expect(
			res.blocks[ 0 ].innerBlocks.map( ( x: any ) => x.name )
		).toEqual( [ 'pediment/hero' ] );
		expect(
			res.blocks[ 1 ].innerBlocks.map( ( x: any ) => x.name )
		).toEqual( [ 'core/heading', 'core/paragraph' ] );
	} );

	it( 'enforces band-shape attrs on kept bands while preserving the chosen band style and children', () => {
		const root = [
			band( 'a', 'is-style-band-elevated', [
				node( 'x', 'core/paragraph' ),
			] ),
		];
		const res = run( root )!;
		expect( res.blocks ).toHaveLength( 1 );
		expect( res.blocks[ 0 ].attributes ).toMatchObject( {
			align: 'full',
			layout: { type: 'constrained' },
		} );
		const tokens = res.blocks[ 0 ].attributes.className.split( /\s+/ );
		expect( tokens ).toContain( 'starter-band' );
		expect( tokens ).toContain( 'is-style-band-elevated' );
		expect( tokens ).not.toContain( 'is-style-band-surface' );
		expect(
			res.blocks[ 0 ].innerBlocks.map( ( x: any ) => x.name )
		).toEqual( [ 'core/paragraph' ] );
	} );

	it( 'strips auto-derived layout/align classes and the legacy starter-section/tagName shape', () => {
		const root = [
			node( 'a', 'core/group', {
				tagName: 'section',
				align: 'full',
				className:
					'starter-section starter-band is-style-band-navy is-layout-constrained wp-block-group-is-layout-constrained alignfull wp-block-group',
				layout: { type: 'default' },
			} ),
		];
		const res = run( root )!;
		expect( res.blocks[ 0 ].attributes.className ).toBe(
			'starter-band is-style-band-navy'
		);
		expect( res.blocks[ 0 ].attributes.tagName ).toBeUndefined();
		expect( res.blocks[ 0 ].attributes.layout ).toEqual( {
			type: 'constrained',
		} );
	} );

	it( 'preserves custom classes and extra style while enforcing band shape', () => {
		const root = [
			node( 'a', 'core/group', {
				className: 'brand starter-band is-style-band-surface',
				backgroundColor: 'surface-elevated',
				style: { spacing: { padding: { top: '2rem' } } },
			} ),
		];
		const res = run( root )!;
		expect( res.blocks[ 0 ].attributes ).toMatchObject( {
			align: 'full',
			backgroundColor: 'surface-elevated',
			layout: { type: 'constrained' },
			style: {
				spacing: {
					padding: { top: '2rem' },
					margin: { top: '0', bottom: '0' },
				},
			},
		} );
		expect( res.blocks[ 0 ].attributes.className.split( /\s+/ ) ).toEqual(
			expect.arrayContaining( [ 'brand', 'starter-band' ] )
		);
	} );

	it( 'HEALS the bug: a starter-section wrapping bands is unwrapped into a flat band list', () => {
		// Reproduces the reported broken page: an outer starter-section that
		// swallowed every band. After normalize the bands must be top-level
		// siblings again (so the AI can move/insert sections), not nested.
		const root = [
			node(
				'wrapper',
				'core/group',
				{ tagName: 'section', className: 'starter-section' },
				[
					band( 'hero', 'is-style-band-surface', [
						node( 'h0', 'pediment/hero' ),
					] ),
					band( 'features', 'is-style-band-surface', [
						node( 'f0', 'pediment/feature-grid' ),
					] ),
					band( 'navy', 'is-style-band-navy' ),
				]
			),
		];
		const res = run( root )!;
		// The whole outer wrapper is replaced…
		expect( res.ids ).toEqual( [ 'wrapper' ] );
		// …with the three bands, flat at top level, order preserved.
		expect( res.blocks ).toHaveLength( 3 );
		expect(
			res.blocks.map(
				( bl: any ) => bl.innerBlocks[ 0 ]?.name ?? '(empty)'
			)
		).toEqual( [ 'pediment/hero', 'pediment/feature-grid', '(empty)' ] );
		// Each is a real band, none nested inside another.
		res.blocks.forEach( ( bl: any ) => {
			expect( bl.attributes.className.split( /\s+/ ) ).toContain(
				'starter-band'
			);
			expect(
				bl.innerBlocks.some( ( c: any ) => c.name === 'core/group' )
			).toBe( false );
		} );
	} );

	it( 'HEALS the "on top" bug: a stray starter-section of loose content becomes its own band, bands stay flat', () => {
		// The exact two-top-level-group shape from the report: a stray group of
		// testimonial/stat content sitting ABOVE a wrapper full of bands.
		const root = [
			node(
				'stray',
				'core/group',
				{ tagName: 'section', className: 'starter-section' },
				[
					node( 'sh', 'pediment/section-head' ),
					node( 'tg', 'pediment/testimonial-grid' ),
					node( 'sg', 'pediment/stat-grid' ),
				]
			),
			node(
				'main',
				'core/group',
				{ tagName: 'section', className: 'starter-section' },
				[
					band( 'hero', 'is-style-band-surface', [
						node( 'h0', 'pediment/hero' ),
					] ),
					band( 'cta', 'is-style-band-surface', [
						node( 'c0', 'pediment/cta' ),
					] ),
				]
			),
		];
		const res = run( root )!;
		expect( res.ids ).toEqual( [ 'stray', 'main' ] );
		// stray loose content → one band; main's two bands lifted flat.
		expect( res.blocks ).toHaveLength( 3 );
		expect(
			res.blocks.map( ( bl: any ) =>
				bl.innerBlocks.map( ( c: any ) => c.name )
			)
		).toEqual( [
			[
				'pediment/section-head',
				'pediment/testimonial-grid',
				'pediment/stat-grid',
			],
			[ 'pediment/hero' ],
			[ 'pediment/cta' ],
		] );
		res.blocks.forEach( ( bl: any ) =>
			expect( bl.attributes.className.split( /\s+/ ) ).toContain(
				'starter-band'
			)
		);
	} );

	it( 'is idempotent on an already-canonical flat band list (structure preserved)', () => {
		const root = [
			band( 'hero', 'is-style-band-surface', [
				node( 'h0', 'pediment/hero' ),
			] ),
			band( 'feat', 'is-style-band-elevated', [
				node( 'f0', 'pediment/feature-grid' ),
			] ),
			band( 'cta', 'is-style-band-navy', [
				node( 'c0', 'pediment/cta' ),
			] ),
		];
		const res = run( root )!;
		expect( res.blocks ).toHaveLength( 3 );
		expect(
			res.blocks.map( ( bl: any ) => bl.attributes.className )
		).toEqual( [
			'starter-band is-style-band-surface',
			'starter-band is-style-band-elevated',
			'starter-band is-style-band-navy',
		] );
		expect(
			res.blocks.map( ( bl: any ) => bl.innerBlocks[ 0 ].name )
		).toEqual( [
			'pediment/hero',
			'pediment/feature-grid',
			'pediment/cta',
		] );
	} );

	it( 'leaves theme section blocks untouched at the top level instead of banding them', () => {
		// End-to-end of the /activities/ corruption via the applier: a hero +
		// activity list are passed through verbatim, NOT wrapped in a cream band.
		const isSection = ( name: string ) =>
			name === 'workation/page-hero' ||
			name === 'workation/activity-list';
		const root = [
			node( 'hero', 'workation/page-hero', { align: 'full' } ),
			node( 'list', 'workation/activity-list', { align: 'full' } ),
		];
		const res = run( root, isSection )!;
		expect( res.blocks ).toHaveLength( 2 );
		expect( res.blocks.map( ( bl: any ) => bl.name ) ).toEqual( [
			'workation/page-hero',
			'workation/activity-list',
		] );
		// Attributes preserved verbatim — no band className, no forced layout.
		res.blocks.forEach( ( bl: any ) => {
			expect( bl.attributes ).toEqual( { align: 'full' } );
			expect( bl.attributes.className ).toBeUndefined();
		} );
	} );

	it( 'wraps loose content but passes a section block through in the same page', () => {
		const isSection = ( name: string ) => name === 'workation/page-hero';
		const root = [
			node( 'hero', 'workation/page-hero', { align: 'full' } ),
			node( 'h', 'core/heading' ),
			node( 'p', 'core/paragraph' ),
		];
		const res = run( root, isSection )!;
		expect( res.blocks ).toHaveLength( 2 );
		expect( res.blocks[ 0 ].name ).toBe( 'workation/page-hero' );
		expect( res.blocks[ 0 ].attributes ).toEqual( { align: 'full' } );
		// The trailing loose run becomes a band.
		expect( res.blocks[ 1 ].attributes.className ).toContain(
			'starter-band'
		);
		expect(
			res.blocks[ 1 ].innerBlocks.map( ( x: any ) => x.name )
		).toEqual( [ 'core/heading', 'core/paragraph' ] );
	} );

	it( 'is idempotent on a page of theme section blocks (fresh clientIds, same shape)', () => {
		const isSection = ( name: string ) => name === 'workation/page-hero';
		const root = [
			node( 'hero', 'workation/page-hero', { align: 'full' } ),
		];
		const first = run( root, isSection )!;
		const rebuilt = first.blocks.map( ( bl: any, i: number ) => ( {
			clientId: 'live-' + i,
			name: bl.name,
			attributes: bl.attributes,
			innerBlocks: bl.innerBlocks,
		} ) );
		const second = run( rebuilt, isSection )!;
		expect( second.blocks.map( ( bl: any ) => bl.name ) ).toEqual( [
			'workation/page-hero',
		] );
		expect( second.blocks[ 0 ].attributes ).toEqual( { align: 'full' } );
	} );

	it( 'clones children so no new block reuses an original (removed) clientId', () => {
		const root = [
			node( 'h', 'pediment/hero' ),
			node( 's', 'core/separator' ),
			node( 'g', 'core/heading' ),
		];
		const res = run( root )!;
		const collect = ( bl: any ): string[] => [
			bl.clientId,
			...( bl.innerBlocks ?? [] ).flatMap( collect ),
		];
		const newIds = res.blocks.flatMap( collect );
		for ( const id of newIds ) {
			expect( res.ids ).not.toContain( id );
		}
	} );
} );
