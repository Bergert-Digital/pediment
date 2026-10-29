# Product Sense

How to judge work from the user's side. Pediment has four users. Judge a change against
each one it touches.

## Journey A: The developer who makes a client site

1. Run `/pediment:start` in an empty directory. **Friction check:** does every question say
   why it is asked? Does the scaffolder finish without a manual step that the skill does not
   name?
2. `npm run env:start`, `npm run seed:plan`, `npm run seed`. **Friction check:** does the plan
   say in plain words what it will create? Does a second `seed:plan` report `0 to write`?
3. Change the accent colour in the client `theme.json`. **Friction check:** does it win over
   the plugin default without `!important`? Is the preset slug easy to find?
4. Add a named style for a core block (see `docs/design-layer.md`). **Friction check:** does
   it appear in the editor and in `pediment-get-vocabulary` without a plugin change?
5. Tag a release. **Friction check:** does `client-release.yml` produce a zip that installs
   through wp-admin?

If a step needs a workaround or an edit in a plugin file, that is the bug. Fix it upstream.

## Journey B: The AI agent that changes a live page through MCP

1. Call `pediment-get-vocabulary`. **Friction check:** does the answer hold everything the
   agent needs to write valid markup — blocks, styles, tokens, rules, languages, post types?
2. Write a page with `pediment-write-page`. **Friction check:** when a rule fails, does the
   error name every violation and say how to fix it? Is nothing written on failure?
3. Read the page back, then roll it back. **Friction check:** does the read return the same
   markup? Does the rollback restore exactly one revision?
4. Validate first with client-kit's validate-blocks. **Friction check:** does the validator
   agree with what the editor shows as valid?

The agent cannot see the screen. Every error message is its only guide.

## Journey C: The site editor in Gutenberg

1. Build a section from core blocks and named styles. **Friction check:** are the style names
   clear in the Styles panel? Does the front end match the editor?
2. Edit the header and the mega menu in the Site Editor. **Friction check:** is every part
   editable there, without a file edit?
3. Add a `pediment/form` and submit it as a visitor. **Friction check:** does the success
   message show? Is the submission stored *and* mailed?

## Journey D: The operator on admin-only hosting

Production has no shell and no WP-CLI. Only wp-admin.

1. Update the plugin. **Friction check:** does the update arrive through the built-in updater,
   with the right version number?
2. Use Settings → Pediment → Seeding. **Friction check:** does the preview tell the
   truth about what the apply button will do? Does every error say what to do next?

A message that is wrong in wording is a real bug here: the operator has no other view.

## The empty, partial and hostile lens

For every block, tool and admin screen, ask:

- **Empty:** no or default attributes — clean output, or broken markup (`<a href="">`)?
- **Partial:** only some fields set — does it degrade sanely?
- **Hostile:** script tags, unbalanced HTML, huge input — is everything escaped, and does the
  content policy reject what it must?

## When unsure what to do next

Walk a journey as a first-time user. The most common real failure is **drift**: a doc,
skill or error message that describes a contract the code no longer keeps. Hunt drift first.
