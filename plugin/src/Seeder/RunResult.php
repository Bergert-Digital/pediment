<?php
declare(strict_types=1);

namespace Pediment\Seeder;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class RunResult {
	/**
	 * @param string[]          $errors
	 * @param string[]          $problems
	 * @param array<string,int> $ids
	 * @param string[]          $notices
	 * @param string[]          $menuNotices Entries left out of a menu created in this run.
	 */
	public function __construct(
		public readonly Plan $plan,
		public readonly bool $applied,
		public readonly string $manifestPath = '',
		public readonly array $errors = [],
		public readonly array $problems = [],
		public readonly array $ids = [],
		public readonly array $notices = [],
		public readonly array $menuNotices = []
	) {}

	public function ok(): bool {
		return [] === $this->errors && [] === $this->problems;
	}
}
