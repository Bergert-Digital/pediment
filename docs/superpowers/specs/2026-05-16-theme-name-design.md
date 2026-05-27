# Theme Name Decision — "Pediment"

**Date:** 2026-05-16
**Status:** Decided (pending user spec review → implementation plan)
**Decision:** Rename the theme from "Starter Theme" / `wp-starter-theme` to **Pediment**.

## Context

The theme is currently a generically-named "Starter Theme" (repo `wp-starter-theme`,
Composer `bergert/wp-starter-theme`, text domain `starter`). It is being positioned as
a **public product**: a forkable WordPress block theme sold/marketed to **agencies that
build sites for consultancies and SMEs**.

A real product needs a distinctive, defensible name with an available `.com` and no
trademark/namespace collision.

## Requirements & Constraints

- **Audience tone:** architectural gravitas — solid, structural, consultancy-grade trust.
- **Domain:** `.com` only. Exact root preferred; product-style variants
  (`<name>theme.com`, `<name>wp.com`, `get<name>.com`, `use<name>.com`) acceptable.
- **Trademark:** public product → must avoid existing WordPress themes, CMS/web
  software products, and confusingly similar marks in adjacent spaces.
- **Style:** recognizable real word (not coined/obscure).

## Decision: Pediment

A *pediment* is the triangular gable crowning a classical portico/temple front — the
formal architectural "cap" of the structure. It signals classical gravitas and
craftsmanship, fitting agencies pitching consultancies and SMEs.

- **Domain — registered:** **`pedimenttheme.com`** (purchased 2026-05-17). Exact
  `pediment.com` is taken (generic word, not enforceable against an unrelated product
  on a variant domain); the brand operates on the registered variant.
- **Namespace/trademark — clean:** No WordPress theme named "Pediment" (on
  WordPress.org or ThemeForest). No software/CMS product named "Pediment" surfaced.
  Minor non-conflicting homonym: "pediment" is also a geology term (a bedrock erosion
  surface) — different field, no commercial-mark conflict.

## Diligence — candidates evaluated and why they were rejected

| Candidate | Outcome | Reason |
|---|---|---|
| Northwind | Rejected | Existing NorthWind WP theme on ThemeForest; heavily generic. |
| Northgate | Rejected by user | "North-/gate" compass direction felt tired. |
| Plinth | Rejected | Existing Plinth WP theme (AtreNet). |
| Basalt | Rejected | Basalt Inc — design-systems agency / Pattern Lab maintainer (adjacent space). |
| Ashlar | Rejected | Existing Ashlar WP theme on WordPress.org + Ashlar Projects (WP agency). |
| Ribvault | Rejected | Collides with "RIB Vault" (RIB Software, DE construction-software firm). |
| Trabeate | Rejected by user | Sound; user preferred a recognizable word over an obscure coinage. |
| Portico | Rejected | Too close to "Porto" — one of the best-selling WP themes (80k+ sales). |
| Atrium | Rejected | Multiple existing WP themes, incl. a finance/consulting one (exact niche). |
| Mortar | Rejected | Active "Mortar" agency WP theme on ThemeForest (same positioning). |
| Lintel | Viable runner-up | Cleanest namespace; less prestige than Pediment. |
| Rotunda | Viable runner-up | High prestige; minor company-name overlap (Rotunda Software, LLC, unrelated field). |
| **Pediment** | **Selected** | Clean namespace + trademark, strong architectural gravitas, free `.com` variants. |

Structural reality observed: in 2026 virtually every short real-word and ≤7-letter
pronounceable coined `.com` is investor-held; recognizable web/WP/software words are
nearly all already used by an existing theme or product. Variant-domain strategy is the
realistic path for a recognizable name.

## Open Caveat (not a blocker)

These web/USPTO/EUIPO searches reduce risk but are **not a legal clearance**. Before
filing a trademark or investing in heavy branding, run a formal USPTO/EUIPO search (or
engage an attorney) — especially for the EU, since the owner is DE-based.

## Rename Scope (for the implementation plan)

The name appears across the codebase and must be changed consistently. Known surface
(non-exhaustive — the plan must enumerate fully):

- `style.css` — Theme Name, Theme URI, Description, Text Domain.
- `package.json` — `name`.
- `composer.json` — `name`, `description`.
- Text domain `starter` → new slug, used in `inc/*.php`
  (`BrandRegistry.php`, `brand-settings.php`, `patterns.php`, `register-blocks.php`,
  `contact-form.php`), block `render.php` files, and `src/blocks/**/block.json`
  `textdomain` fields.
- PHPCS custom sniff namespace/path `tools/phpcs-sniffs/Starter/` and `phpcs.xml.dist`.
- Tests referencing the text domain / theme name (`tests/phpunit/**`).
- Docs/branding references (`README.md`, `AGENTS.md`, `docs/*`).
- CI/release workflows that reference the theme slug or zip name.

Open decisions for the plan:

- **Slug:** `pediment` for text domain / theme folder (confirm no WP-core clash).
- **Repo / Composer package:** keep `bergert/wp-starter-theme` or rename to
  `bergert/pediment`? (Renaming the GitHub repo affects existing remotes/links.)
- ~~Domain to register~~ — resolved: `pedimenttheme.com` (registered 2026-05-17).

These are resolved during planning, not here.
