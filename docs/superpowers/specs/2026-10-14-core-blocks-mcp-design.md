# Core Blocks, Design Layer and MCP — Design

**Date:** 2026-10-14
**Status:** Approved (design), pending implementation plan
**Path classification:** Architectural

## Goal

Make Pediment smaller and AI-first. AI agents build and change pages on the
live site through an MCP server, using core blocks and a named design
vocabulary. A design change is one edit to one named style, not an edit to
every page. Pediment keeps what core blocks cannot do (forms, slider,
mega-menu, blog index, social links), the tokens, and a bootstrap-only seeder.
The in-plugin AI chat and 18 blocks go: 16 presentation blocks plus the
`faq`/`faq-item` pair, which `core/details` replaces.

Pediment is internal first (Bergert Digital client sites) and a product later.
Breaking changes are acceptable when this repo ships the migration for the
three existing client sites.

## Context: the experiment that motivates this

On 2026-10-14 we rebuilt the Workation Castle home page with core blocks only,
through the REST API (the same writes an MCP server makes). Full notes:
`.context/mcp-test/RESULTS.md` in workspace `pediment/cancun-v1` (not in git).

| Finding | Consequence for this design |
| --- | --- |
| A fresh agent rebuilt the home page from screenshots at ~9/10 visual fidelity; 147 core blocks, all valid, no `core/html`. A second agent built a new page in the same style from the first page alone, in one round. | AI does not need Pediment's fixed presentation blocks to produce on-brand pages. |
| The original home page uses **zero** Pediment layout blocks. It uses 24 client-specific `workation/*` blocks. `pediment-website` uses 15 Pediment block types, `straight-solutions-website` 5. | The generic presentation blocks are not the design system clients actually rely on. |
| The agent wrote 13.5 KB of custom CSS (111 rules) into user Global Styles. | Free-form CSS in the database has no history, no review, no token discipline. → Design layer (§2). |
| The agent stored section padding and some radii as literal values in each block. Three design changes then needed per-page edits; at 20 pages × 5 languages ≈ 100 page writes. In the original theme each change is one line. | Literal design values in blocks must be rejected. → Content rules (§3). |
| Booking widget, lightbox and scroll reveal were lost; the transparent header needed a `:has()` hack on theme internals. | Behaviour stays in real blocks. |
| In the editor the core rebuild is an unnamed Group › Grid › Cover tree. | Named styles + `metadata.name` on blocks (§2, §3). |
| Block validity of static core blocks was the agents' main source of errors until they had a validator. | A validator is part of the toolchain (§3). |

## Decisions (from brainstorming)

| # | Decision | Rejected alternatives |
| --- | --- | --- |
| 1 | Pediment is internal first, product later. | Internal only; product only. |
| 2 | Drop the in-plugin AI chat. An MCP server replaces it. | Keep the chat beside MCP. |
| 3 | **Live-first:** AI builds pages on the site through MCP. The site is the source of truth for content. | Repo-first (patterns + seeder as source); per-site choice. |
| 4 | Design vocabulary = **theme base + managed site layer** with revisions, token-only values, and an export back to the theme. | Theme only (AI cannot add styles); site only (free CSS, as in the experiment). |
| 5 | **Convert now:** a converter rewrites legacy presentation blocks to core blocks + styles on the three sites; the blocks are then deleted. | Freeze the blocks; keep everything. |
| 6 | The seeder becomes **bootstrap-only**. | Keep repo→site sync; drop the seeder. |
| 7 | MCP tools live **in the plugin**, as WordPress Abilities, exposed by the canonical MCP Adapter plugin (soft dependency). | Local Node MCP server in `client-kit`; third-party WordPress MCP + written rules. |
| 8 | Block validity is checked by a **Node validator in `client-kit`** (`@wordpress/blocks`, pinned to the site's WordPress version), plus an editor-side safety net. | PHP port of every core block's `save()`. |

## Design

### 1. Architecture

| Part | Job | Status |
| --- | --- | --- |
| Tokens | `theme.json` presets: colours, fonts, font sizes, spacing, shadows; radius scale under `settings.custom.radius`. Client theme overrides by slug. | exists, extended |
| Design layer | Named block style variations in three layers (§2). | new |
| Functional blocks | `form`, `form-field`, `slider`, `mega-menu`, `blog-index`, `social-links`. | kept |
| Seeder | Bootstrap: languages, first pages, navigation, header part. | simplified |
| MCP tools | Abilities in the `pediment/` namespace (§3). | new |

Removed: the AI chat (`src/Chat/`, `src/Anthropic/`, `src/Usage/`, `src/Mock/`,
`src/Rest/ChatController.php`, `src/Schema/tables.php`, `editor/chat/` and the
chat parts of `editor/`, chat settings, chat e2e specs) and 18 blocks (§4):
`hero`, `cta`, `feature`, `feature-grid`, `stat`, `stat-grid`, `step`, `steps`,
`section-head`, `media-text`, `testimonial`, `testimonial-grid`, `logo-cloud`,
`pull-quote`, `image-caption`, `prose`, `faq`, `faq-item`.

`Requires at least` rises from 6.4 to **6.9** (Abilities API in core;
`register_block_style()` with `style_data` needs 6.6).

Data flow for an AI page edit:

1. `get-vocabulary` → tokens, styles, allowed blocks, rules, WP version.
2. The agent composes block markup and validates it locally with the
   `client-kit` validator.
3. `write-page` → server applies the content rules, stores a revision, saves.
4. `check-page` → rules report plus any invalid blocks the editor recorded.

### 2. Design layer

**Format.** Block style variations (WordPress "section styles"). A style has a
slug, a label, the block types it applies to, `theme.json`-shaped style data
(spacing, border, color, typography, shadow, elements, nested blocks) and an
optional `css` string for what JSON cannot express (pseudo-elements such as the
eyebrow line or link arrows, hover states). `&` in `css` refers to the style's
own selector.

**Layers.** A later layer overrides an earlier one with the same slug.

1. **Plugin defaults** — a small generic set built from tokens only:
   `band` (section, `core/group`), `band-alt`, `card` (`core/group`),
   `media-card` (`core/cover`), `tile` (`core/cover`), `eyebrow`
   (`core/paragraph`), `lead` (`core/paragraph`), `pill` (`core/button`),
   `pill-ghost`, `media-row` (`core/columns`), `quote-card` (`core/group`),
   `stat` (`core/group`). The exact set is fixed in the plan by what the
   converter mappings (§4) need; nothing beyond that.
2. **Client theme** — `styles/blocks/*.json` in the client repo (git, reviewed).
   `client-template/` ships the folder and one example.
3. **Site layer** — post type `pediment_style` (not public, revisions on, REST
   off; only the abilities write it). Each post stores one style as JSON. The
   plugin registers active entries at `init` with `register_block_style()`, so
   they render on the front end and in the editor, and clients see them by
   label in the Styles panel.

**Style rules** (`Pediment\Design\StyleValidator`, applied to every layer on
registration and to the site layer on save):

- Colours, font sizes, font families, spacing (padding, margin, gap), border
  radius and shadows must reference tokens: `var:preset|…`,
  `var(--wp--preset--…)` or `var(--wp--custom--…)`. `0`, `inherit`,
  `currentColor`, `transparent` and percentages are allowed.
- Other values (line widths, aspect ratios, transforms, transitions) are free.
- No `!important`.
- `css` selectors must start with `&` (the style itself or its descendants).
- Invalid plugin/theme styles are skipped with a `_doing_it_wrong` notice; an
  invalid site-layer write is rejected with an error list.

**Export.** An admin button (Pediment settings) and `wp pediment design export`
write every active site-layer style as a client-theme JSON file. After the
theme release ships the file, the site entry is marked `promoted` and stops
loading (the theme layer now provides it). Export never deletes anything.

### 3. MCP tools and content rules

**Exposure.** Each tool is an Ability (`wp_register_ability()`) in the
`pediment/` category, with input and output JSON schemas and a permission
callback requiring `edit_pages` (style tools: `edit_theme_options`). When
`WP\MCP\Core\McpAdapter` exists, Pediment creates an MCP server
(`pediment` namespace, HTTP transport) exposing exactly these abilities. Login
uses application passwords. Without the adapter the abilities still exist (and
are testable) but no MCP endpoint is served. Install docs cover uploading the
MCP Adapter plugin zip in wp-admin.

| Ability | Input → output |
| --- | --- |
| `pediment/get-vocabulary` | — → tokens, styles (with layer), allowed blocks, content rules, WP version, active languages. |
| `pediment/list-pages` | filters (language, parent, status) → id, title, slug, parent, language, translations, status, modified. |
| `pediment/read-page` | id → raw markup, title, slug, parent, language, translations, revision count. |
| `pediment/write-page` | id? , title, slug, parent?, language?, translation_of?, status (default `draft` for new pages), markup → id, revision id, warnings. |
| `pediment/rollback-page` | id, revision id → restored revision. |
| `pediment/upsert-style` | slug, label, block types, style data, css? → stored style, revision id. |
| `pediment/delete-style` | slug → result (site layer only). |
| `pediment/check-page` | id → rule violations, recorded invalid blocks, legacy blocks present. |
| `pediment/convert-legacy` | page ids or `all`, `dry_run` → per-page diff / result (§4). |

**Content rules** (`Pediment\Content\Policy`, run by `write-page` and
`check-page`). Errors reject the write; warnings are returned.

- *Error:* a block outside the allowlist. The allowlist is a fixed set of core
  blocks (group, columns, column, cover, image, gallery, heading, paragraph,
  list, list-item, buttons, button, details, quote, separator, spacer,
  media-text, embed), the functional Pediment blocks, and the client theme's own blocks. Filter:
  `pediment_allowed_blocks`. `core/html` is not allowed.
- *Error:* a literal design value in block attributes — any `style.color.*`,
  `style.typography.fontSize|fontFamily`, `style.spacing.*`,
  `style.border.radius`, `style.shadow` value that is not a preset reference.
  Layout attributes stay allowed: column `width`, grid `columnCount` /
  `minimumColumnWidth`, `columnSpan` / `rowSpan`, cover `minHeight`,
  `aspectRatio`, `contentPosition`, image `width` / `height`.
- *Error:* a `className` naming a style slug that does not exist.
- *Warning:* a container block (group, columns, cover) without
  `metadata.name`. Names make the editor's list view readable.
- *Warning:* image URLs outside the site's media library.

**Validity.** `client-kit` gains `validate-blocks` (Node, `@wordpress/blocks` +
`@wordpress/block-library` pinned to the WordPress version that
`get-vocabulary` reports, with the site's style variations registered). The
`/pediment:*` skills run it before every `write-page`. Safety net: an editor
script records invalid blocks (post id, block name, time) to post meta when a
page opens in the editor; `check-page` reports them.

### 4. Migration and removal

**Converter** (`Pediment\Convert\`, one mapper class per legacy block). Each
mapper turns a legacy block's attributes and inner blocks into core block
markup that uses plugin default styles. Examples: `section-head` →
`eyebrow` paragraph + heading + `lead` paragraph; `feature-grid` + `feature` →
grid group of `card` groups; `faq` + `faq-item` → `core/details`; `hero` →
`core/cover`; `cta` → `band` group + `pill` buttons; `prose` → its inner
blocks in a constrained group. Output shapes are fixed templates, so CI can
prove every mapping valid (§5). A legacy block without a mapper is left in
place and reported.

Runs with the same code from:

- MCP: `convert-legacy` (`dry_run` returns a per-page diff).
- wp-admin: a "Convert legacy blocks" screen under Pediment (production has no
  CLI).
- WP-CLI: `wp pediment convert [--dry-run] [--patterns=<dir>]`. `--patterns`
  rewrites a client theme's `patterns/*.php` in a dev environment so new
  environments match production.

Every page gets a revision before conversion. Every run ends with a report
(converted, skipped, unmapped).

**Chat removal.** Delete the chat code, settings tab, editor UI, tests and e2e
specs. An upgrade routine (version gate in `Bootstrap`) drops the chat tables
and deletes the stored API key and chat options. `uninstall.php` loses the
chat parts.

**Seeder: bootstrap only.** `wp pediment seed` keeps identity
(`_pediment_seed_key`), languages, navigation, header part and media. On a site
that is already seeded it only creates missing entries and never updates
existing content. The re-sync path and content-arbitration hashes are removed
(`ContentHash`, `Differ` update branches, the related `Reporter` output). The
claim/adopt path for legacy sites stays as it is.

**Release sequence.**

1. **v4.0.0** — design layer, abilities + MCP server, content rules,
   converter, validator in `client-kit`, chat removed, seeder bootstrap-only.
   Legacy presentation blocks still render but are hidden from the inserter
   (`supports.inserter: false`).
2. **Convert the three sites**, one at a time: `pediment-website`, then
   `straight-solutions-website`, then Workation. Per site: run the converter in
   the client's dev environment, add client-theme styles where the theme had
   styled a legacy block, compare screenshots at 375, 768 and 1440 px, Jonas
   reviews, then convert production through wp-admin.
3. **v5.0.0** — delete the 18 legacy blocks (registration, render, styles,
   editor code). The converter mappers stay until v6.0.0: they work on parsed
   markup and do not need the blocks registered. Ships
   only after all three sites report zero legacy blocks via `check-page`.

### 5. Testing and CI

- **PHPUnit:** `StyleValidator` (accept/reject per rule); `Policy` (one test
  per violation type, warnings); every ability (permission denied for
  anonymous and subscriber, schema validation, revision + rollback); site-layer
  registration and `promoted` handling; export output; each converter mapper
  with valid and empty attributes; upgrade routine removes tables and options;
  seeder never updates existing content.
- **Node / CI:** every converter fixture output and every plugin default style
  passes `@wordpress/blocks` validation at the pinned WordPress version;
  `client-kit` validator unit tests run in `npm run test:kit`.
- **MCP integration:** a CI job starts wp-env with the MCP Adapter plugin,
  creates an application password, and calls `tools/list`, `write-page` and
  `check-page` over JSON-RPC.
- **Playwright e2e:** a page written through the ability opens in the editor
  with zero invalid blocks; theme and site-layer styles appear in the Styles
  panel and render identically on the front end and in the editor.
- **Acceptance (manual, documented in `docs/mcp.md`):** repeat the 2026-10-14
  experiment through the new tools on the Workation dev environment. Each of
  the three design changes (card radius, eyebrow style, section padding) must
  take exactly one `upsert-style` call and zero page writes.

### 6. Documentation

- New: `docs/mcp.md` (setup incl. MCP Adapter install, application passwords,
  tools, rules, acceptance test) and `docs/design-layer.md` (layers, rules,
  export).
- Updated: `AGENTS.md` and `plugin/AGENTS.md` hard rules (block list,
  "no literal design values in content"), `docs/VISION.md`, `docs/blocks.md`,
  `docs/seeding.md` (bootstrap only), `client-template/` docs, the
  `/pediment:start` and `/pediment:port-page` skills (port pages through MCP +
  validator).
- Removed: everything about the AI chat.

## Non-goals

- A visual page builder or any editor UI beyond the Styles panel and the
  convert screen.
- Hosted AI, licence keys, or billing (still backlogged).
- Converting client-specific blocks such as `workation/*`. Clients keep them;
  they count as functional blocks of the client theme.
- WordPress.org listing of Pediment.
- Repo-first page building. Patterns remain for bootstrap and dev only.

## Risks

- **Visual parity of converted pages.** Plugin default styles must match the
  legacy blocks closely; where a client theme styled a legacy block itself, the
  theme needs a matching style. Budget per site in the plan.
- **MCP Adapter is pre-1.0** and not yet on WordPress.org. The soft dependency
  keeps Pediment working without it; pin and test a known adapter version.
- **Validator drift.** The `client-kit` validator must match the site's
  WordPress version; `get-vocabulary` reports it and the validator refuses to
  run on a mismatch it has no pinned build for.
- **Live-first drift from git.** Content lives on the site. Back-ups and
  revisions are the safety net; export covers styles, not pages.
