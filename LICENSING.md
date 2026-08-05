# Licensing

Pediment ships three components with different legal footing, so one repository-wide
licence would be wrong for at least two of them.

| Directory | Licence | File |
| --- | --- | --- |
| `plugin/` | GPL-2.0-or-later | [`LICENSE`](LICENSE) |
| `client-template/` | GPL-2.0-or-later | [`client-template/LICENSE`](client-template/LICENSE) |
| `client-kit/` | PolyForm Shield 1.0.0 | [`client-kit/LICENSE`](client-kit/LICENSE) |

## Why the split

**`plugin/` is GPL because it is WordPress code.** It loads inside WordPress and calls its
APIs, and the WordPress project's position is that plugin PHP inherits the GPL. This was
already declared in `plugin/plugin.php` and `plugin/composer.json`; the root `LICENSE`
makes it explicit rather than implied. Anyone who receives `pediment-plugin.zip` may
inspect, modify and redistribute it under the GPL's terms.

**`client-template/` is GPL for the same reason, plus a practical one.** It is a block
theme with PHP, and the scaffolder copies it into the customer's own repository where it
becomes *their* theme. A restrictive licence there would make ownership of a client's own
site ambiguous.

**`client-kit/` is not WordPress code.** It is a Claude Code plugin — skills and a Node
scaffolder that run on a developer's machine. It never loads in WordPress and never links
against it, so it carries no GPL obligation and is licensed commercially.

PolyForm Shield permits customers to use the kit for their own client work, including
commercial work, and forbids using it to build a product that competes with Pediment. The
full terms are in `client-kit/LICENSE`; the canonical text is at
<https://polyformproject.org/licenses/shield/1.0.0>.

## What this does and does not protect

Worth stating plainly, because licences are often expected to do more than they can.

The plugin executes as plain PHP on the customer's server. Whatever runs there, the
customer has, in readable form — no licence changes that. What a licence does is define
what they may lawfully do with it afterwards, and under the GPL that includes
redistribution. This is the same position every commercial WordPress plugin occupies.

`client-kit/`'s licence is the one with real commercial teeth, because the kit is not
GPL-encumbered. It still lands on the customer's disk; Shield governs use, not access.

## Questions this does not answer

This split is an engineering decision, not legal advice. A German/EU software-licensing
lawyer should confirm it — and the Pediment trademark — before the product is sold.
