import { __ } from '@wordpress/i18n';
import {
	useBlockProps,
	RichText,
	InspectorControls,
	MediaUpload,
} from '@wordpress/block-editor';
import { PanelBody, SelectControl, Button } from '@wordpress/components';

type Attrs = {
	variant: 'default' | 'testimonial';
	quote: string;
	citation: string;
	authorName: string;
	authorRole: string;
	avatarId: number;
};

const ALL_VARIANTS = [ 'default', 'testimonial' ] as const;
const LABELS: Record< string, string > = {
	default: 'Default',
	testimonial: 'Testimonial',
};

function allowedVariants(): string[] {
	const w = ( window as unknown as { starterPullQuoteVariants?: unknown } )
		.starterPullQuoteVariants;
	if ( Array.isArray( w ) && w.length ) {
		return w.map( String );
	}
	return [ ...ALL_VARIANTS ];
}

export default function Edit( {
	attributes,
	setAttributes,
}: {
	attributes: Attrs;
	setAttributes: ( a: Partial< Attrs > ) => void;
} ) {
	const blockProps = useBlockProps( {
		className: `starter-pull-quote is-variant-${ attributes.variant }`,
	} );
	const isTestimonial = attributes.variant === 'testimonial';
	const options = allowedVariants().map( ( v ) => ( {
		label: LABELS[ v ] ?? v,
		value: v,
	} ) );

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Pull quote settings', 'starter' ) }>
					<SelectControl
						label={ __( 'Variant', 'starter' ) }
						value={ attributes.variant }
						options={ options }
						onChange={ ( v ) =>
							setAttributes( {
								variant: v as Attrs[ 'variant' ],
							} )
						}
					/>
					{ isTestimonial && (
						<>
							<MediaUpload
								allowedTypes={ [ 'image' ] }
								onSelect={ ( media: any ) =>
									setAttributes( { avatarId: media.id } )
								}
								render={ ( { open }: { open: () => void } ) => (
									<Button
										variant="secondary"
										onClick={ open }
									>
										{ attributes.avatarId
											? __( 'Replace avatar', 'starter' )
											: __( 'Pick avatar', 'starter' ) }
									</Button>
								) }
							/>
						</>
					) }
				</PanelBody>
			</InspectorControls>

			{ isTestimonial ? (
				<figure { ...blockProps }>
					<RichText
						tagName="blockquote"
						className="starter-pull-quote__quote"
						value={ attributes.quote }
						onChange={ ( v ) => setAttributes( { quote: v } ) }
						placeholder={ __( 'Quote…', 'starter' ) }
					/>
					<figcaption className="starter-pull-quote__by">
						<div className="starter-pull-quote__meta">
							<RichText
								tagName="b"
								className="starter-pull-quote__name"
								value={ attributes.authorName }
								onChange={ ( v ) =>
									setAttributes( { authorName: v } )
								}
								placeholder={ __( 'Name…', 'starter' ) }
							/>
							<RichText
								tagName="span"
								className="starter-pull-quote__role"
								value={ attributes.authorRole }
								onChange={ ( v ) =>
									setAttributes( { authorRole: v } )
								}
								placeholder={ __( 'Role…', 'starter' ) }
							/>
						</div>
					</figcaption>
				</figure>
			) : (
				<blockquote { ...blockProps }>
					<RichText
						tagName="p"
						className="starter-pull-quote__quote"
						value={ attributes.quote }
						onChange={ ( v ) => setAttributes( { quote: v } ) }
						placeholder={ __( 'Quote…', 'starter' ) }
					/>
					<RichText
						tagName="cite"
						className="starter-pull-quote__citation"
						value={ attributes.citation }
						onChange={ ( v ) => setAttributes( { citation: v } ) }
						placeholder={ __( 'Citation (optional)…', 'starter' ) }
					/>
				</blockquote>
			) }
		</>
	);
}
