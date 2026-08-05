# Licensing and repository hygiene design

**Date:** 2026-08-05
**Status:** Design approved, pending implementation plan
**Scope:** Declare the licence split across the three published components, retire two stale
documentation trees, close two dormant public repositories, and record the commercial-protection
design as a backlog item. Supersedes the "make the monorepo private" investigation that produced it.

---

## 1. Why this replaced a repository-privacy migration

The work started as "make a plan so we can keep the main monorepo private," motivated by the
concern that a public repository lets customers take the whole product for free. Investigating what
privacy would actually buy dissolved the premise, and the reasoning is recorded here so it does not
get re-litigated.

### 1.1 What is actually protectable

Sorting every asset by **where it physically ends up** is what settles the question.

| Tier | Assets | Protection available |
|---|---|---|
| Ships to the customer's WordPress server | `plugin/` PHP, blocks, seeder, forms engine, `plugin/src/Chat/PromptBuilder.php` | None. WordPress executes plain PHP source; whatever runs on their server, they have. |
| Ships to the developer's machine | `client-kit/`, `client-template/` | Licence terms and access gating. Copyable in practice. |
| Never ships | Anything behind an HTTP API | Genuine. |

The third tier is **currently empty**. `plugin/src/Anthropic/Client.php:30` calls
`https://api.anthropic.com` directly from the customer's server using the customer's own key, and
the system prompt ships inside the zip. So one hundred percent of Pediment's intellectual property
already lands on someone else's disk, and no repository setting changes that.

### 1.2 Why the docs turned out not to need hiding

The one asset class that never ships is `docs/`. Read in full, it is engineering documentation
rather than commercial material:

- `VISION.md` is 41 lines describing the plugin/client-theme split — effectively a README.
- `PRODUCT_SENSE.md` is a pre-ship QA checklist, and a stale one (see §3.2).
- `client-sites.md`, `STANDARDS.md`, `blocks.md`, `seeding.md`, `WORDPRESS_TRAPS.md` are reference
  material that a developer-facing product benefits from publishing.
- `superpowers/plans/` and `superpowers/specs/` describe code that is public anyway.
- `BACKLOG.md` is the only file with a real argument for privacy: it advertises unfinished work.

A scan for commercial material found no pricing, revenue or contract data. All 21 occurrences of
"pricing" are a `/pricing` page in mega-menu test fixtures. The single genuine finding is 18
mentions of the client name "Workation" (§3.4).

### 1.3 The measured costs of going private

Verified during the investigation, and each is a real cost against no remaining benefit:

- **A public artifact channel is mandatory regardless.** Plugin Update Checker
  (`plugin/src/Updater.php:24`) and `client-template/.wp-env.json:6` both fetch over
  unauthenticated HTTP from a WordPress server or a `wp-env` container. Neither can hold a
  credential, so some repository must stay public to serve release assets.
- **Cross-organisation CI breaks.** Private reusable workflows are reachable only from repositories
  owned by the same organisation, with `Settings → Actions → General → Access` opened. An external
  developer's client repository in their own organisation cannot call
  `Bergert-Digital/pediment/.github/workflows/client-theme.yml@main` at all. `client-release.yml:34`
  additionally checks out this repository, which `GITHUB_TOKEN` cannot authenticate across repos.
- **CI stops being free.** The Bergert-Digital organisation is on the **Free** plan: public
  repositories get unlimited Actions minutes, private ones get 2,000/month. This repository ran
  roughly 100 workflow runs in five weeks, including `wp-env`, PHPUnit, Playwright and scaffold
  jobs. Combined with the organisation's $0 spending limit, which drops Actions events with no UI
  signal, going private risks silently killing CI.
- **Private marketplaces degrade.** Claude Code's background marketplace refresh disables git
  credential helpers, so a private HTTPS marketplace fails to auto-update and falls back to a full
  re-clone. SSH remotes avoid this, but only by requiring every customer to have a loaded SSH key.

### 1.4 What the exposure actually was

`Bergert-Digital/pediment` has **0 forks, 0 stars and 0 watchers**; `Bergert-Digital/pediment-ai`
has 0 forks. There is no fork network and no evidence of the copying the concern anticipated.

### 1.5 The conclusion

The plugin ships as a GPL zip containing all its PHP. `client-template` and `client-kit` are
published deliberately, because a frictionless `/plugin marketplace add Bergert-Digital/pediment`
with no authentication, no download and working background updates is worth more than hiding them.
The docs are documentation. **Nothing is left for a private monorepo to protect**, so the migration
is not built. Commercial protection belongs in a server that never ships — recorded as a backlog
item (§3.5) and specced separately.

---

## 2. Decisions

| # | Decision | Rejected alternative | Why |
|---|---|---|---|
| 1 | The monorepo stays **public**. No `pediment-dist`, no mirror pipeline, no CI-minutes work. | Private monorepo with a public distribution repository | §1. Every asset either ships to customers anyway or is documentation; the remaining benefit is commit-stream privacy, which does not justify a permanent sync pipeline and a metered CI budget. |
| 2 | Three licence zones rather than one repository-wide licence. | A single root licence covering everything | `plugin/` and `client-template/` are WordPress code and inherit the GPL position; `client-kit/` is a Claude Code plugin and a Node scaffolder that neither loads in WordPress nor links against it, so it carries no GPL obligation and can be licensed commercially. |
| 3 | `client-kit/` uses **PolyForm Shield 1.0.0**. | All-rights-reserved; PolyForm Noncommercial; a bespoke EULA | All-rights-reserved grants no permission to *use* the kit, which is incompatible with publishing something people are meant to install. Noncommercial is backwards — the intended users are commercial agency developers. Shield is off-the-shelf, professionally drafted, permits customers to use the kit for their own client work, and forbids using it to build a competing product. |
| 4 | `client-template/` is **GPL-2.0-or-later**, not proprietary. | Licence it commercially alongside `client-kit/` | The scaffolder copies it into the customer's own repository where it becomes their theme. Anything restrictive makes site ownership ambiguous, and it is a WordPress block theme with PHP, so the GPL position applies regardless. |
| 5 | `PRODUCT_SENSE.md` is **deleted**, not rewritten. | Rewrite it against the plugin/client-theme model | Its quality bars are already in `STANDARDS.md` and its journey is already in `client-sites.md`. A rewrite would mostly duplicate both. |
| 6 | `pediment-ai` is made **private**, not deleted. | Delete the repository | Deletion breaks every existing link to its commits, including the ones in this repository's `CHANGELOG.md`. Private costs nothing and preserves them for anyone with access. |
| 7 | Client names in `docs/` are **left in place**, pending Workation's consent. | Scrub all 18 mentions to a placeholder | The mentions are load-bearing in the step-4 and step-6 plans, where "Workation needs per-language media" is a concrete deferral rationale that a placeholder would blur. Consent is the cheaper fix for a low-severity issue. |
| 8 | Commercial protection is **backlogged**, not designed here. | Fold the licence server and hosted AI service into this spec | It is weeks of work, it changes the product's billing model, and it depends on pricing decisions that are not made. This spec must not stall behind it. |
| 9 | The stale `plugin/docs/` tree is **deleted** in this pass. | Leave it; handle it separately | Found during this spec's self-review (§3.6). It duplicates five root docs including `PRODUCT_SENSE.md`, so deleting only the root copy would leave the stale one behind and make decision 5 half-done. |

---

## 3. Design

Five independent deliverables. None depends on another, so they can ship in any order.

### 3.1 Licence declaration

The gap this closes: there is **no root `LICENSE` file**, and `client-kit/` declares no licence at
all — neither a file nor a `license` field in `client-kit/.claude-plugin/plugin.json`. On a public
repository that default means all-rights-reserved, so no one technically has permission to use the
kit they are being invited to install.

| Path | Licence | Notes |
|---|---|---|
| repository root | GPL-2.0-or-later | Covers `plugin/` and `client-template/`; matches the existing declarations in `plugin/plugin.php:13` and `plugin/composer.json:5`. |
| `client-kit/` | PolyForm Shield 1.0.0 | Use permitted, competing products not. |
| `client-template/` | GPL-2.0-or-later | Explicit rather than inherited, because the directory is copied out of this repository into customer repositories. |

Files to add:

- `LICENSE` — GPL-2.0-or-later full text.
- `client-kit/LICENSE` — PolyForm Shield 1.0.0 full text, with Bergert Digital as licensor.
- `client-template/LICENSE` — GPL-2.0-or-later full text.
- `LICENSING.md` — one page explaining which licence covers which directory and why, so the split is
  discoverable without reading three licence files.

Files to change:

- `client-kit/.claude-plugin/plugin.json` — add `"license": "LicenseRef-PolyForm-Shield-1.0.0"`.
  **PolyForm Shield is not on the SPDX licence list** (verified against SPDX v3.28.0, which carries
  only `PolyForm-Noncommercial-1.0.0` and `PolyForm-Small-Business-1.0.0`), so the SPDX
  `LicenseRef-` prefix for non-listed licences is the correct form. A bare
  `PolyForm-Shield-1.0.0` would be an invalid identifier.
- `package.json` — add `"license": "GPL-2.0-or-later"`. The root manifest currently declares none.
- `README.md` — a short licensing section linking to `LICENSING.md`.

`client-kit/` has no `package.json` of its own; its tests run from the root manifest via
`npm run test:kit`. So the kit's licence is declared in `client-kit/LICENSE` and its Claude Code
manifest, not in a package file.

The `client-release.yml` staging step already excludes `docs/` and `AGENTS.md` from client theme
zips; `LICENSE` should ship, so no `rsync` exclusion is added.

**This is a design recommendation, not legal advice.** A German/EU software-licensing lawyer should
confirm the Shield choice and the GPL boundary before Pediment is sold.

### 3.2 Retire `PRODUCT_SENSE.md`

The file describes a model retired two major versions ago. Concretely, its "Journey A" instructs the
reader to clone `pediment-child-theme`, run a fork rename checklist, verify that a child
`theme.json` override wins over the parent palette, and check that wp-env comes up "with the parent
+ plugin mounted from siblings." There is no parent theme, no child theme and no
`pediment-child-theme` repository.

Delete the file. A grep pass found **no inbound references from the root tree** — the only two hits
are `plugin/docs/SESSION_LOG.md:28` and `plugin/docs/BACKLOG.md:17`, both inside the stale duplicate
tree that §3.6 removes. Re-run the grep after both deletions to confirm nothing dangles.

### 3.3 Close the two dormant public repositories — **DONE 2026-08-05**

Executed with explicit user go-ahead, ahead of the implementation plan. Recorded here for the
record; **the plan should not schedule this work again.**

- **`Bergert-Digital/pediment-ai`** — was public, archived, 0 forks, carrying the plugin's pre-merge
  history. Archived repositories are read-only including their settings, so the sequence was
  unarchive → set private → re-archive. Now `{"visibility":"private","archived":true}`.
- **`Bergert-Digital/Pediment-Child-Theme`** — was public and active, still documenting the retired
  parent/child model. Its README was replaced with a deprecation notice pointing at
  `docs/client-sites.md` and the `/pediment:start` flow (commit `c6e198b` on that repo), then the
  repository was archived. It stays **public** so existing inbound links keep resolving; the code is
  GPL and has 0 forks, so there is nothing to gain by hiding it.

Both verified by reading state back with `gh api` rather than trusting the command's exit status.
The backlog item at `docs/BACKLOG.md:18` is closed.

### 3.4 Client names in documentation

"Workation" appears 18 times across `docs/BACKLOG.md` and the step-3, step-4 and step-5 plans,
always as the planned migration target for step 6. No credentials, contract terms or commercial
figures appear alongside it.

The action is to **ask Workation whether they are comfortable being named** in public engineering
documentation, and to scrub only if they are not.

This deliberately does **not** become a backlog item. `docs/BACKLOG.md` tracks engineering work that
can be picked up from the repository, and contacting a client cannot be — the project's own
add-to-backlog convention excludes manual-only tasks for exactly this reason. It is surfaced to the
user as a reminder instead. If the answer comes back "no," scrubbing the 18 mentions is then a
normal backlog item with a clear premise.

### 3.5 Backlog entry for commercial protection

Add a `🟡 High` backlog item capturing the design worked out during this investigation, so it
survives as a decision rather than a conversation:

- **License keys gate updates; a server gates capability.** A licence check inside shipped PHP is a
  speed bump — it reliably gates updates and support, which is what drives renewals, and it does not
  gate execution. This is the model every premium WordPress plugin uses.
- **Move the prompt layer server-side.** `plugin/src/Chat/PromptBuilder.php`,
  `plugin/src/Chat/TurnRunner.php` and `plugin/src/Anthropic/SchemaBuilder.php` are the accumulated
  tuning that currently ships to every customer. Behind an API they stop shipping, and patching the
  call out deletes the feature rather than freeing it. The plugin keeps blocks, the seeder, the
  forms engine and a thin HTTP client.
- **The protection is proportional to the work the server does.** A proxy that authenticates and
  forwards prompts the plugin already contains protects nothing.
- **Costs to weigh:** the token bill moves to Bergert Digital unless bring-your-own-key is retained
  with only the prompts gated, and the endpoint becomes an availability dependency for customer
  sites.
- **Platform decision, unmade:** Freemius, Easy Digital Downloads with Software Licensing,
  SureCart, or a merchant-of-record such as Paddle. A merchant of record absorbs EU VAT
  registration and filing, which is material for a German sole trader.

### 3.6 Delete the stale `plugin/docs/` tree

Found while grepping for `PRODUCT_SENSE` references during this spec's self-review, and not part of
the originally approved scope.

`plugin/` carries a second, orphaned documentation tree left over from the `pediment-ai` merge on
2026-07-30:

```
plugin/docs/BACKLOG.md        plugin/docs/extending.md
plugin/docs/PRODUCT_SENSE.md  plugin/docs/privacy.md
plugin/docs/SESSION_LOG.md    plugin/docs/prompts.md
plugin/docs/STANDARDS.md      plugin/docs/superpowers/
plugin/docs/VISION.md
```

Five of these duplicate root `docs/` files under the same names, with divergent, older content. They
are the source of both surviving `PRODUCT_SENSE` references, so §3.2 is only half done without this.

**They do not ship** — `docs` is listed in `plugin/.distignore`, so the release zip excludes the
whole directory. Verified, and worth stating because it is the difference between "stale internal
clutter" and "we shipped a wrong privacy disclosure to customers."

One file needs a decision rather than deletion: **`plugin/docs/privacy.md`** is a GDPR disclosure
listing what the plugin transmits to Anthropic, and it names *"Brand Settings values: brand name,
tagline, voice/tone"* — but Brand Settings was removed from the product. It is both stale and the
only privacy documentation that exists. Rewrite it against what
`plugin/src/Chat/PromptBuilder.php` actually sends and move it to root `docs/privacy.md`, rather
than deleting it with the rest. `extending.md` and `prompts.md` need the same
current-or-delete judgement during implementation.

---

## 4. Verification

| Deliverable | How it is verified |
|---|---|
| Licence files | `npm run test:kit` passes 78/78 from the repository root (confirmed green on the pre-change tree, so any failure is attributable), and `lint:blocks`, `lint:colors`, `lint:icons` pass. `client-kit/.claude-plugin/plugin.json` and `package.json` both still parse as valid JSON. |
| `PRODUCT_SENSE.md` removal | `grep -rIn "PRODUCT_SENSE" . --exclude-dir=node_modules` returns no hits outside this spec and `docs/SESSION_LOG.md` history entries. |
| `plugin/docs/` removal | The directory is gone; `plugin/docs/privacy.md`'s replacement exists at `docs/privacy.md` with the Brand Settings reference removed. PHPUnit still passes, confirming no PHP file loaded anything from the tree. |
| `pediment-ai` | **Done.** `gh api repos/Bergert-Digital/pediment-ai --jq '{visibility, archived}'` returned `{"visibility":"private","archived":true}`. |
| `Pediment-Child-Theme` | **Done.** `gh api repos/Bergert-Digital/Pediment-Child-Theme --jq '{archived}'` returned `true`, and the served README renders the deprecation notice. |
| Backlog entries | Present in `docs/BACKLOG.md` under the stated priority groups. |

No WordPress runtime behaviour changes, so PHPUnit and Playwright are unaffected but should still
run as a regression check before merge.

---

## 5. Out of scope

- **The repository-privacy migration.** Rejected in §1, with the reasoning recorded so it is a
  decision rather than an omission.
- **The licence server and hosted AI service.** Backlogged in §3.5; its own spec.
- **Rewriting `PRODUCT_SENSE.md`'s content into `STANDARDS.md`.** The material is already there;
  see decision 5.
- **Trademark registration and a Pediment trademark policy.** Raised during the investigation,
  genuinely worth doing, and squarely a lawyer's task rather than an engineering one.
