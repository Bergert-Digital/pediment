/**
 * Presentational category filter for pediment/blog-index.
 *
 * Pure client-side: toggles the `hidden` attribute on cards. No server
 * round-trip — matches the locked design ("filter is presentational").
 * Scoped per block instance so multiple blog-index blocks coexist.
 */

function initBlogIndexFilter( root: HTMLElement ): void {
	const buttons = Array.from(
		root.querySelectorAll< HTMLButtonElement >(
			'.starter-blog-index__filter button'
		)
	);
	if ( ! buttons.length ) {
		return;
	}
	const cards = Array.from(
		root.querySelectorAll< HTMLElement >( '.starter-blog-index__item' )
	);

	buttons.forEach( ( btn ) => {
		btn.addEventListener( 'click', () => {
			buttons.forEach( ( b ) => b.classList.remove( 'is-active' ) );
			btn.classList.add( 'is-active' );
			const filter = btn.getAttribute( 'data-filter' ) || 'all';
			cards.forEach( ( card ) => {
				const cat = card.getAttribute( 'data-cat' ) || '';
				card.hidden = ! ( 'all' === filter || cat === filter );
			} );
		} );
	} );
}

function boot(): void {
	document
		.querySelectorAll< HTMLElement >( '.starter-blog-index' )
		.forEach( initBlogIndexFilter );
}

if ( 'loading' === document.readyState ) {
	document.addEventListener( 'DOMContentLoaded', boot );
} else {
	boot();
}
