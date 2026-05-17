import { __ } from '@wordpress/i18n';
import { useBlockProps, RichText } from '@wordpress/block-editor';
import { TextControl } from '@wordpress/components';

type Attrs = { label: string; url: string; description: string; icon: string };

export default function Edit( {
	attributes,
	setAttributes,
}: {
	attributes: Attrs;
	setAttributes: ( a: Partial< Attrs > ) => void;
} ) {
	const blockProps = useBlockProps( { className: 'starter-mega-link' } );
	return (
		<div { ...blockProps }>
			<TextControl
				label={ __( 'Icon (Phosphor name)', 'starter' ) }
				value={ attributes.icon }
				onChange={ ( v ) => setAttributes( { icon: v } ) }
			/>
			<TextControl
				label={ __( 'URL', 'starter' ) }
				value={ attributes.url }
				onChange={ ( v ) => setAttributes( { url: v } ) }
			/>
			<RichText
				tagName="div"
				className="starter-mega-link__label"
				value={ attributes.label }
				onChange={ ( v ) => setAttributes( { label: v } ) }
				placeholder={ __( 'Link label…', 'starter' ) }
			/>
			<RichText
				tagName="div"
				className="starter-mega-link__desc"
				value={ attributes.description }
				onChange={ ( v ) => setAttributes( { description: v } ) }
				placeholder={ __( 'Short description…', 'starter' ) }
			/>
		</div>
	);
}
