---
name: launch
description: Check a Pediment client site before it goes live — legal pages (Impressum, privacy policy), consent and GDPR, forms, SEO, domain, operations and handover. Runs the automatic checks against the site's URL, walks the user through the rest, and records the result in the client repo's docs/launch.md. Use before the first production deploy or a domain switch, and whenever the user says "launch", "go live" or "release the site".
allowed-tools: Bash(node ${CLAUDE_SKILL_DIR}/../../scripts/launch-check.mjs:*)
---

# Launch check for a Pediment client site

The checklist lives in the client repo at `docs/launch.md`. Each item has an ID, and is
`[auto]` (the check script tests it against the site) or `[manual]` (a person decides). This
skill runs the script, asks about the rest, and writes every answer back into that file. The
site is ready when no item is `[ ]`.

Claude Code prepends `Base directory for this skill: <absolute path>` when this skill loads. Call
that absolute directory `<skill-dir>` for this run. Resolve bundled files from it:

- check script: `<skill-dir>/../../scripts/launch-check.mjs`
- checklist template: `<skill-dir>/../../shared/launch-checklist.md`

Work in the client theme repo (the directory with `seed/manifest.php`). If you are somewhere
else, ask for the client repo path and stop until you have it.

## Rules

- This is a reminder, not legal advice. Never write an Impressum or a privacy policy from your
  own knowledge as final text. The user takes it from a generator (for example eRecht24) or a
  lawyer. You may list what the text must cover.
- Never mark an item `[x]` that you did not see pass or that the user did not confirm.
- Change a live page only through the Pediment MCP tools or the block editor, never with
  `wp post create` / `wp post update` (see the client repo's `AGENTS.md`).
- Never tag a release, push or deploy as part of this skill.

## 1. Find the checklist

Read `docs/launch.md`. If it does not exist (the site was scaffolded before this skill
existed), copy `<skill-dir>/../../shared/launch-checklist.md` to `docs/launch.md` and replace
`__PEDIMENT_NAME__` with the theme name from `style.css`. Tell the user you added it.

If the file exists, compare its item IDs with the template. Add each item the template has
and the file lacks to its section, as `[ ]`. Keep every note the file already has.

## 2. Run the automatic checks

Ask one question: *"Which URL goes live — the staging URL, or the production domain?"*

```bash
node "<skill-dir>/../../scripts/launch-check.mjs" --url <url> --json
```

Exit code 0 means no failure, 1 means at least one failure, 2 means the site did not load
(show the message and stop). Each result has an `id`, a `status` (`pass`, `fail`, `review`), a
`message` and `details`.

Expected failures on a site that is not on its production domain yet:

- `OPS-1` fails on a plain-HTTP URL, including `http://localhost:8888`.
- `SEO-1` and `SEO-2` fail on a staging site that is hidden from search engines on purpose.

Say so when it happens, leave those items open, and tell the user to run the check again on
the production domain right after the switch.

Write each result into `docs/launch.md`:

- `pass` → `[x]` and `— auto <YYYY-MM-DD> on <url>`.
- `fail` → stay `[ ]`, and `— FAIL <YYYY-MM-DD>: <message>`. Show the details to the user.
- `review` → stay `[ ]`. Show the details and ask the user to decide for each one. For `PRIV-1`
  that means each external host: consent first, or the reason it needs none, and a line in the
  privacy policy. If you can drive a browser, confirm `PRIV-1` there too: a fresh profile, the
  network panel, load the page, accept nothing.

Replace an earlier note on the same item; do not stack notes.

## 3. Walk through the manual items

First check what you can from the repo, and report what you found as evidence:

- `QA-2`: does the repo have a `patterns/footer.php` with `Slug: <theme-slug>/footer`? If yes,
  the footer part started from it; if no, from Pediment's default footer, which has no contact
  details. Either way, ask the user to check the live footer: the Site Editor's edits win.
- `FORM-1`: which patterns contain a `pediment/form` block, and does each link to the privacy
  policy near the submit button?
- `PRIV-3`: do patterns contain `core/embed` blocks (YouTube, Vimeo, maps)?
- `LEGAL-4`, `SEO-9`: does `seed/manifest.php` declare more than one language?
- `SEO-8`: is `docs/brief.md` marked as a port (`sourceUrl`)? If yes, offer to compare the old
  sitemap with the new URLs and list the redirects needed.

Then go through the open `[manual]` items one section at a time. Show the open items of one
section in a short list and ask which are done, which are `n/a` (with the reason), and which
stay open. Do not ask about items that are already `[x]`. The user may stop at any time; keep
what is answered.

## 4. Record and report

Update the two header lines in `docs/launch.md`:

- `Planned launch date:` — ask for it if it is `_not set_`. The user may skip it.
- `Last check:` — `<YYYY-MM-DD> on <url>: <n> open`.

Then report:

- **Ready to launch** when no item is `[ ]`.
- Otherwise **Not ready**, and the open items by ID and one-line name, failures first.

Offer to commit `docs/launch.md` (`docs: update launch checklist`).

If items stay open and the user's own instructions (their `CLAUDE.md`) name a task board or a
reminder tool, update or create the launch reminder there: the site, the planned launch date,
the open item IDs, and the sentence to start a new session with: "Run /pediment:launch in
<client repo path>." Otherwise tell the user to keep a reminder.
