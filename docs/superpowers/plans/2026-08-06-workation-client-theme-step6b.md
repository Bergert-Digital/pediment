# Workation Becomes a Pediment Client Theme (Step 6b, theme half) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Convert `Bergert-Digital/workation-castle-website` from a Pediment child theme into a standalone Pediment client theme with a seed manifest, per `docs/superpowers/specs/2026-08-06-workation-client-theme-step6b-design.md`, so that a clean wp-env with the plugin plus this theme builds the whole site from `seed/manifest.php`.

**Architecture:** The repo is converted in place. Roughly 2,349 lines under `inc/` that the plugin or the manifest now owns are deleted first, shrinking the surface every later rename has to touch. The theme's identity then moves in ordered passes — directory and zip name `pediment-child-theme` → `workation`, then the bare `pediment-child` namespace, text domain, PHP namespace, function prefix and constants — with the compound name renamed before the bare one so the bare pass cannot corrupt it. `seed/manifest.php` is generated from a WordPress XML export by a committed script rather than transcribed by hand, and a one-shot admin tool rewrites the stored `post_content` from the old block namespace to the new one at cutover.

**Tech Stack:** PHP 8.1, WordPress 6.9, PHPUnit, Playwright, Node 20 (`node:test`), `@wordpress/scripts`, `@wordpress/env`, GitHub Actions reusable workflows.

## Global Constraints

- **This plan runs in the `workation-castle-website` repository, not the pediment monorepo.** Open a Conductor workspace on that repo. Do not create git worktrees or branches by hand — the workspace is the isolation.
- **Task 0 is a hard prerequisite.** The plugin half (`2026-08-06-nav-submenus-step6b-plugin.md`) must be merged *and released*, because `seed/manifest.php` declares nav submenus and CI runs `seed-check` against the published plugin zip.
- **Never push without explicit user approval.** All work is local until the gated push in Task 11.
- **This is a breaking change.** The stylesheet slug changes, the block namespace changes, and the theme stops being a child. The repo releases **1.0.0**. Release-please owns the version files; never hand-bump. Use a `feat!:` commit or a `BREAKING CHANGE:` footer once, on the identity-rename commit.
- **The one-shot rewrite tool is temporary.** It ships in 1.0.0 and is deleted in the release that follows the cutover. Say so in its own docblock.
- **Never rename stored data.** `_pediment_seed_key`, `_pediment_seed_hash`, `_pediment_seed_source` and the client's own meta keys (`wc_*`) keep their exact names. Only *block* names and *class* names in `post_content` are rewritten, and only by the Task 8 tool.
- **The manifest is generated, never hand-edited.** If a value is wrong, fix `tools/manifest-from-wxr.mjs` and regenerate, so re-running it before the cutover stays a meaningful drift check.
- **Nav item `label` is omitted on every `entry` item** so each language takes the entry's own translated title (spec decision 4). Do not reintroduce the five retired custom labels.
- **`inc/LegacyBlockCopy.php` stays** (spec decision 7). Deleting it is a step 6c decision made against real data.
- Commit messages: conventional summary of at most 60 characters, with the trailer `Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>`. Stage files explicitly by name; never `git add -A`.

---

## File Structure

### Delete

- `inc/seed.php`, `inc/NavTranslations.php`, `inc/PrimaryNav.php`, `inc/Polylang.php`, `inc/ThemeUpdater.php`, `inc/UpdateToken.php`, `inc/settings-updates.php` — 2,349 lines the plugin or the manifest now owns.
- `parts/header.html` — replaced by a pattern the plugin seeds into an editable database part.
- `tests/phpunit/NavTranslationsTest.php`, plus the page-seeding cases inside `ActivitySeedTest.php`, `CheckInSeedTest.php`, `GuideSeedTest.php`, `MapSeedTest.php` (triaged per file in Task 2 — activity and photo data assertions survive).

### Create

- `seed/manifest.php` — 18 entries, 5 languages, 1 nav. Generated.
- `tools/manifest-from-wxr.mjs` — WXR → manifest PHP.
- `tools/manifest-from-wxr.test.mjs` — `node:test` coverage for the grouping and override logic.
- `templates/index.html` — required for `is_block_theme()` once the parent theme is gone.
- `patterns/header.php` — registers `workation/header`, the markup the plugin seeds the header part from.
- `inc/NamespaceRewrite.php` — the one-shot `post_content` rewrite tool.
- `tests/phpunit/NamespaceRewriteTest.php`.

### Modify

- `style.css` — drop `Template:` and `Update URI:`, move the text domain.
- `functions.php` — drop the retired `require_once` lines and the two header filters; rename constants and the prefix.
- `src/blocks/*/block.json` (23 files) — `name` and `textdomain`.
- `patterns/*.php` (18 files) — `Slug:`, `Categories:`, and every `wp:pediment-child/` reference.
- `.wp-env.json` — drop the parent theme, pin the plugin.
- `.github/workflows/` — call the monorepo's reusable workflows.
- `package.json`, `README.md`, `AGENTS.md`.

---

### Task 0: Prerequisites and a green baseline

**Files:** none changed.

**Interfaces:**
- Consumes: the released plugin from `2026-08-06-nav-submenus-step6b-plugin.md`.
- Produces: a recorded baseline every later task compares against.

- [ ] **Step 1: Confirm the plugin release exists**

```bash
gh release list --repo Bergert-Digital/pediment --limit 5
```
Expected: a release newer than v3.0.0 whose notes mention nav submenus. If there is none, STOP — this plan cannot start.

- [ ] **Step 2: Boot the current theme and run its suites**

```bash
npx wp-env start
npx wp-env run tests-wordpress --env-cwd=wp-content/themes/pediment-child-theme ./vendor/bin/phpunit
npx playwright test
```
Expected: both green. Record the counts (tests, assertions, specs) — Task 2 and Task 10 compare against these numbers, and a failure discovered later is otherwise indistinguishable from one that was already there.

- [ ] **Step 3: Generate a throwaway scaffold to diff against**

The target directory's basename must equal `client.slug` — `validateTargetMatchesSlug()` throws otherwise — so the reference is scaffolded at `/tmp/workation`, not at a `-reference` path. Write `/tmp/answers.json`:

```json
{
  "client": {
    "slug": "workation",
    "name": "Workation Castle",
    "description": "An Italian castle between Lake Como and Lake Lugano where teams work, gather and unwind."
  },
  "brief": {
    "does": "Hosts teams and families in a restored castle above Lake Como.",
    "audience": "Companies booking team retreats, and families booking group stays.",
    "tone": "Warm, concrete, unhurried."
  },
  "brand": { "accent": "#FEC601", "primary": "#3A2616", "source": "chosen", "font": { "family": "Inria Sans" } },
  "languages": [
    { "slug": "en", "name": "English", "locale": "en_US", "default": true },
    { "slug": "de", "name": "Deutsch", "locale": "de_DE" },
    { "slug": "nl", "name": "Nederlands", "locale": "nl_NL" },
    { "slug": "fr", "name": "Français", "locale": "fr_FR" },
    { "slug": "it", "name": "Italiano", "locale": "it_IT" }
  ],
  "pages": [ { "key": "home", "title": "Home" } ]
}
```

Then, from a checkout of the pediment monorepo:

```bash
node client-kit/scripts/scaffold.mjs --with-blocks --target /tmp/workation --answers /tmp/answers.json --no-git --template ./client-template
```

Keep `/tmp/workation` for Task 10's checklist. It is a reference to read, never a merge source.

---

### Task 1: Delete the code the plugin now owns

**Files:**
- Delete: `inc/seed.php`, `inc/NavTranslations.php`, `inc/PrimaryNav.php`, `inc/Polylang.php`, `inc/ThemeUpdater.php`, `inc/UpdateToken.php`, `inc/settings-updates.php`, `parts/header.html`
- Modify: `functions.php`

**Interfaces:**
- Consumes: nothing.
- Produces: a theme with no seeding, nav-building or self-update code of its own. `pediment_child_seed_nav_translations()`, `PedimentChild\Seed`, `PEDIMENT_CHILD_NAV_LABELS` and the two header filters cease to exist.

Deletion comes first because every later rename pass touches fewer files for it.

- [ ] **Step 1: Delete the files**

```bash
git rm inc/seed.php inc/NavTranslations.php inc/PrimaryNav.php inc/Polylang.php inc/ThemeUpdater.php inc/UpdateToken.php inc/settings-updates.php parts/header.html
```

- [ ] **Step 2: Drop their `require_once` lines from `functions.php`**

Remove the `require_once` lines for `UpdateToken.php` (line 28), `ThemeUpdater.php` (29), `settings-updates.php` (34, with its enclosing conditional), `seed.php` (39), `PrimaryNav.php` (61), `Polylang.php` (70) and `NavTranslations.php` (75), together with the comment blocks that introduce each. Leave the `require_once` lines for `Consent.php`, `Photos.php`, `Activities.php`, `CheckIn.php`, `Brevo.php`, `WorkationSections.php`, `EstateMap.php`, `AvailabilityForm.php`, `LegacyBlockCopy.php` and `Redirects.php` exactly as they are.

- [ ] **Step 3: Delete the two header filters**

In `functions.php`, delete the function whose docblock begins "Keep the theme-file `header` template part canonical." (around line 393) and its `add_filter()` calls, along with the second filter described in the same comment block. The plugin owns the header part now; these existed only to fight the parent theme's copy off.

- [ ] **Step 4: Verify nothing still references the deleted code**

```bash
grep -rn "PedimentChild\\\\Seed\|pediment_child_seed\|PEDIMENT_CHILD_NAV_LABELS\|pediment_child_translate_nav_url\|ThemeUpdater\|UpdateToken" --exclude-dir=node_modules --exclude-dir=.git --exclude-dir=build .
```
Expected: matches only inside `tests/`, which Task 2 handles. Any match in `inc/`, `functions.php` or `patterns/` is a missed edit — fix it now.

- [ ] **Step 5: Commit**

```bash
git add functions.php
git commit -m "refactor: drop seeding, nav and updater code"
```

---

### Task 2: Triage the tests whose subject is gone

**Files:**
- Delete: `tests/phpunit/NavTranslationsTest.php`
- Modify: `tests/phpunit/ActivitySeedTest.php`, `tests/phpunit/CheckInSeedTest.php`, `tests/phpunit/GuideSeedTest.php`, `tests/phpunit/MapSeedTest.php`, and any Playwright spec asserting seeded pages

**Interfaces:**
- Consumes: Task 1's deletions.
- Produces: a green suite that no longer covers deleted code.

These four files mix page-seeding assertions with activity and photo data assertions that must survive, so they are edited case by case, not deleted wholesale.

- [ ] **Step 1: Delete the test whose whole subject is gone**

```bash
git rm tests/phpunit/NavTranslationsTest.php
```

- [ ] **Step 2: Triage the four mixed files**

For each of `ActivitySeedTest.php`, `CheckInSeedTest.php`, `GuideSeedTest.php`, `MapSeedTest.php`: read it, and delete every test method that calls `PedimentChild\Seed::` or asserts on a page created by the old seeder. Keep every method that asserts on `wc_activity`/`wc_photo` registration, the activity and photo data manifests, or block rendering. If a file has nothing left, `git rm` it.

- [ ] **Step 3: Run the PHPUnit suite**

```bash
npx wp-env run tests-wordpress --env-cwd=wp-content/themes/pediment-child-theme ./vendor/bin/phpunit
```
Expected: PASS. The count is lower than Task 0's baseline by exactly the methods deleted — confirm the drop matches, so a test that broke rather than being removed cannot hide in the difference.

- [ ] **Step 4: Repoint the Playwright specs**

Run `npx playwright test` and read the failures. Specs that navigated to pages the old seeder created (`smoke.spec.ts`, `guide.spec.ts`, `ways-to-stay.spec.ts`, `header-nav.spec.ts` are the likely ones) now have no content to visit. Leave them failing for now and note which ones — Task 10 makes them pass against the manifest-seeded site. Do not weaken an assertion to make it green.

- [ ] **Step 5: Commit**

```bash
git add tests/
git commit -m "test: drop coverage of the retired seeder"
```

---

### Task 3: Rename the theme's identity

**Files:**
- Rename: the theme directory itself, `pediment-child-theme` → `workation`
- Modify: `style.css`, `functions.php`, `package.json`, `.github/workflows/build-release-zip.yml`, `README.md`, `AGENTS.md`, every remaining PHP file

**Interfaces:**
- Consumes: Task 1's smaller file set.
- Produces: stylesheet slug `workation`; constants `WORKATION_DIR` / `WORKATION_VERSION`; PHP namespace `Workation`; function prefix `workation_`; text domain `workation`.

Order matters. `pediment-child-theme` is renamed **before** the bare `pediment-child`, or the bare pass turns it into `workation-theme`.

- [ ] **Step 1: Rewrite the compound name first**

```bash
grep -rl "pediment-child-theme" --exclude-dir=node_modules --exclude-dir=.git --exclude-dir=build . | xargs sed -i '' 's/pediment-child-theme/workation/g'
```
This covers `build-release-zip.yml:81,84`, `.wp-env.json` paths, docs and `package.json`.

- [ ] **Step 2: Rewrite the remaining identifiers, longest first**

```bash
FILES=$(grep -rl "pediment-child\|PedimentChild\|pediment_child_\|PEDIMENT_CHILD_" --exclude-dir=node_modules --exclude-dir=.git --exclude-dir=build .)
echo "$FILES" | xargs sed -i '' 's/PEDIMENT_CHILD_/WORKATION_/g'
echo "$FILES" | xargs sed -i '' 's/pediment_child_/workation_/g'
echo "$FILES" | xargs sed -i '' 's/PedimentChild/Workation/g'
echo "$FILES" | xargs sed -i '' 's/pediment-child/workation/g'
```
The bare `pediment-child` pass runs last so it cannot eat a prefix of the others. It correctly rewrites the `wp-block-pediment-child-*` CSS classes to `wp-block-workation-*`, because those classes are derived from the block names being renamed in Task 4.

- [ ] **Step 3: Fix the `style.css` header by hand**

`sed` cannot make these judgements. In `style.css`:
- Delete the `Template: pediment` line — the theme is no longer a child.
- Delete the `Update URI:` line — `ThemeUpdater` is gone (step 5 decision 8), and leaving it makes WordPress poll a repo that will never answer.
- Set `Text Domain: workation`.
- Leave `Theme Name: Workation Castle` and the release-please version markers alone.

- [ ] **Step 4: Rename the directory**

```bash
cd .. && mv pediment-child-theme workation && cd workation
```
Then restart wp-env so the mount picks up the new path: `npx wp-env destroy && npx wp-env start`.

- [ ] **Step 5: Verify no stale identifier survives**

```bash
grep -rn "pediment-child\|PedimentChild\|pediment_child_\|PEDIMENT_CHILD_" --exclude-dir=node_modules --exclude-dir=.git --exclude-dir=build .
```
Expected: **no output**, except inside `CHANGELOG.md`, which is a historical record and must not be rewritten.

- [ ] **Step 6: Run the suite**

```bash
npx wp-env run tests-wordpress --env-cwd=wp-content/themes/workation ./vendor/bin/phpunit
```
Expected: PASS at Task 2's count. Every later `--env-cwd` uses `wp-content/themes/workation`.

- [ ] **Step 7: Commit**

```bash
git add -u && git add style.css
git commit -m "feat!: rename the theme to workation

The stylesheet slug, PHP namespace, function prefix, constants and text
domain all move from pediment-child to workation, and the theme stops
declaring a parent.

BREAKING CHANGE: the theme directory is now workation, so this installs
beside the old theme rather than upgrading it."
```

---

### Task 4: Rename the block and pattern namespace

**Files:**
- Modify: `src/blocks/*/block.json` (23), `patterns/*.php` (18), `inc/WorkationSections.php`, any `src/blocks/*/index.js` referencing block names

**Interfaces:**
- Consumes: Task 3's identifier rename, which already rewrote the literal string `pediment-child` everywhere.
- Produces: 23 blocks registered as `workation/*`; 18 patterns with `Slug: workation/<name>`; the pattern category `workation`.

Task 3's final `sed` pass already rewrote `"name": "pediment-child/workation-hero"` to `"name": "workation/workation-hero"` and `Slug: pediment-child/guide` to `Slug: workation/guide`. This task verifies that mechanical result and fixes what a blind substitution got wrong.

- [ ] **Step 1: Verify every block name**

```bash
grep -h '"name"' src/blocks/*/block.json | sort
```
Expected: 23 lines, every one `"name": "workation/…"`. A block whose name did not change is a file the earlier `grep -rl` missed.

- [ ] **Step 2: Verify every pattern header**

```bash
grep -h "^ \* Slug:\|^ \* Categories:" patterns/*.php | sort | uniq -c
```
Expected: 18 `Slug: workation/…` lines and 18 `Categories: workation` lines.

- [ ] **Step 3: Check the pattern category is registered under the new name**

The category was registered by `Seed::register_pattern_category()`, which Task 1 deleted. Add a replacement to `functions.php`, next to the block registration:

```php
/**
 * Register the pattern category this theme's patterns declare.
 *
 * Previously done by the theme's own seeder, which the plugin's seeding engine
 * replaced. Patterns whose category is not registered still work, but they are
 * filed under "Uncategorized" in the inserter.
 */
function workation_register_pattern_category(): void {
	register_block_pattern_category(
		'workation',
		array( 'label' => __( 'Workation Castle', 'workation' ) )
	);
}
add_action( 'init', 'workation_register_pattern_category' );
```

- [ ] **Step 4: Build and confirm the blocks register**

```bash
npm ci && npm run build
npx wp-env run cli wp eval 'foreach ( WP_Block_Type_Registry::get_instance()->get_all_registered() as $n => $b ) { if ( str_starts_with( $n, "workation/" ) ) { echo $n, "\n"; } }'
```
Expected: 23 lines. Zero means `build/blocks` was not rebuilt or the theme is not active; a smaller number names the blocks whose `block.json` did not get renamed.

- [ ] **Step 5: Run the suite**

```bash
npx wp-env run tests-wordpress --env-cwd=wp-content/themes/workation ./vendor/bin/phpunit
```
Expected: PASS. Block render tests assert on class names, so a failure here usually means a `wp-block-workation-*` class was rewritten in PHP but not in the SCSS, or the reverse.

- [ ] **Step 6: Commit**

```bash
git add src/blocks patterns functions.php inc/WorkationSections.php
git commit -m "feat: move blocks and patterns to the workation namespace"
```

---

### Task 5: Ship `templates/index.html` and the header pattern

**Files:**
- Create: `templates/index.html`, `patterns/header.php`

**Interfaces:**
- Consumes: the plugin's `pediment_bootstrap_header_markup()`, which looks up the pattern named `get_stylesheet() . '/header'`.
- Produces: a theme that `is_block_theme()` recognises, and a `workation/header` pattern.

- [ ] **Step 1: Add the block-theme marker template**

Create `templates/index.html`. It is the fallback WordPress falls back *to*, and the plugin ships richer templates for every specific case, so it stays minimal:

```html
<!-- wp:template-part {"slug":"header","tagName":"header"} /-->

<!-- wp:group {"tagName":"main","layout":{"type":"constrained"}} -->
<main class="wp-block-group">
	<!-- wp:query {"queryId":0,"query":{"inherit":true}} -->
	<div class="wp-block-query">
		<!-- wp:post-template -->
			<!-- wp:post-title {"isLink":true,"level":2} /-->
			<!-- wp:post-excerpt /-->
		<!-- /wp:post-template -->
	</div>
	<!-- /wp:query -->
</main>
<!-- /wp:group -->

<!-- wp:template-part {"slug":"footer","tagName":"footer"} /-->
```

- [ ] **Step 2: Verify WordPress now sees a block theme**

```bash
npx wp-env run cli wp eval 'var_dump( wp_is_block_theme() );'
```
Expected: `bool(true)`. If this is false, `templates/index.html` is missing or misnamed — every later step depends on it.

- [ ] **Step 3: Move the old header markup into a pattern**

Create `patterns/header.php`, using the markup from the `parts/header.html` deleted in Task 1 (recover it with `git show HEAD~4:parts/header.html`, adjusting the revision as needed):

```php
<?php
/**
 * Title: Header
 * Slug: workation/header
 * Categories: workation
 * Description: The branded site header — logo, navigation, language switcher.
 * Inserter: no
 */
// phpcs:ignoreFile -- block pattern content (verbatim block markup).
?>
```

followed by the recovered markup verbatim, with two changes: any `wp:pediment-child/*` block becomes `wp:workation/*`, and the `core/navigation` block keeps **no** `ref` attribute — the plugin's `pediment_bind_navigation_ref()` binds it to the right language's menu at render time, and a hardcoded ref would override that.

- [ ] **Step 4: Prove the plugin uses it**

```bash
npx wp-env run cli wp eval '$p = WP_Block_Patterns_Registry::get_instance()->get_registered( "workation/header" ); var_dump( null !== $p );'
```
Expected: `bool(true)`. The plugin seeds the header part from this pattern on theme activation; Task 10 checks the seeded part end to end.

- [ ] **Step 5: Commit**

```bash
git add templates/index.html patterns/header.php
git commit -m "feat: ship an index template and a header pattern"
```

---

### Task 6: The manifest generator

**Files:**
- Create: `tools/manifest-from-wxr.mjs`, `tools/manifest-from-wxr.test.mjs`

**Interfaces:**
- Consumes: a WordPress XML export of pages.
- Produces: `buildEntries( xml ): { entries, warnings }` and a CLI writing `seed/manifest.php` to stdout.

Generating rather than transcribing is what makes re-running this before the cutover a real drift check (spec §3.2).

- [ ] **Step 1: Write the failing test**

Create `tools/manifest-from-wxr.test.mjs`:

```js
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { buildEntries } from './manifest-from-wxr.mjs';

const item = ( { id, title, slug, parent = '0', lang, group, status = 'publish' } ) => `
<item>
	<title>${ title }</title>
	<wp:post_id>${ id }</wp:post_id>
	<wp:post_name><![CDATA[${ slug }]]></wp:post_name>
	<wp:post_parent>${ parent }</wp:post_parent>
	<wp:status><![CDATA[${ status }]]></wp:status>
	<wp:post_type><![CDATA[page]]></wp:post_type>
	<category domain="language" nicename="${ lang }"><![CDATA[${ lang }]]></category>
	${ group ? `<category domain="post_translations" nicename="${ group }"><![CDATA[${ group }]]></category>` : '' }
</item>`;

const wrap = ( items ) => `<rss><channel>${ items.join( '' ) }</channel></rss>`;

test( 'an untranslated page becomes a plain entry', () => {
	const { entries } = buildEntries(
		wrap( [ item( { id: 190, title: 'Photos', slug: 'photos', lang: 'en' } ) ] )
	);

	assert.equal( entries.length, 1 );
	assert.deepEqual( entries[ 0 ], {
		key: 'photos',
		title: 'Photos',
		slug: 'photos',
		parent: null,
		languages: {},
	} );
} );

test( 'a translation group becomes per-language slug and title overrides', () => {
	const { entries } = buildEntries(
		wrap( [
			item( { id: 176, title: 'Home', slug: 'home', lang: 'en', group: 'g1' } ),
			item( { id: 582, title: 'Startseite', slug: 'startseite', lang: 'de', group: 'g1' } ),
			item( { id: 261, title: 'Home - Français', slug: 'home', lang: 'fr', group: 'g1' } ),
		] )
	);

	assert.equal( entries.length, 1 );
	assert.equal( entries[ 0 ].key, 'home' );
	assert.deepEqual( entries[ 0 ].languages, {
		de: { slug: 'startseite', title: 'Startseite' },
		fr: { slug: 'home', title: 'Home - Français' },
	} );
} );

test( 'a child page records its parent by key, not by id', () => {
	const { entries } = buildEntries(
		wrap( [
			item( { id: 201, title: 'Guide', slug: 'guide', lang: 'en' } ),
			item( { id: 241, title: 'FAQ', slug: 'faq', parent: '201', lang: 'en' } ),
		] )
	);

	assert.equal( entries.find( ( e ) => e.key === 'faq' ).parent, 'guide' );
} );

test( 'WordPress default pages are skipped and warned about', () => {
	const { entries, warnings } = buildEntries(
		wrap( [
			item( { id: 2, title: 'Beispiel-Seite', slug: 'beispiel-seite', lang: 'en' } ),
			item( { id: 190, title: 'Photos', slug: 'photos', lang: 'en' } ),
		] )
	);

	assert.deepEqual( entries.map( ( e ) => e.key ), [ 'photos' ] );
	assert.equal( warnings.length, 1 );
	assert.match( warnings[ 0 ], /beispiel-seite/ );
} );

test( 'a non-default-language page with no group is warned about, not silently dropped', () => {
	const { entries, warnings } = buildEntries(
		wrap( [ item( { id: 900, title: 'Waise', slug: 'waise', lang: 'de' } ) ] )
	);

	assert.equal( entries.length, 0 );
	assert.match( warnings[ 0 ], /waise/ );
} );
```

- [ ] **Step 2: Run it to verify it fails**

Run: `node --test tools/manifest-from-wxr.test.mjs`
Expected: FAIL — `Cannot find module './manifest-from-wxr.mjs'`.

- [ ] **Step 3: Write the generator**

Create `tools/manifest-from-wxr.mjs`:

```js
#!/usr/bin/env node
/**
 * Build seed/manifest.php's entry list from a WordPress XML export.
 *
 * The site's translated pages exist only in the database — patterns/ is
 * English-only — so their slugs and titles cannot be read out of this repo.
 * Transcribing ~24 rows by hand is how a wrong slug becomes a silent
 * `no-match` in a claim preview, so they are generated instead, and this
 * script is committed so it can be re-run against a fresh export immediately
 * before the cutover as a drift check.
 *
 * Usage: node tools/manifest-from-wxr.mjs export.xml > /tmp/entries.php
 */
import fs from 'node:fs';

/** Pages WordPress creates itself. They were never seeded and are not ours. */
const WORDPRESS_DEFAULTS = new Set( [ 'beispiel-seite', 'sample-page', 'datenschutzerklaerung', 'privacy-policy-2' ] );

const DEFAULT_LANGUAGE = 'en';

const tag = ( xml, name ) => {
	const m = xml.match( new RegExp( `<${ name }>(?:<!\\[CDATA\\[)?([\\s\\S]*?)(?:\\]\\]>)?</${ name }>` ) );
	return m ? m[ 1 ].trim() : '';
};

const term = ( xml, domain ) => {
	const m = xml.match( new RegExp( `<category domain="${ domain }" nicename="([^"]*)"` ) );
	return m ? m[ 1 ] : '';
};

/**
 * @param {string} xml Full WXR document.
 * @returns {{entries: object[], warnings: string[]}}
 */
export function buildEntries( xml ) {
	const warnings = [];

	const pages = xml
		.split( '<item>' )
		.slice( 1 )
		.map( ( chunk ) => '<item>' + chunk.split( '</item>' )[ 0 ] )
		.filter( ( chunk ) => tag( chunk, 'wp:post_type' ) === 'page' )
		.map( ( chunk ) => ( {
			id: tag( chunk, 'wp:post_id' ),
			title: tag( chunk, 'title' ),
			slug: decodeURIComponent( tag( chunk, 'wp:post_name' ) ),
			parent: tag( chunk, 'wp:post_parent' ),
			language: term( chunk, 'language' ) || DEFAULT_LANGUAGE,
			group: term( chunk, 'post_translations' ),
		} ) );

	const byId = new Map( pages.map( ( p ) => [ p.id, p ] ) );
	const kept = pages.filter( ( p ) => {
		if ( WORDPRESS_DEFAULTS.has( p.slug ) ) {
			warnings.push( `skipped "${ p.slug }" (ID ${ p.id }): a WordPress default page, never seeded.` );
			return false;
		}
		return true;
	} );

	// Group members are found by their shared post_translations term. A
	// non-default-language page with no group is an orphan translation: it
	// belongs to no entry, so declaring it is impossible and dropping it
	// quietly would hide a real content problem.
	const translations = new Map();
	for ( const page of kept ) {
		if ( page.language === DEFAULT_LANGUAGE || ! page.group ) {
			continue;
		}
		if ( ! translations.has( page.group ) ) {
			translations.set( page.group, [] );
		}
		translations.get( page.group ).push( page );
	}

	for ( const page of kept ) {
		if ( page.language !== DEFAULT_LANGUAGE && ! page.group ) {
			warnings.push( `orphan translation "${ page.slug }" (${ page.language }, ID ${ page.id }): no translation group, so no entry claims it.` );
		}
	}

	const entries = kept
		.filter( ( p ) => p.language === DEFAULT_LANGUAGE )
		.map( ( page ) => {
			const languages = {};
			for ( const t of translations.get( page.group ) ?? [] ) {
				languages[ t.language ] = { slug: t.slug, title: t.title };
			}

			return {
				key: page.slug,
				title: page.title,
				slug: page.slug,
				parent: page.parent !== '0' ? byId.get( page.parent )?.slug ?? null : null,
				languages,
			};
		} );

	return { entries, warnings };
}

const php = ( value ) => "'" + String( value ).replace( /\\/g, '\\\\' ).replace( /'/g, "\\'" ) + "'";

function render( entries ) {
	const lines = [];
	for ( const entry of entries ) {
		lines.push( `\t\t${ php( entry.key ) } => array(` );
		lines.push( `\t\t\t'title'   => ${ php( entry.title ) },` );
		lines.push( `\t\t\t'slug'    => ${ php( entry.slug ) },` );
		lines.push( `\t\t\t'pattern' => ${ php( 'workation/' + entry.key ) },` );
		if ( entry.parent ) {
			lines.push( `\t\t\t'parent'  => ${ php( entry.parent ) },` );
		}
		const languages = Object.entries( entry.languages );
		if ( languages.length > 0 ) {
			lines.push( `\t\t\t'languages' => array(` );
			for ( const [ code, override ] of languages ) {
				lines.push( `\t\t\t\t${ php( code ) } => array( 'slug' => ${ php( override.slug ) }, 'title' => ${ php( override.title ) } ),` );
			}
			lines.push( `\t\t\t),` );
		}
		lines.push( `\t\t),` );
	}
	return lines.join( '\n' );
}

if ( process.argv[ 1 ] && process.argv[ 1 ].endsWith( 'manifest-from-wxr.mjs' ) ) {
	const file = process.argv[ 2 ];
	if ( ! file ) {
		console.error( 'Usage: node tools/manifest-from-wxr.mjs <export.xml>' );
		process.exit( 1 );
	}
	const { entries, warnings } = buildEntries( fs.readFileSync( file, 'utf8' ) );
	for ( const warning of warnings ) {
		console.error( 'warning: ' + warning );
	}
	console.error( `${ entries.length } entries.` );
	console.log( render( entries ) );
}
```

- [ ] **Step 4: Run the tests to verify they pass**

Run: `node --test tools/manifest-from-wxr.test.mjs`
Expected: PASS, 5 tests.

- [ ] **Step 5: Commit**

```bash
git add tools/manifest-from-wxr.mjs tools/manifest-from-wxr.test.mjs
git commit -m "feat(tools): generate manifest entries from a WXR export"
```

---

### Task 7: Generate the manifest

**Files:**
- Create: `seed/manifest.php`

**Interfaces:**
- Consumes: `tools/manifest-from-wxr.mjs`, the plugin's `Manifest::fromArray()` schema, and the nav submenu support from the plugin half.
- Produces: the manifest every later task seeds from.

- [ ] **Step 1: Generate the entry list**

```bash
node tools/manifest-from-wxr.mjs /path/to/workationcastle.WordPress.xml > /tmp/entries.php
```
Expected on stderr: `18 entries.`, one skip warning for `beispiel-seite`, one for `datenschutzerklaerung`, and **no orphan-translation warnings**. A different entry count means the export is not the one this plan was designed against — stop and reconcile before continuing.

- [ ] **Step 2: Write `seed/manifest.php` around the generated entries**

Create `seed/manifest.php`, pasting the generated block where marked. The nav is hand-written because it encodes a structure the export does not contain:

```php
<?php
/**
 * Pediment seed manifest — Workation Castle.
 *
 * The entry list and its per-language overrides are GENERATED. Do not hand-edit
 * them: fix tools/manifest-from-wxr.mjs and regenerate, so re-running the
 * generator against a fresh export stays a meaningful drift check.
 *
 * @package Workation
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(
	'languages' => array(
		'en' => array( 'name' => 'English', 'locale' => 'en_US', 'default' => true ),
		'de' => array( 'name' => 'Deutsch', 'locale' => 'de_DE' ),
		'nl' => array( 'name' => 'Nederlands', 'locale' => 'nl_NL' ),
		'fr' => array( 'name' => 'Français', 'locale' => 'fr_FR' ),
		'it' => array( 'name' => 'Italiano', 'locale' => 'it_IT' ),
	),
	'pages'     => array(
// <<< paste /tmp/entries.php here >>>
	),
	'navs'      => array(
		'primary' => array(
			'title' => 'Primary',
			'items' => array(
				array( 'entry' => 'activities' ),
				array( 'entry' => 'photos' ),
				array(
					'entry'    => 'ways-to-stay',
					'children' => array(
						array( 'entry' => 'team-retreats' ),
						array( 'entry' => 'workations' ),
						array( 'entry' => 'family-and-groups' ),
					),
				),
				array(
					'entry'    => 'guide',
					'children' => array(
						array( 'entry' => 'arrival' ),
						array( 'entry' => 'check-in' ),
						array( 'entry' => 'map' ),
						array( 'entry' => 'faq' ),
					),
				),
				array( 'entry' => 'contact-us' ),
			),
		),
	),
);
```

Every `entry` item omits `label` on purpose (spec decision 4) so each language's menu takes that entry's own translated title. The nav tree deliberately differs from the page tree: `check-in` is a top-level page shown inside the Guide submenu, and `casa-galbiga` is a child page that appears in no menu.

- [ ] **Step 2b: Add `front_page` to the home entry**

The generator does not know which page is the front page. In the pasted block, add to the `home` entry:

```php
			'front_page' => true,
```

- [ ] **Step 3: Confirm the manifest parses**

```bash
npx wp-env run cli wp eval '\Pediment\Seeder\Manifest::resetCache(); $m = \Pediment\Seeder\Manifest::load(); echo count( $m->entries() ), " entries, ", count( $m->navs() ), " navs, ", count( $m->languages() ), " languages\n";'
```
Expected: `18 entries, 1 navs, 5 languages`. A `ManifestError` here names the exact path that is wrong.

- [ ] **Step 4: Confirm every declared pattern exists**

```bash
npx wp-env run cli wp eval 'foreach ( array( "activities","arrival","casa-galbiga","check-in","contact-us","faq","family-and-groups","feedback","guide","home","imprint","map","photos","privacy-policy","reviews","team-retreats","ways-to-stay","workations" ) as $k ) { if ( null === WP_Block_Patterns_Registry::get_instance()->get_registered( "workation/$k" ) ) { echo "MISSING workation/$k\n"; } }'
```
Expected: no output. A missing pattern means an entry key and its pattern file disagree — the generator derives the pattern name from the slug, and four pattern files are named after their *section* rather than their page (`ways-team-retreats.php`, `ways-workations.php`, `ways-family-and-groups.php`, `contact.php`). Fix by adding the correct `Slug:` header to those files, not by renaming manifest keys.

- [ ] **Step 5: Commit**

```bash
git add seed/manifest.php patterns/
git commit -m "feat: declare the site in a seed manifest"
```

---

### Task 8: The one-shot namespace rewrite tool

**Files:**
- Create: `inc/NamespaceRewrite.php`, `tests/phpunit/NamespaceRewriteTest.php`
- Modify: `functions.php`

**Interfaces:**
- Consumes: nothing from earlier tasks at runtime.
- Produces: `Workation\NamespaceRewrite::plan(): array{posts:int,blocks:int}` and `::apply(): int`, plus a Tools screen with preview and apply buttons.

At cutover, every claimed page's stored `post_content` still names `wp:pediment-child/*` while the theme now registers `workation/*`, so 23 blocks render as "block not found" until this runs.

- [ ] **Step 1: Write the failing test**

Create `tests/phpunit/NamespaceRewriteTest.php`:

```php
<?php
// tests/phpunit/NamespaceRewriteTest.php

use Workation\NamespaceRewrite;

class NamespaceRewriteTest extends WP_UnitTestCase {

	private const LEGACY = '<!-- wp:pediment-child/workation-hero {"headline":"Hi"} -->'
		. '<div class="wp-block-pediment-child-workation-hero">Hi</div>'
		. '<!-- /wp:pediment-child/workation-hero -->';

	private const REWRITTEN = '<!-- wp:workation/workation-hero {"headline":"Hi"} -->'
		. '<div class="wp-block-workation-workation-hero">Hi</div>'
		. '<!-- /wp:workation/workation-hero -->';

	private function page( string $content ): int {
		return self::factory()->post->create(
			[ 'post_type' => 'page', 'post_status' => 'publish', 'post_content' => $content ]
		);
	}

	public function test_a_plan_counts_without_writing() {
		$id = $this->page( self::LEGACY );

		$plan = NamespaceRewrite::plan();

		$this->assertSame( 1, $plan['posts'] );
		$this->assertSame( self::LEGACY, get_post( $id )->post_content, 'a plan never writes' );
	}

	public function test_apply_rewrites_openers_closers_and_classes() {
		$id = $this->page( self::LEGACY );

		$this->assertSame( 1, NamespaceRewrite::apply() );
		$this->assertSame( self::REWRITTEN, get_post( $id )->post_content );
	}

	public function test_plugin_blocks_are_never_touched() {
		$content = '<!-- wp:pediment/prose --><p>x</p><!-- /wp:pediment/prose -->';
		$id      = $this->page( $content );

		NamespaceRewrite::apply();

		$this->assertSame( $content, get_post( $id )->post_content );
	}

	public function test_it_is_idempotent() {
		$id = $this->page( self::LEGACY );

		NamespaceRewrite::apply();
		$this->assertSame( 0, NamespaceRewrite::apply(), 'nothing left to rewrite' );
		$this->assertSame( self::REWRITTEN, get_post( $id )->post_content );
	}

	public function test_the_trash_is_left_alone() {
		$id = $this->page( self::LEGACY );
		wp_trash_post( $id );

		NamespaceRewrite::apply();

		$this->assertStringContainsString( 'wp:pediment-child/', get_post( $id )->post_content );
	}
}
```

- [ ] **Step 2: Run it to verify it fails**

Run: `npx wp-env run tests-wordpress --env-cwd=wp-content/themes/workation ./vendor/bin/phpunit --filter NamespaceRewriteTest`
Expected: FAIL with `Class "Workation\NamespaceRewrite" not found`.

- [ ] **Step 3: Write the tool**

Create `inc/NamespaceRewrite.php`:

```php
<?php
/**
 * One-shot rewrite of stored block names from pediment-child/* to workation/*.
 *
 * TEMPORARY. Ships in 1.0.0, is run exactly once from wp-admin during the
 * cutover, and is deleted in the release that follows.
 *
 * Claimed pages keep their live post_content by design — the seeding engine
 * treats a row with a seed key but no hash as edited and never writes to it —
 * so the renamed blocks cannot arrive through a seed. Between activating this
 * theme and running this tool, every block whose name changed renders as
 * "block not found".
 *
 * Writes go through $wpdb, not wp_update_post(): this is a literal
 * substitution over already-valid stored markup, and running it back through
 * KSES, wptexturize and the block-validation filters risks changing bytes
 * nobody asked to change.
 *
 * @package Workation
 */

declare(strict_types=1);

namespace Workation;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class NamespaceRewrite {
	/** Post types whose content can carry this theme's blocks. */
	private const POST_TYPES = array( 'page', 'post', 'wc_activity', 'wc_photo', 'wp_template_part', 'wp_block' );

	/** Statuses considered. The trash is deliberately absent. */
	private const STATUSES = array( 'publish', 'draft', 'pending', 'private', 'future' );

	/**
	 * Ordered substitutions. The block-name pair covers openers and closers at
	 * once, because a closing delimiter `<!-- /wp:pediment-child/x -->`
	 * contains the opening needle verbatim.
	 */
	private const REPLACEMENTS = array(
		'wp:pediment-child/'       => 'wp:workation/',
		'wp-block-pediment-child-' => 'wp-block-workation-',
	);

	/** @return array{posts:int,blocks:int} */
	public static function plan(): array {
		$posts  = 0;
		$blocks = 0;

		foreach ( self::rows() as $row ) {
			$occurrences = substr_count( $row->post_content, 'wp:pediment-child/' );
			if ( 0 === $occurrences && ! str_contains( $row->post_content, 'wp-block-pediment-child-' ) ) {
				continue;
			}
			++$posts;
			$blocks += $occurrences;
		}

		return array(
			'posts'  => $posts,
			'blocks' => $blocks,
		);
	}

	/** @return int Posts actually rewritten. */
	public static function apply(): int {
		global $wpdb;

		$written = 0;

		foreach ( self::rows() as $row ) {
			$rewritten = strtr( $row->post_content, self::REPLACEMENTS );
			if ( $rewritten === $row->post_content ) {
				continue;
			}

			$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- deliberate: see the file docblock.
				$wpdb->posts,
				array( 'post_content' => $rewritten ),
				array( 'ID' => (int) $row->ID )
			);
			clean_post_cache( (int) $row->ID );
			++$written;
		}

		return $written;
	}

	/** @return object[] Rows of ID and post_content. */
	private static function rows(): array {
		global $wpdb;

		$types    = implode( ',', array_fill( 0, count( self::POST_TYPES ), '%s' ) );
		$statuses = implode( ',', array_fill( 0, count( self::STATUSES ), '%s' ) );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		return (array) $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ID, post_content FROM {$wpdb->posts} WHERE post_type IN ({$types}) AND post_status IN ({$statuses})",
				array_merge( self::POST_TYPES, self::STATUSES )
			)
		);
		// phpcs:enable
	}

	/** Register the Tools screen. */
	public static function register_admin(): void {
		add_action( 'admin_menu', array( __CLASS__, 'add_page' ) );
		add_action( 'admin_post_workation_namespace_rewrite', array( __CLASS__, 'handle' ) );
	}

	public static function add_page(): void {
		add_management_page(
			__( 'Rewrite block namespace', 'workation' ),
			__( 'Rewrite block namespace', 'workation' ),
			'manage_options',
			'workation-namespace-rewrite',
			array( __CLASS__, 'render' )
		);
	}

	public static function render(): void {
		$plan = self::plan();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Rewrite block namespace', 'workation' ); ?></h1>
			<p>
				<?php
				printf(
					/* translators: 1: number of posts, 2: number of blocks. */
					esc_html__( '%1$d posts still carry %2$d pediment-child blocks.', 'workation' ),
					(int) $plan['posts'],
					(int) $plan['blocks']
				);
				?>
			</p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="workation_namespace_rewrite">
				<?php wp_nonce_field( 'workation_namespace_rewrite' ); ?>
				<?php submit_button( __( 'Rewrite now', 'workation' ) ); ?>
			</form>
		</div>
		<?php
	}

	public static function handle(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'workation' ) );
		}
		check_admin_referer( 'workation_namespace_rewrite' );

		$written = self::apply();

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => 'workation-namespace-rewrite',
					'written' => $written,
				),
				admin_url( 'tools.php' )
			)
		);
		exit;
	}
}
```

- [ ] **Step 4: Wire it up**

In `functions.php`, next to the other `require_once` lines:

```php
// TEMPORARY: the cutover's one-shot block-namespace rewrite. Delete in the
// release after the cutover — see inc/NamespaceRewrite.php.
require_once __DIR__ . '/inc/NamespaceRewrite.php';
if ( is_admin() ) {
	Workation\NamespaceRewrite::register_admin();
}
```

- [ ] **Step 5: Run the tests to verify they pass**

Run: `npx wp-env run tests-wordpress --env-cwd=wp-content/themes/workation ./vendor/bin/phpunit --filter NamespaceRewriteTest`
Expected: PASS, 5 tests.

- [ ] **Step 6: Commit**

```bash
git add inc/NamespaceRewrite.php tests/phpunit/NamespaceRewriteTest.php functions.php
git commit -m "feat: add the one-shot block namespace rewrite"
```

---

### Task 9: Repoint the environment and CI

**Files:**
- Modify: `.wp-env.json`, `.github/workflows/ci.yml`
- Delete: `.github/workflows/build-release-zip.yml`, `.github/workflows/check-wpenv-deps.yml`, `.github/workflows/release.yml`
- Create: `.github/workflows/client-ci.yml`

**Interfaces:**
- Consumes: the monorepo's reusable `client-theme.yml` and `client-release.yml`.
- Produces: CI that boots the plugin plus this theme and seeds it.

- [ ] **Step 1: Drop the parent theme from the dev environment**

Replace `.wp-env.json`:

```json
{
	"core": "WordPress/WordPress#6.9",
	"phpVersion": "8.1",
	"themes": [ "." ],
	"plugins": [
		"https://github.com/Bergert-Digital/pediment/releases/latest/download/pediment-plugin.zip",
		"https://downloads.wordpress.org/plugin/polylang.zip"
	],
	"port": 8890,
	"testsPort": 8891,
	"config": {
		"WP_DEBUG": true,
		"WP_DEBUG_LOG": true,
		"WP_DEBUG_DISPLAY": false,
		"SCRIPT_DEBUG": true
	}
}
```

Polylang is added because the manifest declares five languages, and without it `LanguageRegistry::provider()` returns `NullProvider` and nothing multilingual can be exercised locally.

- [ ] **Step 2: Recreate the environment and confirm it boots**

```bash
npx wp-env destroy && npx wp-env start
npx wp-env run cli wp theme list
npx wp-env run cli wp plugin list
```
Expected: `workation` active, `pediment` and `polylang` active, and **no** `pediment` *theme* — the parent is gone.

- [ ] **Step 3: Call the reusable workflows**

Create `.github/workflows/client-ci.yml`:

```yaml
name: Client theme CI

on:
  push:
    branches: [ main ]
  pull_request:

jobs:
  seed:
    uses: Bergert-Digital/pediment/.github/workflows/client-theme.yml@main
```

Delete the workflows the monorepo now owns:

```bash
git rm .github/workflows/build-release-zip.yml .github/workflows/check-wpenv-deps.yml .github/workflows/release.yml
```

Keep `.github/workflows/ci.yml` for this repo's own phpcs, PHPUnit and Playwright jobs, and keep `release-please.yml`. Edit `ci.yml` so every `--env-cwd` path says `wp-content/themes/workation`.

- [ ] **Step 4: Add the release workflow**

Create `.github/workflows/client-release.yml`. The reusable workflow takes exactly one input, `tag`, and resolves the theme slug from `style.css` itself:

```yaml
name: Client theme release

on:
  push:
    tags: [ "v*" ]

jobs:
  release:
    uses: Bergert-Digital/pediment/.github/workflows/client-release.yml@main
    with:
      tag: ${{ github.ref_name }}
```

- [ ] **Step 5: Commit**

```bash
git add .wp-env.json .github/workflows/
git commit -m "ci: build against the plugin, drop the parent theme"
```

---

### Task 10: Prove it end to end

**Files:**
- Modify: Playwright specs left failing by Task 2

**Interfaces:**
- Consumes: everything above.
- Produces: the evidence that step 6b is done.

- [ ] **Step 1: Configure languages, then seed**

```bash
npx wp-env run cli wp pediment languages
npx wp-env run cli wp pediment seed --dry-run
```
Expected from the dry run: 18 entries × 5 languages planned as creates, 5 navs, and a `TRANSLATIONS` section listing the languages with no declared title. Read the plan before applying it. Then:

```bash
npx wp-env run cli wp pediment seed
```

- [ ] **Step 2: Verify the shape of what was written**

```bash
npx wp-env run cli wp eval 'echo count( get_posts( array( "post_type" => "page", "posts_per_page" => -1, "lang" => "" ) ) ), " pages\n";'
npx wp-env run cli wp eval 'echo count( get_posts( array( "post_type" => "wp_navigation", "posts_per_page" => -1, "lang" => "" ) ) ), " navs\n";'
```
Expected: 90 pages (18 × 5) and 5 navs.

- [ ] **Step 3: Verify the submenus survived**

```bash
npx wp-env run cli wp eval '$n = get_posts( array( "post_type" => "wp_navigation", "posts_per_page" => 1, "lang" => "" ) )[0]; echo substr_count( $n->post_content, "<!-- wp:navigation-submenu " ), " submenus, ", substr_count( $n->post_content, "wp:navigation-link" ), " links\n";'
```
Expected: `2 submenus, 10 links`. Anything else means the manifest's nav or the plugin's serializer disagrees with this plan — stop and reconcile before going further.

- [ ] **Step 4: Verify the branded header was seeded**

```bash
npx wp-env run cli wp eval '$p = get_posts( array( "post_type" => "wp_template_part", "post_name__in" => array( "header" ), "posts_per_page" => -1 ) ); echo count( $p ), " header parts\n"; echo ( $p && str_contains( $p[0]->post_content, "site-header" ) ) ? "branded\n" : "GENERIC FALLBACK\n";'
```
Expected: `1 header parts` and `branded`. `GENERIC FALLBACK` means `workation/header` was not registered when the theme was activated — re-check Task 5.

- [ ] **Step 5: A second seed changes nothing**

```bash
npx wp-env run cli wp pediment seed --dry-run
```
Expected: every entry `unchanged`, every nav `unchanged`, zero to write. A nav reporting `update` here means `serialize()` is not byte-stable, which would rewrite all five menus on every future run.

- [ ] **Step 6: Fix the Playwright specs**

Bring the specs Task 2 left failing back to green against the seeded site. Update URLs and expected labels where the retired custom nav labels changed them ("Guest Guide" → "Guide", "Checking in" → "Check-in", "How to get here" → "Arrival", "Find your way around" → "Map", and the removed "More" item). Do not weaken assertions.

```bash
npx playwright test
```
Expected: PASS, at Task 0's spec count minus any spec deleted for testing removed behaviour.

- [ ] **Step 7: Diff against the reference scaffold**

Compare the converted repo against `/tmp/workation` from Task 0:

```bash
diff -rq --exclude=node_modules --exclude=.git --exclude=build . /tmp/workation | grep -v "^Only in \."
```
Read every "Only in /tmp/workation" line — each names a file a freshly scaffolded client theme has and this one does not. Add the ones that matter (`AGENTS.md` conventions, `docs/`, `.gitignore` entries such as `build/`); deliberately skip the ones that do not. Record which were skipped and why in the commit message.

- [ ] **Step 8: Run every gate**

```bash
npx wp-env run tests-wordpress --env-cwd=wp-content/themes/workation ./vendor/bin/phpunit
npx playwright test
composer phpcs
npm run build
```
Expected: all green.

- [ ] **Step 9: Commit**

```bash
git add tests/ .gitignore AGENTS.md
git commit -m "test: prove the manifest builds the whole site"
```

---

### Task 11: Release 1.0.0

**Files:**
- Modify: `README.md`, `docs/` in the Workation repo

**Interfaces:**
- Consumes: a green Task 10.
- Produces: the theme zip step 6c installs.

- [ ] **Step 1: Update the repo's own documentation**

Rewrite `README.md`'s description of the theme: it is a standalone Pediment client theme, its content lives in `seed/manifest.php`, it is seeded with `wp pediment seed` (or the wp-admin Seeding tab), and it updates by admin zip upload rather than an auto-updater. Delete instructions that reference the parent theme, `Tools → Seed content`, or the theme updater.

- [ ] **Step 2: Record the temporary tool's removal date**

Add a note to `README.md` stating that `inc/NamespaceRewrite.php` is a cutover-only tool to be deleted in the release after the cutover, so it cannot be forgotten.

- [ ] **Step 3: Commit**

```bash
git add README.md docs/
git commit -m "docs: describe the standalone client theme"
```

- [ ] **Step 4: STOP — ask before pushing**

Report every gate's result from Task 10 Step 8, the entry/nav/language counts from Task 10 Steps 2-3, and the list of reference-scaffold files deliberately skipped. Then ask whether to push. Do not push without an explicit yes.

- [ ] **Step 5: After approval, push and verify the release**

Push, confirm CI is green including the `seed` job from the reusable workflow, merge the release-please PR, and confirm the published zip's top directory is `workation` and its `style.css` version is `1.0.0`.

- [ ] **Step 6: Hand off to step 6c**

Step 6b ends here. The cutover — claiming the live staging rows, the nav inventory, running the namespace rewrite, re-setting the custom logo and site icon, and the `LegacyBlockCopy.php` decision — belongs to the step 6c plan, which is written against a fresh export taken at cutover time.
