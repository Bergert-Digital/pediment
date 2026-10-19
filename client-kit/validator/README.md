# Block validator runtimes

Each `wp-<line>/` folder pins the WordPress block packages that
`scripts/validate-blocks.mjs --wp <line>` loads. `scripts/lib/wp-runtime.mjs`
installs a folder with `npm ci` into `~/.cache/pediment/validator` on first
use. A folder with a `package-lock.json` is a supported line: the CLI, the
tests and `tools/validate-convert-fixtures.mjs` find it automatically.

## Where the versions come from

Up to 6.9, wordpress-develop's `package.json` lists the `@wordpress/*`
versions itself. From 7.0 on it builds them from one Gutenberg commit:

1. Read `gutenberg.sha` in wordpress-develop's `package.json` at the release
   tag, for example
   `https://raw.githubusercontent.com/WordPress/wordpress-develop/7.1.3/package.json`.
2. Read `packages/<name>/package.json` in Gutenberg at that sha, for example
   `https://raw.githubusercontent.com/WordPress/gutenberg/<sha>/packages/element/package.json`.
3. Cross-check with the npm dist-tag when one exists:
   `npm view @wordpress/blocks dist-tags` (`wp-6.9`, `wp-7.0`; there is no `wp-7.1`).

| Line | Source tag | Gutenberg sha | block-library | blocks | element |
| --- | --- | --- | --- | --- | --- |
| 6.9 | `6.9` branch (6.9.10), listed in `package.json` | – | 9.33.10 (ships 9.33.11) | 15.6.3 | 6.39.0 (ships 6.33.1, not pinned) |
| 7.0 | `7.0.7` | `28c0dedc4eaf001a24237a1fbba4b0887698b000` (Gutenberg 22.6.0-rc.1) | 9.40.2 | 15.13.1 | 6.40.1 |
| 7.1 | `7.1.3` | `dd294ab86ceaf9f3d7d42a09b78f399238ce8826` (Gutenberg 23.6.0-rc.1) | 10.2.0 | 15.24.0 | 8.3.0 |

React is 18.3.1 on every line (wordpress-develop `package.json`).

Known deviations:

- **6.9:** block-library is 9.33.10, not 9.33.11. Version 9.33.11 installs
  a second copy of the editor stores and gives false invalid results.
  element is not pinned, so the lock has 6.39.0.
- **7.0:** the lock was resolved with `--before=2026-06-30T17:00:00Z`, the day
  the 9.40.2 patch was published. The other `@wordpress/*` packages are the
  newer x.49 releases of that day, not the x.40 releases 7.0.7 ships
  (for example rich-text 7.49.0 instead of 7.40.1).
- **7.1:** none. Every `@wordpress/*` package in the lock matches Gutenberg at
  the 7.1.3 sha (rich-text 7.51.0, data 10.51.0, block-editor 16.0.0).

## Add a line

1. Find the versions as described above.
2. In a scratch folder, write `package.json` with the exact block-library,
   blocks and element versions, jsdom 26.1.0 and react/react-dom 18.3.1. Add
   `overrides` that point `@wordpress/blocks` and `@wordpress/element` at the
   direct dependency (`"$@wordpress/blocks"`), as in `wp-7.1/package.json`.
   Without the element override rich-text can get its own element, and
   every `<p>` then fails to validate.
3. Install with npm 10 and a date just after the packages were published:
   `npx -y npm@10 install --before=<ISO date> --ignore-scripts --no-audit --no-fund`.
   Find the date with `npm view @wordpress/block-library time`.
4. Check that each `@wordpress/*` package exists only once:
   `find node_modules -name package.json | grep -E 'node_modules/@wordpress/[^/]+/package.json$' | sed 's#.*@wordpress/##' | sort | uniq -d`
   must print nothing for blocks, element, data, rich-text and private-apis.
5. Check that both `npx -y npm@10 ci` and `npm ci` with npm 11 accept the
   lock. npm 11 rejects a lock that overrides a package that is only a
   transitive dependency (seen with `@wordpress/rich-text` on 7.1), so pin
   only what the override needs.
6. Copy `package.json` and `package-lock.json` into `wp-<line>/`, then run
   `node tools/validate-convert-fixtures.mjs --wp <line>` and
   `PEDIMENT_VALIDATOR_E2E=1 node --test client-kit/tests/validate-blocks.test.mjs`.
7. Update the line list in the `supportedLines()` test, `docs/mcp.md` and
   `docs/convert.md`.
