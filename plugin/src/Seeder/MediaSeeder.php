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

	public function plan( Manifest $manifest ): Plan {
		$items    = [];
		$errors   = [];
		$existing = $this->existing();

		foreach ( $manifest->media() as $key => $spec ) {
			$extension = strtolower( (string) pathinfo( $spec->file, PATHINFO_EXTENSION ) );
			if ( ! isset( self::MIME[ $extension ] ) ) {
				$errors[] = sprintf( 'media.%s: unsupported file type ".%s".', $key, $extension );
				continue;
			}

			$items[] = isset( $existing[ $key ] )
				? new PlanItem( PlanItem::UNCHANGED, PlanItem::KIND_MEDIA, $key, '', $existing[ $key ] )
				: new PlanItem(
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

	public function apply( Plan $plan, Manifest $manifest ): MediaMap {
		$ids = $this->existing();

		foreach ( $plan->byKind( PlanItem::KIND_MEDIA ) as $item ) {
			if ( PlanItem::CREATE !== $item->action ) {
				continue;
			}
			$spec = $manifest->media()[ $item->key ] ?? null;
			if ( ! $spec instanceof MediaSpec ) {
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
			return 0;
		}

		$extension = strtolower( (string) pathinfo( $spec->file, PATHINFO_EXTENSION ) );
		$filename  = wp_unique_filename( $uploads['path'], basename( $spec->file ) );
		$target    = trailingslashit( $uploads['path'] ) . $filename;

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_copy -- seeding from a theme-shipped file, not user input.
		if ( ! copy( $spec->file, $target ) ) {
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
			return 0;
		}

		$attachmentId = (int) $attachmentId;
		update_post_meta( $attachmentId, Meta::KEY, $spec->key );

		if ( 'svg' !== $extension && 'pdf' !== $extension ) {
			wp_update_attachment_metadata( $attachmentId, wp_generate_attachment_metadata( $attachmentId, $target ) );
		}

		return $attachmentId;
	}

	/** @return array<string,int> */
	private function existing(): array {
		$ids = [];
		foreach (
			get_posts(
				[
					'post_type'      => 'attachment',
					'post_status'    => 'inherit',
					'posts_per_page' => -1,
					'no_found_rows'  => true,
					// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- seed identity lookup.
					'meta_key'       => Meta::KEY,
					'meta_compare'   => 'EXISTS',
				]
			) as $attachment
		) {
			$key = (string) get_post_meta( $attachment->ID, Meta::KEY, true );
			if ( '' !== $key && ! isset( $ids[ $key ] ) ) {
				$ids[ $key ] = (int) $attachment->ID;
			}
		}
		return $ids;
	}
}
