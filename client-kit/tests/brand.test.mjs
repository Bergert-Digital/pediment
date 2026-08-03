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
