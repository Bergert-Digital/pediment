---
description: Sync the live parent-theme checkout (/Users/jonas/Entwicklung/pediment) to this workspace's committed branch HEAD and rebuild, so child-theme wp-envs that mount ../pediment serve the latest.
argument-hint: "[branch-or-ref]  — defaults to the current workspace's HEAD commit"
allowed-tools: Bash(git:*), Bash(npm:*), Bash(cd:*), Bash(test:*)
---

# Mount latest parent theme into the child-theme env

**Goal:** prepare `/Users/jonas/Entwicklung/pediment` — the live parent-theme worktree that
`pediment-child-theme` (and other live-mount envs) serve via `"themes": ["...", "../pediment"]` —
so it reflects the latest **committed** parent-theme changes, then rebuild `build/`.

All of these directories are worktrees of the **same** git repo, so the target commit is already in
the shared object store — no `git push`/`fetch` round-trip is needed. This command tests the
**committed HEAD only**; uncommitted edits are not included.

Follow these steps exactly:

1. **Resolve the source ref.**
   - If `$1` is given, `SRC_REF="$1"`; else `SRC_REF=$(git rev-parse HEAD)` (this workspace's HEAD).
   - Capture a human label: `$1` if given, otherwise `git rev-parse --abbrev-ref HEAD`.
   - Resolve to a commit SHA: `git rev-parse "${SRC_REF}^{commit}"`. If this fails, stop and report.

2. **Warn about uncommitted work.** Run in the current workspace:
   `git status --porcelain -- ':!build' ':!package-lock.json'`
   If it lists source files (e.g. `src/`, `templates/`, `parts/`, `inc/`, `*.php`, `theme.json`),
   tell the user those changes are **not committed** and therefore **won't** be mounted/tested,
   and ask whether to proceed. (This command mounts the committed HEAD only.)

3. **Sync the parent checkout without switching its branch.** A branch checked out in a workspace
   worktree cannot also be checked out here, so **detach** onto the SHA (the `-f` discards any stale
   `build/` artifacts in the target folder):
   `git -C /Users/jonas/Entwicklung/pediment checkout -f --detach <SHA>`

4. **Rebuild:** `cd /Users/jonas/Entwicklung/pediment && npm run build`
   If the build fails, show the error output and stop — do not claim success.

5. **Report:** the short SHA + label now mounted, e.g.
   `✓ parent theme mounted at <short-sha> (<label>), rebuilt`.
   Confirm the child-theme wp-env (`pediment-child-theme`, dev port 8890) will now serve it — the
   mount is a bind mount, so **no wp-env restart is needed**. Only template/PHP/theme.json changes
   render without a build; block source (`src/blocks/**`) changes require the rebuild this command ran.
