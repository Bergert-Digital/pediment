import { __ } from '@wordpress/i18n';
import {
	useBlockProps,
	useInnerBlocksProps,
	RichText,
} from '@wordpress/block-editor';

const ALLOWED = [ 'core/image' ];
const TEMPLATE: [ string, Record< string, unknown > ][] = [
	[ 'core/image', {} ],
	[ 'core/image', {} ],
	[ 'core/image', {} ],
];

type Attrs = { caption: string };

export default function Edit( {
	attributes,
	setAttributes,
}: {
	attributes: Attrs;
	setAttributes: ( a: Partial< Attrs > ) => void;
} ) {
	const blockProps = useBlockProps( { className: 'starter-logo-cloud' } );
	const innerProps = useInnerBlocksProps(
		{ className: 'starter-logo-cloud__row' },
		{
			allowedBlocks: ALLOWED,
			template: TEMPLATE,
			orientation: 'horizontal',
		}
	);
	return (
		<section { ...blockProps }>
			<RichText
				tagName="p"
				className="starter-logo-cloud__caption"
				value={ attributes.caption }
				onChange={ ( v ) => setAttributes( { caption: v } ) }
				placeholder={ __( 'Trusted by…', 'pediment' ) }
			/>
			<div { ...innerProps } />
		</section>
	);
}
