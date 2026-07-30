import { __ } from '@wordpress/i18n';
import {
	useBlockProps,
	RichText,
	InspectorControls,
} from '@wordpress/block-editor';
import { PanelBody, TextControl } from '@wordpress/components';
import IconPicker from '../../components/icon-picker';
import IconPreview from '../../components/icon-picker/IconPreview';

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
				<PanelBody title={ __( 'Feature', 'pediment' ) }>
					<IconPicker
						label={ __( 'Icon', 'pediment' ) }
						value={ attributes.icon }
						onChange={ ( icon ) => setAttributes( { icon } ) }
					/>
					<TextControl
						label={ __( 'Link URL', 'pediment' ) }
						value={ attributes.linkUrl }
						onChange={ ( v ) => setAttributes( { linkUrl: v } ) }
					/>
				</PanelBody>
			</InspectorControls>
			<div { ...blockProps }>
				<div className="starter-feature__ic" aria-hidden="true">
					{ attributes.icon && (
						<IconPreview slug={ attributes.icon } />
					) }
				</div>
				<RichText
					tagName="h3"
					className="starter-feature__title"
					value={ attributes.title }
					onChange={ ( v ) => setAttributes( { title: v } ) }
					placeholder={ __( 'Title…', 'pediment' ) }
				/>
				<RichText
					tagName="p"
					className="starter-feature__text"
					value={ attributes.text }
					onChange={ ( v ) => setAttributes( { text: v } ) }
					placeholder={ __( 'Description…', 'pediment' ) }
				/>
				<RichText
					tagName="span"
					className="starter-feature__more"
					value={ attributes.linkText }
					onChange={ ( v ) => setAttributes( { linkText: v } ) }
					placeholder={ __( 'Link text (optional)…', 'pediment' ) }
				/>
			</div>
		</>
	);
}
