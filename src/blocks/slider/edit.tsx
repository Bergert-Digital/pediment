import { __ } from '@wordpress/i18n';
import {
	useBlockProps,
	useInnerBlocksProps,
	InspectorControls,
	PanelColorSettings,
} from '@wordpress/block-editor';
import { PanelBody, ToggleControl } from '@wordpress/components';

type Attrs = {
	mediaPosition: string;
	panelColor: string;
};

const ALLOWED = [ 'pediment/slide' ];
const TEMPLATE: [ string, Record< string, unknown > ][] = [
	[ 'pediment/slide', {} ],
	[ 'pediment/slide', {} ],
];

export default function Edit( {
	attributes,
	setAttributes,
}: {
	attributes: Attrs;
	setAttributes: ( a: Partial< Attrs > ) => void;
} ) {
	const position = attributes.mediaPosition === 'right' ? 'right' : 'left';
	const blockProps = useBlockProps( {
		className: `starter-slider is-editor is-media-${ position }`,
		style: {
			[ '--slide-panel-bg' as string ]:
				attributes.panelColor || undefined,
		},
	} );
	const innerProps = useInnerBlocksProps( blockProps, {
		allowedBlocks: ALLOWED,
		template: TEMPLATE,
		templateLock: false,
		orientation: 'vertical',
	} );

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Layout', 'pediment' ) }>
					<ToggleControl
						label={ __( 'Image on the left', 'pediment' ) }
						checked={ position === 'left' }
						onChange={ ( v ) =>
							setAttributes( {
								mediaPosition: v ? 'left' : 'right',
							} )
						}
						help={ __(
							'Off places the image on the right of every slide.',
							'pediment'
						) }
					/>
				</PanelBody>
			</InspectorControls>
			<InspectorControls group="color">
				<PanelColorSettings
					title={ __( 'Panel', 'pediment' ) }
					colorSettings={ [
						{
							value: attributes.panelColor,
							onChange: ( c?: string ) =>
								setAttributes( { panelColor: c || '#0A1B33' } ),
							label: __( 'Panel background', 'pediment' ),
						},
					] }
				/>
			</InspectorControls>
			<section { ...innerProps } />
		</>
	);
}
