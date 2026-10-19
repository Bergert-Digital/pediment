import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, TextControl } from '@wordpress/components';
import IconPicker from '../../components/icon-picker';
import IconPreview from '../../components/icon-picker/IconPreview';

type Attrs = {
	icon: string;
	label: string;
};

export default function Edit( {
	attributes,
	setAttributes,
}: {
	attributes: Attrs;
	setAttributes: ( a: Partial< Attrs > ) => void;
} ) {
	const blockProps = useBlockProps();

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Icon', 'pediment' ) }>
					<IconPicker
						label={ __( 'Icon', 'pediment' ) }
						value={ attributes.icon }
						onChange={ ( icon ) => setAttributes( { icon } ) }
					/>
					<TextControl
						__nextHasNoMarginBottom
						__next40pxDefaultSize
						label={ __( 'Label', 'pediment' ) }
						help={ __(
							'Screen readers read the label. Leave it empty when the icon only decorates.',
							'pediment'
						) }
						value={ attributes.label }
						onChange={ ( label ) => setAttributes( { label } ) }
					/>
				</PanelBody>
			</InspectorControls>
			<div { ...blockProps }>
				{ attributes.icon ? (
					<span
						className="wp-block-pediment-icon__glyph"
						aria-hidden="true"
					>
						<IconPreview slug={ attributes.icon } />
					</span>
				) : (
					<span className="wp-block-pediment-icon__empty">
						{ __(
							'Choose an icon in the block settings.',
							'pediment'
						) }
					</span>
				) }
			</div>
		</>
	);
}
