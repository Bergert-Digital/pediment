<?php
declare(strict_types=1);

namespace Pediment\Seeder;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class PlanItem {
	public const CREATE    = 'create';
	public const RESTORE   = 'restore';
	public const UPDATE    = 'update';
	public const PROTECTED = 'protected';
	public const UNCHANGED = 'unchanged';
	public const ORPHAN    = 'orphan';

	public const KIND_ENTRY = 'entry';
	public const KIND_MEDIA = 'media';
	public const KIND_NAV   = 'nav';

	/**
	 * @param array<string,array{from:mixed,to:mixed}> $changes
	 * @param array<string,array{from:mixed,to:mixed}> $protectedFields
	 */
	public function __construct(
		public readonly string $action,
		public readonly string $kind,
		public readonly string $key,
		public readonly string $language,
		public readonly int $postId,
		public readonly array $changes = [],
		public readonly array $protectedFields = [],
		public readonly string $note = ''
	) {}

	public function mapKey(): string {
		return $this->key . '|' . $this->language;
	}

	public function writes(): bool {
		return [] !== $this->changes;
	}
}
