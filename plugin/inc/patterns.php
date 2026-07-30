<?php
/**
 * Block pattern registration. WordPress core auto-loads patterns from a
 * theme's patterns/ directory, but plugins get no equivalent scan, so
 * plugin/patterns/*.php is registered explicitly here (Task 6 of the
 * plugin-absorbs-theme migration — patterns/, including footer.php, moved
 * from the theme).
 *
 * @package Pediment
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'init',
	function () {
		pediment_register_pattern_category();
		pediment_register_patterns();
	}
);

/**
 * Registers the shared "pediment" block pattern category, if not already
 * registered.
 *
 * @return void
 */
function pediment_register_pattern_category(): void {
	$cats      = WP_Block_Pattern_Categories_Registry::get_instance()->get_all_registered();
	$cat_slugs = wp_list_pluck( $cats, 'name' );
	if ( ! in_array( 'pediment', $cat_slugs, true ) ) {
		register_block_pattern_category(
			'pediment',
			array(
				'label' => __( 'Pediment', 'pediment' ),
			)
		);
	}
}

/**
 * Scans plugin/patterns/*.php and registers each as a block pattern, mirroring
 * how WordPress core auto-registers a theme's patterns/ directory (see
 * WP_Theme::get_block_patterns() / wp-includes/theme.php). Each file declares
 * a file-header docblock (Title, Slug, Categories, …) and echoes its block
 * markup; the markup can use PHP (e.g. get_bloginfo()) so files are included
 * with output buffering rather than read as static text.
 *
 * @return void
 */
function pediment_register_patterns(): void {
	$dir   = PEDIMENT_AI_PLUGIN_DIR . '/patterns';
	$files = glob( $dir . '/*.php' );
	if ( ! $files ) {
		return;
	}

	$headers = array(
		'title'         => 'Title',
		'slug'          => 'Slug',
		'description'   => 'Description',
		'viewportWidth' => 'Viewport Width',
		'categories'    => 'Categories',
		'keywords'      => 'Keywords',
		'blockTypes'    => 'Block Types',
		'postTypes'     => 'Post Types',
		'inserter'      => 'Inserter',
	);

	$registry = WP_Block_Patterns_Registry::get_instance();

	foreach ( $files as $file ) {
		$data = get_file_data( $file, $headers );
		if ( empty( $data['slug'] ) || $registry->is_registered( $data['slug'] ) ) {
			continue;
		}

		$pattern = array( 'title' => $data['title'] );

		if ( $data['description'] ) {
			$pattern['description'] = $data['description'];
		}
		if ( $data['viewportWidth'] ) {
			$pattern['viewportWidth'] = (int) $data['viewportWidth'];
		}
		if ( $data['categories'] ) {
			$pattern['categories'] = array_filter( array_map( 'trim', explode( ',', $data['categories'] ) ) );
		}
		if ( $data['keywords'] ) {
			$pattern['keywords'] = array_filter( array_map( 'trim', explode( ',', $data['keywords'] ) ) );
		}
		if ( $data['blockTypes'] ) {
			$pattern['blockTypes'] = array_filter( array_map( 'trim', explode( ',', $data['blockTypes'] ) ) );
		}
		if ( $data['postTypes'] ) {
			$pattern['postTypes'] = array_filter( array_map( 'trim', explode( ',', $data['postTypes'] ) ) );
		}
		if ( '' !== $data['inserter'] ) {
			$pattern['inserter'] = in_array( strtolower( $data['inserter'] ), array( 'yes', 'true', '1' ), true );
		}

		ob_start();
		include $file;
		$pattern['content'] = ob_get_clean();
		if ( '' === trim( (string) $pattern['content'] ) ) {
			continue;
		}

		register_block_pattern( $data['slug'], $pattern );
	}
}
