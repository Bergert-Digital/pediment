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
use Pediment\Seeder\Kses;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The one service behind the ability, the wp-admin screen and WP-CLI.
 *
 * The `$trusted` constructor flag (and `create( true )`) chooses how writes treat
 * WordPress's kses filters. Only the WP-CLI command sets it: there is no
 * current user, the operator has shell access, and kses (active when no user is
 * logged in) would strip unrelated content such as iframes and rewrite block
 * comments. A trusted run suspends kses around the write, the way the seeder
 * does (Pediment\Seeder\Kses). An untrusted run (the default, used by the
 * ability and the wp-admin screen) leaves kses on; if kses would change the
 * converted markup in any way beyond block-comment formatting and self-closing tag spacing, the post is
 * refused ("failed", nothing written), in a dry run too.
 *
 * Every write needs a backup revision. A post whose revisions are off, not
 * supported by its type, or limited to fewer than 2 is refused ("failed").
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

	/**
	 * Posts whose content candidates() reads per query.
	 */
	private const CHUNK = 100;

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
	 * @param bool      $trusted   Suspend kses while writing (WP-CLI only; see the class docblock).
	 */
	public function __construct( private Converter $converter, private Policy $policy, private Media $media, private bool $trusted = false ) {}

	/**
	 * The service with the shipped mappers and the media library.
	 *
	 * @param bool $trusted Suspend kses while writing (WP-CLI only).
	 */
	public static function create( bool $trusted = false ): self {
		return new self( Converter::withDefaults(), new Policy(), new AttachmentMedia(), $trusted );
	}

	/**
	 * IDs of every convertible post that holds a legacy block, in every
	 * language, ascending.
	 *
	 * @return list<int>
	 */
	public function candidates(): array {
		global $wpdb;
		$types    = self::TYPES;
		$statuses = self::STATUSES;
		// Coarse pre-filter: the block parser accepts any whitespace after `<!--`
		// (Converter::LEGACY_DELIMITER); the parse below decides.
		$like     = '%' . $wpdb->esc_like( '<!--' ) . '%' . $wpdb->esc_like( 'wp:pediment/' ) . '%';
		// A direct query is not scoped to the request language, so every translation is found.
		$ids = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
				"SELECT ID FROM {$wpdb->posts} WHERE post_type IN (" . implode( ',', array_fill( 0, count( $types ), '%s' ) ) . ') AND post_status IN (' . implode( ',', array_fill( 0, count( $statuses ), '%s' ) ) . ') AND post_content LIKE %s ORDER BY ID ASC',
				...array_merge( $types, $statuses, array( $like ) )
			)
		);
		$out = array();
		foreach ( array_chunk( array_map( 'intval', $ids ), self::CHUNK ) as $chunk ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT ID, post_content FROM {$wpdb->posts} WHERE ID IN (" . implode( ',', array_fill( 0, count( $chunk ), '%d' ) ) . ') ORDER BY ID ASC', ...$chunk ) );
			foreach ( $rows as $row ) {
				if ( array() !== $this->policy->legacyBlocksIn( (string) $row->post_content ) ) {
					$out[] = (int) $row->ID;
				}
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
				? array( 'title' => '' ) + $this->skipped( $id, 'you cannot edit this post' )
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
	 * to convert), failed (the converter refuses the markup — raw HTML in a
	 * legacy container, block HTML in a paragraph attribute, a legacy block
	 * left over —, conversion would add content-rule errors, the revision or
	 * kses guards refuse it, or the save failed; nothing written —
	 * except when the revision limit pruned the backup after the write: then
	 * the content is converted but the backup is gone, and the error says so),
	 * skipped (not a convertible post).
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
		if ( array() !== $result->problems ) {
			$report['status'] = 'failed';
			$report['errors'] = $result->problems;
			return $report;
		}
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
		$problem = $this->writeProblem( $post, $result->markup );
		if ( '' !== $problem ) {
			$report['status'] = 'failed';
			$report['errors'] = array( $problem );
			return $report;
		}
		if ( $dry_run ) {
			$report['status'] = 'preview';
			$report['diff']   = Diff::unified( $before, $result->markup );
			return $report;
		}

		// A trusted run (WP-CLI) suspends kses for the backup revision as well
		// as the write: revisions are stored through wp_insert_post, so kses
		// would strip the backup's iframes and a rollback would delete them.
		$kses = $this->trusted ? Kses::suspend() : false;
		try {
			// Seeded or hand-created posts may have no revision of their current
			// content; save one so rollback-page can restore the legacy version.
			$saved   = wp_save_post_revision( $id );
			$restore = is_int( $saved ) && $saved > 0 ? $saved : PageData::latestRevision( $id );
			if ( ! $this->isBackup( $restore, $id ) ) {
				$report['status'] = 'failed';
				$report['errors'] = array( 'no backup revision could be saved; nothing was written' );
				return $report;
			}
			$updated = wp_update_post(
				wp_slash(
					array(
						'ID'           => $id,
						'post_content' => $result->markup,
					)
				),
				true
			);
		} finally {
			if ( $this->trusted ) {
				Kses::restore( $kses );
			}
		}
		if ( is_wp_error( $updated ) ) {
			$report['status'] = 'failed';
			$report['errors'] = array( $updated->get_error_message() );
			return $report;
		}
		// The content changed, so the editor's invalid-block report is stale.
		InvalidBlockLog::clear( $id );
		if ( ! $this->isBackup( $restore, $id ) ) {
			// The revision limit pruned the backup after the write.
			$report['status'] = 'failed';
			$report['errors'] = array( 'the backup revision was removed after the write; restore the content from the diff or a database backup' );
			return $report;
		}
		$report['status']          = 'converted';
		$report['revision']        = PageData::latestRevision( $id );
		$report['restoreRevision'] = $restore;
		return $report;
	}

	/**
	 * Why this post cannot be written safely, or '' when it can.
	 *
	 * @param \WP_Post $post   Post.
	 * @param string   $markup Converted markup.
	 */
	private function writeProblem( \WP_Post $post, string $markup ): string {
		$keep = post_type_supports( $post->post_type, 'revisions' ) ? wp_revisions_to_keep( $post ) : 0;
		if ( $keep >= 0 && $keep < 2 ) {
			return 'revisions are disabled or limited to fewer than 2 for this post (WP_POST_REVISIONS); conversion needs a backup revision';
		}
		if ( $this->trusted || false === has_filter( 'content_save_pre', 'wp_filter_post_kses' ) ) {
			return '';
		}
		$stored = wp_unslash( apply_filters( 'content_save_pre', wp_slash( $markup ) ) );
		$stored = self::canonical( is_string( $stored ) ? $stored : '' );
		$wanted = self::canonical( $markup );
		if ( $stored === $wanted ) {
			return '';
		}
		$at = 0;
		$max = min( strlen( $stored ), strlen( $wanted ) );
		while ( $at < $max && $stored[ $at ] === $wanted[ $at ] ) {
			++$at;
		}
		$excerpt = trim( (string) preg_replace( '/\s+/', ' ', mb_strcut( $wanted, max( 0, $at - 20 ), 80, 'UTF-8' ) ) );
		return 'saving as this user would remove content outside the converted blocks: ' . $excerpt;
	}

	/**
	 * The markup in a form that ignores what kses and the block filter rewrite
	 * without changing content: block comments re-serialized (JSON spacing,
	 * escapes, block names) and self-closing tags written as `<tag ... />`.
	 *
	 * @param string $markup Markup.
	 */
	private static function canonical( string $markup ): string {
		return (string) preg_replace( '#<([a-z][a-z0-9:-]*)((?:\s+[^<>\s/][^<>]*?)?)\s*/>#i', '<$1$2 />', serialize_blocks( parse_blocks( $markup ) ) );
	}

	/**
	 * Whether the revision still exists and belongs to the post.
	 *
	 * @param int $revision Revision ID.
	 * @param int $post_id  Post ID.
	 */
	private function isBackup( int $revision, int $post_id ): bool {
		return $revision > 0 && wp_is_post_revision( $revision ) === $post_id;
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
	 * Errors in $after whose message (without its block path) is not in $before,
	 * counting repeats: a second identical error is an added error.
	 * Block paths shift when a converted block is removed, so messages are
	 * compared without them.
	 *
	 * @param list<string> $before Errors before conversion.
	 * @param list<string> $after  Errors after conversion.
	 * @return list<string>
	 */
	public static function addedErrors( array $before, array $after ): array {
		$strip = static fn( string $message ): string => (string) preg_replace( self::PATH, '', $message );
		$known = array_count_values( array_map( $strip, $before ) );
		$added = array();
		foreach ( $after as $message ) {
			$key = $strip( $message );
			if ( ( $known[ $key ] ?? 0 ) > 0 ) {
				--$known[ $key ];
			} else {
				$added[] = $message;
			}
		}
		return $added;
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
