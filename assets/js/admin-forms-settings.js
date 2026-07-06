( function () {
	'use strict';

	function fillFromPreset( form, preset ) {
		var set = function ( selector, value ) {
			var el = form.querySelector( selector );
			if ( el ) {
				el.value = value;
			}
		};
		set( '.pf-field-method', preset.method || 'POST' );
		set( '.pf-field-url', preset.url || '' );
		set( '.pf-field-content_type', preset.content_type || 'application/json' );
		set( '.pf-field-body_template', preset.body_template || '' );

		var rows = form.querySelector( '.pf-headers-rows' );
		if ( rows ) {
			rows.innerHTML = '';
			var headers = preset.headers || {};
			var keys = Object.keys( headers );
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
		var wrap = document.createElement( 'div' );
		wrap.className = 'pf-header-row';
		wrap.innerHTML =
			'<input type="text" name="header_keys[]" />' +
			'<input type="text" name="header_values[]" class="code" />';
		wrap.querySelector( '[name="header_keys[]"]' ).value = key;
		wrap.querySelector( '[name="header_values[]"]' ).value = value;
		return wrap;
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		var form = document.querySelector( '.pediment-forms-destination' );
		if ( ! form ) {
			return;
		}
		var presets = {};
		try {
			presets = JSON.parse( form.getAttribute( 'data-presets' ) || '{}' );
		} catch ( e ) {
			presets = {};
		}

		var picker = form.querySelector( '.pediment-forms-preset' );
		if ( picker ) {
			picker.addEventListener( 'change', function () {
				if ( presets[ picker.value ] ) {
					fillFromPreset( form, presets[ picker.value ] );
				}
			} );
		}

		var add = form.querySelector( '.pf-add-header' );
		if ( add ) {
			add.addEventListener( 'click', function () {
				form.querySelector( '.pf-headers-rows' ).appendChild( headerRow( '', '' ) );
			} );
		}
	} );
} )();
