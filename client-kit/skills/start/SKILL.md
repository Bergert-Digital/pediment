---
name: start
description: Create a new Pediment client site — scaffold a standalone client theme repo, brand it, seed it, and report the local URL. Use when starting a new client project, whether porting an existing site or starting fresh.
allowed-tools: Bash(node ${CLAUDE_SKILL_DIR}/../../scripts/scaffold.mjs:*)
---

# Start a Pediment client site

Take a client from nothing to a seeded, rendering local site in one session. You ask only what
cannot be derived; a deterministic scaffolder does everything that must be identical every time.

Claude Code prepends `Base directory for this skill: <absolute path>` when this skill loads. Call
that absolute directory `<skill-dir>` for this run. Resolve every bundled file from it:

- kit manifest: `<skill-dir>/../../.claude-plugin/plugin.json`
- reference answers: `<skill-dir>/../../tests/fixtures/answers-greenfield.json`
- scaffolder: `<skill-dir>/../../scripts/scaffold.mjs`

Never resolve those paths from the client repo's working directory, and do not expect
`CLAUDE_PLUGIN_ROOT` to exist in a Bash tool call.

All per-run scratch files go under `.context/start/` (gitignored) in the directory you are
standing in.

---

## Phase 0 — prerequisites (do this before asking anything)

Check all three. Report **every** failure together with the command that fixes it, then stop.

```bash
docker info >/dev/null 2>&1 && echo "docker: ok" || echo "docker: NOT RUNNING — start Docker Desktop"
node -e "process.stdout.write(process.versions.node)" && echo " node: ok" || echo "node: MISSING"
git --version >/dev/null 2>&1 && echo "git: ok" || echo "git: MISSING"
```

Node must be 20 or newer. `gh` is NOT checked here — it is only needed if the user opts into
creating a GitHub remote at the very end.

---

## Phase 1 — the questionnaire

**One question per message.** Derive everything you can and show it for confirmation instead of
asking. Open with the branching question:

> Are we porting an existing site, or starting fresh?

### If porting

1. **Source URL.** Load it in the browser (Chrome — see the user's browser rules).
2. **Brand.** Read the homepage's computed styles: button background (→ accent), band background
   or heading colour (→ primary), body colour (→ foreground), the first `font-family`, and the
   first border-radius. Show the extracted values and ask only "does this look right?". Do not ask
   the user to name colours they already have.
3. **Pages.** Fetch `/sitemap.xml` (fall back to `/sitemap_index.xml`, then to the nav links on the
   homepage). Present the list **pre-checked** and ask which to drop. The client template ships
   pattern files for exactly four keys — `home`, `about`, `services`, `contact` — so only pages
   using those keys can be seeded on the first run. Tell the user which sitemap pages fall outside
   that set (e.g. `/team/`, `/pricing/`); do not add them to this answers file. Seed the four first,
   then add each remaining page afterward with `/pediment:port-page`, one at a time.
4. **Languages.** Read `<link rel="alternate" hreflang="…">` from the homepage. Present pre-filled
   and ask only for confirmation.
5. **Client name and repo slug.** Derive the slug from the name (lowercase, hyphens); show both.

### If starting fresh

1. **Business name.** Derive the repo slug and theme name; show for confirmation.
2. **What they do, and for whom** — and, in the same question, the tone they want. State plainly:
   *"This goes into `docs/brief.md` for you and future agents to read. Nothing in the plugin reads
   it programmatically yet."* Do not imply the AI features will pick it up.
3. **Languages.** Default first. Most sites are one language — offer that as the default answer.
4. **Sitemap.** Offer Home / About / Services / Contact pre-checked, plus an optional Blog.
5. **Accent colour.** A single hex. Everything else in the palette derives from it.
6. **Logo** — a file path, optional.

---

## Phase 2 — write the answers file

Write `.context/start/answers.json`. This exact shape is what `scaffold.mjs` consumes;
`<skill-dir>/../../tests/fixtures/answers-greenfield.json` is the reference instance.

```json
{
  "version": 1,
  "mode": "greenfield",
  "client": { "name": "Acme Roofing", "slug": "acme-roofing", "description": "One line." },
  "brief": { "does": "…", "audience": "…", "tone": "…", "sourceUrl": null },
  "brand": {
    "accent": "#B91C1C",
    "primary": "#1F2937",
    "foreground": "#1F2937",
    "font": { "family": "Inter", "weights": ["400", "700"] },
    "source": "chosen"
  },
  "languages": [{ "slug": "en", "name": "English", "locale": "en_US", "flag": "gb", "default": true }],
  "pages": [
    { "key": "home", "title": "Home", "frontPage": true },
    { "key": "about", "title": "About" },
    { "key": "contact", "title": "Contact" }
  ],
  "nav": ["about", "contact"],
  "logo": null,
  "plugin": { "version": "3.3.0" },
  "template": { "version": "3.3.0" }
}
```

Rules:

- `mode` is `"port"` or `"greenfield"`. When porting, set `brief.sourceUrl` and
  `brand.source` to `"extracted:<url>"`.
- Page `key`s must be lowercase-hyphenated. A blog index page gets `"postsPage": true` and no
  pattern file.
- Only `home`, `about`, `services`, `contact` have a pattern file in the client template out of
  the box. A page with any other key makes `scaffold.mjs` refuse before writing anything — add
  pages beyond those four with `/pediment:port-page` after the first seed, not by listing them
  here.
- `logo` is `null`, or `{ "file": "logo.svg", "sourcePath": "<absolute path to the file>" }`.
  `file` is the name it will have inside `seed/media/`; `sourcePath` is where to copy it from.
- `plugin.version` / `template.version`: use the latest published release that provides
  `wp pediment seed` — not simply the latest release. Resolve candidates with
  `gh release list --repo Bergert-Digital/pediment --limit 1`, or ask the user if `gh` is
  unavailable. Phase 3 verifies the chosen release actually has the command before seeding and
  stops with a clear message if it does not, rather than discovering it at `seed:plan`.
- Ask the user where the repo should go and confirm the absolute path before writing anything —
  the target directory's **basename must equal `client.slug` exactly**; `scaffold.mjs` refuses
  otherwise, because wp-env derives the in-container theme directory name from it.

---

## Phase 3 — scaffold and boot

```bash
node "<skill-dir>/../../scripts/scaffold.mjs" --answers .context/start/answers.json --target <absolute path> --template client-template
```

Omitting `--template` makes the scaffolder download `pediment-client-template.zip` for the version
named in the answers file instead. That asset does not exist in any release yet, so until it
ships, always pass `--template client-template` when running from a monorepo checkout — dropping
it fails on the very first run.

The scaffolder refuses a target path containing whitespace or a non-empty target directory, and
commits the result before anything else runs. Then, in the new directory:

```bash
npm install
npm run env:start
npx wp-env run cli wp pediment seed --help
```

**Stop here if the last command fails.** It means the plugin release named in `answers.json` does
not provide `wp pediment seed` — it predates the seeding engine. Do not continue to `seed:plan`;
it fails there too, with a less clear error. Tell the user which plugin version was used and that a
release providing `wp pediment seed` is required (see `docs/client-sites.md` in the monorepo).

```bash
npm run languages    # only if the manifest has a `languages` section
npm run seed:plan
```

**Show the user the plan before applying it.** Then:

```bash
npm run seed
```

Report the local URL (http://localhost:8888), the wp-admin URL, and what to do next.

---

## Phase 4 — hand off

- **Greenfield:** tell the user which pattern files to edit (`patterns/<key>.php`), and that
  `npm run seed` re-applies them. Offer to build the first page with them.
- **Port:** hand over the page list and run `/pediment:port-page <url>` per page, one at a time.

Offer to create a GitHub remote **last**, and only with explicit confirmation:

```bash
gh repo create <owner>/<slug> --private --source=. --push
```

Never push without the user saying yes.

---

## If something fails

- **Docker not running** → phase 0 catches it; nothing has been written.
- **`wp-env start` fails** → the repo is already committed, so nothing is lost. Fix Docker, then
  resume from `npm run env:start`.
- **Re-running `/start` in a directory that already has `seed/manifest.php`** → do not scaffold
  again. Say what is already there and offer to resume from phase 3's boot step.
- **The scaffold command was run without `--template`** → it will fail trying to download
  `pediment-client-template.zip`. This is expected until a release ships that asset, not an
  intermittent fault — re-run with `--template <path to a client-template checkout>` (e.g.
  `client-template` from a monorepo checkout).
- **The seed reports problems** → stop and show them. Never re-run a seed to "try again"; the plan
  is deterministic, so a problem repeats until the manifest or a pattern file changes.
- **`wp pediment seed --help` fails right after `env:start`** → the plugin release used does not
  provide the seed command yet — expected against any release before one ships that includes the
  seeding engine, not an intermittent fault. Do not proceed to `seed:plan`. See
  `docs/client-sites.md` for the current status of published releases.
