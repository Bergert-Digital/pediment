#!/usr/bin/env node
import { readdirSync, statSync, existsSync } from 'node:fs';
import { join } from 'node:path';

const root = new URL('../src/blocks/', import.meta.url).pathname;
const required = ['block.json', 'render.php', 'edit.tsx'];

if (!existsSync(root)) {
  console.log(`No src/blocks/ directory yet — skipping.`);
  process.exit(0);
}

const dirs = readdirSync(root).filter((name) => {
  return statSync(join(root, name)).isDirectory();
});

let failed = false;

for (const dir of dirs) {
  const missing = required.filter((file) => !existsSync(join(root, dir, file)));
  if (missing.length > 0) {
    console.error(`✗ src/blocks/${dir}/ is missing: ${missing.join(', ')}`);
    failed = true;
  } else {
    console.log(`✓ src/blocks/${dir}/`);
  }
}

if (failed) {
  console.error('\nEach src/blocks/<name>/ must contain block.json, render.php, and edit.tsx.');
  process.exit(1);
}
