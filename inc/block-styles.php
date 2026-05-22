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
