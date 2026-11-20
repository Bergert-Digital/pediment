# Licensing

Everything in this repository is licensed under the GNU General Public License,
version 2 or (at your option) any later version — GPL-2.0-or-later. The full
text is in [`LICENSE`](LICENSE).

| Directory | Licence | File |
| --- | --- | --- |
| `plugin/` | GPL-2.0-or-later | [`LICENSE`](LICENSE) |
| `client-template/` | GPL-2.0-or-later | [`client-template/LICENSE`](client-template/LICENSE) |
| `client-kit/` | GPL-2.0-or-later | [`client-kit/LICENSE`](client-kit/LICENSE) |

`client-template/` and `client-kit/` carry their own copy of the licence because
each one is copied out of this repository on its own: the scaffolder copies the
template into a new client theme repository, and the kit installs as a Claude
Code plugin.

## Third-party assets

The plugin bundles assets under their own licences:

| Asset | Licence | File |
| --- | --- | --- |
| Plus Jakarta Sans (`plugin/assets/fonts/`) | SIL Open Font License 1.1 | [`plugin/assets/fonts/LICENSE-OFL.txt`](plugin/assets/fonts/LICENSE-OFL.txt) |
| Phosphor Icons (`plugin/assets/icons/`) | MIT | <https://github.com/phosphor-icons/core/blob/main/LICENSE> |

Composer and npm dependencies keep their own licences, listed in
`plugin/composer.lock` and the `package-lock.json` files.

## Contributions

Contributions are accepted under the same licence. See
[CONTRIBUTING.md](CONTRIBUTING.md).
