<?php
declare(strict_types=1);

namespace Pediment\Seeder;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class PostTypeSpec {
	/** @param array<string,mixed> $args register_post_type() args */
	public function __construct(
		public readonly string $slug,
		public readonly array $args
	) {}
}
