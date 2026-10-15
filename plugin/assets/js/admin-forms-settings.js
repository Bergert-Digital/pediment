( function () {
	'use strict';

	function fillFromPreset( form, preset ) {
		const set = function ( selector, value ) {
			const el = form.querySelector( selector );
			if ( el ) {
				el.value = value;
			}
		};
		set( '.pf-field-method', preset.method || 'POST' );
		set( '.pf-field-url', preset.url || '' );
		set(
			'.pf-field-content_type',
			preset.content_type || 'application/json'
		);
		set( '.pf-field-body_template', preset.body_template || '' );

		const rows = form.querySelector( '.pf-headers-rows' );
		if ( rows ) {
			rows.innerHTML = '';
			let headers = preset.headers || {};
			let keys = Object.keys( headers );
			if ( keys.length === 0 ) {
				keys = [ '' ];
				headers = { '': '' };
			}
			keys.forEach( function ( key ) {
				rows.appendChild( headerRow( key, headers[ key ] || '' ) );
			} );
		}
	}

	function headerRow( key, value ) {
		const wrap = document.createElement( 'div' );
		wrap.className = 'pf-header-row';
		wrap.innerHTML =
			'<input type="text" name="header_keys[]" />' +
			'<input type="text" name="header_values[]" class="code" />';
		wrap.querySelector( '[name="header_keys[]"]' ).value = key;
		wrap.querySelector( '[name="header_values[]"]' ).value = value;
		return wrap;
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		const form = document.querySelector( '.pediment-forms-destination' );
		if ( ! form ) {
			return;
		}
		let presets = {};
		try {
			presets = JSON.parse( form.getAttribute( 'data-presets' ) || '{}' );
		} catch {
			presets = {};
		}

		const picker = form.querySelector( '.pediment-forms-preset' );
		if ( picker ) {
			picker.addEventListener( 'change', function () {
				if ( presets[ picker.value ] ) {
					fillFromPreset( form, presets[ picker.value ] );
				}
			} );
		}

		const add = form.querySelector( '.pf-add-header' );
		if ( add ) {
			add.addEventListener( 'click', function () {
				form.querySelector( '.pf-headers-rows' ).appendChild(
					headerRow( '', '' )
				);
			} );
		}
	} );
} )();
