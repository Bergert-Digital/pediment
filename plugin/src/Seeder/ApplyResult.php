<?php
declare(strict_types=1);

namespace Pediment\Seeder;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ApplyResult {
	/**
	 * @param array<string,int> $ids    mapKey => post ID for every desired entry that exists after the run.
	 * @param string[]          $errors
	 */
	public function __construct(
		public readonly array $ids = [],
		public readonly array $errors = []
	) {}
}
