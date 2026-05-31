import { useState, useEffect } from '@wordpress/element';
import { getCatalog, type IconData } from './catalog';

/*
 * Render a single icon inline by slug, using the cached editor catalog.
 * The wrapper viewBox + presentation attributes come from the set manifest,
 * so stroke-based sets render correctly. Renders nothing until the catalog
 * has loaded or if the slug is unknown.
 */
export default function IconPreview( {
	slug,
	className = 'i',
}: {
	slug: string;
	className?: string;
} ) {
	const [ data, setData ] = useState< IconData | undefined >( undefined );

	useEffect( () => {
		let active = true;
		getCatalog()
			.then( ( d ) => active && setData( d ) )
			.catch( () => active && setData( undefined ) );
		return () => {
			active = false;
		};
	}, [ slug ] );

	const markup = data?.markup[ slug ];
	if ( ! markup || ! data ) {
		return null;
	}
	return (
		<svg
			className={ className }
			viewBox={ data.set.viewBox }
			data-icon={ slug }
			aria-hidden="true"
			focusable="false"
			{ ...data.set.svgAttrs }
			dangerouslySetInnerHTML={ { __html: markup } }
		/>
	);
}
