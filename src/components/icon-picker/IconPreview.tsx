import { useState, useEffect } from '@wordpress/element';
import { getCatalog } from './catalog';

/*
 * Render a single icon inline by slug, using the cached editor catalog.
 * Renders nothing until the catalog has loaded or if the slug is unknown.
 */
export default function IconPreview( {
	slug,
	className = 'i',
}: {
	slug: string;
	className?: string;
} ) {
	const [ markup, setMarkup ] = useState< string | undefined >( undefined );

	useEffect( () => {
		let active = true;
		getCatalog()
			.then( ( cat ) => active && setMarkup( cat[ slug ] ) )
			.catch( () => active && setMarkup( undefined ) );
		return () => {
			active = false;
		};
	}, [ slug ] );

	if ( ! markup ) {
		return null;
	}
	return (
		<svg
			className={ className }
			viewBox="0 0 256 256"
			data-icon={ slug }
			aria-hidden="true"
			focusable="false"
			dangerouslySetInnerHTML={ { __html: markup } }
		/>
	);
}
