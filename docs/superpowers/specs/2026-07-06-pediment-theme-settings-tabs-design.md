# Pediment Theme settings page with tabs

**Date:** 2026-07-06
**Status:** Approved

## Goal

Move the forms settings UI out from under the **Form Submissions** CPT submenu and into a
dedicated **Settings → Pediment Theme** options page, organized as tabs. Introduce a small,
reusable tab framework so future theme settings drop in as new tabs without touching forms code.

## Current state

`inc/forms-settings.php` registers a single admin page as a submenu of the `form_submission`
CPT (`add_submenu_page( 'edit.php?post_type=' . PEDIMENT_FORM_CPT, … )`, slug `pediment-forms`).
The page renders three stacked sections in one function
(`pediment_form_render_settings_page()`):

1. **General** — retention days + default destination
2. **Secrets** — encrypted credential list + add form
3. **Destinations** — destinations table + add/edit editor (presets, send-test)

Five `admin_post_*` handlers process the forms; `pediment_form_settings_redirect()` bounces back
to the CPT-scoped URL with a transient notice. A JS file
(`assets/js/admin-forms-settings.js`) drives presets + header rows, keyed off CSS classes
(`.pediment-forms-destination`, `.pediment-forms-preset`), enqueued when the hook suffix contains
the page slug.

Nothing outside `inc/forms-settings.php` and the JS references the page location. No e2e or
PHPUnit test navigates to the settings URL or asserts its menu placement; the PHPUnit suites
exercise the logic functions (sanitize, save, secret, test-destination) directly.

## Design

### 1. New location

Replace the CPT submenu registration with `add_options_page()`:

- Page title: **Pediment Theme**
- Menu title: **Pediment Theme**
- Capability: `manage_options`
- Slug: `pediment-theme`
- URL: `options-general.php?page=pediment-theme`

The page no longer appears under Form Submissions.

### 2. Tab framework — `inc/settings-page.php` (new)

A generic, forms-agnostic registry:

```php
pediment_settings_register_tab( string $id, string $label, callable $render, int $priority = 10 ): void;
```

Responsibilities:

- Own the `add_options_page()` registration for `pediment-theme`.
- Maintain an in-memory list of registered tabs.
- Resolve the active tab from `$_GET['tab']` (sanitized with `sanitize_key`), defaulting to the
  lowest-priority registered tab. Unknown tab id falls back to the default.
- Render the page shell: `<h1>Pediment Theme</h1>`, `settings_errors()`, a
  `nav-tab-wrapper` with one `nav-tab` link per registered tab (sorted by priority then
  registration order), then invoke the active tab's render callback inside `.wrap`.
- Expose a helper `pediment_settings_page_url( string $tab = '' ): string` returning the
  `options-general.php?page=pediment-theme[&tab=…]` admin URL, for redirects.

No `apply_filters` hook for now (YAGNI) — the registry is a plain function; a filter can be added
later if a plugin needs to inject a tab.

### 3. Forms registers three tabs — `inc/forms-settings.php`

Split `pediment_form_render_settings_page()` into three focused render callbacks, each registered
via the framework on `admin_init` (or an equivalent hook that runs before the page renders):

| Tab id        | Label             | Priority | Content                                                        |
|---------------|-------------------|----------|----------------------------------------------------------------|
| `general`     | General           | 10       | Retention days + default destination form                      |
| `destinations`| Form Destinations | 20       | Destinations table + add/edit editor (presets, headers, test)  |
| `secrets`     | Secrets           | 30       | Secrets list + add form                                        |

Reading order: General → Form Destinations → Secrets. `general` is the default tab.

Each callback renders only its own `<form>`(s); the surrounding `.wrap`, heading, nav, and
`settings_errors()` come from the framework.

### 3a. Editing destinations

Today the destinations UI can only *add* (or blind-overwrite by retyping every field): the
add/edit form always renders blank and there is no per-row edit action. Add a real edit flow,
server-side (no new JS dependency):

- Each destinations-table row gets an **Edit** link:
  `pediment_settings_page_url( 'destinations' )` with `&edit=<id>` appended.
- `pediment_form_render_destinations_tab()` reads `$_GET['edit']` (`sanitize_key`) and, when it
  matches a stored destination, pre-fills the add/edit form from that record:
  - `id` — pre-filled **and `readonly`** (changing the id would create a new record via the
    upsert-by-id save, not rename). A hidden field is not needed; the readonly text input still
    posts its value.
  - `label`, `url`, `body_template` — pre-filled values.
  - `method`, `content_type` — the matching `<option>` marked `selected`.
  - Headers — one populated `.pf-header-row` per stored header (key + value); when the record has
    no headers, render a single blank row (current behavior).
  - Secret tokens (`{{ secret:NAME }}`) in the url/headers/body render back verbatim — they are
    references, not secret values, so this leaks nothing.
- In edit mode the section heading reads "Edit destination: `<id>`", the submit button reads
  "Update destination", and a **Cancel / add new** link points back to the blank form
  (`pediment_settings_page_url( 'destinations' )`).
- In add mode (no/unknown `edit`) the form renders exactly as today: blank, id editable, heading
  "Add / edit destination", button "Save destination".
- The save handler (`pediment_form_handle_save_destination`) and sanitize logic are unchanged —
  upsert-by-id already updates an existing record when the posted id matches.

A small render helper keeps this testable: `pediment_form_destination_form_values( string $edit_id ): array`
returns the field defaults (`id`, `label`, `method`, `url`, `content_type`, `headers`,
`body_template`, and an `is_edit` bool) for a given edit id — an empty/unknown id yields the blank
add-mode defaults. The render function consumes this array; unit tests assert it against stored
destinations.

### 4. Wiring updates

- **Redirect:** `pediment_form_settings_redirect()` gains a `$tab` argument (or infers it from the
  submitted form) and redirects to `pediment_settings_page_url( $tab )`, preserving the active tab
  so the transient notice lands on the tab that was edited. Each handler passes its own tab id
  (`general` → general handler, `destinations` → save/delete destination + test, `secrets` →
  save/delete secret).
- **Enqueue:** the `admin_enqueue_scripts` condition matches the new hook suffix
  (`settings_page_pediment-theme`). The destinations JS still loads on the page; it is harmless on
  the other tabs (its target selectors are absent), so no per-tab gating is required.
- **Handlers & logic:** all five `admin_post_*` handlers and every sanitize/save/secret/test
  function stay unchanged. Only the page shell and redirect target move.
- **Constants:** `PEDIMENT_FORM_SETTINGS_PAGE` is removed or repointed; the new page slug lives in
  the framework (e.g. `PEDIMENT_SETTINGS_PAGE = 'pediment-theme'`).

### 5. Loading

`inc/settings-page.php` must be required before `inc/forms-settings.php` registers its tabs
(functions.php include order), so `pediment_settings_register_tab()` is defined when called.

## Testing

- **Existing PHPUnit suites** (SettingsSanitize, SettingsTestDestination, Secrets, Retention, …)
  are untouched by this change and must keep passing.
- **New PHPUnit test** (`tests/phpunit/Settings/TabsTest.php` or similar): assert that after the
  forms module loads, the three tabs (`general`, `destinations`, `secrets`) are registered with the
  expected labels and ordering, and that the active-tab resolver defaults to `general` and falls
  back to `general` for an unknown tab id.
- **Edit pre-fill test** (`pediment_form_destination_form_values`): a known `edit` id returns the
  stored record's field values with `is_edit === true`; an empty or unknown id returns blank
  add-mode defaults with `is_edit === false`.
- **Manual / e2e check:** Settings → Pediment Theme shows three tabs; each tab's save round-trips
  and shows its success/error notice on the correct tab; clicking **Edit** on a destination
  pre-fills the form (id readonly), saving updates it in place; the page is gone from under Form
  Submissions.

## Out of scope

- No changes to the destinations/secrets/delivery *data storage* format or the save/sanitize
  logic (edit reuses the existing upsert-by-id save).
- No filter-based tab extensibility (deferred).
- No new settings fields beyond relocating, re-tabbing, and adding an edit affordance to the
  existing ones.
