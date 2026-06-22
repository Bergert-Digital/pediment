import { __ } from '@wordpress/i18n';
import {
	useBlockProps,
	InspectorControls,
	PanelColorSettings,
	MediaUpload,
	MediaUploadCheck,
} from '@wordpress/block-editor';
import {
	PanelBody,
	TextControl,
	TextareaControl,
	ToggleControl,
	Button,
} from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { useState } from '@wordpress/element';
import { panelFg } from './panel-fg';

type Slide = {
	mediaId: number;
	altOverride: string;
	eyebrow: string;
	heading: string;
	body: string;
	buttonText: string;
	buttonUrl: string;
};

type Attrs = {
	mediaPosition: string;
	panelColor: string;
	slides: Slide[];
};

const emptySlide = (): Slide => ( {
	mediaId: 0,
	altOverride: '',
	eyebrow: '',
	heading: '',
	body: '',
	buttonText: '',
	buttonUrl: '',
} );

function move< T >( arr: T[], from: number, to: number ): T[] {
	if ( to < 0 || to >= arr.length ) {
		return arr;
	}
	const copy = arr.slice();
	const [ item ] = copy.splice( from, 1 );
	copy.splice( to, 0, item );
	return copy;
}

const has = ( s: string ) => ( s ?? '' ).trim() !== '';

function SlideImage( { slide }: { slide: Slide } ) {
	const media = useSelect(
		( select: any ) =>
			slide.mediaId ? select( 'core' ).getMedia( slide.mediaId ) : null,
		[ slide.mediaId ]
	);
	const url = media ? ( media as any ).source_url : '';
	if ( ! url ) {
		return (
			<span className="starter-slide__placeholder" aria-hidden="true">
				<svg
					viewBox="0 0 24 24"
					fill="none"
					stroke="currentColor"
					strokeWidth="1.5"
					strokeLinecap="round"
					strokeLinejoin="round"
				>
					<rect x="3" y="3" width="18" height="18" rx="2" />
					<circle cx="8.5" cy="8.5" r="1.5" />
					<path d="M21 15l-5-5L5 21" />
				</svg>
			</span>
		);
	}
	return (
		<img
			className="starter-slide__img"
			src={ url }
			alt={ slide.altOverride || ( media as any ).alt_text || '' }
		/>
	);
}

export default function Edit( {
	attributes,
	setAttributes,
}: {
	attributes: Attrs;
	setAttributes: ( a: Partial< Attrs > ) => void;
} ) {
	const position = attributes.mediaPosition === 'right' ? 'right' : 'left';
	const bg = has( attributes.panelColor ) ? attributes.panelColor : '#0A1B33';
	const slides = attributes.slides ?? [];
	const [ active, setActive ] = useState( 0 );
	const [ autoOpen, setAutoOpen ] = useState( -1 );

	const activeIndex = Math.min( active, Math.max( 0, slides.length - 1 ) );

	const commit = ( next: Slide[] ) => setAttributes( { slides: next } );
	const updateSlide = ( i: number, patch: Partial< Slide > ) =>
		commit(
			slides.map( ( s, idx ) => ( idx === i ? { ...s, ...patch } : s ) )
		);

	const blockProps = useBlockProps( {
		className: `starter-slider is-editor is-media-${ position }`,
		style: {
			[ '--slide-panel-bg' as string ]: bg,
			[ '--slide-panel-fg' as string ]: panelFg( bg ),
		},
	} );

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Layout', 'pediment' ) }>
					<ToggleControl
						label={ __( 'Image on the left', 'pediment' ) }
						checked={ position === 'left' }
						onChange={ ( v ) =>
							setAttributes( {
								mediaPosition: v ? 'left' : 'right',
							} )
						}
						help={ __(
							'Off places the image on the right of every slide.',
							'pediment'
						) }
					/>
				</PanelBody>
			</InspectorControls>
			<InspectorControls group="color">
				<PanelColorSettings
					title={ __( 'Panel', 'pediment' ) }
					colorSettings={ [
						{
							value: attributes.panelColor,
							onChange: ( c?: string ) =>
								setAttributes( { panelColor: c || '#0A1B33' } ),
							label: __( 'Panel background', 'pediment' ),
						},
					] }
				/>
			</InspectorControls>
			<InspectorControls>
				{ slides.map( ( slide, i ) => (
					<PanelBody
						key={ i }
						title={ `${ __( 'Slide', 'pediment' ) } ${ i + 1 }` }
						initialOpen={ i === autoOpen }
						onToggle={ ( open: boolean ) => {
							if ( open ) {
								setActive( i );
							}
						} }
					>
						<MediaUploadCheck>
							<MediaUpload
								allowedTypes={ [ 'image' ] }
								value={ slide.mediaId }
								onSelect={ ( m: any ) =>
									updateSlide( i, { mediaId: m.id } )
								}
								render={ ( { open }: { open: () => void } ) => (
									<div className="starter-slider-form__media">
										<Button
											variant="secondary"
											onClick={ open }
										>
											{ slide.mediaId
												? __(
														'Replace image',
														'pediment'
												  )
												: __(
														'Select image',
														'pediment'
												  ) }
										</Button>
										{ slide.mediaId ? (
											<Button
												variant="link"
												isDestructive
												onClick={ () =>
													updateSlide( i, {
														mediaId: 0,
													} )
												}
											>
												{ __(
													'Remove image',
													'pediment'
												) }
											</Button>
										) : null }
									</div>
								) }
							/>
						</MediaUploadCheck>
						<TextControl
							label={ __( 'Alt text override', 'pediment' ) }
							value={ slide.altOverride }
							onChange={ ( v ) =>
								updateSlide( i, { altOverride: v } )
							}
						/>
						<TextControl
							label={ __( 'Eyebrow', 'pediment' ) }
							value={ slide.eyebrow }
							onChange={ ( v ) =>
								updateSlide( i, { eyebrow: v } )
							}
						/>
						<TextControl
							label={ __( 'Heading', 'pediment' ) }
							value={ slide.heading }
							onChange={ ( v ) =>
								updateSlide( i, { heading: v } )
							}
						/>
						<TextareaControl
							label={ __( 'Body', 'pediment' ) }
							value={ slide.body }
							onChange={ ( v ) => updateSlide( i, { body: v } ) }
						/>
						<TextControl
							label={ __( 'Button text', 'pediment' ) }
							value={ slide.buttonText }
							onChange={ ( v ) =>
								updateSlide( i, { buttonText: v } )
							}
						/>
						<TextControl
							label={ __( 'Button URL', 'pediment' ) }
							type="url"
							value={ slide.buttonUrl }
							onChange={ ( v ) =>
								updateSlide( i, { buttonUrl: v } )
							}
						/>
						<div className="starter-slider-form__toolbar">
							<Button
								size="small"
								variant="secondary"
								aria-label={ __( 'Move slide up', 'pediment' ) }
								disabled={ i === 0 }
								onClick={ () =>
									commit( move( slides, i, i - 1 ) )
								}
							>
								↑
							</Button>
							<Button
								size="small"
								variant="secondary"
								aria-label={ __(
									'Move slide down',
									'pediment'
								) }
								disabled={ i === slides.length - 1 }
								onClick={ () =>
									commit( move( slides, i, i + 1 ) )
								}
							>
								↓
							</Button>
							<Button
								size="small"
								isDestructive
								variant="tertiary"
								onClick={ () => {
									commit(
										slides.filter( ( _, idx ) => idx !== i )
									);
									setActive( Math.max( 0, i - 1 ) );
								} }
							>
								{ __( 'Remove slide', 'pediment' ) }
							</Button>
						</div>
					</PanelBody>
				) ) }
				<PanelBody title={ __( 'Slides', 'pediment' ) }>
					<Button
						variant="primary"
						onClick={ () => {
							const next = [ ...slides, emptySlide() ];
							setAutoOpen( next.length - 1 );
							setActive( next.length - 1 );
							commit( next );
						} }
					>
						{ __( 'Add slide', 'pediment' ) }
					</Button>
				</PanelBody>
			</InspectorControls>

			<div { ...blockProps }>
				{ slides.length === 0 ? (
					<div className="starter-slider__empty">
						{ __(
							'Add your first slide from the block settings sidebar.',
							'pediment'
						) }
					</div>
				) : (
					<>
						<div className="starter-slider__track">
							<div
								className="starter-slider__rail"
								style={ {
									transform: `translateX(${
										-activeIndex * 100
									}%)`,
								} }
							>
								{ slides.map( ( slide, i ) => (
									<div
										key={ i }
										className={
											i === activeIndex
												? 'starter-slide is-active'
												: 'starter-slide'
										}
									>
										<figure className="starter-slide__media">
											<SlideImage slide={ slide } />
										</figure>
										<div className="starter-slide__panel">
											{ has( slide.eyebrow ) && (
												<p className="starter-slide__eyebrow">
													{ slide.eyebrow }
												</p>
											) }
											{ has( slide.heading ) && (
												<h2 className="starter-slide__heading">
													{ slide.heading }
												</h2>
											) }
											{ has( slide.body ) && (
												<p className="starter-slide__body">
													{ slide.body }
												</p>
											) }
											{ has( slide.buttonText ) &&
												has( slide.buttonUrl ) && (
													<span className="starter-slide__button">
														{ slide.buttonText }
													</span>
												) }
										</div>
									</div>
								) ) }
							</div>
						</div>
						{ slides.length > 1 && (
							<>
								<button
									type="button"
									className="starter-slider__arrow starter-slider__arrow--prev"
									aria-label={ __(
										'Previous slide',
										'pediment'
									) }
									onClick={ () =>
										setActive(
											( activeIndex -
												1 +
												slides.length ) %
												slides.length
										)
									}
								>
									<span aria-hidden="true">&lsaquo;</span>
								</button>
								<button
									type="button"
									className="starter-slider__arrow starter-slider__arrow--next"
									aria-label={ __(
										'Next slide',
										'pediment'
									) }
									onClick={ () =>
										setActive(
											( activeIndex + 1 ) % slides.length
										)
									}
								>
									<span aria-hidden="true">&rsaquo;</span>
								</button>
								<div
									className="starter-slider__pagination"
									role="group"
									aria-label={ __( 'Slides', 'pediment' ) }
								>
									{ slides.map( ( _, i ) => (
										<button
											key={ i }
											type="button"
											className={
												i === activeIndex
													? 'starter-slider__dot is-current'
													: 'starter-slider__dot'
											}
											aria-label={ `${ __(
												'Go to slide',
												'pediment'
											) } ${ i + 1 }` }
											onClick={ () => setActive( i ) }
										/>
									) ) }
								</div>
							</>
						) }
						<p className="starter-slider__count">
							{ `${ __( 'Slide', 'pediment' ) } ${
								activeIndex + 1
							} / ${ slides.length }` }
						</p>
					</>
				) }
			</div>
		</>
	);
}
