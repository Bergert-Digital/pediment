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
				tagName="div"
				className="starter-faq-item__question"
				value={ attributes.question }
				onChange={ ( v ) => setAttributes( { question: v } ) }
				placeholder={ __( 'Question…', 'pediment' ) }
			/>
			<RichText
				tagName="div"
				className="starter-faq-item__answer"
				value={ attributes.answer }
				onChange={ ( v ) => setAttributes( { answer: v } ) }
				placeholder={ __( 'Answer…', 'pediment' ) }
			/>
		</div>
	);
}
