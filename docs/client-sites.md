# Building and maintaining a client site

A client site is a standalone WordPress theme repo that pairs with the Pediment WordPress plugin.
Pediment also ships a Claude Code developer kit that creates and maintains those theme repos. The
two plugins have different runtimes: WordPress installs `pediment-plugin.zip`; developers install
`client-kit/` once in Claude Code.

## The three units

- **The Pediment WordPress plugin** — the product. It supplies blocks, templates, tokens, forms,
  and seeding to WordPress and ships as `pediment-plugin.zip`.
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
into that question does not change the plugin's behaviour — see "What a scaffolded repo contains"
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
                          read by humans and agents; nothing in the
                          plugin reads it programmatically
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

### The header

The plugin seeds a database-backed `header` template part when a theme activates
(`pediment_bootstrap_header_template_part()`), because template parts cannot ship from a plugin
and the client template ships no `parts/`. A theme owns the *initial* markup by registering a
block pattern named `<theme-slug>/header`; the bootstrap reads it and uses it to seed the part. If
no such pattern is registered, a generic fallback header is used instead. From then on the header
is edited in the Site Editor and lives in the database — the pattern is never consulted again, so
a later change to the pattern (or a missing one) has no effect on a site that already has its
header part.

**Seeding is not synchronous with activation.** WordPress does not guarantee that a theme's own
`patterns/*.php` have been scanned into the pattern registry by the moment `after_switch_theme`
fires — that scan is a separate mechanism, tied to `init`, with no ordering guarantee relative to
the switch itself. Reading the registry at `after_switch_theme` time risks silently seeding the
generic fallback regardless of what the theme ships. So activating a theme only marks seeding as
pending; the actual seed happens on a later `init` pass (in practice, whichever request follows —
usually the very next page load after activation, since WordPress itself defers firing
`after_switch_theme` to that same following request). Until that pass completes, no `header` part
exists yet for the newly active theme, and any page rendered in that narrow window shows core's
"Template part has been deleted or is unavailable" message rather than a broken header with the
wrong content. In the ordinary wp-admin activation flow this window closes before a real visitor
could hit it — activating a theme redirects to another admin page, and that redirect's own `init`
is what completes the seed, before anyone loads the front end.

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

The seeder bootstraps a site. It never changes the content, title or slug of a
page that exists, never rewrites a menu, and keeps reading settings and a logo
that a person chose. It only fills gaps: empty reading settings or logo,
missing language tags, and the translation links of pages it creates (see
[docs/seeding.md](seeding.md), "Bootstrap only"). So the
day-to-day loop depends on where the page lives:

- **A page that does not exist yet** — declare it in `seed/manifest.php`, add
  `patterns/<key>.php`, then:

  ```bash
  npm run seed:plan    # read the plan: expect one create per new row
  npm run seed
  ```

- **A page that exists** — change it in the block editor, or through the
  Pediment MCP tools ([docs/mcp.md](mcp.md)) after validating the markup with
  client-kit's `validate-blocks`. A re-seed does not touch it.
- **New environments should match the live page** — export it into its
  pattern file and commit the file:

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
3. Settings → Pediment Theme → Seeding → **Preview plan**, then **Apply plan**. It creates what
   the new release declares and the site does not have yet (a new page, a new menu); it never
   changes anything a person made.

**The client theme has no auto-updater.** Unlike the Pediment plugin, which updates itself
through wp-admin, a new theme release only reaches production when someone uploads the zip by
hand. This is a deliberate step-5 decision, not an oversight — revisit only if it turns out to
hurt in practice.

## Upgrade to 4.0

Pediment 4.0 is a breaking release. On each site, in this order:

1. **Update the plugin.** wp-admin → Plugins → update Pediment (or upload
   `pediment-plugin.zip`). WordPress 6.9 or later is required. The first
   request after the update removes the retired AI chat's tables and settings;
   the first admin request removes the 3.x seeder's content hashes
   (`_pediment_seed_hash`, `_pediment_seed_source`, `_pediment_seed_mega_hash`).
   Pages, menus and media do not change.
2. **Install the MCP Adapter plugin** (`mcp-adapter` 0.7.0) if AI agents
   should build pages on the site. [mcp.md](mcp.md) has the application
   password and the `claude mcp add` line.
3. **Convert legacy blocks.** Add client-theme styles for the new blocks first
   (where the theme styled a legacy block), then go to Settings → Pediment
   Theme → Convert legacy blocks. The tab works in batches of 25 posts. Run
   the preview first and read the per-page diff, then convert. Revisions must
   be enabled (at least 2 per post): every page gets a backup revision first,
   and the result table links each converted page to a **Restore** link. In
   4.x the legacy `pediment/*` presentation blocks still render, but the
   inserter hides them. 5.0 removes them (see "Upgrade to 5.0"), and 6.0
   removes the converter (see "Upgrade to 6.0"). The 5.0 converter guide
   ([convert.md at v5.0.0](https://github.com/Bergert-Digital/Pediment/blob/v5.0.0/docs/convert.md)) has the full order for a production site.

   On admin-only hosting you cannot change the revision limit yourself. If the
   preview reports that revisions are disabled or limited to fewer than 2,
   ask the host or the site's developer to raise `WP_POST_REVISIONS` in
   `wp-config.php` (to 2 or more, or remove the limit). Do not convert until
   then: without a backup revision the converter skips the page
   ([convert.md at v5.0.0](https://github.com/Bergert-Digital/Pediment/blob/v5.0.0/docs/convert.md), "Results").
4. **Know what changed in seeding.** `wp pediment seed` and "Apply plan" are
   bootstrap-only now: they add what is missing (pages, menus, media, missing
   language tags, empty reading settings) and never change a page, menu,
   translation, reading setting or logo a person made. A deploy that used to push
   pattern edits to live pages through a re-seed no longer does. Change live
   pages in the editor or through the MCP tools ([seeding.md](seeding.md),
   "Bootstrap only"). The plan summary reads `N to write, N orphan,
   N unchanged.` (no `protected` count), and `--format=json` drops
   `protected` from each item and from `counts`.
5. **The AI chat is gone.** The editor sidebar, its REST routes and the AI
   settings tab were removed. The MCP tools replace them.
6. **Update the client repo's own docs.** An existing client repo carries the
   3.x text in its own `AGENTS.md` and `README.md`: pages are
   "content-protected", and a change to an existing page means "edit the
   pattern, then `npm run seed`". Remove both. An agent that follows them
   after the upgrade edits a pattern, sees `0 to write,` and reports the work
   done while the live page has not changed. Copy the 4.0 wording from
   [`client-template/AGENTS.md`](../client-template/AGENTS.md) and
   [`client-template/README.md`](../client-template/README.md): a new page
   goes in the manifest and a pattern and is seeded once; an existing page
   changes through `pediment-write-page` or the editor, then
   `npm run adopt -- <key>` refreshes its pattern for new environments.

## Upgrade to 5.0

Pediment 5.0 removes the 18 legacy presentation blocks (`pediment/hero`,
`cta`, `feature-grid`, `faq` and the others in `Policy::LEGACY`). A post that
still holds one shows it empty or unstyled. The converter is still in 5.x, so
you can also convert after the update. The steps below need wp-admin only, so
they work on admin-only hosting.

1. **Check for legacy content.** Go to Settings → Pediment Theme → Convert
   legacy blocks. The preview must list no posts. It covers pages, posts,
   drafts, private posts, synced patterns and templates. If it lists posts,
   convert them first (step 3 of "Upgrade to 4.0" and
   [convert.md at v5.0.0](https://github.com/Bergert-Digital/Pediment/blob/v5.0.0/docs/convert.md)).
2. **Check the client theme's patterns.** The seeder writes a pattern file
   as it is. A page seeded from a pattern that still holds a legacy block
   renders it empty, and `pediment-check-page` reports it as an error. The
   site's developer
   converts the pattern files in the client repo first
   (`wp pediment convert --patterns=…`, see [convert.md at v5.0.0](https://github.com/Bergert-Digital/Pediment/blob/v5.0.0/docs/convert.md),
   "Pattern files") and releases the theme. Then upload the new theme zip
   ("Deploying").
3. **Update the plugin.** wp-admin → Plugins → update Pediment (or upload
   `pediment-plugin.zip`).
4. **Read the admin notice.** While posts still hold a legacy block,
   administrators see a notice at the top of the wp-admin screens: "N posts
   still contain blocks that Pediment 5 removed. They no longer render." Its
   link opens the Convert tab, which shows no notice. Convert the posts
   there. The notice disappears after the conversion. The count is kept for
   up to 12 hours and is cleared when a post is saved or deleted and after
   each conversion.
5. **Know what changed for AI agents.** `pediment-write-page` rejects a page
   that holds a legacy block, and `pediment-check-page` reports a stored page
   that holds one as an error, not a warning. The message names the cause:
   "`pediment/hero` was removed in Pediment 5; convert it with
   pediment-convert-legacy". In 5.x the `pediment-convert-legacy` tool still
   works. 6.0 removes the tool and changes the message ("Upgrade to 6.0").
6. **Know what stays.** The accent format still works: editors apply
   **Accent** to text in core headings and paragraphs, and existing accent
   text keeps its look. In 5.x the converter (the Convert tab, the
   `pediment-convert-legacy` tool and `wp pediment convert`) and the
   `pediment_hero_variants()` function and filter still work. The converter
   reads the filter; the block editor no longer does. Pediment 6 removes
   them (see "Upgrade to 6.0").

## Upgrade to 6.0

Pediment 6.0 removes the legacy-block converter: the Convert tab, the
`pediment-convert-legacy` tool, `wp pediment convert`, the admin notice about
legacy content, and the `pediment_hero_variants()` function and filter. Pages,
menus, media and styles do not change. A post that still holds a legacy block
stays empty or unstyled, and nothing in 6.x converts it any more. The steps
below need wp-admin only.

1. **Check for legacy content.** On 5.x, go to Settings → Pediment Theme →
   Convert legacy blocks. The preview must list no posts. If it lists posts,
   convert them first ("Upgrade to 5.0").
2. **A site on 4.x or older** updates to 5.x first. Download
   `pediment-plugin.zip` from the
   [v5.0.0 release](https://github.com/Bergert-Digital/Pediment/releases/tag/v5.0.0),
   upload it (Plugins → Add New → Upload), and follow "Upgrade to 5.0", step 1
   included. Only then update to 6.x.
3. **Check the client theme.** A theme that calls `add_filter(
   'pediment_hero_variants', … )` keeps working: the filter no longer has an
   effect. Remove that code. A theme that calls the `pediment_hero_variants()`
   function itself stops with a fatal error on 6.0; remove that call before
   the update.
4. **Update the plugin.** wp-admin → Plugins → update Pediment (or upload
   `pediment-plugin.zip`).
5. **Know what stays.** `pediment-write-page` still rejects a legacy block,
   and `pediment-check-page` still reports one, as an error and in
   `legacyBlocks`. The message now says to rebuild that part with core blocks
   and named styles; [blocks.md](blocks.md#what-to-build-instead) has the tree
   for each removed block.

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

   **A site on the retired Pediment parent theme** (a child theme with
   `Template: pediment`) needs a different order for steps 1–2:
   - Upload the standalone theme first, then activate the plugin. With the
     parent theme still loaded, activating the plugin fails with
     "Cannot redeclare pediment_…()" (WordPress's activation sandbox keeps
     the site up, but the plugin stays inactive).
   - If the standalone theme replaces the child theme in place (same slug),
     WordPress keeps the stored `template = pediment` and still loads the
     parent's PHP. Visiting Appearance → Themes does not clear it. Activate
     any other theme, then activate the client theme again; only then
     activate the plugin.
   - Between the theme switch and the plugin activation the site renders
     broken (no header, fallback fonts, empty blocks), because the
     parent's `theme.json` and blocks are gone and the plugin's are not yet
     loaded. Do the two steps back to back, or under maintenance mode.
   - Pediment 5 has no legacy blocks, and Pediment 6 has no converter. Install
     Pediment 5.x from the
     [v5.0.0 release](https://github.com/Bergert-Digital/Pediment/releases/tag/v5.0.0)
     for this move, not 6.x. The parent theme's pages stay empty or unstyled
     after the plugin is active, until you convert them (Settings → Pediment
     Theme → Convert legacy blocks, see "Upgrade to 5.0"). Then update to 6.x
     ("Upgrade to 6.0").
3. Settings → Pediment Theme → Seeding → **Preview claim** — read the plan,
   including every MEDIA line (claim matches media by file name since 4.1).
4. **Claim content** — identity only; see
   [`wp pediment claim`](seeding.md#wp-pediment-claim) for what it matches
   and what it refuses to.
5. **Preview plan** (seed) — expect every claimed row `unchanged` and a
   `create` only for pages, menus or media the site really does not have.
6. **Apply plan** (seed) — creates those rows; claimed rows stay as they are.

**Step 5 is a gate, not a formality.** A `create` for a page the site already
has means the claim was incomplete — that row didn't match, or matched the
wrong one — and the fix is to go back and correct the manifest or the claim,
never to apply the plan anyway. Applying over an incomplete claim risks creating
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
