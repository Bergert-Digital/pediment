# Building and maintaining a client site

A client site is a standalone WordPress theme repo that pairs with the Pediment plugin. This
is the reference for making one, working in it day to day, and shipping updates to it — read it
before your first client, or when working as an agent inside a client repo.

## The three units

- **`client-kit/`** — a Claude Code plugin. It carries the `/pediment:start` and
  `/pediment:port-page` skills and one deterministic scaffolder
  (`client-kit/scripts/scaffold.mjs`). This is what a client developer installs.
- **`pediment-client-template.zip`** — a release asset: the client theme template, with its
  `__PEDIMENT_*__` tokens still literal. The scaffolder downloads and rewrites it. **This asset
  does not exist in any release yet** — see "Scaffolding without Claude Code" below for the
  workaround.
- **This monorepo** — owns the plugin, the client theme template's source
  (`client-template/`), the reusable CI/release workflows client repos call
  (`.github/workflows/client-theme.yml`, `.github/workflows/client-release.yml`), and this doc.

**A client developer never clones this repo.** They install the `client-kit` plugin, and the
template arrives as a release asset. The monorepo is only for people working on Pediment itself,
or — until the release asset ships — for running the scaffolder from a local checkout (below).

## Making a site

Install the kit once, from a local checkout of this repo:

```
/plugin marketplace add ./client-kit
/plugin install pediment
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

Either way, the skill writes what it learned to `.context/start/answers.json` (gitignored scratch
space), then scaffolds, boots wp-env, and seeds:

```bash
node client-kit/scripts/scaffold.mjs --answers .context/start/answers.json --target <path> --template client-template
npm install
npm run env:start
npm run languages    # only if the manifest has a languages section
npm run seed:plan    # shown to you before anything is applied
npm run seed
```

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

## Scaffolding without Claude Code

`scaffold.mjs` is a pure function of one answers file — the skill only owns the parts that need
judgment. Its CLI takes exactly four flags: `--answers <file>`, `--target <dir>`,
`--template <dir>` (optional), and `--no-git` (optional, skips the initial commit):

```bash
node client-kit/scripts/scaffold.mjs \
  --answers answers.json \
  --target ~/Entwicklung/acme-roofing \
  --template client-template
```

`client-kit/tests/fixtures/answers-greenfield.json` is the reference answers file — copy its
shape by hand if you're not going through `/pediment:start`.

`--template client-template` is currently **required** when running from a monorepo checkout.
Omitting it makes the scaffolder try to download `pediment-client-template.zip` for the version
named in the answers file from a GitHub release — that asset doesn't exist in any release yet, so
omitting `--template` fails on the very first run. Once a release ships it, `--template` becomes
optional and the scaffolder will download and unzip it into a temp directory automatically.

The scaffolder refuses a target path that contains whitespace (WordPress derives the theme
stylesheet identifier from the directory name, and the Site Editor's template-part edit URLs
can't parse a space in it) or a non-empty target directory, and it commits the result before
anything else runs — so a failure in `npm install` or `wp-env start` afterwards never loses work.
