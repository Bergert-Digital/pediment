import { store, getContext, getElement } from '@wordpress/interactivity';

type Ctx = { isOpen: boolean };
let closeTimer: ReturnType< typeof setTimeout > | undefined;

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
			clearTimeout( closeTimer );
			closeAllExcept( ref );
			ctx.isOpen = true;
		},
		close() {
			getContext< Ctx >().isOpen = false;
		},
		toggle() {
			const ctx = getContext< Ctx >();
			if ( ctx.isOpen ) {
				actions.close();
			} else {
				actions.open();
			}
		},
		onPointerEnter() {
			if ( window.matchMedia( '(hover: hover)' ).matches ) {
				actions.open();
			}
		},
		onPointerLeave() {
			if ( window.matchMedia( '(hover: hover)' ).matches ) {
				clearTimeout( closeTimer );
				closeTimer = setTimeout( () => actions.close(), 150 );
			}
		},
		onKeydown( event: KeyboardEvent ) {
			if ( event.key === 'Escape' ) {
				const ctx = getContext< Ctx >();
				if ( ctx.isOpen ) {
					ctx.isOpen = false;
					const { ref } = getElement();
					ref
						?.querySelector< HTMLButtonElement >(
							'.starter-mega-menu__trigger'
						)
						?.focus();
				}
			}
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
			const { ref } = getElement();
			ref?.addEventListener( 'starter-mega-close', () =>
				actions.close()
			);
			const onDocPointer = ( e: Event ) => {
				const ctx = getContext< Ctx >();
				if ( ctx.isOpen && ref && ! ref.contains( e.target as Node ) ) {
					actions.close();
				}
			};
			document.addEventListener( 'pointerdown', onDocPointer );
		},
	},
} );
