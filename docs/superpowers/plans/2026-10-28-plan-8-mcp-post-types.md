# Plan 8 — MCP Tools for Posts and Custom Post Types

**Goal:** Let an agent list, read, check, write and roll back posts and custom post types through the Pediment MCP server, under the same content rules as pages. Today every page tool refuses any post whose type is not `page` (`PageData::find()`), and `ListPages` and `WritePage` hard-code `post_type => 'page'`.

**Architecture:** The five page tools take an optional `post_type` input (default `page`). A new class `Pediment\Abilities\PostTypes` decides which types the tools serve and maps capabilities from the post type object. The tool names stay. `get-vocabulary` lists the served types. The language seam gets one method, `translatesPostType()`, so `language` and `translation_of` work only on types the language plugin translates.

**Tech stack:** PHP 8.1, WordPress 6.9+, Abilities API, MCP Adapter 0.7.0, PHPUnit via wp-env (default and Polylang suites; WPML in CI only).

## Decisions

- D1 — Keep the eight tool names. `post_type` defaults to `page`, so every existing call, the client-kit skills and the smoke test keep working. Renaming to `*-post` would break every agent prompt for a cosmetic gain.
- D2 — Served types: `page`, `post`, and every non-built-in type that is registered with `show_in_rest`, supports `editor` and uses the block editor. `pediment_style` is never served (it has its own tools). Other built-in types (`wp_block`, `wp_template`, `wp_navigation`, `attachment`, …) are never served. The filter `pediment_mcp_post_types` can remove types or add non-built-in types; the hard rules apply after the filter, like `core/html` for allowed blocks.
- D3 — The type is explicit. A tool that takes an `id` refuses a post of another type, even when both types are served, and the error names the real type ("post 12 is a guide; pass post_type \"guide\""). A wrong default never writes to the wrong row.
- D4 — Capabilities come from the post type object: `cap->edit_posts` to list, read, check and write; `edit_post` on the id; `cap->publish_posts` for `publish` and `private`. For pages these are `edit_pages` and `publish_pages`, as before.
- D5 — `parent` only on hierarchical types, and the parent must have the same type.
- D6 — `language` and `translation_of` only on types the language plugin translates (`LanguageProvider::translatesPostType()`); otherwise a named error and nothing is written.
- D7 — Types without `revisions` support are served. `write-page` then returns `revision: 0` and a warning that the write cannot be rolled back. The seeder's default `supports` for manifest `post_types` gains `revisions`.
- D8 — The editor's invalid-block report covers every served type, not only pages.
- D9 — Meta fields and taxonomies stay out of this plan. Add them later only for meta registered with `show_in_rest`.
- D10 — Added after the workation thread `2026-10-28-mcp-custom-post-types` (R3, R4): `write-page` takes `excerpt`, `menu_order` and `featured_image`. An image URL is imported once and reused by source URL (`_pediment_source_url`). The alt text lives on the post (`_pediment_featured_image_alt`) and replaces the attachment's alt when the post's thumbnail renders, so translations share one attachment and keep their own alt text without Polylang media translation (which Pediment keeps off).

## Tasks

- [x] T1 `PostTypes`: `served()`, `isServed()`, `refusal()`, `can()`, `canEditAny()`, `describe()` for `get-vocabulary`.
- [x] T2 `translatesPostType()` on `LanguageProvider` (Null: false, Polylang: `pll_is_translated_post_type()`, WPML: `wpml_is_translated_post_type`).
- [x] T3 `PageData` and the five page abilities take `post_type`; `summary()` adds `type`.
- [x] T4 `get-vocabulary` returns `postTypes`; its permission accepts `edit_posts` of any served type.
- [x] T5 `InvalidBlockLog` route and editor script accept every served type.
- [x] T6 Seeder default `supports` adds `revisions`.
- [x] T7 PHPUnit: a fixture CPT (served, refused, capability mapping, type mismatch, parent rule, revision warning, invalid-block route); Polylang: language on an untranslated CPT is refused.
- [x] T8 `tools/mcp-smoke.mjs`: write and read a `post` through MCP.
- [x] T9 Docs: `docs/mcp.md`, `docs/seeding.md`, roadmap.
- [x] T10 `excerpt`, `menu_order`, `featured_image` on write and read (D10), with PHPUnit and a Polylang case.

## Verification

- `composer lint -d plugin`, `npm run lint:js`
- `npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit`
- the same with `-c phpunit-polylang.xml.dist`
- `node tools/mcp-smoke.mjs`
- `npm run test:kit`
