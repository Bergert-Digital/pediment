<?php
/**
 * The post types the page abilities serve.
 *
 * @package Pediment
 */

declare(strict_types=1);

namespace Pediment\Abilities;

use Pediment\Language\LanguageRegistry;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Decides which post types the page abilities may list, read and write, and
 * maps their capabilities from the post type object.
 */
final class PostTypes {

	public const FILTER  = 'pediment_mcp_post_types';
	public const DEFAULT = 'page';

	/**
	 * Built-in types the abilities serve; every other built-in type
	 * (wp_block, wp_template, wp_navigation, attachment, …) has its own
	 * semantics and is never served.
	 */
	private const BUILTIN = array( 'page', 'post' );

	/**
	 * Types never served, even through the filter: site styles have their own abilities.
	 */
	private const NEVER = array( 'pediment_style' );

	/**
	 * Served post type slugs: page, post, and every non-built-in type that
	 * is in the REST API and edited in the block editor. The filter may
	 * remove types or add non-built-in ones; the rules apply after it.
	 *
	 * @return list<string>
	 */
	public static function served(): array {
		$types = array_merge( self::BUILTIN, array_values( get_post_types( array( '_builtin' => false ), 'names' ) ) );
		/**
		 * Filters the post types the Pediment MCP tools serve.
		 *
		 * @param list<string> $types Post type slugs.
		 */
		$types = (array) apply_filters( self::FILTER, $types );
		$types = array_unique( array_map( 'strval', $types ) );
		return array_values( array_filter( $types, array( self::class, 'qualifies' ) ) );
	}

	/**
	 * Whether the abilities serve this post type.
	 *
	 * @param string $type Post type slug.
	 */
	public static function isServed( string $type ): bool {
		return in_array( $type, self::served(), true );
	}

	/**
	 * The post type from ability input (default page).
	 *
	 * @param mixed $input Ability input.
	 */
	public static function fromInput( $input ): string {
		return (string) ( ( (array) $input )['post_type'] ?? self::DEFAULT );
	}

	/**
	 * The named error for a type the abilities do not serve, or null.
	 *
	 * @param string $type Post type slug.
	 */
	public static function refusal( string $type ): ?\WP_Error {
		if ( self::isServed( $type ) ) {
			return null;
		}
		$problem = "post_type \"{$type}\" is not available; use one of: " . implode( ', ', self::served() );
		return new \WP_Error(
			'pediment_post_type_unavailable',
			__( 'The MCP tools do not serve that post type.', 'pediment' ) . "\n- " . $problem,
			array(
				'status' => 400,
				'errors' => array( $problem ),
			)
		);
	}

	/**
	 * Whether the current user has a primitive capability of the post type,
	 * e.g. `edit_posts` (edit_pages for pages) or `publish_posts`. A type the
	 * abilities do not serve falls back to the plain capability, so the call
	 * reaches execute() and gets a named error instead of a bare refusal.
	 *
	 * @param string $type Post type slug.
	 * @param string $cap  Key of the post type's `cap` object.
	 */
	public static function can( string $type, string $cap ): bool {
		$object = self::isServed( $type ) ? get_post_type_object( $type ) : null;
		return current_user_can( $object instanceof \WP_Post_Type ? (string) $object->cap->{$cap} : $cap );
	}

	/**
	 * Whether the current user may edit some served type (gate for the
	 * abilities that are not about one type).
	 */
	public static function canEditAny(): bool {
		foreach ( self::served() as $type ) {
			if ( self::can( $type, 'edit_posts' ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * The served types, for get-vocabulary.
	 *
	 * @return list<array{slug:string,label:string,hierarchical:bool,revisions:bool,translated:bool}>
	 */
	public static function describe(): array {
		$provider = LanguageRegistry::provider();
		$out      = array();
		foreach ( self::served() as $type ) {
			$object = get_post_type_object( $type );
			$out[]  = array(
				'slug'         => $type,
				'label'        => (string) $object->labels->singular_name,
				'hierarchical' => (bool) $object->hierarchical,
				'revisions'    => post_type_supports( $type, 'revisions' ),
				'translated'   => $provider->translatesPostType( $type ),
			);
		}
		return $out;
	}

	/**
	 * Whether a post type meets the hard rules.
	 *
	 * @param string $type Post type slug.
	 */
	private static function qualifies( string $type ): bool {
		$object = get_post_type_object( $type );
		if ( ! $object instanceof \WP_Post_Type || in_array( $type, self::NEVER, true ) ) {
			return false;
		}
		if ( $object->_builtin && ! in_array( $type, self::BUILTIN, true ) ) {
			return false;
		}
		return $object->show_in_rest && post_type_supports( $type, 'editor' ) && use_block_editor_for_post_type( $type );
	}
}
