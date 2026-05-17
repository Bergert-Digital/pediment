import { store, getContext } from '@wordpress/interactivity';

store( 'starter/mega-menu', {
	actions: {
		toggle() {
			const ctx = getContext< { isOpen: boolean } >();
			ctx.isOpen = ! ctx.isOpen;
		},
		onKeydown() {},
		onFocusOut() {},
		onPointerEnter() {},
		onPointerLeave() {},
	},
} );
