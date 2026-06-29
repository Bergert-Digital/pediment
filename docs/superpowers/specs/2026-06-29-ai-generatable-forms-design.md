# AI-generatable forms — design

**Date:** 2026-06-29
**Status:** Approved (brainstorm), pending implementation plan
**Repo:** `pediment` theme (this workspace). No code changes required in `pediment-ai`.

## Problem

The theme ships a single `pediment/contact-form` block with a frozen field set
(name / email / message / optional phone). The `pediment-ai` plugin already
auto-discovers it and can drop it onto a page, but it is the *only* form and
cannot be reshaped. We want the AI to generate **arbitrary forms** — it invents
the fields for the purpose at hand (booking request, registration, feedback
survey, newsletter signup, …) and the form delivers submissions to **multiple
email recipients and/or a webhook** (e.g. an n8n endpoint). Child themes must be
able to build on top of the system.

## Decisions (from brainstorming)

1. **Arbitrary AI-shaped forms** (not a fixed catalogue of new form types).
2. **Generalize and replace** — one generic form system; contact becomes a
   preset on top of it; the standalone `contact-form` block is deprecated.
3. **Admin-defined named destinations only.** The AI/form references a
   destination by id; it can never write a raw email address or webhook URL.
   This removes the open-mail-relay / SSRF surface.
4. **One generic field block** keyed by a `fieldType` attribute (not one block
   per field type).
5. **Always store + deliver**, with a configurable retention/purge.
6. **All code lives in the theme.** The AI integration is a theme-side
   `pediment_ai_system_prompt` filter — no `pediment-ai` change.
7. Destinations are **admin-only** (editors pick from them, cannot create them).

## Architecture

The post content *is* the form definition. The AI emits a block tree; the editor
shows/edits it; the submission endpoint re-derives the authoritative field list
from the saved post content at submit time. One source of truth, secure by
construction (the browser cannot lie about a form's fields or required rules).

### 1. Blocks (`src/blocks/`)

**`pediment/form`** — container block.
- `supports`: `align: [wide]`, `html: false`.
- `allowsInnerBlocks: true`, `allowedChildBlocks: [pediment/form-field]`.
- Attributes:
  - `destination` (string, default `""`) — id of a registered destination;
    empty → site `default` destination.
  - `successMessage` (string).
  - `submitLabel` (string, default `"Send"`).
- `render.php` outputs: the `<form>` wrapper, a hidden honeypot field
  (`hp_field`), a hidden time-trap timestamp (`_t`), hidden `post_id` and
  `form_key`, the nested fields, the submit button, and an inline success/error
  container.
- `view.js` posts to the REST endpoint and renders field-keyed errors inline,
  reusing the patterns in today's `src/blocks/contact-form/view.js`.

**`pediment/form-field`** — child block (`requiresParent: [pediment/form]`).
- Attributes:
  - `fieldType` (enum): `text · email · tel · textarea · select · checkbox ·
    radio · number · date`.
  - `label` (string, required).
  - `name` (string) — machine name; auto-slugged from `label` when empty; must
    be unique within the form (editor de-duplicates).
  - `required` (boolean, default `false`).
  - `placeholder` (string).
  - `helpText` (string).
  - `options` (array of `{ label, value }`) — used by `select` and `radio`.
- `render.php` emits the appropriate control for the `fieldType`, with label,
  `required` marker, `helpText`, and `aria` wiring.

### 2. Submission endpoint (`inc/forms.php`)

- Route: `POST pediment/v1/forms`.
- Back-compat: the existing `POST pediment/v1/contact` route is kept as a thin
  alias that maps onto the new handler.
- `permission_callback => __return_true` (public, anonymous form — same posture
  as the current contact endpoint). Anti-spam = honeypot (`hp_field`) +
  time-trap (`_t`), reusing the existing constants/min-age logic.
- Request payload: `post_id`, `form_key` (block `anchor`/index used to
  disambiguate multiple forms on one page), and the field values.
- **Server-authoritative validation:**
  1. Load the post by `post_id`; `parse_blocks` on its content.
  2. Locate the matching `pediment/form` block via `form_key`.
  3. Derive the field list from its `pediment/form-field` children.
  4. Validate each submitted value against the derived field: `required`
     presence, type format (email / number / date), option membership for
     `select`/`radio`, and **reject any field not in the derived list**.
  5. Return a field-keyed error map on failure (same envelope as the current
     contact handler).
- On success → store, then deliver (see below).

### 3. Storage — `form_submission` CPT

- Private CPT (`public => false`, `show_ui => true`), admin-readable list.
- Each submission stores: per-field meta + a JSON snapshot of all values, source
  `post_id`, resolved `destination` id, `submitted_at`, and a delivery-status
  record (per channel: pending / sent / failed + error).
- **Store first, then deliver** — durability against a failed email/webhook.
- Retention: a global setting (days; `0` = keep forever) drives a cron purge
  that generalizes the existing `pediment_contact_cleanup` hook.

### 4. Destinations + delivery

**Registry (`inc/forms-destinations.php`).**
- Final list = admin-defined destinations (stored option, edited in a new
  **Settings → Forms** tab) merged with code-registered ones via the
  `pediment_form_destinations` filter.
- A destination = `{ label, emails: [], webhook: "", reply_to: "", subject: "",
  secret: "" }`.
- A built-in `default` destination falls back to the configured site recipient
  (or `admin_email`).

**Delivery (`inc/forms-delivery.php`).**
Resolves the destination id → destination, then runs the channels
independently:
- **Email** → `wp_mail` to each recipient; body formatted from field
  labels/values; `reply_to` and `subject` honored.
- **Webhook** → `wp_remote_post` with JSON `{ fields, post_id, destination,
  submitted_at }`. If `secret` is set, add an `X-Pediment-Signature` HMAC header
  (SHA-256 over the raw body) so n8n can verify authenticity. HTTPS-only;
  non-HTTPS webhooks are rejected at save time.
- The per-channel result is written back onto the submission record.

### 5. Child-theme extensibility

- `pediment_form_destinations` (filter) — register destinations in code.
- `pediment_form_field_types` (filter) — add a `fieldType` with its
  validator + renderer callbacks (e.g. a file or date-range field).
- `pediment_form_submission_received` (action, `$submission`, `$destination`) —
  side-effects (CRM sync, Slack, analytics).
- `pediment_form_email_body` / `pediment_form_webhook_payload` (filters) —
  reshape delivery output.

### 6. Contact-form migration

- Reimplement contact as a **preset pattern**: a `pediment/form` with
  name / email / message (/ phone) fields → `default` destination, carrying the
  old success copy. Lives under `patterns/`.
- `pediment/contact-form` is **deprecated**:
  - Removed from the inserter (`inserter: false` / unregistered from picker).
  - Kept rendering via a server-side shim so existing published pages keep
    working.
  - A block transform converts old instances into the generic `pediment/form`.
  - `contact_submission` data and the `/contact` route remain for back-compat.
  - Full removal deferred to a later major version.

### 7. pediment-ai integration (theme-side only)

- `add_filter('pediment_ai_system_prompt', …)` (filter already exists in
  `pediment-ai`'s `PromptBuilder`, and receives the block schema). The theme
  appends:
  - the **live list of valid destination ids + labels**, and
  - a short authoring note: "to collect input, use `pediment/form` with
    `pediment/form-field` children; set `destination` only to one of the listed
    ids; never invent a destination id (omit it to use the default)."
- No change to the `pediment-ai` plugin.

## Out of scope (YAGNI for v1)

Multi-step / wizard forms, conditional field logic, file uploads (left as a
`pediment_form_field_types` extension point), CAPTCHA/reCAPTCHA, payment fields,
per-form storage toggles, per-IP rate limiting beyond the time-trap.

## Security notes

- Destinations are references → a prompt or a tampered request can never
  exfiltrate submissions to an arbitrary inbox or URL.
- Fields are re-derived server-side from saved post content → no field
  injection, no client-supplied required-rule bypass.
- Webhook URLs are admin-defined and HTTPS-only; optional HMAC signing.
- All stored and emailed values are sanitized/escaped on the way in and out.
