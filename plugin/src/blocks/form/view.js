( function () {
	'use strict';

	function init() {
		document.querySelectorAll( 'form.pediment-form' ).forEach( bindForm );
	}

	function bindForm( form ) {
		const restUrl = form.getAttribute( 'data-rest-url' );
		const postId = form.getAttribute( 'data-post-id' );
		const formKey = form.getAttribute( 'data-form-key' );
		const statusEl = form.querySelector( '.pediment-form__status' );
		const submitBtn = form.querySelector( '.pediment-form__submit' );
		const resetBtn = form.querySelector( '.pediment-form__reset' );

		if ( resetBtn ) {
			resetBtn.addEventListener( 'click', function () {
				hideSuccess( form );
			} );
		}

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

			clearErrors( form );
			showStatus( statusEl, '', null );

			const fileInputs = form.querySelectorAll(
				'input[type="file"][data-pediment-field]'
			);
			const request = { method: 'POST' };
			if ( fileInputs.length ) {
				const tooLarge = checkFiles( form, fileInputs );
				if ( tooLarge ) {
					showStatus( statusEl, tooLarge, 'error' );
					return;
				}
				request.body = toFormData( payload, fileInputs );
			} else {
				request.headers = { 'Content-Type': 'application/json' };
				request.body = JSON.stringify( payload );
			}

			if ( submitBtn ) {
				submitBtn.disabled = true;
			}

			fetch( restUrl, request )
				.then( function ( res ) {
					// A web server that refuses a large upload (HTTP 413)
					// answers with HTML, not JSON.
					return res
						.json()
						.catch( function () {
							return null;
						} )
						.then( function ( body ) {
							if ( ! body && res.status === 413 ) {
								body = {
									message:
										form.getAttribute(
											'data-upload-error'
										) || 'The files are too large.',
								};
							}
							return { res, body };
						} );
				} )
				.then( function ( out ) {
					if ( submitBtn ) {
						submitBtn.disabled = false;
					}
					if ( out.res.ok && out.body && out.body.ok ) {
						showSuccess( form );
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

	// Swap the fields for the success panel. The form keeps its height, so
	// the content below it does not move; the panel is centred in that box.
	function showSuccess( form ) {
		const panel = form.querySelector( '.pediment-form__success' );
		if ( ! panel ) {
			return;
		}
		form.style.minHeight = form.getBoundingClientRect().height + 'px';
		form.setAttribute( 'data-state', 'success' );
		panel.hidden = false;

		const heading = panel.querySelector(
			'.pediment-form__success-heading'
		);
		if ( heading ) {
			heading.focus( { preventScroll: true } );
		}
		const rect = panel.getBoundingClientRect();
		const viewport =
			window.innerHeight || document.documentElement.clientHeight;
		if ( rect.top < 0 || rect.bottom > viewport ) {
			const reduce =
				window.matchMedia &&
				window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;
			panel.scrollIntoView( {
				block: 'center',
				behavior: reduce ? 'auto' : 'smooth',
			} );
		}
	}

	// "Send another message": an empty form again, focus on its first field.
	function hideSuccess( form ) {
		const panel = form.querySelector( '.pediment-form__success' );
		form.reset();
		clearErrors( form );
		showStatus( form.querySelector( '.pediment-form__status' ), '', null );
		if ( panel ) {
			panel.hidden = true;
		}
		form.removeAttribute( 'data-state' );
		form.style.minHeight = '';
		const first = form.querySelector( '[data-pediment-field]' );
		if ( first ) {
			first.focus();
		}
	}

	// Check each file against its field's limit and all files against the
	// form's total limit. Field errors are shown in place; returns the status
	// message, or '' when everything fits.
	function checkFiles( form, fileInputs ) {
		const errors = {};
		let total = 0;
		fileInputs.forEach( function ( el ) {
			const max = parseInt( el.getAttribute( 'data-max-size' ), 10 );
			Array.prototype.forEach.call( el.files || [], function ( file ) {
				total += file.size;
				if ( max && file.size > max ) {
					errors[ el.name ] = el.getAttribute( 'data-size-error' );
				}
			} );
		} );
		if ( Object.keys( errors ).length ) {
			applyErrors( form, errors );
			return errors[ Object.keys( errors )[ 0 ] ];
		}
		const maxTotal = parseInt( form.getAttribute( 'data-max-upload' ), 10 );
		if ( maxTotal && total > maxTotal ) {
			return form.getAttribute( 'data-upload-error' );
		}
		return '';
	}

	function toFormData( payload, fileInputs ) {
		const data = new FormData();
		[ 'post_id', 'form_key', 'hp_field', '_t' ].forEach( function ( key ) {
			data.append( key, String( payload[ key ] ) );
		} );
		Object.keys( payload.fields ).forEach( function ( name ) {
			data.append( 'fields[' + name + ']', payload.fields[ name ] );
		} );
		fileInputs.forEach( function ( el ) {
			Array.prototype.forEach.call( el.files || [], function ( file ) {
				data.append( 'files[' + el.name + '][]', file, file.name );
			} );
		} );
		return data;
	}

	function collectFields( form ) {
		const out = {};
		form.querySelectorAll( '[data-pediment-field]' ).forEach(
			function ( el ) {
				const name = el.getAttribute( 'name' );
				if ( ! name || el.type === 'file' ) {
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
