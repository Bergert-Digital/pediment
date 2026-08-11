<?php
/**
 * Forces the standalone fixture client theme to be active during wp-env.
 */

add_action( 'init', function () {
	if ( wp_get_theme()->get_stylesheet() !== 'pediment-fixture' ) {
		switch_theme( 'pediment-fixture' );
	}
}, 1 );
