<?php
/**
 * Brand settings field & section registry.
 *
 * @package Pediment
 */

namespace Pediment;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class BrandRegistry {
	/**
	 * @return array<string,array<string,mixed>> Keyed by field key.
	 */
	public static function fields(): array {
		$fields = array(
			'brand_name'    => array(
				'label'   => __( 'Brand name', 'pediment' ),
				'section' => 'identity',
				'type'    => 'text',
				'default' => '',
			),
			'brand_tagline' => array(
				'label'   => __( 'Tagline', 'pediment' ),
				'section' => 'identity',
				'type'    => 'text',
				'default' => '',
			),
			'voice_tone'    => array(
				'label'   => __( 'Voice / tone', 'pediment' ),
				'section' => 'identity',
				'type'    => 'textarea',
				'default' => '',
			),
			'logo_id'       => array(
				'label'   => __( 'Logo', 'pediment' ),
				'section' => 'identity',
				'type'    => 'image',
				'default' => 0,
			),
			'contact_email' => array(
				'label'   => __( 'Contact email', 'pediment' ),
				'section' => 'contact',
				'type'    => 'email',
				'default' => '',
			),
			'phone'         => array(
				'label'   => __( 'Phone', 'pediment' ),
				'section' => 'contact',
				'type'    => 'text',
				'default' => '',
			),
			'address'       => array(
				'label'   => __( 'Address', 'pediment' ),
				'section' => 'contact',
				'type'    => 'textarea',
				'default' => '',
			),
			'social_links'  => array(
				'label'   => __( 'Social links', 'pediment' ),
				'section' => 'social',
				'type'    => 'social',
				'default' => array(),
			),
			'og_image_id'   => array(
				'label'   => __( 'Default OG image', 'pediment' ),
				'section' => 'og',
				'type'    => 'image',
				'default' => 0,
			),
		);

		/**
		 * Filter the Brand Settings field registry.
		 *
		 * @param array<string,array<string,mixed>> $fields Fields keyed by field key.
		 */
		$fields = (array) apply_filters( 'pediment_brand_fields', $fields );

		// Fill in nulls so consumers can assume every field has sanitize/renderer.
		foreach ( $fields as $key => $def ) {
			$fields[ $key ] = array_merge(
				array(
					'sanitize' => null,
					'renderer' => null,
				),
				$def
			);
		}
		return $fields;
	}

	/**
	 * @return array<string,array<string,string>> Keyed by section slug.
	 */
	public static function sections(): array {
		$sections = array(
			'identity' => array( 'title' => __( 'Identity', 'pediment' ) ),
			'contact'  => array( 'title' => __( 'Contact', 'pediment' ) ),
			'social'   => array( 'title' => __( 'Social', 'pediment' ) ),
			'og'       => array( 'title' => __( 'OG / SEO', 'pediment' ) ),
		);

		/**
		 * Filter the Brand Settings section registry.
		 *
		 * @param array<string,array<string,string>> $sections Sections keyed by section slug.
		 */
		return (array) apply_filters( 'pediment_brand_sections', $sections );
	}
}
