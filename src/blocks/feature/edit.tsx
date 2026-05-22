import { __ } from '@wordpress/i18n';
import {
	useBlockProps,
	RichText,
	InspectorControls,
} from '@wordpress/block-editor';
import { PanelBody, TextControl } from '@wordpress/components';

type Attrs = {
	icon: string;
	title: string;
	text: string;
	linkText: string;
	linkUrl: string;
};

export default function Edit( {
	attributes,
	setAttributes,
}: {
	attributes: Attrs;
	setAttributes: ( a: Partial< Attrs > ) => void;
} ) {
	const blockProps = useBlockProps( { className: 'starter-feature' } );
	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Feature', 'starter' ) }>
					<TextControl
						label={ __( 'Phosphor icon name', 'starter' ) }
						value={ attributes.icon }
						onChange={ ( v ) => setAttributes( { icon: v } ) }
						help={ __( 'e.g. trend-up, gear, stack', 'starter' ) }
					/>
					<TextControl
						label={ __( 'Link URL', 'starter' ) }
						value={ attributes.linkUrl }
						onChange={ ( v ) => setAttributes( { linkUrl: v } ) }
					/>
				</PanelBody>
			</InspectorControls>
			<div { ...blockProps }>
				<div className="starter-feature__ic" aria-hidden="true">
					{ attributes.icon && (
						<svg
							className="i"
							aria-hidden="true"
							focusable={ false }
						>
							<use
								href={ `#ph-${ attributes.icon
									.toLowerCase()
									.replace( /[^a-z0-9-]/g, '' ) }` }
							/>
						</svg>
					) }
				</div>
				<RichText
					tagName="h3"
					className="starter-feature__title"
					value={ attributes.title }
					onChange={ ( v ) => setAttributes( { title: v } ) }
					placeholder={ __( 'Title…', 'starter' ) }
				/>
				<RichText
					tagName="p"
					className="starter-feature__text"
					value={ attributes.text }
					onChange={ ( v ) => setAttributes( { text: v } ) }
					placeholder={ __( 'Description…', 'starter' ) }
				/>
				<RichText
					tagName="span"
					className="starter-feature__more"
					value={ attributes.linkText }
					onChange={ ( v ) => setAttributes( { linkText: v } ) }
					placeholder={ __( 'Link text (optional)…', 'starter' ) }
				/>
			</div>
		</>
	);
}
