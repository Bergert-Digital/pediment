<?php
declare(strict_types=1);

namespace Pediment\Seeder;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class DesiredEntry {
	/** @param array<string,string[]> $terms */
	public function __construct(
		public readonly string $key,
		public readonly string $language,
		public readonly string $postType,
		public readonly string $title,
		public readonly string $slug,
		public readonly ?string $parentKey,
		public readonly string $content,
		public readonly bool $frontPage,
		public readonly bool $postsPage,
		public readonly int $menuOrder,
		public readonly array $terms,
		public readonly string $sourceHash
	) {}

	public function id(): string {
		return $this->key . '|' . $this->language;
	}
}
