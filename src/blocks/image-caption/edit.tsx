import { __ } from '@wordpress/i18n';
import {
	useBlockProps,
	RichText,
	MediaUpload,
	InspectorControls,
} from '@wordpress/block-editor';
import { PanelBody, TextControl, Button } from '@wordpress/components';
import { useSelect } from '@wordpress/data';

type Attrs = { mediaId: number; caption: string; altOverride: string };

export default function Edit( {
	attributes,
	setAttributes,
}: {
	attributes: Attrs;
	setAttributes: ( a: Partial< Attrs > ) => void;
} ) {
	const blockProps = useBlockProps( { className: 'starter-image-caption' } );
	const media = useSelect(
		( select: any ) => {
			return attributes.mediaId
				? select( 'core' ).getMedia( attributes.mediaId )
				: null;
		},
		[ attributes.mediaId ]
	);

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Image', 'pediment' ) }>
					<TextControl
						label={ __( 'Alt text override', 'pediment' ) }
						value={ attributes.altOverride }
						onChange={ ( v ) =>
							setAttributes( { altOverride: v } )
						}
						help={ __(
							'Leave empty to use the media library alt text.',
							'pediment'
						) }
					/>
				</PanelBody>
			</InspectorControls>
			<figure { ...blockProps }>
				<MediaUpload
					allowedTypes={ [ 'image' ] }
					value={ attributes.mediaId }
					onSelect={ ( m: any ) =>
						setAttributes( { mediaId: m.id } )
					}
					render={ ( { open }: { open: () => void } ) =>
						media ? (
							<button
								type="button"
								className="starter-image-caption__replace"
								onClick={ open }
								aria-label={ __( 'Replace image', 'pediment' ) }
							>
								<img
									className="starter-image-caption__img"
									src={ ( media as any ).source_url }
									alt={
										attributes.altOverride ||
										( media as any ).alt_text ||
										''
									}
								/>
							</button>
						) : (
							<Button variant="primary" onClick={ open }>
								{ __( 'Pick image', 'pediment' ) }
							</Button>
						)
					}
				/>
				<RichText
					tagName="figcaption"
					className="starter-image-caption__caption"
					value={ attributes.caption }
					onChange={ ( v ) => setAttributes( { caption: v } ) }
					placeholder={ __( 'Caption (optional)…', 'pediment' ) }
				/>
			</figure>
		</>
	);
}
