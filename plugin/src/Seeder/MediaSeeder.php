<?php
/**
 * Media presence: every media key in the manifest resolves to exactly one
 * attachment, identified by _pediment_seed_key like everything else.
 *
 * @package Pediment
 */

declare(strict_types=1);

namespace Pediment\Seeder;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class MediaSeeder {
	private const MIME = [
		'jpg'  => 'image/jpeg',
		'jpeg' => 'image/jpeg',
		'png'  => 'image/png',
		'gif'  => 'image/gif',
		'webp' => 'image/webp',
		'avif' => 'image/avif',
		'svg'  => 'image/svg+xml',
		'pdf'  => 'application/pdf',
	];

	/** @var string[] Failures from the most recent apply(). */
	private array $errors = [];

	public function plan( Manifest $manifest ): Plan {
		$items    = [];
		$errors   = [];
		$existing = $this->existing();

		foreach ( $this->duplicates() as $key => $duplicateIds ) {
			$errors[] = sprintf(
				'media.%s is carried by %d attachments (IDs %s). Identity must be unique — delete or re-key the extras.',
				$key,
				count( $duplicateIds ),
				implode( ', ', $duplicateIds )
			);
		}

		foreach ( $manifest->media() as $key => $spec ) {
			$extension = strtolower( (string) pathinfo( $spec->file, PATHINFO_EXTENSION ) );
			if ( ! isset( self::MIME[ $extension ] ) ) {
				$errors[] = sprintf( 'media.%s: unsupported file type ".%s".', $key, $extension );
				continue;
			}

			if ( isset( $existing[ $key ] ) ) {
				$postId  = $existing[ $key ];
				$items[] = 'trash' === get_post_status( $postId )
					? new PlanItem(
						PlanItem::RESTORE,
						PlanItem::KIND_MEDIA,
						$key,
						'',
						$postId,
						[ 'status' => [ 'from' => 'trash', 'to' => 'inherit' ] ]
					)
					: new PlanItem( PlanItem::UNCHANGED, PlanItem::KIND_MEDIA, $key, '', $postId );
				continue;
			}

			$items[] = new PlanItem(
				PlanItem::CREATE,
				PlanItem::KIND_MEDIA,
				$key,
				'',
				0,
				[ 'file' => [ 'from' => null, 'to' => basename( $spec->file ) ] ]
			);
		}

		return new Plan( $items, $errors );
	}

	public function map( Manifest $manifest ): MediaMap {
		return new MediaMap( array_intersect_key( $this->existing(), $manifest->media() ) );
	}

	/** @return string[] Failures from the most recent apply(). */
	public function errors(): array {
		return $this->errors;
	}

	public function apply( Plan $plan, Manifest $manifest ): MediaMap {
		$this->errors = [];

		// Same contract as Applier::apply(): an errored plan writes nothing.
		if ( $plan->hasErrors() ) {
			$this->errors = $plan->errors();
			return $this->map( $manifest );
		}

		$ids = array_intersect_key( $this->existing(), $manifest->media() );

		foreach ( $plan->byKind( PlanItem::KIND_MEDIA ) as $item ) {
			$spec = $manifest->media()[ $item->key ] ?? null;
			if ( ! $spec instanceof MediaSpec ) {
				continue;
			}

			if ( PlanItem::RESTORE === $item->action ) {
				$restored = wp_update_post( [ 'ID' => $item->postId, 'post_status' => 'inherit' ], true );
				if ( is_wp_error( $restored ) ) {
					$this->errors[] = sprintf( 'media.%s: could not restore attachment %d — %s', $item->key, $item->postId, $restored->get_error_message() );
					continue;
				}
				Meta::clearTrashBookkeeping( $item->postId );
				$ids[ $item->key ] = $item->postId;
				continue;
			}

			if ( PlanItem::CREATE !== $item->action ) {
				continue;
			}
			$id = $this->sideload( $spec );
			if ( $id > 0 ) {
				$ids[ $item->key ] = $id;
			}
		}

		$map = new MediaMap( $ids );

		$logoKey = $manifest->siteLogo();
		if ( '' !== $logoKey && $map->id( $logoKey ) > 0 && (int) get_theme_mod( 'custom_logo' ) !== $map->id( $logoKey ) ) {
			set_theme_mod( 'custom_logo', $map->id( $logoKey ) );
		}

		return $map;
	}

	private function sideload( MediaSpec $spec ): int {
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$uploads = wp_upload_dir();
		if ( ! empty( $uploads['error'] ) ) {
			$this->errors[] = sprintf( 'media.%s: the uploads directory is not writable — %s', $spec->key, $uploads['error'] );
			return 0;
		}

		$extension = strtolower( (string) pathinfo( $spec->file, PATHINFO_EXTENSION ) );
		$filename  = wp_unique_filename( $uploads['path'], basename( $spec->file ) );
		$target    = trailingslashit( $uploads['path'] ) . $filename;

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_copy -- seeding from a theme-shipped file, not user input.
		if ( ! copy( $spec->file, $target ) ) {
			$this->errors[] = sprintf( 'media.%s: could not copy %s into the uploads directory.', $spec->key, $spec->file );
			return 0;
		}

		$attachmentId = wp_insert_attachment(
			[
				'post_mime_type' => self::MIME[ $extension ],
				'post_title'     => $spec->title,
				'post_status'    => 'inherit',
			],
			$target,
			0,
			true
		);

		if ( is_wp_error( $attachmentId ) ) {
			$this->errors[] = sprintf( 'media.%s: could not insert the attachment — %s', $spec->key, $attachmentId->get_error_message() );
			return 0;
		}

		$attachmentId = (int) $attachmentId;
		update_post_meta( $attachmentId, Meta::KEY, $spec->key );

		if ( 'svg' !== $extension && 'pdf' !== $extension ) {
			wp_update_attachment_metadata( $attachmentId, wp_generate_attachment_metadata( $attachmentId, $target ) );
		}

		return $attachmentId;
	}

	/** @return array<string,int> seed key => the oldest attachment ID carrying it */
	private function existing(): array {
		$ids = [];
		foreach ( $this->keyed() as $key => $attachmentIds ) {
			$ids[ $key ] = $attachmentIds[0];
		}
		return $ids;
	}

	/** @return array<string,int[]> Keys carried by more than one attachment. */
	private function duplicates(): array {
		return array_filter( $this->keyed(), static fn( array $attachmentIds ): bool => count( $attachmentIds ) > 1 );
	}

	/** @return array<string,int[]> seed key => attachment IDs, ordered by ID ASC */
	private function keyed(): array {
		$ids = [];
		foreach (
			get_posts(
				[
					'post_type'      => 'attachment',
					// Trashed attachments still hold their seed key: ignoring them
					// would re-upload and leave two attachments under one identity.
					'post_status'    => [ 'inherit', 'trash' ],
					'posts_per_page' => -1,
					'no_found_rows'  => true,
					'orderby'        => 'ID',
					'order'          => 'ASC',
					// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- seed identity lookup.
					'meta_key'       => Meta::KEY,
					'meta_compare'   => 'EXISTS',
				]
			) as $attachment
		) {
			$key = (string) get_post_meta( $attachment->ID, Meta::KEY, true );
			if ( '' !== $key ) {
				$ids[ $key ][] = (int) $attachment->ID;
			}
		}
		return $ids;
	}
}
