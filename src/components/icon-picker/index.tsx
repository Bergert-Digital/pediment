import { __, sprintf } from '@wordpress/i18n';
import {
	Button,
	Dropdown,
	SearchControl,
	Spinner,
	Notice,
} from '@wordpress/components';
import { useState, useEffect, useMemo } from '@wordpress/element';
import { filterIcons } from './filter';
import { getCatalog, type Catalog } from './catalog';

// Cap how many icons render with no active search, so opening the popover does
// not mount ~1,500 DOM nodes at once. A search narrows below this instantly.
const NO_QUERY_LIMIT = 150;

function IconGlyph( { markup }: { markup: string } ) {
	return (
		<svg
			viewBox="0 0 256 256"
			width={ 24 }
			height={ 24 }
			aria-hidden="true"
			focusable="false"
			dangerouslySetInnerHTML={ { __html: markup } }
		/>
	);
}

export default function IconPicker( {
	value,
	onChange,
	label = __( 'Icon', 'pediment' ),
}: {
	value: string;
	onChange: ( slug: string ) => void;
	label?: string;
} ) {
	const [ catalog, setCatalog ] = useState< Catalog | null >( null );
	const [ error, setError ] = useState< string | null >( null );
	const [ query, setQuery ] = useState( '' );

	useEffect( () => {
		let active = true;
		if ( ! catalog ) {
			getCatalog()
				.then( ( data ) => active && setCatalog( data ) )
				.catch( ( err: Error ) => active && setError( err.message ) );
		}
		return () => {
			active = false;
		};
	}, [ catalog ] );

	const allSlugs = useMemo(
		() => ( catalog ? Object.keys( catalog ) : [] ),
		[ catalog ]
	);
	const matches = useMemo(
		() => filterIcons( allSlugs, query ),
		[ allSlugs, query ]
	);
	const truncated = ! query.trim() && matches.length > NO_QUERY_LIMIT;
	const visible = truncated ? matches.slice( 0, NO_QUERY_LIMIT ) : matches;

	const currentMarkup = catalog && value ? catalog[ value ] : undefined;

	return (
		<div className="pediment-icon-picker">
			<span className="pediment-icon-picker__label">{ label }</span>
			<Dropdown
				className="pediment-icon-picker__dropdown"
				contentClassName="pediment-icon-picker__popover"
				popoverProps={ { placement: 'bottom-start' } }
				renderToggle={ ( {
					isOpen,
					onToggle,
				}: {
					isOpen: boolean;
					onToggle: () => void;
				} ) => (
					<Button
						variant="secondary"
						onClick={ onToggle }
						aria-expanded={ isOpen }
						className="pediment-icon-picker__toggle"
					>
						{ currentMarkup ? (
							<IconGlyph markup={ currentMarkup } />
						) : null }
						<span>{ value || __( 'Choose…', 'pediment' ) }</span>
					</Button>
				) }
				renderContent={ () => (
					<div className="pediment-icon-picker__content">
						{ error && (
							<Notice status="error" isDismissible={ false }>
								{ error }
							</Notice>
						) }
						{ ! catalog && ! error && <Spinner /> }
						{ catalog && (
							<>
								<SearchControl
									value={ query }
									onChange={ setQuery }
									placeholder={ __(
										'Search icons…',
										'pediment'
									) }
									__nextHasNoMarginBottom
								/>
								<div
									className="pediment-icon-picker__grid"
									role="listbox"
									aria-label={ __( 'Icons', 'pediment' ) }
								>
									{ visible.map( ( slug ) => (
										<Button
											key={ slug }
											className={
												slug === value
													? 'pediment-icon-picker__cell is-selected'
													: 'pediment-icon-picker__cell'
											}
											aria-label={ slug }
											aria-selected={ slug === value }
											role="option"
											onClick={ () => onChange( slug ) }
										>
											<IconGlyph
												markup={ catalog[ slug ] }
											/>
										</Button>
									) ) }
								</div>
								{ matches.length === 0 && (
									<p className="pediment-icon-picker__empty">
										{ __( 'No icons match.', 'pediment' ) }
									</p>
								) }
								{ truncated && (
									<p className="pediment-icon-picker__hint">
										{ sprintf(
											/* translators: %d: number of icons shown. */
											__(
												'Showing first %d. Search to narrow.',
												'pediment'
											),
											NO_QUERY_LIMIT
										) }
									</p>
								) }
							</>
						) }
					</div>
				) }
			/>
		</div>
	);
}
