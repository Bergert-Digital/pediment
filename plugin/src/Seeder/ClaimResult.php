<?php
/**
 * The result of a `ClaimRunner::run()` call.
 *
 * @package Pediment
 */

declare(strict_types=1);

namespace Pediment\Seeder;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ClaimResult {
	/**
	 * @param string[] $errors
	 * @param bool     $dryRun The caller asked for a plan only. Not the same as
	 *                         ! $applied: a real run that stops early applies nothing too.
	 */
	public function __construct(
		public readonly Plan $plan,
		public readonly bool $applied,
		public readonly string $manifestPath = '',
		public readonly array $errors = [],
		public readonly bool $dryRun = false
	) {}
}
