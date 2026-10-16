<?php
/**
 * Line diff for dry-run previews.
 *
 * @package Pediment
 */

declare(strict_types=1);

namespace Pediment\Convert;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A unified-style line diff: "@@" hunks, " " context, "-" removed, "+" added.
 */
final class Diff {

	/**
	 * Longest diff returned, in bytes; longer diffs end with a marker line.
	 */
	public const MAX_BYTES = 20000;

	/**
	 * Unchanged lines shown around each change.
	 */
	private const CONTEXT = 2;

	/**
	 * Largest middle section compared line by line; above it the whole
	 * middle shows as removed and added.
	 */
	private const MAX_CELLS = 250000;

	/**
	 * The diff of two texts; '' when they are equal.
	 *
	 * @param string $before Old text.
	 * @param string $after  New text.
	 */
	public static function unified( string $before, string $after ): string {
		if ( $before === $after ) {
			return '';
		}
		$old    = explode( "\n", $before );
		$new    = explode( "\n", $after );
		$n      = count( $old );
		$m      = count( $new );
		$prefix = 0;
		while ( $prefix < $n && $prefix < $m && $old[ $prefix ] === $new[ $prefix ] ) {
			++$prefix;
		}
		$suffix = 0;
		while ( $suffix < $n - $prefix && $suffix < $m - $prefix && $old[ $n - 1 - $suffix ] === $new[ $m - 1 - $suffix ] ) {
			++$suffix;
		}
		$ops = array();
		for ( $i = 0; $i < $prefix; $i++ ) {
			$ops[] = array( ' ', $old[ $i ] );
		}
		array_push( $ops, ...self::middle( array_slice( $old, $prefix, $n - $prefix - $suffix ), array_slice( $new, $prefix, $m - $prefix - $suffix ) ) );
		for ( $i = $n - $suffix; $i < $n; $i++ ) {
			$ops[] = array( ' ', $old[ $i ] );
		}
		return self::hunks( $ops );
	}

	/**
	 * Edit operations for the changed middle (longest common subsequence).
	 *
	 * @param list<string> $left  Old lines.
	 * @param list<string> $right New lines.
	 * @return list<array{0:string,1:string}>
	 */
	private static function middle( array $left, array $right ): array {
		$n = count( $left );
		$m = count( $right );
		if ( $n * $m > self::MAX_CELLS ) {
			return array_merge(
				array_map( static fn( string $line ): array => array( '-', $line ), $left ),
				array_map( static fn( string $line ): array => array( '+', $line ), $right )
			);
		}
		$lcs = array_fill( 0, $n + 1, array_fill( 0, $m + 1, 0 ) );
		for ( $i = $n - 1; $i >= 0; $i-- ) {
			for ( $j = $m - 1; $j >= 0; $j-- ) {
				$lcs[ $i ][ $j ] = $left[ $i ] === $right[ $j ] ? $lcs[ $i + 1 ][ $j + 1 ] + 1 : max( $lcs[ $i + 1 ][ $j ], $lcs[ $i ][ $j + 1 ] );
			}
		}
		$ops = array();
		$i   = 0;
		$j   = 0;
		while ( $i < $n || $j < $m ) {
			if ( $i < $n && $j < $m && $left[ $i ] === $right[ $j ] ) {
				$ops[] = array( ' ', $left[ $i++ ] );
				++$j;
			} elseif ( $i < $n && ( $j >= $m || $lcs[ $i + 1 ][ $j ] >= $lcs[ $i ][ $j + 1 ] ) ) {
				$ops[] = array( '-', $left[ $i++ ] );
			} else {
				$ops[] = array( '+', $right[ $j++ ] );
			}
		}
		return $ops;
	}

	/**
	 * Groups operations into hunks with context and caps the length.
	 *
	 * @param list<array{0:string,1:string}> $ops Operations.
	 */
	private static function hunks( array $ops ): string {
		$show = array();
		$last = count( $ops ) - 1;
		foreach ( $ops as $k => $op ) {
			if ( ' ' !== $op[0] ) {
				$to = min( $last, $k + self::CONTEXT );
				for ( $c = max( 0, $k - self::CONTEXT ); $c <= $to; $c++ ) {
					$show[ $c ] = true;
				}
			}
		}
		$out      = '';
		$previous = -2;
		$old_line = 1;
		$new_line = 1;
		foreach ( $ops as $k => $op ) {
			if ( isset( $show[ $k ] ) ) {
				if ( $k !== $previous + 1 ) {
					$out .= "@@ -{$old_line} +{$new_line} @@\n";
				}
				$out     .= $op[0] . $op[1] . "\n";
				$previous = $k;
			}
			$old_line += '+' === $op[0] ? 0 : 1;
			$new_line += '-' === $op[0] ? 0 : 1;
		}
		if ( strlen( $out ) > self::MAX_BYTES ) {
			$out = mb_strcut( $out, 0, self::MAX_BYTES, 'UTF-8' ) . "\n… diff truncated at " . self::MAX_BYTES . " bytes\n";
		}
		return $out;
	}
}
