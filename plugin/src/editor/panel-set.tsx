/**
 * Panel sets in the editor canvas: a block with the class `pediment-panels`
 * gets `data-state="<n>"` while panel n, tab n, or a block inside one of them
 * is selected. The front end of such a set shows one panel at a time and keys
 * its CSS on the same `data-state` (set by the theme's own script), so the
 * editor shows the panel a person is editing, and every panel's text can be
 * reached with a click or from List View. With no such selection the set has
 * no `data-state`, and the theme's default panel shows.
 *
 * See docs/blocks.md#panel-sets.
 */
import { addFilter } from '@wordpress/hooks';
import { useSelect } from '@wordpress/data';
import { store as blockEditorStore } from '@wordpress/block-editor';
import type { ComponentType } from '@wordpress/element';
import { SET_CLASS, hasClass, selectedPanelIndex } from './panel-set-index';

type BlockEditorSelectors = {
	getSelectedBlockClientId: () => string | null;
	getBlockParents: ( clientId: string ) => string[];
	getBlockOrder: ( clientId: string ) => string[];
	getBlockAttributes: (
		clientId: string
	) => Record< string, unknown > | null;
};

type BlockListBlockProps = {
	clientId: string;
	attributes?: Record< string, unknown >;
	wrapperProps?: Record< string, unknown >;
};

function PanelSet( {
	BlockListBlock,
	...props
}: BlockListBlockProps & {
	BlockListBlock: ComponentType< BlockListBlockProps >;
} ) {
	const index = useSelect(
		( select ) => {
			const store = select(
				blockEditorStore
			) as unknown as BlockEditorSelectors;
			const selected = store.getSelectedBlockClientId();
			if ( ! selected ) {
				return null;
			}
			const chain = new Set( [
				selected,
				...store.getBlockParents( selected ),
			] );
			if ( ! chain.has( props.clientId ) ) {
				return null;
			}
			return selectedPanelIndex(
				{
					order: store.getBlockOrder,
					className: ( id ) =>
						store.getBlockAttributes( id )?.className,
				},
				props.clientId,
				chain
			);
		},
		[ props.clientId ]
	);

	if ( index === null ) {
		return <BlockListBlock { ...props } />;
	}
	return (
		<BlockListBlock
			{ ...props }
			wrapperProps={ {
				...props.wrapperProps,
				'data-state': String( index ),
			} }
		/>
	);
}

function withPanelState(
	BlockListBlock: ComponentType< BlockListBlockProps >
) {
	return function PanelSetOrBlock( props: BlockListBlockProps ) {
		return hasClass( props.attributes?.className, SET_CLASS ) ? (
			<PanelSet BlockListBlock={ BlockListBlock } { ...props } />
		) : (
			<BlockListBlock { ...props } />
		);
	};
}

addFilter( 'editor.BlockListBlock', 'pediment/panel-set', withPanelState );
