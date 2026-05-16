# Product Sense

How to evaluate work from a user's perspective. There are two users — judge against both.

## Journey A: The agency developer forking for a new client

Walk this mentally before shipping anything that touches the fork/extension surface:

1. Clone `wp-starter-child-theme`, run the fork rename checklist. **Friction check:** is every
   token to rename actually listed in the README? Did anything new I added introduce a token
   that a forker would miss and ship with "starter-child" still in it?
2. `npm run env:start`, `npm run build`. **Friction check:** does a clean checkout build without
   manual steps? Does wp-env come up with the parent + plugin mounted from siblings?
3. Override a color in the child `theme.json`. **Friction check:** does it actually win over the
   parent without `!important` hacks? Is the token name discoverable?
4. Add a `client/promo-banner`-style block. **Friction check:** does the block contract in
   `docs/client-blocks.md` still match reality? Does the AI plugin pick it up with no extra work?
5. Add a brand field via `starter_brand_fields`. **Friction check:** does it appear in the admin
   UI, participate in `Brand::all()`, and round-trip through its sanitizer?

If any step needs a workaround, that's the bug — not the symptom you noticed downstream.

## Journey B: The site editor building a page

1. Open the block inserter, drop in a Hero / CTA / FAQ / Prose. **Friction check:** is the
   block's purpose obvious from its title and description? Does it look right with *empty*
   attributes (no `<a href="">`, no orphan markup, no PHP notices)?
2. Configure **Settings → Brand Settings** once. **Friction check:** are required-feeling fields
   (contact email, social links) discoverable? Does Social Links hide cleanly when unset?
3. Submit the contact form as a visitor. **Friction check:** does the editor preview match the
   front-end render? Does the success message show? Is the submission stored *and* mailed?
4. Draft a page with the AI plugin. **Friction check:** does generated content use real tokens
   and render airily (section rhythm), with no stray dividers?

## The empty / loading / error lens

For every block and admin surface, ask the three questions:

- **Empty:** rendered with no/default attributes — graceful, or broken markup?
- **Partial:** only some fields set (e.g. CTA with text but no URL) — does it degrade sanely?
- **Hostile:** script tags, unbalanced HTML, huge input — is everything sanitized
  (`wp_kses_post` / `esc_url` / `esc_html` / `esc_attr`)?

A block that only looks good fully populated in the editor is not done.

## When unsure what to do next

Open the theme as a first-time forker *and* as a first-time editor. The most common real
failure here is **drift**: docs/blocks.md or client-blocks.md describing a contract the code no
longer honors, or a parent change that quietly breaks the child-override path. Hunt drift first.
