<?php
/**
 * Applies the site layer: registers style names and injects style data into
 * the theme global-styles origin, on top of plugin and theme styles.
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
		add_filter( 'wp_theme_json_data_theme', array( $layer, 'injectSiteData' ) );
		add_action( 'admin_init', array( $layer, 'onAdminInit' ) );
	}

	/**
	 * Registers each active site style's name so theme.json keeps its data and
	 * the Styles panel lists it. Names already registered (plugin defaults)
	 * are left alone; the site layer's theme-data injection overrides their data.
	 */
	public function registerNames(): void {
		$this->registerNamesFor( $this->store->all( SiteStyles::STATE_ACTIVE ) );
	}

	/**
	 * Registers the not-yet-registered blockType/slug pairs of the given styles.
	 *
	 * @param list<array<string,mixed>> $styles Site style specs.
	 */
	private function registerNamesFor( array $styles ): void {
		$registry = \WP_Block_Styles_Registry::get_instance();
		foreach ( $styles as $spec ) {
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
	 * Adds active site styles to the theme-origin data.
	 *
	 * Core calls this filter after it has injected registry (plugin) styles and
	 * theme styles/**.json partials into the theme data, so update_with() layers
	 * site values on top: plugin < theme < site. The post editor reads this data
	 * through the theme global-styles REST endpoint, which a user-origin filter
	 * never reached.
	 *
	 * Names are registered here too: sanitising drops variations whose name is
	 * not in the registry, and data can be built after a same-request save or
	 * between init 10 and 21.
	 *
	 * @param \WP_Theme_JSON_Data $data Theme-origin data.
	 * @return \WP_Theme_JSON_Data
	 */
	public function injectSiteData( $data ) {
		$styles = $this->store->all( SiteStyles::STATE_ACTIVE );
		$this->registerNamesFor( $styles );
		$blocks = array();
		foreach ( $styles as $spec ) {
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
	 * Skips admin-ajax (heartbeat) and users who cannot edit theme options, so
	 * they do not pay for reading every theme partial.
	 */
	public function onAdminInit(): void {
		if ( wp_doing_ajax() || ! current_user_can( 'edit_theme_options' ) ) {
			return;
		}
		$this->promoteShippedStyles();
	}

	/**
	 * Marks active site styles as promoted once the theme ships them: a theme
	 * partial with the same slug that already covers the style's block types
	 * and contains every site value. A site override whose values differ from
	 * the theme partial stays active.
	 *
	 * @param array<string, array{blockTypes: list<string>, styles: array}>|null $theme_variations Theme partials by slug; read from the active theme when null.
	 * @return list<string> Promoted slugs.
	 */
	public function promoteShippedStyles( ?array $theme_variations = null ): array {
		$theme_variations = $theme_variations ?? self::themeVariations();
		$promoted         = array();
		foreach ( $this->store->all( SiteStyles::STATE_ACTIVE ) as $spec ) {
			$theme = $theme_variations[ $spec['slug'] ] ?? null;
			if ( null === $theme || ! self::themeContains( $theme, $spec ) ) {
				continue;
			}
			if ( $this->store->setState( $spec['slug'], SiteStyles::STATE_PROMOTED ) ) {
				$promoted[] = $spec['slug'];
			}
		}
		return $promoted;
	}

	/**
	 * Whether a theme partial already holds a site style's block types and values.
	 *
	 * @param array{blockTypes: list<string>, styles: array} $theme Theme partial.
	 * @param array<string, mixed>                           $spec  Site style spec.
	 */
	private static function themeContains( array $theme, array $spec ): bool {
		if ( array() !== array_diff( $spec['blockTypes'], $theme['blockTypes'] ) ) {
			return false;
		}
		$theme_styles = self::normalizeStyles( $theme['styles'] );
		// Loose comparison: key order may differ, values must match.
		return array_replace_recursive( $theme_styles, self::normalizeStyles( $spec['styles'] ) ) == $theme_styles; // phpcs:ignore Universal.Operators.StrictComparisons.LooseEqual
	}

	/**
	 * Runs a style node through WP_Theme_JSON the way core reads theme
	 * partials, so `var:preset|color|x` and `var(--wp--preset--color--x)`
	 * compare equal.
	 *
	 * @param array $styles Style node.
	 */
	private static function normalizeStyles( array $styles ): array {
		$raw = ( new \WP_Theme_JSON(
			array(
				'version' => \WP_Theme_JSON::LATEST_SCHEMA,
				'styles'  => $styles,
			)
		) )->get_raw_data();
		return (array) ( $raw['styles'] ?? array() );
	}

	/**
	 * The active theme's block style partials (styles/**.json), keyed by slug.
	 *
	 * @return array<string, array{blockTypes: list<string>, styles: array}>
	 */
	public static function themeVariations(): array {
		$variations = array();
		foreach ( \WP_Theme_JSON_Resolver::get_style_variations( 'block' ) as $variation ) {
			$slug                = (string) ( $variation['slug'] ?? _wp_to_kebab_case( (string) ( $variation['title'] ?? '' ) ) );
			$variations[ $slug ] = array(
				'blockTypes' => array_values( (array) ( $variation['blockTypes'] ?? array() ) ),
				'styles'     => (array) ( $variation['styles'] ?? array() ),
			);
		}
		return $variations;
	}
}
