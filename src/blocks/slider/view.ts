import { store, getContext, getElement } from '@wordpress/interactivity';

type Ctx = { active: number; count: number };

const wrap = ( i: number, n: number ) => ( n > 0 ? ( ( i % n ) + n ) % n : 0 );

/**
 * Apply the active index to the DOM imperatively, by document order. DOM order
 * is the single source of truth for slide/dot position, so no per-slide context
 * is needed.
 *
 * @param {HTMLElement} root   The slider root element.
 * @param {number}      active The zero-based index of the active slide.
 */
const paint = ( root: HTMLElement, active: number ) => {
	const slides = root.querySelectorAll< HTMLElement >( '.starter-slide' );
	const dots = root.querySelectorAll< HTMLElement >( '.starter-slider__dot' );

	slides.forEach( ( slide, i ) => {
		const on = i === active;
		slide.classList.toggle( 'is-active', on );
		slide.setAttribute( 'aria-hidden', on ? 'false' : 'true' );
		slide.toggleAttribute( 'inert', ! on );
	} );

	dots.forEach( ( dot, i ) => {
		const on = i === active;
		dot.classList.toggle( 'is-current', on );
		if ( on ) {
			dot.setAttribute( 'aria-current', 'true' );
		} else {
			dot.removeAttribute( 'aria-current' );
		}
	} );

	const live = root.querySelector< HTMLElement >( '.starter-slider__live' );
	if ( live ) {
		live.textContent = `${ active + 1 } / ${ slides.length }`;
	}
};

const { actions } = store( 'pediment/slider', {
	actions: {
		next() {
			const ctx = getContext< Ctx >();
			ctx.active = wrap( ctx.active + 1, ctx.count );
		},
		prev() {
			const ctx = getContext< Ctx >();
			ctx.active = wrap( ctx.active - 1, ctx.count );
		},
		goTo() {
			const ctx = getContext< Ctx >();
			const { ref } = getElement();
			const idx = Number( ref?.getAttribute( 'data-index' ) ?? 0 );
			ctx.active = wrap( idx, ctx.count );
		},
		onKeydown( event: KeyboardEvent ) {
			if ( event.key === 'ArrowRight' ) {
				event.preventDefault();
				actions.next();
			} else if ( event.key === 'ArrowLeft' ) {
				event.preventDefault();
				actions.prev();
			}
		},
	},
	callbacks: {
		init() {
			const { ref } = getElement();
			if ( ! ref ) {
				return;
			}
			ref.classList.add( 'is-enhanced' );
			const ctx = getContext< Ctx >();
			paint( ref as HTMLElement, ctx.active );
		},
		render() {
			const { ref } = getElement();
			if ( ! ref ) {
				return;
			}
			const ctx = getContext< Ctx >();
			paint( ref as HTMLElement, ctx.active );
		},
	},
} );
