<?php
/**
 * Navigation entities, resolved by (seed key, language) like everything else —
 * not by title or slug. A stray post holding the `primary` slug is what turned
 * replacements into `primary-2` (7d7ca30).
 *
 * @package Pediment
 */

declare(strict_types=1);

namespace Pediment\Seeder;

use Pediment\Language\LanguageProvider;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class NavSeeder {
	/** @var string[] Failures from the most recent apply(). */
	private array $errors = [];

	public function __construct( private LanguageProvider $lang ) {}

	/** @param array<string,int> $entryIds */
	public function plan( Manifest $manifest, array $entryIds ): Plan {
		$items    = [];
		$errors   = [];
		$existing = $this->existing();

		foreach ( $this->duplicates() as $mapKey => $duplicateIds ) {
			$errors[] = sprintf(
				'navs.%s is carried by %d navigation entities (IDs %s). Identity must be unique — delete or re-key the extras.',
				explode( '|', (string) $mapKey )[0],
				count( $duplicateIds ),
				implode( ', ', $duplicateIds )
			);
		}

		foreach ( $this->lang->languages() as $language ) {
			foreach ( $manifest->navs() as $key => $spec ) {
				$mapKey = $key . '|' . $language;
				$postId = (int) ( $existing[ $mapKey ] ?? 0 );

				if ( 0 === $postId ) {
					$items[] = new PlanItem(
						PlanItem::CREATE,
						PlanItem::KIND_NAV,
						$key,
						$language,
						0,
						[ 'items' => [ 'from' => 0, 'to' => self::countLinks( $spec->items ) ] ]
					);
					continue;
				}

				// A client who deleted the menu still owns the identity: without
				// this the trashed entity keeps its seed key, a second one is
				// created beside it, and the next run reports a duplicate — the
				// `primary-2` shape this class exists to prevent.
				if ( 'trash' === get_post_status( $postId ) ) {
					$items[] = new PlanItem(
						PlanItem::RESTORE,
						PlanItem::KIND_NAV,
						$key,
						$language,
						$postId,
						[ 'status' => [ 'from' => 'trash', 'to' => 'publish' ] ]
					);
					continue;
				}

				$current = (string) get_post( $postId )->post_content;
				$desired = $this->serialize( $spec, $language, $entryIds, $current, $postId );
				$items[] = $current === $desired
					? new PlanItem( PlanItem::UNCHANGED, PlanItem::KIND_NAV, $key, $language, $postId )
					: new PlanItem(
						PlanItem::UPDATE,
						PlanItem::KIND_NAV,
						$key,
						$language,
						$postId,
						[
							'items' => [
								// The `<!-- ` prefix on the submenu needle is deliberate: the
								// closing delimiter is `<!-- /wp:navigation-submenu -->`, and a
								// bare needle would match it too and double every submenu.
								// mega-menu is self-closing, so its needle has no such twin;
								// the full prefix is used for consistency.
								'from' => substr_count( $current, 'wp:navigation-link' )
									+ substr_count( $current, '<!-- wp:navigation-submenu' )
									+ substr_count( $current, '<!-- wp:pediment/mega-menu' ),
								'to'   => self::countLinks( $spec->items ),
							],
						],
						[],
						self::hasMega( $spec )
							? 'membership is git-owned; mega menu content is kept once edited in the editor'
							: 'membership is git-owned; editor changes to this menu are reverted'
					);
			}
		}

		return new Plan( $items, $errors );
	}

	/**
	 * @param array<string,int> $entryIds
	 * @return array<string,int> navKey|language => post ID
	 */
	public function apply( Plan $plan, Manifest $manifest, array $entryIds ): array {
		$this->errors = [];

		// Same contract as Applier::apply(): an errored plan writes nothing.
		if ( $plan->hasErrors() ) {
			$this->errors = $plan->errors();
			return $this->existing();
		}

		$ids = $this->existing();

		// Without this, wp_filter_post_kses() strips the escapes out of the
		// navigation-link JSON on save, the stored markup never matches a fresh
		// serialize(), and every nav is rewritten on every run.
		$kses = Kses::suspend();

		try {
			foreach ( $plan->byKind( PlanItem::KIND_NAV ) as $item ) {
				$spec = $manifest->navs()[ $item->key ] ?? null;
				if ( ! $spec instanceof NavSpec ) {
					continue;
				}

				// Checked for every item, including UNCHANGED ones: a menu that
				// quietly comes out short is worse than one that fails, and the
				// problem persists across runs even though the nav stops changing.
				$unresolved = $this->unresolvedEntries( $spec, $item->language, $entryIds );
				foreach ( $unresolved as $missing ) {
					$this->errors[] = sprintf( 'navs.%s: "%s" has no seeded post yet — the link is missing from the menu.', $spec->key, $missing );
				}

				// Never write a half-truth. serialize() skips a link it cannot
				// resolve, so writing here would REPLACE the live menu with a
				// shortened one — an entry whose create failed earlier in the same
				// run would silently delete a link from the header. Leave the
				// entity exactly as it is and let phase 5 report the mismatch.
				if ( [] !== $unresolved ) {
					continue;
				}

				if ( PlanItem::UNCHANGED === $item->action ) {
					continue;
				}

				if ( PlanItem::CREATE === $item->action ) {
					$content = $this->serialize( $spec, $item->language, $entryIds );
					$postId  = wp_insert_post(
						wp_slash(
							[
								'post_type'    => 'wp_navigation',
								'post_status'  => 'publish',
								'post_title'   => $spec->title,
								'post_name'    => $this->slugFor( $spec, $item->language ),
								'post_content' => $content,
							]
						),
						true
					);
					if ( is_wp_error( $postId ) ) {
						$this->errors[] = sprintf( 'navs.%s: could not create the navigation entity — %s', $spec->key, $postId->get_error_message() );
						continue;
					}
					$postId = (int) $postId;
					$this->lang->setLanguage( $postId, $item->language );
					update_post_meta( $postId, Meta::KEY, $spec->key );
					MegaBlocks::writeHashes( $postId, '' );
				} elseif ( PlanItem::RESTORE === $item->action ) {
					$postId  = $item->postId;
					$old     = (string) get_post( $postId )->post_content;
					$content = $this->serialize( $spec, $item->language, $entryIds, $old, $postId );
					// The slug is rewritten too: wp_trash_post() renames it to
					// `primary__trashed`, and leaving that behind is what a later
					// create would collide with.
					$restored = wp_update_post(
						wp_slash(
							[
								'ID'           => $postId,
								'post_status'  => 'publish',
								'post_name'    => $this->slugFor( $spec, $item->language ),
								'post_content' => $content,
							]
						),
						true
					);
					if ( is_wp_error( $restored ) ) {
						$this->errors[] = sprintf( 'navs.%s: could not restore the navigation entity %d — %s', $spec->key, $postId, $restored->get_error_message() );
						continue;
					}
					Meta::clearTrashBookkeeping( $postId );
					MegaBlocks::writeHashes( $postId, $old );
				} else {
					$postId  = $item->postId;
					$old     = (string) get_post( $postId )->post_content;
					$content = $this->serialize( $spec, $item->language, $entryIds, $old, $postId );
					$updated = wp_update_post( wp_slash( [ 'ID' => $postId, 'post_content' => $content ] ), true );
					if ( is_wp_error( $updated ) ) {
						$this->errors[] = sprintf( 'navs.%s: could not update the navigation entity — %s', $spec->key, $updated->get_error_message() );
						continue;
					}
					MegaBlocks::writeHashes( $postId, $old );
				}

				$ids[ $item->mapKey() ] = $postId;
			}

			$this->repairLanguage( $plan, $ids );
			$this->linkTranslationGroups( $ids );

			return $ids;
		} finally {
			Kses::restore( $kses );
		}
	}

	/** @return string[] Failures from the most recent apply(). */
	public function errors(): array {
		return $this->errors;
	}

	/** @param array<string,int> $entryIds */
	public function serialize( NavSpec $spec, string $language, array $entryIds, string $current = '', int $navId = 0 ): string {
		$blocks     = [];
		$storedMega = MegaBlocks::extract( $current );
		$megaHashes = 0 < $navId ? MegaBlocks::storedHashes( $navId ) : [];
		$megaIndex  = 0;

		foreach ( $spec->items as $item ) {
			$item = (array) $item;

			// Membership is git-owned, content is hash-arbitrated: the block
			// always (re)appears where the manifest says, but once a human
			// edits it in the editor its stored markup stops hashing to what
			// the seeder last wrote, and from then on it is spliced through
			// verbatim. Matching is positional: nth mega item, nth stored
			// block. See docs/seeding.md ("Mega menus").
			if ( isset( $item['mega'] ) ) {
				$stored   = $storedMega[ $megaIndex ] ?? null;
				$blocks[] = null !== $stored && ! MegaBlocks::gitOwns( $stored, (string) ( $megaHashes[ $megaIndex ] ?? '' ) )
					? $stored
					: $this->megaMarkup( (array) $item['mega'], $language, $entryIds );
				++$megaIndex;
				continue;
			}

			// A language switcher is a dynamic multilingual-plugin block, not a
			// link to a seeded post. The active provider owns the block name and
			// attribute shape (Polylang vs WPML); a monolingual site returns ''
			// and the switcher is simply omitted.
			if ( isset( $item['language_switcher'] ) ) {
				$switcher = $this->lang->languageSwitcherBlock( $item['language_switcher'] );
				if ( '' !== $switcher ) {
					$blocks[] = $switcher;
				}
				continue;
			}

			$attrs = $this->linkAttrs( $item, $language, $entryIds );

			// Reported by apply() via unresolvedEntries(), not from here:
			// serialize() must stay pure, and an unresolved link has to be
			// reported on EVERY run, not only the one that rewrites the nav.
			// A submenu parent takes its children with it — a submenu whose own
			// link is missing is not a menu anyone meant, and promoting the
			// children to the top level would silently restructure the header.
			if ( [] === $attrs ) {
				continue;
			}

			if ( ! isset( $item['children'] ) ) {
				$blocks[] = '<!-- wp:navigation-link ' . wp_json_encode( $attrs, JSON_UNESCAPED_SLASHES ) . ' /-->';
				continue;
			}

			$children = [];
			foreach ( (array) $item['children'] as $child ) {
				$childAttrs = $this->linkAttrs( (array) $child, $language, $entryIds );
				if ( [] === $childAttrs ) {
					continue;
				}
				$children[] = '<!-- wp:navigation-link ' . wp_json_encode( $childAttrs, JSON_UNESCAPED_SLASHES ) . ' /-->';
			}

			$blocks[] = '<!-- wp:navigation-submenu ' . wp_json_encode( $attrs, JSON_UNESCAPED_SLASHES ) . ' -->'
				. ( [] === $children ? "\n" : "\n" . implode( "\n", $children ) . "\n" )
				. '<!-- /wp:navigation-submenu -->';
		}

		return implode( "\n", $blocks );
	}

	/**
	 * The block attributes one navigation item resolves to.
	 *
	 * Key order is load-bearing, not cosmetic: plan() decides UPDATE by
	 * comparing stored post_content against a fresh serialize(), so reordering
	 * these would make every nav on every site rewrite itself once.
	 *
	 * JSON_UNESCAPED_SLASHES matches what the block editor writes, and keeps
	 * the markup stable under KSES, which strips `\/` on save.
	 *
	 * @param array<string,mixed> $item
	 * @param array<string,int>   $entryIds
	 * @return array<string,mixed> Empty when an entry item has no resolved post.
	 */
	private function linkAttrs( array $item, string $language, array $entryIds ): array {
		if ( ! isset( $item['entry'] ) ) {
			return [
				'label' => (string) $item['label'],
				'url'   => (string) $item['url'],
				'kind'  => 'custom',
			];
		}

		$postId = (int) ( $entryIds[ $item['entry'] . '|' . $language ] ?? 0 );
		if ( 0 === $postId ) {
			return [];
		}

		$post = get_post( $postId );

		return [
			'label' => (string) ( $item['label'] ?? ( $post ? $post->post_title : '' ) ),
			'type'  => $post ? $post->post_type : 'page',
			'id'    => $postId,
			'kind'  => 'post-type',
			'url'   => (string) get_permalink( $postId ),
		];
	}

	/**
	 * One `pediment/mega-menu` block from a manifest mega item.
	 *
	 * Key order is load-bearing for the same reason as linkAttrs(): label,
	 * columns; per column heading, icon, links; per link label, description,
	 * url — optional keys are omitted, never emitted empty. Entry links
	 * resolve through linkAttrs() (per-language permalink, label falls back
	 * to the post title); the resolver's id/kind/type keys are dropped
	 * because the block schema does not know them.
	 *
	 * @param array<string,mixed> $mega
	 * @param array<string,int>   $entryIds
	 */
	private function megaMarkup( array $mega, string $language, array $entryIds ): string {
		$columns = [];

		foreach ( (array) $mega['columns'] as $column ) {
			$column = (array) $column;
			$links  = [];

			foreach ( (array) $column['links'] as $link ) {
				$link  = (array) $link;
				$attrs = $this->linkAttrs( $link, $language, $entryIds );
				// Same contract as the top-level items: an unresolved entry is
				// dropped here and reported by apply() via unresolvedEntries(),
				// which also refuses to write the shortened menu.
				if ( [] === $attrs ) {
					continue;
				}

				$out = [ 'label' => (string) $attrs['label'] ];
				if ( isset( $link['description'] ) ) {
					$out['description'] = (string) $link['description'];
				}
				$out['url'] = (string) $attrs['url'];
				$links[]    = $out;
			}

			$col = [ 'heading' => (string) $column['heading'] ];
			if ( isset( $column['icon'] ) ) {
				$col['icon'] = (string) $column['icon'];
			}
			$col['links'] = $links;
			$columns[]    = $col;
		}

		$attrs = [
			'label'   => (string) $mega['label'],
			'columns' => $columns,
		];

		// Mega attrs carry client copy, and Gutenberg's JS serializeAttributes()
		// re-serializes EVERY block in the post whenever the nav is saved in the
		// Site Editor, not just the ones a human touched. serialize_block_attributes()
		// is core's PHP twin of that JS function (wp_json_encode with
		// JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE, then --/</>/&/\" each
		// rewritten to a \u escape) — matching it byte-for-byte is what keeps an
		// UNTOUCHED mega block's stored hash matching after an unrelated editor
		// save. wp_json_encode() alone would byte-shift any label/description
		// containing an umlaut, `&`, `"`, or `--`, and the block would be silently
		// captured as client-owned. The links/switcher lines below deliberately
		// keep plain wp_json_encode( …, JSON_UNESCAPED_SLASHES ): their byte format
		// is locked by existing sites, and the zero-mega golden test
		// (test_a_mega_free_manifest_serializes_exactly_as_before) enforces that.
		return '<!-- wp:pediment/mega-menu ' . serialize_block_attributes( $attrs ) . ' /-->';
	}

	/**
	 * Public because Claimer derives the same slug when matching a legacy
	 * navigation entity; the derivation must not exist twice.
	 */
	public function slugFor( NavSpec $spec, string $language ): string {
		return sanitize_title( $spec->key . ( '' !== $language ? '-' . $language : '' ) );
	}

	/**
	 * Put every language of a nav key into one translation group.
	 *
	 * Same rule as entries: pll_save_post_translations() replaces the whole
	 * group, so this runs once with the full map after every entity is written.
	 *
	 * Without it, a translated menu is invisible to pll_get_post(), the header's
	 * per-language lookup falls back to whichever nav was saved last, and every
	 * language renders one language's navigation — the outage this engine's nav
	 * identity model exists to prevent.
	 *
	 * Known consequence, not a bug fixed here: keyed() detects a nav's language
	 * through languageOf(), which matches it against $this->lang->languages() —
	 * the currently configured set — so a nav that still carries a language
	 * term naming a language no longer configured matches no candidate and is
	 * filed under the empty-language key (`key|''`). (A language dropped
	 * through Polylang's own delete path does NOT reach this state; it leaves
	 * the nav untagged, which languageOf() resolves to the default language.
	 * See languageOf()'s docblock.) The guard just above
	 * (`'' === $language`) then excludes it from every map handed to
	 * linkTranslations(). Its row is untouched, still carrying the dropped
	 * language in Polylang's own post-language term, but the still-configured
	 * siblings under the same key get relinked here on every run, and
	 * pll_save_post_translations() REPLACES the whole group: Polylang's own
	 * save_translations() diffs the new map against the group's current
	 * membership and deletes anything absent from it, so the dropped-language
	 * nav is unlinked from its group site-wide even though nothing about its
	 * row changed. Same mechanism Applier::linkTranslationGroups() documents
	 * for entries (there: an ORPHAN plan item excluded from $ids), reached
	 * here by a different route (an empty-language bucket, not a plan
	 * action). See docs/BACKLOG.md (Medium, step-4 Task 10 review).
	 *
	 * @param array<string,int> $ids navKey|language => post ID
	 */
	private function linkTranslationGroups( array $ids ): void {
		$languages = $this->lang->languages();
		if ( count( $languages ) < 2 ) {
			return;
		}

		$byKey = [];
		foreach ( $ids as $mapKey => $postId ) {
			// array_pad(explode('|', ...), 2, '') does not truncate if a nav key
			// or language code ever contained '|' itself — not reachable today
			// (both come from manifest identifiers), same caveat as
			// Applier::linkTranslationGroups().
			[ $key, $language ] = array_pad( explode( '|', (string) $mapKey ), 2, '' );
			if ( '' === $language || $postId <= 0 ) {
				continue;
			}
			$byKey[ $key ][ $language ] = $postId;
		}

		foreach ( $byKey as $map ) {
			// Same fix as Applier::linkTranslationGroups(), applied here
			// separately by explicit decision (see this class's own top
			// docblock): a map missing even one configured language must
			// never reach linkTranslations(), or an ordinary write failure
			// (one language's create errors while the others succeed)
			// silently unlinks whichever language got left out of a group
			// that already existed. count($map) >= 2 let a 2-of-3 map
			// through; only "covers every configured language" is safe. This
			// does not change the documented empty-language-bucket
			// consequence above: there, $languages shrinks along with the
			// map, so the count still matches.
			if ( count( $map ) !== count( $languages ) ) {
				continue;
			}
			$this->lang->linkTranslations( $map );
		}
	}

	/**
	 * Entry keys this nav references that have no seeded post yet.
	 *
	 * @param array<string,int> $entryIds
	 * @return string[]
	 */
	private function unresolvedEntries( NavSpec $spec, string $language, array $entryIds ): array {
		$missing = [];
		foreach ( $spec->items as $item ) {
			$missing = array_merge( $missing, $this->unresolvedItem( (array) $item, $language, $entryIds ) );
		}
		return $missing;
	}

	/**
	 * @param array<string,mixed> $item
	 * @param array<string,int>   $entryIds
	 * @return string[]
	 */
	private function unresolvedItem( array $item, string $language, array $entryIds ): array {
		$missing = [];

		if ( isset( $item['entry'] ) && 0 === (int) ( $entryIds[ $item['entry'] . '|' . $language ] ?? 0 ) ) {
			$missing[] = (string) $item['entry'];
		}

		foreach ( (array) ( $item['children'] ?? [] ) as $child ) {
			$missing = array_merge( $missing, $this->unresolvedItem( (array) $child, $language, $entryIds ) );
		}

		foreach ( (array) ( ( (array) ( $item['mega'] ?? [] ) )['columns'] ?? [] ) as $column ) {
			foreach ( (array) ( ( (array) $column )['links'] ?? [] ) as $link ) {
				$missing = array_merge( $missing, $this->unresolvedItem( (array) $link, $language, $entryIds ) );
			}
		}

		return $missing;
	}

	/** Whether any of the spec's items declares a mega menu. */
	private static function hasMega( NavSpec $spec ): bool {
		foreach ( $spec->items as $item ) {
			if ( isset( ( (array) $item )['mega'] ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Total links a nav spec describes, counting submenu parents and children.
	 *
	 * The plan's `items` change is operator-facing arithmetic, so it has to
	 * count the same things on both sides: `count( $spec->items )` would report
	 * a two-level menu as its top-level width and read as a shrink.
	 *
	 * A mega item counts as 1: its links are JSON attributes, invisible to the
	 * needles plan() counts on the stored side.
	 *
	 * @param array<int,array<string,mixed>> $items
	 */
	private static function countLinks( array $items ): int {
		$count = 0;
		foreach ( $items as $item ) {
			// A language switcher is not a link; skip it so this count stays
			// symmetric with plan()'s `from` tally (which counts only
			// navigation-link/-submenu markup in the stored nav).
			if ( isset( ( (array) $item )['language_switcher'] ) ) {
				continue;
			}
			++$count;
			$count += self::countLinks( (array) ( ( (array) $item )['children'] ?? [] ) );
		}
		return $count;
	}

	/** @return array<string,int> navKey|language => post ID */
	private function existing(): array {
		$ids = [];
		foreach ( $this->keyed() as $mapKey => $postIds ) {
			$ids[ $mapKey ] = $postIds[0];
		}
		return $ids;
	}

	/** @return array<string,int[]> navKey|language => post IDs carrying that identity, only where there is more than one. */
	private function duplicates(): array {
		return array_filter( $this->keyed(), static fn( array $postIds ): bool => count( $postIds ) > 1 );
	}

	/**
	 * Public because Claimer needs the same already-claimed lookup to skip a
	 * nav that already carries its key when planning claims; the query must
	 * not exist twice.
	 *
	 * @return array<string,int[]> navKey|language => every post ID carrying that identity.
	 */
	public function keyed(): array {
		$ids = [];
		foreach (
			get_posts(
				$this->lang->unscopedQuery(
					[
						'post_type'      => 'wp_navigation',
						// Trashed entities still hold their seed key: ignoring them
						// would create a second nav under one identity.
						'post_status'    => [ 'publish', 'draft', 'trash' ],
						'posts_per_page' => -1,
						'no_found_rows'  => true,
						'orderby'        => 'ID',
						'order'          => 'ASC',
						// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- seed identity lookup.
						'meta_key'       => Meta::KEY,
						'meta_compare'   => 'EXISTS',
					]
				)
			) as $nav
		) {
			$key = (string) get_post_meta( $nav->ID, Meta::KEY, true );
			if ( '' === $key ) {
				continue;
			}
			$ids[ $key . '|' . $this->languageOf( (int) $nav->ID ) ][] = (int) $nav->ID;
		}
		return $ids;
	}

	/**
	 * Which language bucket a keyed navigation entity belongs in.
	 *
	 * Two distinct states used to collapse into the same empty-language bucket,
	 * and only one of them belongs there:
	 *
	 * - **Untagged.** A nav that carries no Polylang language term at all. On
	 *   admin-only hosting that is the DEFAULT state, not a corner case:
	 *   `wp_navigation` is made translatable only by `PolylangSetup::configure()`
	 *   via `wp pediment languages`, which has no wp-admin front door, so every
	 *   legacy menu on such a site is untagged. `Claimer` deliberately claims an
	 *   untagged nav for the default language (`Claimer::languageMatches()`) and
	 *   writes only the seed key. Filing it under `key|''` meant the next seed
	 *   found no `key|<default>`, emitted CREATE, and wrote a SECOND entity
	 *   carrying the same key — invisible to `duplicates()`, which bucketed the
	 *   two apart. `StateReader::languageOf()` already resolves an untagged page
	 *   to the default language; this is the same rule for navs.
	 * - **Tagged with a language that is no longer configured.** Still `''`. This
	 *   is the case `linkTranslationGroups()`'s docblock discusses, and it is
	 *   left exactly as it was — a nav that really does carry a language term
	 *   outside the configured set is not the default language, and calling it
	 *   one would hand the default language's seed a foreign-language row to
	 *   overwrite.
	 *
	 * Note that DROPPING a language in Polylang does not produce the second
	 * state: `Languages::delete()` deletes the language term itself, and
	 * `TranslatableObject::delete_language()` calls
	 * `wp_delete_object_term_relationships()`, so the nav is left genuinely
	 * untagged and `pll_get_post_language()` returns `false` (verified against
	 * Polylang in this repo's test environment). Such a nav therefore lands in
	 * the default-language bucket from here on. That is forced, not chosen:
	 * once Polylang has deleted the term there is nothing left in the database
	 * that distinguishes it from a legacy nav that was never tagged, and the
	 * legacy nav is the case that must work. It matches how entries have always
	 * behaved — `StateReader::languageOf()` resolves an untagged post to the
	 * default language and `Applier::repairLanguage()` tags it — so navs stop
	 * being the outlier. See docs/BACKLOG.md.
	 */
	private function languageOf( int $postId ): string {
		foreach ( $this->lang->languages() as $candidate ) {
			if ( $this->lang->translationOf( $postId, $candidate ) === $postId ) {
				return $candidate;
			}
		}

		return $this->lang->hasLanguage( $postId ) ? '' : $this->lang->defaultLanguage();
	}

	/**
	 * Tag a pre-existing navigation entity that has no language at all.
	 *
	 * The nav-side mirror of `Applier::repairLanguage()`, needed for the same
	 * migration case and reached the same way: a claimed legacy nav carries the
	 * seed key and no language term, so without this it would stay untagged
	 * forever — invisible to `pll_get_post()`, and to the per-language header
	 * lookup in `inc/nav-language.php`. A CREATE is skipped because
	 * `apply()` already tagged it in the same write. `hasLanguage()` is the only
	 * check: a nav that already carries a (possibly different) language is left
	 * exactly as it is, because re-tagging it is an editorial decision.
	 *
	 * Runs before `linkTranslationGroups()`: `pll_save_post_translations()` can
	 * only put a post in a group once that post has a language.
	 *
	 * A no-op on a monolingual site — `NullProvider::hasLanguage()` is always
	 * true.
	 *
	 * @param array<string,int> $ids navKey|language => post ID
	 */
	private function repairLanguage( Plan $plan, array $ids ): void {
		foreach ( $plan->byKind( PlanItem::KIND_NAV ) as $item ) {
			if ( PlanItem::CREATE === $item->action || '' === $item->language ) {
				continue;
			}
			$postId = (int) ( $ids[ $item->mapKey() ] ?? 0 );
			if ( $postId <= 0 ) {
				continue;
			}
			if ( ! $this->lang->hasLanguage( $postId ) ) {
				$this->lang->setLanguage( $postId, $item->language );
			}
		}
	}
}
