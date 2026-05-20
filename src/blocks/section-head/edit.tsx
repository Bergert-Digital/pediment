import { __ } from '@wordpress/i18n';
import {
	useBlockProps,
	RichText,
	InspectorControls,
} from '@wordpress/block-editor';
import {
	PanelBody,
	__experimentalToggleGroupControl as ToggleGroupControl,
	__experimentalToggleGroupControlOption as ToggleGroupControlOption,
} from '@wordpress/components';

type Attrs = {
	eyebrow: string;
	headline: string;
	lead: string;
	alignment: 'start' | 'center';
	level: 2 | 3;
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
				<PanelBody title={ __( 'Section head', 'starter' ) }>
					<ToggleGroupControl
						label={ __( 'Alignment', 'starter' ) }
						value={ attributes.alignment }
						onChange={ ( v ) =>
							setAttributes( { alignment: ( v as 'start' | 'center' ) ?? 'start' } )
						}
						isBlock
					>
						<ToggleGroupControlOption value="start" label={ __( 'Start', 'starter' ) } />
						<ToggleGroupControlOption value="center" label={ __( 'Center', 'starter' ) } />
					</ToggleGroupControl>
					<ToggleGroupControl
						label={ __( 'Heading level', 'starter' ) }
						value={ String( attributes.level ) }
						onChange={ ( v ) =>
							setAttributes( { level: v === '3' ? 3 : 2 } )
						}
						isBlock
					>
						<ToggleGroupControlOption value="2" label="H2" />
						<ToggleGroupControlOption value="3" label="H3" />
					</ToggleGroupControl>
				</PanelBody>
			</InspectorControls>
			<div { ...blockProps }>
				<div className="starter-section-head__inner">
					<RichText
						tagName="p"
						className="starter-section-head__eyebrow"
						value={ attributes.eyebrow }
						onChange={ ( v ) => setAttributes( { eyebrow: v } ) }
						placeholder={ __( 'Eyebrow…', 'starter' ) }
						allowedFormats={ [] }
					/>
					<RichText
						tagName={ HeadingTag }
						className="starter-section-head__headline"
						value={ attributes.headline }
						onChange={ ( v ) => setAttributes( { headline: v } ) }
						placeholder={ __( 'Headline…', 'starter' ) }
						allowedFormats={ [] }
					/>
					<RichText
						tagName="p"
						className="starter-section-head__lead"
						value={ attributes.lead }
						onChange={ ( v ) => setAttributes( { lead: v } ) }
						placeholder={ __( 'Lead…', 'starter' ) }
						allowedFormats={ [] }
					/>
				</div>
			</div>
		</>
	);
}
