( function () {
	var sels = [
		'.starter-band > *',
		'.starter-hero > *',
		'.starter-feature-grid .starter-feature',
		'.starter-steps .starter-step',
		'.starter-stat',
		'.starter-logo-cloud .starter-logo',
		'.starter-faq-item',
		'.starter-media-card',
		'.starter-cta'
	];
	var nodes = document.querySelectorAll( sels.join( ',' ) );
	if ( ! nodes.length || ! ( 'IntersectionObserver' in window ) ) {
		return;
	}
	nodes.forEach( function ( el ) { el.setAttribute( 'data-reveal', '' ); } );

	var seen = new Map();
	document.querySelectorAll( '[data-reveal]' ).forEach( function ( el ) {
		var p = el.parentElement;
		var n = seen.get( p ) || 0;
		el.style.transitionDelay = Math.min( n, 6 ) * 85 + 'ms';
		seen.set( p, n + 1 );
	} );

	var io = new IntersectionObserver( function ( entries ) {
		entries.forEach( function ( e ) {
			if ( e.isIntersecting ) {
				e.target.classList.add( 'is-in' );
				io.unobserve( e.target );
			}
		} );
	}, { rootMargin: '0px 0px -8% 0px', threshold: 0.08 } );

	document.querySelectorAll( '[data-reveal]' ).forEach( function ( el ) {
		io.observe( el );
	} );
} )();
