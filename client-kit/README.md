# Pediment client kit

Pediment itself is a WordPress plugin installed in WordPress from `pediment-plugin.zip`. This
directory is a separate Claude Code developer kit: it provides `/pediment:start`,
`/pediment:port-page`, and the deterministic scaffolder that creates standalone client-theme
repositories. The kit is installed once in Claude Code and is not copied into client repos.

## Install

In Claude Code, from any directory:

```text
/plugin marketplace add Bergert-Digital/pediment
/plugin install pediment@pediment
```

Claude Code fetches the public Pediment repository over HTTPS. The developer does not clone or
work inside the monorepo.

## Use

In the empty directory where the client project should be created:

```text
/pediment:start
```

The skill reads the installed kit version, asks the greenfield or porting questionnaire, downloads
the matching `pediment-client-template.zip`, scaffolds a standalone theme repo, boots wp-env with
the matching `pediment-plugin.zip`, seeds it, and reports the local URL.

The v3.0.0 release predates both the seeding engine and the client-template release asset. The
complete external flow begins with the first later release that contains this distribution work.

## Maintainer-only local scaffolding

Pediment maintainers can bypass the release download while testing an unreleased template from a
monorepo checkout:

```bash
node client-kit/scripts/scaffold.mjs \
  --answers answers.json \
  --target ~/Entwicklung/acme-roofing \
  --template client-template
```

`client-kit/tests/fixtures/answers-greenfield.json` is the reference answers file. This local
override is not part of external onboarding.

## Maintainer smoke checks

Before release, run `npm run test:kit`, `claude plugin validate . --strict`, and add the repository
root as a local-scope marketplace from a disposable directory. After release, repeat installation
with `Bergert-Digital/pediment`, run `/pediment:start` outside the monorepo, and require a second
`npm run seed:plan` to report `0 to write`. The exact commands and cleanup guards live in the
approved implementation plan.
