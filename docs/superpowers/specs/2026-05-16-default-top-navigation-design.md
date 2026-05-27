# Default Top Navigation — Design

**Date:** 2026-05-16
**Status:** Approved
**Scope:** Style and populate the starter theme's default top navigation: a seeded starter menu, always-sticky header, core responsive overlay on mobile, and full visual treatment (active page, hover/focus, CTA button, submenus).

## Context

The header part [parts/header.html](../../../parts/header.html) currently contains a bare
`<!-- wp:navigation /-->` block: no menu reference, no items, no fallback configured. It
falls back to WordPress's auto page-list. `theme.json` already styles
`styles.blocks.core/navigation` (size `sm`, weight `500`, text color, hover → accent) and
keeps global CSS in `styles.css`. The theme seeds Home/About/Contact/Blog pages via the
`wp starter-theme seed` CLI command ([inc/seed.php](../../../inc/seed.php)).

## Decisions

| Topic | Decision |
|-------|----------|
| Sticky behavior | Always sticky, full size (no shrink/hide on scroll) |
| Mobile menu | Core Navigation block built-in responsive overlay (no custom JS) |
| Default menu source | Inner blocks baked into the header part (Approach 1) |
| Menu items | About · Blog · **Contact** (Contact = filled CTA button); site title links home |
| Visual touches | Active page indicator, hover/focus states, CTA button item, submenu styling |

### Why Approach 1 (inner blocks in the header part)

A static template part cannot bake a dynamic `wp_navigation` post ID, so PHP-seeding a
Navigation post on activation gains nothing practical (links must be relative URLs anyway,
since seeded pages don't exist at activation time) while adding fragile reference logic.
Inner blocks render immediately on a freshly activated theme with near-zero PHP, and
WordPress auto-promotes them to an editable `wp_navigation` entity the first time the
Site Editor loads/saves the template part. This is the standard FSE pattern (Twenty
Twenty-Four / Twenty Twenty-Five). Trade-off: links are relative URLs (`/about`) rather
than DB-bound page references — robust, but not "linked" in the database sense.

### Active-state addendum (discovered during implementation review)

Core's `core/navigation-link` only emits `current-menu-item` / `aria-current="page"`
when the link carries a non-empty `id` matching the queried object
(`wp-includes/blocks/navigation-link.php`). `kind:"custom"` relative-URL links never
get it, so the active-page indicator cannot work from markup/CSS alone. Baking page
`id`s into a static template part is fragile (auto-increment IDs differ per install).
**Resolution:** keep the relative-URL inner blocks and add one small, focused PHP filter
on `render_block_core/navigation-link` that sets `aria-current="page"` when the link's
URL path equals the current request path. This is the minimal deviation from the
zero-PHP pillar and keeps links install-independent.

Known limitation: the filter compares request and link *paths* without stripping a
`home_url()` subdirectory prefix, so on a subdirectory WordPress install the
active-page indicator silently does nothing (the menu still works). Root installs —
the documented target and the test environment — are unaffected. Hardening (strip the
`home_url()` path from both sides) is a deferred, optional follow-up.

## Revision: Approach 2 supersedes Approach 1 (post-merge)

**Why:** After merge, the Site Editor → Navigation screen still showed the old
page-list, not the curated menu. Root cause: that screen (and core's nav resolution)
operates on `wp_navigation` *entities*, not template-part inner blocks. WordPress has
no file-based mechanism for a specific default menu — navigation content is
database-only (`wp_navigation` CPT). Baked inner blocks render on the front end but
diverge permanently from the editor-managed entity; that divergence is structural to
core, not a fixable defect. To have curated items (incl. the Contact CTA) that are also
the editor-managed, consistent menu, the menu must exist as a `wp_navigation` entity —
i.e. Approach 2 (previously "out of scope"). User chose Approach 2 done natively.

**Approach 2 design (authoritative; overrides "Design" §1 and the Decisions row
"Default menu source"):**

1. `parts/header.html` — **bare** `<!-- wp:navigation {"overlayMenu":"mobile",…} /-->`
   (inner blocks removed), `site-header` group class retained. Matches the core-theme
   pattern; the block resolves to a `wp_navigation` entity.
2. `inc/nav-seed.php` (new) — idempotent seeder on `after_switch_theme` (fires on
   child-theme activation too, as the parent `functions.php` always loads) and reused
   by `wp starter-theme seed`. Menu content = three `wp:navigation-link` blocks
   (About `/about`, Blog `/blog`, Contact `/contact` + `nav-cta`).
   - **Adopt, don't proliferate:** if a `wp_navigation` post is a pristine fallback
     (content exactly `<!-- wp:page-list /-->`) or carries our `_starter_seeded_nav`
     meta marker, rewrite its content to our links and stamp the marker; otherwise
     create a new post (title "Header Navigation", slug `starter-header`, marker set).
   - **Overwrite protection:** never modify a `wp_navigation` post that is neither a
     pristine page-list fallback nor our own marked post (protects user edits).
3. `inc/nav-seed.php` — `render_block_data` filter for `core/navigation`: if the block
   has no `ref`, inject `ref` = the seeded post ID. Deterministic resolution on front
   end + REST render; not reliant on the fragile "newest entity" heuristic.
4. `inc/nav-active.php`, all `theme.json` CSS, and the e2e/PHPUnit specs are retained.
   A PHPUnit test covers the seeder's adopt / create / protect branches. The e2e
   assertions are unchanged (items now sourced from the entity).
5. On the shared :8890 env, stale entity #4 is exactly `<!-- wp:page-list /-->`, so the
   seeder *adopts* it — no duplicate; editor and front end converge.

This uses the proper `wp_navigation` CPT and core's own resolution path: standards-
compliant at the data layer, with the minimal PHP core structurally requires.

## Design

### 1. Header part markup — `parts/header.html`

- Add a `site-header` class to the top-level `<header>` group (sticky targeting hook).
- Populate the navigation block with three `wp:navigation-link` inner blocks:

```html
<!-- wp:navigation {"overlayMenu":"mobile","layout":{"type":"flex","orientation":"horizontal"},"style":{"spacing":{"blockGap":"var:preset|spacing|30"}}} -->
  <!-- wp:navigation-link {"label":"About","url":"/about","kind":"custom"} /-->
  <!-- wp:navigation-link {"label":"Blog","url":"/blog","kind":"custom"} /-->
  <!-- wp:navigation-link {"label":"Contact","url":"/contact","kind":"custom","className":"nav-cta"} /-->
<!-- /wp:navigation -->
```

- `overlayMenu:"mobile"` — core hamburger overlay on small screens, inline on desktop.
- Relative URLs resolve correctly whether or not the seed CLI has run.
- `nav-cta` class on Contact drives button styling; preserved and visible in the editor's
  Advanced → CSS class field.
- No Home item — the site title already links home.

### 2. Sticky behavior

Added to `theme.json` → `styles.css` (theme's established home for global CSS):

```css
.site-header{position:sticky;top:0;z-index:50;background:var(--wp--preset--color--surface)}
```

The existing bottom border is retained. Explicit `surface` background prevents page content
bleeding through while scrolling. `position:sticky` works as a child of the existing
`.wp-site-blocks` flex column.

### 3. Styling

Extends the existing `styles.blocks.core/navigation` rules; raw CSS appended to
`theme.json` → `styles.css`:

- **Active page:** `.wp-block-navigation .current-menu-item > a`,
  `.wp-block-navigation a[aria-current="page"]` → accent color + underline indicator.
  The `aria-current="page"` attribute is supplied by the PHP filter in
  [inc/nav-active.php](../../../inc/nav-active.php) (see Active-state addendum).
- **Focus-visible:** keyboard focus ring using the existing `--wp--preset--shadow--focus`
  token.
- **CTA item (`.nav-cta`):** filled `accent` background, `surface` text, `0.5rem` radius,
  button padding, no underline; hover → `accent-hover`. Reuses the same tokens as the
  theme's `button` element. Stays a button in the mobile overlay.
- **Submenu/dropdown:** `.wp-block-navigation__submenu-container` → `surface` background,
  `border` color, `lifted` shadow, `0.5rem` radius, padding; default core hover-to-open
  retained.
- **Mobile overlay:** `surface` background for `.is-menu-open`, text-colored
  hamburger/close icon, items inherit the above (CTA still a button).

### 4. Verification

Per the single-test-env rule, verify on the child-theme wp-env at **localhost:8890**
(which runs this starter theme as the parent) — never start wp-env from this directory.
A new Playwright e2e spec asserts:

- the three menu items render,
- the header computes `position: sticky`,
- `.nav-cta` renders with button styling (background + radius),
- the overlay toggles below the mobile breakpoint,
- the current page receives the active treatment.

One small PHP filter is added (`inc/nav-active.php`), so a PHPUnit test covers its
pure path-matching helper. The filter's end-to-end wiring is exercised by the Playwright
active-treatment assertion above.

## Files touched

- `parts/header.html` — class + navigation inner blocks
- `theme.json` — `styles.css` additions (sticky, active, focus, CTA, submenu, overlay)
- `inc/nav-active.php` — `render_block_core/navigation-link` active-state filter (new)
- `inc/nav-seed.php` — `wp_navigation` entity seeder + `core/navigation` ref filter (new, Approach 2)
- `functions.php` — `require_once` the new filters
- `tests/e2e/navigation.spec.ts` — Playwright e2e spec for the navigation
- `tests/phpunit/` — unit tests for the active-state helper and the nav-entity seeder

## Out of scope (YAGNI)

- Scroll-shrink / hide-on-scroll header behavior
- Custom JS mobile menu
- Mega-menu / multi-column submenus
