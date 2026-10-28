<?php
/**
 * Ability pediment/write-page.
 *
 * @package Pediment
 */

declare(strict_types=1);

namespace Pediment\Abilities;

use Pediment\Content\InvalidBlockLog;
use Pediment\Content\Policy;
use Pediment\Language\LanguageRegistry;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Creates or updates a page (or post of another served type) after the content rules pass.
 */
final class WritePage extends Ability {

	/**
	 * Constructor.
	 *
	 * @param Policy $policy Policy.
	 */
	public function __construct( private Policy $policy ) {}

	public function name(): string {
		return 'pediment/write-page';
	}

	protected function label(): string {
		return __( 'Write page', 'pediment' );
	}

	protected function description(): string {
		return __( 'Creates a page (status draft unless given) or updates the page with the given id. With post_type (default page; get-vocabulary lists the served types) it writes a post of that type instead, and an id must be of that type. The markup must pass the content rules (see get-vocabulary) or nothing is written and every violation is listed. Validate static blocks with the client-kit validate-blocks tool first. Every update keeps a revision (see rollback-page). Optional: slug, parent (hierarchical types only), excerpt (plain text), menu_order, featured_image ({url, alt} imports the image once per URL and reuses it later; {id, alt} uses a media library image; {id: 0} removes it; alt belongs to this post, so each translation keeps its own), status, language code and translation_of (id of the post of the same type this one translates; translated types only). Fields the post type does not support are refused.', 'pediment' );
	}

	protected function inputSchema(): array {
		return array(
			'type'                 => 'object',
			'properties'           => array(
				'id'             => array( 'type' => 'integer', 'minimum' => 1 ),
				'post_type'      => PageData::postTypeSchema(),
				'title'          => array( 'type' => 'string', 'minLength' => 1 ),
				'slug'           => array( 'type' => 'string' ),
				'parent'         => array( 'type' => 'integer', 'minimum' => 0 ),
				'status'         => array( 'type' => 'string', 'enum' => array( 'draft', 'pending', 'private', 'publish' ) ),
				'language'       => array( 'type' => 'string' ),
				'translation_of' => array( 'type' => 'integer', 'minimum' => 1 ),
				'markup'         => array( 'type' => 'string' ),
				'excerpt'        => array( 'type' => 'string' ),
				'menu_order'     => array( 'type' => 'integer' ),
				'featured_image' => array(
					'type'                 => 'object',
					'properties'           => array(
						'url' => array( 'type' => 'string' ),
						'id'  => array( 'type' => 'integer', 'minimum' => 0 ),
						'alt' => array( 'type' => 'string' ),
					),
					'additionalProperties' => false,
				),
			),
			'required'             => array( 'title', 'markup' ),
			'additionalProperties' => false,
		);
	}

	protected function outputSchema(): array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'id'             => array( 'type' => 'integer' ),
				'status'         => array( 'type' => 'string' ),
				'url'            => array( 'type' => 'string' ),
				'revision'       => array( 'type' => 'integer' ),
				'featured_image' => array( 'type' => 'integer' ),
				'warnings'       => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
			),
			'required'   => array( 'id', 'status', 'url', 'revision', 'featured_image', 'warnings' ),
		);
	}

	protected function annotations(): array {
		return array( 'readonly' => false, 'destructive' => false, 'idempotent' => false );
	}

	public function permission( $input ): bool {
		$input = (array) $input;
		$type  = PostTypes::fromInput( $input );
		$id    = (int) ( $input['id'] ?? 0 );
		$can   = $id > 0 ? PageData::canEditInput( $input ) : PostTypes::can( $type, 'edit_posts' );
		if ( ! $can ) {
			return false;
		}
		// Linking rewrites the original's translation group, so the user must be able to edit it.
		// An unknown id passes here; execute() answers with a named error.
		$of = (int) ( $input['translation_of'] ?? 0 );
		if ( $of > 0 && PageData::find( $of, $type ) instanceof \WP_Post && ! current_user_can( 'edit_post', $of ) ) {
			return false;
		}
		// Importing an image uploads a file.
		if ( isset( $input['featured_image']['url'] ) && ! current_user_can( 'upload_files' ) ) {
			return false;
		}
		// Core's REST controller also gates `private` on publish rights.
		return ! in_array( $input['status'] ?? '', array( 'publish', 'private' ), true ) || PostTypes::can( $type, 'publish_posts' );
	}

	public function execute( $input ) {
		$type    = PostTypes::fromInput( $input );
		$refused = PostTypes::refusal( $type );
		if ( null !== $refused ) {
			return $refused;
		}
		$id = (int) ( $input['id'] ?? 0 );
		if ( $id > 0 ) {
			$page = PageData::find( $id, $type );
			if ( ! $page instanceof \WP_Post ) {
				return $page;
			}
		}

		$provider = LanguageRegistry::provider();
		$language = (string) ( $input['language'] ?? '' );
		if ( '' !== $language && ! in_array( $language, $provider->languages(), true ) ) {
			return self::errorList( 'pediment_language_unknown', __( 'Unknown language code.', 'pediment' ), array( "language \"{$language}\" is not configured; use one of: " . implode( ', ', array_filter( $provider->languages() ) ) ) );
		}
		if ( '' !== $language && ! $provider->translatesPostType( $type ) ) {
			return self::errorList( 'pediment_language_unsupported', __( 'This post type has no languages. Nothing was written.', 'pediment' ), array( "post_type \"{$type}\" is not translated; leave out language and translation_of, or make the type translatable in the language plugin" ) );
		}
		if ( isset( $input['translation_of'] ) ) {
			if ( '' === $language ) {
				return self::errorList( 'pediment_language_unknown', __( 'translation_of needs a language.', 'pediment' ), array( 'pass the language code of this translation together with translation_of' ) );
			}
			$of = (int) $input['translation_of'];
			if ( ! PageData::find( $of, $type ) instanceof \WP_Post ) {
				return self::errorList( 'pediment_page_not_found', __( 'translation_of is not a post of this type.', 'pediment' ), array( "translation_of {$of} is not a {$type}" ), 404 );
			}
			$taken = $provider->translationOf( $of, $language );
			if ( $taken > 0 && $taken !== $id && ! current_user_can( 'edit_post', $taken ) ) {
				return self::errorList( 'pediment_translation_locked', __( 'The translation group holds a page you cannot edit. Nothing was written.', 'pediment' ), array( "translation_of {$of}: the {$language} translation is page {$taken}, which you cannot edit" ), 403 );
			}
		}
		$parent = (int) ( $input['parent'] ?? 0 );
		if ( $parent > 0 && ! is_post_type_hierarchical( $type ) ) {
			return self::errorList( 'pediment_parent_unsupported', __( 'This post type has no parents. Nothing was written.', 'pediment' ), array( "post_type \"{$type}\" is not hierarchical; leave out parent" ) );
		}
		if ( $parent > 0 && ! PageData::find( $parent, $type ) instanceof \WP_Post ) {
			return self::errorList( 'pediment_page_not_found', __( 'parent is not a post of this type.', 'pediment' ), array( "parent {$parent} is not a {$type}" ), 404 );
		}
		$unsupported = array();
		foreach ( array( 'excerpt' => 'excerpt', 'menu_order' => 'page-attributes' ) as $field => $feature ) {
			if ( isset( $input[ $field ] ) && ! post_type_supports( $type, $feature ) ) {
				$unsupported[] = "{$field}: post_type \"{$type}\" does not support {$feature}; leave out {$field}";
			}
		}
		$image = isset( $input['featured_image'] ) ? (array) $input['featured_image'] : null;
		if ( null !== $image ) {
			$unsupported = array_merge( $unsupported, FeaturedImage::problems( $image, $type ) );
		}
		if ( array() !== $unsupported ) {
			return self::errorList( 'pediment_field_unsupported', __( 'A field does not fit this post type. Nothing was written.', 'pediment' ), $unsupported );
		}

		$check  = $this->policy->check( (string) $input['markup'] );
		$errors = $check['errors'];
		// core/post-title prints the title unescaped and administrators bypass
		// kses, so markup in a title would reach the front end as live HTML.
		$title = (string) $input['title'];
		if ( wp_strip_all_tags( $title ) !== trim( $title ) ) {
			array_unshift( $errors, 'title: markup is not allowed in a page title — use plain text' );
		}
		// Themes print excerpts without escaping, as they print titles.
		$excerpt = isset( $input['excerpt'] ) ? (string) $input['excerpt'] : null;
		if ( null !== $excerpt && wp_strip_all_tags( $excerpt ) !== trim( $excerpt ) ) {
			array_unshift( $errors, 'excerpt: markup is not allowed in an excerpt — use plain text' );
		}
		if ( array() !== $errors ) {
			return self::errorList( 'pediment_content_invalid', __( 'The page breaks the content rules. Nothing was written.', 'pediment' ), $errors );
		}

		// Imported before the post is written, so a failed download writes nothing.
		$attachment = null === $image ? null : FeaturedImage::resolve( $image );
		if ( is_wp_error( $attachment ) ) {
			return $attachment;
		}

		$postarr = array(
			'post_type'    => $type,
			'post_title'   => $title,
			'post_content' => (string) $input['markup'],
		);
		if ( isset( $input['slug'] ) ) {
			$postarr['post_name'] = sanitize_title( (string) $input['slug'] );
		}
		if ( isset( $input['parent'] ) ) {
			$postarr['post_parent'] = (int) $input['parent'];
		}
		if ( null !== $excerpt ) {
			$postarr['post_excerpt'] = trim( $excerpt );
		}
		if ( isset( $input['menu_order'] ) ) {
			$postarr['menu_order'] = (int) $input['menu_order'];
		}
		if ( isset( $input['status'] ) ) {
			$postarr['post_status'] = (string) $input['status'];
		} elseif ( 0 === $id ) {
			$postarr['post_status'] = 'draft';
		}

		if ( $id > 0 ) {
			$postarr['ID'] = $id;
			$result        = wp_update_post( wp_slash( $postarr ), true );
		} else {
			$result = wp_insert_post( wp_slash( $postarr ), true );
			if ( ! is_wp_error( $result ) && $result > 0 ) {
				// WordPress saves no revision on creation; keep the first content restorable.
				wp_save_post_revision( $result );
			}
		}
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$id = (int) $result;

		if ( '' !== $language ) {
			$provider->setLanguage( $id, $language );
			if ( isset( $input['translation_of'] ) ) {
				$map = array();
				foreach ( $provider->languages() as $code ) {
					$other = '' === $code ? 0 : $provider->translationOf( (int) $input['translation_of'], $code );
					if ( $other > 0 ) {
						$map[ $code ] = $other;
					}
				}
				$map[ $language ] = $id;
				$provider->linkTranslations( $map );
			}
		}

		if ( null !== $attachment ) {
			FeaturedImage::apply( $id, $attachment, isset( $image['alt'] ) ? (string) $image['alt'] : null );
		}

		InvalidBlockLog::clear( $id );

		$warnings = $check['warnings'];
		if ( ! wp_revisions_enabled( get_post( $id ) ) ) {
			$warnings[] = "post_type \"{$type}\" keeps no revisions on this site; this write cannot be rolled back";
		}

		return array(
			'id'             => $id,
			'status'         => (string) get_post_status( $id ),
			'url'            => (string) get_permalink( $id ),
			'revision'       => PageData::latestRevision( $id ),
			'featured_image' => (int) get_post_thumbnail_id( $id ),
			'warnings'       => $warnings,
		);
	}
}
