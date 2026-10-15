<?php
/**
 * Ability pediment/get-vocabulary.
 *
 * @package Pediment
 */

declare(strict_types=1);

namespace Pediment\Abilities;

use Pediment\Content\Policy;
use Pediment\Design\Vocabulary;
use Pediment\Language\LanguageRegistry;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Everything an agent needs before writing: tokens, styles, blocks, rules.
 */
final class GetVocabulary extends Ability {

	/**
	 * Constructor.
	 *
	 * @param Vocabulary $vocabulary Vocabulary.
	 */
	public function __construct( private Vocabulary $vocabulary ) {}

	public function name(): string {
		return 'pediment/get-vocabulary';
	}

	protected function label(): string {
		return __( 'Get design vocabulary', 'pediment' );
	}

	protected function description(): string {
		return __( 'Read this first. Returns the design tokens, every named block style (with the layer that provides it), the blocks a page may use, the content rules, the WordPress version (pass it to the client-kit validate-blocks tool) and the site languages.', 'pediment' );
	}

	protected function inputSchema(): array {
		return array(
			'type'                 => 'object',
			'properties'           => array(),
			'additionalProperties' => false,
			'default'              => array(),
		);
	}

	protected function outputSchema(): array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'wordpress'       => array( 'type' => 'string' ),
				'tokens'          => array( 'type' => 'object' ),
				'styles'          => array( 'type' => 'array' ),
				'allowedBlocks'   => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
				'rules'           => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
				'languages'       => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
				'defaultLanguage' => array( 'type' => 'string' ),
			),
			'required'   => array( 'wordpress', 'tokens', 'styles', 'allowedBlocks', 'rules', 'languages', 'defaultLanguage' ),
		);
	}

	protected function annotations(): array {
		return array( 'readonly' => true, 'destructive' => false, 'idempotent' => true );
	}

	public function permission( $input ): bool {
		return current_user_can( 'edit_pages' );
	}

	public function execute( $input ) {
		$provider = LanguageRegistry::provider();
		return array(
			'wordpress'       => (string) get_bloginfo( 'version' ),
			'tokens'          => array(
				'colors'       => $this->slugs( array( 'color', 'palette' ) ),
				'fontSizes'    => $this->slugs( array( 'typography', 'fontSizes' ) ),
				'fontFamilies' => $this->slugs( array( 'typography', 'fontFamilies' ) ),
				'spacing'      => $this->slugs( array( 'spacing', 'spacingSizes' ) ),
				'shadows'      => $this->slugs( array( 'shadow', 'presets' ) ),
				'radius'       => (array) wp_get_global_settings( array( 'custom', 'radius' ) ),
			),
			'styles'          => $this->vocabulary->styles(),
			'allowedBlocks'   => Policy::allowedBlocks(),
			'rules'           => Policy::RULES,
			'languages'       => array_values( array_filter( $provider->languages(), static fn( string $code ): bool => '' !== $code ) ),
			'defaultLanguage' => $provider->defaultLanguage(),
		);
	}

	/**
	 * Preset slugs at a settings path, across origins.
	 *
	 * @param list<string> $path Settings path.
	 * @return list<string>
	 */
	private function slugs( array $path ): array {
		$slugs = array();
		foreach ( (array) wp_get_global_settings( $path ) as $items ) {
			foreach ( (array) $items as $item ) {
				if ( is_array( $item ) && isset( $item['slug'] ) ) {
					$slugs[] = (string) $item['slug'];
				}
			}
		}
		return array_values( array_unique( $slugs ) );
	}
}
