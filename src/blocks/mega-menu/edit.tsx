import { __ } from '@wordpress/i18n';
import {
	useBlockProps,
	InspectorControls,
	useBlockEditingMode,
} from '@wordpress/block-editor';
import { useSelect } from '@wordpress/data';
import { useState } from '@wordpress/element';
import { store as editorStore } from '@wordpress/editor';
import {
	PanelBody,
	TextControl,
	ToggleControl,
	Button,
} from '@wordpress/components';

type Link = { label: string; url: string; description: string };
type Column = { heading: string; icon: string; links: Link[] };
type Attrs = { label: string; columns: Column[] };

const emptyLink = (): Link => ( {
	label: '',
	url: '',
	description: '',
} );
const emptyColumn = (): Column => ( {
	heading: '',
	icon: '',
	links: [ emptyLink() ],
} );

const iconSlug = ( raw: string | undefined ): string =>
	( raw ?? '' ).toLowerCase().replace( /[^a-z0-9-]/g, '' );

function move< T >( arr: T[], from: number, to: number ): T[] {
	if ( to < 0 || to >= arr.length ) {
		return arr;
	}
	const copy = arr.slice();
	const [ item ] = copy.splice( from, 1 );
	copy.splice( to, 0, item );
	return copy;
}

const has = ( s: string ) => s.trim() !== '';
const renderable = ( l: Link ) => has( l.label ) || has( l.url );

export default function Edit( {
	attributes,
	setAttributes,
}: {
	attributes: Attrs;
	setAttributes: ( a: Partial< Attrs > ) => void;
} ) {
	// Editing is only allowed when the wp_navigation entity is the open
	// document (Site Editor → Navigation). Everywhere else (page editor
	// preview, template editor) useBlockEditingMode('disabled') neutralizes
	// the block — no toolbar, no Inspector, no setAttributes path. This is
	// the destroy-on-attr-change escape hatch: uncontrolled core/navigation
	// children get re-instantiated when mutated, which collapses the form
	// mid-edit. Disabled mode prevents the mutation from ever firing.
	const isEntityContext = useSelect(
		( select ) =>
			(
				select( editorStore ) as { getCurrentPostType?: () => string }
			 )?.getCurrentPostType?.() === 'wp_navigation',
		[]
	);
	useBlockEditingMode( isEntityContext ? 'default' : 'disabled' );

	// Editor-only preview state: render the panel open by default so the
	// columns are visible without hovering the trigger. Not a block attr —
	// front-end behaviour (interactivity runtime hover/focus/click) is
	// unchanged.
	const [ previewPanelOpen, setPreviewPanelOpen ] = useState( true );

	// Tracks the index of the most-recently-added column so its PanelBody
	// mounts opened. initialOpen is only evaluated on first mount, so
	// existing columns keep their toggled state when a new one is added.
	const [ autoOpenIndex, setAutoOpenIndex ] = useState( -1 );

	const blockProps = useBlockProps( { className: 'starter-mega-menu' } );
	const columns = attributes.columns ?? [];
	const commit = ( next: Column[] ) => setAttributes( { columns: next } );
	const updateColumn = ( ci: number, patch: Partial< Column > ) =>
		commit(
			columns.map( ( c, i ) => ( i === ci ? { ...c, ...patch } : c ) )
		);
	const updateLink = ( ci: number, li: number, patch: Partial< Link > ) =>
		updateColumn( ci, {
			links: columns[ ci ].links.map( ( l, i ) =>
				i === li ? { ...l, ...patch } : l
			),
		} );

	// Mirrors render.php: the panel renders only when at least one link has
	// a label or url. The static preview deliberately reproduces render.php's
	// DOM/classes (no ServerSideRender) so it updates synchronously in place
	// — a core/navigation child that re-renders asynchronously gets
	// deselected, which would collapse this sidebar form mid-edit.
	const previewColumns = columns
		.map( ( c ) => ( {
			heading: c.heading,
			icon: c.icon,
			links: ( c.links ?? [] ).filter( renderable ),
		} ) )
		.filter( ( c ) => c.links.length > 0 );
	const hasPanel = previewColumns.length > 0;

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Mega Menu', 'starter' ) }>
					<TextControl
						label={ __( 'Menu label', 'starter' ) }
						value={ attributes.label }
						onChange={ ( label ) => setAttributes( { label } ) }
					/>
					<ToggleControl
						label={ __( 'Show panel preview', 'starter' ) }
						help={ __(
							'Renders the panel open in the editor canvas. Front-end hover/focus/click behaviour is unchanged.',
							'starter'
						) }
						checked={ previewPanelOpen }
						onChange={ setPreviewPanelOpen }
					/>
				</PanelBody>
				{ columns.map( ( column, ci ) => (
					<PanelBody
						key={ ci }
						title={ `${ __( 'Column', 'starter' ) } ${ ci + 1 }` }
						initialOpen={ ci === autoOpenIndex }
					>
						<div className="starter-mega-form__section">
							<p className="starter-mega-form__section-label">
								{ __( 'Header', 'starter' ) }
							</p>
							<TextControl
								label={ __( 'Heading', 'starter' ) }
								value={ column.heading }
								onChange={ ( heading ) =>
									updateColumn( ci, { heading } )
								}
							/>
							<TextControl
								label={ __(
									'Icon (Phosphor name)',
									'starter'
								) }
								help={ __(
									'Available: arrow-right, article, bank, caret-down, check-circle, gear, microphone, monitor-play, seal-check, stack, trend-up. Add more in assets/icons/phosphor-sprite.svg.',
									'starter'
								) }
								value={ column.icon ?? '' }
								onChange={ ( icon ) =>
									updateColumn( ci, { icon } )
								}
							/>
						</div>

						<hr className="starter-mega-form__divider" />

						<div className="starter-mega-form__section">
							<p className="starter-mega-form__section-label">
								{ __( 'Links', 'starter' ) }
							</p>
							{ column.links.map( ( link, li ) => (
								<div
									key={ li }
									className="starter-mega-form__link"
								>
									<TextControl
										label={ __( 'Label', 'starter' ) }
										value={ link.label }
										onChange={ ( v ) =>
											updateLink( ci, li, {
												label: v,
											} )
										}
									/>
									<TextControl
										label={ __( 'URL', 'starter' ) }
										type="url"
										value={ link.url }
										onChange={ ( v ) =>
											updateLink( ci, li, {
												url: v,
											} )
										}
									/>
									<TextControl
										label={ __( 'Description', 'starter' ) }
										value={ link.description }
										onChange={ ( v ) =>
											updateLink( ci, li, {
												description: v,
											} )
										}
									/>
									<div className="starter-mega-form__toolbar">
										<Button
											size="small"
											variant="tertiary"
											aria-label={ __(
												'Move link up',
												'starter'
											) }
											onClick={ () =>
												updateColumn( ci, {
													links: move(
														column.links,
														li,
														li - 1
													),
												} )
											}
											disabled={ li === 0 }
										>
											↑
										</Button>
										<Button
											size="small"
											variant="tertiary"
											aria-label={ __(
												'Move link down',
												'starter'
											) }
											onClick={ () =>
												updateColumn( ci, {
													links: move(
														column.links,
														li,
														li + 1
													),
												} )
											}
											disabled={
												li === column.links.length - 1
											}
										>
											↓
										</Button>
										<Button
											size="small"
											isDestructive
											variant="tertiary"
											onClick={ () =>
												updateColumn( ci, {
													links: column.links.filter(
														( _, i ) => i !== li
													),
												} )
											}
										>
											{ __( 'Remove', 'starter' ) }
										</Button>
									</div>
								</div>
							) ) }
							<Button
								variant="secondary"
								className="starter-mega-form__add"
								onClick={ () =>
									updateColumn( ci, {
										links: [ ...column.links, emptyLink() ],
									} )
								}
							>
								{ __( 'Add link', 'starter' ) }
							</Button>
						</div>

						<hr className="starter-mega-form__divider" />

						<div className="starter-mega-form__toolbar starter-mega-form__toolbar--column">
							<Button
								size="small"
								variant="secondary"
								aria-label={ __( 'Move column up', 'starter' ) }
								onClick={ () =>
									commit( move( columns, ci, ci - 1 ) )
								}
								disabled={ ci === 0 }
							>
								↑
							</Button>
							<Button
								size="small"
								variant="secondary"
								aria-label={ __(
									'Move column down',
									'starter'
								) }
								onClick={ () =>
									commit( move( columns, ci, ci + 1 ) )
								}
								disabled={ ci === columns.length - 1 }
							>
								↓
							</Button>
							<Button
								size="small"
								isDestructive
								variant="tertiary"
								onClick={ () =>
									commit(
										columns.filter( ( _, i ) => i !== ci )
									)
								}
							>
								{ __( 'Remove column', 'starter' ) }
							</Button>
						</div>
					</PanelBody>
				) ) }
				<PanelBody title={ __( 'Columns', 'starter' ) }>
					<Button
						variant="primary"
						onClick={ () => {
							setAutoOpenIndex( columns.length );
							commit( [ ...columns, emptyColumn() ] );
						} }
					>
						{ __( 'Add column', 'starter' ) }
					</Button>
				</PanelBody>
			</InspectorControls>
			<div { ...blockProps }>
				<button type="button" className="starter-mega-menu__trigger">
					{ has( attributes.label )
						? attributes.label
						: __( 'Menu', 'starter' ) }
				</button>
				{ hasPanel && (
					<div
						className={
							previewPanelOpen
								? 'starter-mega-menu__panel is-preview-open'
								: 'starter-mega-menu__panel'
						}
						hidden={ ! previewPanelOpen }
					>
						{ previewColumns.map( ( column, ci ) => {
							const colIcon = iconSlug( column.icon );
							const hasIcon = has( colIcon );
							const hasHeading = has( column.heading );
							return (
								<div key={ ci } className="starter-mega-column">
									{ ( hasHeading || hasIcon ) && (
										<p className="starter-mega-column__heading">
											{ hasIcon && (
												<svg
													className="i starter-mega-column__icon"
													width="24"
													height="24"
													viewBox="0 0 256 256"
													aria-hidden="true"
													focusable="false"
												>
													<use
														href={ `#ph-${ colIcon }` }
													/>
												</svg>
											) }
											{ column.heading }
										</p>
									) }
									<div className="starter-mega-column__links">
										{ column.links.map( ( link, li ) => (
											<a
												key={ li }
												className="starter-mega-link"
												href={ link.url }
											>
												<span className="starter-mega-link__label">
													{ link.label }
												</span>
												{ has( link.description ) && (
													<span className="starter-mega-link__desc">
														{ link.description }
													</span>
												) }
											</a>
										) ) }
									</div>
								</div>
							);
						} ) }
					</div>
				) }
			</div>
		</>
	);
}
