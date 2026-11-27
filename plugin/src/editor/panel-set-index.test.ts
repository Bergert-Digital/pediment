import { selectedPanelIndex, type BlockTree } from './panel-set-index';

/*
 * set
 * ├─ panels (wrapper)
 * │  ├─ p0 (pediment-panel) ─ p0-text
 * │  └─ p1 (pediment-panel) ─ p1-text
 * ├─ tabs (wrapper)
 * │  ├─ t0 (pediment-panel-tab)
 * │  └─ t1 (pediment-panel-tab) ─ t1-text
 * ├─ intro
 * └─ nested (pediment-panels) ─ n0 (pediment-panel)
 */
const children: Record< string, string[] > = {
	set: [ 'panels', 'tabs', 'intro', 'nested' ],
	panels: [ 'p0', 'p1' ],
	p0: [ 'p0-text' ],
	p1: [ 'p1-text' ],
	tabs: [ 't0', 't1' ],
	t1: [ 't1-text' ],
	nested: [ 'n0' ],
};
const classes: Record< string, string > = {
	set: 'pediment-panels',
	p0: 'pediment-panel',
	p1: 'is-style-card pediment-panel',
	t0: 'pediment-panel-tab',
	t1: 'pediment-panel-tab',
	nested: 'pediment-panels',
	n0: 'pediment-panel',
};
const tree: BlockTree = {
	order: ( id ) => children[ id ] ?? [],
	className: ( id ) => classes[ id ],
};
const chain = ( ...ids: string[] ) => new Set( [ ...ids, 'set' ] );

describe( 'selectedPanelIndex', () => {
	it( 'finds the panel that is selected', () => {
		expect( selectedPanelIndex( tree, 'set', chain( 'p1' ) ) ).toBe( 1 );
	} );

	it( 'finds the panel whose inner block is selected', () => {
		expect(
			selectedPanelIndex( tree, 'set', chain( 'p0-text', 'p0' ) )
		).toBe( 0 );
	} );

	it( 'maps a selected tab to its panel index', () => {
		expect(
			selectedPanelIndex( tree, 'set', chain( 't1-text', 't1' ) )
		).toBe( 1 );
	} );

	it( 'returns null when the selection is outside every panel and tab', () => {
		expect( selectedPanelIndex( tree, 'set', chain( 'intro' ) ) ).toBe(
			null
		);
		expect( selectedPanelIndex( tree, 'set', chain() ) ).toBe( null );
	} );

	it( 'leaves the panels of a nested set to that set', () => {
		expect(
			selectedPanelIndex( tree, 'set', chain( 'n0', 'nested' ) )
		).toBe( null );
		expect(
			selectedPanelIndex( tree, 'nested', new Set( [ 'n0', 'nested' ] ) )
		).toBe( 0 );
	} );

	it( 'ignores class names that only contain the marker', () => {
		const other: BlockTree = {
			order: ( id ) => ( id === 'set' ? [ 'a' ] : [] ),
			className: ( id ) =>
				id === 'a' ? 'pediment-panel-tabs' : undefined,
		};
		expect( selectedPanelIndex( other, 'set', new Set( [ 'a' ] ) ) ).toBe(
			null
		);
	} );
} );
