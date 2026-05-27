import { __ } from '@wordpress/i18n';
import { useBlockProps, RichText } from '@wordpress/block-editor';

type Attrs = { value: string; label: string; context: string };

export default function Edit( {
	attributes,
	setAttributes,
}: {
	attributes: Attrs;
	setAttributes: ( a: Partial< Attrs > ) => void;
} ) {
	const blockProps = useBlockProps( { className: 'starter-stat' } );
	return (
		<div { ...blockProps }>
			<RichText
				tagName="strong"
				className="starter-stat__value"
				value={ attributes.value }
				onChange={ ( v ) => setAttributes( { value: v } ) }
				placeholder={ __( '99%', 'pediment' ) }
			/>
			<RichText
				tagName="span"
				className="starter-stat__label"
				value={ attributes.label }
				onChange={ ( v ) => setAttributes( { label: v } ) }
				placeholder={ __( 'Uptime', 'pediment' ) }
			/>
			<RichText
				tagName="span"
				className="starter-stat__context"
				value={ attributes.context }
				onChange={ ( v ) => setAttributes( { context: v } ) }
				placeholder={ __( 'Context (optional)', 'pediment' ) }
			/>
		</div>
	);
}
