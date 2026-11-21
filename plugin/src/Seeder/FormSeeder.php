<?php
/**
 * Seeds the manifest's `forms` section: e-mail form destinations and the
 * default destination.
 *
 * Bootstrap-only, like the rest of the engine. A destination is created once,
 * identified by the `seed_key` it carries in the destinations option; after
 * that it belongs to the site owner, who changes its recipients under
 * Settings → Pediment → Form Destinations. The default destination is filled
 * only while the site has none that resolves.
 *
 * @package Pediment
 */

declare(strict_types=1);

namespace Pediment\Seeder;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class FormSeeder {
	/** Plan key of the default-destination item; seed keys cannot contain ':'. */
	public const DEFAULT_KEY = 'default:destination';

	/** @var string[] Failures from the most recent apply(). */
	private array $errors = [];

	public function plan( Manifest $manifest ): Plan {
		$items    = [];
		$errors   = [];
		$existing = $this->existing();

		foreach ( $this->duplicates() as $key => $ids ) {
			$errors[] = sprintf(
				'forms.destinations.%s is carried by %d form destinations (IDs %s). Identity must be unique — delete the extras under Settings → Pediment → Form Destinations.',
				$key,
				count( $ids ),
				implode( ', ', $ids )
			);
		}

		foreach ( $manifest->formDestinations() as $key => $record ) {
			if ( isset( $existing[ $key ] ) ) {
				$items[] = new PlanItem( PlanItem::UNCHANGED, PlanItem::KIND_FORM, $key, '', 0, [], 'destination ' . $existing[ $key ] . ' — recipients belong to the site owner' );
				continue;
			}
			$items[] = new PlanItem(
				PlanItem::CREATE,
				PlanItem::KIND_FORM,
				$key,
				'',
				0,
				[ 'to' => [ 'from' => null, 'to' => implode( ', ', $this->recipients( $record ) ) ] ]
			);
		}

		$default = $manifest->formDefault();
		if ( '' !== $default && '' === pediment_form_resolve_destination_id( '' ) ) {
			$items[] = new PlanItem(
				PlanItem::CREATE,
				PlanItem::KIND_FORM,
				self::DEFAULT_KEY,
				'',
				0,
				[ 'default' => [ 'from' => null, 'to' => $default ] ]
			);
		}

		return new Plan( $items, $errors );
	}

	public function apply( Plan $plan, Manifest $manifest ): void {
		$this->errors = [];
		$declared     = $manifest->formDestinations();

		foreach ( $plan->byKind( PlanItem::KIND_FORM ) as $item ) {
			if ( PlanItem::CREATE !== $item->action || self::DEFAULT_KEY === $item->key || ! isset( $declared[ $item->key ] ) ) {
				continue;
			}
			$record = $declared[ $item->key ];
			$dest   = [
				'id'             => pediment_form_next_destination_id(),
				'type'           => 'email',
				'seed_key'       => $item->key,
				'label'          => (string) ( $record['label'] ?? $item->key ),
				'to'             => $this->recipients( $record ),
				'subject'        => (string) ( $record['subject'] ?? '' ),
				'reply_to_field' => (string) ( $record['reply_to_field'] ?? '' ),
				'body_template'  => (string) ( $record['body_template'] ?? '' ),
				'attach_files'   => $record['attach_files'] ?? true,
			];
			$problems = pediment_form_validate_destination( $dest );
			if ( [] !== $problems ) {
				$this->errors[] = sprintf( 'forms.destinations.%s: %s', $item->key, implode( ' ', $problems ) );
				continue;
			}
			pediment_form_save_destination( $dest );
		}

		$default = $manifest->formDefault();
		if ( '' !== $default && '' === pediment_form_resolve_destination_id( '' ) ) {
			$id = $this->existing()[ $default ] ?? '';
			if ( '' !== $id ) {
				update_option( PEDIMENT_FORM_DEFAULT_DEST_OPTION, $id );
			}
		}
	}

	/** @return string[] */
	public function errors(): array {
		return $this->errors;
	}

	/**
	 * Every declared destination exists, and a declared default resolves.
	 *
	 * @return string[]
	 */
	public function verify( Manifest $manifest ): array {
		$problems = [];
		$existing = $this->existing();
		foreach ( array_keys( $manifest->formDestinations() ) as $key ) {
			if ( ! isset( $existing[ $key ] ) ) {
				$problems[] = sprintf( 'forms.destinations.%s: no form destination carries this seed key.', $key );
			}
		}
		if ( '' !== $manifest->formDefault() && '' === pediment_form_resolve_destination_id( '' ) ) {
			$problems[] = 'forms.default_destination: the site has no default form destination.';
		}
		return $problems;
	}

	/**
	 * Recipients for a declared destination: the manifest's, or the site's
	 * admin e-mail as a placeholder until the owner sets the real ones.
	 *
	 * @param array<string,mixed> $record Declared destination.
	 * @return string[]
	 */
	private function recipients( array $record ): array {
		if ( array_key_exists( 'to', $record ) ) {
			return pediment_form_split_recipients( $record['to'] );
		}
		return [ (string) get_option( 'admin_email' ) ];
	}

	/** @return array<string,string> seed key => destination id (first one wins) */
	private function existing(): array {
		$out = [];
		foreach ( $this->keyed() as $key => $ids ) {
			$out[ $key ] = $ids[0];
		}
		return $out;
	}

	/** @return array<string,string[]> */
	private function duplicates(): array {
		return array_filter( $this->keyed(), static fn( array $ids ): bool => count( $ids ) > 1 );
	}

	/** @return array<string,string[]> seed key => destination ids */
	private function keyed(): array {
		$stored = get_option( PEDIMENT_FORM_DESTINATIONS_OPTION, [] );
		$keyed  = [];
		foreach ( is_array( $stored ) ? $stored : [] as $dest ) {
			$key = is_array( $dest ) ? (string) ( $dest['seed_key'] ?? '' ) : '';
			if ( '' !== $key ) {
				$keyed[ $key ][] = (string) ( $dest['id'] ?? '' );
			}
		}
		return $keyed;
	}
}
