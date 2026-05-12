import { __ } from '@wordpress/i18n';
import { useBlockProps, RichText } from '@wordpress/block-editor';

type Attrs = { question: string; answer: string };

export default function Edit( {
	attributes,
	setAttributes,
}: {
	attributes: Attrs;
	setAttributes: ( a: Partial< Attrs > ) => void;
} ) {
	const blockProps = useBlockProps( { className: 'starter-faq-item' } );
	return (
		<div { ...blockProps }>
			<RichText
				tagName="strong"
				value={ attributes.question }
				onChange={ ( v ) => setAttributes( { question: v } ) }
				placeholder={ __( 'Question…', 'starter' ) }
			/>
			<RichText
				tagName="p"
				value={ attributes.answer }
				onChange={ ( v ) => setAttributes( { answer: v } ) }
				placeholder={ __( 'Answer…', 'starter' ) }
			/>
		</div>
	);
}
