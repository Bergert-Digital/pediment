<?php
/**
 * Registers Pediment's ability category and abilities.
 *
 * @package Pediment
 */

declare(strict_types=1);

namespace Pediment\Abilities;

use Pediment\Content\Policy;
use Pediment\Design\SiteStyles;
use Pediment\Design\StyleValidator;
use Pediment\Design\Vocabulary;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Wires the Abilities API hooks.
 */
final class Registrar {

	public const CATEGORY = 'pediment';

	/**
	 * Hooks registration (call at plugin load).
	 */
	public static function register(): void {
		add_action( 'wp_abilities_api_categories_init', array( self::class, 'registerCategory' ) );
		add_action( 'wp_abilities_api_init', array( self::class, 'registerAbilities' ) );
	}

	/**
	 * Every Pediment ability.
	 *
	 * @return list<Ability>
	 */
	public static function abilities(): array {
		$validator = new StyleValidator();
		$store     = new SiteStyles( $validator );
		$policy    = new Policy();
		return array(
			new GetVocabulary( new Vocabulary( $validator, $store ) ),
			new ListPages(),
			new ReadPage(),
			new CheckPage( $policy ),
			new WritePage( $policy ),
			new RollbackPage(),
			new UpsertStyle( $store ),
			new DeleteStyle( $store ),
		);
	}

	/**
	 * Ability names, in registration order.
	 *
	 * @return list<string>
	 */
	public static function names(): array {
		return array_map( static fn( Ability $ability ): string => $ability->name(), self::abilities() );
	}

	/**
	 * On wp_abilities_api_categories_init.
	 */
	public static function registerCategory(): void {
		wp_register_ability_category(
			self::CATEGORY,
			array(
				'label'       => __( 'Pediment', 'pediment' ),
				'description' => __( 'Build and change pages and design styles under Pediment’s content and design rules.', 'pediment' ),
			)
		);
	}

	/**
	 * On wp_abilities_api_init.
	 */
	public static function registerAbilities(): void {
		foreach ( self::abilities() as $ability ) {
			wp_register_ability( $ability->name(), $ability->args() );
		}
	}
}
