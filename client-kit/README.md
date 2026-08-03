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
