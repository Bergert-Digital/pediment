import { __ } from '@wordpress/i18n';
import {
	useBlockProps,
	useInnerBlocksProps,
	MediaUpload,
	MediaPlaceholder,
	InspectorControls,
} from '@wordpress/block-editor';
import { PanelBody, TextControl } from '@wordpress/components';
import { useSelect } from '@wordpress/data';

type Attrs = {
	mediaId: number;
	altOverride: string;
};

const ALLOWED = [
	'core/heading',
	'core/paragraph',
	'core/list',
	'core/list-item',
	'core/separator',
	'core/buttons',
];

const TEMPLATE: [ string, Record< string, unknown > ][] = [
	[ 'core/heading', { level: 2 } ],
	[ 'core/paragraph', { placeholder: 'Start writing…' } ],
];

export default function Edit( {
	attributes,
	setAttributes,
}: {
	attributes: Attrs;
	setAttributes: ( a: Partial< Attrs > ) => void;
} ) {
	const blockProps = useBlockProps( { className: 'starter-slide' } );
	const innerBlocksProps = useInnerBlocksProps(
		{ className: 'starter-slide__panel' },
		{ allowedBlocks: ALLOWED, template: TEMPLATE, templateLock: false }
	);
	const media = useSelect(
		( select: any ) =>
			attributes.mediaId
				? select( 'core' ).getMedia( attributes.mediaId )
				: null,
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
			<div { ...blockProps }>
				<figure className="starter-slide__media">
					{ media ? (
						<MediaUpload
							allowedTypes={ [ 'image' ] }
							value={ attributes.mediaId }
							onSelect={ ( m: any ) =>
								setAttributes( { mediaId: m.id } )
							}
							render={ ( { open }: { open: () => void } ) => (
								<button
									type="button"
									className="starter-slide__replace"
									onClick={ open }
									aria-label={ __(
										'Replace image',
										'pediment'
									) }
								>
									<img
										className="starter-slide__img"
										src={ ( media as any ).source_url }
										alt={
											attributes.altOverride ||
											( media as any ).alt_text ||
											''
										}
									/>
								</button>
							) }
						/>
					) : (
						<MediaPlaceholder
							icon="format-image"
							labels={ { title: __( 'Image', 'pediment' ) } }
							allowedTypes={ [ 'image' ] }
							accept="image/*"
							onSelect={ ( m: any ) =>
								setAttributes( { mediaId: m.id } )
							}
						/>
					) }
				</figure>
				<div { ...innerBlocksProps } />
			</div>
		</>
	);
}
