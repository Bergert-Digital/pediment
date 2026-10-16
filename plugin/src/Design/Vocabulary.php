<?php
/**
 * The merged design vocabulary: every style the site can use, with the layer
 * that provides it.
 *
 * @package Pediment
 */

declare(strict_types=1);

namespace Pediment\Design;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reads plugin, theme and site layers into one list.
 */
final class Vocabulary {

	/**
	 * Constructor.
	 *
	 * @param StyleValidator $validator Validator (theme warnings).
	 * @param SiteStyles     $store     Site layer.
	 */
	public function __construct( private StyleValidator $validator, private SiteStyles $store ) {}

	/**
	 * Lists effective styles.
	 *
	 * @param array|null $theme_variations Theme partials; read from the active theme when null.
	 * @return list<array>
	 */
	public function styles( ?array $theme_variations = null ): array {
		$theme_variations = $theme_variations ?? \WP_Theme_JSON_Resolver::get_style_variations( 'block' );

		$out = array();
		foreach ( DefaultStyles::specs() as $spec ) {
			$out[ $spec['slug'] ] = $this->entry( $spec, 'plugin', array(), null );
		}
		foreach ( $theme_variations as $variation ) {
			$spec = array(
				'slug'       => (string) ( $variation['slug'] ?? _wp_to_kebab_case( (string) ( $variation['title'] ?? '' ) ) ),
				'label'      => (string) ( $variation['title'] ?? '' ),
				'blockTypes' => array_values( (array) ( $variation['blockTypes'] ?? array() ) ),
				'styles'     => (array) ( $variation['styles'] ?? array() ),
			);
			$out[ $spec['slug'] ] = $this->entry( $spec, 'theme', $this->validator->validate( $spec ), $out[ $spec['slug'] ]['layer'] ?? null );
		}
		foreach ( $this->store->all( SiteStyles::STATE_ACTIVE ) as $spec ) {
			$out[ $spec['slug'] ] = $this->entry( $spec, 'site', array(), $out[ $spec['slug'] ]['layer'] ?? null );
		}
		ksort( $out );
		return array_values( $out );
	}

	/**
	 * Builds one entry.
	 *
	 * @param array       $spec      Spec.
	 * @param string      $layer     Layer.
	 * @param list<string> $warnings Warnings.
	 * @param string|null $overrides Layer this entry replaces.
	 */
	private function entry( array $spec, string $layer, array $warnings, ?string $overrides ): array {
		return array(
			'slug'       => $spec['slug'],
			'label'      => $spec['label'],
			'blockTypes' => $spec['blockTypes'],
			'layer'      => $layer,
			'overrides'  => $overrides,
			'styles'     => $spec['styles'],
			'warnings'   => $warnings,
		);
	}
}
