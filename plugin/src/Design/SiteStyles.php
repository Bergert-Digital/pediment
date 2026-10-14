<?php
/**
 * The site layer of the design vocabulary: revisioned, validated styles stored
 * as private posts.
 *
 * @package Pediment
 */

declare(strict_types=1);

namespace Pediment\Design;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stores one style per `pediment_style` post. post_name = slug, post_title =
 * label, post_content = JSON spec encoded with JSON_HEX_* flags so kses never
 * sees a tag, entity or quote to rewrite.
 */
final class SiteStyles {

	public const POST_TYPE      = 'pediment_style';
	public const STATE_META     = '_pediment_style_state';
	public const STATE_ACTIVE   = 'active';
	public const STATE_PROMOTED = 'promoted';

	private const JSON_FLAGS = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;

	/**
	 * Constructor.
	 *
	 * @param StyleValidator $validator Validator.
	 */
	public function __construct( private StyleValidator $validator ) {}

	/**
	 * Registers the private, revisioned post type.
	 */
	public static function registerPostType(): void {
		register_post_type(
			self::POST_TYPE,
			array(
				'label'           => __( 'Design styles', 'pediment' ),
				'public'          => false,
				'show_ui'         => false,
				'show_in_rest'    => false,
				'rewrite'         => false,
				'query_var'       => false,
				'supports'        => array( 'title', 'revisions' ),
				'capability_type' => 'page',
				'map_meta_cap'    => true,
			)
		);
	}

	/**
	 * Creates or updates a style by slug.
	 *
	 * @param array $spec Style spec.
	 * @return array{id:int,revision:int}|\WP_Error
	 */
	public function save( array $spec ) {
		$errors = $this->validator->validate( $spec );
		if ( array() !== $errors ) {
			return new \WP_Error( 'pediment_style_invalid', __( 'The style breaks the design rules.', 'pediment' ), array( 'errors' => $errors ) );
		}

		$payload = array(
			'slug'       => $spec['slug'],
			'label'      => $spec['label'],
			'blockTypes' => array_values( $spec['blockTypes'] ),
			'styles'     => $spec['styles'],
		);

		$post     = $this->find( $spec['slug'] );
		$postarr  = array(
			'post_type'    => self::POST_TYPE,
			'post_status'  => 'publish',
			'post_name'    => $spec['slug'],
			'post_title'   => $spec['label'],
			'post_content' => wp_slash( (string) wp_json_encode( $payload, self::JSON_FLAGS ) ),
		);
		if ( null !== $post ) {
			$postarr['ID'] = $post->ID;
		}

		$id = null === $post ? wp_insert_post( $postarr, true ) : wp_update_post( $postarr, true );
		if ( is_wp_error( $id ) ) {
			return $id;
		}

		update_post_meta( (int) $id, self::STATE_META, self::STATE_ACTIVE );
		wp_clean_theme_json_cache();

		$revisions = wp_get_post_revisions( (int) $id, array( 'posts_per_page' => 1 ) );
		return array(
			'id'       => (int) $id,
			'revision' => (int) ( array_key_first( $revisions ) ?? 0 ),
		);
	}

	/**
	 * Returns a stored style.
	 *
	 * @param string $slug Slug.
	 * @return array|null
	 */
	public function get( string $slug ): ?array {
		$post = $this->find( $slug );
		return null === $post ? null : $this->to_spec( $post );
	}

	/**
	 * Lists stored styles, optionally by state, ordered by slug.
	 *
	 * @param string|null $state State filter.
	 * @return list<array>
	 */
	public function all( ?string $state = null ): array {
		$args = array(
			'post_type'      => self::POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'name',
			'order'          => 'ASC',
			'no_found_rows'  => true,
		);
		if ( null !== $state ) {
			$args['meta_key']   = self::STATE_META; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			$args['meta_value'] = $state; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
		}
		$specs = array();
		foreach ( get_posts( $args ) as $post ) {
			$spec = $this->to_spec( $post );
			if ( null !== $spec ) {
				$specs[] = $spec;
			}
		}
		return $specs;
	}

	/**
	 * Sets a style's state.
	 *
	 * @param string $slug  Slug.
	 * @param string $state One of the STATE_* constants.
	 */
	public function setState( string $slug, string $state ): bool {
		$post = $this->find( $slug );
		if ( null === $post || ! in_array( $state, array( self::STATE_ACTIVE, self::STATE_PROMOTED ), true ) ) {
			return false;
		}
		update_post_meta( $post->ID, self::STATE_META, $state );
		wp_clean_theme_json_cache();
		return true;
	}

	/**
	 * Deletes a style permanently.
	 *
	 * @param string $slug Slug.
	 */
	public function delete( string $slug ): bool {
		$post = $this->find( $slug );
		if ( null === $post ) {
			return false;
		}
		$deleted = false !== wp_delete_post( $post->ID, true );
		wp_clean_theme_json_cache();
		return $deleted;
	}

	/**
	 * Finds the post for a slug.
	 *
	 * @param string $slug Slug.
	 */
	private function find( string $slug ): ?\WP_Post {
		$posts = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'publish',
				'post_name__in'  => array( $slug ),
				'posts_per_page' => 1,
				'no_found_rows'  => true,
			)
		);
		return $posts[0] ?? null;
	}

	/**
	 * Decodes a post into a spec.
	 *
	 * @param \WP_Post $post Post.
	 */
	private function to_spec( \WP_Post $post ): ?array {
		$data = json_decode( $post->post_content, true );
		if ( ! is_array( $data ) ) {
			return null;
		}
		$state = (string) get_post_meta( $post->ID, self::STATE_META, true );
		return array(
			'id'         => $post->ID,
			'slug'       => (string) ( $data['slug'] ?? $post->post_name ),
			'label'      => (string) ( $data['label'] ?? $post->post_title ),
			'blockTypes' => array_values( (array) ( $data['blockTypes'] ?? array() ) ),
			'styles'     => (array) ( $data['styles'] ?? array() ),
			'state'      => '' === $state ? self::STATE_ACTIVE : $state,
		);
	}
}
