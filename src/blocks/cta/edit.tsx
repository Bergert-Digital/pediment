import { __ } from '@wordpress/i18n';
import {
	useBlockProps,
	RichText,
	InspectorControls,
} from '@wordpress/block-editor';
import { PanelBody, TextControl } from '@wordpress/components';

type Attrs = {
	title: string;
	body: string;
	primaryText: string;
	primaryUrl: string;
	secondaryText: string;
	secondaryUrl: string;
};

export default function Edit( {
	attributes,
	setAttributes,
}: {
	attributes: Attrs;
	setAttributes: ( a: Partial< Attrs > ) => void;
} ) {
	const blockProps = useBlockProps( { className: 'starter-cta' } );
	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'CTA links', 'starter' ) }>
					<TextControl
						label="Primary URL"
						value={ attributes.primaryUrl }
						onChange={ ( v ) => setAttributes( { primaryUrl: v } ) }
					/>
					<TextControl
						label="Secondary URL"
						value={ attributes.secondaryUrl }
						onChange={ ( v ) =>
							setAttributes( { secondaryUrl: v } )
						}
					/>
				</PanelBody>
			</InspectorControls>
			<div { ...blockProps }>
				<RichText
					tagName="h2"
					value={ attributes.title }
					onChange={ ( v ) => setAttributes( { title: v } ) }
					placeholder={ __( 'Title…', 'starter' ) }
				/>
				<RichText
					tagName="p"
					value={ attributes.body }
					onChange={ ( v ) => setAttributes( { body: v } ) }
					placeholder={ __( 'Body…', 'starter' ) }
				/>
				<RichText
					tagName="span"
					value={ attributes.primaryText }
					onChange={ ( v ) => setAttributes( { primaryText: v } ) }
					placeholder={ __( 'Primary CTA…', 'starter' ) }
				/>
				<RichText
					tagName="span"
					value={ attributes.secondaryText }
					onChange={ ( v ) => setAttributes( { secondaryText: v } ) }
					placeholder={ __( 'Secondary CTA (optional)…', 'starter' ) }
				/>
			</div>
		</>
	);
}
