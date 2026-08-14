# Mega menus: seeded from the manifest, content editable in the editor

Date: 2026-08-14
Status: approved design (option "A2" — hash-arbitrated ownership)

## Problem

Client sites treat nav membership as git-owned: `NavSeeder` rewrites every
seeded `wp_navigation` entity whenever its stored `post_content` differs from a
fresh `serialize()` of the manifest spec. The manifest's nav-item grammar knows
page `entry` links, custom `label`+`url` links, one level of `children`, and
`language_switcher` — there is no way to express a `pediment/mega-menu` block.
Consequences:

1. A mega menu added in the Site Editor is wiped by the next seed run — mega
   menus and seeding are mutually exclusive on seeded sites.
2. Before the plugin migration, sites hand-built mega menus in the Site Editor
   and the edits persisted. That workflow is gone on seeded sites.

Both are fixed here: clients declare mega menus in `seed/manifest.php` like any
other nav item, **and** the mega block's content becomes editable in wp-admin
without being reverted — the same two-regime ownership contract seeded pages
already have (`docs/seeding.md`, "The two hashes").

## Ownership model (the decision this spec exists to record)

- **Membership stays git-owned.** Which items a nav contains, their order, and
  whether a mega menu exists at a given position all come from the manifest.
  Deleting the mega block in the editor, adding a second one, or reordering the
  nav is reverted on the next seed, exactly like every other nav item.
- **Mega content is hash-arbitrated, mirroring pages.** The seeder stores a
  hash of each mega block's markup as it last wrote it. As long as the stored
  block still matches that hash, git owns the content and manifest changes flow
  through on re-seed. The moment a human edits the block in the editor, the
  hash stops matching and the seeder preserves the stored block verbatim from
  then on. Re-asserting git = delete the block in the editor and re-seed
  (membership git-ownership re-creates it from the manifest, re-hashed).
- No git-side source hash is needed for navs: `plan()`'s byte comparison of
  stored content against a fresh `serialize()` already detects git changes;
  the row-side hash alone decides who owns each block.

Rejected alternatives, for the record: keeping full git ownership (the client
cannot edit; contradicts the requirement), editor-owned-forever after first
seed (git iteration on the menu would silently stop applying — wrong for the
developer-driven first consumer, pediment-website), and unmanaged navs
(abandons membership git-ownership entirely).

## Manifest grammar

A `mega` discriminator key, parallel to `language_switcher`:

```php
'items' => array(
    array( 'entry' => 'features' ),
    array(
        'mega' => array(
            'label'   => 'Products',
            'columns' => array(
                array(
                    'heading' => 'Banking',
                    'icon'    => 'bank', // optional pediment icon slug
                    'links'   => array(
                        array( 'entry' => 'features', 'description' => 'Everyday account' ),
                        array( 'label' => 'Savings', 'url' => '/savings/', 'description' => 'Earn more' ),
                    ),
                ),
            ),
        ),
    ),
),
```

Validation happens at parse time in `Manifest::navItem()`, in the existing
"rejecting rather than dropping it quietly" style, with exact operator paths:

- `mega.label`: required non-empty string.
- `mega.columns`: required non-empty array. Each column: `heading` required
  string, `icon` optional string, `links` required non-empty array.
- Each link: `entry` XOR `label`+`url` — the same rule as top-level items,
  same error wording; `entry` must name a declared entry. `description` is
  optional. Error paths reach the leaf:
  `navs.primary.items.1.mega.columns.0.links.2: needs either 'entry' or both 'url' and 'label'.`
- A `mega` item is a leaf: `children` on it is rejected, and `mega` inside
  `children` is rejected (the `$allowChildren === false` branch), each with a
  precise `ManifestError`.
- `mega` combined with `entry`, `url`, `label`, or `language_switcher` on the
  same item is rejected — one discriminator per item.

## Serialization

`NavSeeder::serialize()` emits, per mega item:

```
<!-- wp:pediment/mega-menu {"label":"Products","columns":[{"heading":"Banking","icon":"bank","links":[{"label":"Features","description":"Everyday account","url":"https://…/features/"}]}]} /-->
```

- **Fixed key order, load-bearing** (same contract as `linkAttrs()`): top level
  `label`, `columns`; per column `heading`, `icon`, `links` (omit `icon` when
  not declared — never emit an empty placeholder); per link `label`,
  `description`, `url` (omit `description` when not declared).
  **Amended during final review:** mega attrs are encoded with core's
  `serialize_block_attributes()`, not bare
  `wp_json_encode( …, JSON_UNESCAPED_SLASHES )` as this spec originally said.
  Gutenberg re-serializes every block on any nav save (raw UTF-8; `&`, `"`,
  `--`, `<`, `>` hex-escaped), so only core's serializer keeps an *untouched*
  block byte-identical across editor saves — with the original encoding, an
  umlaut or ampersand in the copy would flip the block client-owned without
  any human edit. Links/switcher deliberately keep `wp_json_encode`: their
  byte format is locked by existing sites (zero-mega golden).
- Entry links reuse `linkAttrs()` for per-language resolution (post ID →
  permalink, label defaults to the post title), then map to the block's link
  shape: `label`, `description` (from the manifest), `url` (the resolved
  permalink). The resolver's `id`/`kind`/`type` keys are dropped — the block
  schema (`plugin/src/blocks/mega-menu/block.json`) does not know them.
- Unresolved entry links are dropped from the markup and reported through
  `unresolvedEntries()`, which recurses into mega columns. The existing
  "never write a half-truth" guard in `apply()` (skip the whole nav write when
  anything is unresolved) covers mega entries with no extra code.
- `serialize()` gains the nav's stored content and post ID as inputs (0/empty
  for CREATE) so it can arbitrate per block — see below. It stays a
  deterministic function of its inputs; it already reads the database
  (`get_post()`, `get_permalink()`), so reading the hash meta is not a purity
  change.

## Arbitration mechanics

Stored mega blocks are extracted **verbatim** from the current `post_content`
as self-closing block comments (`<!-- wp:pediment/mega-menu … /-->`) — never
parsed and re-serialized, which would break byte stability. Matching is
positional: the nth manifest mega item corresponds to the nth stored mega
block. Per position:

1. **No stored block** → emit manifest markup. (First seed, or the client
   deleted it — membership is git-owned, so it comes back.)
2. **Stored block's hash matches the last-seeded hash** → untouched → emit
   manifest markup. (Git updates flow through; if the manifest is also
   unchanged this reproduces the stored bytes and the nav plans UNCHANGED.)
3. **Hash missing, foreign version, or mismatched** → the client edited it →
   splice the stored markup in verbatim.

The hash lives in one new meta key on the nav entity:

- `Meta::MEGA_HASH` = `_pediment_seed_mega_hash` — a JSON array of versioned
  hashes (the `ContentHash::VERSION . ':' . sha256` shape), one per mega
  position, in nav order. Written by `apply()` on every write of that nav
  (CREATE / RESTORE / UPDATE), **per position**: a position emitted from the
  manifest (regimes 1 and 2) gets a fresh hash of the markup as written; a
  position spliced in verbatim because the client owns it (regime 3) carries
  its existing stored hash entry forward unchanged, so it keeps mismatching
  and stays client-owned. This is the nav-side twin of the page rule "an
  update on a client-edited page must leave the arbitration hash alone"
  (`Applier.php`) — without it, any membership-driven rewrite would re-hash
  the client's edited markup, flip the block back to "untouched", and the next
  manifest change would silently overwrite the client's edits. Never written
  for navs whose manifest has no mega items.

Properties this buys, all inherited from the page contract:

- A claimed legacy nav has no hash → its existing mega blocks are preserved on
  first seed ("a claimed row's very first seed is safe").
- A `ContentHash::VERSION` bump makes every stored hash foreign → falls back
  to "treat as edited", never a silent overwrite.
- `Verifier` keeps passing: it re-serializes with the same stored
  content/ID inputs, so preserved blocks compare equal by construction.

**Known edge, documented not solved:** matching is positional, so swapping two
mega items *between* each other in the manifest transfers their
edit-ownership. One mega menu per nav is the overwhelmingly common case.

## Plan and counts

- `plan()` passes the stored content/ID into `serialize()`; the byte
  comparison then does the right thing in every regime.
- Items tally symmetry: `from` adds
  `substr_count( $current, '<!-- wp:pediment/mega-menu' )` (the `<!-- ` prefix
  trick is unnecessary for a self-closing block — there is no closing
  delimiter — but the full-prefix needle is used anyway for consistency);
  `to`: `countLinks()` counts a mega item as **1**. Inner mega links are JSON
  attributes, invisible to the `wp:navigation-link` needle, so 1-per-item is
  symmetric by construction.
- The UPDATE plan note becomes: *"membership is git-owned; mega menu content
  is kept once edited in the editor"* (only for navs with mega items; the
  existing note stays for the rest — no wording churn on unaffected sites).

## KSES

`apply()` already suspends KSES around nav writes. Mega attributes are nested
JSON containing user copy; a test must prove a saved mega nav round-trips
byte-identical to `serialize()` (quotes, entities, non-ASCII, slashes in
URLs), or every mega nav rewrites on every run.

## Compatibility

- A manifest with zero mega items serializes **byte-identically** to today's
  output and writes no new meta — existing client sites plan UNCHANGED after
  upgrading. Locked by a golden test.
- The editor side needs no work: `edit.tsx` exists, the block declares
  `"parent": ["core/navigation"]`, and `inc/mega-menu.php` handles render-time
  nav integration. The seeder was the only thing wiping edits.
- `serialize()`'s signature change is internal; `Verifier` (~line 150) is the
  only other caller and is updated in the same change.

## Tests

In `tests/phpunit`, next to the existing seeder and `MegaMenu/` tests:

1. Manifest validation: every rejection above, asserting exact error paths.
2. Serialize golden output for a representative mega spec (key order, omitted
   optionals, `JSON_UNESCAPED_SLASHES`).
3. Idempotency: seed twice → second plan UNCHANGED.
4. Arbitration matrix: untouched + manifest change → UPDATE and re-hash;
   editor-edited → preserved, plans UNCHANGED; edited + manifest change →
   still preserved; block deleted in editor → re-seeded from manifest;
   claimed legacy nav with a pre-existing mega block → preserved on first
   seed; foreign hash version → preserved; membership-driven UPDATE on a nav
   whose mega block is client-owned → block preserved **and** its hash entry
   carried forward, so it is still client-owned on the following run.
5. KSES round-trip byte-identity through a real `wp_update_post()`.
6. Per-language entry resolution: two languages, per-language permalinks and
   title fallbacks in the emitted markup.
7. Zero-mega golden: a manifest without mega items serializes byte-identically
   to the pre-change output.
8. Count symmetry: plan `from`/`to` tallies for navs with mega items.

## Docs

- `docs/seeding.md`: the nav section's "There is no hash arbitration for navs
  at all" paragraph gains the mega-scoped exception and a short description of
  the ownership model and the delete-to-re-assert workflow.
- Client-template manifest docs (wherever the nav grammar is documented):
  the `mega` item shape, the leaf rule, and the ownership semantics.

## Acceptance

- A client manifest can declare a mega menu; `npm run seed` produces a header
  nav containing a rendering `pediment/mega-menu` with per-language resolved
  links.
- Editing the mega block in the Site Editor persists across seed runs; all
  other editor changes to seeded navs still revert; Verifier passes in both
  regimes.
- Manifest changes to an untouched mega menu re-apply on seed.
- Sites without mega items see zero plan changes after upgrading.
