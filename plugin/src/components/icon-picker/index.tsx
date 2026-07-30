import { __, _n, sprintf } from '@wordpress/i18n';
import {
	Button,
	Dropdown,
	SearchControl,
	SelectControl,
	Spinner,
	Notice,
} from '@wordpress/components';
import { useState, useEffect, useMemo, useRef } from '@wordpress/element';
import { filterIcons } from './filter';
import { categoriesFromMeta, categoryLabel } from './categories';
import { getCatalog, type IconData, type IconSet } from './catalog';

// How many icons to add to the grid each time the scroll sentinel appears.
const CHUNK = 120;

function IconGlyph( { markup, set }: { markup: string; set: IconSet } ) {
	return (
		<svg
			viewBox={ set.viewBox }
			width={ 24 }
			height={ 24 }
			aria-hidden="true"
			focusable="false"
			{ ...set.svgAttrs }
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
	const [ catalog, setCatalog ] = useState< IconData | null >( null );
	const [ error, setError ] = useState< string | null >( null );
	const [ query, setQuery ] = useState( '' );
	const [ category, setCategory ] = useState( '' );
	const [ visibleCount, setVisibleCount ] = useState( CHUNK );

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
		() => ( catalog ? Object.keys( catalog.markup ) : [] ),
		[ catalog ]
	);
	const categories = useMemo(
		() => categoriesFromMeta( catalog?.meta ?? null ),
		[ catalog ]
	);
	const matches = useMemo(
		() => filterIcons( allSlugs, query, category, catalog?.meta ?? null ),
		[ allSlugs, query, category, catalog ]
	);

	// Reset the progressive window whenever the filter changes.
	useEffect( () => {
		setVisibleCount( CHUNK );
	}, [ query, category ] );

	const visible = matches.slice( 0, visibleCount );
	const hasMore = visibleCount < matches.length;

	const sentinelRef = useRef< HTMLDivElement | null >( null );
	const gridRef = useRef< HTMLDivElement | null >( null );
	useEffect( () => {
		if ( ! hasMore ) {
			return;
		}
		const el = sentinelRef.current;
		if ( ! el ) {
			return;
		}
		const observer = new IntersectionObserver(
			( entries ) => {
				if ( entries.some( ( e ) => e.isIntersecting ) ) {
					setVisibleCount( ( c ) => c + CHUNK );
				}
			},
			{ root: gridRef.current, rootMargin: '200px' }
		);
		observer.observe( el );
		return () => observer.disconnect();
	}, [ hasMore ] );

	const currentMarkup =
		catalog && value ? catalog.markup[ value ] : undefined;

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
						{ currentMarkup && catalog ? (
							<IconGlyph
								markup={ currentMarkup }
								set={ catalog.set }
							/>
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
								{ categories.length > 0 && (
									<SelectControl
										label={ __( 'Category', 'pediment' ) }
										value={ category }
										options={ [
											{
												label: __(
													'All categories',
													'pediment'
												),
												value: '',
											},
											...categories.map( ( c ) => ( {
												label: categoryLabel( c ),
												value: c,
											} ) ),
										] }
										onChange={ setCategory }
										__nextHasNoMarginBottom
									/>
								) }
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
									ref={ gridRef }
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
												markup={
													catalog.markup[ slug ]
												}
												set={ catalog.set }
											/>
										</Button>
									) ) }
									{ hasMore && (
										<div
											ref={ sentinelRef }
											className="pediment-icon-picker__sentinel"
											aria-hidden="true"
										/>
									) }
								</div>
								{ matches.length === 0 && (
									<p className="pediment-icon-picker__empty">
										{ __( 'No icons match.', 'pediment' ) }
									</p>
								) }
								{ matches.length > 0 && (
									<p className="pediment-icon-picker__count">
										{ sprintf(
											/* translators: %d: number of matching icons. */
											_n(
												'%d icon',
												'%d icons',
												matches.length,
												'pediment'
											),
											matches.length
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
