# Client-kit external distribution and installed-path repair

**Date:** 2026-08-04
**Status:** Design approved; written spec awaiting user review
**Scope:** Make the existing `client-kit/` installable and runnable by an external developer who
does not clone the Pediment monorepo

## 1. Problem statement

Pediment has two different things called plugins:

- `plugin/` is the **WordPress plugin and product**. WordPress installs it from
  `pediment-plugin.zip`.
- `client-kit/` is a small **Claude Code plugin for developers**. It provides
  `/pediment:start`, `/pediment:port-page`, and the deterministic theme scaffolder.

The Claude Code kit validates and installs from a local checkout, but its skills refer to files as
if the developer were standing in this monorepo. An installed skill runs from the developer's
client project, so paths such as `client-kit/scripts/scaffold.mjs`, `client-template`, and
`shared/visual-qa.md` do not resolve. The current marketplace manifest also lives below the repo
root, making the documented remote install impossible even though the documentation says an
external developer never clones the monorepo.

The problem is therefore not a missing WordPress feature. It is a broken distribution boundary
around the developer tool that creates standalone client-theme repositories.

## 2. Evidence and constraints

- A live local installation on 2026-08-04 proved that the existing manifest schema, `source: "./"`,
  plugin discovery, and both skill registrations are valid.
- Claude Code injects an absolute `Base directory for this skill` whenever it loads a skill. The
  skills can derive the kit root from that guaranteed directory; they do not need
  `CLAUDE_PLUGIN_ROOT`, a hook, or an npm package.
- `${CLAUDE_SKILL_DIR}` is available for path substitution in skill frontmatter such as
  `allowed-tools`, but `CLAUDE_PLUGIN_ROOT` is not exported into Bash tool calls.
- `client-kit/scripts/scaffold.mjs` already downloads
  `pediment-client-template.zip` from the matching GitHub release when `--template` is omitted.
- `.github/workflows/build-release-zip.yml` already builds and uploads both
  `pediment-plugin.zip` and `pediment-client-template.zip` for a release.
- The public latest release is v3.0.0 as of 2026-08-04. It predates the seeding engine and the
  template-asset workflow, so no published release can complete the external flow yet.
- Claude Code marketplace sources may be Git repositories or local paths. Hosting the kit as a
  website zip is not part of the plugin installation model. External developers do not need to
  visit GitHub, create a GitHub account, or clone the repo; Claude Code fetches the public repo
  over HTTPS.

## 3. Goals

1. An external developer can install the Pediment client kit with two Claude Code commands and no
   monorepo checkout.
2. `/pediment:start` can run from an unrelated empty project directory and resolve every bundled
   script, fixture, and shared instruction from the installed kit.
3. A scaffold uses one version for the kit, theme template, and WordPress plugin without asking the
   developer to discover or choose that version.
4. The first release containing this work supports the complete path from kit installation to a
   seeded WordPress site at `http://localhost:8888`.
5. Automated tests fail on the exact checkout-relative path form that escaped the current guard.

## 4. Non-goals

- **Changing WordPress plugin behavior.** The seeding engine, blocks, updater, and admin experience
  are unchanged; this work only makes the existing released behavior reachable.
- **Publishing the scaffolder to npm.** The installed skill already has a guaranteed base
  directory, so npm would add a second publishing and versioning channel without solving a new
  problem.
- **Hosting release artifacts on the Pediment website.** GitHub Releases already provides the
  versioned HTTPS assets and is part of the automated release workflow. The developer does not
  interact with GitHub directly.
- **Putting the Claude Code kit into client-theme repositories.** The kit is installed once in
  Claude Code; it creates client repos but is not copied into them.
- **Adding client-theme auto-updates or changing client-theme release workflows.** Those are
  separate product decisions.
- **Excluding `client-kit/tests/` from installed copies.** The marketplace format has no supported
  exclusion field, and the extra files are harmless.
- **Giving the kit an independent version.** The user explicitly chose one monorepo version line.

## 5. User stories

1. As an external WordPress developer, I want to install the Pediment development kit without
   cloning its source repo so that I can start a client site from my normal workspace.
2. As an external WordPress developer, I want `/pediment:start` to obtain the compatible theme
   template and WordPress plugin automatically so that I do not have to understand Pediment's
   internal repository layout or release matrix.
3. As an external WordPress developer, I want a failed download or incompatible release to name
   the exact version and missing capability so that I can distinguish a release problem from a
   local Docker or WordPress problem.
4. As a Pediment maintainer, I want release-please to update the kit and marketplace versions with
   the monorepo so that a released kit cannot silently request assets from the wrong tag.
5. As a Pediment maintainer, I want CI to reject checkout-relative skill paths so that a kit that
   works only inside this monorepo cannot ship again.

## 6. Decisions

| Area | Decision | Rejected alternative | Reason |
|---|---|---|---|
| Bundled paths | Derive the kit root from the injected skill base directory | `CLAUDE_PLUGIN_ROOT` or a hook | The skill loader already supplies the required absolute location. |
| Scaffolder delivery | Run the bundled `scripts/scaffold.mjs` | Publish and run it through `npx` | Avoids a second package, release, and version line. |
| Template delivery | Download the release's `pediment-client-template.zip` by default | Require `--template client-template` | External developers do not have the monorepo's sibling `client-template/` directory. |
| Marketplace | Put `.claude-plugin/marketplace.json` at the repo root with `source: "./client-kit"` | Keep the marketplace inside `client-kit/` | Claude Code can add `Bergert-Digital/pediment` directly only when the manifest is at repo root. |
| Version | Keep kit, marketplace, template, and WordPress plugin on the monorepo release version | Independently version `client-kit` | A single version makes the correct release URL deterministic. |
| Artifact hosting | Continue using GitHub Releases | Add a website upload step | The existing automated channel already creates both required assets. |

## 7. End-to-end external developer flow

### 7.1 Install the developer kit once

In Claude Code, from any directory:

```text
/plugin marketplace add Bergert-Digital/pediment
/plugin install pediment@pediment
```

Claude Code fetches the public repository over HTTPS and installs only the plugin at
`./client-kit`. The explicit `plugin@marketplace` form avoids selecting a same-named entry from a
different marketplace. The external developer does not clone or work inside the Pediment
monorepo.

### 7.2 Create one client site

The developer opens Claude Code in the empty directory where they want to work and runs:

```text
/pediment:start
```

The skill:

1. Checks Docker, Node 20+, and git before writing anything.
2. Reads the installed kit's `.claude-plugin/plugin.json` version, called `V` below.
3. Asks the existing greenfield or porting questionnaire, one question per message.
4. Writes `.context/start/answers.json` with `plugin.version = V` and
   `template.version = V`. It does not ask the developer to choose a Pediment version.
5. Derives the absolute kit root from the injected skill base directory and runs the bundled
   `scripts/scaffold.mjs` from there.
6. Does **not** pass `--template`. The scaffolder downloads:

   ```text
   https://github.com/Bergert-Digital/pediment/releases/download/vV/pediment-client-template.zip
   ```

7. Scaffolds and commits a standalone client-theme repository. The template's `.wp-env.json`
   points at the matching `vV/pediment-plugin.zip` release asset.
8. Runs `npm install`, starts wp-env, verifies `wp pediment seed --help`, shows the dry-run plan,
   and seeds the site. Multilingual sites configure languages before seeding as they do today.
9. Reports `http://localhost:8888`, the wp-admin URL, and the existing day-two commands.

The resulting repo contains only the client theme, declarative seed files, documentation, and its
thin development workflow. It does not contain `client-kit/` or the Pediment monorepo.

### 7.3 Day-two and production flow

This work does not change day-two behavior. Developers edit `patterns/*.php` and
`seed/manifest.php`, review `npm run seed:plan`, apply `npm run seed`, and use
`npm run adopt -- <key>` for accepted editor changes. Client-theme tags still produce a theme zip
through the existing reusable workflow, and production still receives theme updates by manual
wp-admin upload.

## 8. Requirements and acceptance criteria

### P0.1 Root marketplace discovery

Relocate the marketplace manifest to `.claude-plugin/marketplace.json` at the monorepo root. Its
single Pediment entry must use `source: "./client-kit"`. Keep the plugin manifest at
`client-kit/.claude-plugin/plugin.json`.

Acceptance criteria:

- [ ] `claude plugin validate . --strict` accepts the root marketplace and nested plugin.
- [ ] `/plugin marketplace add Bergert-Digital/pediment` succeeds without a local checkout path.
- [ ] `/plugin install pediment@pediment` succeeds and `claude plugin details pediment` lists
  both `start` and `port-page`.
- [ ] No installation instruction for an external developer requires `./client-kit`.

### P0.2 One release version

`client-kit/.claude-plugin/plugin.json` and the Pediment entry in the root marketplace manifest
must equal the monorepo version. Release-please must manage both values as release `extra-files`;
they must not be bumped manually out of band.

Acceptance criteria:

- [ ] The repository's structural tests compare both kit version fields with the `"."` version in
  `.release-please-manifest.json`.
- [ ] A release PR updates the plugin manifest and marketplace entry to the same version.
- [ ] `/pediment:start` reads the installed plugin manifest and writes that value to both
  `plugin.version` and `template.version` in its answers file.
- [ ] The skill no longer runs `gh release list` or asks the developer which Pediment version to
  use during the normal installed flow.

### P0.3 Installed-path-safe skills

Both skills must resolve bundled resources from the absolute `Base directory for this skill` that
Claude Code injects. `/pediment:start` must resolve `scripts/scaffold.mjs` and any referenced
fixture this way. `/pediment:port-page` must resolve `shared/fidelity-critic-prompt.md` and
`shared/visual-qa.md` this way.

Skill frontmatter may use `${CLAUDE_SKILL_DIR}` to pre-authorize the bundled scaffolder command.
The body must not assume that shell calls receive a `CLAUDE_PLUGIN_ROOT` environment variable.

Acceptance criteria:

- [ ] Neither skill contains a path whose first component is `client-kit/`.
- [ ] Neither skill resolves a bundled file relative to the client repo's current working
  directory.
- [ ] Running the bundled scaffolder through `/pediment:start` uses the installed absolute path
  and does not ask for an avoidable tool-permission confirmation.
- [ ] The two port-page shared documents can be loaded while the current working directory is an
  unrelated scaffolded client repo.

### P0.4 Release-template path is the normal path

The installed `/pediment:start` flow must omit `--template`, causing `scaffold.mjs` to download the
template asset for the kit version. `--template <dir>` remains supported only as a manual local
development override.

Acceptance criteria:

- [ ] The normal skill command contains `--answers` and `--target` but no `--template`.
- [ ] A missing template asset fails before client-theme files are written and reports the exact
  URL, version, HTTP status, and local `--template` override.
- [ ] A successful download unpacks the expected `client-template/` directory and leaves no
  unresolved `__PEDIMENT_*__` tokens in the scaffold.
- [ ] The generated `.wp-env.json` references `pediment-plugin.zip` at the same release version.

### P0.5 Structural regression tests

Replace the current suffix-matching resource guard with a validator that checks both reference
form and target existence. It must reject a path that merely contains a valid
`scripts/<file>.mjs` or `shared/<file>.md` suffix after a checkout-only prefix.

Acceptance criteria:

- [ ] The current installed-path-safe references pass.
- [ ] A fixture or validator unit case containing `client-kit/scripts/scaffold.mjs` fails even
  though `scripts/scaffold.mjs` exists under the kit root.
- [ ] A correctly formed reference to a missing bundled file fails.
- [ ] Version equality and root marketplace source are covered by `npm run test:kit`.

### P0.6 Documentation and terminology

Update `client-kit/README.md`, `docs/client-sites.md`, and the related backlog entries. The docs
must call `plugin/` the WordPress plugin/product and `client-kit/` the Claude Code developer kit,
show the remote install commands, and remove instructions that require a monorepo checkout for the
normal flow.

Acceptance criteria:

- [ ] External onboarding uses `Bergert-Digital/pediment` and `pediment@pediment`.
- [ ] Local `--template client-template` usage is documented only under maintainer/manual
  scaffolding instructions.
- [ ] Documentation states that the external flow is available only from the first release that
  includes the seeding engine and both release assets.
- [ ] The fixed checkout-path and weak-guard backlog items are closed or linked to their fixing
  commit; the cosmetic shipped-tests item is dropped or explicitly declined.

### P1.1 Repeatable remote-install smoke test

Document a maintainer smoke procedure that installs the public marketplace into an isolated or
disposable Claude configuration, checks both skills, and cleans up afterward. Automate it only if
Claude Code provides a stable isolated-config interface without mutating a developer's normal
installation.

## 9. Error handling and recovery

- **Marketplace unavailable:** stop at installation and report the repository source; no client
  files exist yet.
- **Installed version and marketplace version differ:** fail the structural test and block the
  release. Runtime must not guess which one is authoritative.
- **Template asset missing or network download fails:** `resolveTemplate()` reports the exact URL
  and status. No partially scaffolded theme should be treated as successful. Maintainers may use
  `--template <dir>` locally; external onboarding must not require it.
- **WordPress plugin asset missing:** wp-env fails with the pinned release URL. Report version `V`
  and stop; do not silently substitute `main` or another release.
- **Released WordPress plugin lacks `wp pediment seed`:** the existing post-boot preflight stops
  before `seed:plan` and names version `V`.
- **Docker or Node prerequisite fails:** the existing phase-zero checks stop before scaffolding.
- **Seeding fails:** show the deterministic error or plan and stop. Do not retry unchanged input.

## 10. Verification strategy

### Repository checks before merge

1. Run `npm run test:kit` for marketplace shape, version lockstep, resource-reference validation,
   and scaffolder tests.
2. Run the existing JavaScript lint and client scaffold CI path.
3. Validate both manifests with `claude plugin validate . --strict`.
4. Add the root marketplace from the local repo, install `pediment@pediment`, and verify both
   installed skills from a working directory outside the monorepo.
5. Scaffold with the fixture and no `--template` only when testing against a release that already
   contains the template asset; local pre-release CI continues using the explicit local template
   override so it does not depend on an unreleased URL.

### Release gate

After the changes land, merge the next release-please release PR. The release is part of making the
feature usable even though it is a separate shipping action. Verify that its tag provides:

- `pediment-plugin.zip` containing `wp pediment seed`;
- `pediment-client-template.zip`;
- a kit and root marketplace entry whose version equals the tag.

Then repeat the complete install and `/pediment:start` flow from outside the monorepo. The task is
not externally complete until this post-release smoke reaches a seeded site and a second
`npm run seed:plan` reports `0 to write`.

## 11. Success metrics

This is infrastructure without product telemetry, so success is measured by deterministic smoke
results:

- **Install completion:** 100% of the two-command clean-install smoke attempts succeed without a
  clone or local path.
- **Installed-path correctness:** 100% of bundled path lookups succeed from outside the monorepo;
  zero skill instructions contain checkout-relative `client-kit/` paths.
- **Version integrity:** the kit manifest, marketplace entry, template asset tag, generated
  `template.version`, generated `plugin.version`, and WordPress plugin URL all carry the same `V`.
- **First-run completion:** the release smoke reaches a seeded local site and the second dry run
  converges to `0 to write`.
- **Regression sensitivity:** the structural test fails for the previously missed prefixed-path
  case.

## 12. Rollout and timeline considerations

The work has two serialized phases:

1. Land the marketplace relocation, version management, installed-path fixes, tests, and docs.
2. Cut the next monorepo release, verify both assets, and run the remote external-developer smoke.

There is no hard calendar deadline. The latest public release remains v3.0.0 at spec time and cannot
complete this flow, so documentation must not claim current external availability until phase 2
passes. No external developer should be used as the first validation of the release path.

## 13. Open questions

There are no blocking product questions. The following implementation detail is non-blocking and
must preserve the acceptance criteria above:

- **Engineering:** choose the smallest testable representation for installed resource references
  in skill prose. Whether this is one derived `PEDIMENT_KIT_ROOT` shell variable or repeated
  base-relative paths is an implementation choice; all runtime paths must still derive from the
  injected skill base directory.
