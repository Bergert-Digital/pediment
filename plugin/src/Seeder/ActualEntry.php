<?php
declare(strict_types=1);

namespace Pediment\Seeder;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ActualEntry {
	public function __construct(
		public readonly int $id,
		public readonly string $key,
		public readonly string $language,
		public readonly string $postType,
		public readonly string $title,
		public readonly string $slug,
		public readonly int $parentId,
		public readonly string $status,
		public readonly int $menuOrder,
		public readonly string $storedHash,
		public readonly string $currentHash,
		public readonly string $sourceHash
	) {}

	public function mapKey(): string {
		return $this->key . '|' . $this->language;
	}
}
