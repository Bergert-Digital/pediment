<?php
declare(strict_types=1);

namespace Pediment\Seeder;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class MediaSpec {
	public function __construct(
		public readonly string $key,
		public readonly string $file,
		public readonly string $title
	) {}
}
