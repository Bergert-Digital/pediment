<?php
/**
 * Deterministic media lookups for converter tests.
 *
 * @package Pediment
 */

use Pediment\Convert\Media;

/**
 * Attachments 55, 77, 88 and 99 exist; every other ID is missing.
 */
final class FakeMedia implements Media {

	public function url( int $id, string $size ): string {
		return in_array( $id, array( 55, 77, 88, 99 ), true ) ? "https://site.test/wp-content/uploads/2026/10/photo-{$id}-{$size}.jpg" : '';
	}

	public function alt( int $id ): string {
		return 77 === $id ? 'The team at work' : '';
	}
}
