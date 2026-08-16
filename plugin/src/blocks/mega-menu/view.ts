import { store, getContext, getElement } from '@wordpress/interactivity';

type Ctx = { isOpen: boolean; suppressFocus?: boolean; pointerType?: string };

// Per-instance hover-close timers, keyed by the block root element.
const timers = new WeakMap< Element, ReturnType< typeof setTimeout > >();

const inResponsiveOverlay = ( ref: Element | null ) =>
	Boolean(
		ref?.closest(
			'.wp-block-navigation__responsive-container.is-menu-open'
		)
	);

const closeAllExcept = ( keep: Element | null ) => {
	document
		.querySelectorAll< HTMLElement >( '.starter-mega-menu' )
		.forEach( ( el ) => {
			if ( el !== keep ) {
				el.dispatchEvent( new CustomEvent( 'starter-mega-close' ) );
			}
		} );
};

const { actions } = store( 'pediment/mega-menu', {
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
		onPointerDown( event: PointerEvent ) {
			// Record the actual pointer type of the interaction so toggle()
			// (a plain click, which carries no pointerType) can tell a mouse
			// click apart from a finger/stylus tap. This is what makes hybrid
			// touchscreen laptops (hover-capable AND touch) behave correctly.
			getContext< Ctx >().pointerType = event?.pointerType || '';
		},
		toggle() {
			const { ref } = getElement();
			const ctx = getContext< Ctx >();
			// Mouse users drive open/close via pointer + focus; a mouse click
			// on the trigger is a no-op to avoid an open()+toggle() double-fire.
			// Touch/pen taps and every interaction inside the responsive
			// overlay use this click to toggle the accordion.
			if ( ctx.pointerType === 'mouse' && ! inResponsiveOverlay( ref ) ) {
				return;
			}
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
			const { ref } = getElement();
			// A touch/pen tap also fires click -> toggle(); let that own
			// open/close so the tap's focus doesn't cancel it out. Inside the
			// overlay the click owns it too. Keyboard/mouse focus opens here.
			if (
				ctx.pointerType === 'touch' ||
				ctx.pointerType === 'pen' ||
				inResponsiveOverlay( ref )
			) {
				return;
			}
			actions.open();
		},
		onPointerEnter( event: PointerEvent ) {
			const ctx = getContext< Ctx >();
			ctx.pointerType = event?.pointerType || '';
			const { ref } = getElement();
			// Only a real mouse hover opens the dropdown; touch/pen wait for
			// the tap. Never hover-open inside the responsive overlay.
			if ( ctx.pointerType === 'mouse' && ! inResponsiveOverlay( ref ) ) {
				actions.open();
			}
		},
		onPointerLeave( event: PointerEvent ) {
			const { ref } = getElement();
			if (
				( event?.pointerType || '' ) !== 'mouse' ||
				! ref ||
				inResponsiveOverlay( ref )
			) {
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
