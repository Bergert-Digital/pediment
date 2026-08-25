import { __ } from '@wordpress/i18n';
import {
	useBlockProps,
	RichText,
	InspectorControls,
	MediaUpload,
} from '@wordpress/block-editor';
import { PanelBody, TextControl, Button } from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import IconPicker from '../../components/icon-picker';
import IconPreview from '../../components/icon-picker/IconPreview';

type Attrs = {
	icon: string;
	imageId: number;
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
	const media = useSelect(
		( select: any ) => {
			return attributes.imageId
				? select( 'core' ).getMedia( attributes.imageId )
				: null;
		},
		[ attributes.imageId ]
	);

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Feature', 'pediment' ) }>
					<IconPicker
						label={ __( 'Icon', 'pediment' ) }
						value={ attributes.icon }
						onChange={ ( icon ) => setAttributes( { icon } ) }
					/>
					<MediaUpload
						allowedTypes={ [ 'image' ] }
						value={ attributes.imageId }
						onSelect={ ( m: any ) =>
							setAttributes( { imageId: m.id } )
						}
						render={ ( { open }: { open: () => void } ) => (
							<Button variant="secondary" onClick={ open }>
								{ attributes.imageId
									? __( 'Replace image', 'pediment' )
									: __(
											'Pick image instead of icon (optional)',
											'pediment'
									  ) }
							</Button>
						) }
					/>
					{ attributes.imageId !== 0 && (
						<Button
							variant="tertiary"
							isDestructive
							onClick={ () => setAttributes( { imageId: 0 } ) }
						>
							{ __( 'Remove image', 'pediment' ) }
						</Button>
					) }
					<TextControl
						label={ __( 'Link URL', 'pediment' ) }
						value={ attributes.linkUrl }
						onChange={ ( v ) => setAttributes( { linkUrl: v } ) }
					/>
				</PanelBody>
			</InspectorControls>
			<div { ...blockProps }>
				{ media ? (
					<img
						className="starter-feature__img"
						src={ ( media as any ).source_url }
						alt=""
					/>
				) : (
					<div className="starter-feature__ic" aria-hidden="true">
						{ attributes.icon && (
							<IconPreview slug={ attributes.icon } />
						) }
					</div>
				) }
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
