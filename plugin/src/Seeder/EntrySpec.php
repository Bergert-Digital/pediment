<?php
declare(strict_types=1);

namespace Pediment\Seeder;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class EntrySpec {
	/** @param array<string,string[]> $terms */
	public function __construct(
		public readonly string $key,
		public readonly string $postType,
		public readonly string $title,
		public readonly string $slug,
		public readonly ?string $parent,
		public readonly ?string $pattern,
		public readonly ?string $content,
		public readonly bool $frontPage,
		public readonly bool $postsPage,
		public readonly int $menuOrder,
		public readonly array $terms
	) {}
}
