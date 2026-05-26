( function () {
	'use strict';

	function init() {
		document
			.querySelectorAll( 'form.starter-contact-form' )
			.forEach( bindForm );
	}

	function bindForm( form ) {
		const restUrl = form.getAttribute( 'data-rest-url' );
		const successMsg =
			form.getAttribute( 'data-success' ) ||
			'Thanks — we will be in touch.';
		const statusEl = form.querySelector( '.starter-contact-form__status' );
		const submitBtn = form.querySelector( '.starter-contact-form__submit' );

		form.addEventListener( 'submit', function ( e ) {
			e.preventDefault();
			if ( ! restUrl ) {
				return;
			}

			const payload = {
				name: valueOf( form, 'name' ),
				email: valueOf( form, 'email' ),
				phone: valueOf( form, 'phone' ),
				message: valueOf( form, 'message' ),
				hp_field: valueOf( form, 'hp_field' ),
				_t: valueOf( form, '_t' ),
			};

			submitBtn.disabled = true;
			showStatus( statusEl, '', null );

			fetch( restUrl, {
				method: 'POST',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify( payload ),
			} )
				.then( function ( res ) {
					return res.json().then( function ( body ) {
						return { res, body };
					} );
				} )
				.then( function ( out ) {
					submitBtn.disabled = false;
					if ( out.res.ok && out.body && out.body.ok ) {
						form.querySelectorAll(
							'input,textarea,button'
						).forEach( function ( el ) {
							el.disabled = true;
						} );
						showStatus( statusEl, successMsg, 'success' );
					} else {
						const msg =
							out.body && out.body.message
								? out.body.message
								: 'Something went wrong. Please try again.';
						showStatus( statusEl, msg, 'error' );
					}
				} )
				.catch( function () {
					submitBtn.disabled = false;
					showStatus(
						statusEl,
						'Network error. Please try again.',
						'error'
					);
				} );
		} );
	}

	function valueOf( form, name ) {
		const el = form.querySelector( '[name="' + name + '"]' );
		return el ? el.value : '';
	}

	function showStatus( el, msg, state ) {
		if ( ! el ) {
			return;
		}
		if ( ! msg ) {
			el.hidden = true;
			el.textContent = '';
			el.removeAttribute( 'data-state' );
			return;
		}
		el.hidden = false;
		el.textContent = msg;
		if ( state ) {
			el.setAttribute( 'data-state', state );
		} else {
			el.removeAttribute( 'data-state' );
		}
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
