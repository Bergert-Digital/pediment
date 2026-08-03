# Scaffolder and `/start` — migration step 5 design

**Date:** 2026-08-03
**Status:** Design approved, pending implementation plan
**Scope:** Migration step 5 of `2026-07-29-pediment-dev-flow-design.md` — §4.4 (`/start`) and §4.5
(scaffolding), plus the backlog item "update the client-theme template repo"

---

## 1. What step 5 is

Steps 1–4 built the engine: one plugin artifact, a declarative seeder with identity keys and hash
arbitration, and a `LanguageProvider` seam with a working Polylang adapter. None of it is reachable
without a client repo to run it in, and creating that repo is still the hand-driven checklist §2.1
diagnosed. Step 5 builds the front door.

Two deliverables:

- **A scaffolder** that turns a client name and a slug into a working standalone client theme repo.
- **`/start`**, a Claude Code skill that asks what cannot be derived, drives the scaffolder, and
  takes the site to "seeded and rendering locally" in one session.

### 1.1 Four premises of the parent spec that did not survive contact

Each was checked against the working tree before designing around it.

| Parent spec claim | Actual state | Consequence |
|---|---|---|
| §4.4: `/start` "sequences the existing skills (`initialize`, `discover`, `port-site`, `build-header`, `port-page`, `create-seed-content`)" | Only `port-site` and `port-page` exist, both solely in `workation-castle-website/.claude/skills/`. The other four exist nowhere. | "Sequence existing skills" is really "port two skills to the new engine and write the connective tissue." |
| §4.5: build a `pediment doctor` command | Its checks are now obsolete, already implemented, or one-shot prerequisites — see decision 6. | Not built. |
| §4.4: the questionnaire's answers "populate the plugin's site config which `PromptBuilder` reads" | `plugin/src/Chat/PromptBuilder.php` is 123 lines and builds a fully static prompt. It reads no options and has no brand, tone, or site-context input. | Brand voice is not wiring, it is the whole feature. Deferred — decision 7. |
| §4.5: the client theme still carries `ThemeUpdater`, `UpdateToken`, `settings-updates.php` | Already true that it should not; also true that nothing replaced them. `plugin/src/Updater.php` updates the plugin only, and the plugin exposes no `pediment_update_checkers` filter. | Client themes ship with no auto-updater — decision 8. |

`port-site` and `port-page` are additionally written against the retired world: `port-site` patches a
*child* `theme.json` expecting a parent palette to inherit from, and `port-page` builds pages live in
wp-env and then fights to persist them, which is the exact problem §4.2's `adopt` command inverted.

---

## 2. Decisions

| # | Decision | Rejected alternative | Why |
|---|---|---|---|
| 1 | The client template lives in this monorepo as `client-template/`. `pediment-child-theme` is archived, not migrated. | Rename `pediment-child-theme` → `pediment-client-template` and keep it as a separate repo | A second repo is the drift §2.1 catalogued. Here the template is built, tested and released beside the plugin that it pairs with. |
| 2 | The template is **not** the e2e fixture. `tests/fixtures/client-theme/` stays as it is. | Promote the fixture to double as the template | The fixture carries a mega-menu demo page, six sample posts and two languages because Playwright asserts on them. Merging makes every e2e assertion a constraint on what new clients start with. |
| 3 | Developers install a **Claude Code plugin** (`client-kit/`, built plugin-shaped from day one), not a clone of this repo. The template arrives as a release asset, `pediment-client-template.zip`. | Clone the monorepo; publish an npm scaffolder package | Nobody should pull `vendor/`, `plugin/node_modules/`, both test suites and ~46 MB of JPEGs still in history to make a client site. The release channel that ships `pediment-plugin.zip` already exists, needs no npm org, and pins template-to-plugin version alignment for free. |
| 4 | The client repo is **thin**: no composer, no phpcs, no Playwright, no build step. Its CI is one `uses:` line pointing at a reusable workflow here. | Copy today's child-theme tooling into every client repo | The client repo is the one that multiplies per client, so whatever is copied into it drifts N times. §7 of the parent spec named this exactly; a reusable workflow is the consumption mechanism it said was missing. |
| 5 | Client-block tooling (`src/blocks/` + wp-scripts) is **not** scaffolded. Add it behind a `--with-blocks` flag the first time a client needs a bespoke block. | Ship the build pipeline ready-to-use | §4.5's point is that the client theme has almost no PHP left. Shipping a build pipeline for a directory that does not exist is how the old template got heavy. |
| 6 | **`pediment doctor` is not built.** | Build it as specified in §4.5 | Every check it was to perform is now obsolete, already implemented, or belongs elsewhere — see §3.7. It was a solution to the parent/child rename problem, and that problem is gone. |
| 7 | **Brand voice is captured, not consumed.** The questionnaire's positioning and tone answers land in `docs/brief.md` and nowhere else. No manifest `brand` section, no `PromptBuilder` change. | Wire it end to end now; or capture it into the manifest for later consumption | Deliberate deferral. `/start` must say plainly that nothing reads it yet, so this does not become a fifth place brand voice is documented and not implemented. |
| 8 | **Client themes ship no auto-updater.** Production updates a client theme by admin zip upload. | Port the child theme's PUC checker and `UpdateToken` | Verified the production path still closes: `plugin/inc/seeding-admin.php` runs the same `Runner` as the CLI with PHP limits lifted, so admin-only hosting can upload the zip and re-seed from Settings → Pediment Theme → Seeding. A small regression against today's child theme, accepted; revisit if step 6 shows it hurts. |
| 9 | `/start` covers **both branches** — porting an existing site and starting fresh. | Greenfield only, port deferred to its own step | Decided by the user after seeing the cost. Contained by keeping per-page porting in its own skill (§3.5). |

---

## 3. Design

### 3.1 Topology

```
pediment/                          monorepo, internal, never cloned by a client dev
  plugin/                          UNCHANGED by this step
  client-template/                 NEW — canonical standalone client theme source
  client-kit/                      NEW — Claude Code plugin bundle
    .claude-plugin/plugin.json
    skills/start/SKILL.md
    skills/port-page/SKILL.md      ported from workation, rewritten around adopt
    shared/fidelity-critic-prompt.md   ported
    shared/visual-qa.md                ported
    scripts/scaffold.mjs           one entry point, zero dependencies
    scripts/brand.mjs              ported colour maths, unit-tested
  .github/workflows/
    client-theme.yml               NEW — reusable CI, called by every client repo
    client-release.yml             NEW — reusable release, builds <slug>.zip
    build-release-zip.yml          MODIFIED — also attaches pediment-client-template.zip
  tests/fixtures/client-theme/     UNCHANGED
```

`client-kit/` is laid out as a Claude Code plugin from the first commit. Internally it is installed
from the local path; publishing it as a marketplace entry when Pediment productizes is then an
addition, not a migration.

### 3.2 The scaffolder

One command, a pure function of one input:

```
node scaffold.mjs --answers .context/start/answers.json --target ~/Entwicklung/<slug>
```

The questionnaire's entire output is a JSON answers file. That boundary is the point: the agent
handles what needs judgment, the script handles what must be identical every time, and the script
becomes unit-testable by feeding it answer files and asserting on the emitted tree — no browser, no
Docker, no wp-env.

**Rewriting is token-driven, not knowledge-driven.** The template ships literal `__PEDIMENT_SLUG__`
and `__PEDIMENT_NAME__` placeholders; the scaffolder does a blind recursive replace across every
file, and CI asserts no `__…__` token survives in the output. A new template file containing a token
therefore needs no scaffolder change — the coupling that would otherwise make the script rot is
designed out.

The slug still has to be right in one place that is easy to underestimate: `patterns/*.php`. Step 4
established that the seeder resolves patterns from `WP_Block_Patterns_Registry` by their `Slug:`
header, not by filename. A wrong namespace there does not fail loudly — it resolves nothing, which
is the silent-success class of failure `--dry-run` exists to expose.

The scaffolder's other jobs: unpack the template zip (or `--template <path>` for local development),
refuse a target path containing whitespace (which breaks Site Editor URLs) or a non-empty target
directory, derive the `theme.json` palette from the accent colour via `brand.mjs`, write
`seed/manifest.php` from the answers, write the version block, then `git init` and commit.

**Version pinning is explicit and recorded.** The client `package.json` carries:

```json
"pediment": { "template": "3.3.0", "plugin": "3.3.0" }
```

Without it nothing can answer "which generation is this client on" — the question that got
`chore(wp-env): bump parent/plugin refs` opened five separate times (#5, #18, #34, #39, #58).

`brand.mjs` is a port of workation's `tools/brand-extract.mjs` (56 lines) and `tools/theme-reskin.mjs`
(34 lines), merged into one internal module rather than a second entry point. It is pure colour
maths — normalize `rgb()`/short hex, derive `accent-hover`, `accent-tint` and surfaces from one
accent. Hex arithmetic performed by an agent in prose is unverifiable; these are functions with
tests. The capture half — reading a live site's computed styles in the browser — stays in the skill,
where it already lives in `port-site`.

### 3.3 The `/start` flow

**Phase 0 — prerequisites.** Docker running, node ≥ 20, git present. Checked before anything is
created; all failures reported together, each with the command that fixes it. `gh` is checked later
and only when creating a remote is opted into.

**Phase 1 — one branching question**, then five or six more, asked one at a time, following §4.4's
principle of asking only what cannot be derived:

| Port (~5, mostly confirmations) | Greenfield (~6) |
|---|---|
| Source URL | Business name |
| Confirm the extracted palette and fonts (shown, not asked) | What they do, and for whom |
| Which pages to port — pre-checked from `sitemap.xml` | Languages, default first |
| Confirm languages — pre-filled from `hreflang` | Sitemap (Home/About/Services/Contact offered pre-checked) |
| Client name and repo slug | Accent colour |
| | Logo file, optional |

The repo slug and theme name are derived from the business name and shown for confirmation, not
asked as separate questions. Tone is captured as part of the positioning question and lands in
`docs/brief.md` only; the skill states that nothing consumes it yet (decision 7).

**Phase 2 — the automated tail**, identical for both branches: `scaffold.mjs` → `git init` and
commit → `npm install` (one dependency, `@wordpress/env`) → `wp-env start` → `wp pediment languages`
(only when the manifest declares a `languages` section) → `wp pediment seed --dry-run`, shown to the
user → `wp pediment seed` → report the local URL and next steps. Creating a GitHub remote is offered at the end and requires explicit confirmation.

The port branch's per-page work happens after that tail, page by page (§3.5).

### 3.4 `docs/brief.md`

The durable artifact of the questionnaire: business name, what they do and for whom, tone,
languages, the sitemap, and the brand decisions with their source (extracted from `<url>`, or
chosen). It is written once by `/start` and is thereafter a normal file in the client repo that
humans and agents both read. Nothing parses it.

### 3.5 Porting, after step 3's engine

Workation's `port-page` grew an entire "Step 9: Persist to version control" section because pages
built live in wp-env were destroyed by the next re-seed. Step 3 inverted that: `wp pediment adopt
<key>` exports a live page's block markup to `patterns/<slug>.php` and resets the hash. The loop
becomes:

1. Add the entry to `seed/manifest.php`.
2. Build the page — author `patterns/<slug>.php` directly, or build it in the editor and adopt it.
3. `wp pediment seed`.
4. Screenshot, compare against the source under the fidelity critic, iterate.

Multilingual falls out of step 4 for free: `wp pediment adopt --language=de` writes
`patterns/<slug>.de.php` with the correct `Slug:` header.

Per-page porting stays its own skill (`/pediment:port-page`) rather than swelling `/start`. `/start`
scaffolds and seeds the shell, then hands over a page list and invokes it per page. This is also the
containment boundary for risk: the porting loop is browser-driven with a judgment call in it, so CI
can prove it runs but not that its output is good. The greenfield path stays fully testable and
shippable on its own.

### 3.6 The client repo

```
<client>/
  style.css theme.json           theme headers + client tokens, merged OVER plugin defaults per slug
  templates/ patterns/           optional template overrides + page content
  seed/manifest.php seed/media/  structure + assets
  docs/brief.md                  the questionnaire's durable artifact
  .wp-env.json                   pinned pediment-plugin.zip release (+ Polylang if multilingual)
  package.json                   env:start, env:stop, seed, seed:plan, adopt + the version block
  .github/workflows/ci.yml       one `uses:` line
  .github/workflows/release.yml  one `uses:` line, triggered on a pushed tag
  AGENTS.md README.md .gitignore
```

**No release-please in client repos.** Version discipline there is a pushed tag: `release.yml` fires
on `v*` and calls the reusable release workflow. release-please earns its keep in a repo whose
commits are conventional and whose changelog is read; a client repo's commits are content edits, and
adding a bot, a manifest, a config and a permanently-open release PR to every client site is exactly
the per-client weight decision 4 exists to prevent.

Two reusable workflows live in the monorepo and are called by every client repo:

- **`client-theme.yml`** (CI): boot wp-env, `wp pediment seed --dry-run`, seed, assert the front page
  renders, report plugin-pin drift.
- **`client-release.yml`**: build `<slug>.zip` and stamp the `style.css` version header.

The second matters more than its size suggests. The released artifact's version header failing to
move — so production never sees the update — was found and fixed independently in all four repos
(`9c9af20`, `22f0024`, `432faf6`, workation #22). A reusable workflow gives that defect exactly one
place to live.

### 3.7 Where `doctor`'s checks went

| §4.5 check | Where it lives now |
|---|---|
| Slug consistency across five files | Impossible by construction — the scaffolder writes every occurrence from one input; the five files were a parent/child artifact. |
| Folder-name whitespace | The scaffolder refuses such a target path outright. `tools/check-folder-name.mjs` already covers the monorepo on `env:start`. |
| Languages configured before seeding | Already a hard gate — step 4's `Runner` errors when the manifest and Polylang disagree. |
| Plugin pin drift | Reported by `client-theme.yml`, which already reads `package.json`. A JSON comparison, not a command. |
| Docker running, node version, `gh` authenticated | One-shot prerequisites; they belong in `/start`'s phase 0, which is what §4.4 actually specifies. |

### 3.8 Error handling and resumability

Every failure mode is refuse-early or resumable.

- Prerequisites report together and stop, before anything is written.
- A non-empty target directory or a whitespace-containing path is refused before the first write.
- The `git init` and commit land **before** wp-env starts, so a Docker failure costs no work.
  Re-running `/start` against a directory that already has `seed/manifest.php` detects the state and
  offers to resume from the env phase rather than starting over.
- Template download failure falls back to `--template <local path>`.
- The seed always dry-runs first, so the plan is visible before any write.

---

## 4. Verification

Three layers.

1. **Node unit tests on `scaffold.mjs` and `brand.mjs`** — answers file in, file tree out; an
   assertion that no `__…__` token survives; colour-derivation cases.
2. **A CI job in this repo** that scaffolds from `client-template/` with a fixture answers file and
   then runs `client-theme.yml` against the result. One job exercises template, scaffolder, reusable
   workflow and plugin together — and it is the template's only regression gate, which is why
   decision 2's duplication against the e2e fixture is affordable.
3. **The existing suites, unchanged.** Nothing under `plugin/` changes, so PHPUnit and Playwright
   are pure regression gates here. The PHP that step 5 *does* add — the template's
   `seed/manifest.php` and `patterns/*.php` — is exercised by layer 2, by being seeded for real.

Step 5 ships as a `feat:` minor with no plugin change.

---

## 5. Deliberately out of scope

- Brand voice consumption in `PromptBuilder` (decision 7).
- A client-theme auto-updater (decision 8).
- Client-block build tooling; add behind `--with-blocks` when a client needs one (decision 5).
- Publishing `client-kit/` to a Claude Code marketplace. Built plugin-shaped so this is later an
  addition, not a migration (decision 3).
- Migrating an existing site — that is step 6.

---

## 6. Parent-spec open questions this step resolves

- *"Does the child template keep its own CI, or consume a reusable workflow from the monorepo?"* —
  Reusable workflow, as it was leaning.
- *"The same question applies to the shared e2e helpers."* — Resolved differently: client repos get
  no e2e suite at all. The checks that mattered live in `client-theme.yml`, so there is nothing to
  copy and therefore nothing to drift.
- *"Where does `pediment doctor` live?"* — Nowhere; it is not built (decision 6).
- *"Should AI features be license-gated from the start?"* — Still deferred, untouched by this step.
