import { __ } from '@wordpress/i18n';
import {
	useBlockProps,
	useInnerBlocksProps,
	RichText,
} from '@wordpress/block-editor';

const ALLOWED = [ 'starter/mega-link' ];
const TEMPLATE: [ string, Record< string, unknown > ][] = [
	[ 'starter/mega-link', {} ],
	[ 'starter/mega-link', {} ],
];

type Attrs = { heading: string };

export default function Edit( {
	attributes,
	setAttributes,
}: {
	attributes: Attrs;
	setAttributes: ( a: Partial< Attrs > ) => void;
} ) {
	const blockProps = useBlockProps( { className: 'starter-mega-column' } );
	const innerBlocksProps = useInnerBlocksProps(
		{ className: 'starter-mega-column__links' },
		{ allowedBlocks: ALLOWED, template: TEMPLATE, templateLock: false }
	);
	return (
		<div { ...blockProps }>
			<RichText
				tagName="div"
				className="starter-mega-column__heading"
				value={ attributes.heading }
				onChange={ ( v ) => setAttributes( { heading: v } ) }
				placeholder={ __( 'Column heading…', 'starter' ) }
			/>
			<div { ...innerBlocksProps } />
		</div>
	);
}
