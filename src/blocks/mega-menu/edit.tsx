import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, TextControl, Button } from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';

type Link = { label: string; url: string; description: string; icon: string };
type Column = { heading: string; links: Link[] };
type Attrs = { label: string; columns: Column[] };

const emptyLink = (): Link => ( {
	label: '',
	url: '',
	description: '',
	icon: '',
} );
const emptyColumn = (): Column => ( { heading: '', links: [ emptyLink() ] } );

function move< T >( arr: T[], from: number, to: number ): T[] {
	if ( to < 0 || to >= arr.length ) {
		return arr;
	}
	const copy = arr.slice();
	const [ item ] = copy.splice( from, 1 );
	copy.splice( to, 0, item );
	return copy;
}

export default function Edit( {
	attributes,
	setAttributes,
}: {
	attributes: Attrs;
	setAttributes: ( a: Partial< Attrs > ) => void;
} ) {
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

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Mega Menu', 'starter' ) }>
					<TextControl
						label={ __( 'Menu label', 'starter' ) }
						value={ attributes.label }
						onChange={ ( label ) => setAttributes( { label } ) }
					/>
				</PanelBody>
				{ columns.map( ( column, ci ) => (
					<PanelBody
						key={ ci }
						title={ `${ __( 'Column', 'starter' ) } ${ ci + 1 }` }
						initialOpen={ false }
					>
						<TextControl
							label={ __( 'Heading', 'starter' ) }
							value={ column.heading }
							onChange={ ( heading ) =>
								updateColumn( ci, { heading } )
							}
						/>
						{ column.links.map( ( link, li ) => (
							<div key={ li } className="starter-mega-form__link">
								<TextControl
									label={ __( 'Label', 'starter' ) }
									value={ link.label }
									onChange={ ( v ) =>
										updateLink( ci, li, { label: v } )
									}
								/>
								<TextControl
									label={ __( 'URL', 'starter' ) }
									type="url"
									value={ link.url }
									onChange={ ( v ) =>
										updateLink( ci, li, { url: v } )
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
								<TextControl
									label={ __(
										'Icon (Phosphor name)',
										'starter'
									) }
									help={ __(
										'e.g. gear, bank, article',
										'starter'
									) }
									value={ link.icon }
									onChange={ ( v ) =>
										updateLink( ci, li, { icon: v } )
									}
								/>
								<div className="starter-mega-form__row">
									<Button
										variant="tertiary"
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
										{ __( 'Up', 'starter' ) }
									</Button>
									<Button
										variant="tertiary"
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
										{ __( 'Down', 'starter' ) }
									</Button>
									<Button
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
										{ __( 'Remove link', 'starter' ) }
									</Button>
								</div>
							</div>
						) ) }
						<Button
							variant="secondary"
							onClick={ () =>
								updateColumn( ci, {
									links: [ ...column.links, emptyLink() ],
								} )
							}
						>
							{ __( 'Add link', 'starter' ) }
						</Button>
						<div className="starter-mega-form__row">
							<Button
								variant="tertiary"
								onClick={ () =>
									commit( move( columns, ci, ci - 1 ) )
								}
								disabled={ ci === 0 }
							>
								{ __( 'Move column up', 'starter' ) }
							</Button>
							<Button
								variant="tertiary"
								onClick={ () =>
									commit( move( columns, ci, ci + 1 ) )
								}
								disabled={ ci === columns.length - 1 }
							>
								{ __( 'Move column down', 'starter' ) }
							</Button>
							<Button
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
						onClick={ () =>
							commit( [ ...columns, emptyColumn() ] )
						}
					>
						{ __( 'Add column', 'starter' ) }
					</Button>
				</PanelBody>
			</InspectorControls>
			<div { ...blockProps }>
				<ServerSideRender
					block="starter/mega-menu"
					attributes={ attributes }
				/>
			</div>
		</>
	);
}
