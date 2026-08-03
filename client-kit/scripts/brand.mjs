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
