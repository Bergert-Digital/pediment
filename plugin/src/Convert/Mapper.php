<?php
/**
 * Contract for one legacy block (or parent/child pair) → core blocks.
 *
 * @package Pediment
 */

declare(strict_types=1);

namespace Pediment\Convert;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Converts legacy Pediment blocks to core blocks with named styles.
 */
interface Mapper {

	/**
	 * Legacy block names this mapper converts.
	 *
	 * @return list<string>
	 */
	public function names(): array;

	/**
	 * The core block that replaces a legacy block, or null when the legacy
	 * block rendered nothing (the converter then removes it and notes it).
	 *
	 * @param array   $block   Parsed legacy block (parse_blocks() shape).
	 * @param Context $context Conversion run.
	 * @return array|null Parsed core block.
	 */
	public function map( array $block, Context $context ): ?array;
}
