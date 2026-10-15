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
				$lines[] = sprintf( '  %-11s %-16s %s', $item->action, $item->key, self::describe( $item ) );
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

		if ( [] !== $result->menuNotices ) {
			$lines[] = '';
			$lines[] = 'MENUS';
			foreach ( $result->menuNotices as $notice ) {
				$lines[] = '  - ' . $notice;
			}
		}

		if ( [] !== $result->notices ) {
			$lines[] = '';
			$lines[] = 'TRANSLATIONS';
			foreach ( $result->notices as $notice ) {
				$lines[] = '  - ' . $notice;
			}
		}

		$lines[] = '';
		$lines[] = self::summaryLine( $result ) . ( $result->applied ? '' : ' Nothing was written (--dry-run).' );

		return implode( "\n", $lines );
	}

	/**
	 * A claim report is its own renderer, not a seed report.
	 *
	 * Claiming writes no content, so summaryLine()'s "N to write" would read 0
	 * over a run that just gave 95 rows their identity — the exact shape of
	 * misreport this reporting exists to prevent.
	 */
	public static function claimText( ClaimResult $result ): string {
		$plan         = $result->plan;
		$applied      = $result->applied;
		$manifestPath = $result->manifestPath;
		$errors       = $result->errors;

		$lines   = [];
		$lines[] = $applied ? 'Pediment claim' : 'Pediment claim — dry run';
		if ( '' !== $manifestPath ) {
			$lines[] = 'manifest: ' . $manifestPath;
		}

		foreach (
			[
				PlanItem::KIND_ENTRY => 'PAGES & POSTS',
				PlanItem::KIND_NAV   => 'NAV',
			] as $kind => $heading
		) {
			$items = $plan->byKind( $kind );
			if ( [] === $items ) {
				continue;
			}
			$lines[] = '';
			$lines[] = $heading;
			foreach ( $items as $item ) {
				$language = '' === $item->language ? '' : ' (' . $item->language . ')';
				$lines[]  = sprintf( '  %-10s %-16s %s', $item->action, $item->key . $language, $item->note );
			}
		}

		if ( [] !== $errors ) {
			$lines[] = '';
			$lines[] = 'ERRORS';
			foreach ( $errors as $error ) {
				$lines[] = '  - ' . $error;
			}
		}

		$counts  = [
			PlanItem::CLAIM     => count( $plan->byAction( PlanItem::CLAIM ) ),
			PlanItem::NO_MATCH  => count( $plan->byAction( PlanItem::NO_MATCH ) ),
			PlanItem::AMBIGUOUS => count( $plan->byAction( PlanItem::AMBIGUOUS ) ),
		];
		$lines[] = '';
		$lines[] = sprintf(
			'%d to claim, %d without a match, %d ambiguous.%s',
			$counts[ PlanItem::CLAIM ],
			$counts[ PlanItem::NO_MATCH ],
			$counts[ PlanItem::AMBIGUOUS ],
			$applied ? '' : ' Nothing was written (--dry-run).'
		);

		return implode( "\n", $lines );
	}

	/**
	 * "N to write," is a contract, not wording: the seed-check action and
	 * client CI grep for "0 to write," to prove a seed converged. Keep both
	 * the words and the comma.
	 */
	public static function summaryLine( RunResult $result ): string {
		$counts = $result->plan->counts();
		$writes = count( array_filter( $result->plan->items(), static fn( PlanItem $item ): bool => $item->writes() ) );

		return sprintf(
			'%d to write, %d orphan, %d unchanged.',
			$writes,
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
