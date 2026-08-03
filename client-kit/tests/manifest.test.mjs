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
    nav: [],
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
