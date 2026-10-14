<?php
/**
 * Applies the site layer: registers style names and injects style data into
 * the user global-styles origin, which overrides plugin and theme layers.
 *
 * @package Pediment
 */

declare(strict_types=1);

namespace Pediment\Design;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Runtime side of the site layer.
 */
final class SiteLayer {

	/**
	 * Constructor.
	 *
	 * @param SiteStyles $store Store.
	 */
	public function __construct( private SiteStyles $store ) {}

	/**
	 * Hooks the layer into WordPress.
	 *
	 * @param SiteLayer $layer Instance.
	 */
	public static function register( SiteLayer $layer ): void {
		add_action( 'init', array( $layer, 'registerNames' ), 21 );
		add_filter( 'wp_theme_json_data_user', array( $layer, 'injectUserData' ) );
		add_action( 'admin_init', array( $layer, 'onAdminInit' ) );
	}

	/**
	 * Registers each active site style's name so theme.json keeps its data and
	 * the Styles panel lists it. Names already registered (plugin defaults)
	 * are left alone; the user origin overrides their data.
	 */
	public function registerNames(): void {
		$registry = \WP_Block_Styles_Registry::get_instance();
		foreach ( $this->store->all( SiteStyles::STATE_ACTIVE ) as $spec ) {
			foreach ( $spec['blockTypes'] as $block_type ) {
				if ( ! $registry->is_registered( $block_type, $spec['slug'] ) ) {
					register_block_style(
						$block_type,
						array(
							'name'  => $spec['slug'],
							'label' => $spec['label'],
						)
					);
				}
			}
		}
	}

	/**
	 * Adds active site styles to the user origin.
	 *
	 * @param \WP_Theme_JSON_Data $data User-origin data.
	 * @return \WP_Theme_JSON_Data
	 */
	public function injectUserData( $data ) {
		$blocks = array();
		foreach ( $this->store->all( SiteStyles::STATE_ACTIVE ) as $spec ) {
			foreach ( $spec['blockTypes'] as $block_type ) {
				$blocks[ $block_type ]['variations'][ $spec['slug'] ] = $spec['styles'];
			}
		}
		if ( array() === $blocks ) {
			return $data;
		}
		return $data->update_with(
			array(
				'version' => \WP_Theme_JSON::LATEST_SCHEMA,
				'styles'  => array( 'blocks' => $blocks ),
			)
		);
	}

	/**
	 * Admin hook entry point. WordPress passes an empty string to admin_init
	 * callbacks, which the typed promoteShippedStyles() parameter rejects.
	 */
	public function onAdminInit(): void {
		$this->promoteShippedStyles();
	}

	/**
	 * Marks active site styles as promoted once the theme ships the same slug.
	 *
	 * @param list<string>|null $theme_slugs Theme partial slugs; read from the active theme when null.
	 * @return list<string> Promoted slugs.
	 */
	public function promoteShippedStyles( ?array $theme_slugs = null ): array {
		$theme_slugs = $theme_slugs ?? self::themeSlugs();
		$promoted    = array();
		foreach ( $this->store->all( SiteStyles::STATE_ACTIVE ) as $spec ) {
			if ( in_array( $spec['slug'], $theme_slugs, true ) && $this->store->setState( $spec['slug'], SiteStyles::STATE_PROMOTED ) ) {
				$promoted[] = $spec['slug'];
			}
		}
		return $promoted;
	}

	/**
	 * Slugs of the active theme's block style partials (styles/**.json).
	 *
	 * @return list<string>
	 */
	public static function themeSlugs(): array {
		$slugs = array();
		foreach ( \WP_Theme_JSON_Resolver::get_style_variations( 'block' ) as $variation ) {
			$slugs[] = (string) ( $variation['slug'] ?? _wp_to_kebab_case( (string) ( $variation['title'] ?? '' ) ) );
		}
		return $slugs;
	}
}
