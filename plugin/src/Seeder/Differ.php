<?php
/**
 * Phase 3: desired state x actual state -> a plan.
 *
 * The seeder bootstraps a site; it never maintains one. Per desired entry:
 *
 *   1. no actual row  -> create
 *   2. a row exists   -> unchanged, whatever a person or an MCP tool did to
 *                        its title, content, slug, parent, menu order or
 *                        status (a trashed row stays in the trash, with a note)
 *
 * Rows the manifest no longer declares are reported as orphans and left in
 * place. Duplicate identities and post_type mismatches block the whole run.
 *
 * @package Pediment
 */

declare(strict_types=1);

namespace Pediment\Seeder;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Differ {
	/**
	 * @param array<string,DesiredEntry> $desired
	 * @param array<string,ActualEntry>  $actual
	 * @param array<string,int[]>        $duplicates
	 */
	public function diff( array $desired, array $actual, array $duplicates = [] ): Plan {
		$items  = [];
		$errors = [];

		foreach ( $duplicates as $mapKey => $ids ) {
			[ $duplicateKey, $duplicateLanguage ] = array_pad( explode( '|', (string) $mapKey, 2 ), 2, '' );
			$errors[]                             = sprintf(
				'Seed key "%s"%s is carried by %d posts (IDs %s). Identity must be unique — delete or re-key the extras before seeding.',
				$duplicateKey,
				'' === $duplicateLanguage ? '' : sprintf( ' (language "%s")', $duplicateLanguage ),
				count( $ids ),
				implode( ', ', $ids )
			);
		}

		foreach ( $desired as $mapKey => $want ) {
			$have = $actual[ $mapKey ] ?? null;

			if ( null === $have ) {
				$items[] = new PlanItem(
					PlanItem::CREATE,
					PlanItem::KIND_ENTRY,
					$want->key,
					$want->language,
					0,
					[
						'title'      => [ 'from' => null, 'to' => $want->title ],
						'content'    => [ 'from' => null, 'to' => $want->content ],
						'slug'       => [ 'from' => null, 'to' => $want->slug ],
						'parent_key' => [ 'from' => null, 'to' => $want->parentKey ],
						'status'     => [ 'from' => null, 'to' => 'publish' ],
						'menu_order' => [ 'from' => null, 'to' => $want->menuOrder ],
					]
				);
				continue;
			}

			if ( $have->postType !== $want->postType ) {
				$errors[] = sprintf(
					'Seed key "%s" is a %s in the database but a %s in the manifest (post ID %d). post_type is never rewritten — re-key one of them.',
					$mapKey,
					$have->postType,
					$want->postType,
					$have->id
				);
				continue;
			}

			// Restoring a trashed row would be a write to a row that exists;
			// creating a second one would duplicate its identity. Neither: say
			// where it is and what makes the next seed create it again.
			$items[] = new PlanItem(
				PlanItem::UNCHANGED,
				PlanItem::KIND_ENTRY,
				$want->key,
				$want->language,
				$have->id,
				[],
				'trash' === $have->status
					? 'in the trash — left alone; restore it in the editor, or empty the trash and the next seed creates it again'
					: ''
			);
		}

		foreach ( $actual as $mapKey => $have ) {
			if ( isset( $desired[ $mapKey ] ) ) {
				continue;
			}
			$items[] = new PlanItem(
				PlanItem::ORPHAN,
				PlanItem::KIND_ENTRY,
				$have->key,
				$have->language,
				$have->id,
				[],
				sprintf( '"%s" (ID %d) carries a seed key the manifest no longer declares — left in place', $have->title, $have->id )
			);
		}

		return new Plan( $items, $errors );
	}
}
