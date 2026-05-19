import { __ } from '@wordpress/i18n';
import {
	useBlockProps,
	RichText,
	InspectorControls,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalLinkControl as LinkControl,
} from '@wordpress/block-editor';
import { PanelBody, TextControl } from '@wordpress/components';

type Attrs = { label: string; url: string; description: string; icon: string };

export default function Edit( {
	attributes,
	setAttributes,
}: {
	attributes: Attrs;
	setAttributes: ( a: Partial< Attrs > ) => void;
} ) {
	const blockProps = useBlockProps( { className: 'starter-mega-link' } );
	const { label, url, description, icon } = attributes;
	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Link', 'starter' ) }>
					<LinkControl
						value={ { url } }
						onChange={ ( next: { url?: string } ) =>
							setAttributes( { url: next.url ?? '' } )
						}
					/>
					<TextControl
						label={ __( 'Icon (Phosphor name)', 'starter' ) }
						value={ icon }
						onChange={ ( v ) => setAttributes( { icon: v } ) }
						help={ __( 'e.g. gear, bank, article', 'starter' ) }
					/>
				</PanelBody>
			</InspectorControls>
			<div { ...blockProps }>
				{ icon ? (
					<svg
						className="starter-mega-link__icon"
						aria-hidden="true"
						focusable="false"
					>
						<use href={ `#ph-${ icon }` } />
					</svg>
				) : (
					<span
						className="starter-mega-link__icon starter-mega-link__icon--empty"
						aria-hidden="true"
					/>
				) }
				<RichText
					tagName="span"
					className="starter-mega-link__label"
					value={ label }
					onChange={ ( v ) => setAttributes( { label: v } ) }
					placeholder={ __( 'Link label…', 'starter' ) }
				/>
				<RichText
					tagName="span"
					className="starter-mega-link__desc"
					value={ description }
					onChange={ ( v ) => setAttributes( { description: v } ) }
					placeholder={ __( 'Short description…', 'starter' ) }
				/>
			</div>
		</>
	);
}
