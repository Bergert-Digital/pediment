<?php
/**
 * Renders a RunResult as plain text for WP-CLI and (inside <pre>) wp-admin.
 *
 * @package Pediment
 */

declare(strict_types=1);

namespace Pediment\Seeder;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Reporter {
	public static function text( RunResult $result ): string {
		$lines = [];

		$lines[] = $result->applied ? 'Pediment seed' : 'Pediment seed — dry run';
		if ( '' !== $result->manifestPath ) {
			$lines[] = 'manifest: ' . $result->manifestPath;
		}

		foreach (
			[
				PlanItem::KIND_MEDIA => 'MEDIA',
				PlanItem::KIND_ENTRY => 'PAGES & POSTS',
				PlanItem::KIND_NAV   => 'NAV',
			] as $kind => $heading
		) {
			$items = $result->plan->byKind( $kind );
			if ( [] === $items ) {
				continue;
			}
			$lines[] = '';
			$lines[] = $heading;
			foreach ( $items as $item ) {
				$described = self::describe( $item );
				$lines[]   = sprintf( '  %-11s %-16s %s', $item->action, $item->key, $described );

				// When describe() already fell back to the note, the fields line
				// would repeat that same sentence once per protected field — three
				// identical sentences for the commonest shape on a live site.
				if ( [] !== $item->protectedFields && $described !== $item->note ) {
					$lines[] = sprintf( '              ^ protected: %s (%s)', implode( ', ', array_keys( $item->protectedFields ) ), $item->note );
				}
			}
		}

		if ( [] !== $result->errors ) {
			$lines[] = '';
			// The Runner deliberately continues past a failed write so it can
			// still verify and flush, so "nothing was applied" is only true
			// before phase 4. Saying it afterwards would be a lie of exactly the
			// kind this report exists to prevent.
			$lines[] = $result->applied
				? 'ERRORS (the run continued — see above for what landed)'
				: 'ERRORS (nothing was applied)';
			foreach ( $result->errors as $error ) {
				$lines[] = '  - ' . $error;
			}
		}

		if ( [] !== $result->problems ) {
			$lines[] = '';
			$lines[] = 'VERIFICATION FAILED';
			foreach ( $result->problems as $problem ) {
				$lines[] = '  - ' . $problem;
			}
		}

		$lines[] = '';
		$lines[] = self::summaryLine( $result ) . ( $result->applied ? '' : ' Nothing was written (--dry-run).' );

		return implode( "\n", $lines );
	}

	public static function summaryLine( RunResult $result ): string {
		$counts    = $result->plan->counts();
		$writes    = ( $counts[ PlanItem::CREATE ] ?? 0 ) + ( $counts[ PlanItem::UPDATE ] ?? 0 ) + ( $counts[ PlanItem::RESTORE ] ?? 0 );
		$protected = 0;
		foreach ( $result->plan->items() as $item ) {
			if ( [] !== $item->protectedFields ) {
				++$protected;
			}
		}

		return sprintf(
			'%d to write, %d protected, %d orphan, %d unchanged.',
			$writes,
			$protected,
			$counts[ PlanItem::ORPHAN ] ?? 0,
			$counts[ PlanItem::UNCHANGED ] ?? 0
		);
	}

	private static function describe( PlanItem $item ): string {
		$parts = [];
		foreach ( $item->changes as $field => $change ) {
			if ( 'content' === $field ) {
				$parts[] = sprintf( 'content -> %d bytes', strlen( (string) $change['to'] ) );
				continue;
			}
			$parts[] = null === $change['from']
				? sprintf( '%s=%s', $field, self::truncate( self::scalar( $change['to'] ) ) )
				: self::change( $field, $change['from'], $change['to'] );
		}
		if ( [] === $parts && '' !== $item->note ) {
			return $item->note;
		}
		return implode( '; ', $parts );
	}

	private static function scalar( mixed $value ): string {
		if ( is_bool( $value ) ) {
			return $value ? 'true' : 'false';
		}
		return (string) ( null === $value ? '' : $value );
	}

	/** Keeps one plan line readable when a title or slug is very long. */
	private static function truncate( string $value, int $limit = 60 ): string {
		return mb_strlen( $value ) > $limit ? mb_substr( $value, 0, $limit - 1 ) . '…' : $value;
	}

	private static function change( string $field, mixed $from, mixed $to ): string {
		// Numbers read badly in quotes: `items 3 -> 4`, not `items "3" -> "4"`.
		if ( is_int( $from ) && is_int( $to ) ) {
			return sprintf( '%s %d -> %d', $field, $from, $to );
		}
		return sprintf( '%s "%s" -> "%s"', $field, self::truncate( self::scalar( $from ) ), self::truncate( self::scalar( $to ) ) );
	}
}
