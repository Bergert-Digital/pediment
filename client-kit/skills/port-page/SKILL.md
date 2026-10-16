---
name: port-page
description: Rebuild one existing page as native blocks in a scaffolded client theme. The seed creates the page once; every later change goes through the pediment-write-page MCP tool, iterating under an independent fidelity critic until it matches the source; then adopt it back into git. Use after /pediment:start has scaffolded and seeded the site.
allowed-tools: Bash(node ${CLAUDE_SKILL_DIR}/../../scripts/validate-blocks.mjs:*)
---

# Port one page to Pediment

Rebuild ONE existing public page as native blocks, iterate under an independent visual fidelity
critic, then commit the result to git as a pattern file.

The seeder bootstraps: `npm run seed` creates the page once, from its pattern file, and never
updates it again. Every change after that goes to the live page through the `pediment-write-page`
MCP tool. The pattern file is refreshed at the end with `npm run adopt`, for new environments.

Claude Code prepends `Base directory for this skill: <absolute path>` when this skill loads. Call
that directory `<skill-dir>` and resolve the bundled review instructions as
`<skill-dir>/../../shared/fidelity-critic-prompt.md` and
`<skill-dir>/../../shared/visual-qa.md`. Never resolve them from the client theme's working
directory.

**Argument:** the source page URL. Derive `<key>` from its path (`/about-us/` → `about-us`;
homepage → `home`).

All per-run files go under `.context/port/<key>/` (gitignored).

---

## 1. Preconditions

Check all four. Stop on the first failure with the stated message.

1. **wp-env is running** — `npx wp-env run cli wp option get siteurl`. If it errors: "wp-env is not
   running — start it with `npm run env:start`, then re-run `/pediment:port-page`."
2. **The site is seeded** — `npx wp-env run cli wp pediment seed --dry-run` must succeed. If the
   manifest is missing: "This is not a scaffolded Pediment client theme. Run `/pediment:start`
   first."
3. **The source URL is publicly reachable** — load it in the browser (Chrome). Stop on an error or
   a redirect loop.
4. **The Pediment MCP server is connected** — the `pediment-get-vocabulary` tool is available. If
   it is not, first check that the MCP Adapter plugin is active:
   `npx wp-env run cli wp plugin is-active mcp-adapter`. A site scaffolded before the client
   template shipped the adapter does not have it, and the MCP endpoint does not exist there. Add
   `"https://downloads.wordpress.org/plugin/mcp-adapter.0.7.0.zip"` to the `plugins` array in
   `.wp-env.json`, run `npm run env:start`, then
   `npx wp-env run cli wp plugin activate mcp-adapter` (an older `env:start` does not activate it).
   Then create an application password with
   `npx wp-env run cli wp user application-password create admin claude --porcelain`, then run
   `claude mcp add --transport http pediment "<siteurl>/?rest_route=/pediment/v1/mcp" --header "Authorization: Basic $(printf 'admin:<password>' | base64)"`
   with the site URL from check 1, and stop: "The Pediment MCP server is now configured —
   restart Claude Code in this directory, then re-run `/pediment:port-page`."

---

## 2. Capture the source

Screenshot the source page full-height at 1440px and at 375px into `.context/port/<key>/`.
Extract its text content and section structure. Note the section order — it is what the critic
compares against.

---

## 3. Declare the page

Add the entry inside the existing `'pages' => array( ... )` block in `seed/manifest.php` — a bare
top-level key is rejected outright. `Manifest::SECTIONS` only allows `version`, `languages`,
`pages`, `posts`, `entries`, `media`, `navs`, `post_types`, `site`; an unrecognised top-level key
throws `ManifestError` and breaks the whole manifest, not just this page.

Both the manifest `pattern` value and the pattern file's `Slug:` header below need `<theme-slug>` —
derive it from `package.json`'s `name`, or from an existing `patterns/*.php` file's own `Slug:`
header. Getting it wrong is not always loud: for the default language, `ContentResolver` throws
`ManifestError` when the pattern it looks up is not registered, so a wrong namespace on this page
aborts the seed with an error naming the slug it tried. It is only a *translation* pattern (a
non-default-language `patterns/<key>.<lang>.php`) that degrades quietly — falling back to the
default language's content with no error at all. Get the namespace right regardless:

```php
'<key>' => array( 'title' => '<Title>', 'pattern' => '<theme-slug>/<key>' ),
```

Create `patterns/<key>.php` with the standard header — the `Slug:` header must be
`<theme-slug>/<key>` exactly, because that is what the seeder looks up in the pattern registry, not
the filename:

```php
<?php
/**
 * Title: <Title>
 * Slug: <theme-slug>/<key>
 * Categories: pediment
 * Inserter: no
 */
// phpcs:ignoreFile -- block pattern content
?>
```

Do not seed yet. The seed creates the page once, from the pattern as it is at that moment.

---

## 4. Build the first version

Author the page's markup in `patterns/<key>.php`, below the header. Call `pediment-get-vocabulary`
first and use the blocks and named styles it lists. Never set custom colours, font sizes or
spacing — the brand is in `theme.json` and the named styles, and hardcoding defeats it. Do not
use the legacy `pediment/*` presentation blocks (the ones `pediment-check-page` reports as
legacy): Pediment 4.0 hides them from the inserter, and 5.0 removes them.

---

## 5. Validate, then seed

Validate the pattern before seeding it. The editor rejects a static block whose markup does not
match what it generates, and the page then opens as "invalid content".

```bash
node ${CLAUDE_SKILL_DIR}/../../scripts/validate-blocks.mjs --file patterns/<key>.php
```

Fix every INVALID, STALE, LOOSE and JSON line and re-run until it exits 0. Blocks listed under
"not checked" are Pediment or theme blocks the validator does not know; that is expected. The
validator resolves `{{media_id:<key>}}` and `{{media_url:<key>}}` placeholders itself, so adopted
patterns validate as they are; an id such as 900001 in a message stands for such a placeholder.

The first run installs a pinned WordPress runtime (about 700 MB, once) into
`~/.cache/pediment/validator`, so tell the user why it is slow. Pass `--wp <version>` when the site
does not run WordPress 6.9. Exit code 2 means the tool itself failed (for example no network on
the first run), not that the page is invalid.

Then `npm run seed:plan` — expect one `create` line for `<key>` per declared language (one line on
a monolingual site) — and `npm run seed`. On a multilingual site the seed creates the page in every
declared language, with the default-language content where no translation pattern exists, and links
them into one translation group.

Note the ID of each new page. Read them from the seed's own plan, which finds pages by their seed
key and language, never by slug (a slug lookup misses a nested key, a custom slug and every other
language):

```bash
npx wp-env run cli wp pediment seed --dry-run --format=json
```

Each item with `"kind": "entry"` and `"key": "<key>"` is one page: `language` is its language code
(empty on a monolingual site) and `post_id` is its ID.

---

## 6. Iterate under the critic

Screenshot the rebuilt page at the same two widths. Read
`<skill-dir>/../../shared/fidelity-critic-prompt.md`. Before dispatch, replace its
`{{RUBRIC_PATH}}` placeholder with the resolved absolute path of
`<skill-dir>/../../shared/visual-qa.md`, fill the remaining placeholders, and send the resulting
prompt verbatim to an independent critic with the source and rebuilt screenshots.

Fix what it names on the live page, never by editing the pattern file and re-seeding (a seed does
not update a page that exists):

1. Read the current markup with `pediment-read-page` (`id` = the page ID) and keep a working copy
   in `.context/port/<key>/page.html`. It carries real media URLs, not placeholders.
2. Edit the working copy, then validate it:
   `node ${CLAUDE_SKILL_DIR}/../../scripts/validate-blocks.mjs --file .context/port/<key>/page.html`.
3. Write it with `pediment-write-page` (`id`, `title`, `markup`). A rejected write lists every rule
   it broke; fix them all and write again.

Re-screenshot and re-run the critic. Stop when it reports no material differences, or after four
rounds — then report honestly what still differs and why.

Run the checks in `<skill-dir>/../../shared/visual-qa.md` before declaring the page done.

---

## 7. Persist

Export the live page into its pattern file, so the first seed of a new environment creates the
page as it is now:

```bash
npm run adopt -- <key>
```

Re-run the validator from step 5 on the adopted file. Read the diff before committing — `adopt`
does not convert sized image variants or `srcset` URLs to placeholders, so a page with responsive
images can carry environment-specific URLs into git.

Commit `seed/manifest.php` and `patterns/<key>.php` together.

---

## Multilingual pages

Port the default language first. The seed in step 5 already created the page in every other
declared language and linked it into the translation group. Never create a second page for one of
these languages: the seeder does not know it, and the group then holds the wrong page.

For each additional language, take that language's `post_id` from the step 5 JSON. Write the
translation with `pediment-write-page` (`id` = that page ID, `title`, `markup`), or translate that
page in the editor. Use `language` and `translation_of` only for a language the seed did not create
(the step 5 JSON has no item for it); that creates a new page without a seed key, so tell the user.

Then run `npm run adopt -- <key> --language=<code>` — note the single
`--`; npm forwards everything after it verbatim, and a second `--` would arrive at
`wp pediment adopt` as a literal extra positional argument, which WP-CLI rejects with "Too many
positional arguments: --". This writes
`patterns/<key>.<lang>.php` with the correct `Slug: <theme-slug>/<key>-<lang>` header. The filename
suffix and the header suffix must agree, or the translated pattern is reported missing exactly as if
the file did not exist.
