<?php
/**
 * Converts stored posts: pages, posts, synced patterns and DB templates.
 *
 * @package Pediment
 */

declare(strict_types=1);

namespace Pediment\Convert;

use Pediment\Abilities\PageData;
use Pediment\Content\InvalidBlockLog;
use Pediment\Content\Policy;
use Pediment\Language\LanguageRegistry;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The one service behind the ability, the wp-admin screen and WP-CLI.
 */
final class PostConverter {

	/**
	 * Post types that can hold legacy blocks and render them on pages.
	 */
	public const TYPES = array( 'page', 'post', 'wp_block', 'wp_template', 'wp_template_part' );

	/**
	 * Template types; editing them also needs edit_theme_options.
	 */
	public const TEMPLATE_TYPES = array( 'wp_template', 'wp_template_part' );

	private const STATUSES = array( 'publish', 'draft', 'pending', 'private', 'future' );

	/**
	 * Strips the block path from a Policy message ("blocks[0] core/group > [1] core/paragraph: …").
	 */
	private const PATH = '/^blocks\[\d+\](?: \S+)?(?: > \[\d+\](?: \S+)?)*: /';

	/**
	 * Constructor.
	 *
	 * @param Converter $converter Converter.
	 * @param Policy    $policy    Content rules.
	 * @param Media     $media     Image lookups.
	 */
	public function __construct( private Converter $converter, private Policy $policy, private Media $media ) {}

	/**
	 * The service with the shipped mappers and the media library.
	 */
	public static function create(): self {
		return new self( Converter::withDefaults(), new Policy(), new AttachmentMedia() );
	}

	/**
	 * IDs of every convertible post that holds a legacy block, in every
	 * language, ascending.
	 *
	 * @return list<int>
	 */
	public function candidates(): array {
		$ids = get_posts(
			LanguageRegistry::provider()->unscopedQuery(
				array(
					'post_type'      => self::TYPES,
					'post_status'    => self::STATUSES,
					'posts_per_page' => -1,
					'fields'         => 'ids',
					'orderby'        => 'ID',
					'order'          => 'ASC',
					'no_found_rows'  => true,
				)
			)
		);
		$out = array();
		foreach ( array_map( 'intval', $ids ) as $id ) {
			$content = (string) get_post_field( 'post_content', $id, 'raw' );
			if ( str_contains( $content, '<!-- wp:pediment/' ) && array() !== $this->policy->legacyBlocksIn( $content ) ) {
				$out[] = $id;
			}
		}
		return $out;
	}

	/**
	 * Converts (or previews) several posts.
	 *
	 * @param list<int>          $ids     Post IDs.
	 * @param bool               $dry_run Preview only; nothing is written.
	 * @param callable|null      $can     fn(int $id): bool — posts it refuses are skipped. Null skips no post (WP-CLI).
	 * @return array{dryRun:bool,posts:list<array>,summary:array<string,int>}
	 */
	public function runMany( array $ids, bool $dry_run, ?callable $can = null ): array {
		$posts = array();
		foreach ( array_values( array_unique( array_map( 'intval', $ids ) ) ) as $id ) {
			$posts[] = null !== $can && get_post( $id ) instanceof \WP_Post && ! $can( $id )
				? $this->skipped( $id, 'you cannot edit this post' )
				: $this->run( $id, $dry_run );
		}
		return array(
			'dryRun'  => $dry_run,
			'posts'   => $posts,
			'summary' => self::summary( $posts ),
		);
	}

	/**
	 * Converts (or previews) one post.
	 *
	 * Status: preview (dry run, would convert), converted, unchanged (nothing
	 * to convert), failed (conversion would add content-rule errors, or the
	 * save failed; nothing written), skipped (not a convertible post).
	 *
	 * @param int  $id      Post ID.
	 * @param bool $dry_run Preview only.
	 * @return array{id:int,type:string,title:string,language:string,status:string,converted:list<string>,blocks:int,unmapped:list<string>,notes:list<string>,errors:list<string>,existingErrors:list<string>,diff:string,revision:int,restoreRevision:int}
	 */
	public function run( int $id, bool $dry_run ): array {
		$post = get_post( $id );
		if ( ! $post instanceof \WP_Post ) {
			return $this->skipped( $id, 'no post with this id' );
		}
		if ( ! in_array( $post->post_type, self::TYPES, true ) ) {
			return $this->skipped( $id, "post type {$post->post_type} is not converted" );
		}

		$before = $post->post_content;
		$result = $this->converter->convert( $before, $this->media );
		$report = $this->report( $post, $result );
		if ( ! $result->changed() ) {
			return $report;
		}

		$old_errors               = $this->policy->check( $before )['errors'];
		$new_errors               = $this->policy->check( $result->markup )['errors'];
		$report['existingErrors'] = $old_errors;
		$report['errors']         = self::addedErrors( $old_errors, $new_errors );
		if ( array() !== $report['errors'] ) {
			$report['status'] = 'failed';
			return $report;
		}
		if ( $dry_run ) {
			$report['status'] = 'preview';
			$report['diff']   = Diff::unified( $before, $result->markup );
			return $report;
		}

		// Seeded or hand-created posts may have no revision of their current
		// content; save one so rollback-page can restore the legacy version.
		$saved   = wp_save_post_revision( $id );
		$restore = is_int( $saved ) && $saved > 0 ? $saved : PageData::latestRevision( $id );
		$updated = wp_update_post(
			wp_slash(
				array(
					'ID'           => $id,
					'post_content' => $result->markup,
				)
			),
			true
		);
		if ( is_wp_error( $updated ) ) {
			$report['status'] = 'failed';
			$report['errors'] = array( $updated->get_error_message() );
			return $report;
		}
		InvalidBlockLog::clear( $id );
		$report['status']          = 'converted';
		$report['revision']        = PageData::latestRevision( $id );
		$report['restoreRevision'] = $restore;
		return $report;
	}

	/**
	 * Counts per status, plus the number of legacy blocks converted.
	 *
	 * @param list<array> $posts Post reports.
	 * @return array{posts:int,blocks:int,converted:int,preview:int,unchanged:int,failed:int,skipped:int}
	 */
	public static function summary( array $posts ): array {
		$summary = array(
			'posts'     => count( $posts ),
			'blocks'    => 0,
			'converted' => 0,
			'preview'   => 0,
			'unchanged' => 0,
			'failed'    => 0,
			'skipped'   => 0,
		);
		foreach ( $posts as $post ) {
			++$summary[ $post['status'] ];
			$summary['blocks'] += in_array( $post['status'], array( 'converted', 'preview' ), true ) ? $post['blocks'] : 0;
		}
		return $summary;
	}

	/**
	 * Errors in $after whose message (without its block path) is not in $before.
	 * Block paths shift when a converted block is removed, so messages are
	 * compared without them.
	 *
	 * @param list<string> $before Errors before conversion.
	 * @param list<string> $after  Errors after conversion.
	 * @return list<string>
	 */
	public static function addedErrors( array $before, array $after ): array {
		$strip = static fn( string $message ): string => (string) preg_replace( self::PATH, '', $message );
		$known = array_map( $strip, $before );
		return array_values( array_filter( $after, static fn( string $message ): bool => ! in_array( $strip( $message ), $known, true ) ) );
	}

	/**
	 * The report of a post the converter looked at.
	 *
	 * @param \WP_Post $post   Post.
	 * @param Result   $result Conversion result.
	 */
	private function report( \WP_Post $post, Result $result ): array {
		$names = array_values( array_unique( $result->converted ) );
		sort( $names );
		return array(
			'id'              => $post->ID,
			'type'            => $post->post_type,
			'title'           => $post->post_title,
			'language'        => LanguageRegistry::provider()->languageOf( $post->ID ),
			'status'          => 'unchanged',
			'converted'       => $names,
			'blocks'          => count( $result->converted ),
			'unmapped'        => $result->unmapped,
			'notes'           => $result->notes,
			'errors'          => array(),
			'existingErrors'  => array(),
			'diff'            => '',
			'revision'        => 0,
			'restoreRevision' => 0,
		);
	}

	/**
	 * The report of a post the converter did not look at.
	 *
	 * @param int    $id     Post ID.
	 * @param string $reason Why.
	 */
	private function skipped( int $id, string $reason ): array {
		$post = get_post( $id );
		return array(
			'id'              => $id,
			'type'            => $post instanceof \WP_Post ? $post->post_type : '',
			'title'           => $post instanceof \WP_Post ? $post->post_title : '',
			'language'        => '',
			'status'          => 'skipped',
			'converted'       => array(),
			'blocks'          => 0,
			'unmapped'        => array(),
			'notes'           => array( $reason ),
			'errors'          => array(),
			'existingErrors'  => array(),
			'diff'            => '',
			'revision'        => 0,
			'restoreRevision' => 0,
		);
	}
}
