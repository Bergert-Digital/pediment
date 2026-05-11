<?php
/**
 * Forces wp-starter-theme to be the active theme during wp-env.
 */

add_action( 'init', function () {
	if ( wp_get_theme()->get_stylesheet() !== 'wp-starter-theme' ) {
		switch_theme( 'wp-starter-theme' );
	}
}, 1 );
