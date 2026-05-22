import { store, getContext, getElement } from '@wordpress/interactivity';

type Ctx = { isOpen: boolean; suppressFocus?: boolean };

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
		onTriggerFocus() {
			// Open when the trigger is focused (keyboard tab). Skip the
			// programmatic refocus that Escape performs, so closing does
			// not immediately reopen.
			const ctx = getContext< Ctx >();
			if ( ctx.suppressFocus ) {
				ctx.suppressFocus = false;
				return;
			}
			// On non-hover (touch) devices the tap also fires click ->
			// toggle(); let that own open/close so focus+click don't
			// cancel out. Hover/keyboard devices open on focus here.
			if ( ! hoverCapable() ) {
				return;
			}
			actions.open();
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
			// Escape closes regardless of where focus is (e.g. hover-opened
			// with focus still outside the menu) and returns focus to the
			// trigger. Document-scoped so it is not gated on focus location.
			const onDocKeydown = ( e: KeyboardEvent ) => {
				if ( e.key === 'Escape' && ctx.isOpen ) {
					ctx.isOpen = false;
					const trig = ref?.querySelector< HTMLButtonElement >(
						'.starter-mega-menu__trigger'
					);
					// Only arm the one-shot guard when the refocus will
					// actually fire a focus event. If the trigger already
					// holds focus, .focus() is a no-op and would leave
					// suppressFocus stuck true, swallowing the next genuine
					// focus-open.
					if ( trig && trig.ownerDocument.activeElement !== trig ) {
						ctx.suppressFocus = true;
					}
					trig?.focus();
				}
			};

			ref?.addEventListener( 'starter-mega-close', onClose );
			document.addEventListener( 'pointerdown', onDocPointer );
			document.addEventListener( 'keydown', onDocKeydown );

			return () => {
				ref?.removeEventListener( 'starter-mega-close', onClose );
				document.removeEventListener( 'pointerdown', onDocPointer );
				document.removeEventListener( 'keydown', onDocKeydown );
				if ( ref ) {
					clearTimeout( timers.get( ref ) );
				}
			};
		},
	},
} );
