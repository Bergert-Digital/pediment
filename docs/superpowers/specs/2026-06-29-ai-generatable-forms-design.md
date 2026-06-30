# AI-generatable forms — design

**Date:** 2026-06-29
**Status:** Approved (brainstorm), pending implementation plan
**Repo:** mostly the `pediment` theme (this workspace), plus one small focused
addition to `pediment-ai` (an admin-only "draft destination" assist endpoint).

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
3. **Admin-defined named destinations only.** The page-authoring AI / form
   references a destination *by id*; it can never write a raw URL, header, or
   credential. This removes the open-mail-relay / SSRF surface.
4. **One generic field block** keyed by a `fieldType` attribute (not one block
   per field type).
5. **Always store + deliver**, with a configurable retention/purge.
6. **Delivery is HTTP-only.** A destination is a configurable outbound HTTP
   request (Brevo / Resend / Mailgun / n8n / Slack / anything). There is no
   `wp_mail` channel — providers handle email reliably; WordPress does not.
   Submissions are always persisted to the CPT, so nothing is lost if a request
   fails.
7. **Destinations are admin-only** (`manage_options`); editors and the
   page-authoring AI only pick from them.
8. **AI-assisted destination authoring**, admin-only, with **presets + free-form
   AI**. The AI drafts the request *shape*; credentials never reach the model
   (referenced by `{{ secret:* }}` tokens); nothing goes live until the admin
   reviews, tests, and saves.

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
  record (pending / sent / failed + HTTP status + response snippet).
- **Store first, then deliver** — durability against a failed HTTP request; the
  admin can retry a failed delivery from the submission view.
- Retention: a global setting (days; `0` = keep forever) drives a cron purge
  that generalizes the existing `pediment_contact_cleanup` hook.

### 4. Destinations = templated HTTP requests

A destination is a configurable outbound HTTP request, defined by an admin and
referenced by id. This single shape covers email providers (Brevo, Resend,
Mailgun), automation (n8n), and chat (Slack) alike.

**Registry (`inc/forms-destinations.php`).**
- Final list = admin-defined destinations (stored option, edited in a new
  **Settings → Forms** tab) merged with code-registered ones via the
  `pediment_form_destinations` filter.
- A destination =
  ```
  {
    id, label,
    method:        "POST",                 // GET/POST/PUT/PATCH
    url:           "https://api.brevo.com/v3/smtp/email",
    headers:       { "api-key": "{{ secret:brevo_api_key }}", ... },
    content_type:  "application/json",      // or x-www-form-urlencoded
    body_template: "<string with tokens>",  // see Token templating
    secret_refs:   [ "brevo_api_key" ]      // names only; values in secret store
  }
  ```
- Credential **values** are never stored on the destination — only `{{ secret:*
  }}` references. The actual values live in a separate **encrypted secret store**
  (option, encrypted at rest like `pediment-ai` does for its API key) keyed by
  name, edited in dedicated password fields.

**Token templating (`inc/forms-template.php`).**
Tokens usable in `url`, `headers`, and `body_template`:
- `{{ field:NAME }}` — a submitted field value.
- `{{ all_fields }}` — object/map of every submitted field (JSON contexts only).
- `{{ meta:post_id | page_url | submitted_at | destination }}` — submission meta.
- `{{ secret:NAME }}` — resolved from the encrypted secret store at send time.
- **Structural, escaped interpolation.** For a JSON `content_type`, the template
  is parsed as JSON and tokens are replaced with correctly typed/escaped values
  (`all_fields` → object, scalars → JSON-escaped) so a field value containing
  `"`/`{`/newlines cannot break out of the body or forge structure. Header values
  are CRLF-stripped. Unknown tokens / tokens referencing a non-existent field are
  rejected at **save** time (and resolve to empty at send time as a backstop).

**Delivery (`inc/forms-delivery.php`).**
- Resolve the destination → render `url`/`headers`/`body` from the submission →
  `wp_remote_request`.
- Considered delivered on a 2xx; non-2xx / transport error is recorded as failed
  with the status + response snippet on the submission (admin can retry).
- **HTTPS-only**; an **SSRF guard** rejects loopback / RFC1918 / link-local /
  cloud-metadata (`169.254.169.254`) targets unless explicitly allowlisted via
  `pediment_form_allowed_hosts`. Enforced both at save and at send.

**Presets (`inc/forms-presets.php`).**
Ship curated, known-good request templates for **Brevo, Resend, Mailgun, n8n
webhook, Slack, and Custom** (method + url + headers + content-type + a starter
body_template with secret-token placeholders). The admin picks a preset as a
starting point; the AI fills/customizes the body and wires the form fields.

### 5. Child-theme extensibility

- `pediment_form_destinations` (filter) — register destinations in code.
- `pediment_form_presets` (filter) — register provider presets.
- `pediment_form_field_types` (filter) — add a `fieldType` with its
  validator + renderer callbacks (e.g. a file or date-range field).
- `pediment_form_template_tokens` (filter) — add custom `{{ … }}` tokens.
- `pediment_form_allowed_hosts` (filter) — allowlist hosts past the SSRF guard.
- `pediment_form_submission_received` (action, `$submission`, `$destination`) —
  side-effects (CRM sync, Slack, analytics).
- `pediment_form_request_args` (filter) — reshape the final `wp_remote_request`
  args before send.

### 6. Contact-form migration

- Reimplement contact as a **preset pattern**: a `pediment/form` with
  name / email / message (/ phone) fields and the old success copy. Lives under
  `patterns/`. Its `destination` is left empty until the admin configures one —
  submissions are still captured in the CPT meanwhile.
- Because delivery is now HTTP-only, the old contact form's `wp_mail`
  notification is **not** carried over; the admin wires an email-provider
  destination (e.g. the Brevo preset) to restore email notifications. The
  upgrade notes must call this out.
- `pediment/contact-form` is **deprecated**:
  - Removed from the inserter (`inserter: false` / unregistered from picker).
  - Kept rendering via a server-side shim so existing published pages keep
    working (still posting to the back-compat `/contact` route + `wp_mail`).
  - A block transform converts old instances into the generic `pediment/form`.
  - `contact_submission` data and the `/contact` route remain for back-compat.
  - Full removal deferred to a later major version.

### 7. AI-assisted destination authoring (admin-only)

A second, admin-only AI surface, distinct from page authoring. Lives on the
**Settings → Forms** "new/edit destination" screen.

Flow:
1. Admin picks a **preset** or "Describe it to the AI."
2. Admin describes the provider in plain language; the AI returns a **draft**
   `{ method, url, headers, content_type, body_template }` with the form fields
   wired via tokens and credentials referenced as `{{ secret:* }}` placeholders.
3. Admin pastes credential values into the encrypted secret fields, clicks
   **Send test** (dry-run with sample data; shows the provider's real response),
   then **Save**. Nothing is active until saved.

Safety:
- `manage_options` only.
- **Human-in-the-loop**: AI emits a *draft into a form*, never writes live config.
- **Secrets never reach the model** — it drafts header *shapes* with
  `{{ secret:* }}` tokens; real values live only in the encrypted store.
- Save-time validation (valid JSON, known tokens, HTTPS, SSRF guard) applies to
  AI drafts exactly as to hand-written ones.

### 8. pediment-ai integration

Two parts:

- **Page-authoring (no plugin change).** A theme-side
  `add_filter('pediment_ai_system_prompt', …)` (filter already exists in
  `pediment-ai`'s `PromptBuilder` and receives the block schema) appends the
  **live list of valid destination ids + labels** and a short note: "to collect
  input, use `pediment/form` with `pediment/form-field` children; set
  `destination` only to one of the listed ids; never invent one."
- **Destination authoring (small plugin addition).** A new admin-only
  structured-output endpoint in `pediment-ai` (`POST
  pediment-ai/v1/draft-destination`) that takes the admin's description + the
  target form's field names and returns the draft request JSON. Reuses the
  existing Anthropic client, encryption, and rate-limit infra. The theme's
  Settings page calls it.

## Out of scope (YAGNI for v1)

Multi-step / wizard forms, conditional field logic, file uploads (left as a
`pediment_form_field_types` extension point), CAPTCHA/reCAPTCHA, payment fields,
per-form storage toggles, per-IP rate limiting beyond the time-trap.

## Security notes

- **Destinations are references.** The page-authoring AI and any tampered request
  reference a destination only by id → submissions can never be redirected to an
  arbitrary URL.
- **Secrets are isolated.** Credentials live in an encrypted store, referenced by
  `{{ secret:* }}`. They never appear in block content, post meta, the page-
  authoring prompt, or the destination-authoring model input.
- **Server-authoritative fields.** Fields are re-derived from saved post content
  → no field injection, no client-supplied required-rule bypass.
- **Safe interpolation.** Field values are structurally JSON-escaped into the body
  and CRLF-stripped in headers → no body break-out or header injection.
- **SSRF + HTTPS guard** on every destination URL (loopback / RFC1918 /
  link-local / metadata blocked unless allowlisted), enforced at save and send.
- **Human-in-the-loop authoring.** AI-drafted destinations are inert until an
  admin reviews, tests, and saves; the same validation gates AI and hand-written
  configs.
- All stored values are sanitized/escaped on the way in and on display.
