import { store, getContext, getElement } from '@wordpress/interactivity';

type Ctx = { isOpen: boolean };

// Per-instance hover-close timers, keyed by the block root element.
const timers = new WeakMap< Element, ReturnType< typeof setTimeout > >();

const hoverCapable = () => window.matchMedia( '(hover: hover)' ).matches;

const closeAllExcept = ( keep: Element | null ) => {
	document
		.querySelectorAll< HTMLElement >( '.starter-mega-menu' )
		.forEach( ( el ) => {
			if ( el !== keep ) {
				el.dispatchEvent( new CustomEvent( 'starter-mega-close' ) );
			}
		} );
};

const { actions } = store( 'starter/mega-menu', {
	actions: {
		open() {
			const ctx = getContext< Ctx >();
			const { ref } = getElement();
			if ( ref ) {
				clearTimeout( timers.get( ref ) );
			}
			closeAllExcept( ref );
			ctx.isOpen = true;
		},
		close() {
			getContext< Ctx >().isOpen = false;
		},
		toggle() {
			// Hover devices drive open/close via pointer + focus; the click
			// is a no-op there to avoid an open()+toggle() double-fire on
			// hybrid touch/hover devices. Non-hover (touch) uses the click.
			if ( hoverCapable() ) {
				return;
			}
			const ctx = getContext< Ctx >();
			if ( ctx.isOpen ) {
				actions.close();
			} else {
				actions.open();
			}
		},
		onPointerEnter() {
			if ( hoverCapable() ) {
				actions.open();
			}
		},
		onPointerLeave() {
			if ( ! hoverCapable() ) {
				return;
			}
			const { ref } = getElement();
			if ( ! ref ) {
				return;
			}
			const ctx = getContext< Ctx >();
			clearTimeout( timers.get( ref ) );
			timers.set(
				ref,
				setTimeout( () => {
					ctx.isOpen = false;
				}, 150 )
			);
		},
		onKeydown( event: KeyboardEvent ) {
			if ( event.key !== 'Escape' ) {
				return;
			}
			const ctx = getContext< Ctx >();
			if ( ! ctx.isOpen ) {
				return;
			}
			ctx.isOpen = false;
			const { ref } = getElement();
			ref
				?.querySelector< HTMLButtonElement >(
					'.starter-mega-menu__trigger'
				)
				?.focus();
		},
		onFocusOut( event: FocusEvent ) {
			const { ref } = getElement();
			const next = event.relatedTarget as Node | null;
			if ( ref && ( ! next || ! ref.contains( next ) ) ) {
				actions.close();
			}
		},
	},
	callbacks: {
		init() {
			const ctx = getContext< Ctx >();
			const { ref } = getElement();

			const onClose = () => {
				ctx.isOpen = false;
			};
			const onDocPointer = ( e: Event ) => {
				if ( ctx.isOpen && ref && ! ref.contains( e.target as Node ) ) {
					ctx.isOpen = false;
				}
			};

			ref?.addEventListener( 'starter-mega-close', onClose );
			document.addEventListener( 'pointerdown', onDocPointer );

			return () => {
				ref?.removeEventListener( 'starter-mega-close', onClose );
				document.removeEventListener( 'pointerdown', onDocPointer );
				if ( ref ) {
					clearTimeout( timers.get( ref ) );
				}
			};
		},
	},
} );
