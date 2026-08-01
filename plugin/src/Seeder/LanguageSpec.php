<?php
declare(strict_types=1);

namespace Pediment\Seeder;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class LanguageSpec {
	public function __construct(
		public readonly string $slug,
		public readonly string $name,
		public readonly string $locale,
		public readonly string $flag,
		public readonly bool $isDefault
	) {}
}
