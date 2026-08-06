<?php
declare(strict_types=1);

namespace Pediment\Seeder;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class NavSpec {
	/** @param array<int,array{entry?:string,url?:string,label?:string,children?:array<int,array{entry?:string,url?:string,label?:string}>}> $items */
	public function __construct(
		public readonly string $key,
		public readonly string $title,
		public readonly array $items
	) {}
}
