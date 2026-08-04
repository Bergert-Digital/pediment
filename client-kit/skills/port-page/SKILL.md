---
name: port-page
description: Rebuild one existing page as native Pediment blocks in a scaffolded client theme, iterating under an independent fidelity critic until it matches the source, then adopt it back into git. Use after /pediment:start has scaffolded and seeded the site.
---

# Port one page to Pediment

Rebuild ONE existing public page as native Pediment blocks, iterate under an independent visual
fidelity critic, then commit the result to git as a pattern file.

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

Check all three. Stop on the first failure with the stated message.

1. **wp-env is running** — `npx wp-env run cli wp option get siteurl`. If it errors: "wp-env is not
   running — start it with `npm run env:start`, then re-run `/pediment:port-page`."
2. **The site is seeded** — `npx wp-env run cli wp pediment seed --dry-run` must succeed. If the
   manifest is missing: "This is not a scaffolded Pediment client theme. Run `/pediment:start`
   first."
3. **The source URL is publicly reachable** — load it in the browser (Chrome). Stop on an error or
   a redirect loop.

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

Then `npm run seed:plan`, read the plan, and `npm run seed`.

---

## 4. Build the page

Two routes; pick per page and say which you are using.

- **Author the pattern file directly** when the structure is clear from the source. Faster, and the
  diff is readable.
- **Build it in the block editor**, then `npm run adopt -- <key>` to write it back into
  `patterns/<key>.php` with media URLs converted to `{{media_*}}` placeholders. Use this when the
  layout needs to be seen to be got right.

Prefer a purpose-built `pediment/*` block over composing primitives. Never set custom colours, font
sizes or spacing — the brand is in `theme.json` and hardcoding defeats it.

---

## 5. Iterate under the critic

Screenshot the rebuilt page at the same two widths. Dispatch an independent critic with
`<skill-dir>/../../shared/fidelity-critic-prompt.md`, giving it the source and rebuilt screenshots. Fix what it
names, re-seed, re-screenshot, re-run. Stop when it reports no material differences, or after
four rounds — then report honestly what still differs and why.

Run the checks in `<skill-dir>/../../shared/visual-qa.md` before declaring the page done.

---

## 6. Persist

If the page was built in the editor, adopt it now:

```bash
npm run adopt -- <key>
```

Read the diff before committing — `adopt` does not convert sized image variants or `srcset` URLs to
placeholders, so a page with responsive images can carry environment-specific URLs into git.

Commit `seed/manifest.php` and `patterns/<key>.php` together.

---

## Multilingual pages

Port the default language first. For each additional language, translate the page in the editor and
run `npm run adopt -- <key> --language=<code>` — note the single `--`; npm forwards everything after
it verbatim, and a second `--` would arrive at `wp pediment adopt` as a literal extra positional
argument, which WP-CLI rejects with "Too many positional arguments: --". This writes
`patterns/<key>.<lang>.php` with the correct `Slug: <theme-slug>/<key>-<lang>` header. The filename
suffix and the header suffix must agree, or the translated pattern is reported missing exactly as if
the file did not exist.
