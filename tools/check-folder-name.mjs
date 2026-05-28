#!/usr/bin/env node
import { basename } from 'node:path';

const name = basename(process.cwd());

if (/\s/.test(name)) {
  const suggested = name.toLowerCase().replace(/\s+/g, '-');
  console.error(
    `\n✗ Project folder "${name}" contains whitespace.\n\n` +
    `  WordPress derives the theme stylesheet identifier from the directory\n` +
    `  name. The Site Editor's template-part edit URLs are built as\n` +
    `  ?p=<stylesheet>//<slug>, which WordPress's JS routing cannot parse\n` +
    `  when <stylesheet> contains a space — Edit on a template or template\n` +
    `  part lands on ?p=&canvas=edit (empty p).\n\n` +
    `  Rename the folder to a lowercase-hyphenated slug (suggested:\n` +
    `  "${suggested}") and re-run env:start.\n`,
  );
  process.exit(1);
}
