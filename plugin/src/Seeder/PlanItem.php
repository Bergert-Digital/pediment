<?php
declare(strict_types=1);

namespace Pediment\Seeder;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class PlanItem {
	public const CREATE    = 'create'; // No post carries this key yet.
	public const RESTORE   = 'restore'; // Exists but trashed.
	public const UPDATE    = 'update'; // At least one field will be written.
	public const PROTECTED = 'protected'; // Client-edited: nothing will be written.
	public const UNCHANGED = 'unchanged';
	public const ORPHAN    = 'orphan'; // Carries a seed key the manifest dropped.

	public const CLAIM     = 'claim'; // An unkeyed row will receive this seed key.
	public const NO_MATCH  = 'no-match'; // Nothing to claim; the next seed creates it.
	public const AMBIGUOUS = 'ambiguous'; // More than one candidate; nothing is written.

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
