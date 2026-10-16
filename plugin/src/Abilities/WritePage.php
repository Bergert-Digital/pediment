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
 * Creates or updates a page after the content rules pass.
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
		return __( 'Creates a page (status draft unless given) or updates the page with the given id. The markup must pass the content rules (see get-vocabulary) or nothing is written and every violation is listed. Validate static blocks with the client-kit validate-blocks tool first. Every update keeps a revision (see rollback-page). Optional: slug, parent, status, language code and translation_of (id of the page this one translates).', 'pediment' );
	}

	protected function inputSchema(): array {
		return array(
			'type'                 => 'object',
			'properties'           => array(
				'id'             => array( 'type' => 'integer', 'minimum' => 1 ),
				'title'          => array( 'type' => 'string', 'minLength' => 1 ),
				'slug'           => array( 'type' => 'string' ),
				'parent'         => array( 'type' => 'integer', 'minimum' => 0 ),
				'status'         => array( 'type' => 'string', 'enum' => array( 'draft', 'pending', 'private', 'publish' ) ),
				'language'       => array( 'type' => 'string' ),
				'translation_of' => array( 'type' => 'integer', 'minimum' => 1 ),
				'markup'         => array( 'type' => 'string' ),
			),
			'required'             => array( 'title', 'markup' ),
			'additionalProperties' => false,
		);
	}

	protected function outputSchema(): array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'id'       => array( 'type' => 'integer' ),
				'status'   => array( 'type' => 'string' ),
				'url'      => array( 'type' => 'string' ),
				'revision' => array( 'type' => 'integer' ),
				'warnings' => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
			),
			'required'   => array( 'id', 'status', 'url', 'revision', 'warnings' ),
		);
	}

	protected function annotations(): array {
		return array( 'readonly' => false, 'destructive' => false, 'idempotent' => false );
	}

	public function permission( $input ): bool {
		$input = (array) $input;
		$id    = (int) ( $input['id'] ?? 0 );
		$can   = $id > 0 ? PageData::canEditInput( $input ) : current_user_can( 'edit_pages' );
		if ( ! $can ) {
			return false;
		}
		// Linking rewrites the original's translation group, so the user must be able to edit it.
		// An unknown id passes here; execute() answers with a named error.
		$of = (int) ( $input['translation_of'] ?? 0 );
		if ( $of > 0 && PageData::find( $of ) instanceof \WP_Post && ! current_user_can( 'edit_post', $of ) ) {
			return false;
		}
		// Core's REST controller also gates `private` on publish rights.
		return ! in_array( $input['status'] ?? '', array( 'publish', 'private' ), true ) || current_user_can( 'publish_pages' );
	}

	public function execute( $input ) {
		$id = (int) ( $input['id'] ?? 0 );
		if ( $id > 0 ) {
			$page = PageData::find( $id );
			if ( ! $page instanceof \WP_Post ) {
				return $page;
			}
		}

		$provider = LanguageRegistry::provider();
		$language = (string) ( $input['language'] ?? '' );
		if ( '' !== $language && ! in_array( $language, $provider->languages(), true ) ) {
			return self::errorList( 'pediment_language_unknown', __( 'Unknown language code.', 'pediment' ), array( "language \"{$language}\" is not configured; use one of: " . implode( ', ', array_filter( $provider->languages() ) ) ) );
		}
		if ( isset( $input['translation_of'] ) ) {
			if ( '' === $language ) {
				return self::errorList( 'pediment_language_unknown', __( 'translation_of needs a language.', 'pediment' ), array( 'pass the language code of this translation together with translation_of' ) );
			}
			$of = (int) $input['translation_of'];
			if ( ! PageData::find( $of ) instanceof \WP_Post ) {
				return self::errorList( 'pediment_page_not_found', __( 'translation_of is not a page.', 'pediment' ), array( "translation_of {$of} is not a page" ), 404 );
			}
			$taken = $provider->translationOf( $of, $language );
			if ( $taken > 0 && $taken !== $id && ! current_user_can( 'edit_post', $taken ) ) {
				return self::errorList( 'pediment_translation_locked', __( 'The translation group holds a page you cannot edit. Nothing was written.', 'pediment' ), array( "translation_of {$of}: the {$language} translation is page {$taken}, which you cannot edit" ), 403 );
			}
		}
		if ( isset( $input['parent'] ) && (int) $input['parent'] > 0 && ! PageData::find( (int) $input['parent'] ) instanceof \WP_Post ) {
			return self::errorList( 'pediment_page_not_found', __( 'parent is not a page.', 'pediment' ), array( "parent {$input['parent']} is not a page" ), 404 );
		}

		$check  = $this->policy->check( (string) $input['markup'] );
		$errors = $check['errors'];
		// core/post-title prints the title unescaped and administrators bypass
		// kses, so markup in a title would reach the front end as live HTML.
		$title = (string) $input['title'];
		if ( wp_strip_all_tags( $title ) !== trim( $title ) ) {
			array_unshift( $errors, 'title: markup is not allowed in a page title — use plain text' );
		}
		if ( array() !== $errors ) {
			return self::errorList( 'pediment_content_invalid', __( 'The page breaks the content rules. Nothing was written.', 'pediment' ), $errors );
		}

		$postarr = array(
			'post_type'    => 'page',
			'post_title'   => $title,
			'post_content' => (string) $input['markup'],
		);
		if ( isset( $input['slug'] ) ) {
			$postarr['post_name'] = sanitize_title( (string) $input['slug'] );
		}
		if ( isset( $input['parent'] ) ) {
			$postarr['post_parent'] = (int) $input['parent'];
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

		InvalidBlockLog::clear( $id );

		return array(
			'id'       => $id,
			'status'   => (string) get_post_status( $id ),
			'url'      => (string) get_permalink( $id ),
			'revision' => PageData::latestRevision( $id ),
			'warnings' => $check['warnings'],
		);
	}
}
