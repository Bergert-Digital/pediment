# Scaffolder and `/start` (Migration Step 5) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Turn "make a new Pediment client site" into one skill invocation — `/pediment:start` asks what cannot be derived, a deterministic node scaffolder emits a standalone client theme repo from a monorepo-owned template, and the run ends at a seeded site rendering on localhost — implementing migration step 5 of `docs/superpowers/specs/2026-08-03-scaffolder-and-start-step5-design.md`.

**Architecture:** Three new top-level directories. `client-template/` is the canonical standalone client theme, written with literal `__PEDIMENT_*__` placeholders. `client-kit/` is a Claude Code plugin bundle carrying the `/start` and `/port-page` skills plus one scaffolder entry point (`scripts/scaffold.mjs`) and its pure helpers (`brand.mjs`, `manifest.mjs`). Two reusable GitHub workflows in `.github/workflows/` are called by every scaffolded client repo, sharing their real work with the monorepo's own integration job through a composite action. The questionnaire's entire output is one JSON answers file; the scaffolder is a pure function of it, which is what makes it unit-testable without a browser, Docker, or wp-env.

**Tech Stack:** Node 20 (built-in `node:test`, `node:fs/promises`, `fetch`; zero runtime dependencies), WordPress 6.9, PHP 8.1 (generated manifest and pattern files only — no plugin PHP changes), GitHub Actions (`workflow_call` + composite actions), `@wordpress/env`.

## Global Constraints

- **Never push without explicit user approval.** All work is local until the single gated push in Task 15.
- Work stays on the current branch `dev-flow-step-5`. No new branches or worktrees — the Conductor workspace *is* the isolation.
- **Nothing under `plugin/` changes.** This step is additive tooling: it ships as a **minor** with conventional `feat:`/`fix:`/`docs:`/`test:`/`ci:` commits only — no `!`, no `Release-As:` footer. Version files belong to release-please; never hand-bump.
- **`client-kit/scripts/*.mjs` have zero dependencies.** Node built-ins only (`node:fs/promises`, `node:path`, `node:child_process`, `node:os`, global `fetch`). The one external binary allowed is `unzip`, and only on the template-download path, guarded by an explicit check.
- **All new tests use `node:test` + `node:assert/strict`**, run with `node --test`. No jest, no new devDependencies anywhere.
- **Never invent token slugs.** The client palette uses exactly the eleven slugs the plugin's defaults declare (`plugin/tokens/theme.json`): `primary`, `accent`, `accent-hover`, `accent-tint`, `surface`, `surface-elevated`, `surface-sunken`, `foreground`, `foreground-muted`, `border`, `border-strong`.
- **The manifest format is fixed and strictly validated by the plugin.** Top-level sections are exactly `version`, `languages`, `pages`, `posts`, `entries`, `media`, `navs`, `post_types`, `site`. An unrecognised key is a hard `ManifestError`. See `docs/seeding.md`.
- **Never write pages with `wp post create`.** The seeder owns identity (`_pediment_seed_key`) and the arbitration hashes.
- **`3.3.0` in every answers fixture is a placeholder, not a real release.** The latest published
  release is **v3.0.0**, and it predates the step-3 seeding engine entirely — `wp pediment seed`
  does not exist in it. Consequences, both load-bearing: every local rehearsal and the CI job must
  point wp-env at this workspace's own `plugin/` directory, never at a released zip; and
  `resolveTemplate`'s download path cannot be exercised at all until a release ships
  `pediment-client-template.zip` (Task 13). `/pediment:start` resolves the real current version at
  runtime with `gh release list`, so no shipped code hardcodes a version — only the fixtures do.
- Working directory: `/Users/jonas/conductor/workspaces/pediment/charlottetown`.
- Every task ends with its own suite green locally: `npm run test:kit` (new), plus `npm run lint:colors` and `npm run lint:blocks` from the root where markup changed.

## Design decisions this plan makes beyond the spec

The spec fixes the architecture. Six gaps had to be closed to make it implementable. Each is deliberate — flag it to the user if a review disagrees.

1. **The template ships no `seed/manifest.php`.** The manifest is a function of the answers (which pages, which languages), so the scaffolder *generates* it rather than token-replacing a stub. Consequence: `client-template/` on its own is not a seedable theme, and its only regression gate is the scaffold-and-seed integration job (Task 9). Accepted — a stub manifest that the scaffolder overwrites would be a second, silently-diverging source of truth for the format.
2. **Pattern pruning.** The template ships `patterns/{home,about,services,contact}.php`; a sitemap answer that omits `services` must also delete `patterns/services.php`, or the site carries a registered pattern for a page that does not exist. The scaffolder prunes by the page-key set.
3. **`brand.mjs` emits only the slugs it sets.** Spike claim 5 (§4.1 of the parent spec) established that the plugin merges client tokens over its defaults **per slug**, unlike parent/child `theme.json` semantics. So the generated `theme.json` declares the palette entries the brand actually changes and nothing else — the opposite of workation's `theme-reskin.mjs`, which had to fork the parent's whole array.
4. **Version-header stamping is a tested node script, not inline `sed` in a workflow.** The released artifact's version header failing to move was found and fixed independently in all four repos (`9c9af20`, `22f0024`, `432faf6`, workation #22). `tools/stamp-theme-version.mjs` gives it one home *and* a unit test, which inline `sed` inside YAML can never have.
5. **The reusable client CI workflow and the monorepo's integration job share a composite action.** Otherwise the workflow every client repo depends on would be the one thing this repo's CI never executes. `.github/actions/seed-check/` holds the real steps; `client-theme.yml` is a thin `workflow_call` wrapper around it, and Task 9's job calls the same action against a locally built plugin.
6. **wp-env theme activation is explicit.** The monorepo activates its fixture theme with an mu-plugin (`plugin/tests/fixtures/mu-activate-theme.php`); a client repo instead runs `wp theme activate <slug>` and `wp plugin activate pediment` as part of `npm run env:start`. One fewer magic file in the repo that multiplies per client.

## File Structure (end state)

```
client-template/                        NEW — canonical standalone client theme
  style.css                             __PEDIMENT_NAME__ / __PEDIMENT_SLUG__ headers
  theme.json                            minimal; scaffolder writes settings
  templates/index.html                  required for wp_is_block_theme()
  patterns/home.php                     Slug: __PEDIMENT_SLUG__/home
  patterns/about.php  services.php  contact.php
  seed/media/.gitkeep
  docs/brief.md                         questionnaire artifact, tokenised
  .wp-env.json                          pinned plugin release zip
  package.json                          env/seed scripts + the version block
  .github/workflows/ci.yml              one `uses:` line
  .github/workflows/release.yml         one `uses:` line, on pushed v* tag
  AGENTS.md  README.md  .gitignore

client-kit/                             NEW — Claude Code plugin bundle
  .claude-plugin/plugin.json
  .claude-plugin/marketplace.json       lets it be installed from a local path
  skills/start/SKILL.md
  skills/port-page/SKILL.md
  shared/fidelity-critic-prompt.md      ported from workation
  shared/visual-qa.md                   ported from workation
  scripts/scaffold.mjs                  the one entry point
  scripts/brand.mjs                     pure colour maths + theme.json emission
  scripts/manifest.mjs                  pure answers -> seed/manifest.php
  tests/brand.test.mjs  manifest.test.mjs  scaffold.test.mjs  kit.test.mjs
  tests/fixtures/answers-greenfield.json  answers-multilingual.json  answers-ci.json
  tests/fixtures/mini-template/         tiny tree for scaffolder unit tests

.github/actions/seed-check/action.yml   NEW — the shared boot-seed-render steps
.github/workflows/client-theme.yml      NEW — reusable CI for client repos
.github/workflows/client-release.yml    NEW — reusable release for client repos
.github/workflows/ci.yml                MODIFIED — kit tests + scaffold-and-seed job
.github/workflows/build-release-zip.yml MODIFIED — attach pediment-client-template.zip
tools/stamp-theme-version.mjs           NEW — tested version-header stamping
tools/stamp-theme-version.test.mjs      NEW
package.json                            MODIFIED — test:kit script
docs/client-sites.md                    NEW — how to make and run a client site
docs/BACKLOG.md  README.md  AGENTS.md   MODIFIED
```

---

### Task 1: `brand.mjs` — colour maths and `theme.json` emission

**Files:**
- Create: `client-kit/scripts/brand.mjs`
- Create: `client-kit/tests/brand.test.mjs`
- Modify: `package.json` (add `test:kit`)

**Interfaces:**
- Consumes: nothing.
- Produces:
  - `normalizeHex(input: string|null): string|null` — `'rgb(10, 20, 30)'` / `'#ABC'` / `'abcdef'` → `'#0a141e'` / `'#aabbcc'` / `'#abcdef'`; `null` when unparseable.
  - `darken(hex: string, amount: number): string`
  - `mix(hex: string, withHex: string, ratio: number): string`
  - `derivePalette(input: {accent: string, primary?: string, foreground?: string}): Record<string,string>` keyed by the eleven plugin slugs.
  - `themeJsonSettings(brand: {accent, primary?, foreground?, font?: {family, weights, fontFile?}}): object` — a full `theme.json` object (`$schema`, `version: 2`, `settings`).

- [ ] **Step 1: Write the failing tests**

Create `client-kit/tests/brand.test.mjs`:

```js
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { normalizeHex, darken, mix, derivePalette, themeJsonSettings } from '../scripts/brand.mjs';

test('normalizeHex accepts rgb(), short hex, bare hex and rejects junk', () => {
  assert.equal(normalizeHex('rgb(10, 20, 30)'), '#0a141e');
  assert.equal(normalizeHex('rgba(10,20,30,0.5)'), '#0a141e');
  assert.equal(normalizeHex('#ABC'), '#aabbcc');
  assert.equal(normalizeHex('abcdef'), '#abcdef');
  assert.equal(normalizeHex('  #B91C1C '), '#b91c1c');
  assert.equal(normalizeHex('not a colour'), null);
  assert.equal(normalizeHex(null), null);
});

test('darken and mix are deterministic and clamped', () => {
  assert.equal(darken('#ffffff', 0.5), '#808080');
  assert.equal(darken('#000000', 0.5), '#000000');
  assert.equal(mix('#000000', '#ffffff', 0.5), '#808080');
  assert.equal(mix('#000000', '#ffffff', 1), '#ffffff');
});

test('derivePalette returns exactly the eleven plugin token slugs', () => {
  const palette = derivePalette({ accent: '#B91C1C' });
  assert.deepEqual(Object.keys(palette).sort(), [
    'accent', 'accent-hover', 'accent-tint', 'border', 'border-strong',
    'foreground', 'foreground-muted', 'primary', 'surface',
    'surface-elevated', 'surface-sunken',
  ]);
  assert.equal(palette.accent, '#b91c1c');
  assert.equal(palette.surface, '#ffffff');
});

test('derivePalette falls back to Pediment defaults for primary and foreground', () => {
  const palette = derivePalette({ accent: '#B91C1C' });
  assert.equal(palette.primary, '#0a1b33');
  assert.equal(palette.foreground, '#0b1b33');
});

test('derivePalette derives hover darker and tint lighter than the accent', () => {
  const { accent, 'accent-hover': hover, 'accent-tint': tint } = derivePalette({ accent: '#0E7490' });
  const lum = (h) => [1, 3, 5].reduce((s, i) => s + parseInt(h.slice(i, i + 2), 16), 0);
  assert.ok(lum(hover) < lum(accent), 'hover should be darker than accent');
  assert.ok(lum(tint) > lum(accent), 'tint should be lighter than accent');
});

test('derivePalette throws on an unparseable accent', () => {
  assert.throws(() => derivePalette({ accent: 'burgundy' }), /accent/i);
});

test('themeJsonSettings emits only the palette, not the plugin defaults verbatim', () => {
  const out = themeJsonSettings({ accent: '#B91C1C' });
  assert.equal(out.version, 2);
  assert.equal(out.$schema, 'https://schemas.wp.org/trunk/theme.json');
  assert.equal(out.settings.color.palette.length, 11);
  assert.ok(out.settings.color.palette.every((p) => p.slug && p.color && p.name));
  assert.equal(out.settings.typography, undefined);
});

test('themeJsonSettings adds body and heading families with a fontFace when a font file is given', () => {
  const out = themeJsonSettings({
    accent: '#B91C1C',
    font: { family: 'Inter', weights: ['400', '700'], fontFile: 'inter.woff2' },
  });
  const families = out.settings.typography.fontFamilies;
  assert.deepEqual(families.map((f) => f.slug), ['body', 'heading']);
  assert.match(families[0].fontFamily, /^Inter, system-ui/);
  assert.equal(families[0].fontFace[0].src[0], 'file:./assets/fonts/inter.woff2');
});

test('themeJsonSettings omits fontFace when there is no downloaded file', () => {
  const out = themeJsonSettings({ accent: '#B91C1C', font: { family: 'Georgia' } });
  const families = out.settings.typography.fontFamilies;
  assert.match(families[0].fontFamily, /^Georgia, system-ui/);
  assert.equal(families[0].fontFace, undefined);
});
```

- [ ] **Step 2: Add the test script and run it to verify it fails**

In the root `package.json`, add to `scripts`:

```json
"test:kit": "node --test client-kit/tests/"
```

Run: `npm run test:kit`
Expected: FAIL — `Cannot find module '.../client-kit/scripts/brand.mjs'`.

- [ ] **Step 3: Write the implementation**

Create `client-kit/scripts/brand.mjs`:

```js
/**
 * Pure brand maths for the Pediment client scaffolder.
 *
 * Ported from workation's tools/brand-extract.mjs and tools/theme-reskin.mjs,
 * with one behavioural change: the plugin merges client tokens over its own
 * defaults PER SLUG (see the parent spec's spike claim 5), so this emits only
 * the slugs the brand sets instead of forking a parent palette wholesale.
 *
 * The capture layer — reading a live site's computed styles — stays in the
 * /start skill, exactly as it does in workation's port-site.
 */

/** Pediment's own default primary/foreground, used when a brand declares neither. */
const DEFAULT_PRIMARY = '#0a1b33';
const DEFAULT_FOREGROUND = '#0b1b33';
const STACK = ', system-ui, -apple-system, "Segoe UI", Roboto, sans-serif';

/** Human names for the eleven token slugs, matching plugin/tokens/theme.json. */
const NAMES = {
  primary: 'Primary',
  accent: 'Accent',
  'accent-hover': 'Accent hover',
  'accent-tint': 'Accent tint',
  surface: 'Surface',
  'surface-elevated': 'Surface elevated',
  'surface-sunken': 'Surface sunken',
  foreground: 'Foreground',
  'foreground-muted': 'Foreground muted',
  border: 'Border',
  'border-strong': 'Border strong',
};

export function normalizeHex(input) {
  if (!input) return null;
  let s = String(input).trim();
  const rgb = s.match(/rgba?\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)/i);
  if (rgb) {
    return '#' + [rgb[1], rgb[2], rgb[3]]
      .map((n) => Number(n).toString(16).padStart(2, '0')).join('');
  }
  s = s.replace('#', '').toLowerCase();
  if (s.length === 3) s = s.split('').map((c) => c + c).join('');
  if (!/^[0-9a-f]{6}$/.test(s)) return null;
  return '#' + s;
}

const toRgb = (h) => {
  const x = normalizeHex(h).slice(1);
  return [0, 2, 4].map((i) => parseInt(x.slice(i, i + 2), 16));
};
const toHex = (rgb) =>
  '#' + rgb.map((n) => Math.max(0, Math.min(255, Math.round(n)))
    .toString(16).padStart(2, '0')).join('');

export function darken(hex, amount) {
  return toHex(toRgb(hex).map((c) => c * (1 - amount)));
}

export function mix(hex, withHex, ratio) {
  const a = toRgb(hex);
  const b = toRgb(withHex);
  return toHex(a.map((c, i) => c * (1 - ratio) + b[i] * ratio));
}

export function derivePalette({ accent, primary, foreground } = {}) {
  const a = normalizeHex(accent);
  if (!a) {
    throw new Error(`Unusable accent colour: ${JSON.stringify(accent)}. Expected a hex or rgb() value.`);
  }
  const p = normalizeHex(primary) || DEFAULT_PRIMARY;
  const fg = normalizeHex(foreground) || DEFAULT_FOREGROUND;

  return {
    primary: p,
    accent: a,
    'accent-hover': darken(a, 0.12),
    'accent-tint': mix(a, '#ffffff', 0.88),
    surface: '#ffffff',
    'surface-elevated': mix(p, '#ffffff', 0.95),
    'surface-sunken': mix(p, '#ffffff', 0.92),
    foreground: fg,
    'foreground-muted': mix(fg, '#ffffff', 0.45),
    border: mix(p, '#ffffff', 0.9),
    'border-strong': mix(p, '#ffffff', 0.8),
  };
}

export function themeJsonSettings(brand = {}) {
  const palette = Object.entries(derivePalette(brand))
    .map(([slug, color]) => ({ slug, color, name: NAMES[slug] }));

  const theme = {
    $schema: 'https://schemas.wp.org/trunk/theme.json',
    version: 2,
    settings: { color: { palette } },
  };

  if (brand.font && brand.font.family) {
    const family = brand.font.family + STACK;
    const face = brand.font.fontFile
      ? [{
          fontFamily: brand.font.family,
          fontWeight: (brand.font.weights || ['400', '700']).join(' '),
          fontStyle: 'normal',
          fontDisplay: 'swap',
          src: ['file:./assets/fonts/' + brand.font.fontFile],
        }]
      : null;

    theme.settings.typography = {
      fontFamilies: ['body', 'heading'].map((slug) => {
        const entry = { slug, name: slug === 'body' ? 'Body' : 'Heading', fontFamily: family };
        if (face) entry.fontFace = face;
        return entry;
      }),
    };
  }

  return theme;
}
```

- [ ] **Step 4: Run the tests to verify they pass**

Run: `npm run test:kit`
Expected: PASS — 9 tests.

- [ ] **Step 5: Commit**

```bash
git add client-kit/scripts/brand.mjs client-kit/tests/brand.test.mjs package.json
git commit -m "feat(kit): add the client scaffolder's brand colour maths"
```

---

### Task 2: `manifest.mjs` — answers to `seed/manifest.php`

**Files:**
- Create: `client-kit/scripts/manifest.mjs`
- Create: `client-kit/tests/manifest.test.mjs`

**Interfaces:**
- Consumes: nothing (pure).
- Produces:
  - `phpString(value: string): string` — a single-quoted PHP literal with `\` and `'` escaped.
  - `renderManifest(answers: object): string` — the complete PHP source of `seed/manifest.php`.

The answers shape this consumes (the full schema is fixed in Task 3, and `client-kit/tests/fixtures/answers-greenfield.json` is its reference instance):

```jsonc
{
  "version": 1,
  "mode": "greenfield",
  "client": { "name": "Acme Roofing", "slug": "acme-roofing" },
  "languages": [ { "slug": "en", "name": "English", "locale": "en_US", "flag": "gb", "default": true } ],
  "pages": [
    { "key": "home",     "title": "Home",     "frontPage": true },
    { "key": "about",    "title": "About" },
    { "key": "services", "title": "Services" },
    { "key": "contact",  "title": "Contact" },
    { "key": "blog",     "title": "Blog", "postsPage": true }
  ],
  "logo": { "file": "logo.svg", "sourcePath": "/Users/x/Downloads/acme-logo.svg" },
  "nav": [ "about", "services", "contact" ]
}
```

`logo` is `null` or an object with **both** keys: `file` is the filename inside `seed/media/`,
which this module writes into the manifest; `sourcePath` is where to copy it from, which only
`scaffold()` (Task 4) reads. This module ignores `sourcePath` entirely.

Rules, each from `docs/seeding.md`:
- A page with `postsPage: true` gets `'content' => ''` (it has no pattern file — WordPress renders the posts index).
- Every other page gets `'pattern' => '<slug>/<key>'`.
- `frontPage`/`postsPage` become `front_page`/`posts_page`; at most one of each.
- `languages` is emitted **only when there is more than one language**; a monolingual site declares no section at all, and the whole multilingual code path stays dormant.
- `site.logo` and the `media` entry are emitted only when a logo was given.
- Nav items are `array( 'entry' => '<key>' )` with **no `label`** — `NavSeeder::serialize()` falls back to the linked entry's own per-language `post_title`, and a hardcoded label would render identical text in every language.

- [ ] **Step 1: Write the failing tests**

Create `client-kit/tests/manifest.test.mjs`:

```js
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { phpString, renderManifest } from '../scripts/manifest.mjs';

const base = {
  version: 1,
  client: { name: 'Acme Roofing', slug: 'acme-roofing' },
  languages: [{ slug: 'en', name: 'English', locale: 'en_US', flag: 'gb', default: true }],
  pages: [
    { key: 'home', title: 'Home', frontPage: true },
    { key: 'about', title: 'About' },
  ],
  nav: ['about'],
};

test('phpString escapes backslashes and single quotes', () => {
  assert.equal(phpString("O'Brien"), "'O\\'Brien'");
  assert.equal(phpString('back\\slash'), "'back\\\\slash'");
  assert.equal(phpString('plain'), "'plain'");
});

test('renderManifest opens with a php tag and returns an array', () => {
  const out = renderManifest(base);
  assert.match(out, /^<\?php\n/);
  assert.match(out, /^return array\(/m);
  assert.match(out, /^\);\n$/m);
  assert.match(out, /'version'\s*=>\s*1,/);
});

test('renderManifest namespaces patterns with the client slug', () => {
  const out = renderManifest(base);
  assert.match(out, /'pattern'\s*=>\s*'acme-roofing\/home'/);
  assert.match(out, /'pattern'\s*=>\s*'acme-roofing\/about'/);
});

test('renderManifest marks the front page and gives a posts page empty content', () => {
  const out = renderManifest({
    ...base,
    pages: [...base.pages, { key: 'blog', title: 'Blog', postsPage: true }],
  });
  assert.match(out, /'front_page'\s*=>\s*true/);
  assert.match(out, /'posts_page'\s*=>\s*true/);
  assert.match(out, /'blog'\s*=>\s*array\([^)]*'content'\s*=>\s*''/);
  assert.doesNotMatch(out, /'blog'\s*=>\s*array\([^)]*'pattern'/);
});

test('renderManifest omits the languages section for a monolingual site', () => {
  assert.doesNotMatch(renderManifest(base), /'languages'/);
});

test('renderManifest emits languages with the default first for a multilingual site', () => {
  const out = renderManifest({
    ...base,
    languages: [
      { slug: 'de', name: 'Deutsch', locale: 'de_DE', flag: 'de' },
      { slug: 'en', name: 'English', locale: 'en_US', flag: 'gb', default: true },
    ],
  });
  const languages = out.slice(out.indexOf("'languages'"));
  assert.ok(languages.indexOf("'en'") < languages.indexOf("'de'"), 'default language must be emitted first');
  assert.match(out, /'default'\s*=>\s*true/);
  assert.match(out, /'locale'\s*=>\s*'de_DE'/);
});

test('renderManifest emits nav items without labels', () => {
  const out = renderManifest(base);
  assert.match(out, /'navs'\s*=>\s*array\(/);
  assert.match(out, /array\(\s*'entry'\s*=>\s*'about'\s*\)/);
  assert.doesNotMatch(out, /'label'/);
});

test('renderManifest emits media and site.logo only when a logo is given', () => {
  assert.doesNotMatch(renderManifest(base), /'media'/);
  const out = renderManifest({ ...base, logo: { file: 'logo.svg' } });
  assert.match(out, /'media'\s*=>\s*array\(/);
  assert.match(out, /'file'\s*=>\s*'seed\/media\/logo\.svg'/);
  assert.match(out, /'site'\s*=>\s*array\(\s*'logo'\s*=>\s*'logo'\s*\)/);
});

test('renderManifest escapes a title containing an apostrophe', () => {
  const out = renderManifest({
    ...base,
    pages: [{ key: 'home', title: "What's on", frontPage: true }],
  });
  assert.match(out, /'What\\'s on'/);
});

test('renderManifest rejects two front pages', () => {
  assert.throws(() => renderManifest({
    ...base,
    pages: [
      { key: 'home', title: 'Home', frontPage: true },
      { key: 'other', title: 'Other', frontPage: true },
    ],
  }), /front page/i);
});

test('renderManifest rejects a nav item that is not a declared page', () => {
  assert.throws(() => renderManifest({ ...base, nav: ['nope'] }), /nope/);
});
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `npm run test:kit`
Expected: FAIL — `Cannot find module '.../client-kit/scripts/manifest.mjs'`.

- [ ] **Step 3: Write the implementation**

Create `client-kit/scripts/manifest.mjs`:

```js
/**
 * Renders a client theme's seed/manifest.php from the /start answers file.
 *
 * The format is validated strictly by the plugin — unrecognised top-level or
 * per-entry keys are a hard ManifestError, never a silently-skipped section.
 * See docs/seeding.md in the pediment monorepo for the contract this emits.
 */

const INDENT = '\t';

export function phpString(value) {
  return "'" + String(value).replace(/\\/g, '\\\\').replace(/'/g, "\\'") + "'";
}

/** Default language first — the engine re-orders anyway, and order is load-bearing. */
function orderedLanguages(languages) {
  const list = [...(languages || [])];
  const defaultIndex = list.findIndex((l) => l.default);
  if (defaultIndex > 0) list.unshift(list.splice(defaultIndex, 1)[0]);
  return list;
}

function renderLanguages(languages) {
  const lines = [`${INDENT}'languages' => array(`];
  for (const lang of languages) {
    const parts = [`'name' => ${phpString(lang.name || lang.slug.toUpperCase())}`,
      `'locale' => ${phpString(lang.locale)}`];
    if (lang.flag) parts.push(`'flag' => ${phpString(lang.flag)}`);
    if (lang.default) parts.push("'default' => true");
    lines.push(`${INDENT}${INDENT}${phpString(lang.slug)} => array( ${parts.join(', ')} ),`);
  }
  lines.push(`${INDENT}),`);
  return lines;
}

function renderPages(pages, slug) {
  const lines = [`${INDENT}'pages' => array(`];
  for (const page of pages) {
    const parts = [`'title' => ${phpString(page.title)}`];
    parts.push(page.postsPage
      ? "'content' => ''"
      : `'pattern' => ${phpString(`${slug}/${page.key}`)}`);
    if (page.frontPage) parts.push("'front_page' => true");
    if (page.postsPage) parts.push("'posts_page' => true");
    lines.push(`${INDENT}${INDENT}${phpString(page.key)} => array( ${parts.join(', ')} ),`);
  }
  lines.push(`${INDENT}),`);
  return lines;
}

function renderNav(nav) {
  return [
    `${INDENT}'navs' => array(`,
    `${INDENT}${INDENT}'primary' => array(`,
    `${INDENT}${INDENT}${INDENT}'title' => 'Header Navigation',`,
    // No 'label': NavSeeder::serialize() falls back to the linked entry's own
    // post_title, which is already per-language. A fixed label would render the
    // same text in every language.
    `${INDENT}${INDENT}${INDENT}'items' => array(`,
    ...nav.map((key) => `${INDENT}${INDENT}${INDENT}${INDENT}array( 'entry' => ${phpString(key)} ),`),
    `${INDENT}${INDENT}${INDENT}),`,
    `${INDENT}${INDENT}),`,
    `${INDENT}),`,
  ];
}

export function renderManifest(answers) {
  const slug = answers.client.slug;
  const pages = answers.pages || [];
  const nav = answers.nav || [];

  if (pages.filter((p) => p.frontPage).length > 1) {
    throw new Error('At most one page may be the front page.');
  }
  if (pages.filter((p) => p.postsPage).length > 1) {
    throw new Error('At most one page may be the posts page.');
  }
  const keys = new Set(pages.map((p) => p.key));
  for (const item of nav) {
    if (!keys.has(item)) {
      throw new Error(`Nav item "${item}" is not a declared page.`);
    }
  }

  const languages = orderedLanguages(answers.languages);
  const lines = [
    '<?php',
    '/**',
    ` * Seed manifest for ${answers.client.name}.`,
    ' *',
    ' * Structure lives here; content lives in patterns/. Run `npm run seed:plan`',
    ' * to see what a seed would change before running `npm run seed`.',
    ' *',
    ` * @package ${slug}`,
    ' */',
    '',
    'return array(',
    `${INDENT}'version' => 1,`,
  ];

  if (languages.length > 1) lines.push(...renderLanguages(languages));

  if (answers.logo && answers.logo.file) {
    lines.push(
      `${INDENT}'media' => array(`,
      `${INDENT}${INDENT}'logo' => array( 'file' => ${phpString('seed/media/' + answers.logo.file)}, 'title' => ${phpString(answers.client.name + ' logo')} ),`,
      `${INDENT}),`,
      `${INDENT}'site' => array( 'logo' => 'logo' ),`,
    );
  }

  lines.push(...renderPages(pages, slug));
  if (nav.length) lines.push(...renderNav(nav));
  lines.push(');', '');

  return lines.join('\n');
}
```

- [ ] **Step 4: Run the tests to verify they pass**

Run: `npm run test:kit`
Expected: PASS — the 11 new tests in `manifest.test.mjs`, and Task 1's still green.

- [ ] **Step 5: Verify the generated PHP actually parses**

Run:

```bash
node -e "import('./client-kit/scripts/manifest.mjs').then(m=>{const fs=require('node:fs');fs.writeFileSync('/tmp/manifest-check.php',m.renderManifest({version:1,client:{name:\"O'Brien Roofing\",slug:'obrien-roofing'},languages:[{slug:'en',name:'English',locale:'en_US',default:true},{slug:'de',name:'Deutsch',locale:'de_DE'}],pages:[{key:'home',title:'Home',frontPage:true},{key:'blog',title:'Blog',postsPage:true}],nav:['blog'],logo:{file:'logo.svg'}}))})"
php -l /tmp/manifest-check.php
```

Expected: `No syntax errors detected in /tmp/manifest-check.php`.

- [ ] **Step 6: Commit**

```bash
git add client-kit/scripts/manifest.mjs client-kit/tests/manifest.test.mjs
git commit -m "feat(kit): render seed manifests from the scaffolder's answers file"
```

---

### Task 3: `scaffold.mjs` — template resolution, copying, token replacement, refusals

**Files:**
- Create: `client-kit/scripts/scaffold.mjs`
- Create: `client-kit/tests/scaffold.test.mjs`
- Create: `client-kit/tests/fixtures/mini-template/style.css`
- Create: `client-kit/tests/fixtures/mini-template/patterns/home.php`
- Create: `client-kit/tests/fixtures/mini-template/patterns/services.php`
- Create: `client-kit/tests/fixtures/mini-template/package.json`

**Interfaces:**
- Consumes: nothing from earlier tasks yet (Task 4 wires in `brand.mjs` and `manifest.mjs`).
- Produces:
  - `TOKENS: readonly string[]` — `['__PEDIMENT_SLUG__', '__PEDIMENT_NAME__', '__PEDIMENT_DESCRIPTION__', '__PEDIMENT_PLUGIN_VERSION__', '__PEDIMENT_TEMPLATE_VERSION__']`
  - `replaceTokens(text: string, values: Record<string,string>): string`
  - `validateTarget(target: string): void` — throws on whitespace or a non-empty existing directory.
  - `validateSlug(slug: string): void` — throws unless `/^[a-z0-9]+(-[a-z0-9]+)*$/`.
  - `copyTemplate(srcDir: string, destDir: string, values: Record<string,string>, opts?: {keepPages?: string[]}): Promise<string[]>` — returns written paths relative to `destDir`.
  - `assertNoTokens(destDir: string): Promise<void>` — throws listing every file with a surviving `__…__` token.
  - `resolveTemplate(opts: {template?: string, version?: string, cacheDir?: string}): Promise<string>`

- [ ] **Step 1: Write the mini template fixture**

Create `client-kit/tests/fixtures/mini-template/style.css`:

```css
/*
Theme Name: __PEDIMENT_NAME__
Description: __PEDIMENT_DESCRIPTION__
Version: 0.1.0
Text Domain: __PEDIMENT_SLUG__
*/
```

Create `client-kit/tests/fixtures/mini-template/patterns/home.php`:

```php
<?php
/**
 * Title: Home
 * Slug: __PEDIMENT_SLUG__/home
 * Inserter: no
 */
// phpcs:ignoreFile -- block pattern content
?>
<!-- wp:paragraph --><p>Home</p><!-- /wp:paragraph -->
```

Create `client-kit/tests/fixtures/mini-template/patterns/services.php`:

```php
<?php
/**
 * Title: Services
 * Slug: __PEDIMENT_SLUG__/services
 * Inserter: no
 */
// phpcs:ignoreFile -- block pattern content
?>
<!-- wp:paragraph --><p>Services</p><!-- /wp:paragraph -->
```

Create `client-kit/tests/fixtures/mini-template/package.json`:

```json
{
  "name": "__PEDIMENT_SLUG__",
  "version": "0.1.0",
  "private": true,
  "pediment": { "template": "__PEDIMENT_TEMPLATE_VERSION__", "plugin": "__PEDIMENT_PLUGIN_VERSION__" }
}
```

- [ ] **Step 2: Write the failing tests**

Create `client-kit/tests/scaffold.test.mjs`:

```js
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { mkdtemp, mkdir, readFile, writeFile, rm } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import {
  TOKENS, replaceTokens, validateTarget, validateSlug,
  copyTemplate, assertNoTokens, resolveTemplate,
} from '../scripts/scaffold.mjs';

const here = path.dirname(fileURLToPath(import.meta.url));
const MINI = path.join(here, 'fixtures', 'mini-template');
const values = {
  __PEDIMENT_SLUG__: 'acme-roofing',
  __PEDIMENT_NAME__: 'Acme Roofing',
  __PEDIMENT_DESCRIPTION__: 'Roofing for the North East.',
  __PEDIMENT_PLUGIN_VERSION__: '3.3.0',
  __PEDIMENT_TEMPLATE_VERSION__: '3.3.0',
};

const temp = () => mkdtemp(path.join(tmpdir(), 'pediment-scaffold-'));

test('replaceTokens replaces every known token, everywhere', () => {
  const out = replaceTokens('__PEDIMENT_SLUG__ and __PEDIMENT_SLUG__ and __PEDIMENT_NAME__', values);
  assert.equal(out, 'acme-roofing and acme-roofing and Acme Roofing');
});

test('replaceTokens leaves unknown tokens alone so assertNoTokens can catch them', () => {
  assert.equal(replaceTokens('__PEDIMENT_MYSTERY__', values), '__PEDIMENT_MYSTERY__');
});

test('TOKENS lists every token the templates may use', () => {
  assert.deepEqual([...TOKENS].sort(), Object.keys(values).sort());
});

test('validateSlug accepts lowercase-hyphenated slugs and rejects everything else', () => {
  validateSlug('acme-roofing');
  validateSlug('acme2');
  assert.throws(() => validateSlug('Acme Roofing'), /slug/i);
  assert.throws(() => validateSlug('acme_roofing'), /slug/i);
  assert.throws(() => validateSlug('-acme'), /slug/i);
  assert.throws(() => validateSlug(''), /slug/i);
});

test('validateTarget refuses a path containing whitespace', () => {
  assert.throws(() => validateTarget('/tmp/my client/site'), /whitespace/i);
});

test('validateTarget refuses a non-empty existing directory', async () => {
  const dir = await temp();
  await writeFile(path.join(dir, 'occupied.txt'), 'x');
  assert.throws(() => validateTarget(dir), /not empty/i);
  await rm(dir, { recursive: true, force: true });
});

test('validateTarget accepts a missing directory and an empty one', async () => {
  const dir = await temp();
  validateTarget(dir);
  validateTarget(path.join(dir, 'does-not-exist-yet'));
  await rm(dir, { recursive: true, force: true });
});

test('copyTemplate writes the tree with tokens replaced', async () => {
  const dir = await temp();
  const dest = path.join(dir, 'out');
  const written = await copyTemplate(MINI, dest, values);

  assert.ok(written.includes('style.css'));
  assert.ok(written.includes(path.join('patterns', 'home.php')));

  const css = await readFile(path.join(dest, 'style.css'), 'utf8');
  assert.match(css, /Theme Name: Acme Roofing/);
  assert.match(css, /Text Domain: acme-roofing/);

  const home = await readFile(path.join(dest, 'patterns', 'home.php'), 'utf8');
  assert.match(home, /Slug: acme-roofing\/home/);

  await rm(dir, { recursive: true, force: true });
});

test('copyTemplate prunes pattern files for pages that were not chosen', async () => {
  const dir = await temp();
  const dest = path.join(dir, 'out');
  const written = await copyTemplate(MINI, dest, values, { keepPages: ['home'] });

  assert.ok(written.includes(path.join('patterns', 'home.php')));
  assert.ok(!written.includes(path.join('patterns', 'services.php')));
  await assert.rejects(readFile(path.join(dest, 'patterns', 'services.php'), 'utf8'));

  await rm(dir, { recursive: true, force: true });
});

test('assertNoTokens passes on a fully-replaced tree', async () => {
  const dir = await temp();
  const dest = path.join(dir, 'out');
  await copyTemplate(MINI, dest, values);
  await assertNoTokens(dest);
  await rm(dir, { recursive: true, force: true });
});

test('assertNoTokens names the file when a token survives', async () => {
  const dir = await temp();
  const dest = path.join(dir, 'out');
  await mkdir(dest, { recursive: true });
  await writeFile(path.join(dest, 'leftover.txt'), 'hello __PEDIMENT_MYSTERY__ world');

  await assert.rejects(assertNoTokens(dest), (err) => {
    assert.match(err.message, /leftover\.txt/);
    assert.match(err.message, /__PEDIMENT_MYSTERY__/);
    return true;
  });

  await rm(dir, { recursive: true, force: true });
});

test('resolveTemplate returns a local directory unchanged', async () => {
  assert.equal(await resolveTemplate({ template: MINI }), MINI);
});

test('resolveTemplate refuses a local template path that does not exist', async () => {
  await assert.rejects(resolveTemplate({ template: '/nope/not/here' }), /not a directory/i);
});
```

- [ ] **Step 3: Run the tests to verify they fail**

Run: `npm run test:kit`
Expected: FAIL — `Cannot find module '.../client-kit/scripts/scaffold.mjs'`.

- [ ] **Step 4: Write the implementation**

Create `client-kit/scripts/scaffold.mjs`:

```js
#!/usr/bin/env node
/**
 * Scaffold a standalone Pediment client theme repo from the client template.
 *
 * A pure function of one JSON answers file, by design: the /start skill owns
 * everything that needs judgment, this owns everything that must be identical
 * every time. That boundary is what makes scaffolding unit-testable without a
 * browser, Docker or wp-env.
 *
 * Rewriting is token-driven, never knowledge-driven — the template ships
 * literal __PEDIMENT_*__ placeholders and this replaces all of them blindly,
 * so a new template file carrying a token needs no change here.
 */

import { cp, mkdir, readdir, readFile, rm, stat, writeFile } from 'node:fs/promises';
import { existsSync, readdirSync, statSync } from 'node:fs';
import { execFileSync } from 'node:child_process';
import { tmpdir } from 'node:os';
import path from 'node:path';

export const TOKENS = Object.freeze([
  '__PEDIMENT_SLUG__',
  '__PEDIMENT_NAME__',
  '__PEDIMENT_DESCRIPTION__',
  '__PEDIMENT_PLUGIN_VERSION__',
  '__PEDIMENT_TEMPLATE_VERSION__',
]);

const RELEASE_BASE = 'https://github.com/Bergert-Digital/pediment/releases';
/** Files copied verbatim — token replacement would corrupt them. */
const BINARY = /\.(png|jpe?g|gif|webp|avif|ico|woff2?|ttf|otf|zip|pdf)$/i;

export function replaceTokens(text, values) {
  let out = text;
  for (const token of TOKENS) {
    if (Object.hasOwn(values, token)) {
      out = out.split(token).join(values[token]);
    }
  }
  return out;
}

export function validateSlug(slug) {
  if (!/^[a-z0-9]+(-[a-z0-9]+)*$/.test(String(slug || ''))) {
    throw new Error(
      `"${slug}" is not a usable theme slug. Use lowercase letters, digits and single hyphens ` +
      '(e.g. "acme-roofing") — WordPress derives the stylesheet identifier from it.',
    );
  }
}

export function validateTarget(target) {
  if (/\s/.test(target)) {
    throw new Error(
      `Target path "${target}" contains whitespace.\n\n` +
      '  WordPress derives the theme stylesheet identifier from the directory name, and the\n' +
      "  Site Editor's template-part edit URLs are built as ?p=<stylesheet>//<slug>, which\n" +
      '  WordPress\'s JS routing cannot parse when <stylesheet> contains a space.\n\n' +
      '  Use a lowercase-hyphenated directory name instead.',
    );
  }
  if (existsSync(target)) {
    if (!statSync(target).isDirectory()) {
      throw new Error(`Target "${target}" exists and is not a directory.`);
    }
    if (readdirSync(target).length > 0) {
      throw new Error(`Target directory "${target}" is not empty. Refusing to scaffold into it.`);
    }
  }
}

async function walk(dir, base = dir) {
  const out = [];
  for (const entry of await readdir(dir, { withFileTypes: true })) {
    const full = path.join(dir, entry.name);
    if (entry.isDirectory()) {
      out.push(...await walk(full, base));
    } else {
      out.push(path.relative(base, full));
    }
  }
  return out;
}

export async function copyTemplate(srcDir, destDir, values, opts = {}) {
  const keepPages = opts.keepPages || null;
  const written = [];

  for (const rel of await walk(srcDir)) {
    if (keepPages) {
      const match = rel.match(/^patterns[/\\]([^/\\.]+)\.php$/);
      if (match && !keepPages.includes(match[1])) continue;
    }

    const src = path.join(srcDir, rel);
    const dest = path.join(destDir, replaceTokens(rel, values));
    await mkdir(path.dirname(dest), { recursive: true });

    if (BINARY.test(rel)) {
      await cp(src, dest);
    } else {
      await writeFile(dest, replaceTokens(await readFile(src, 'utf8'), values));
    }
    written.push(rel);
  }

  return written;
}

export async function assertNoTokens(destDir) {
  const offenders = [];
  for (const rel of await walk(destDir)) {
    if (BINARY.test(rel)) continue;
    const found = (await readFile(path.join(destDir, rel), 'utf8')).match(/__[A-Z0-9_]+__/g);
    if (found) offenders.push(`${rel}: ${[...new Set(found)].join(', ')}`);
  }
  if (offenders.length) {
    throw new Error(
      'Unreplaced template tokens survived scaffolding:\n  ' + offenders.join('\n  ') +
      '\n\nAdd the token to TOKENS in scaffold.mjs, or remove it from the template.',
    );
  }
}

export async function resolveTemplate({ template, version, cacheDir } = {}) {
  if (template) {
    let info;
    try {
      info = await stat(template);
    } catch {
      throw new Error(`--template "${template}" is not a directory.`);
    }
    if (!info.isDirectory()) throw new Error(`--template "${template}" is not a directory.`);
    return template;
  }

  if (!version) {
    throw new Error('Either --template <dir> or --plugin-version <x.y.z> is required.');
  }

  try {
    execFileSync('unzip', ['-v'], { stdio: 'ignore' });
  } catch {
    throw new Error('`unzip` is required to download the client template. Install it, or pass --template <dir>.');
  }

  const dir = cacheDir || path.join(tmpdir(), `pediment-template-${version}`);
  await mkdir(dir, { recursive: true });
  const zip = path.join(dir, 'client-template.zip');
  const url = `${RELEASE_BASE}/download/v${version}/pediment-client-template.zip`;

  const response = await fetch(url);
  if (!response.ok) {
    throw new Error(`Could not download ${url} (HTTP ${response.status}). Pass --template <dir> to scaffold from a local checkout.`);
  }
  await writeFile(zip, Buffer.from(await response.arrayBuffer()));
  await rm(path.join(dir, 'client-template'), { recursive: true, force: true });
  execFileSync('unzip', ['-q', zip, '-d', dir], { stdio: 'inherit' });

  return path.join(dir, 'client-template');
}
```

- [ ] **Step 5: Run the tests to verify they pass**

Run: `npm run test:kit`
Expected: PASS — the 13 new tests in `scaffold.test.mjs`, and nothing earlier regressed.

- [ ] **Step 6: Commit**

```bash
git add client-kit/scripts/scaffold.mjs client-kit/tests/scaffold.test.mjs client-kit/tests/fixtures/mini-template
git commit -m "feat(kit): copy and tokenise the client template deterministically"
```

---

### Task 4: `scaffold.mjs` — composition, generated files, and the CLI

**Files:**
- Modify: `client-kit/scripts/scaffold.mjs` (add `scaffold()` and the CLI entry)
- Modify: `client-kit/tests/scaffold.test.mjs` (append the composition tests)
- Create: `client-kit/tests/fixtures/answers-greenfield.json`
- Create: `client-kit/tests/fixtures/answers-multilingual.json`
- Modify: `client-kit/tests/fixtures/mini-template/.wp-env.json`

**Interfaces:**
- Consumes: `themeJsonSettings` (Task 1), `renderManifest` (Task 2), `copyTemplate` / `assertNoTokens` / `validateTarget` / `validateSlug` / `resolveTemplate` (Task 3).
- Produces:
  - `scaffold(answers: object, opts: {target: string, template?: string, git?: boolean}): Promise<{target: string, files: string[]}>`
  - CLI: `node scaffold.mjs --answers <file> --target <dir> [--template <dir>] [--no-git]`

What `scaffold()` writes on top of the copied template:
- `theme.json` — from `themeJsonSettings(answers.brand)`, two-space JSON, trailing newline.
- `seed/manifest.php` — from `renderManifest(answers)`.
- `seed/media/<logo>` — copied from `answers.logo.sourcePath` when present.
- `.wp-env.json` — the Polylang plugin entry appended when `answers.languages.length > 1`.
- `docs/brief.md` — the questionnaire prose (the template ships the skeleton; this fills the body).
- `git init -b main`, `git add -A`, one commit, unless `git: false`.

- [ ] **Step 1: Write the answers fixtures**

Create `client-kit/tests/fixtures/answers-greenfield.json`:

```json
{
  "version": 1,
  "mode": "greenfield",
  "client": {
    "name": "Acme Roofing",
    "slug": "acme-roofing",
    "description": "Flat and pitched roofing for the North East."
  },
  "brief": {
    "does": "Flat and pitched roof installation, repair and inspection.",
    "audience": "Homeowners and small commercial landlords within 40 miles of Newcastle.",
    "tone": "Plain, reassuring, no jargon.",
    "sourceUrl": null
  },
  "brand": {
    "accent": "#B91C1C",
    "primary": "#1F2937",
    "foreground": "#1F2937",
    "font": { "family": "Inter", "weights": ["400", "700"] },
    "source": "chosen"
  },
  "languages": [
    { "slug": "en", "name": "English", "locale": "en_US", "flag": "gb", "default": true }
  ],
  "pages": [
    { "key": "home", "title": "Home", "frontPage": true },
    { "key": "about", "title": "About" },
    { "key": "services", "title": "Services" },
    { "key": "contact", "title": "Contact" }
  ],
  "nav": ["about", "services", "contact"],
  "logo": null,
  "plugin": { "version": "3.3.0" },
  "template": { "version": "3.3.0" }
}
```

Create `client-kit/tests/fixtures/answers-multilingual.json` — identical except:

```json
{
  "client": { "name": "Bergwerk Hotel", "slug": "bergwerk-hotel", "description": "A mountain hotel in the Zillertal." },
  "languages": [
    { "slug": "de", "name": "Deutsch", "locale": "de_DE", "flag": "de", "default": true },
    { "slug": "en", "name": "English", "locale": "en_US", "flag": "gb" }
  ]
}
```

(Write the file out in full — copy the greenfield fixture and replace those two keys, keeping `version`, `mode`, `brief`, `brand`, `pages`, `nav`, `logo`, `plugin` and `template`.)

Create `client-kit/tests/fixtures/mini-template/.wp-env.json`:

```json
{
  "core": "WordPress/WordPress#6.9",
  "phpVersion": "8.1",
  "themes": ["."],
  "plugins": [
    "https://github.com/Bergert-Digital/pediment/releases/download/v__PEDIMENT_PLUGIN_VERSION__/pediment-plugin.zip"
  ],
  "config": { "WP_DEBUG": true, "WP_DEBUG_LOG": true, "SCRIPT_DEBUG": true }
}
```

- [ ] **Step 2: Write the failing tests**

Append to `client-kit/tests/scaffold.test.mjs`:

```js
import { scaffold } from '../scripts/scaffold.mjs';
import { execFileSync } from 'node:child_process';

const greenfield = JSON.parse(
  await readFile(path.join(here, 'fixtures', 'answers-greenfield.json'), 'utf8'),
);
const multilingual = JSON.parse(
  await readFile(path.join(here, 'fixtures', 'answers-multilingual.json'), 'utf8'),
);

test('scaffold writes theme.json from the brand answers', async () => {
  const dir = await temp();
  const target = path.join(dir, 'acme-roofing');
  await scaffold(greenfield, { target, template: MINI, git: false });

  const theme = JSON.parse(await readFile(path.join(target, 'theme.json'), 'utf8'));
  assert.equal(theme.version, 2);
  const accent = theme.settings.color.palette.find((p) => p.slug === 'accent');
  assert.equal(accent.color, '#b91c1c');
  assert.equal(theme.settings.typography.fontFamilies[0].slug, 'body');

  await rm(dir, { recursive: true, force: true });
});

test('scaffold writes a manifest naming every chosen page', async () => {
  const dir = await temp();
  const target = path.join(dir, 'acme-roofing');
  await scaffold(greenfield, { target, template: MINI, git: false });

  const manifest = await readFile(path.join(target, 'seed', 'manifest.php'), 'utf8');
  for (const key of ['home', 'about', 'services', 'contact']) {
    assert.match(manifest, new RegExp(`'${key}' => array\\(`));
  }
  assert.match(manifest, /'pattern' => 'acme-roofing\/home'/);

  await rm(dir, { recursive: true, force: true });
});

test('scaffold leaves no surviving template tokens', async () => {
  const dir = await temp();
  const target = path.join(dir, 'acme-roofing');
  await scaffold(greenfield, { target, template: MINI, git: false });
  await assertNoTokens(target);
  await rm(dir, { recursive: true, force: true });
});

test('scaffold pins the plugin release in .wp-env.json and omits Polylang when monolingual', async () => {
  const dir = await temp();
  const target = path.join(dir, 'acme-roofing');
  await scaffold(greenfield, { target, template: MINI, git: false });

  const env = JSON.parse(await readFile(path.join(target, '.wp-env.json'), 'utf8'));
  assert.equal(env.plugins.length, 1);
  assert.match(env.plugins[0], /\/v3\.3\.0\/pediment-plugin\.zip$/);

  await rm(dir, { recursive: true, force: true });
});

test('scaffold adds Polylang to .wp-env.json for a multilingual site', async () => {
  const dir = await temp();
  const target = path.join(dir, 'bergwerk-hotel');
  await scaffold(multilingual, { target, template: MINI, git: false });

  const env = JSON.parse(await readFile(path.join(target, '.wp-env.json'), 'utf8'));
  assert.equal(env.plugins.length, 2);
  assert.match(env.plugins[1], /polylang/);

  const manifest = await readFile(path.join(target, 'seed', 'manifest.php'), 'utf8');
  assert.match(manifest, /'languages' => array\(/);

  await rm(dir, { recursive: true, force: true });
});

test('scaffold records the template and plugin versions in package.json', async () => {
  const dir = await temp();
  const target = path.join(dir, 'acme-roofing');
  await scaffold(greenfield, { target, template: MINI, git: false });

  const pkg = JSON.parse(await readFile(path.join(target, 'package.json'), 'utf8'));
  assert.equal(pkg.name, 'acme-roofing');
  assert.deepEqual(pkg.pediment, { template: '3.3.0', plugin: '3.3.0' });

  await rm(dir, { recursive: true, force: true });
});

test('scaffold writes docs/brief.md carrying the positioning answers', async () => {
  const dir = await temp();
  const target = path.join(dir, 'acme-roofing');
  await scaffold(greenfield, { target, template: MINI, git: false });

  const brief = await readFile(path.join(target, 'docs', 'brief.md'), 'utf8');
  assert.match(brief, /Acme Roofing/);
  assert.match(brief, /Homeowners and small commercial landlords/);
  assert.match(brief, /Plain, reassuring, no jargon/);
  assert.match(brief, /nothing reads this file programmatically/i);

  await rm(dir, { recursive: true, force: true });
});

test('scaffold initialises a git repo with one commit when git is enabled', async () => {
  const dir = await temp();
  const target = path.join(dir, 'acme-roofing');
  await scaffold(greenfield, { target, template: MINI, git: true });

  const log = execFileSync('git', ['-C', target, 'log', '--oneline'], { encoding: 'utf8' });
  assert.equal(log.trim().split('\n').length, 1);
  assert.match(log, /Acme Roofing/);
  const status = execFileSync('git', ['-C', target, 'status', '--porcelain'], { encoding: 'utf8' });
  assert.equal(status.trim(), '');

  await rm(dir, { recursive: true, force: true });
});

test('scaffold refuses a bad slug before writing anything', async () => {
  const dir = await temp();
  const target = path.join(dir, 'out');
  await assert.rejects(
    scaffold({ ...greenfield, client: { ...greenfield.client, slug: 'Acme Roofing' } },
      { target, template: MINI, git: false }),
    /slug/i,
  );
  assert.equal(existsSync(target), false);
  await rm(dir, { recursive: true, force: true });
});
```

Add `import { existsSync } from 'node:fs';` to the test file's imports.

- [ ] **Step 3: Run the tests to verify they fail**

Run: `npm run test:kit`
Expected: FAIL — `The requested module '../scripts/scaffold.mjs' does not provide an export named 'scaffold'`.

- [ ] **Step 4: Write the implementation**

Add these two imports **at the top of `client-kit/scripts/scaffold.mjs`**, beside the existing
`node:` imports — ES module imports are hoisted, so putting them mid-file works but reads as a
mistake:

```js
import { themeJsonSettings } from './brand.mjs';
import { renderManifest } from './manifest.mjs';
```

Then append the rest to `client-kit/scripts/scaffold.mjs`:

```js
const POLYLANG = 'https://downloads.wordpress.org/plugin/polylang.3.8.6.zip';

function briefMarkdown(answers) {
  const { client, brief, brand, languages, pages } = answers;
  return [
    `# ${client.name} — brief`,
    '',
    'Written by `/pediment:start`. This is the durable record of what the site is for.',
    'It is read by humans and by agents working in this repo; **nothing reads this file',
    'programmatically**, so editing it changes documentation, not behaviour.',
    '',
    '## What they do',
    '',
    brief.does,
    '',
    '## Who for',
    '',
    brief.audience,
    '',
    '## Tone',
    '',
    brief.tone,
    '',
    '## Languages',
    '',
    ...languages.map((l) => `- ${l.name} (\`${l.slug}\`, ${l.locale})${l.default ? ' — default' : ''}`),
    '',
    '## Pages at launch',
    '',
    ...pages.map((p) => `- ${p.title} (\`${p.key}\`)`),
    '',
    '## Brand',
    '',
    `- Accent: \`${brand.accent}\``,
    `- Primary: \`${brand.primary || 'Pediment default'}\``,
    `- Type: ${brand.font && brand.font.family ? brand.font.family : 'Pediment default'}`,
    `- Source: ${brand.source === 'chosen' ? 'chosen during /start' : brand.source}`,
    '',
  ].join('\n');
}

export async function scaffold(answers, opts) {
  const { target, template, git = true } = opts;

  validateSlug(answers.client.slug);
  validateTarget(target);

  const values = {
    __PEDIMENT_SLUG__: answers.client.slug,
    __PEDIMENT_NAME__: answers.client.name,
    __PEDIMENT_DESCRIPTION__: answers.client.description || `${answers.client.name} — built with Pediment.`,
    __PEDIMENT_PLUGIN_VERSION__: answers.plugin.version,
    __PEDIMENT_TEMPLATE_VERSION__: (answers.template && answers.template.version) || answers.plugin.version,
  };

  const srcDir = await resolveTemplate({
    template,
    version: answers.template && answers.template.version,
  });

  const files = await copyTemplate(srcDir, target, values, {
    keepPages: answers.pages.filter((p) => !p.postsPage).map((p) => p.key),
  });

  await writeFile(
    path.join(target, 'theme.json'),
    JSON.stringify(themeJsonSettings(answers.brand), null, 2) + '\n',
  );

  await mkdir(path.join(target, 'seed'), { recursive: true });
  await writeFile(path.join(target, 'seed', 'manifest.php'), renderManifest(answers));

  if (answers.logo && answers.logo.sourcePath) {
    await mkdir(path.join(target, 'seed', 'media'), { recursive: true });
    await cp(answers.logo.sourcePath, path.join(target, 'seed', 'media', answers.logo.file));
  }

  if (answers.languages.length > 1) {
    const envPath = path.join(target, '.wp-env.json');
    const env = JSON.parse(await readFile(envPath, 'utf8'));
    env.plugins = [...env.plugins, POLYLANG];
    await writeFile(envPath, JSON.stringify(env, null, 2) + '\n');
  }

  await mkdir(path.join(target, 'docs'), { recursive: true });
  await writeFile(path.join(target, 'docs', 'brief.md'), briefMarkdown(answers));

  await assertNoTokens(target);

  if (git) {
    const run = (...args) => execFileSync('git', ['-C', target, ...args], { stdio: 'inherit' });
    execFileSync('git', ['init', '-b', 'main', target], { stdio: 'inherit' });
    run('add', '-A');
    run('commit', '-m',
      `chore: scaffold ${answers.client.name} from the Pediment client template v${values.__PEDIMENT_TEMPLATE_VERSION__}`);
  }

  return { target, files };
}

function parseArgs(argv) {
  const out = { git: true };
  for (let i = 0; i < argv.length; i += 1) {
    const arg = argv[i];
    if (arg === '--no-git') out.git = false;
    else if (arg === '--answers') out.answers = argv[++i];
    else if (arg === '--target') out.target = argv[++i];
    else if (arg === '--template') out.template = argv[++i];
    else throw new Error(`Unknown argument: ${arg}`);
  }
  if (!out.answers || !out.target) {
    throw new Error('Usage: scaffold.mjs --answers <file> --target <dir> [--template <dir>] [--no-git]');
  }
  return out;
}

if (process.argv[1] && process.argv[1].endsWith('scaffold.mjs')) {
  const args = parseArgs(process.argv.slice(2));
  const answers = JSON.parse(await readFile(args.answers, 'utf8'));
  const result = await scaffold(answers, {
    target: path.resolve(args.target),
    template: args.template,
    git: args.git,
  });
  console.log(`Scaffolded ${answers.client.name} into ${result.target} (${result.files.length} template files).`);
}
```

- [ ] **Step 5: Run the tests to verify they pass**

Run: `npm run test:kit`
Expected: PASS — the 9 new composition tests, and nothing earlier regressed.

- [ ] **Step 6: Commit**

```bash
git add client-kit/scripts/scaffold.mjs client-kit/tests/scaffold.test.mjs client-kit/tests/fixtures
git commit -m "feat(kit): compose theme.json, the manifest and the brief when scaffolding"
```

---

### Task 5: `client-template/` — the real client theme template

**Files:**
- Create: `client-template/style.css`, `theme.json`, `templates/index.html`
- Create: `client-template/patterns/{home,about,services,contact}.php`
- Create: `client-template/seed/media/.gitkeep`
- Create: `client-template/docs/brief.md` (placeholder body; `scaffold()` overwrites)
- Create: `client-template/.wp-env.json`, `package.json`, `.gitignore`
- Create: `client-template/.github/workflows/ci.yml`, `release.yml`
- Create: `client-template/AGENTS.md`, `README.md`
- Modify: `client-kit/tests/scaffold.test.mjs` (add the real-template test)

**Interfaces:**
- Consumes: `TOKENS` (Task 3), `scaffold()` (Task 4).
- Produces: the directory `client-template/`, consumed by Task 9's integration job and Task 13's release asset.

The patterns below use only blocks the plugin ships, with attribute names verified against their
`block.json`: `pediment/hero` (`variant` — one of `default|centered|media-bg|stat-card` —
`headline`, `subheadline`), `pediment/section-head` (`headline`), `pediment/prose` (no attributes;
an InnerBlocks container), and `pediment/cta` (`title`, `body`, `primaryText`, `primaryUrl` — **not**
`headline`/`buttonText`). `pediment/footer` is a plugin-registered pattern with exactly that slug.

- [ ] **Step 1: Write the theme shell**

`client-template/style.css`:

```css
/*
Theme Name: __PEDIMENT_NAME__
Description: __PEDIMENT_DESCRIPTION__
Version: 0.1.0
Requires at least: 6.9
Tested up to: 6.9
Requires PHP: 8.1
Text Domain: __PEDIMENT_SLUG__
*/

/* Client-specific CSS goes here. The design system ships in the Pediment plugin. */
```

`client-template/theme.json` (a valid placeholder — `scaffold()` overwrites it with the brand):

```json
{
  "$schema": "https://schemas.wp.org/trunk/theme.json",
  "version": 2,
  "settings": {}
}
```

`client-template/templates/index.html` — required for `wp_is_block_theme()` to return true:

```html
<!-- wp:template-part {"slug":"header","tagName":"header"} /-->

<!-- wp:group {"tagName":"main","layout":{"type":"constrained"}} -->
<main class="wp-block-group"><!-- wp:post-content /--></main>
<!-- /wp:group -->

<!-- wp:pattern {"slug":"pediment/footer"} /-->
```

`client-template/.gitignore`:

```
node_modules/
.wp-env.override.json
*.log
.DS_Store
```

- [ ] **Step 2: Write the four starter patterns**

Each is a real, seedable pattern. `client-template/patterns/home.php`:

```php
<?php
/**
 * Title: Home
 * Slug: __PEDIMENT_SLUG__/home
 * Categories: pediment
 * Description: Landing page for __PEDIMENT_NAME__.
 * Inserter: no
 */
// phpcs:ignoreFile -- block pattern content
?>
<!-- wp:pediment/hero {"variant":"centered","headline":"__PEDIMENT_NAME__","subheadline":"__PEDIMENT_DESCRIPTION__"} /-->

<!-- wp:pediment/section-head {"headline":"What we do"} /-->

<!-- wp:pediment/prose -->
<!-- wp:paragraph --><p>Replace this with a short description of the service, in the client's own words. Build the rest of the page in the editor, then run <code>npm run adopt -- home</code> to bring it back into this file.</p><!-- /wp:paragraph -->
<!-- /wp:pediment/prose -->

<!-- wp:pediment/cta {"title":"Get in touch","body":"Tell us what you need and we will come back to you within one working day.","primaryText":"Contact us","primaryUrl":"/contact/"} /-->
```

`client-template/patterns/about.php`:

```php
<?php
/**
 * Title: About
 * Slug: __PEDIMENT_SLUG__/about
 * Categories: pediment
 * Description: About page for __PEDIMENT_NAME__.
 * Inserter: no
 */
// phpcs:ignoreFile -- block pattern content
?>
<!-- wp:pediment/hero {"variant":"centered","headline":"About","subheadline":"Who we are and how we work."} /-->

<!-- wp:pediment/prose -->
<!-- wp:paragraph --><p>Replace this with the story of __PEDIMENT_NAME__ — who it serves, how long it has been going, and what makes it worth choosing.</p><!-- /wp:paragraph -->
<!-- /wp:pediment/prose -->
```

`client-template/patterns/services.php`:

```php
<?php
/**
 * Title: Services
 * Slug: __PEDIMENT_SLUG__/services
 * Categories: pediment
 * Description: Services page for __PEDIMENT_NAME__.
 * Inserter: no
 */
// phpcs:ignoreFile -- block pattern content
?>
<!-- wp:pediment/hero {"variant":"centered","headline":"Services","subheadline":"What we can do for you."} /-->

<!-- wp:pediment/prose -->
<!-- wp:paragraph --><p>Replace this with the service list. One section head per service reads better than a bulleted list.</p><!-- /wp:paragraph -->
<!-- /wp:pediment/prose -->
```

`client-template/patterns/contact.php`:

```php
<?php
/**
 * Title: Contact
 * Slug: __PEDIMENT_SLUG__/contact
 * Categories: pediment
 * Description: Contact page for __PEDIMENT_NAME__.
 * Inserter: no
 */
// phpcs:ignoreFile -- block pattern content
?>
<!-- wp:pediment/hero {"variant":"centered","headline":"Contact","subheadline":"Tell us what you need."} /-->

<!-- wp:pediment/prose -->
<!-- wp:paragraph --><p>Add a <code>pediment/form</code> block here once the destination is configured in Settings → Pediment → Forms.</p><!-- /wp:paragraph -->
<!-- /wp:pediment/prose -->
```

- [ ] **Step 3: Write the env and scripts**

`client-template/.wp-env.json`:

```json
{
  "core": "WordPress/WordPress#6.9",
  "phpVersion": "8.1",
  "themes": ["."],
  "plugins": [
    "https://github.com/Bergert-Digital/pediment/releases/download/v__PEDIMENT_PLUGIN_VERSION__/pediment-plugin.zip"
  ],
  "config": {
    "WP_DEBUG": true,
    "WP_DEBUG_LOG": true,
    "SCRIPT_DEBUG": true
  }
}
```

`client-template/package.json`:

```json
{
  "name": "__PEDIMENT_SLUG__",
  "version": "0.1.0",
  "private": true,
  "description": "__PEDIMENT_DESCRIPTION__",
  "pediment": {
    "template": "__PEDIMENT_TEMPLATE_VERSION__",
    "plugin": "__PEDIMENT_PLUGIN_VERSION__"
  },
  "scripts": {
    "env:start": "wp-env start && wp-env run cli wp theme activate __PEDIMENT_SLUG__ && wp-env run cli wp plugin activate pediment",
    "env:stop": "wp-env stop",
    "languages": "wp-env run cli wp pediment languages",
    "seed:plan": "wp-env run cli wp pediment seed --dry-run",
    "seed": "wp-env run cli wp pediment seed",
    "adopt": "wp-env run cli wp pediment adopt"
  },
  "devDependencies": {
    "@wordpress/env": "^10.0.0"
  }
}
```

`client-template/.github/workflows/ci.yml`:

```yaml
name: CI

on:
  pull_request:
  push:
    branches: [main]

jobs:
  seed:
    uses: Bergert-Digital/pediment/.github/workflows/client-theme.yml@main
```

`client-template/.github/workflows/release.yml`:

```yaml
name: Release

on:
  push:
    tags: ["v*"]

permissions:
  contents: write

jobs:
  release:
    uses: Bergert-Digital/pediment/.github/workflows/client-release.yml@main
    with:
      tag: ${{ github.ref_name }}
```

- [ ] **Step 4: Write the docs**

`client-template/docs/brief.md` (overwritten by `scaffold()`; present so the directory exists and a manual copy of the template is still coherent):

```markdown
# __PEDIMENT_NAME__ — brief

Written by `/pediment:start`. Nothing reads this file programmatically.
```

`client-template/README.md`:

```markdown
# __PEDIMENT_NAME__

A standalone WordPress block theme built on [Pediment](https://github.com/Bergert-Digital/pediment).
The design system, blocks, templates and seeding engine ship in the **Pediment plugin**; this repo
holds only what is specific to __PEDIMENT_NAME__: brand tokens, page content, and the seed manifest.

## Local development

```bash
npm install
npm run env:start     # http://localhost:8888
npm run seed:plan     # see what a seed would change
npm run seed
```

## Changing content

Structure — which pages exist, their slugs, nesting, nav membership — lives in
`seed/manifest.php`. Content lives in `patterns/*.php`. Edit either, then run `npm run seed`.

A page a client has edited in the editor is protected: the seeder compares a content hash and
leaves edited pages alone, reconciling structure only. To bring a client's live edit back into
git, run `npm run adopt -- <page-key>`.

## Deploying

Push a `v*` tag. The release workflow builds `__PEDIMENT_SLUG__.zip` with the version header
stamped, and attaches it to the GitHub release. Install it via Appearance → Themes → Add New →
Upload, then re-seed from Settings → Pediment Theme → Seeding.

The Pediment plugin updates itself through wp-admin; this theme does not, by design.
```

`client-template/AGENTS.md`:

```markdown
# AGENTS.md — __PEDIMENT_NAME__

A standalone Pediment client theme. The engine lives in the Pediment plugin, not here.

## Hard rules

- **Never write pages with `wp post create` or `wp post update`.** The seeder owns identity
  (`_pediment_seed_key`) and the content-arbitration hashes; a hand-written post bypasses both.
- Structure goes in `seed/manifest.php`; content goes in `patterns/*.php`. Run
  `npm run seed:plan` before `npm run seed`, always.
- To capture an edit made in the block editor, run `npm run adopt -- <key>` — never copy markup
  out of the database by hand.
- Never edit plugin files to get client-specific behaviour. Use theme.json, patterns, template
  overrides in `templates/`, and the plugin's filters.
- Colours come from `theme.json` presets. Use `var(--wp--preset--color--…)`, never literals.
- Languages are declared in the manifest and configured with `npm run languages` **before**
  seeding, never after.

## Verification

```bash
npm run env:start
npm run seed:plan
npm run seed
```

Then load http://localhost:8888 and check the pages you changed at 375px, 768px and 1440px.
```

- [ ] **Step 5: Add the real-template test**

Append to `client-kit/tests/scaffold.test.mjs`:

```js
const REAL_TEMPLATE = path.resolve(here, '..', '..', 'client-template');

test('the real client-template scaffolds cleanly and prunes unchosen patterns', async () => {
  const dir = await temp();
  const target = path.join(dir, 'acme-roofing');
  await scaffold(
    { ...greenfield, pages: greenfield.pages.filter((p) => p.key !== 'services'), nav: ['about', 'contact'] },
    { target, template: REAL_TEMPLATE, git: false },
  );

  await assertNoTokens(target);
  assert.ok(existsSync(path.join(target, 'patterns', 'home.php')));
  assert.equal(existsSync(path.join(target, 'patterns', 'services.php')), false);
  assert.ok(existsSync(path.join(target, 'templates', 'index.html')));

  const css = await readFile(path.join(target, 'style.css'), 'utf8');
  assert.match(css, /Text Domain: acme-roofing/);

  const home = await readFile(path.join(target, 'patterns', 'home.php'), 'utf8');
  assert.match(home, /Slug: acme-roofing\/home/);

  await rm(dir, { recursive: true, force: true });
});
```

- [ ] **Step 6: Run the tests and the colour lint**

Run: `npm run test:kit && npm run lint:colors`
Expected: PASS. `lint:colors` must not flag the template — the patterns carry no colour literals.

- [ ] **Step 7: Commit**

```bash
git add client-template
git add client-kit/tests/scaffold.test.mjs
git commit -m "feat(template): add the standalone client theme template"
```

---

### Task 6: `stamp-theme-version.mjs` — the version header, with a test this time

**Files:**
- Create: `tools/stamp-theme-version.mjs`
- Create: `tools/stamp-theme-version.test.mjs`
- Modify: `package.json` (extend `test:kit` to cover `tools/`)

**Interfaces:**
- Consumes: nothing.
- Produces:
  - `stampStyleCss(css: string, version: string): string`
  - `stampPackageJson(json: string, version: string): string`
  - CLI: `node tools/stamp-theme-version.mjs <themeDir> <version>`

This exists because the released artifact's version header failing to move — so production never sees the update — was found and fixed independently in all four repos (`9c9af20`, `22f0024`, `432faf6`, workation #22). Inline `sed` in a workflow cannot be unit-tested; this can.

- [ ] **Step 1: Write the failing tests**

Create `tools/stamp-theme-version.test.mjs`:

```js
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { stampStyleCss, stampPackageJson } from './stamp-theme-version.mjs';

const css = `/*
Theme Name: Acme Roofing
Description: Roofing.
Version: 0.1.0
Text Domain: acme-roofing
*/
`;

test('stampStyleCss rewrites the Version header', () => {
  const out = stampStyleCss(css, '1.4.0');
  assert.match(out, /^Version: 1\.4\.0$/m);
  assert.doesNotMatch(out, /0\.1\.0/);
});

test('stampStyleCss leaves other headers untouched', () => {
  const out = stampStyleCss(css, '1.4.0');
  assert.match(out, /^Theme Name: Acme Roofing$/m);
  assert.match(out, /^Text Domain: acme-roofing$/m);
});

test('stampStyleCss throws when there is no Version header to move', () => {
  assert.throws(() => stampStyleCss('/*\nTheme Name: X\n*/\n', '1.4.0'), /Version header/);
});

test('stampStyleCss is idempotent', () => {
  assert.equal(stampStyleCss(stampStyleCss(css, '1.4.0'), '1.4.0'), stampStyleCss(css, '1.4.0'));
});

test('stampPackageJson rewrites version and preserves everything else', () => {
  const out = stampPackageJson('{\n  "name": "acme-roofing",\n  "version": "0.1.0"\n}\n', '1.4.0');
  const parsed = JSON.parse(out);
  assert.equal(parsed.version, '1.4.0');
  assert.equal(parsed.name, 'acme-roofing');
  assert.match(out, /\n$/);
});
```

- [ ] **Step 2: Point `test:kit` at both directories and run it**

In the root `package.json`:

```json
"test:kit": "node --test client-kit/tests/ tools/"
```

Run: `npm run test:kit`
Expected: FAIL — `Cannot find module './stamp-theme-version.mjs'`.

- [ ] **Step 3: Write the implementation**

Create `tools/stamp-theme-version.mjs`:

```js
#!/usr/bin/env node
/**
 * Stamp a client theme's released version into style.css and package.json.
 *
 * The released artifact's version header failing to move — so production never
 * sees the update — was found and fixed independently in all four Pediment
 * repos (9c9af20, 22f0024, 432faf6, workation #22). Inline sed in a workflow
 * cannot carry a test; this can, and does.
 */

import { readFile, writeFile } from 'node:fs/promises';
import path from 'node:path';

export function stampStyleCss(css, version) {
  if (!/^Version:.*$/m.test(css)) {
    throw new Error('style.css has no Version header to stamp. Add "Version: 0.1.0" to the theme header block.');
  }
  return css.replace(/^Version:.*$/m, `Version: ${version}`);
}

export function stampPackageJson(json, version) {
  const parsed = JSON.parse(json);
  parsed.version = version;
  return JSON.stringify(parsed, null, 2) + '\n';
}

if (process.argv[1] && process.argv[1].endsWith('stamp-theme-version.mjs')) {
  const [dir, raw] = process.argv.slice(2);
  if (!dir || !raw) {
    console.error('Usage: stamp-theme-version.mjs <themeDir> <version|vX.Y.Z>');
    process.exit(1);
  }
  const version = raw.replace(/^v/, '');

  const cssPath = path.join(dir, 'style.css');
  await writeFile(cssPath, stampStyleCss(await readFile(cssPath, 'utf8'), version));

  const pkgPath = path.join(dir, 'package.json');
  await writeFile(pkgPath, stampPackageJson(await readFile(pkgPath, 'utf8'), version));

  console.log(`Stamped ${version} into ${cssPath} and ${pkgPath}`);
}
```

- [ ] **Step 4: Run the tests to verify they pass**

Run: `npm run test:kit`
Expected: PASS — the 6 new tests in `tools/`, and nothing earlier regressed.

- [ ] **Step 5: Commit**

```bash
git add tools/stamp-theme-version.mjs tools/stamp-theme-version.test.mjs package.json
git commit -m "feat(release): stamp client theme version headers with a tested script"
```

---

### Task 7: The `seed-check` composite action and the reusable client CI workflow

**Files:**
- Create: `.github/actions/seed-check/action.yml`
- Create: `.github/workflows/client-theme.yml`

**Interfaces:**
- Consumes: nothing from earlier tasks at runtime.
- Produces:
  - Composite action `./.github/actions/seed-check` with inputs `theme-path` (default `.`) and `plugin-source` (default `''`).
  - Reusable workflow `client-theme.yml`, callable as `Bergert-Digital/pediment/.github/workflows/client-theme.yml@main`, with no required inputs.

The composite action holds the real steps so this repo's own integration job (Task 9) executes the same code every client repo depends on. Without that, the workflow every client site relies on would be the one thing never exercised here.

- [ ] **Step 1: Write the composite action**

Create `.github/actions/seed-check/action.yml`:

```yaml
name: Seed check
description: Boot wp-env for a Pediment client theme, seed it, and assert the front page renders.

inputs:
  theme-path:
    description: Path to the client theme directory (containing .wp-env.json).
    required: false
    default: "."
  plugin-source:
    description: >-
      Optional path to a locally built plugin directory (one whose basename is `pediment`).
      When set, it replaces the pinned release URL in .wp-env.json so a PR tests its own
      plugin build. A directory, not a zip — wp-env's local-path support for directories is
      the well-trodden path, and the monorepo's own .wp-env.json already relies on it.
    required: false
    default: ""

runs:
  using: composite
  steps:
    - name: Report the pinned Pediment versions
      shell: bash
      working-directory: ${{ inputs.theme-path }}
      run: |
        node -e "const p=require('./package.json').pediment||{};console.log('template:',p.template||'unknown','plugin:',p.plugin||'unknown')"

    - name: Point wp-env at the locally built plugin
      if: inputs.plugin-source != ''
      shell: bash
      working-directory: ${{ inputs.theme-path }}
      env:
        PLUGIN_SOURCE: ${{ inputs.plugin-source }}
      run: |
        node -e "
          const fs = require('node:fs');
          const env = JSON.parse(fs.readFileSync('.wp-env.json', 'utf8'));
          env.plugins = env.plugins.map((p) => /pediment-plugin\.zip/.test(p) ? process.env.PLUGIN_SOURCE : p);
          fs.writeFileSync('.wp-env.json', JSON.stringify(env, null, 2) + '\n');
          console.log(JSON.stringify(env.plugins, null, 2));
        "

    - name: Install and boot
      shell: bash
      working-directory: ${{ inputs.theme-path }}
      run: |
        npm install
        npm run env:start

    - name: Configure languages
      shell: bash
      working-directory: ${{ inputs.theme-path }}
      run: |
        if grep -q "'languages'" seed/manifest.php; then
          npm run languages
        else
          echo "Monolingual manifest — skipping wp pediment languages."
        fi

    - name: Show the seed plan
      shell: bash
      working-directory: ${{ inputs.theme-path }}
      run: npm run seed:plan

    - name: Seed
      shell: bash
      working-directory: ${{ inputs.theme-path }}
      run: npm run seed

    - name: Assert the front page renders
      shell: bash
      working-directory: ${{ inputs.theme-path }}
      run: |
        set -euo pipefail
        status=$(curl -s -o /tmp/front.html -w '%{http_code}' http://localhost:8888/)
        echo "HTTP $status"
        test "$status" = "200"
        grep -q "<body" /tmp/front.html
        if grep -qi "There has been a critical error" /tmp/front.html; then
          echo "::error::The front page rendered a WordPress critical error."
          exit 1
        fi

    - name: Re-seed and assert the plan is now empty of content writes
      shell: bash
      working-directory: ${{ inputs.theme-path }}
      run: |
        set -euo pipefail
        npm run seed:plan | tee /tmp/replan.txt
        if grep -qiE '^\s*(create|update)\b' /tmp/replan.txt; then
          echo "::error::A second dry run still wants to write — the seeder is not converging."
          exit 1
        fi

    - name: Stop wp-env
      if: always()
      shell: bash
      working-directory: ${{ inputs.theme-path }}
      run: npm run env:stop
```

- [ ] **Step 2: Write the reusable workflow**

Create `.github/workflows/client-theme.yml`:

```yaml
name: Client theme CI

# Called by every scaffolded Pediment client repo:
#
#   jobs:
#     seed:
#       uses: Bergert-Digital/pediment/.github/workflows/client-theme.yml@main
#
# One copy of these checks lives here so a fix reaches every client site without
# anyone editing N repos — the copy-paste pattern the dev-flow review diagnosed.

on:
  workflow_call:
    inputs:
      theme-path:
        description: Path to the client theme directory.
        required: false
        type: string
        default: "."

jobs:
  seed:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4

      - uses: actions/setup-node@v4
        with:
          node-version: "20"

      - uses: Bergert-Digital/pediment/.github/actions/seed-check@main
        with:
          theme-path: ${{ inputs.theme-path }}
```

- [ ] **Step 3: Verify the YAML parses**

Run:

```bash
node -e "const s=require('node:fs').readFileSync('.github/actions/seed-check/action.yml','utf8'); if(/\t/.test(s)) throw new Error('tabs in YAML'); console.log('no tabs, '+s.split('\n').length+' lines')"
node -e "const s=require('node:fs').readFileSync('.github/workflows/client-theme.yml','utf8'); if(/\t/.test(s)) throw new Error('tabs in YAML'); console.log('no tabs, '+s.split('\n').length+' lines')"
```

Expected: both print a line count. Real execution is proven by Task 9 — note that in the commit body.

- [ ] **Step 4: Commit**

```bash
git add .github/actions/seed-check/action.yml .github/workflows/client-theme.yml
git commit -m "ci(client): add the reusable client-theme seed check

The composite action holds the real steps so the monorepo's own integration
job runs the same code every client repo depends on."
```

---

### Task 8: The reusable client release workflow

**Files:**
- Create: `.github/workflows/client-release.yml`

**Interfaces:**
- Consumes: `tools/stamp-theme-version.mjs` (Task 6), fetched from this repo at run time.
- Produces: reusable workflow `client-release.yml`, input `tag` (required, e.g. `v1.4.0`), output: `<slug>.zip` attached to the tag's release.

- [ ] **Step 1: Write the workflow**

Create `.github/workflows/client-release.yml`:

```yaml
name: Client theme release

# Called by a scaffolded client repo on a pushed v* tag:
#
#   jobs:
#     release:
#       uses: Bergert-Digital/pediment/.github/workflows/client-release.yml@main
#       with:
#         tag: ${{ github.ref_name }}

on:
  workflow_call:
    inputs:
      tag:
        description: The tag being released (e.g. v1.4.0).
        required: true
        type: string

permissions:
  contents: write

jobs:
  zip:
    runs-on: ubuntu-latest
    steps:
      - name: Check out the client theme
        uses: actions/checkout@v4
        with:
          ref: ${{ inputs.tag }}

      - name: Check out Pediment's tooling
        uses: actions/checkout@v4
        with:
          repository: Bergert-Digital/pediment
          ref: main
          path: .pediment-tools
          sparse-checkout: tools

      - uses: actions/setup-node@v4
        with:
          node-version: "20"

      - name: Resolve the theme slug from style.css
        id: slug
        run: |
          set -euo pipefail
          SLUG=$(node -e "
            const css = require('node:fs').readFileSync('style.css', 'utf8');
            const m = css.match(/^Text Domain:\s*(.+)$/m);
            if (!m) { console.error('style.css has no Text Domain header'); process.exit(1); }
            process.stdout.write(m[1].trim());
          ")
          echo "slug=$SLUG" >> "$GITHUB_OUTPUT"
          echo "Theme slug: $SLUG"

      - name: Stamp the version header
        run: node .pediment-tools/tools/stamp-theme-version.mjs . "${{ inputs.tag }}"

      - name: Verify the header actually moved
        run: |
          set -euo pipefail
          VERSION="${{ inputs.tag }}"
          VERSION="${VERSION#v}"
          grep -q "^Version: ${VERSION}$" style.css
          echo "style.css Version header is ${VERSION}"

      - name: Stage and zip
        env:
          SLUG: ${{ steps.slug.outputs.slug }}
        run: |
          set -euo pipefail
          mkdir -p "stage/${SLUG}"
          rsync -a \
            --exclude '.git' \
            --exclude '.github' \
            --exclude '.pediment-tools' \
            --exclude 'node_modules' \
            --exclude 'stage' \
            --exclude '.wp-env.json' \
            --exclude '.wp-env.override.json' \
            --exclude 'package-lock.json' \
            ./ "stage/${SLUG}/"
          cd stage && zip -rq "../${SLUG}.zip" "${SLUG}"
          unzip -l "../${SLUG}.zip" | tail -5

      - name: Attach the zip to the release
        env:
          GH_TOKEN: ${{ secrets.GITHUB_TOKEN }}
          SLUG: ${{ steps.slug.outputs.slug }}
        run: gh release upload "${{ inputs.tag }}" "${SLUG}.zip" --clobber
```

- [ ] **Step 2: Verify the YAML has no tabs and the slug extraction works**

Run:

```bash
node -e "const s=require('node:fs').readFileSync('.github/workflows/client-release.yml','utf8'); if(/\t/.test(s)) throw new Error('tabs in YAML'); console.log('ok')"
node -e "
  const css = require('node:fs').readFileSync('client-template/style.css','utf8').replace('__PEDIMENT_SLUG__','acme-roofing');
  const m = css.match(/^Text Domain:\s*(.+)\$/m);
  console.log('slug:', m[1].trim());
"
```

Expected: `ok` then `slug: acme-roofing`.

- [ ] **Step 3: Commit**

```bash
git add .github/workflows/client-release.yml
git commit -m "ci(client): add the reusable client theme release workflow"
```

---

### Task 9: The scaffold-and-seed integration job

**Files:**
- Create: `client-kit/tests/fixtures/answers-ci.json`
- Modify: `.github/workflows/ci.yml`

**Interfaces:**
- Consumes: `scaffold.mjs` (Task 4), `client-template/` (Task 5), `.github/actions/seed-check` (Task 7).
- Produces: the `scaffold` job — the one gate that proves the template, the scaffolder, the composite action and the plugin work together.

Monolingual and four pages, deliberately: the plugin's own e2e suite already covers multilingual seeding, and this job pays for a full wp-env boot.

- [ ] **Step 1: Write the CI answers fixture**

Create `client-kit/tests/fixtures/answers-ci.json`:

```json
{
  "version": 1,
  "mode": "greenfield",
  "client": {
    "name": "Pediment CI Client",
    "slug": "pediment-ci-client",
    "description": "Scaffolded in CI to prove the template still seeds."
  },
  "brief": {
    "does": "Nothing — this site exists to prove the scaffolder works.",
    "audience": "The Pediment CI job.",
    "tone": "Neutral.",
    "sourceUrl": null
  },
  "brand": {
    "accent": "#0E7490",
    "primary": "#0A1B33",
    "foreground": "#0B1B33",
    "font": { "family": "Inter", "weights": ["400", "700"] },
    "source": "chosen"
  },
  "languages": [
    { "slug": "en", "name": "English", "locale": "en_US", "flag": "gb", "default": true }
  ],
  "pages": [
    { "key": "home", "title": "Home", "frontPage": true },
    { "key": "about", "title": "About" },
    { "key": "services", "title": "Services" },
    { "key": "contact", "title": "Contact" }
  ],
  "nav": ["about", "services", "contact"],
  "logo": null,
  "plugin": { "version": "3.3.0" },
  "template": { "version": "3.3.0" }
}
```

- [ ] **Step 2: Add the unit-test step to the existing lint job**

In `.github/workflows/ci.yml`, in the `lint-blocks` job, after `- run: npm run lint:js`, add:

```yaml
      - run: npm run test:kit
```

- [ ] **Step 3: Add the scaffold job**

Append to `.github/workflows/ci.yml`:

```yaml
  scaffold:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4

      - uses: actions/setup-node@v4
        with:
          node-version: "20"
          cache: npm
          cache-dependency-path: plugin/package-lock.json

      - uses: shivammathur/setup-php@v2
        with:
          php-version: "8.1"
          tools: composer

      - name: Stage the plugin this PR would ship
        run: |
          set -euo pipefail
          composer install --no-dev --optimize-autoloader --prefer-dist --no-progress -d plugin
          cd plugin && npm ci && npm run build && cd ..
          mkdir -p stage-plugin/pediment
          rsync -a --exclude-from=plugin/.distignore plugin/ stage-plugin/pediment/
          ls stage-plugin/pediment/plugin.php

      - name: Scaffold a client site from the template
        run: |
          set -euo pipefail
          git config --global user.email "ci@pediment.invalid"
          git config --global user.name "Pediment CI"
          node client-kit/scripts/scaffold.mjs \
            --answers client-kit/tests/fixtures/answers-ci.json \
            --target "$RUNNER_TEMP/pediment-ci-client" \
            --template client-template

      - name: Seed it and assert it renders
        uses: ./.github/actions/seed-check
        with:
          theme-path: ${{ runner.temp }}/pediment-ci-client
          plugin-source: ${{ github.workspace }}/stage-plugin/pediment

      - name: Upload the scaffolded site on failure
        if: failure()
        uses: actions/upload-artifact@v4
        with:
          name: scaffolded-client
          path: ${{ runner.temp }}/pediment-ci-client
```

- [ ] **Step 4: Rehearse the job locally, end to end**

This is the step that actually proves the task. Run:

```bash
rm -rf /tmp/pediment-ci-client
node client-kit/scripts/scaffold.mjs --answers client-kit/tests/fixtures/answers-ci.json --target /tmp/pediment-ci-client --template client-template
cd /tmp/pediment-ci-client && npm install && npm run env:start
```

Then, still in `/tmp/pediment-ci-client`:

```bash
npm run seed:plan
npm run seed
curl -s -o /tmp/front.html -w '%{http_code}\n' http://localhost:8888/
grep -c "wp-block" /tmp/front.html
npm run seed:plan
```

Expected: the plan lists four page creations, the seed applies them, `curl` returns `200`, the front page contains block markup, and the **second** plan lists no creates or updates. Then:

```bash
cd /tmp/pediment-ci-client && npm run env:stop && cd - && rm -rf /tmp/pediment-ci-client
```

**Required, not optional:** the scaffolded `.wp-env.json` points at the released plugin zip for
`answers-ci.json`'s pinned version, and **no released version contains the seeder** — the latest
release is v3.0.0, which predates step 3. Before `env:start`, edit the scaffolded `.wp-env.json` so
its plugin entry is the absolute path of this workspace's `plugin/` directory:

```bash
node -e "
  const fs = require('node:fs');
  const p = '/tmp/pediment-ci-client/.wp-env.json';
  const env = JSON.parse(fs.readFileSync(p, 'utf8'));
  env.plugins = ['/Users/jonas/conductor/workspaces/pediment/charlottetown/plugin'];
  fs.writeFileSync(p, JSON.stringify(env, null, 2) + '\n');
"
```

This is the same substitution the composite action's `plugin-source` input performs in CI. Without
it `wp pediment seed` will not exist and the rehearsal fails at the plan step.

- [ ] **Step 5: Commit**

```bash
git add .github/workflows/ci.yml client-kit/tests/fixtures/answers-ci.json
git commit -m "ci: scaffold and seed a client site on every run"
```

---

### Task 10: The `client-kit` plugin bundle and its structural test

**Files:**
- Create: `client-kit/.claude-plugin/plugin.json`
- Create: `client-kit/.claude-plugin/marketplace.json`
- Create: `client-kit/README.md`
- Create: `client-kit/tests/kit.test.mjs`

**Interfaces:**
- Consumes: `client-kit/scripts/*.mjs` (Tasks 1–4).
- Produces: an installable Claude Code plugin directory. Skill files land in Tasks 11 and 12; this task creates the manifest and the test that keeps the two in sync.

- [ ] **Step 1: Write the failing test**

Create `client-kit/tests/kit.test.mjs`:

```js
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFile, readdir } from 'node:fs/promises';
import { existsSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const kit = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');

test('plugin.json declares a name, description and version', async () => {
  const manifest = JSON.parse(await readFile(path.join(kit, '.claude-plugin', 'plugin.json'), 'utf8'));
  assert.equal(manifest.name, 'pediment');
  assert.ok(manifest.description.length > 20);
  assert.match(manifest.version, /^\d+\.\d+\.\d+$/);
});

test('marketplace.json points at this directory and agrees with plugin.json', async () => {
  const market = JSON.parse(await readFile(path.join(kit, '.claude-plugin', 'marketplace.json'), 'utf8'));
  const manifest = JSON.parse(await readFile(path.join(kit, '.claude-plugin', 'plugin.json'), 'utf8'));
  assert.equal(market.plugins.length, 1);
  assert.equal(market.plugins[0].name, manifest.name);
  assert.equal(market.plugins[0].version, manifest.version);
  assert.equal(market.plugins[0].source, './');
});

test('every skill has YAML frontmatter with a name and description', async () => {
  const skillsDir = path.join(kit, 'skills');
  const names = await readdir(skillsDir);
  assert.ok(names.length > 0, 'expected at least one skill');

  for (const name of names) {
    const file = path.join(skillsDir, name, 'SKILL.md');
    assert.ok(existsSync(file), `${name} has no SKILL.md`);
    const body = await readFile(file, 'utf8');
    const front = body.match(/^---\n([\s\S]*?)\n---\n/);
    assert.ok(front, `${name}: SKILL.md has no frontmatter block`);
    assert.match(front[1], /^name:\s*\S+/m, `${name}: frontmatter has no name`);
    assert.match(front[1], /^description:\s*\S+/m, `${name}: frontmatter has no description`);
    assert.match(front[1], new RegExp(`^name:\\s*${name}\\s*$`, 'm'), `${name}: frontmatter name must match its directory`);
  }
});

test('every script a skill references actually exists', async () => {
  const skillsDir = path.join(kit, 'skills');
  for (const name of await readdir(skillsDir)) {
    const body = await readFile(path.join(skillsDir, name, 'SKILL.md'), 'utf8');
    for (const [, rel] of body.matchAll(/\b(scripts\/[a-z0-9-]+\.mjs)\b/g)) {
      assert.ok(existsSync(path.join(kit, rel)), `${name}: references missing ${rel}`);
    }
    for (const [, rel] of body.matchAll(/\b(shared\/[a-z0-9-]+\.md)\b/g)) {
      assert.ok(existsSync(path.join(kit, rel)), `${name}: references missing ${rel}`);
    }
  }
});
```

- [ ] **Step 2: Run it to verify it fails**

Run: `npm run test:kit`
Expected: FAIL — `ENOENT ... client-kit/.claude-plugin/plugin.json`.

- [ ] **Step 3: Write the manifests**

Create `client-kit/.claude-plugin/plugin.json`:

```json
{
  "name": "pediment",
  "description": "Create and build Pediment WordPress client sites: scaffold a standalone client theme, seed it, and port existing pages into it.",
  "version": "0.1.0",
  "author": { "name": "Bergert Digital", "email": "jonas@bergert.digital" },
  "homepage": "https://github.com/Bergert-Digital/pediment",
  "repository": "https://github.com/Bergert-Digital/pediment",
  "keywords": ["wordpress", "pediment", "scaffolding", "block-theme"]
}
```

Create `client-kit/.claude-plugin/marketplace.json`:

```json
{
  "name": "pediment",
  "description": "Pediment's client-site toolkit for Claude Code.",
  "owner": { "name": "Bergert Digital", "email": "jonas@bergert.digital" },
  "plugins": [
    {
      "name": "pediment",
      "description": "Create and build Pediment WordPress client sites: scaffold a standalone client theme, seed it, and port existing pages into it.",
      "version": "0.1.0",
      "source": "./",
      "author": { "name": "Bergert Digital", "email": "jonas@bergert.digital" }
    }
  ]
}
```

Create `client-kit/README.md`:

```markdown
# Pediment client kit

A Claude Code plugin for building Pediment client sites. It carries the `/pediment:start` and
`/pediment:port-page` skills plus one deterministic scaffolder.

**A client developer never clones this monorepo.** They install this plugin, and the client theme
template arrives as a release asset (`pediment-client-template.zip`).

## Install

From a local checkout of this repo:

```
/plugin marketplace add ./client-kit
/plugin install pediment
```

## Use

```
/pediment:start
```

Answers a short questionnaire, scaffolds a standalone client theme repo into a directory you
choose, boots wp-env, seeds it, and reports the local URL.

## Scaffolding without the skill

```bash
node client-kit/scripts/scaffold.mjs \
  --answers answers.json \
  --target ~/Entwicklung/acme-roofing \
  --template client-template
```

Omit `--template` to download `pediment-client-template.zip` for the version named in the answers
file. `client-kit/tests/fixtures/answers-greenfield.json` is the reference answers file.
```

- [ ] **Step 4: Create a placeholder skill so the structural test has something to check**

The real content lands in Task 11. Create `client-kit/skills/start/SKILL.md` with only its frontmatter and a one-line body:

```markdown
---
name: start
description: Create a new Pediment client site — scaffold a standalone client theme repo, brand it, seed it, and report the local URL. Use when starting a new client project, whether porting an existing site or starting fresh.
---

# Start a Pediment client site

Body written in Task 11.
```

- [ ] **Step 5: Run the tests to verify they pass**

Run: `npm run test:kit`
Expected: PASS — the 4 new tests in `kit.test.mjs`, and nothing earlier regressed.

- [ ] **Step 6: Commit**

```bash
git add client-kit/.claude-plugin client-kit/README.md client-kit/tests/kit.test.mjs client-kit/skills/start/SKILL.md
git commit -m "feat(kit): package the client kit as a Claude Code plugin"
```

---

### Task 11: The `/pediment:start` skill

**Files:**
- Modify: `client-kit/skills/start/SKILL.md` (replace the placeholder body)

**Interfaces:**
- Consumes: `client-kit/scripts/scaffold.mjs` (Task 4), the answers schema (Task 4), `client-kit/skills/port-page/SKILL.md` (Task 12, referenced by name only).
- Produces: the `/pediment:start` slash command.

- [ ] **Step 1: Write the skill**

Replace the body of `client-kit/skills/start/SKILL.md`, keeping the frontmatter from Task 10:

````markdown
# Start a Pediment client site

Take a client from nothing to a seeded, rendering local site in one session. You ask only what
cannot be derived; a deterministic scaffolder does everything that must be identical every time.

All per-run scratch files go under `.context/start/` (gitignored) in the directory you are
standing in.

---

## Phase 0 — prerequisites (do this before asking anything)

Check all three. Report **every** failure together with the command that fixes it, then stop.

```bash
docker info >/dev/null 2>&1 && echo "docker: ok" || echo "docker: NOT RUNNING — start Docker Desktop"
node -e "process.stdout.write(process.versions.node)" && echo " node: ok" || echo "node: MISSING"
git --version >/dev/null 2>&1 && echo "git: ok" || echo "git: MISSING"
```

Node must be 20 or newer. `gh` is NOT checked here — it is only needed if the user opts into
creating a GitHub remote at the very end.

---

## Phase 1 — the questionnaire

**One question per message.** Derive everything you can and show it for confirmation instead of
asking. Open with the branching question:

> Are we porting an existing site, or starting fresh?

### If porting

1. **Source URL.** Load it in the browser (Chrome — see the user's browser rules).
2. **Brand.** Read the homepage's computed styles: button background (→ accent), band background
   or heading colour (→ primary), body colour (→ foreground), the first `font-family`, and the
   first border-radius. Show the extracted values and ask only "does this look right?". Do not ask
   the user to name colours they already have.
3. **Pages.** Fetch `/sitemap.xml` (fall back to `/sitemap_index.xml`, then to the nav links on the
   homepage). Present the list **pre-checked** and ask which to drop.
4. **Languages.** Read `<link rel="alternate" hreflang="…">` from the homepage. Present pre-filled
   and ask only for confirmation.
5. **Client name and repo slug.** Derive the slug from the name (lowercase, hyphens); show both.

### If starting fresh

1. **Business name.** Derive the repo slug and theme name; show for confirmation.
2. **What they do, and for whom** — and, in the same question, the tone they want. State plainly:
   *"This goes into `docs/brief.md` for you and future agents to read. Nothing in the plugin reads
   it programmatically yet."* Do not imply the AI features will pick it up.
3. **Languages.** Default first. Most sites are one language — offer that as the default answer.
4. **Sitemap.** Offer Home / About / Services / Contact pre-checked, plus an optional Blog.
5. **Accent colour.** A single hex. Everything else in the palette derives from it.
6. **Logo** — a file path, optional.

---

## Phase 2 — write the answers file

Write `.context/start/answers.json`. This exact shape is what `scaffold.mjs` consumes;
`client-kit/tests/fixtures/answers-greenfield.json` is the reference instance.

```json
{
  "version": 1,
  "mode": "greenfield",
  "client": { "name": "Acme Roofing", "slug": "acme-roofing", "description": "One line." },
  "brief": { "does": "…", "audience": "…", "tone": "…", "sourceUrl": null },
  "brand": {
    "accent": "#B91C1C",
    "primary": "#1F2937",
    "foreground": "#1F2937",
    "font": { "family": "Inter", "weights": ["400", "700"] },
    "source": "chosen"
  },
  "languages": [{ "slug": "en", "name": "English", "locale": "en_US", "flag": "gb", "default": true }],
  "pages": [
    { "key": "home", "title": "Home", "frontPage": true },
    { "key": "about", "title": "About" },
    { "key": "contact", "title": "Contact" }
  ],
  "nav": ["about", "contact"],
  "logo": null,
  "plugin": { "version": "3.3.0" },
  "template": { "version": "3.3.0" }
}
```

Rules:

- `mode` is `"port"` or `"greenfield"`. When porting, set `brief.sourceUrl` and
  `brand.source` to `"extracted:<url>"`.
- Page `key`s must be lowercase-hyphenated. A blog index page gets `"postsPage": true` and no
  pattern file.
- `logo` is `null`, or `{ "file": "logo.svg", "sourcePath": "<absolute path to the file>" }`.
  `file` is the name it will have inside `seed/media/`; `sourcePath` is where to copy it from.
- `plugin.version` / `template.version`: use the latest published release. Resolve it with
  `gh release list --repo Bergert-Digital/pediment --limit 1`, or ask the user if `gh` is
  unavailable.
- Ask the user where the repo should go and confirm the absolute path before writing anything.

---

## Phase 3 — scaffold and boot

```bash
node scripts/scaffold.mjs --answers .context/start/answers.json --target <absolute path>
```

(From a monorepo checkout, add `--template client-template` to scaffold from the working tree
instead of downloading the release asset.)

The scaffolder refuses a target path containing whitespace or a non-empty target directory, and
commits the result before anything else runs. Then, in the new directory:

```bash
npm install
npm run env:start
npm run languages    # only if the manifest has a `languages` section
npm run seed:plan
```

**Show the user the plan before applying it.** Then:

```bash
npm run seed
```

Report the local URL (http://localhost:8888), the wp-admin URL, and what to do next.

---

## Phase 4 — hand off

- **Greenfield:** tell the user which pattern files to edit (`patterns/<key>.php`), and that
  `npm run seed` re-applies them. Offer to build the first page with them.
- **Port:** hand over the page list and run `/pediment:port-page <url>` per page, one at a time.

Offer to create a GitHub remote **last**, and only with explicit confirmation:

```bash
gh repo create <owner>/<slug> --private --source=. --push
```

Never push without the user saying yes.

---

## If something fails

- **Docker not running** → phase 0 catches it; nothing has been written.
- **`wp-env start` fails** → the repo is already committed, so nothing is lost. Fix Docker, then
  resume from `npm run env:start`.
- **Re-running `/start` in a directory that already has `seed/manifest.php`** → do not scaffold
  again. Say what is already there and offer to resume from phase 3's boot step.
- **The template download fails** (no release yet, offline) → re-run with
  `--template <path to a client-template checkout>`.
- **The seed reports problems** → stop and show them. Never re-run a seed to "try again"; the plan
  is deterministic, so a problem repeats until the manifest or a pattern file changes.
````

- [ ] **Step 2: Run the structural test**

Run: `npm run test:kit`
Expected: PASS — the `kit.test.mjs` reference checks now resolve `scripts/scaffold.mjs`.

- [ ] **Step 3: Verify the skill's own instructions are executable**

Read the skill back and check each shell command against the files that now exist: `scaffold.mjs`
accepts every flag the skill passes, and `package.json` in `client-template/` defines `env:start`,
`languages`, `seed:plan` and `seed`. Fix any mismatch in the skill, not in the scripts.

- [ ] **Step 4: Commit**

```bash
git add client-kit/skills/start/SKILL.md
git commit -m "feat(kit): write the /pediment:start skill"
```

---

### Task 12: The `/pediment:port-page` skill and the shared critic prompts

**Files:**
- Create: `client-kit/skills/port-page/SKILL.md`
- Create: `client-kit/shared/fidelity-critic-prompt.md`
- Create: `client-kit/shared/visual-qa.md`

**Interfaces:**
- Consumes: `client-kit/shared/*.md`, the seeder's `adopt` command.
- Produces: the `/pediment:port-page` slash command.

Source material: `~/Entwicklung/workation-castle-website/.claude/skills/port-page/SKILL.md` and
`.claude/skills/shared/{fidelity-critic-prompt.md,visual-qa.md}`. Read all three first. The
critic prompts port nearly verbatim; the skill body does not.

**What changes from workation's version.** Its Step 9 ("Persist to version control") existed
because pages built live in wp-env were destroyed by the next re-seed. That is now backwards:
`wp pediment adopt <key>` exports the live page into `patterns/<slug>.php` and resets the hash, so
persisting is one command rather than a section. The preconditions change too — there is no parent
theme and no `theme.json` `settings` check, because `/start` already wrote the brand.

- [ ] **Step 1: Port the shared critic prompts**

Copy both files from workation into `client-kit/shared/`, then read them and fix any reference to
the parent/child theme, to `tools/brand-extract.mjs`, or to building pages that persist by hand.
They should describe only how to judge visual fidelity between a source screenshot and a rebuilt
page.

- [ ] **Step 2: Write the skill**

Create `client-kit/skills/port-page/SKILL.md`:

````markdown
---
name: port-page
description: Rebuild one existing page as native Pediment blocks in a scaffolded client theme, iterating under an independent fidelity critic until it matches the source, then adopt it back into git. Use after /pediment:start has scaffolded and seeded the site.
---

# Port one page to Pediment

Rebuild ONE existing public page as native Pediment blocks, iterate under an independent visual
fidelity critic, then commit the result to git as a pattern file.

**Argument:** the source page URL. Derive `<key>` from its path (`/about-us/` → `about-us`;
homepage → `home`).

All per-run files go under `.context/port/<key>/` (gitignored).

---

## 1. Preconditions

Check all three. Stop on the first failure with the stated message.

1. **wp-env is running** — `npx wp-env run cli wp option get siteurl`. If it errors: "wp-env is not
   running — start it with `npm run env:start`, then re-run `/pediment:port-page`."
2. **The site is seeded** — `npx wp-env run cli wp pediment seed --dry-run` must succeed. If the
   manifest is missing: "This is not a scaffolded Pediment client theme. Run `/pediment:start`
   first."
3. **The source URL is publicly reachable** — load it in the browser (Chrome). Stop on an error or
   a redirect loop.

---

## 2. Capture the source

Screenshot the source page full-height at 1440px and at 375px into `.context/port/<key>/`.
Extract its text content and section structure. Note the section order — it is what the critic
compares against.

---

## 3. Declare the page

Add the entry to `seed/manifest.php`:

```php
'<key>' => array( 'title' => '<Title>', 'pattern' => '<theme-slug>/<key>' ),
```

Create `patterns/<key>.php` with the standard header — the `Slug:` header must be
`<theme-slug>/<key>` exactly, because that is what the seeder looks up in the pattern registry, not
the filename:

```php
<?php
/**
 * Title: <Title>
 * Slug: <theme-slug>/<key>
 * Categories: pediment
 * Inserter: no
 */
// phpcs:ignoreFile -- block pattern content
?>
```

Then `npm run seed:plan`, read the plan, and `npm run seed`.

---

## 4. Build the page

Two routes; pick per page and say which you are using.

- **Author the pattern file directly** when the structure is clear from the source. Faster, and the
  diff is readable.
- **Build it in the block editor**, then `npm run adopt -- <key>` to write it back into
  `patterns/<key>.php` with media URLs converted to `{{media_*}}` placeholders. Use this when the
  layout needs to be seen to be got right.

Prefer a purpose-built `pediment/*` block over composing primitives. Never set custom colours, font
sizes or spacing — the brand is in `theme.json` and hardcoding defeats it.

---

## 5. Iterate under the critic

Screenshot the rebuilt page at the same two widths. Dispatch an independent critic with
`shared/fidelity-critic-prompt.md`, giving it the source and rebuilt screenshots. Fix what it
names, re-seed, re-screenshot, re-run. Stop when it reports no material differences, or after
four rounds — then report honestly what still differs and why.

Run the checks in `shared/visual-qa.md` before declaring the page done.

---

## 6. Persist

If the page was built in the editor, adopt it now:

```bash
npm run adopt -- <key>
```

Read the diff before committing — `adopt` does not convert sized image variants or `srcset` URLs to
placeholders, so a page with responsive images can carry environment-specific URLs into git.

Commit `seed/manifest.php` and `patterns/<key>.php` together.

---

## Multilingual pages

Port the default language first. For each additional language, translate the page in the editor and
run `npm run adopt -- <key> -- --language=<code>`, which writes `patterns/<key>.<lang>.php` with the
correct `Slug: <theme-slug>/<key>-<lang>` header. The filename suffix and the header suffix must
agree, or the translated pattern is reported missing exactly as if the file did not exist.
````

- [ ] **Step 3: Run the structural test**

Run: `npm run test:kit`
Expected: PASS — the shared/*.md references resolve.

- [ ] **Step 4: Commit**

```bash
git add client-kit/skills/port-page/SKILL.md client-kit/shared
git commit -m "feat(kit): port the page-porting skill onto the seeding engine"
```

---

### Task 13: Ship `pediment-client-template.zip` as a release asset

**Files:**
- Modify: `.github/workflows/build-release-zip.yml`

**Interfaces:**
- Consumes: `client-template/` (Task 5).
- Produces: a second asset on every release, which is how `scaffold.mjs`'s download path finds the template.

- [ ] **Step 1: Add the staging and upload steps**

**The template ships verbatim, with its `__PEDIMENT_*__` tokens intact.** Do not stamp a version
into `client-template/package.json` here — `__PEDIMENT_TEMPLATE_VERSION__` is a token the
scaffolder resolves from the answers file, and replacing it at release time would leave the
scaffolder nothing to substitute and `assertNoTokens` nothing to catch.

In `.github/workflows/build-release-zip.yml`, after the existing `Upload plugin zip` step, append:

```yaml
      - name: Stage and zip the client template
        run: |
          set -euo pipefail
          mkdir -p stage-template
          rsync -a client-template/ stage-template/client-template/
          cd stage-template && zip -rq ../pediment-client-template.zip client-template
          unzip -l ../pediment-client-template.zip | tail -5

      - name: Upload the client template zip
        env:
          GH_TOKEN: ${{ secrets.GITHUB_TOKEN }}
          TAG: ${{ inputs.tag }}
        run: gh release upload "$TAG" pediment-client-template.zip --clobber
```

- [ ] **Step 2: Verify the zip builds and still scaffolds**

Run:

```bash
set -euo pipefail
rm -rf /tmp/stage-template /tmp/pediment-client-template.zip /tmp/unzipped /tmp/zip-scaffold
mkdir -p /tmp/stage-template
rsync -a client-template/ /tmp/stage-template/client-template/
cd /tmp/stage-template && zip -rq /tmp/pediment-client-template.zip client-template && cd -
mkdir -p /tmp/unzipped && unzip -q /tmp/pediment-client-template.zip -d /tmp/unzipped
node client-kit/scripts/scaffold.mjs --answers client-kit/tests/fixtures/answers-ci.json --target /tmp/zip-scaffold --template /tmp/unzipped/client-template --no-git
grep -c "acme\|__PEDIMENT" /tmp/zip-scaffold/style.css || true
node -e "console.log(require('/tmp/zip-scaffold/package.json').pediment)"
```

Expected: the scaffold succeeds, `style.css` contains no `__PEDIMENT` tokens, and `package.json`'s
`pediment` block reads `{ template: '3.3.0', plugin: '3.3.0' }` — proving the template ships with
tokens intact and the scaffolder, not the release workflow, resolves them.

Then: `rm -rf /tmp/stage-template /tmp/pediment-client-template.zip /tmp/unzipped /tmp/zip-scaffold`

- [ ] **Step 3: Verify the workflow YAML has no tabs**

Run: `node -e "const s=require('node:fs').readFileSync('.github/workflows/build-release-zip.yml','utf8'); if(/\t/.test(s)) throw new Error('tabs'); console.log('ok')"`
Expected: `ok`

- [ ] **Step 4: Commit**

```bash
git add .github/workflows/build-release-zip.yml
git commit -m "ci(release): attach pediment-client-template.zip to releases"
```

---

### Task 14: Documentation and backlog

**Files:**
- Create: `docs/client-sites.md`
- Modify: `README.md`, `AGENTS.md`, `docs/BACKLOG.md`

**Interfaces:**
- Consumes: everything above.
- Produces: the durable explanation of how a client site is made and maintained.

- [ ] **Step 1: Write `docs/client-sites.md`**

Cover, with real commands taken from the files as built (do not paraphrase from this plan — read
the actual `package.json` scripts and skill):

- The three units and why a client developer never clones this repo: the `client-kit` Claude Code
  plugin, the `pediment-client-template.zip` release asset, and this monorepo.
- Making a site: install the kit, run `/pediment:start`, what the six questions are.
- What a scaffolded repo contains and, explicitly, what it does **not** — no composer, no phpcs, no
  Playwright, no build step, no auto-updater.
- The `pediment` block in `package.json` and how to read version drift.
- Day-two work: edit `seed/manifest.php` for structure, `patterns/*.php` for content,
  `npm run seed:plan` then `npm run seed`; `npm run adopt -- <key>` to take a client's live edit
  back into git.
- Deploying: push a `v*` tag, upload the zip via wp-admin, re-seed from Settings → Pediment Theme →
  Seeding. State plainly that the theme has no auto-updater and why (the plugin does).
- Scaffolding without Claude Code, using `scaffold.mjs` and a hand-written answers file.

- [ ] **Step 2: Update `README.md` and `AGENTS.md`**

In `README.md`, add a short "Building a client site" section pointing at `docs/client-sites.md`.

In `AGENTS.md`, under **Project**, note that the repo now also ships `client-template/` (the client
theme template, tokenised) and `client-kit/` (the Claude Code plugin), and add to **Environment and
verification**:

```bash
npm run test:kit
```

- [ ] **Step 3: Update `docs/BACKLOG.md`**

- Tick and remove **"Update the client-theme template repo"** under 🟡 High — it is done, by
  replacement rather than migration.
- Add under 🟡 High:

```markdown
- [ ] **Archive `pediment-child-theme`.** Migration step 5 replaced it with `client-template/` in
  this monorepo. The old repo still exists and still describes a parent/child world that no longer
  ships. Archive it on GitHub with a README pointing at `docs/client-sites.md`. Needs an explicit
  go-ahead — it is an outward-facing, hard-to-reverse action.
```

- Add under 🟢 Medium:

```markdown
- [ ] **`client-release.yml` has never run.** The reusable client release workflow is written and
  its version-stamping is unit-tested (`tools/stamp-theme-version.test.mjs`), but no client repo
  has pushed a `v*` tag through it yet. Verify it end to end on the first real client site, and
  treat a failure there as expected rather than surprising.
- [ ] **Brand voice is captured but not consumed.** `/pediment:start` writes positioning and tone
  into `docs/brief.md`; `PromptBuilder` still builds a fully static prompt and reads no options.
  Deliberate (step-5 design decision 7) — close the loop when the AI side is next worked on, and
  until then keep the skill honest about it.
- [ ] **The client theme has no auto-updater.** Step 5 decision 8: `ThemeUpdater`/`UpdateToken` did
  not come across, so client themes update by admin zip upload. Revisit if step 6 shows it hurts.
```

- [ ] **Step 4: Verify the docs match the code**

Run every command block in `docs/client-sites.md` that is safe to run (the `npm run` script names
against `client-template/package.json`, the `scaffold.mjs` invocation with `--no-git` into a temp
directory). Fix the docs where they drift.

- [ ] **Step 5: Commit**

```bash
git add docs/client-sites.md README.md AGENTS.md docs/BACKLOG.md
git commit -m "docs: explain how Pediment client sites are made and maintained"
```

---

### Task 15: Full local verification and the gated push

**Files:** none created; this is the gate.

- [ ] **Step 1: Run every suite**

```bash
npm run test:kit
npm run lint:blocks
npm run lint:colors
npm run lint:icons
node tools/generate-wpml-config.mjs --check
cd plugin && composer lint && cd ..
```

Expected: all pass. Nothing under `plugin/` changed, so `composer lint` is a regression check.

- [ ] **Step 2: Confirm the plugin really is untouched**

```bash
git diff --stat origin/main...HEAD -- plugin/
```

Expected: empty output. If it is not, something in this plan drifted out of scope — stop and report
rather than pushing.

- [ ] **Step 3: Run the plugin's own suites once**

```bash
npm run env:start
npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit
npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit -c phpunit-polylang.xml.dist
cd plugin && npm run e2e && cd ..
npm run env:stop
```

Expected: green. These are pure regression gates.

- [ ] **Step 4: Rehearse the full scaffold once more, from a clean slate**

```bash
rm -rf /tmp/final-check
node client-kit/scripts/scaffold.mjs --answers client-kit/tests/fixtures/answers-ci.json --target /tmp/final-check --template client-template
cd /tmp/final-check && git log --oneline && npm install && npm run env:start && npm run seed:plan
```

Read the plan. If it lists the four pages and the nav, apply it with `npm run seed`, load
http://localhost:8888, and confirm the header, the front page and the nav all render. Then
`npm run env:stop`, `cd -`, `rm -rf /tmp/final-check`.

- [ ] **Step 5: Summarise for the user and ASK before pushing**

Report: what shipped, what the CI job now proves, which two things are written but never executed
(`client-release.yml`, and the template-download path in `resolveTemplate`), and the backlog items
added. Then ask explicitly whether to push. **Do not push without a yes.**

- [ ] **Step 6: Push, once approved**

```bash
git push -u origin dev-flow-step-5
```

Then open the PR only if asked, and watch CI with `/check-ci`.
