# Contributing to Pediment

Thank you for your interest in Pediment. Bug reports, fixes and documentation
improvements are welcome.

## Before you start

- Search the existing issues first. Open a new issue for a bug, or to discuss a
  feature before you write code for it.
- Report security problems privately. See [SECURITY.md](SECURITY.md).
- Read [docs/STANDARDS.md](docs/STANDARDS.md). It holds the block contract,
  the test rules and the lint gates every change must pass.
- Read [docs/WORDPRESS_TRAPS.md](docs/WORDPRESS_TRAPS.md) before a
  WordPress-side fix.

## Set up

You need Docker, Node 20+, PHP 8.1+ and Composer.

```bash
npm ci
composer install -d plugin
( cd plugin && npm ci && npm run build )
npm run env:start
```

Use `npm ci`, not `npm install`. CI runs npm 10, and npm 11 rewrites the
lockfiles into a form npm 10 rejects. Change a lockfile only on purpose, with
`npx -y npm@10 install <package>`.

The site runs at <http://localhost:8888>. See the [README](README.md) for the
first-boot activation commands.

## Check your change

Run these from the repository root before you open a pull request:

```bash
composer lint -d plugin
npm run lint:js
npm run lint:blocks
npm run lint:colors
npx wp-env run tests-wordpress --env-cwd=wp-content/plugins/pediment-ai ./vendor/bin/phpunit
( cd plugin && npm run e2e )
npm run test:kit
```

For a layout or typography change, also run `node tools/audit-landing.mjs` and
check the result at 375px, 768px and 1440px and in the block editor.

A new or changed block keeps `block.json`, `index.tsx`, `edit.tsx`,
`render.php` and `style.scss`, plus PHPUnit coverage for valid and empty
attributes. Block styles use `var(--wp--preset--…)`; colour literals fail CI.

## Pull requests

- Branch from `main` and keep one change per pull request.
- Use [Conventional Commits](https://www.conventionalcommits.org/):
  `feat:`, `fix:`, `docs:`, `refactor:`, `test:`, `chore:`. Write the summary
  in the imperative, 60 characters or fewer. release-please builds the
  changelog and the version number from these messages, so do not edit
  version numbers or `CHANGELOG.md` by hand.
- Add or update tests for the behaviour you change, and update the docs in
  `docs/` when you change a documented behaviour.
- CI must be green. The WPML suite needs a commercial WPML licence, so it does
  not run for pull requests from forks; a maintainer runs it before merge.

## Licence

Pediment is licensed under GPL-2.0-or-later. By submitting a contribution, you
agree that it is licensed under the same terms. See [LICENSING.md](LICENSING.md).

## Code of Conduct

Everyone who takes part in this project follows the
[Code of Conduct](CODE_OF_CONDUCT.md).
