<?php
/**
 * Register theme block styles.
 *
 * @package Pediment
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'init',
	function () {
		register_block_style(
			'core/group',
			array(
				'name'  => 'band-surface',
				'label' => __( 'Band — surface (white)', 'pediment' ),
			)
		);
		register_block_style(
			'core/group',
			array(
				'name'  => 'band-elevated',
				'label' => __( 'Band — elevated (tinted)', 'pediment' ),
			)
		);
		register_block_style(
			'core/group',
			array(
				'name'  => 'band-navy',
				'label' => __( 'Band — navy', 'pediment' ),
			)
		);
		register_block_style(
			'core/query',
			array(
				'name'  => 'insights-grid',
				'label' => __( 'Insights grid', 'pediment' ),
			)
		);
	}
);

// Insight-card styles ship only on pages that render `pediment/blog-index` or
// `core/query` (the two blocks that produce insight-card markup). Hoists ~5 KB
// of CSS out of the always-loaded theme.css.
add_action(
	'init',
	function () {
		$rel  = 'assets/css/insight-card.css';
		$path = get_theme_file_path( $rel );
		$args = array(
			'handle' => 'pediment-insight-card',
			'src'    => get_theme_file_uri( $rel ),
			'ver'    => file_exists( $path ) ? (string) filemtime( $path ) : false,
			'path'   => $path,
		);
		wp_enqueue_block_style( 'pediment/blog-index', $args );
		wp_enqueue_block_style( 'core/query', $args );
	}
);
