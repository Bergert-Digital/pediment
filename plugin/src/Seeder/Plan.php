<?php
declare(strict_types=1);

namespace Pediment\Seeder;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Plan {
	/**
	 * @param PlanItem[] $items
	 * @param string[]   $errors Fatal problems; nothing is applied while any exist.
	 */
	public function __construct( private array $items = [], private array $errors = [] ) {}

	/** @return PlanItem[] */
	public function items(): array {
		return $this->items;
	}

	/** @return string[] */
	public function errors(): array {
		return $this->errors;
	}

	public function hasErrors(): bool {
		return [] !== $this->errors;
	}

	public function isEmpty(): bool {
		foreach ( $this->items as $item ) {
			if ( $item->writes() ) {
				return false;
			}
		}
		return true;
	}

	/** @return PlanItem[] */
	public function byAction( string $action ): array {
		return array_values( array_filter( $this->items, static fn( PlanItem $i ): bool => $i->action === $action ) );
	}

	/** @return PlanItem[] */
	public function byKind( string $kind ): array {
		return array_values( array_filter( $this->items, static fn( PlanItem $i ): bool => $i->kind === $kind ) );
	}

	/** @return array<string,int> */
	public function counts(): array {
		$counts = [];
		foreach ( $this->items as $item ) {
			$counts[ $item->action ] = ( $counts[ $item->action ] ?? 0 ) + 1;
		}
		return $counts;
	}

	public static function merge( Plan ...$plans ): Plan {
		$items  = [];
		$errors = [];
		foreach ( $plans as $plan ) {
			$items  = array_merge( $items, $plan->items() );
			$errors = array_merge( $errors, $plan->errors() );
		}
		return new self( $items, $errors );
	}
}
