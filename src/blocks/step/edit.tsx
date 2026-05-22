import { __ } from '@wordpress/i18n';
import { useBlockProps, RichText } from '@wordpress/block-editor';

type Attrs = { title: string; text: string };

export default function Edit( {
	attributes,
	setAttributes,
}: {
	attributes: Attrs;
	setAttributes: ( a: Partial< Attrs > ) => void;
} ) {
	const blockProps = useBlockProps( { className: 'starter-step' } );
	return (
		<div { ...blockProps }>
			<span className="starter-step__num" aria-hidden="true" />
			<div>
				<RichText
					tagName="h3"
					className="starter-step__title"
					value={ attributes.title }
					onChange={ ( v ) => setAttributes( { title: v } ) }
					placeholder={ __( 'Step title…', 'pediment' ) }
				/>
				<RichText
					tagName="p"
					className="starter-step__text"
					value={ attributes.text }
					onChange={ ( v ) => setAttributes( { text: v } ) }
					placeholder={ __( 'Step description…', 'pediment' ) }
				/>
			</div>
		</div>
	);
}
