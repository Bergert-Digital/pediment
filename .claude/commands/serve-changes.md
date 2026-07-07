---
description: Commit the current workspace's changes, fast-forward the local `development` branch onto them, and rebuild the parent-theme checkout so live-mount wp-envs serve them — no push.
argument-hint: "[commit subject]  — omit to derive a Conventional Commit message from the diff"
allowed-tools: Bash(git:*), Bash(npm:*), Bash(cd:*), Bash(test:*), Bash(awk:*)
---

# Serve changes: commit → integrate into `development` → rebuild

**Goal:** take the work in the current Conductor workspace, commit it, fast-forward the
local `development` branch onto it, and rebuild the parent-theme checkout that live-mount
wp-envs serve — so the change is live locally without a `git push`.

**Context (why this is more than three commands):**

- All pediment worktrees share one git object store. `development` is checked out in its
  own worktree (usually `/Users/jonas/Entwicklung/pediment`); child-theme envs mount that
  path via `"themes": [ …, "../pediment" ]`.
- `build/blocks/**` is **gitignored and per-worktree**; `build/blocks-manifest.php` is
  **tracked** and must match source. So we build in the workspace (to refresh the tracked
  manifest **before** committing) and again in the `development` checkout (to refresh its
  ignored `build/blocks/**` for serving).

Follow these steps exactly. On **any** failure, stop and report — never claim success
without the evidence.

1. **Preflight.**
   - `BR=$(git rev-parse --abbrev-ref HEAD)`. If `BR` is `development`, `main`, or `master`,
     stop: this command integrates a *workspace* branch into `development`, so you must be
     on one.
   - Confirm this is a pediment theme repo: `test -f style.css && test -d src/blocks`. If
     not, stop.

2. **Build the workspace** so the tracked manifest is current.
   - `npm run build`. If it fails, show the error and stop.

3. **Commit the workspace changes** (skip cleanly if the tree is already clean).
   - Review `git status --short` and `git diff`.
   - Stage **only** source files that belong to THIS work, plus `build/blocks-manifest.php`
     **if it changed**. Never `git add -A` / `git add .`. Never stage `build/blocks/**`
     (gitignored) or unrelated churn (e.g. a `package-lock.json` diff you didn't intend —
     revert it with `git checkout` instead).
   - Message: if `$1` is given, use it as the subject; otherwise derive a Conventional
     Commit (`fix(scope): …`, `feat(scope): …`, etc.) from the diff, following the repo's
     `/commit` conventions (imperative, ≤60-char subject, a body that explains *why* when
     it isn't obvious). End with:
     `Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>`
   - Use a HEREDOC for the message. Do **not** amend existing commits.
   - If there was nothing to commit, say so and continue — the branch may already hold the
     work.

4. **Locate the `development` worktree.**
   - `DEV=$(git worktree list --porcelain | awk '/^worktree /{w=$2} /^branch refs\/heads\/development$/{print w}')`
   - If `DEV` is empty, stop: `development` isn't checked out in any worktree — report and
     ask how to proceed (don't guess a path or move the branch).
   - Verify it's clean: `git -C "$DEV" status --porcelain` must be empty. If it's dirty,
     stop and show the output — never disturb another worktree's working tree.

5. **Fast-forward `development` onto this branch.**
   - Try `git -C "$DEV" merge --ff-only "$BR"`.
   - If that fails because `development` has advanced past this branch's base, make the
     fast-forward possible by rebasing this workspace branch onto `development`, then retry:
     - **Guard first:** if `git rev-parse --abbrev-ref --symbolic-full-name @{u}` succeeds
       (this branch is pushed) *and* `git log @{u}..HEAD --oneline` is non-empty, warn the
       user that rebasing rewrites already-pushed history and **ask** before continuing.
     - `git rebase development`. If it stops on conflicts, run `git rebase --abort` and
       report — don't try to auto-resolve.
     - `git -C "$DEV" merge --ff-only "$BR"`.

6. **Rebuild the `development` checkout** so the live-mount env serves it.
   - `cd "$DEV" && npm run build`. If it fails, show the error and stop.

7. **Report.**
   - The short SHA now on `development`, the workspace branch it came from, and that `$DEV`
     was rebuilt.
   - Remind: the child-theme wp-env (dev port 8890) serves `$DEV` via a bind mount — no
     restart needed. `src/blocks/**` changes needed the rebuild this command just ran;
     template/PHP/`theme.json` changes would render without one.
