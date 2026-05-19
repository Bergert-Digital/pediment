import { __ } from '@wordpress/i18n';
import {
	useBlockProps,
	useInnerBlocksProps,
	RichText,
	store as blockEditorStore,
} from '@wordpress/block-editor';
import { useDispatch } from '@wordpress/data';
import { createBlock } from '@wordpress/blocks';
import { Button } from '@wordpress/components';

const ALLOWED = [ 'starter/mega-column' ];
const TEMPLATE: [ string, Record< string, unknown >, unknown[] ][] = [
	[ 'starter/mega-column', {}, [] ],
];

type Attrs = { label: string };

export default function Edit( {
	attributes,
	setAttributes,
	clientId,
}: {
	attributes: Attrs;
	setAttributes: ( a: Partial< Attrs > ) => void;
	clientId: string;
} ) {
	const blockProps = useBlockProps( { className: 'starter-mega-menu' } );
	const { insertBlock } = useDispatch( blockEditorStore );
	const addColumn = () =>
		insertBlock(
			createBlock( 'starter/mega-column' ),
			undefined,
			clientId
		);
	const innerBlocksProps = useInnerBlocksProps(
		{ className: 'starter-mega-menu__panel' },
		{
			allowedBlocks: ALLOWED,
			template: TEMPLATE,
			templateLock: false,
			renderAppender: () => (
				<Button
					variant="secondary"
					icon="plus"
					className="starter-mega-menu__add-col"
					onClick={ addColumn }
				>
					{ __( 'Add column', 'starter' ) }
				</Button>
			),
		}
	);
	return (
		<div { ...blockProps }>
			<RichText
				tagName="span"
				className="starter-mega-menu__trigger"
				value={ attributes.label }
				onChange={ ( v ) => setAttributes( { label: v } ) }
				placeholder={ __( 'Menu label…', 'starter' ) }
			/>
			<div { ...innerBlocksProps } />
		</div>
	);
}
