# WPML Support — Design

**Date:** 2026-08-28
**Status:** Approved (design), pending implementation plan
**Path classification:** Architectural

## Goal

Add first-class **WPML** support to the Pediment plugin, at full feature
parity with the existing Polylang integration. A site can run either
Polylang *or* WPML (never both active at once), and the Pediment seeding
engine, front-end nav binding, language switcher, and the
`wp pediment languages` configuration command all work the same way
against whichever plugin is active.

## Context: the existing seam

The codebase was designed for this. Everything multilingual flows through
a single abstraction in `plugin/src/Language/`:

- **`LanguageProvider`** (interface) — the one seam between the seeding
  engine and a multilingual plugin. Methods: `languages()`,
  `defaultLanguage()`, `setLanguage(int,string)`, `hasLanguage(int)`,
  `linkTranslations(array)`, `translationOf(int,string)`,
  `unscopedQuery(array)`.
- **`PolylangProvider`** — the Polylang implementation; the *only* place
  (besides `PolylangSetup` and two `inc/` files) allowed to call `pll_*`.
- **`NullProvider`** — monolingual fallback when no multilingual plugin is
  active.
- **`LanguageRegistry`** — resolver. `provider()` memoizes and returns the
  active provider (`PolylangProvider::isActive() ? … : NullProvider`),
  with a `pediment_language_provider` filter override.
- **`PolylangSetup`** — a *concrete* class (no interface yet) that
  reconciles Polylang's own settings to the manifest; run by
  `wp pediment languages`, invoked as `new PolylangSetup()` from
  `plugin/wp-cli/LanguagesCommand.php`.

Engine consumers already take a `LanguageProvider` via constructor DI:
`Seeder/Claimer`, `Applier`, `NavSeeder`, `StateReader`, `Adopter`,
`Verifier`, `DesiredState`. The two runners resolve it from the registry.

Three integration categories exist today, and the WPML work touches all
three:
1. The provider abstraction (`plugin/src/Language/`) — the intended seam.
2. Two front-end `inc/` files calling `pll_*` directly
   (`inc/nav-language.php`, `inc/polylang-compat.php`).
3. One hardcoded emitted block string
   (`wp:polylang/navigation-language-switcher`) in `NavSeeder`.

The Polylang author left WPML-anticipating comments, e.g.
`PolylangProvider::unscopedQuery()` sets `suppress_filters=true`
specifically because "WPML scopes through the `posts_*` filters this flag
turns off."

## Decisions (from brainstorming)

- **CI testing:** a WPML license is available; CI runs against a **real
  WPML install** (Approach A), zip supplied via secret.
- **Scope:** **full parity** — provider + a WPML analogue of
  `PolylangSetup` (writes WPML's own languages/translatable-types) + a
  WPML language switcher in the seeded header.
- **Language switcher:** target **latest WPML**; emit WPML's **native
  `wpml/language-switcher` block**.
- **Precedence when both are active (edge case):** Polylang wins.
  Detection order Polylang → WPML → Null; filter-overridable.
- **Chosen approach:** Approach 1 — extend the existing seam and extract
  two small interfaces, keeping all WPML code quarantined the same way
  Polylang's is (no scattered `if (wpml)` branches through the engine).

## Design

### 1. Interface & seam changes (`plugin/src/Language/`)

**`LanguageProvider` gains two methods** (both existing providers
implement; `NullProvider` returns safe empties):

- `currentLanguage(): string` — front-end current-language code.
  Replaces the direct `pll_current_language()` call in
  `inc/nav-language.php`, which currently bypasses the seam.
- `languageSwitcherBlock(): string` — returns the serialized switcher
  block for the seeded header. Polylang returns today's
  `wp:polylang/navigation-language-switcher` string; WPML returns a
  `wp:wpml/language-switcher` block. `NavSeeder` calls this instead of
  hardcoding the string.

**New `LanguageSetup` interface** — extracted from the concrete
`PolylangSetup`. One method: `configure(Manifest $manifest): void`,
matching the current signature. `PolylangSetup` implements it unchanged;
`WpmlSetup` is the new implementation.

**`LanguageRegistry` gains `setup(): LanguageSetup`** — resolves the setup
class the same way `provider()` resolves the provider, with a parallel
`pediment_language_setup` filter. `LanguagesCommand` calls
`LanguageRegistry::setup()->configure(...)` instead of
`new PolylangSetup()`.

**Detection precedence** (both `provider()` and `setup()`):
Polylang (`PolylangProvider::isActive()`) → WPML
(`WpmlProvider::isActive()`) → Null. Polylang wins if both are
configured. Overridable via the respective filter.

### 2. `WpmlProvider` (`plugin/src/Language/WpmlProvider.php`)

`implements LanguageProvider`. All WPML / `icl_*` calls are quarantined
here, same invariant as `PolylangProvider`.

| Interface method | WPML realization |
|---|---|
| `isActive()` | `defined('ICL_SITEPRESS_VERSION')` **and** `apply_filters('wpml_active_languages', null)` is non-empty (parity with Polylang's "configured, not merely installed" rule) |
| `languages()` | `apply_filters('wpml_active_languages', null)` → return the language codes (keys) |
| `defaultLanguage()` | `apply_filters('wpml_default_language', null)` |
| `currentLanguage()` | `apply_filters('wpml_current_language', null)` |
| `setLanguage(int $id, string $lang)` | `do_action('wpml_set_element_language_details', …)` with `element_type = 'post_' . get_post_type($id)`, a fresh **trid**, `language_code = $lang`, no source |
| `hasLanguage(int $id)` | `apply_filters('wpml_element_language_code', null, [...])` returns non-null for the post |
| `linkTranslations(array $idsByLang)` | anchor the group on the default-language post's **trid**, then re-register each other post via `wpml_set_element_language_details` with that `trid` + `source_language_code`. Mirrors Polylang's "replace whole group" semantics |
| `translationOf(int $id, string $lang)` | `apply_filters('wpml_object_id', $id, get_post_type($id), false, $lang)`; returns null when the translation is absent, like `pll_get_post` |
| `unscopedQuery(array $args)` | same idiom as Polylang — set `lang`/scoping off and `suppress_filters = true` (the existing comment already documents this covers WPML) |
| `languageSwitcherBlock()` | serialized `wp:wpml/language-switcher` block (latest WPML's native block) |

**Key WPML wrinkle — trids.** WPML has no separate "set language" vs
"link translation"; both are **trid** (translation-group id) operations.
`WpmlProvider` hides this internally so the engine's existing
`setLanguage()` → `linkTranslations()` call order (in `Applier`,
`NavSeeder`) works unchanged. `setLanguage()` assigns/creates a trid;
`linkTranslations()` re-anchors the group's members onto a single trid.

### 3. `WpmlSetup` (`plugin/src/Language/WpmlSetup.php`)

`implements LanguageSetup`, the WPML analogue of `PolylangSetup`.
Reconciles WPML's own settings to the manifest:

- **Languages** — ensure the manifest's languages are active and the
  default matches. Activate via WPML's settings API
  (`SitePress::set_active_languages` / the `icl_sitepress_settings`
  option), set default via `wpml_set_default_language`. Where WPML lacks
  a public setter, write through its settings option the same way
  `PolylangSetup` writes `PLL()->options`.
- **Translatable post types** — the analogue of the two
  `pll_get_post_types` filters: register `wp_navigation` as translatable,
  keep `wp_template_part` **shared** (not translated). WPML reads this
  from config, so inject via the **`wpml_config_array` filter** and set
  the per-type translation mode in
  `icl_sitepress_settings['custom_posts_sync_option']`.

### 4. Front-end & compat

- **`plugin/inc/wpml-compat.php`** — new file, loaded unconditionally
  alongside `polylang-compat.php` from `plugin.php`, no-ops when WPML is
  inactive. Registers the `wpml_config_array` filter so `wp_navigation`
  is translatable and `wp_template_part` is shared at runtime (parity
  with `polylang-compat.php`'s two `pll_get_post_types` filters).
- **`plugin/inc/nav-language.php`** — replace the inline
  `pll_current_language()` / `pll_default_language()` lookups with
  `LanguageRegistry::provider()->currentLanguage()` /
  `->defaultLanguage()`, making the nav binding provider-agnostic. The
  `lang` unscoping query var stays (works for both plugins).
- **`plugin/wp-cli/LanguagesCommand.php`** — resolve setup via
  `LanguageRegistry::setup()` instead of `new PolylangSetup()`.

### 5. `NavSeeder`

Replace the hardcoded `wp:polylang/navigation-language-switcher` string
with `$this->language->languageSwitcherBlock()`. The `language_switcher`
manifest key and its plumbing are unchanged.

## Testing & CI

Mirror the existing Polylang test setup in a **separate WPML environment**
(WPML and Polylang cannot be active together):

- **`plugin/tests/wpml/`** mirroring `plugin/tests/polylang/`:
  `bootstrap.php` (requires the real WPML plugin; activates the
  manifest's languages via WPML's settings API), plus `WpmlProviderTest`,
  `WpmlSetupTest`, and behavior tests paralleling the Polylang ones
  (Claimer / Applier / NavLanguage / NavBinding / Adopter / DesiredState /
  RunnerLanguageGate / RegistryDetection).
- **Interface contract** — extend the shared
  `plugin/tests/phpunit/Language/LanguageProviderTest.php` so the contract
  runs against `WpmlProvider` too.
- **E2E** — `plugin/tests/e2e/multilingual-wpml.spec.ts` paralleling
  `multilingual.spec.ts`, driving WPML via `wp eval`
  (`wpml_set_element_language_details` / `wpml_object_id`).
- **wp-env** — a WPML-specific config (CI-only) that provisions WPML from
  a **local file path**, not a public URL. The WPML zip(s) are supplied
  to CI as a **secret** (base64, or an authenticated download URL kept in
  a repo secret), decoded to a local dir in the workflow before
  `wp-env start`.
- **WPML components (to pin during implementation):** core is
  `sitepress-multilingual-cms`; the native language-switcher block may
  also require **WPML String Translation**. The implementation plan
  confirms and pins the exact component list against the target WPML
  version before writing the switcher block emitter.
- **Graceful skip** — when the WPML secret/zip is absent (forked PRs, a
  contributor without a license), the WPML suite **skips** rather than
  fails, keeping the repo green for licenseless contributors while the
  maintainer's CI runs the real integration.
- **CI job** — a new `wpml` job alongside the existing `polylang` one,
  gated on the secret being present.

## Files touched

New:
- `plugin/src/Language/WpmlProvider.php`
- `plugin/src/Language/WpmlSetup.php`
- `plugin/src/Language/LanguageSetup.php` (interface)
- `plugin/inc/wpml-compat.php`
- `plugin/tests/wpml/*` (bootstrap + test suite)
- `plugin/tests/e2e/multilingual-wpml.spec.ts`
- CI-only wp-env config for WPML + new CI job

Modified:
- `plugin/src/Language/LanguageProvider.php` (two new methods)
- `plugin/src/Language/PolylangProvider.php` (implement two new methods)
- `plugin/src/Language/NullProvider.php` (implement two new methods)
- `plugin/src/Language/PolylangSetup.php` (implement `LanguageSetup`)
- `plugin/src/Language/LanguageRegistry.php` (`setup()` + WPML detection)
- `plugin/src/Seeder/NavSeeder.php` (use `languageSwitcherBlock()`)
- `plugin/inc/nav-language.php` (route through provider)
- `plugin/wp-cli/LanguagesCommand.php` (resolve setup via registry)
- `plugin/plugin.php` (load `wpml-compat.php`)
- `plugin/tests/phpunit/Language/LanguageProviderTest.php` (run vs WPML)

## Non-goals

- Running Polylang and WPML simultaneously (unsupported by both plugins;
  precedence is only a deterministic tie-break, not a supported mode).
- WPML String Translation of theme strings beyond what the switcher block
  requires.
- Migrating existing Polylang sites to WPML.
