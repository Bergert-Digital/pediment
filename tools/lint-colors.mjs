#!/usr/bin/env node
import { readdirSync, statSync, readFileSync } from 'node:fs';
import { join } from 'node:path';

const root = new URL('../src/blocks/', import.meta.url).pathname;
const HEX = /#[0-9a-fA-F]{3}(?:[0-9a-fA-F]{1,5})?\b/;
const RGB = /\brgb[a]?\s*\(/i;
const HSL = /\bhsl[a]?\s*\(/i;

function* walk(dir) {
  for (const name of readdirSync(dir)) {
    const p = join(dir, name);
    if (statSync(p).isDirectory()) {
      yield* walk(p);
    } else {
      yield p;
    }
  }
}

let failed = false;

for (const path of walk(root)) {
  if (!path.endsWith('.scss') && !path.endsWith('.css')) { continue; }
  const lines = readFileSync(path, 'utf8').split('\n');
  lines.forEach((line, i) => {
    if (HEX.test(line) || RGB.test(line) || HSL.test(line)) {
      console.error(`✗ ${path}:${i + 1} — color literal: ${line.trim()}`);
      failed = true;
    }
  });
}

if (failed) {
  console.error('\nUse theme.json tokens via var(--wp--preset--color--*).');
  process.exit(1);
}

console.log('✓ No color literals in src/blocks/ stylesheets.');
