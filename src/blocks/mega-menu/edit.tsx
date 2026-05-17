import { __ } from '@wordpress/i18n';
import {
	useBlockProps,
	useInnerBlocksProps,
	RichText,
} from '@wordpress/block-editor';

const ALLOWED = [ 'starter/mega-column' ];
const TEMPLATE: [ string, Record< string, unknown >, unknown[] ][] = [
	[ 'starter/mega-column', {}, [] ],
	[ 'starter/mega-column', {}, [] ],
	[ 'starter/mega-column', {}, [] ],
];

type Attrs = { label: string };

export default function Edit( {
	attributes,
	setAttributes,
}: {
	attributes: Attrs;
	setAttributes: ( a: Partial< Attrs > ) => void;
} ) {
	const blockProps = useBlockProps( { className: 'starter-mega-menu' } );
	const innerBlocksProps = useInnerBlocksProps(
		{ className: 'starter-mega-menu__panel' },
		{ allowedBlocks: ALLOWED, template: TEMPLATE, templateLock: false }
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
