<?php
/**
 * Outcome of converting one piece of markup.
 *
 * @package Pediment
 */

declare(strict_types=1);

namespace Pediment\Convert;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Converted markup plus what happened to it.
 */
final class Result {

	/**
	 * Constructor.
	 *
	 * @param string       $markup    Converted markup; the input itself when nothing was converted.
	 * @param list<string> $converted Legacy block names converted or removed, one per block, in document order.
	 * @param list<string> $unmapped  Legacy block names left in place because no mapper exists.
	 * @param list<string> $notes     Changes beyond the block swap: dropped styles and attributes, removed empty blocks.
	 * @param list<string> $problems  Why the markup cannot be converted safely (raw HTML in a legacy
	 *                                container, block HTML in a paragraph attribute, a legacy block that
	 *                                does not parse). When set, $markup is the input: nothing may be written.
	 */
	public function __construct(
		public readonly string $markup,
		public readonly array $converted,
		public readonly array $unmapped,
		public readonly array $notes,
		public readonly array $problems = array()
	) {}

	/**
	 * Whether the markup changed.
	 */
	public function changed(): bool {
		return array() !== $this->converted;
	}
}
