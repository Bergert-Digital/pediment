import { __ } from '@wordpress/i18n';
import {
	useBlockProps,
	RichText,
	InspectorControls,
} from '@wordpress/block-editor';
import {
	PanelBody,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis -- public ToggleGroupControl not yet stabilised in @wordpress/components v28
	__experimentalToggleGroupControl as ToggleGroupControl,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis -- see above
	__experimentalToggleGroupControlOption as ToggleGroupControlOption,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis -- UnitControl still experimental in v28
	__experimentalUnitControl as UnitControl,
} from '@wordpress/components';

type Attrs = {
	eyebrow: string;
	headline: string;
	lead: string;
	alignment: 'start' | 'center';
	level: 2 | 3;
	maxWidth: string;
};

export default function Edit( {
	attributes,
	setAttributes,
}: {
	attributes: Attrs;
	setAttributes: ( a: Partial< Attrs > ) => void;
} ) {
	const blockProps = useBlockProps( {
		className: `starter-section-head is-alignment-${ attributes.alignment }`,
	} );
	const HeadingTag = `h${ attributes.level }` as 'h2' | 'h3';
	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Section head', 'pediment' ) }>
					<ToggleGroupControl
						label={ __( 'Alignment', 'pediment' ) }
						value={ attributes.alignment }
						onChange={ ( v ) =>
							setAttributes( {
								alignment:
									( v as 'start' | 'center' ) ?? 'start',
							} )
						}
						isBlock
					>
						<ToggleGroupControlOption
							value="start"
							label={ __( 'Start', 'pediment' ) }
						/>
						<ToggleGroupControlOption
							value="center"
							label={ __( 'Center', 'pediment' ) }
						/>
					</ToggleGroupControl>
					<ToggleGroupControl
						label={ __( 'Heading level', 'pediment' ) }
						value={ String( attributes.level ) }
						onChange={ ( v ) =>
							setAttributes( { level: v === '3' ? 3 : 2 } )
						}
						isBlock
					>
						<ToggleGroupControlOption value="2" label="H2" />
						<ToggleGroupControlOption value="3" label="H3" />
					</ToggleGroupControl>
					<UnitControl
						label={ __( 'Max width', 'pediment' ) }
						help={ __(
							'Leave empty to follow the block’s alignment.',
							'pediment'
						) }
						value={ attributes.maxWidth }
						units={ [
							{ value: 'px', label: 'px' },
							{ value: 'rem', label: 'rem' },
							{ value: '%', label: '%' },
						] }
						onChange={ ( v ) =>
							setAttributes( { maxWidth: v ?? '' } )
						}
					/>
				</PanelBody>
			</InspectorControls>
			<div { ...blockProps }>
				<div
					className="starter-section-head__inner"
					style={
						attributes.maxWidth
							? { maxWidth: attributes.maxWidth }
							: undefined
					}
				>
					<RichText
						tagName="p"
						className="starter-section-head__eyebrow"
						value={ attributes.eyebrow }
						onChange={ ( v ) => setAttributes( { eyebrow: v } ) }
						placeholder={ __( 'Eyebrow…', 'pediment' ) }
						allowedFormats={ [] }
					/>
					<RichText
						tagName={ HeadingTag }
						className="starter-section-head__headline"
						value={ attributes.headline }
						onChange={ ( v ) => setAttributes( { headline: v } ) }
						placeholder={ __( 'Headline…', 'pediment' ) }
						allowedFormats={ [] }
					/>
					<RichText
						tagName="p"
						className="starter-section-head__lead"
						value={ attributes.lead }
						onChange={ ( v ) => setAttributes( { lead: v } ) }
						placeholder={ __( 'Lead…', 'pediment' ) }
						allowedFormats={ [] }
					/>
				</div>
			</div>
		</>
	);
}
