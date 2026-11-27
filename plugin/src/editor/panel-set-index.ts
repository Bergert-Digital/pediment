/**
 * Which panel of a panel set holds the editor selection. Pure, so it is unit
 * tested without the block editor (panel-set-index.test.ts); panel-set.tsx
 * feeds it the block-editor store.
 *
 * A panel set is a block with the class `pediment-panels`. Its panels carry
 * `pediment-panel`, its triggers (tabs, thumbnails, dots) carry
 * `pediment-panel-tab`. Panel n and tab n belong together. Both may sit at any
 * depth inside the set, but not inside a nested set: a nested set counts its
 * own panels.
 */

export const SET_CLASS = 'pediment-panels';
export const PANEL_CLASS = 'pediment-panel';
export const TAB_CLASS = 'pediment-panel-tab';

export type BlockTree = {
	/** Client ids of a block's inner blocks, in order. */
	order: ( clientId: string ) => string[];
	/** The block's `className` attribute. */
	className: ( clientId: string ) => unknown;
};

export const hasClass = ( className: unknown, name: string ): boolean =>
	typeof className === 'string' && className.split( /\s+/ ).includes( name );

/**
 * @param tree  Read access to the block tree.
 * @param setId Client id of the panel set.
 * @param chain The selected block and all its ancestors.
 * @return Zero-based index of the panel (or tab) in `chain`, or null when the
 *         selection is in neither.
 */
export function selectedPanelIndex(
	tree: BlockTree,
	setId: string,
	chain: ReadonlySet< string >
): number | null {
	const panels: string[] = [];
	const tabs: string[] = [];
	const walk = ( id: string ) => {
		for ( const child of tree.order( id ) ) {
			const className = tree.className( child );
			if ( hasClass( className, SET_CLASS ) ) {
				continue;
			}
			if ( hasClass( className, PANEL_CLASS ) ) {
				panels.push( child );
			} else if ( hasClass( className, TAB_CLASS ) ) {
				tabs.push( child );
			} else {
				walk( child );
			}
		}
	};
	walk( setId );

	for ( const list of [ panels, tabs ] ) {
		const index = list.findIndex( ( id ) => chain.has( id ) );
		if ( index !== -1 ) {
			return index;
		}
	}
	return null;
}
