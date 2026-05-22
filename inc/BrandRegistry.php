<?php
/**
 * Brand settings field & section registry.
 *
 * @package Starter
 */

namespace Starter;

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
				'label'   => __( 'Brand name', 'starter' ),
				'section' => 'identity',
				'type'    => 'text',
				'default' => '',
			),
			'brand_tagline' => array(
				'label'   => __( 'Tagline', 'starter' ),
				'section' => 'identity',
				'type'    => 'text',
				'default' => '',
			),
			'voice_tone'    => array(
				'label'   => __( 'Voice / tone', 'starter' ),
				'section' => 'identity',
				'type'    => 'textarea',
				'default' => '',
			),
			'logo_id'       => array(
				'label'   => __( 'Logo', 'starter' ),
				'section' => 'identity',
				'type'    => 'image',
				'default' => 0,
			),
			'contact_email' => array(
				'label'   => __( 'Contact email', 'starter' ),
				'section' => 'contact',
				'type'    => 'email',
				'default' => '',
			),
			'phone'         => array(
				'label'   => __( 'Phone', 'starter' ),
				'section' => 'contact',
				'type'    => 'text',
				'default' => '',
			),
			'address'       => array(
				'label'   => __( 'Address', 'starter' ),
				'section' => 'contact',
				'type'    => 'textarea',
				'default' => '',
			),
			'social_links'  => array(
				'label'   => __( 'Social links', 'starter' ),
				'section' => 'social',
				'type'    => 'social',
				'default' => array(),
			),
			'og_image_id'   => array(
				'label'   => __( 'Default OG image', 'starter' ),
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
		$fields = (array) apply_filters( 'starter_brand_fields', $fields );

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
			'identity' => array( 'title' => __( 'Identity', 'starter' ) ),
			'contact'  => array( 'title' => __( 'Contact', 'starter' ) ),
			'social'   => array( 'title' => __( 'Social', 'starter' ) ),
			'og'       => array( 'title' => __( 'OG / SEO', 'starter' ) ),
		);

		/**
		 * Filter the Brand Settings section registry.
		 *
		 * @param array<string,array<string,string>> $sections Sections keyed by section slug.
		 */
		return (array) apply_filters( 'starter_brand_sections', $sections );
	}
}
