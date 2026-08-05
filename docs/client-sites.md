# Building and maintaining a client site

A client site is a standalone WordPress theme repo that pairs with the Pediment WordPress plugin.
Pediment also ships a Claude Code developer kit that creates and maintains those theme repos. The
two plugins have different runtimes: WordPress installs `pediment-plugin.zip`; developers install
`client-kit/` once in Claude Code.

## The three units

- **The Pediment WordPress plugin** — the product. It supplies blocks, templates, tokens, forms,
  seeding, and AI features to WordPress and ships as `pediment-plugin.zip`.
- **The Pediment client kit** — the Claude Code tooling under `client-kit/`. It carries
  `/pediment:start`, `/pediment:port-page`, and `scripts/scaffold.mjs`. It is installed globally in
  Claude Code and is never copied into a client theme.
- **`pediment-client-template.zip`** — the tokenised standalone theme template. The scaffolder
  downloads the release asset and rewrites it into a client-owned repo.

This monorepo owns all three sources and the reusable client CI/release workflows. An external
client developer never clones it.

## Making a site

Install the developer kit once in Claude Code:

```text
/plugin marketplace add Bergert-Digital/pediment
/plugin install pediment@pediment
```

Then, in the empty directory (or its parent) where the new site should live:

```
/pediment:start
```

The skill checks Docker, Node, and git are available, then asks a short questionnaire — one
question per message. It branches immediately on:

> Are we porting an existing site, or starting fresh?

**Starting fresh** asks six questions: business name (derives the repo slug), what the business
does and for whom (with tone, in the same message — this is written into `docs/brief.md`, see
below), languages (defaults to one), a pre-checked sitemap (Home / About / Services / Contact,
plus optional Blog), an accent colour (the rest of the palette derives from it), and an optional
logo file path. The skill states this plainly when it asks: `docs/brief.md` is for you and future
agents to read; **nothing in the plugin reads it programmatically**. Typing a considered tone
into that question does not make the AI editor use it — see "What a scaffolded repo contains"
below.

**Porting an existing site** asks a different five: the source URL, brand (extracted from the
live site's computed styles and shown for confirmation, not asked for by name), the page list
(from `/sitemap.xml`, pre-checked), languages (from `hreflang` tags, pre-filled), and the client
name and repo slug.

Either way, the skill writes `.context/start/answers.json`, reads version `V` from its installed
`.claude-plugin/plugin.json`, and uses `V` for both `plugin.version` and `template.version`. It then
runs the bundled scaffolder from the absolute injected skill directory:

```bash
node "<skill-dir>/../../scripts/scaffold.mjs" --answers .context/start/answers.json --target <path>
npm install
npm run env:start
npx wp-env run cli wp pediment seed --help
npm run languages    # only if the manifest has a languages section
npm run seed:plan    # shown to you before anything is applied
npm run seed
```

The scaffolder downloads `pediment-client-template.zip` from release `vV`; the generated
`.wp-env.json` downloads `pediment-plugin.zip` from that same release. The v3.0.0 release predates
the seeding engine and template asset, so it cannot complete this flow. The first later release
containing this distribution work is the minimum supported external version.

It reports the local URL (`http://localhost:8888`) and the wp-admin URL when done.

## What a scaffolded repo contains

```
<slug>/
  style.css               Theme header — Version:, Text Domain:
  theme.json              Brand tokens, derived from the accent colour
  templates/index.html
  patterns/*.php          Content, one pattern per page
  seed/manifest.php       Structure: pages, nav, media, languages
  seed/media/             Logo and other seeded media files
  docs/brief.md           What the business does, for whom, in what tone —
                          read by humans and agents, read by nothing in the
                          plugin: it does not feed the AI editor or anything
                          else programmatically
  AGENTS.md               The client repo's own hard rules for working here
                          (never `wp post create`/`update`, `seed:plan` before
                          `seed`, `adopt -- <key>` to capture editor edits)
  README.md               Local-dev, day-two, and deploy quickstart — a condensed
                          version of this whole doc, scoped to this one site
  .wp-env.json
  .gitignore
  .github/workflows/ci.yml, release.yml   Call this monorepo's reusable workflows
  package.json
```

An agent working inside a scaffolded repo should read that repo's own `AGENTS.md` first — this
doc is the durable explanation of the system as a whole, that file is the day-to-day rulebook for
the one site it ships in.

What it explicitly does **not** contain: no Composer, no PHPCS, no Playwright, no build step. The
design system, blocks, templates, and seeding engine all ship inside the Pediment **plugin** —
this repo holds only what's specific to the client. There is also **no theme auto-updater**; see
"Deploying," below.

## The `pediment` block in `package.json`

```json
"pediment": {
  "template": "__PEDIMENT_TEMPLATE_VERSION__",
  "plugin": "__PEDIMENT_PLUGIN_VERSION__"
}
```

The scaffolder fills these in once, at scaffold time, from the answers file's `template.version`
and `plugin.version`. Nothing in the plugin or the theme reads this block programmatically — it
is a human-readable record of which template and plugin versions this site was built against.
Nothing updates it afterwards either: `tools/stamp-theme-version.mjs` (run by the release
workflow on a pushed tag) only stamps the theme's own top-level `version` and `style.css`
`Version:` header, never this block. To check for drift, compare `pediment.plugin` against the
plugin version actually active in wp-admin (Plugins list) or the latest
`Bergert-Digital/pediment` release — a stale value here means the site hasn't been re-scaffolded
or manually checked against a newer plugin release recently, not that anything is broken.

## Day-two work

Structure — which pages exist, their slugs, nesting, nav membership, languages — lives in
`seed/manifest.php`. Content lives in `patterns/*.php`. See
[docs/seeding.md](seeding.md) for the full manifest/pattern format, the content-arbitration
contract, and how to read a dry-run plan; this section is only the day-to-day loop:

```bash
npm run seed:plan    # see what a seed would change; read it before applying
npm run seed
```

To take a client's live edit in the block editor back into git (the seeder otherwise leaves an
edited page's content alone forever, by design — see `docs/seeding.md`'s arbitration contract):

```bash
npm run adopt -- <key>
```

The `--` matters: `npm run adopt -- about --language=de` forwards `about --language=de` to `wp
pediment adopt` intact. A second `--` would arrive as a literal extra positional argument and
WP-CLI rejects it.

## Deploying

Push a tag matching `v*`. The theme's own `.github/workflows/release.yml` calls this monorepo's
reusable `client-release.yml`, which stamps the version into `style.css` and `package.json` and
attaches `<slug>.zip` to the GitHub release. From there:

1. Download the zip from the release.
2. Appearance → Themes → Add New → Upload in wp-admin, and activate it.
3. Settings → Pediment Theme → Seeding → apply the plan, to bring the manifest's structure live.

**The client theme has no auto-updater.** Unlike the Pediment plugin, which updates itself
through wp-admin, a new theme release only reaches production when someone uploads the zip by
hand. This is a deliberate step-5 decision, not an oversight — revisit only if it turns out to
hurt in practice.

## Moving an existing site onto Pediment

A site that already exists — built on the parent theme, a page builder, or
anything else — carries no `_pediment_seed_key` on any of its content, and
`StateReader` resolves actual state purely from that key (see
[docs/seeding.md](seeding.md)). Running `wp pediment seed` against such a
site with no prior step sees no existing rows at all and plans a `CREATE`
for every manifest entry — duplicating the whole site rather than adopting
it. The order (`docs/superpowers/specs/2026-08-05-migration-step6-design.md`
§3.3):

1. Install `pediment-plugin.zip` and activate it (wp-admin).
2. Upload and activate the standalone client theme (wp-admin).
3. Settings → Pediment Theme → Seeding → **Preview claim** — read the plan.
4. **Claim content** — identity only; see
   [`wp pediment claim`](seeding.md#wp-pediment-claim) for what it matches
   and what it refuses to.
5. **Preview plan** (seed) — expect `0 to write`, N `protected`.
6. **Apply plan** (seed) — structure only.

**Step 5 is a gate, not a formality.** A preview that reports anything other
than protected pages and the structural changes you actually expect means
the claim was incomplete — some row didn't match, or matched the wrong one —
and the fix is to go back and correct the manifest or the claim, never to
apply the plan anyway. Applying over an incomplete claim risks creating
duplicate content for whatever didn't get identity in step 4, which is
exactly the failure claiming exists to prevent.

## Maintainer-only local scaffolding

This section is for Pediment maintainers working from a monorepo checkout. External developers use
`/pediment:start`; they do not need this override. `scaffold.mjs` remains a pure function of one
answers file, and maintainers can pass `--template client-template` to test an unreleased local
template without depending on a release asset.

```bash
node client-kit/scripts/scaffold.mjs \
  --answers answers.json \
  --target ~/Entwicklung/acme-roofing \
  --template client-template
```

`client-kit/tests/fixtures/answers-greenfield.json` is the reference answers file — copy its
shape by hand if you're not going through `/pediment:start`.

The scaffolder refuses a target path that contains whitespace (WordPress derives the theme
stylesheet identifier from the directory name, and the Site Editor's template-part edit URLs
can't parse a space in it) or a non-empty target directory, and it commits the result before
anything else runs — so a failure in `npm install` or `wp-env start` afterwards never loses work.
