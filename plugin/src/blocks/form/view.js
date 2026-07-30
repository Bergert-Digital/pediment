( function () {
	'use strict';

	function init() {
		document.querySelectorAll( 'form.pediment-form' ).forEach( bindForm );
	}

	function bindForm( form ) {
		const restUrl = form.getAttribute( 'data-rest-url' );
		const postId = form.getAttribute( 'data-post-id' );
		const formKey = form.getAttribute( 'data-form-key' );
		const successMsg =
			form.getAttribute( 'data-success' ) ||
			'Thanks — we will be in touch.';
		const statusEl = form.querySelector( '.pediment-form__status' );
		const submitBtn = form.querySelector( '.pediment-form__submit' );

		form.addEventListener( 'submit', function ( e ) {
			e.preventDefault();
			if ( ! restUrl ) {
				return;
			}

			const payload = {
				post_id: postId ? parseInt( postId, 10 ) : 0,
				form_key: formKey || '',
				hp_field: valueOf( form, 'hp_field' ),
				_t: valueOf( form, '_t' ),
				fields: collectFields( form ),
			};

			if ( submitBtn ) {
				submitBtn.disabled = true;
			}
			clearErrors( form );
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
					if ( submitBtn ) {
						submitBtn.disabled = false;
					}
					if ( out.res.ok && out.body && out.body.ok ) {
						form.querySelectorAll(
							'input,textarea,select,button'
						).forEach( function ( el ) {
							el.disabled = true;
						} );
						showStatus( statusEl, successMsg, 'success' );
						return;
					}
					const data =
						out.body && out.body.data ? out.body.data : null;
					if ( data && data.errors ) {
						applyErrors( form, data.errors );
					}
					const msg =
						out.body && out.body.message
							? out.body.message
							: 'Something went wrong. Please try again.';
					showStatus( statusEl, msg, 'error' );
				} )
				.catch( function () {
					if ( submitBtn ) {
						submitBtn.disabled = false;
					}
					showStatus(
						statusEl,
						'Network error. Please try again.',
						'error'
					);
				} );
		} );
	}

	function collectFields( form ) {
		const out = {};
		form.querySelectorAll( '[data-pediment-field]' ).forEach(
			function ( el ) {
				const name = el.getAttribute( 'name' );
				if ( ! name ) {
					return;
				}
				if ( el.type === 'checkbox' ) {
					out[ name ] = el.checked ? el.value || '1' : '';
					return;
				}
				if ( el.type === 'radio' ) {
					if ( el.checked ) {
						out[ name ] = el.value;
					} else if ( ! ( name in out ) ) {
						out[ name ] = '';
					}
					return;
				}
				out[ name ] = el.value;
			}
		);
		return out;
	}

	function valueOf( form, name ) {
		const el = form.querySelector( '[name="' + name + '"]' );
		return el ? el.value : '';
	}

	function clearErrors( form ) {
		form.querySelectorAll( '.pediment-form__field-error' ).forEach(
			function ( el ) {
				el.remove();
			}
		);
	}

	function applyErrors( form, errors ) {
		Object.keys( errors ).forEach( function ( name ) {
			const field = form.querySelector( '[name="' + name + '"]' );
			if ( ! field ) {
				return;
			}
			const wrap =
				field.closest( '.pediment-form__field' ) || field.parentNode;
			const err = document.createElement( 'small' );
			err.className = 'pediment-form__field-error';
			err.textContent = errors[ name ];
			wrap.appendChild( err );
		} );
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
