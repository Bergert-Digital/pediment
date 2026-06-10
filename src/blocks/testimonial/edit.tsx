import { __ } from '@wordpress/i18n';
import {
	useBlockProps,
	RichText,
	InspectorControls,
	MediaUpload,
} from '@wordpress/block-editor';
import { PanelBody, Button } from '@wordpress/components';

type Attrs = {
	quote: string;
	authorName: string;
	authorRole: string;
	avatarId: number;
};

export default function Edit( {
	attributes,
	setAttributes,
}: {
	attributes: Attrs;
	setAttributes: ( a: Partial< Attrs > ) => void;
} ) {
	const blockProps = useBlockProps( { className: 'starter-testimonial' } );
	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Testimonial', 'pediment' ) }>
					<MediaUpload
						allowedTypes={ [ 'image' ] }
						onSelect={ ( media: any ) =>
							setAttributes( { avatarId: media.id } )
						}
						render={ ( { open }: { open: () => void } ) => (
							<Button variant="secondary" onClick={ open }>
								{ attributes.avatarId
									? __( 'Replace avatar', 'pediment' )
									: __(
											'Pick avatar (optional)',
											'pediment'
									  ) }
							</Button>
						) }
					/>
				</PanelBody>
			</InspectorControls>
			<figure { ...blockProps }>
				<span className="starter-testimonial__mark" aria-hidden="true">
					&ldquo;
				</span>
				<RichText
					tagName="blockquote"
					className="starter-testimonial__quote"
					value={ attributes.quote }
					onChange={ ( v ) => setAttributes( { quote: v } ) }
					placeholder={ __( 'Quote…', 'pediment' ) }
				/>
				<figcaption className="starter-testimonial__by">
					<div className="starter-testimonial__meta">
						<RichText
							tagName="b"
							className="starter-testimonial__name"
							value={ attributes.authorName }
							onChange={ ( v ) =>
								setAttributes( { authorName: v } )
							}
							placeholder={ __( 'Name…', 'pediment' ) }
						/>
						<RichText
							tagName="span"
							className="starter-testimonial__role"
							value={ attributes.authorRole }
							onChange={ ( v ) =>
								setAttributes( { authorRole: v } )
							}
							placeholder={ __( 'Role, Company…', 'pediment' ) }
						/>
					</div>
				</figcaption>
			</figure>
		</>
	);
}
