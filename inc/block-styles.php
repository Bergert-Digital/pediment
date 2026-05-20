<?php
/**
 * Register theme block styles.
 *
 * @package Starter
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
				'label' => __( 'Band — surface (white)', 'starter' ),
			)
		);
		register_block_style(
			'core/group',
			array(
				'name'  => 'band-elevated',
				'label' => __( 'Band — elevated (tinted)', 'starter' ),
			)
		);
		register_block_style(
			'core/group',
			array(
				'name'  => 'band-navy',
				'label' => __( 'Band — navy', 'starter' ),
			)
		);
	}
);
