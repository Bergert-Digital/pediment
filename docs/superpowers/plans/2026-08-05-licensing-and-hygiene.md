# Licensing and Repository Hygiene Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Declare the three-zone licence split across the published components, and remove two stale documentation trees without losing the live documentation buried in one of them.

**Architecture:** Four independent tasks. Tasks 1–2 add licence files and make the split discoverable. Task 3 rescues the two `plugin/docs/` files that document current behaviour, correcting them against the source. Task 4 deletes what remains. Task 3 must run before Task 4, because Task 4 deletes the directory Task 3 reads from. Tasks 1 and 2 are order-independent of the rest.

**Tech Stack:** Markdown, JSON manifests, `curl` for canonical licence texts, Node 20 test runner (`node --test`), PHPUnit via wp-env, `npm` lint scripts.

**Spec:** `docs/superpowers/specs/2026-08-05-licensing-and-hygiene-design.md`

## Global Constraints

- Work in the existing Conductor checkout on the current branch. Do not create a branch or worktree.
- Never push. Commit only; the user pushes.
- Conventional commit summaries of at most 60 characters, with the trailer
  `Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>`.
- Stage each task's files explicitly by name. Never `git add -A`.
- Do not change WordPress runtime behaviour. No PHP under `plugin/src/` or `plugin/inc/` is edited by this plan.
- Do not add npm or composer dependencies.
- Licence texts must be **downloaded verbatim from the canonical source**, never typed, summarised, or reproduced from memory. A paraphrased licence file is legally meaningless.
- **§3.3 of the spec (making `pediment-ai` private, archiving `Pediment-Child-Theme`) is already done.** Do not repeat it.
- **§3.5 of the spec (the commercial-protection backlog entry) is already done.** It is in `docs/BACKLOG.md` under 🟡 High. Do not add it again.

---

## File Structure

**Created:**

| Path | Responsibility |
|---|---|
| `LICENSE` | GPL-2.0-or-later full text; governs `plugin/` and `client-template/` |
| `client-kit/LICENSE` | PolyForm Shield 1.0.0 full text; governs the Claude Code kit |
| `client-template/LICENSE` | GPL-2.0-or-later full text; travels into scaffolded client repos |
| `LICENSING.md` | One page mapping directory → licence → reasoning |
| `docs/privacy.md` | Accurate GDPR disclosure of what the chat transmits to Anthropic |
| `docs/extending.md` | The plugin's filter reference, corrected against current namespaces |

**Modified:**

| Path | Change |
|---|---|
| `package.json` | Add `"license": "GPL-2.0-or-later"` |
| `client-kit/.claude-plugin/plugin.json` | Add `"license": "LicenseRef-PolyForm-Shield-1.0.0"` |
| `README.md` | Add a Licensing section; correct the stale one-asset release claim |

**Deleted:**

| Path | Reason |
|---|---|
| `docs/PRODUCT_SENSE.md` | Documents the retired parent/child model |
| `plugin/docs/` (whole tree) | Orphaned duplicate from the `pediment-ai` merge |

---

## Task 1: Licence files and manifest declarations

**Files:**
- Create: `LICENSE`, `client-kit/LICENSE`, `client-template/LICENSE`
- Modify: `package.json`, `client-kit/.claude-plugin/plugin.json`

**Interfaces:**
- Consumes: nothing.
- Produces: the three licence files that `LICENSING.md` (Task 2) links to by path.

**Context you need:** `client-kit/` has no `package.json` of its own — its 78 tests run from the root manifest via `npm run test:kit`. So the kit's licence is declared in `client-kit/LICENSE` and its Claude Code manifest only.

- [ ] **Step 1: Record the pre-change baseline**

The point is to prove any later failure is attributable to this task.

```bash
npm run test:kit 2>&1 | tail -5
```

Expected: `pass 78`, `fail 0`.

- [ ] **Step 2: Download the GPLv2 text to the repository root**

```bash
curl -sSL -o LICENSE https://www.gnu.org/licenses/old-licenses/gpl-2.0.txt
```

- [ ] **Step 3: Verify the download is the real licence, not an error page**

```bash
wc -l < LICENSE
head -2 LICENSE
grep -c "GNU GENERAL PUBLIC LICENSE" LICENSE
```

Expected: `338` lines; the first two lines are `GNU GENERAL PUBLIC LICENSE` and `Version 2, June 1991`; the grep count is at least `1`. If the line count is near zero you fetched a 404 body — stop and re-fetch.

- [ ] **Step 4: Copy the same text to the client template**

`client-template/` gets its own copy rather than a symlink, because `client-release.yml` rsyncs the directory into a client theme zip and a symlink would not survive.

```bash
cp LICENSE client-template/LICENSE
diff -q LICENSE client-template/LICENSE && echo "identical"
```

Expected: `identical`.

- [ ] **Step 5: Download the PolyForm Shield text for the kit**

The version tag in the URL is deliberate — `main` does not exist on that repository, and an untagged fetch would 404.

```bash
curl -sSL -o client-kit/LICENSE \
  https://raw.githubusercontent.com/polyformproject/polyform-licenses/1.0.0/PolyForm-Shield-1.0.0.md
```

- [ ] **Step 6: Verify the PolyForm download**

```bash
wc -c < client-kit/LICENSE
head -1 client-kit/LICENSE
grep -c "^## Noncompete" client-kit/LICENSE
```

Expected: `5748` bytes; first line `# PolyForm Shield License 1.0.0`; grep count `1`. A byte count of `14` means a 404 body — stop and re-fetch.

- [ ] **Step 7: Append the required notice to the kit licence**

PolyForm Shield's **Notices** section requires that recipients get any `Required Notice:` line the licensor supplies. Without it the licensor is unnamed and the noncompete term has no identified beneficiary.

Append exactly these three lines to the end of `client-kit/LICENSE`:

```text

Required Notice: Copyright Bergert Digital (https://github.com/Bergert-Digital/pediment)
Licensor Line of Business: Pediment WordPress site engine and client kit (https://github.com/Bergert-Digital/pediment)
```

- [ ] **Step 8: Verify the notice landed**

```bash
tail -3 client-kit/LICENSE
```

Expected: a blank line, then the two notice lines exactly as written above.

- [ ] **Step 9: Add the licence field to the root manifest**

```bash
npm pkg set license="GPL-2.0-or-later"
```

- [ ] **Step 10: Add the licence field to the kit's Claude Code manifest**

Edit `client-kit/.claude-plugin/plugin.json`, adding the `license` key after `version`. The `LicenseRef-` prefix is SPDX's form for licences not on the SPDX list — PolyForm Shield is not listed (SPDX v3.28.0 carries only `PolyForm-Noncommercial-1.0.0` and `PolyForm-Small-Business-1.0.0`), so a bare `PolyForm-Shield-1.0.0` would be an invalid identifier.

```json
{
  "name": "pediment",
  "description": "Create and build Pediment WordPress client sites: scaffold a standalone client theme, seed it, and port existing pages into it.",
  "version": "3.0.0",
  "license": "LicenseRef-PolyForm-Shield-1.0.0",
  "author": { "name": "Bergert Digital", "email": "jonas@bergert.digital" },
  "homepage": "https://github.com/Bergert-Digital/pediment",
  "repository": "https://github.com/Bergert-Digital/pediment",
  "keywords": ["wordpress", "pediment", "scaffolding", "block-theme"]
}
```

Leave `version` at whatever value is currently in the file — release-please owns it, and this plan must not move it.

- [ ] **Step 11: Verify both manifests still parse**

```bash
node -e "console.log('root:', require('./package.json').license)"
node -e "console.log('kit:', require('./client-kit/.claude-plugin/plugin.json').license)"
```

Expected: `root: GPL-2.0-or-later` and `kit: LicenseRef-PolyForm-Shield-1.0.0`.

- [ ] **Step 12: Run the test suite and lint gates**

```bash
npm run test:kit 2>&1 | tail -5
npm run lint:blocks && npm run lint:colors && npm run lint:icons
```

Expected: `pass 78`, `fail 0`, and all three lint scripts exit 0.

- [ ] **Step 13: Commit**

```bash
git add LICENSE client-kit/LICENSE client-template/LICENSE package.json client-kit/.claude-plugin/plugin.json
git commit -m "$(cat <<'EOF'
chore(license): declare the GPL / PolyForm Shield split

plugin/ and client-template/ are GPL-2.0-or-later; client-kit/ is PolyForm
Shield 1.0.0. The kit previously declared no licence at all, which on a
public repo defaults to all-rights-reserved and grants no permission to use
the thing people are told to install.

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

## Task 2: `LICENSING.md` and the README licensing section

**Files:**
- Create: `LICENSING.md`
- Modify: `README.md`

**Interfaces:**
- Consumes: the three licence files created in Task 1.
- Produces: nothing later tasks depend on.

**Context you need:** `README.md` has seven `##` headings, ending with `## Building a client site`. The new Licensing section goes at the end. While in the file, one existing sentence is factually wrong and gets corrected — see Step 3.

- [ ] **Step 1: Create `LICENSING.md`**

```markdown
# Licensing

Pediment ships three components with different legal footing, so one repository-wide
licence would be wrong for at least two of them.

| Directory | Licence | File |
| --- | --- | --- |
| `plugin/` | GPL-2.0-or-later | [`LICENSE`](LICENSE) |
| `client-template/` | GPL-2.0-or-later | [`client-template/LICENSE`](client-template/LICENSE) |
| `client-kit/` | PolyForm Shield 1.0.0 | [`client-kit/LICENSE`](client-kit/LICENSE) |

## Why the split

**`plugin/` is GPL because it is WordPress code.** It loads inside WordPress and calls its
APIs, and the WordPress project's position is that plugin PHP inherits the GPL. This was
already declared in `plugin/plugin.php` and `plugin/composer.json`; the root `LICENSE`
makes it explicit rather than implied. Anyone who receives `pediment-plugin.zip` may
inspect, modify and redistribute it under the GPL's terms.

**`client-template/` is GPL for the same reason, plus a practical one.** It is a block
theme with PHP, and the scaffolder copies it into the customer's own repository where it
becomes *their* theme. A restrictive licence there would make ownership of a client's own
site ambiguous.

**`client-kit/` is not WordPress code.** It is a Claude Code plugin — skills and a Node
scaffolder that run on a developer's machine. It never loads in WordPress and never links
against it, so it carries no GPL obligation and is licensed commercially.

PolyForm Shield permits customers to use the kit for their own client work, including
commercial work, and forbids using it to build a product that competes with Pediment. The
full terms are in `client-kit/LICENSE`; the canonical text is at
<https://polyformproject.org/licenses/shield/1.0.0>.

## What this does and does not protect

Worth stating plainly, because licences are often expected to do more than they can.

The plugin executes as plain PHP on the customer's server. Whatever runs there, the
customer has, in readable form — no licence changes that. What a licence does is define
what they may lawfully do with it afterwards, and under the GPL that includes
redistribution. This is the same position every commercial WordPress plugin occupies.

`client-kit/`'s licence is the one with real commercial teeth, because the kit is not
GPL-encumbered. It still lands on the customer's disk; Shield governs use, not access.

## Questions this does not answer

This split is an engineering decision, not legal advice. A German/EU software-licensing
lawyer should confirm it — and the Pediment trademark — before the product is sold.
```

- [ ] **Step 2: Append the Licensing section to `README.md`**

Add at the end of the file, after `## Building a client site`:

```markdown

## Licensing

Three components, three licences: `plugin/` and `client-template/` are GPL-2.0-or-later,
`client-kit/` is PolyForm Shield 1.0.0. See [LICENSING.md](LICENSING.md) for what each
covers and why they differ.
```

- [ ] **Step 3: Correct the stale release claim in `README.md`**

The `## Releases` section currently says a release creates *exactly one* asset. The pipeline in `.github/workflows/build-release-zip.yml` uploads two: `pediment-plugin.zip` and `pediment-client-template.zip`. This is a factual error in a file this task already edits, so it is corrected here rather than left standing.

Replace this paragraph:

```markdown
Main-only development uses release-please. A release creates exactly one asset:
`pediment-plugin.zip`, which installs as `wp-content/plugins/pediment/`. It
does not include a client theme; install or keep a site-specific standalone
theme alongside it.
```

with:

```markdown
Main-only development uses release-please. A release publishes two assets:
`pediment-plugin.zip`, which installs as `wp-content/plugins/pediment/`, and
`pediment-client-template.zip`, which the client kit's scaffolder downloads to
create a new client theme. The plugin zip contains no client theme; every site
pairs it with a standalone theme of its own.
```

- [ ] **Step 4: Verify the claim you just wrote is true**

Do not take the plan's word for it — confirm against the workflow.

```bash
grep -n "gh release upload" .github/workflows/build-release-zip.yml
```

Expected: two lines, one uploading `pediment-plugin.zip` and one uploading `pediment-client-template.zip`. If only one appears, the README's original text was right — revert Step 3 and report the discrepancy.

- [ ] **Step 5: Verify every link in `LICENSING.md` resolves**

```bash
for f in LICENSE client-template/LICENSE client-kit/LICENSE; do test -f "$f" && echo "ok $f" || echo "MISSING $f"; done
```

Expected: three `ok` lines. Task 1 must have run first.

- [ ] **Step 6: Commit**

```bash
git add LICENSING.md README.md
git commit -m "$(cat <<'EOF'
docs(license): explain the split; fix the release-asset count

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

## Task 3: Rescue the two live documents from `plugin/docs/`

**Files:**
- Create: `docs/privacy.md`, `docs/extending.md`
- Read (not yet deleted): `plugin/docs/privacy.md`, `plugin/docs/extending.md`

**Interfaces:**
- Consumes: nothing.
- Produces: the two files that must exist before Task 4 deletes `plugin/docs/`.

**Context you need — why these two and not the others.** `plugin/docs/` holds nine entries. Five (`BACKLOG.md`, `PRODUCT_SENSE.md`, `SESSION_LOG.md`, `STANDARDS.md`, `VISION.md`) duplicate root `docs/` files with older content and are pure loss. `prompts.md` documents `Jobs/ComposeJob::systemBlock()`, which does not exist — the prompt is built in `plugin/src/Chat/PromptBuilder.php::systemPrompt()` — and what remains of its content is covered accurately by `extending.md`'s System prompt section, so it is deleted with the rest.

The two rescued here are different. `privacy.md` is the **only** privacy documentation in the repository and is stale in a way that matters. `extending.md` documents ten filters that all still exist, and is documented nowhere else — a grep of `docs/*.md` for `pediment_ai_system_prompt` returns nothing.

- [ ] **Step 1: Confirm what the chat actually transmits, before writing that it does**

```bash
grep -n "normalizeImages\|block_tree\|historyToMessages\|contextMessage" plugin/src/Rest/ChatController.php plugin/src/Chat/TurnRunner.php | head
grep -rn "brand\|voice_tone\|tagline" plugin/src/Chat/PromptBuilder.php
```

Expected: the first command shows images, block tree, conversation history and context message all being assembled into the request. The second returns **no matches** — confirming the old disclosure's claim that "Brand Settings values: brand name, tagline, voice/tone" are sent is false. Brand Settings was removed from the product.

- [ ] **Step 2: Create `docs/privacy.md`**

```markdown
# Privacy / GDPR disclosure

The Pediment plugin's AI chat sends data to Anthropic when an editor uses it. Nothing is
sent unless someone opens the chat sidebar and submits a turn.

## What is transmitted

- The editor's chat message text.
- Prior messages in the same conversation — up to the last 20 turns.
- The block tree of the post being edited, including all of its editorial copy, and which
  block is currently selected.
- Any images the editor attaches to a message (PNG, JPEG, GIF or WebP).
- When the request names a URL, the page content Pediment fetched server-side from it.
- URLs the model chooses to retrieve through Anthropic's hosted `web_fetch` and
  `web_search` tools. Anthropic performs those retrievals from its own infrastructure.

## What is not transmitted

No form submissions, no site visitor data, no WordPress user accounts or credentials, no
commerce data, and no content from posts other than the one being edited.

## Recommended privacy-policy paragraph (German)

> Unsere Website nutzt zur Inhaltserstellung im Backend einen KI-Dienst der Anthropic, PBC
> (548 Market St, San Francisco, CA 94104, USA). Bei Nutzung der Funktion werden die
> Eingabe der Redakteurin oder des Redakteurs, der bisherige Gesprächsverlauf, der Inhalt
> des bearbeiteten Beitrags sowie gegebenenfalls hochgeladene Bilder an Anthropic
> übertragen. Die Verarbeitung erfolgt auf Grundlage unseres berechtigten Interesses gemäß
> Art. 6 Abs. 1 lit. f DSGVO. Die Datenübermittlung in die USA erfolgt auf Basis der
> EU-Standardvertragsklauseln.

## Recommended privacy-policy paragraph (English)

> This website uses an AI service from Anthropic, PBC (548 Market St, San Francisco, CA
> 94104, USA) for internal content drafting. When the feature is used, the editor's input,
> the conversation history so far, the content of the post being edited and any uploaded
> images are transmitted to Anthropic. Processing is based on our legitimate interest under
> Art. 6(1)(f) GDPR. Transfers to the US rely on the EU Standard Contractual Clauses.

## Turning it off

Either enable **Mock mode** under Settings → Pediment, or define
`PEDIMENT_AI_MOCK` as `true` in `wp-config.php`. With mock mode active the chat UI stays
visible but the plugin never contacts Anthropic. Clearing the API key has the same
practical effect: without a key no request can be made.
```

- [ ] **Step 3: Verify the disclosure's factual claims**

```bash
grep -n "image/png\|image/jpeg\|image/gif\|image/webp" plugin/src/Rest/ChatController.php
grep -n "historyToMessages" plugin/src/Chat/TurnRunner.php
grep -n "add_options_page" -A 3 plugin/src/Settings/Page.php
grep -rn "PEDIMENT_AI_MOCK" plugin/src/Bootstrap.php
```

Expected: the four MIME types appear in `normalizeImages`; `historyToMessages( $history, 20 )` confirms the 20-turn figure; the options page registers under the title `Pediment` (so "Settings → Pediment" is correct, **not** "Settings → Pediment AI" as the old file said); and `PEDIMENT_AI_MOCK` is read in `Bootstrap.php`. Fix the document if any claim does not hold.

- [ ] **Step 4: Create `docs/extending.md` from the old file with three corrections**

```bash
cp plugin/docs/extending.md docs/extending.md
sed -i '' 's/\\PedimentAi\\/\\Pediment\\/g' docs/extending.md
```

Then apply these three edits by hand. Everything else in the file is accurate and stays.

1. Change the title from `# Extending pediment-ai from a child theme` to `# Extending Pediment from a client theme`, and the opening sentence from "so a child theme can register blocks" to "so a client theme can register blocks". There is no parent or child theme any more.
2. The `sed` above already replaced every `\PedimentAi\` namespace with `\Pediment\` — three occurrences: `\PedimentAi\Anthropic\SchemaBuilder::invalidate()`, `\PedimentAi\Anthropic\Client` and `\PedimentAi\Mock\MockProvider`. All three classes exist under `Pediment\`; following the file as written today would fatal. Step 5 verifies the replacement.
3. In the System prompt section, delete the phrase "inject brand voice," from the sentence beginning "Wrap the prompt to". Brand Settings no longer exists, so brand voice is not a thing the filter injects from. Keep the code example — appending a brand-voice string is still a valid use of the filter, it is just not reading from a settings screen.

- [ ] **Step 5: Verify the namespace corrections took, and that no filter is fictional**

```bash
grep -c "PedimentAi" docs/extending.md
for f in pediment_ai_block_namespaces pediment_ai_system_prompt pediment_ai_provider \
         pediment_ai_model_compose pediment_ai_model_edit pediment_ai_model_refine \
         pediment_ai_max_tokens pediment_ai_max_iterations pediment_ai_dispatch_mode \
         pediment_ai_loopback_url; do
  printf "%-32s %s\n" "$f" "$(grep -rIl "$f" plugin/src plugin/inc | head -1)"
done
```

Expected: the count is `0`, and every one of the ten filters resolves to a real source file. All ten were verified present when this plan was written; if any now resolves to nothing, remove that section from the document rather than shipping a filter that does not exist.

- [ ] **Step 6: Commit**

```bash
git add docs/privacy.md docs/extending.md
git commit -m "$(cat <<'EOF'
docs: rescue the privacy and extending docs from plugin/docs

privacy.md claimed Brand Settings values are sent to Anthropic; that setting
was removed from the product. Rewritten against what ChatController and
TurnRunner actually transmit, which also adds uploaded images and
conversation history. extending.md documented ten live filters under a dead
\PedimentAi\ namespace and existed nowhere in root docs.

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

## Task 4: Delete the stale documentation trees

**Files:**
- Delete: `docs/PRODUCT_SENSE.md`, `plugin/docs/` (entire directory)

**Interfaces:**
- Consumes: `docs/privacy.md` and `docs/extending.md` from Task 3. **Do not start this task until Task 3 is committed** — otherwise the rescued content is destroyed.

**Context you need:** `docs/PRODUCT_SENSE.md` instructs the reader to clone `pediment-child-theme`, run a fork rename checklist, verify a child `theme.json` override wins over a parent palette, and check that wp-env comes up "with the parent + plugin mounted from siblings". None of those exist. Its quality bars are already in `docs/STANDARDS.md` and its journey is in `docs/client-sites.md`, so it is deleted rather than rewritten.

`plugin/docs/` never shipped to customers — `docs` is listed in `plugin/.distignore`, so the release zip excludes the whole directory.

- [ ] **Step 1: Confirm Task 3's rescues are committed before destroying the source**

```bash
git log --oneline -1 -- docs/privacy.md docs/extending.md
test -s docs/privacy.md && test -s docs/extending.md && echo "both present and non-empty"
```

Expected: a commit hash, and `both present and non-empty`. If either check fails, stop and finish Task 3.

- [ ] **Step 2: Re-confirm `plugin/docs/` is excluded from the release zip**

```bash
grep -x "docs" plugin/.distignore && echo "excluded — never shipped"
```

Expected: `docs`, then `excluded — never shipped`. This is what makes the stale privacy disclosure an internal problem rather than a customer-facing one; if the line is absent, say so in your report.

- [ ] **Step 3: Delete both trees**

```bash
git rm -q docs/PRODUCT_SENSE.md
git rm -rq plugin/docs
```

- [ ] **Step 4: Verify no reference dangles**

```bash
grep -rIn "PRODUCT_SENSE" . --exclude-dir=node_modules --exclude-dir=.git || echo "no PRODUCT_SENSE references"
grep -rIn "plugin/docs" . --exclude-dir=node_modules --exclude-dir=.git || echo "no plugin/docs references"
```

Expected: the only surviving hits are inside `docs/superpowers/` (this plan and its spec) and any `docs/SESSION_LOG.md` history entry. Both are records of what happened and are correct to keep. **Any hit in `AGENTS.md`, `README.md`, `docs/STANDARDS.md`, `.claude/commands/` or `client-kit/` is a real dangling pointer** — fix it before committing.

- [ ] **Step 5: Run the full check suite**

Docs-only deletions should not move any of these. Running them proves no PHP file was loading something from the deleted tree.

```bash
npm run test:kit 2>&1 | tail -5
npm run lint:blocks && npm run lint:colors && npm run lint:icons
```

Expected: `pass 78`, `fail 0`, all lint scripts exit 0.

- [ ] **Step 6: Run PHPUnit as a regression check**

```bash
npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment ./vendor/bin/phpunit 2>&1 | tail -15
```

Expected: no failures. If wp-env is not running, start it with `npm run env:start` first. If the environment cannot be brought up, say so explicitly in your report rather than claiming the check passed.

- [ ] **Step 7: Commit**

`git rm` in Step 3 already staged both deletions, so this commits without a further `git add`.

```bash
git commit -m "$(cat <<'EOF'
docs: delete PRODUCT_SENSE and the orphaned plugin/docs tree

PRODUCT_SENSE documented the parent/child model retired in v3.0.0.
plugin/docs was a duplicate tree left by the pediment-ai merge; its two
live documents were rescued to docs/ in the previous commit.

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

## Final verification

After all four tasks, confirm the spec's §4 verification table end to end:

```bash
test -f LICENSE && test -f client-kit/LICENSE && test -f client-template/LICENSE && echo "licences present"
node -e "require('./package.json');require('./client-kit/.claude-plugin/plugin.json');console.log('manifests parse')"
test ! -e plugin/docs && test ! -e docs/PRODUCT_SENSE.md && echo "stale trees gone"
test -f docs/privacy.md && test -f docs/extending.md && echo "rescues in place"
npm run test:kit 2>&1 | tail -3
git status --short
```

Expected: every echo fires, `pass 78`, and a clean working tree.

`git log --oneline -4` should show four commits, one per task. Do not push.

## Out of scope

- The repository-privacy migration. Rejected in the spec's §1.
- The licence server and hosted AI service. Backlogged; its own spec.
- Client names in docs. Resolved 2026-08-05: the user founded Workation and confirmed the 18 mentions are fine. No scrub, no backlog item.
- Legal review of the GPL/PolyForm boundary and the Pediment trademark.
