<?php
/**
 * Seed key => attachment lookups for content resolution.
 *
 * @package Pediment
 */

declare(strict_types=1);

namespace Pediment\Seeder;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class MediaMap {
	/** @param array<string,int> $ids seed key => attachment ID */
	public function __construct( private array $ids = [] ) {}

	public function has( string $key ): bool {
		return ! empty( $this->ids[ $key ] );
	}

	public function id( string $key ): int {
		return (int) ( $this->ids[ $key ] ?? 0 );
	}

	public function url( string $key ): string {
		$id = $this->id( $key );
		return $id > 0 ? (string) wp_get_attachment_url( $id ) : '';
	}
}
