import { __ } from '@wordpress/i18n';
import { useBlockProps, RichText } from '@wordpress/block-editor';

type Attrs = { quote: string; citation: string };

export default function Edit( {
	attributes,
	setAttributes,
}: {
	attributes: Attrs;
	setAttributes: ( a: Partial< Attrs > ) => void;
} ) {
	const blockProps = useBlockProps( { className: 'starter-pull-quote' } );
	return (
		<blockquote { ...blockProps }>
			<RichText
				tagName="p"
				value={ attributes.quote }
				onChange={ ( v ) => setAttributes( { quote: v } ) }
				placeholder={ __( 'Quote…', 'starter' ) }
			/>
			<RichText
				tagName="cite"
				value={ attributes.citation }
				onChange={ ( v ) => setAttributes( { citation: v } ) }
				placeholder={ __( 'Citation (optional)…', 'starter' ) }
			/>
		</blockquote>
	);
}
