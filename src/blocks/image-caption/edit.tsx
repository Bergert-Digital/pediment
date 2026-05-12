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
				<PanelBody title={ __( 'Image', 'starter' ) }>
					<TextControl
						label={ __( 'Alt text override', 'starter' ) }
						value={ attributes.altOverride }
						onChange={ ( v ) =>
							setAttributes( { altOverride: v } )
						}
						help={ __(
							'Leave empty to use the media library alt text.',
							'starter'
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
								aria-label={ __( 'Replace image', 'starter' ) }
							>
								<img
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
								{ __( 'Pick image', 'starter' ) }
							</Button>
						)
					}
				/>
				<RichText
					tagName="figcaption"
					value={ attributes.caption }
					onChange={ ( v ) => setAttributes( { caption: v } ) }
					placeholder={ __( 'Caption (optional)…', 'starter' ) }
				/>
			</figure>
		</>
	);
}
