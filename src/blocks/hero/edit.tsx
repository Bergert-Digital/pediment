import { __ } from '@wordpress/i18n';
import {
	useBlockProps,
	RichText,
	InspectorControls,
	MediaUpload,
} from '@wordpress/block-editor';
import {
	PanelBody,
	SelectControl,
	TextControl,
	TextareaControl,
	Button,
} from '@wordpress/components';
import { useSelect } from '@wordpress/data';

type Metric = { value: string; label: string };
type Attrs = {
	variant: 'default' | 'centered' | 'media-bg' | 'stat-card';
	headline: string;
	subheadline: string;
	ctaText: string;
	ctaUrl: string;
	secondaryText: string;
	secondaryUrl: string;
	eyebrow: string;
	ticks: string[];
	statValue: string;
	statText: string;
	metrics: Metric[];
	mediaId: number;
};

const ALL_VARIANTS = [
	'default',
	'centered',
	'media-bg',
	'stat-card',
] as const;
const LABELS: Record< string, string > = {
	default: 'Default',
	centered: 'Centered',
	'media-bg': 'Media BG',
	'stat-card': 'Stat card',
};

function allowedVariants(): string[] {
	const w = ( window as unknown as { starterHeroVariants?: unknown } )
		.starterHeroVariants;
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
		className: `starter-hero is-variant-${ attributes.variant }`,
	} );
	const isStatCard = attributes.variant === 'stat-card';
	const mediaUrl = useSelect(
		( select ) => {
			if ( ! attributes.mediaId ) {
				return '';
			}
			const media = ( select( 'core' ) as any ).getMedia?.( attributes.mediaId );
			const sizes = media?.media_details?.sizes;
			return (
				sizes?.large?.source_url ||
				sizes?.medium_large?.source_url ||
				sizes?.full?.source_url ||
				media?.source_url ||
				''
			);
		},
		[ attributes.mediaId ]
	);
	const hasGlassContent =
		!! attributes.statValue ||
		!! attributes.statText ||
		( Array.isArray( attributes.metrics ) && attributes.metrics.length > 0 );
	const options = allowedVariants().map( ( v ) => ( {
		label: LABELS[ v ] ?? v,
		value: v,
	} ) );

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Hero settings', 'starter' ) }>
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
					<TextControl
						label={ __( 'CTA URL', 'starter' ) }
						value={ attributes.ctaUrl }
						onChange={ ( v ) => setAttributes( { ctaUrl: v } ) }
					/>
					{ isStatCard && (
						<>
							<TextControl
								label={ __( 'Eyebrow', 'starter' ) }
								value={ attributes.eyebrow }
								onChange={ ( v ) =>
									setAttributes( { eyebrow: v } )
								}
							/>
							<TextControl
								label={ __( 'Secondary CTA text', 'starter' ) }
								value={ attributes.secondaryText }
								onChange={ ( v ) =>
									setAttributes( { secondaryText: v } )
								}
							/>
							<TextControl
								label={ __( 'Secondary CTA URL', 'starter' ) }
								value={ attributes.secondaryUrl }
								onChange={ ( v ) =>
									setAttributes( { secondaryUrl: v } )
								}
							/>
							<TextareaControl
								label={ __(
									'Trust ticks (one per line)',
									'starter'
								) }
								value={ ( attributes.ticks || [] ).join(
									'\n'
								) }
								onChange={ ( v ) =>
									setAttributes( {
										ticks: v
											.split( '\n' )
											.map( ( s ) => s.trim() )
											.filter( Boolean ),
									} )
								}
							/>
							<TextControl
								label={ __( 'Stat value', 'starter' ) }
								value={ attributes.statValue }
								onChange={ ( v ) =>
									setAttributes( { statValue: v } )
								}
							/>
							<TextControl
								label={ __( 'Stat text', 'starter' ) }
								value={ attributes.statText }
								onChange={ ( v ) =>
									setAttributes( { statText: v } )
								}
							/>
							<TextareaControl
								label={ __(
									'Metrics — “value | label” per line',
									'starter'
								) }
								value={ ( attributes.metrics || [] )
									.map(
										( m ) => `${ m.value } | ${ m.label }`
									)
									.join( '\n' ) }
								onChange={ ( v ) =>
									setAttributes( {
										metrics: v
											.split( '\n' )
											.map( ( line ) => {
												const [ value, label ] =
													line.split( '|' );
												return {
													value: ( value || '' ).trim(),
													label: ( label || '' ).trim(),
												};
											} )
											.filter(
												( m ) =>
													m.value !== '' ||
													m.label !== ''
											),
									} )
								}
							/>
						</>
					) }
					{ ( attributes.variant === 'media-bg' ||
						isStatCard ) && (
						<MediaUpload
							allowedTypes={ [ 'image' ] }
							onSelect={ ( media: any ) =>
								setAttributes( { mediaId: media.id } )
							}
							render={ ( { open }: { open: () => void } ) => (
								<Button variant="secondary" onClick={ open }>
									{ attributes.mediaId
										? __( 'Replace image', 'starter' )
										: __( 'Pick image', 'starter' ) }
								</Button>
							) }
						/>
					) }
				</PanelBody>
			</InspectorControls>

			<div { ...blockProps }>
				<div className="starter-hero__col">
					{ isStatCard && (
						<RichText
							tagName="span"
							className="starter-hero__eyebrow"
							value={ attributes.eyebrow }
							onChange={ ( v ) =>
								setAttributes( { eyebrow: v } )
							}
							placeholder={ __( 'Eyebrow…', 'starter' ) }
						/>
					) }
					<RichText
						tagName="h1"
						className="starter-hero__headline"
						value={ attributes.headline }
						onChange={ ( v ) => setAttributes( { headline: v } ) }
						placeholder={ __( 'Headline…', 'starter' ) }
					/>
					<RichText
						tagName="p"
						className="starter-hero__subheadline"
						value={ attributes.subheadline }
						onChange={ ( v ) =>
							setAttributes( { subheadline: v } )
						}
						placeholder={ __( 'Subheadline…', 'starter' ) }
					/>
					<RichText
						tagName="span"
						className="starter-hero__cta"
						value={ attributes.ctaText }
						onChange={ ( v ) => setAttributes( { ctaText: v } ) }
						placeholder={ __( 'CTA text…', 'starter' ) }
					/>
				</div>
				{ isStatCard && (
					<figure className="starter-hero__fig" aria-hidden="true">
						{ mediaUrl && (
							<img
								className="starter-hero__img"
								src={ mediaUrl }
								alt=""
							/>
						) }
						{ hasGlassContent && (
							<div className="starter-hero__glass">
								{ attributes.statValue && (
									<div className="starter-hero__stat-value">
										{ attributes.statValue }
									</div>
								) }
								{ attributes.statText && (
									<div className="starter-hero__stat-text">
										{ attributes.statText }
									</div>
								) }
								{ Array.isArray( attributes.metrics ) &&
									attributes.metrics.length > 0 && (
										<div className="starter-hero__metrics">
											{ attributes.metrics.map( ( m, i ) => (
												<div
													key={ i }
													className="starter-hero__metric"
												>
													<b>{ m.value }</b>
													<span>{ m.label }</span>
												</div>
											) ) }
										</div>
									) }
							</div>
						) }
					</figure>
				) }
			</div>
		</>
	);
}
