<?php
/**
 * Phase 3: desired state x actual state -> a plan.
 *
 * The whole arbitration rule lives here and nowhere else:
 *
 *   1. no actual row                                  -> create
 *   2. stored hash absent / foreign / != current hash -> the client edited it:
 *      never touch title or content, enforce structure only
 *   3. otherwise                                      -> write content when the
 *      source hash shows git changed, else leave it
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
						'parent'     => [ 'from' => null, 'to' => $want->parentKey ],
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

			$edited    = ! ContentHash::matches( $have->storedHash, $have->currentHash );
			$changes   = [];
			$protected = [];

			if ( $edited ) {
				if ( $have->title !== $want->title ) {
					$protected['title'] = [ 'from' => $have->title, 'to' => $want->title ];
				}
				if ( $want->sourceHash !== $have->sourceHash ) {
					$protected['content'] = [ 'from' => '(database)', 'to' => '(manifest)' ];
				}
			} elseif ( '' === $have->sourceHash || $have->sourceHash !== $want->sourceHash ) {
				// Untouched by the client and git moved (or provenance is
				// unknown but nobody edited it, so writing is safe).
				if ( $have->title !== $want->title ) {
					$changes['title'] = [ 'from' => $have->title, 'to' => $want->title ];
				}
				$changes['content'] = [ 'from' => '(database)', 'to' => $want->content ];
			}

			if ( $have->slug !== $want->slug ) {
				$changes['slug'] = [ 'from' => $have->slug, 'to' => $want->slug ];
			}
			if ( $have->menuOrder !== $want->menuOrder ) {
				$changes['menu_order'] = [ 'from' => $have->menuOrder, 'to' => $want->menuOrder ];
			}

			$wantParentId = null;
			if ( null !== $want->parentKey ) {
				$parentActual = $actual[ $want->parentKey . '|' . $want->language ] ?? null;
				$wantParentId = $parentActual instanceof ActualEntry ? $parentActual->id : null;
			}
			$parentDiffers = ( null === $want->parentKey && 0 !== $have->parentId )
				|| ( null !== $want->parentKey && ( null === $wantParentId || $have->parentId !== $wantParentId ) );
			if ( $parentDiffers ) {
				// Named `parent_key`, not `parent`, because the values are a seed
				// key and a post ID — descriptive, not directly writable. The
				// applier resolves the real post_parent from the desired entry.
				$changes['parent_key'] = [ 'from' => $have->parentId, 'to' => $want->parentKey ];
			}

			// Only `trash` is restored. `draft` and `pending` are editorial states
			// — a client taking a page offline or holding a revision for review
			// must not be overruled by the next seed run.
			$restoring = 'trash' === $have->status;
			if ( $restoring ) {
				$changes['status'] = [ 'from' => $have->status, 'to' => 'publish' ];
			}

			$action = PlanItem::UNCHANGED;
			$note   = '';
			if ( $restoring ) {
				$action = PlanItem::RESTORE;
			} elseif ( [] !== $changes ) {
				$action = PlanItem::UPDATE;
			} elseif ( [] !== $protected ) {
				$action = PlanItem::PROTECTED;
			}
			if ( [] !== $protected ) {
				// Three different situations reach rule 2, and telling an operator
				// "the client edited this" about a database the seeder has simply
				// never stamped is the difference between running `wp pediment
				// adopt` and concluding the whole site was hand-edited.
				if ( '' === $have->storedHash ) {
					$note = 'never seeded by this engine — content left alone; run `wp pediment adopt` to take it into git';
				} elseif ( ! str_starts_with( $have->storedHash, ContentHash::VERSION . ':' ) ) {
					$note = 'seeded by an older hash version — content left alone; re-adopt to refresh it';
				} else {
					$note = 'edited in the editor — content and title left alone';
				}
			}

			$items[] = new PlanItem(
				$action,
				PlanItem::KIND_ENTRY,
				$want->key,
				$want->language,
				$have->id,
				$changes,
				$protected,
				$note
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
				[],
				sprintf( '"%s" (ID %d) carries a seed key the manifest no longer declares — left in place', $have->title, $have->id )
			);
		}

		return new Plan( $items, $errors );
	}
}
