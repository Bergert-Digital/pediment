# Changelog

## [6.5.0](https://github.com/Bergert-Digital/pediment/compare/v6.4.0...v6.5.0) (2026-11-21)


### Features

* **forms:** let a form name a destination by seed key ([a513082](https://github.com/Bergert-Digital/pediment/commit/a513082dce4c0ccb3ec6287552e9ca4542cdeb0b))
* **forms:** let a form name a destination by seed key ([#164](https://github.com/Bergert-Digital/pediment/issues/164)) ([1bf765e](https://github.com/Bergert-Digital/pediment/commit/1bf765e8116c3d6269e6322d2ccf35557d961504))

## [6.4.0](https://github.com/Bergert-Digital/pediment/compare/v6.3.0...v6.4.0) (2026-11-21)


### Features

* **forms:** add a file field type with private storage ([d0c6977](https://github.com/Bergert-Digital/pediment/commit/d0c69777401ced2416bad00354df2bb18f483ba6))
* **forms:** add a file field type with private storage ([#163](https://github.com/Bergert-Digital/pediment/issues/163)) ([adb645a](https://github.com/Bergert-Digital/pediment/commit/adb645ae8482219b1d414d2f60b89ed716716f77))
* **forms:** send an optional confirmation to the sender ([e938411](https://github.com/Bergert-Digital/pediment/commit/e938411db70feed9ad7a7f79fd6ea4491427d25a))
* **license:** open-source Pediment under GPL-2.0-or-later ([#161](https://github.com/Bergert-Digital/pediment/issues/161)) ([de9a8bb](https://github.com/Bergert-Digital/pediment/commit/de9a8bb3310c8ec86491c65aeb656d7088c632d3))
* **license:** relicense client-kit under GPL-2.0-or-later ([f48332d](https://github.com/Bergert-Digital/pediment/commit/f48332d7c3b3fdc348f0a5565c6df49af5201b32))

## [6.3.0](https://github.com/Bergert-Digital/pediment/compare/v6.2.0...v6.3.0) (2026-11-18)


### Features

* **forms:** add e-mail destination type ([ae81b9c](https://github.com/Bergert-Digital/pediment/commit/ae81b9c28a5b899b2fe0d82482a8f55dfbba0d2c))
* **forms:** add e-mail form destinations ([#159](https://github.com/Bergert-Digital/pediment/issues/159)) ([625b8fe](https://github.com/Bergert-Digital/pediment/commit/625b8fe2a906b7683b3414bf645905817f27183f))
* **seeder:** seed e-mail form destinations ([f035afa](https://github.com/Bergert-Digital/pediment/commit/f035afaecfd5813f9bdd45b2acbbcd85c060716b))

## [6.2.0](https://github.com/Bergert-Digital/pediment/compare/v6.1.0...v6.2.0) (2026-10-30)


### Features

* **mcp:** add an AI agents tab that connects an agent ([25f8df8](https://github.com/Bergert-Digital/pediment/commit/25f8df831db2e7be9c9b21b3416d07708c25785c))
* **mcp:** collapse the connect form after a setup ([c2b787e](https://github.com/Bergert-Digital/pediment/commit/c2b787e97956dc0c16725e702b7ccbc488e754b4))
* **mcp:** connect AI agents from the Pediment settings page ([#153](https://github.com/Bergert-Digital/pediment/issues/153)) ([0aa6b42](https://github.com/Bergert-Digital/pediment/commit/0aa6b42ec1a65b345de181f3539c18b82eac56cf))
* **mcp:** show agent setup in Claude and Codex tabs ([6d25a39](https://github.com/Bergert-Digital/pediment/commit/6d25a3925efb60fee1cbfe5da5962caa54634d84))
* **settings:** move the settings page to ?page=pediment ([8096d23](https://github.com/Bergert-Digital/pediment/commit/8096d236fca9a42281ddbcdf18fd83006a603da3))
* **settings:** rename the settings entry to Pediment ([f342c02](https://github.com/Bergert-Digital/pediment/commit/f342c0234dfcdcc1a06f6c4311161f18a95aac98))


### Bug Fixes

* **blocks:** no empty href or open tags from hostile input ([6d7c13f](https://github.com/Bergert-Digital/pediment/commit/6d7c13f8c458bffbea1c16d6b6e27f2ed02df45e))
* honest seed reports, safe empty blocks, multilingual CI leg ([#148](https://github.com/Bergert-Digital/pediment/issues/148)) ([bd64e55](https://github.com/Bergert-Digital/pediment/commit/bd64e5543fb0efb74497c7df00507eab5ad1ce93))
* **seeder:** name an unconfigured language in adopt ([cbb2247](https://github.com/Bergert-Digital/pediment/commit/cbb2247d0ae47f6d925676f5c039a821283331ee))
* **seeder:** name planning problems as such, not verification ([362a7aa](https://github.com/Bergert-Digital/pediment/commit/362a7aa64ef687c5def0fa37f061a21dd6d52943))
* **seeder:** stop calling a real run that wrote nothing a dry run ([1e99ec7](https://github.com/Bergert-Digital/pediment/commit/1e99ec7bd31192aeaa78c9c93db77951f12e360c))

## [6.1.0](https://github.com/Bergert-Digital/pediment/compare/v6.0.0...v6.1.0) (2026-10-28)


### Features

* **mcp:** serve posts and custom post types ([d38c8b2](https://github.com/Bergert-Digital/pediment/commit/d38c8b20a5e2090187d8b77c88c00a80a42b4aac))
* **mcp:** serve posts and custom post types ([#145](https://github.com/Bergert-Digital/pediment/issues/145)) ([654005a](https://github.com/Bergert-Digital/pediment/commit/654005a611e35613ab7eea8a3c8d2767b9f57f83))
* **mcp:** write excerpt, menu order and featured image ([fe7a9c1](https://github.com/Bergert-Digital/pediment/commit/fe7a9c18b51f53f5b6bf5a7ca247c9df5a497aee))

## [6.0.0](https://github.com/Bergert-Digital/pediment/compare/v5.0.0...v6.0.0) (2026-10-21)


### ⚠ BREAKING CHANGES

* Pediment 6 removes the legacy-block converter ([#139](https://github.com/Bergert-Digital/pediment/issues/139))
* the legacy-block converter is removed: the Convert tab, the pediment/convert-legacy ability, wp pediment convert, the legacy content notice, and pediment_hero_variants() with its filter. Convert leftover content on Pediment 5.x before you update.

### Features

* Pediment 6 removes the legacy-block converter ([#139](https://github.com/Bergert-Digital/pediment/issues/139)) ([c2f70af](https://github.com/Bergert-Digital/pediment/commit/c2f70af4ca2b2950f3f0186ad50d8f5fca7748d4))
* remove the legacy-block converter ([b06ad56](https://github.com/Bergert-Digital/pediment/commit/b06ad56c24dae4f1bbef928429856b80f2ecec20))


### Refactors

* **policy:** stop naming the converter ([d5757f4](https://github.com/Bergert-Digital/pediment/commit/d5757f46f83335bb3e4d7194b552ea81ed37dc3a))

## [5.0.0](https://github.com/Bergert-Digital/pediment/compare/v4.4.0...v5.0.0) (2026-10-20)


### ⚠ BREAKING CHANGES

* Pediment 5 removes the 18 legacy blocks ([#132](https://github.com/Bergert-Digital/pediment/issues/132))
* the 18 legacy pediment/* presentation blocks are removed. Convert leftover content with Settings → Pediment Theme → Convert legacy blocks before or after updating.

### Features

* Pediment 5 removes the 18 legacy blocks ([#132](https://github.com/Bergert-Digital/pediment/issues/132)) ([983936b](https://github.com/Bergert-Digital/pediment/commit/983936b3406a1ff2b204fa4a6892ac026e354744))
* **policy:** reject legacy blocks in page writes ([e55e7b7](https://github.com/Bergert-Digital/pediment/commit/e55e7b785149be33c4a75bc09dbf81bfa52a4044))
* remove the 18 legacy blocks ([400f3b7](https://github.com/Bergert-Digital/pediment/commit/400f3b7ceacd5238933549f98522643f0fa72c79))
* warn admins about leftover legacy content ([cd99ae7](https://github.com/Bergert-Digital/pediment/commit/cd99ae7793e751b6218f9e76a1d3bf6e504a5595))


### Refactors

* **editor:** load the accent format for all blocks ([7d99485](https://github.com/Bergert-Digital/pediment/commit/7d994853c215ae8b0d9651501c16bb74125e1c26))
* keep hero variants for the converter only ([b40d099](https://github.com/Bergert-Digital/pediment/commit/b40d0993f7ae9bf7e4cb036c09e4ca77c484b637))

## [4.4.0](https://github.com/Bergert-Digital/pediment/compare/v4.3.0...v4.4.0) (2026-10-19)


### Features

* **kit:** validate blocks against WordPress 7.1 ([7772e81](https://github.com/Bergert-Digital/pediment/commit/7772e8199918a90cccdffebdcdbf4b9e3c4916ca))
* **kit:** validate blocks against WordPress 7.1 ([#130](https://github.com/Bergert-Digital/pediment/issues/130)) ([6b5adb8](https://github.com/Bergert-Digital/pediment/commit/6b5adb8c6d48f436b02b58fb1e799e382c7c930b))


### Bug Fixes

* **convert:** note dropped testimonial initials ([9659a3e](https://github.com/Bergert-Digital/pediment/commit/9659a3e43bc8839cea19178e75f161fa804ceda0))
* **kit:** pin WordPress 7.0 validator to element 6.40.1 ([f6ae641](https://github.com/Bergert-Digital/pediment/commit/f6ae641a50e8153ee8e9bd5da2601c8b0d59def2))

## [4.3.0](https://github.com/Bergert-Digital/pediment/compare/v4.2.0...v4.3.0) (2026-10-19)


### Features

* add pediment/icon and convert feature icons to it ([d4fdbee](https://github.com/Bergert-Digital/pediment/commit/d4fdbeebe704334058af728e7124c00a43652239))
* **convert:** mark converted card links with a class ([a72b313](https://github.com/Bergert-Digital/pediment/commit/a72b3138333a3ed8b4a052b4f3a75f3298ce13c1))
* pediment/icon block and centred cards for conversion ([#128](https://github.com/Bergert-Digital/pediment/issues/128)) ([bd5583e](https://github.com/Bergert-Digital/pediment/commit/bd5583e99d1964b6e1b7823e23d181d1c28fcb7e))
* reveal converted cards, steps and FAQ on scroll ([bfb6757](https://github.com/Bergert-Digital/pediment/commit/bfb675736e55f5bebea704ed8b7ad079b06076e3))


### Bug Fixes

* **convert:** keep hero stat metrics start-aligned ([ce74d6e](https://github.com/Bergert-Digital/pediment/commit/ce74d6e667e1596cc2bc39256cd108f11ae5a2da))
* **styles:** centre the card through typography.textAlign ([c1e194a](https://github.com/Bergert-Digital/pediment/commit/c1e194a720170c90a739ebf1e3b740373e2febe7))


### Refactors

* **convert:** centre feature cards in the card style ([ab431f4](https://github.com/Bergert-Digital/pediment/commit/ab431f41831d8c963eb870fbc67b1ecff1267000))

## [4.2.0](https://github.com/Bergert-Digital/pediment/compare/v4.1.0...v4.2.0) (2026-10-16)


### Features

* **convert:** mark converted section heads with a class ([31e9911](https://github.com/Bergert-Digital/pediment/commit/31e991144940496fcb7d850ff32311c75276e537))
* **convert:** mark converted section heads with a class ([#126](https://github.com/Bergert-Digital/pediment/issues/126)) ([5447cc6](https://github.com/Bergert-Digital/pediment/commit/5447cc62f3f13e499168d8c56b7109f6305eca82))

## [4.1.0](https://github.com/Bergert-Digital/pediment/compare/v4.0.0...v4.1.0) (2026-10-16)


### Features

* **blocks:** accept a palette slug as slider panelColor ([da9b753](https://github.com/Bergert-Digital/pediment/commit/da9b7537513b93790f341dc5c9094c6c9142667e))
* claim seed media, slider palette slugs, raw pattern seeding ([#124](https://github.com/Bergert-Digital/pediment/issues/124)) ([75b54c9](https://github.com/Bergert-Digital/pediment/commit/75b54c9513598fd28118d424b38b0b54a0207950))
* **seeder:** claim media by file name ([7db003c](https://github.com/Bergert-Digital/pediment/commit/7db003c900c000f6f6e8618e649f3751da7823e7))


### Bug Fixes

* **blocks:** accept only hex or palette slug as panelColor ([99642ae](https://github.com/Bergert-Digital/pediment/commit/99642ae72093a959a2fccb6d4f074b1b5f3f9261))
* **seeder:** keep claimed private media and safe advice ([a686221](https://github.com/Bergert-Digital/pediment/commit/a686221cc2e60b22ff4e39359153219b34dca45a))
* **seeder:** resolve pattern placeholders before block hooks ([d46cb55](https://github.com/Bergert-Digital/pediment/commit/d46cb553acf46f16de31fac69d7d6ea06d443077))
* **seeder:** stop when the raw pattern loader is missing ([cd15183](https://github.com/Bergert-Digital/pediment/commit/cd15183ffe6014d91cb1da2524eb7329e98720a8))

## [4.0.0](https://github.com/Bergert-Digital/pediment/compare/v3.10.0...v4.0.0) (2026-10-16)


### ⚠ BREAKING CHANGES

* replace AI chat with MCP tools and a site design layer ([#117](https://github.com/Bergert-Digital/pediment/issues/117))
* **seeder:** wp pediment seed no longer restores trashed seed media and no longer re-asserts site.logo over a logo chosen on the site.
* **seeder:** wp pediment seed no longer re-syncs navigation menus to the manifest, and _pediment_seed_mega_hash is no longer written or read.
* **seeder:** wp pediment seed never changes existing entries. The summary line drops the protected count, and --format=json drops the protected field and the protected count.
* the /pediment/v1/chat routes, the AI settings tab and the pediment_ai_* provider/model filters are gone.
* the in-editor AI chat sidebar is gone and WordPress 6.9 or newer is required.

### Features

* **abilities:** add pediment/convert-legacy ([db4fc24](https://github.com/Bergert-Digital/pediment/commit/db4fc24eda27ff0785e52be6f073c57471e676b9))
* **abilities:** convert at most 25 posts per all call ([a78a87a](https://github.com/Bergert-Digital/pediment/commit/a78a87a1171dd392bb3d5feeb5e335c4912c7d3f))
* **abilities:** read vocabulary and pages through abilities ([eaf8424](https://github.com/Bergert-Digital/pediment/commit/eaf84243f9610aa5c53e96b99bbd82eb967da4c7))
* **abilities:** write pages and styles through abilities ([ec0a8a0](https://github.com/Bergert-Digital/pediment/commit/ec0a8a054bf0f845642ebc34972359b75cb26adb))
* **blocks:** hide legacy blocks from the inserter ([3900dce](https://github.com/Bergert-Digital/pediment/commit/3900dcecea7a310e47a47c566d90d8614c1c9c4f))
* **cli:** add wp pediment convert ([e61ae8b](https://github.com/Bergert-Digital/pediment/commit/e61ae8b6c3716e41b05913c54e79a333c3a45527))
* **content:** add the content policy for page writes ([baf2a91](https://github.com/Bergert-Digital/pediment/commit/baf2a91174c3ffe03588b5f46e097f9842f5720e))
* **content:** editor reports invalid blocks per page ([b89d845](https://github.com/Bergert-Digital/pediment/commit/b89d8456ffe73a00dd23560c8ad4954d6a112121))
* **convert:** add the convert legacy blocks settings tab ([7e93e73](https://github.com/Bergert-Digital/pediment/commit/7e93e7316b91f807e45c365fcb0f0d0e4f2411cf))
* **convert:** add the converter and the text mappers ([173c3f9](https://github.com/Bergert-Digital/pediment/commit/173c3f9e83baccdfefc040693bd6f4304402f452))
* **convert:** convert stored posts with a revision first ([50a8d18](https://github.com/Bergert-Digital/pediment/commit/50a8d18c954f1ca6b51ffc6ace47722564bef5ff))
* **convert:** map faq, testimonials and logo cloud ([570027a](https://github.com/Bergert-Digital/pediment/commit/570027abfed27533e708329c112241569fc3dbd5))
* **convert:** map feature, stat and step grids ([c1dce67](https://github.com/Bergert-Digital/pediment/commit/c1dce67de49a256cd7773fbfef9c6390fc30647a))
* **convert:** map hero and call-to-action ([912948b](https://github.com/Bergert-Digital/pediment/commit/912948bde94c02c7b04d376f67e0ba689ed44766))
* **convert:** map media-text and image-caption ([92397a6](https://github.com/Bergert-Digital/pediment/commit/92397a6287c8c896bf3d536e1fa86caad88a5291))
* **convert:** warn before production, link the restore ([c29b2aa](https://github.com/Bergert-Digital/pediment/commit/c29b2aa774093379fbc66a61c0f9566f47cc4dac))
* **design:** add revisioned site-layer style store ([fb0fcf5](https://github.com/Bergert-Digital/pediment/commit/fb0fcf578d6bdf6e61c74393de7a4fc9db7a7931))
* **design:** add the converter's default styles ([b6f887e](https://github.com/Bergert-Digital/pediment/commit/b6f887e96cf7b4cb5317e4cc2891e8fafa026d6c))
* **design:** add token-rule style validator ([e1b6976](https://github.com/Bergert-Digital/pediment/commit/e1b69769fa59c21870f1e8bdfe055fc82d0bcdec))
* **design:** apply site-layer styles over theme and plugin ([819be41](https://github.com/Bergert-Digital/pediment/commit/819be412a0ae9a536754c349318a301ad11c2f20))
* **design:** export site styles to the client theme ([26759cc](https://github.com/Bergert-Digital/pediment/commit/26759cc9b7ce61f9b02b230b174f85950aaa52d3))
* **design:** list the merged design vocabulary ([63b67fc](https://github.com/Bergert-Digital/pediment/commit/63b67fc07e2a204d0a0dc1dee401aa2703071209))
* **design:** ship token-only default block styles ([da2d43a](https://github.com/Bergert-Digital/pediment/commit/da2d43a5742011e3ff40b360d6750d3224b7ba34))
* drop retired AI chat data on upgrade and uninstall ([2c6fa60](https://github.com/Bergert-Digital/pediment/commit/2c6fa60a11891589d2164273f21e26e97b49aed5))
* **kit:** validate block markup like the editor does ([fec8f0c](https://github.com/Bergert-Digital/pediment/commit/fec8f0c0c8916d6721dd75a6aebc2f25cf82adc5))
* **language:** read a post's language through the seam ([d8a14b4](https://github.com/Bergert-Digital/pediment/commit/d8a14b4e6b51b012e7ebf5756da004ad562efe6b))
* **mcp:** expose Pediment abilities as an MCP server ([a2aee79](https://github.com/Bergert-Digital/pediment/commit/a2aee7972bc99a80dc86ca9621d299d20bf958f2))
* remove the AI chat editor UI, require WordPress 6.9 ([45c0e9a](https://github.com/Bergert-Digital/pediment/commit/45c0e9a30f80b0c5ee31ff81002c572e26b64b16))
* remove the AI chat server, REST API and settings ([60a8e84](https://github.com/Bergert-Digital/pediment/commit/60a8e8440cac4b575e029b21f03d0b24f2bc7b47))
* replace AI chat with MCP tools and a site design layer ([#117](https://github.com/Bergert-Digital/pediment/issues/117)) ([7d8fac7](https://github.com/Bergert-Digital/pediment/commit/7d8fac7087f9389f125e01c150a311b31c222b9c))
* **seeder:** create menus once, never rewrite them ([30e6bad](https://github.com/Bergert-Digital/pediment/commit/30e6badda6930dc8cea73b1a2e73e6ba79d6280e))
* **seeder:** never restore media or reset the logo ([29043ba](https://github.com/Bergert-Digital/pediment/commit/29043baab81da07ebfc360a1c852748f597ca748))
* **seeder:** seed creates missing entries only ([8479eb5](https://github.com/Bergert-Digital/pediment/commit/8479eb58bf84b5b39391dd4bb2a20b0b7ba76b4a))
* **upgrade:** drop the 3.x seed hash meta once per site ([3f81b00](https://github.com/Bergert-Digital/pediment/commit/3f81b005db4740355675adaa0b87915727e60cd7))


### Bug Fixes

* **abilities:** empty translations as object, scope list-pages ([8ec82d1](https://github.com/Bergert-Digital/pediment/commit/8ec82d114b24dd8aedecb3a4cf2399dc1e80ee96))
* **abilities:** filter list-pages by language before the cap ([2a58c67](https://github.com/Bergert-Digital/pediment/commit/2a58c6769ee0204d5639adb96359d6db3c3ab322))
* **abilities:** guard translation_of and parent in write-page ([f8b438a](https://github.com/Bergert-Digital/pediment/commit/f8b438af8089043369f0559834603fdc4ce66e96))
* **abilities:** never return an autosave as the revision ([d07a80e](https://github.com/Bergert-Digital/pediment/commit/d07a80eba5f5bc03995ff439350bdbe184b53f96))
* **abilities:** reject markup in write-page titles ([14abce5](https://github.com/Bergert-Digital/pediment/commit/14abce5b29a094ad45fbabfd618149b41b15e5c8))
* **admin:** say what the seed adds and what it keeps ([4d9e953](https://github.com/Bergert-Digital/pediment/commit/4d9e953a35d2544ad083e20566c6a76836571707))
* **cli:** harden pattern file conversion ([1835beb](https://github.com/Bergert-Digital/pediment/commit/1835beba4b316ee89d7b616fbf91857c272ff314))
* **content:** check block markup as raw text ([adaf9df](https://github.com/Bergert-Digital/pediment/commit/adaf9dfa785dd25ee2f4e4735647e3d7bdafbcc6))
* **content:** scan block markup and every style leaf ([9240866](https://github.com/Bergert-Digital/pediment/commit/92408668626c8eb16cc62ca137886dc7bb8a840c))
* **content:** scan rendered markup across blocks ([14a0fbe](https://github.com/Bergert-Digital/pediment/commit/14a0fbe807ab9ac35e7fd8affcf6f835cdcce828))
* **content:** scan shortcode attributes after decoding ([9bbca2d](https://github.com/Bergert-Digital/pediment/commit/9bbca2debabef71edc679dcd31b79b66ed6377c2))
* **content:** scan the markup core renders, not stripped text ([fddd6b8](https://github.com/Bergert-Digital/pediment/commit/fddd6b86019c7f77a0789bb8d1fb22b676b67767))
* **content:** tell agents about the markup scan in rules ([f755fc5](https://github.com/Bergert-Digital/pediment/commit/f755fc5fddbd97ce475dcd0f747c26628918f550))
* **convert:** add a cursor so stuck posts cannot stall batches ([72f426d](https://github.com/Bergert-Digital/pediment/commit/72f426d88971f54bff885d57e999dd7d7694262c))
* **convert:** batch the convert tab and bind the nonce ([9c9df88](https://github.com/Bergert-Digital/pediment/commit/9c9df88930651992cc746cd1852c004b7edcfd46))
* **convert:** build stat paragraphs only when set ([6ed468b](https://github.com/Bergert-Digital/pediment/commit/6ed468bd4d2e52d360843d8f4d71e9f35583133d))
* **convert:** guard backups, kses and candidate scan ([95cd806](https://github.com/Bergert-Digital/pediment/commit/95cd806ef4568bd98c8148614b7ea29986c735ff))
* **convert:** keep legacy block metadata on the new block ([95a2a4c](https://github.com/Bergert-Digital/pediment/commit/95a2a4c9409aab7c2d4771de71018ba7987071de))
* **convert:** make grids collapse and left heads align ([5459a7a](https://github.com/Bergert-Digital/pediment/commit/5459a7ab84b542083090e141ce9bee7f474fc7e9))
* **convert:** refuse markup that cannot convert safely ([30243fa](https://github.com/Bergert-Digital/pediment/commit/30243faec36357bcbfadab35c9fad15146e4a37b))
* **convert:** split paragraph answers, refuse block HTML ([cb9e93c](https://github.com/Bergert-Digital/pediment/commit/cb9e93c48c63c8c8c1506cedf32a5d68edadde29))
* **convert:** trusted backup, canonical kses check ([fcdce28](https://github.com/Bergert-Digital/pediment/commit/fcdce2888d3f50a309cf52ed58271f37555faa47))
* **design:** apply site layer through theme data so the editor sees it ([850dd79](https://github.com/Bergert-Digital/pediment/commit/850dd79d390d2476acb18980b9ee78905aeb7fa0))
* **design:** close style validator bypasses ([c01bb40](https://github.com/Bergert-Digital/pediment/commit/c01bb4057b7ca6b61236fa991de5ee6f4786ef90))
* **design:** fail clearly when export has nothing to write ([ee610f3](https://github.com/Bergert-Digital/pediment/commit/ee610f3d9814fa8c08e638481b253720f856ef58))
* **design:** keep site overrides and theme values on export ([c9f6cde](https://github.com/Bergert-Digital/pediment/commit/c9f6cdee43fa4b91d3d1c6b1e03242c5772afe7f))
* **design:** readable pill text, report bad style files ([fb11d54](https://github.com/Bergert-Digital/pediment/commit/fb11d548bc298d48ab083288090d010c1504b294))
* **design:** refuse to store styles that fail to encode ([9225c26](https://github.com/Bergert-Digital/pediment/commit/9225c266b821da3494bdbee26db806470c49e9e2))
* **design:** register site style names when injecting ([f089778](https://github.com/Bergert-Digital/pediment/commit/f08977878f56fac451fb55570b7e68fe5a364d9a))
* **design:** save a first revision when a style is created ([b7d1bf0](https://github.com/Bergert-Digital/pediment/commit/b7d1bf01b869588e0982c60ac4aaba709ef495a2))
* **design:** stop dotted keys hiding style values from checks ([2532018](https://github.com/Bergert-Digital/pediment/commit/2532018aed50636607e8e51b435d6fc9e3d118c5))
* **design:** validate css by core grammar and block "&lt;" ([bb9529f](https://github.com/Bergert-Digital/pediment/commit/bb9529f68169fd184e8dee146f3b6a4d9c07f6d8))
* **kit:** make validator install race-safe, flag core typos ([b9d78ee](https://github.com/Bergert-Digital/pediment/commit/b9d78ee39e23ee7d2e2f19ad59225a4a4d26bd44))
* **kit:** resolve media placeholders in validated patterns ([106925b](https://github.com/Bergert-Digital/pediment/commit/106925bf858f5389e06feb8c4921aeb6200e9a74))
* **patterns:** reconvert landing, stat contrast on navy ([b63636c](https://github.com/Bergert-Digital/pediment/commit/b63636c10cb8a0456c86f876edba80f36a47b1aa))
* **seeder:** find seeded menus in every status ([2f8c5e8](https://github.com/Bergert-Digital/pediment/commit/2f8c5e82f3546d2eb9980fee22860a92bb8788e7))
* **seeder:** keep a trashed page out of reading settings ([4814540](https://github.com/Bergert-Digital/pediment/commit/4814540b10382d997fabd645f307e4b043cf1dac))
* **seeder:** keep reading settings and logo a person chose ([cda098b](https://github.com/Bergert-Digital/pediment/commit/cda098b42c855e8e451f3c96bcef7f9c634efbad))
* **seeder:** keep trashed pages out of new menus and groups ([3c3efa9](https://github.com/Bergert-Digital/pediment/commit/3c3efa9ae834cd851ea3a3ca901d641cbdfe3133))
* **seeder:** link translation groups only when a run adds ([495a31a](https://github.com/Bergert-Digital/pediment/commit/495a31a8c64f81dc13bb1b4797b4faf907a04f3b))
* **seeder:** name the language in MENUS notices ([a12d32e](https://github.com/Bergert-Digital/pediment/commit/a12d32efb05ad643e82a6f8e3c58d43d083c6497))
* **seeder:** never make a trashed attachment the logo ([f7bcacc](https://github.com/Bergert-Digital/pediment/commit/f7bcacce5411a9ddc63275c5e8f9b26492a207d4))
* **upgrade:** stop chat cleanup checks once done ([e742174](https://github.com/Bergert-Digital/pediment/commit/e74217452f00af6d932129c4bd040e6a341a5b67))


### Refactors

* **patterns:** convert shipped patterns to core blocks ([2a4323c](https://github.com/Bergert-Digital/pediment/commit/2a4323cba75d7cc76452b0ffade7da6ff7f7a74f))
* **seeder:** remove the content-arbitration hashes ([679bcda](https://github.com/Bergert-Digital/pediment/commit/679bcda006def63fbdc8de04537c81a2e44a684a))

## [3.10.0](https://github.com/Bergert-Digital/pediment/compare/v3.9.0...v3.10.0) (2026-10-07)


### Features

* **forms:** show submission details on the edit screen ([1531fa6](https://github.com/Bergert-Digital/pediment/commit/1531fa63b00a5feabe50964201ad1c2a4431aaf5))
* **forms:** show submission details; keep stored fields intact ([#110](https://github.com/Bergert-Digital/pediment/issues/110)) ([088f3ca](https://github.com/Bergert-Digital/pediment/commit/088f3ca5f685fbbd022a2e1aa22b9a244bef6148))


### Bug Fixes

* **forms:** keep umlauts, quotes and line breaks in fields ([74a38d2](https://github.com/Bergert-Digital/pediment/commit/74a38d2f61793d29cf21e018bd200e2ed976736b))

## [3.9.0](https://github.com/Bergert-Digital/pediment/compare/v3.8.0...v3.9.0) (2026-08-28)


### Features

* **wpml:** declare wp_navigation translatable, template parts shared ([ecf62ee](https://github.com/Bergert-Digital/pediment/commit/ecf62eedc05d31eae048890d18488fa7c658477a))
* **wpml:** detect WpmlProvider in the registry (Polylang &gt; WPML &gt; Null) ([85bc29d](https://github.com/Bergert-Digital/pediment/commit/85bc29da18f1de7f13eca5cb6f201293cce3001a))
* **wpml:** dropdown lists all languages + roomy menu card ([f075740](https://github.com/Bergert-Digital/pediment/commit/f075740b3bd3a447bb9838cc6f0bca8cdf1e1113))
* **wpml:** emit native wpml/language-switcher block ([0be5c30](https://github.com/Bergert-Digital/pediment/commit/0be5c30f73b253ef8482c3597ce4ecaf8dd69c07))
* **wpml:** hover-to-reveal dropdown language switcher ([ac091d8](https://github.com/Bergert-Digital/pediment/commit/ac091d8a31a49dd5c61c7226b47fd35441104057))
* **wpml:** WpmlProvider read methods (detection, languages, translationOf) ([c4e0e1f](https://github.com/Bergert-Digital/pediment/commit/c4e0e1ff54e901ce8d91a1d64d088e30e29994fd))
* **wpml:** WpmlProvider write methods via trid management ([eebe366](https://github.com/Bergert-Digital/pediment/commit/eebe366f1850a2083e9ee4330139ac891603aa1d))
* **wpml:** WpmlSetup configures WPML languages from the manifest ([b93be06](https://github.com/Bergert-Digital/pediment/commit/b93be0608f687e8100040b9a55e70771db6a1bc3))


### Bug Fixes

* **editor:** stop the AI normalizer from corrupting full-bleed section pages ([f5fc1b5](https://github.com/Bergert-Digital/pediment/commit/f5fc1b5dc0a75bb13f77f51a6b07f68a6965df30))
* **wpml:** bind per-language nav through the provider seam, not query scoping ([959a3ff](https://github.com/Bergert-Digital/pediment/commit/959a3ffd3ab8cccb9701cb273619682bac919381))
* **wpml:** emit a renderable language-switcher block ([86897d1](https://github.com/Bergert-Digital/pediment/commit/86897d1138594401f2c792b40b74d96696fae58f))
* **wpml:** resolve nav link permalinks in the target language ([ee44ea6](https://github.com/Bergert-Digital/pediment/commit/ee44ea68e6e85b953c131e32ce71ac9598b101a4))
* **wpml:** restore WPML language on every path in permalinkInLanguage ([a4cfe95](https://github.com/Bergert-Digital/pediment/commit/a4cfe95a6f4001782290052167cc8c86d6aab7f1))
* **wpml:** setup() gates the WPML branch on isLoaded(), not isActive() ([67af88f](https://github.com/Bergert-Digital/pediment/commit/67af88fd9d7e174f4b326e1cca59658bfc4389e7))
* **wpml:** switcher dropdown opens below as a compact toggle + panel; stop nav wrap ([d642524](https://github.com/Bergert-Digital/pediment/commit/d642524b705595fe60c9a885ab6cc17af32dd7a5))
* **wpml:** WpmlSetup triggers WPML config parse so headless deploys translate navigation ([0432ae4](https://github.com/Bergert-Digital/pediment/commit/0432ae4e0c4d33bec53d6bf8460608de526364c9))


### Refactors

* **language:** extract LanguageSetup interface + registry resolver ([a0a62a2](https://github.com/Bergert-Digital/pediment/commit/a0a62a2859f97b7456065a371f6f2da4b9d39b48))
* **language:** route current-language + switcher block through the provider seam ([2d4314d](https://github.com/Bergert-Digital/pediment/commit/2d4314df58d2c8a4b85d8c9abf1be121aa436487))

## [3.8.0](https://github.com/Bergert-Digital/pediment/compare/v3.7.0...v3.8.0) (2026-08-25)


### Features

* **blocks:** allow an image in place of the icon on feature cards ([e671b46](https://github.com/Bergert-Digital/pediment/commit/e671b46ae1018293eebf313f93b62e81c8f10952))

## [3.7.0](https://github.com/Bergert-Digital/pediment/compare/v3.6.0...v3.7.0) (2026-08-25)


### Features

* **chat:** advertise block attribute keys and surface AI provider status ([#95](https://github.com/Bergert-Digital/pediment/issues/95)) ([696b2e7](https://github.com/Bergert-Digital/pediment/commit/696b2e7d71501d3f4def0391938eabc48a090cc7))
* **chat:** advertise each block's attribute keys to the AI ([f3e4d60](https://github.com/Bergert-Digital/pediment/commit/f3e4d6067bcdd1962bca5dcbe33036624d6e9e1c))
* **chat:** surface mock mode and a missing API key in the sidebar ([248b8db](https://github.com/Bergert-Digital/pediment/commit/248b8db9497fdf79282b1db9d86f7d43b27e9288))


### Bug Fixes

* **chat:** deep-link the AI-status notice to the settings hub AI tab ([f9592c8](https://github.com/Bergert-Digital/pediment/commit/f9592c81801daa4f19216675d8343bd479f27241))

## [3.6.0](https://github.com/Bergert-Digital/pediment/compare/v3.5.0...v3.6.0) (2026-08-18)


### Features

* **chat:** let the AI set page excerpt and title for SEO ([0e338fd](https://github.com/Bergert-Digital/pediment/commit/0e338fd0d7e4cfbf583d8e4a95dc09c8bff89d85))

## [3.5.0](https://github.com/Bergert-Digital/pediment/compare/v3.4.0...v3.5.0) (2026-08-16)


### Features

* **chat:** visible "Delete history" button with confirmation ([ce94660](https://github.com/Bergert-Digital/pediment/commit/ce94660465eb0bdef4946908a9ed6ce396c039d1))


### Bug Fixes

* **mega-menu:** full-height mobile overlay, caret affordance, robust touch ([69bf4cd](https://github.com/Bergert-Digital/pediment/commit/69bf4cd70a4d5f230010b2bc1952508b720bfa52))

## [3.4.0](https://github.com/Bergert-Digital/pediment/compare/v3.3.0...v3.4.0) (2026-08-15)


### Features

* **dev:** auto-activate + seed the fixture theme on local env start ([b32d096](https://github.com/Bergert-Digital/pediment/commit/b32d0967ee77e11a1db4988a3d1837e35bcacbc9))
* **mega-menu:** fade the dropdown panel in and out ([621c06b](https://github.com/Bergert-Digital/pediment/commit/621c06b2ca2dbf8a44872ea01d6362845485240a))
* **mega-menu:** manifest-seeded mega menus, editable in the WP backend ([#90](https://github.com/Bergert-Digital/pediment/issues/90)) ([82bbc01](https://github.com/Bergert-Digital/pediment/commit/82bbc01a8275fe15fd4e0986c1e0a3b8995703f6))
* **seeder:** hash-arbitrated splice keeps editor-owned mega content ([fa112e9](https://github.com/Bergert-Digital/pediment/commit/fa112e934907ef59fd8d3319b801592dd6040ad9))
* **seeder:** MegaBlocks extraction and per-position hash arbitration ([bbf2992](https://github.com/Bergert-Digital/pediment/commit/bbf2992f331a512c82fe9848caa7be5cd734af95))
* **seeder:** record and carry forward mega ownership hashes on apply ([0887c7c](https://github.com/Bergert-Digital/pediment/commit/0887c7c08191b75b3373f0c02bab4461ee7d428b))
* **seeder:** serialize manifest mega items as pediment/mega-menu blocks ([23168a9](https://github.com/Bergert-Digital/pediment/commit/23168a9533d44a2cd0118e20d10675a6341bba32))
* **seeder:** validate the mega nav-item grammar in the manifest ([09cd6e6](https://github.com/Bergert-Digital/pediment/commit/09cd6e60ad1969e88b60cfe928bb364dbf512706))


### Bug Fixes

* **build:** stop the editor watch from wiping the blocks build ([95907fc](https://github.com/Bergert-Digital/pediment/commit/95907fcb844dfa49c22b37d5b0344d15dafd9843))
* **dev:** add root `start` script so Conductor's run command works ([dfbb723](https://github.com/Bergert-Digital/pediment/commit/dfbb723c7c3b5fbc8fb39e4462cf6b341b6ffb58))
* **env:** reliable wp-env start + correct client scaffold plugin/theme slugs ([#87](https://github.com/Bergert-Digital/pediment/issues/87)) ([22f9a73](https://github.com/Bergert-Digital/pediment/commit/22f9a73844398fde5c2c3a48a6d3b57ccf270a4c))
* **mega-menu:** anchor editor preview panel to trigger left edge ([000b119](https://github.com/Bergert-Digital/pediment/commit/000b11967505547febd5a24a8f2edf0f9fed3952))
* **mega-menu:** mark block with contentRole so it inserts in the nav editor ([88f0dd1](https://github.com/Bergert-Digital/pediment/commit/88f0dd1446b10de36e6347b2d6bf1ec03ad0886e))
* **seeder:** hash persisted nav row for mega ownership ([078c12b](https://github.com/Bergert-Digital/pediment/commit/078c12bec0c4274ff9e2f2b3185a6a634f0849b6))
* **seeder:** match Gutenberg attribute serialization for mega blocks ([dd53cc5](https://github.com/Bergert-Digital/pediment/commit/dd53cc5659b4f228f457cdf6681bd0da18df5a2b))
* **seeder:** verifier honors editor-owned mega content ([db67e56](https://github.com/Bergert-Digital/pediment/commit/db67e5618c41777ee95351e4cf09bea2bea8b31f))

## [3.3.0](https://github.com/Bergert-Digital/pediment/compare/v3.2.2...v3.3.0) (2026-08-08)


### Features

* **seeder:** a language_switcher nav item seeds the Polylang switcher block ([#85](https://github.com/Bergert-Digital/pediment/issues/85)) ([e394ceb](https://github.com/Bergert-Digital/pediment/commit/e394cebf77f980517fb56b2036e50ce79361ca72))

## [3.2.2](https://github.com/Bergert-Digital/pediment/compare/v3.2.1...v3.2.2) (2026-08-07)


### Bug Fixes

* keep template parts out of Polylang so the shared header renders under Pro ([#83](https://github.com/Bergert-Digital/pediment/issues/83)) ([6ad8732](https://github.com/Bergert-Digital/pediment/commit/6ad873217db793a1bd8a796fefe4ce0a58d110c2))

## [3.2.1](https://github.com/Bergert-Digital/pediment/compare/v3.2.0...v3.2.1) (2026-08-07)


### Bug Fixes

* **seeder:** re-assert a translation's shared slug once its language is set ([#81](https://github.com/Bergert-Digital/pediment/issues/81)) ([163a158](https://github.com/Bergert-Digital/pediment/commit/163a158d79a17e1c94e0b59040e98b8e07e201aa))

## [3.2.0](https://github.com/Bergert-Digital/pediment/compare/v3.1.0...v3.2.0) (2026-08-06)


### Features

* **seeder:** accept one level of nav children ([4c5169a](https://github.com/Bergert-Digital/pediment/commit/4c5169af38ea6d20421fe65b3b0e880874161fff))
* **seeder:** claim existing content, own the header, ship client blocks ([#78](https://github.com/Bergert-Digital/pediment/issues/78)) ([0e77d9d](https://github.com/Bergert-Digital/pediment/commit/0e77d9d29b28769535c9773db0ac1245d46cae93))
* **seeder:** nav submenus, plus the step 6b migration design and plans ([#80](https://github.com/Bergert-Digital/pediment/issues/80)) ([89e24c7](https://github.com/Bergert-Digital/pediment/commit/89e24c7db8a1b6859924bcfc3996cc9701129935))
* **seeder:** serialize nav children as submenus ([2b9cac6](https://github.com/Bergert-Digital/pediment/commit/2b9cac67067420c8294633019837c6c725cb54d7))


### Bug Fixes

* **seeder:** block writes on unresolved nav children ([b873b6a](https://github.com/Bergert-Digital/pediment/commit/b873b6abf317b3b95842e307d628a9ee36530782))

## [3.1.0](https://github.com/Bergert-Digital/pediment/compare/v3.0.0...v3.1.0) (2026-08-04)


### Features

* **i18n:** generate wpml-config.xml from block.json ([c04c321](https://github.com/Bergert-Digital/pediment/commit/c04c32101688560dcc15643a2459038b0eaac306))
* **language:** add LanguageProvider seam with a null implementation ([230241d](https://github.com/Bergert-Digital/pediment/commit/230241d9585799bcdd1e20fd399f15a004194567))
* **language:** add the Polylang adapter and per-language seeding (migration step 4) ([#69](https://github.com/Bergert-Digital/pediment/issues/69)) ([4cf7c87](https://github.com/Bergert-Digital/pediment/commit/4cf7c870f1f47cd11faea8e827df5a6daa4bd509))
* **language:** add the Polylang provider ([bcf5257](https://github.com/Bergert-Digital/pediment/commit/bcf52570e153ca4291294724b5c5c435dfa378de))
* **language:** configure Polylang from the manifest ([4e00993](https://github.com/Bergert-Digital/pediment/commit/4e009931e36d61be24a6f086d9b5901eeaedf2d2))
* **language:** detect Polylang in the registry ([002c5ce](https://github.com/Bergert-Digital/pediment/commit/002c5ceff1243e66de93d07fe5707fa9590c3741))
* **language:** give every language its own navigation menu ([245b676](https://github.com/Bergert-Digital/pediment/commit/245b676a0b8b109b789c11a8e5bffb6fd1ca388a))
* **seeder:** add seed meta keys and versioned content hash ([802e7e4](https://github.com/Bergert-Digital/pediment/commit/802e7e44b2a11076e3be3c7625792d964e338c46))
* **seeder:** add the declarative seeding engine (migration step 3) ([#67](https://github.com/Bergert-Digital/pediment/issues/67)) ([b97f932](https://github.com/Bergert-Digital/pediment/commit/b97f932a7501cfe83a5a596b1285c33ec6a2faad))
* **seeder:** add the wp-admin seeding tab with lifted PHP limits ([972f55a](https://github.com/Bergert-Digital/pediment/commit/972f55ae7373f280a2661473a7e956dc2760b1b6))
* **seeder:** add wp pediment adopt to export live pages ([1087785](https://github.com/Bergert-Digital/pediment/commit/1087785db48ebc561735020b9d8c47b8176513f4))
* **seeder:** add wp pediment seed with a readable dry-run plan ([25ad178](https://github.com/Bergert-Digital/pediment/commit/25ad178af88701ef4fcf5f137fc891cc298ca289))
* **seeder:** apply plan with slash-safe writes, hashing ([639c209](https://github.com/Bergert-Digital/pediment/commit/639c2091c05f28c2adeb15d05b7238dff622d4a7))
* **seeder:** declare per-language titles, slugs and patterns ([5c19c8b](https://github.com/Bergert-Digital/pediment/commit/5c19c8bbbdf35c074348bc15c56db0572c83d835))
* **seeder:** declare site languages in the manifest ([40f626f](https://github.com/Bergert-Digital/pediment/commit/40f626f4f1ee4a7313efb09dea33f0fe5681288c))
* **seeder:** diff desired against actual state into a plan ([4c79613](https://github.com/Bergert-Digital/pediment/commit/4c796133410d6bb062f40d49d4be4a4864d7f20c))
* **seeder:** link entry translation groups after the write loop ([01e3634](https://github.com/Bergert-Digital/pediment/commit/01e3634bf832a4bd54474b89e55122d835306ebc))
* **seeder:** load and validate the declarative seed manifest ([81a02f0](https://github.com/Bergert-Digital/pediment/commit/81a02f0367f7c3c9701dfc2ba4705a28a1906a61))
* **seeder:** refuse to seed into a mismatched language set ([9169445](https://github.com/Bergert-Digital/pediment/commit/916944557414bd14d18778db361d78405756d972))
* **seeder:** register manifest post types on init ([4a31356](https://github.com/Bergert-Digital/pediment/commit/4a313569e8065ae5e63c4ca6b259c5d4a0bd0bff))
* **seeder:** resolve content, titles and slugs per language ([44051e5](https://github.com/Bergert-Digital/pediment/commit/44051e59b668d5fca626def18dc9b0d70dbeed42))
* **seeder:** resolve desired and actual state by seed key ([49b4ffe](https://github.com/Bergert-Digital/pediment/commit/49b4ffeeacb018c68596b983e758a59e76906468))
* **seeder:** resolve entry content and media keys ([6293ccc](https://github.com/Bergert-Digital/pediment/commit/6293cccdf098278eaca26acff6bb3f77403df644))
* **seeder:** resolve navigation entities by seed key ([b326915](https://github.com/Bergert-Digital/pediment/commit/b326915eb4ba14934debf7c778440e675815bb7d))
* **seeder:** run the five seeding phases with post-condition checks ([73cf43e](https://github.com/Bergert-Digital/pediment/commit/73cf43ef81e39b0035823b36d6aaf42c3fc68028))
* **seeder:** seed media attachments and site logo ([f2cd00e](https://github.com/Bergert-Digital/pediment/commit/f2cd00e121a4ca47e4569e98ff6f11d6374b5e73))


### Bug Fixes

* **docs:** correct drifted back-references in the languages docs ([ebfddbb](https://github.com/Bergert-Digital/pediment/commit/ebfddbb57bcae64962e1a1275850b7a5588d5959))
* **e2e:** add the decoy-nav test that actually catches a deleted binding ([08dac8e](https://github.com/Bergert-Digital/pediment/commit/08dac8ec524ecbe10dcfcb92d10cd2971148cf11))
* **e2e:** make multilingual.spec.ts self-discriminating and precise ([dc91a07](https://github.com/Bergert-Digital/pediment/commit/dc91a075806391754d3aacd75fdf500587678539))
* **language-tests:** share the language-reseed fix across the whole Polylang suite ([893bedc](https://github.com/Bergert-Digital/pediment/commit/893bedcb1199e87de0d34c8967506d11367de044))
* **nav:** actually untag the legacy-nav fixture in the unscoped-fallback test ([b87942f](https://github.com/Bergert-Digital/pediment/commit/b87942fef9c0f05a2ca9fb62260de2bfeec5c154))
* **nav:** bind the header's navigation to the current language ([1069ee6](https://github.com/Bergert-Digital/pediment/commit/1069ee62ba8637050bf04fed3fc0d9e3b133987f))
* **nav:** don't override inner blocks, gate the default language, make primary filterable ([10b2e95](https://github.com/Bergert-Digital/pediment/commit/10b2e955a056ab04a2b2ff060ef758acc69c2efd))
* **nav:** set lang explicitly for the unscoped candidate; clean up added language in tests ([69a1a84](https://github.com/Bergert-Digital/pediment/commit/69a1a84318e4dfbd8a7d3a04c593dbadfa3e71e4))
* **polylang:** drop unsupported active_plugins rationale from bootstrap ([66dce0b](https://github.com/Bergert-Digital/pediment/commit/66dce0bb60464b1d55e44472249fb33c852bc84d))
* **seeder:** adopt into the language's own pattern file ([2974fc7](https://github.com/Bergert-Digital/pediment/commit/2974fc70e4d765783865d4ff42adc8855dabda11))
* **seeder:** anchor media id rewrites and roll back ([d01130c](https://github.com/Bergert-Digital/pediment/commit/d01130cdb53dfce1bc6881a040005b99569380c5))
* **seeder:** gate writes on a clean plan, verify navs ([aa58044](https://github.com/Bergert-Digital/pediment/commit/aa580447cfe2cb63fa460075051a2a26cfcb05ba))
* **seeder:** hash adopt's source the way the seeder will ([d49f0dc](https://github.com/Bergert-Digital/pediment/commit/d49f0dcc536712cee047dc007efcac659c733043))
* **seeder:** keep Adopter's filename in sync with its Slug header ([b67408a](https://github.com/Bergert-Digital/pediment/commit/b67408aa70bae7ffab92c174f142f03a0360d9c3))
* **seeder:** let phase 2 see non-public post types ([f09d77f](https://github.com/Bergert-Digital/pediment/commit/f09d77fb61538ef349e31a37416c0c671f0eb1d7))
* **seeder:** make `wp pediment seed --json` reachable ([6e699a2](https://github.com/Bergert-Digital/pediment/commit/6e699a2bfc14550fe6751df737af02801ff1e011))
* **seeder:** make adopt round-trip and keep placeholders ([c2fa4e7](https://github.com/Bergert-Digital/pediment/commit/c2fa4e7a19dd581734d6836422e8c3efcdee40a0))
* **seeder:** make the verifier's parent check able to fail ([4097b52](https://github.com/Bergert-Digital/pediment/commit/4097b52040e420b12a7148a958f99959fa8161ab))
* **seeder:** name the create parent change parent_key ([f769508](https://github.com/Bergert-Digital/pediment/commit/f769508010297ff71313bc5e87f1793eee815803))
* **seeder:** name the language in nav verification problems ([e1735c6](https://github.com/Bergert-Digital/pediment/commit/e1735c6da9c883bd00ae22b3228adf0069075350))
* **seeder:** never write a nav with an unresolved link ([46acc0e](https://github.com/Bergert-Digital/pediment/commit/46acc0e37583af380cb90372b334a054e41b4d9e))
* **seeder:** reject silent no-ops in per-language entry overrides ([e5f139a](https://github.com/Bergert-Digital/pediment/commit/e5f139aa4c83eda2cf3dfea840f09440cf58cae9))
* **seeder:** reject unknown manifest keys and empty slugs ([708c87f](https://github.com/Bergert-Digital/pediment/commit/708c87fcffcc436697d3f32a86e087a864be218f))
* **seeder:** report media failures and restore trashed ([a339d64](https://github.com/Bergert-Digital/pediment/commit/a339d6464df72c92451417ee9ed21b391fcefee7))
* **seeder:** report media keys the manifest never declares ([c64cf43](https://github.com/Bergert-Digital/pediment/commit/c64cf43f9663900421f7d03f960cf6eefd01b1ed))
* **seeder:** report missing nav links on every run ([90fda31](https://github.com/Bergert-Digital/pediment/commit/90fda31126aca8402d50876454a227573d5c6351))
* **seeder:** report partial applies and protected items ([b2c489e](https://github.com/Bergert-Digital/pediment/commit/b2c489ef23821a2a608e7cccbf5af98b60c6fb21))
* **seeder:** report slug collisions, protect client terms ([71cd2bb](https://github.com/Bergert-Digital/pediment/commit/71cd2bbb7cb8dc356607ed2eb5b69f2d038a4e3e))
* **seeder:** report unresolved media ids, not just urls ([4a67300](https://github.com/Bergert-Digital/pediment/commit/4a67300f6e4d60ea83054b7c95f6b739b7d82f72))
* **seeder:** restore a trashed nav instead of re-creating it ([9768ce2](https://github.com/Bergert-Digital/pediment/commit/9768ce2bf0fdf7a2cb98c82d2c166576ef937b68))
* **seeder:** sharpen plan records and restore semantics ([ffe97be](https://github.com/Bergert-Digital/pediment/commit/ffe97be45c8f84e5fd99461a37dede5d5359f9c0))
* **seeder:** stop the translation notice mis-firing on real slugs and defaults ([19050c6](https://github.com/Bergert-Digital/pediment/commit/19050c6ef21bad557d8ef28f049c78539df6f802))
* **seeder:** tag untagged posts on re-seed, verify language, guard partial groups ([f39ff60](https://github.com/Bergert-Digital/pediment/commit/f39ff60e249d0f293eda04bbcb2579675d7cbfe0))
* **seeder:** tell the operator when a seed is rejected ([51d8176](https://github.com/Bergert-Digital/pediment/commit/51d817602756ce9b513cee89435b5010ed6f5b09))


### Performance

* **seeder:** cache the manifest per request ([7a13d87](https://github.com/Bergert-Digital/pediment/commit/7a13d8750290fd37fc572e66aa1d7ed66b287939))

## [3.0.0](https://github.com/Bergert-Digital/pediment/compare/v2.4.1...v3.0.0) (2026-07-30)


### ⚠ BREAKING CHANGES

* fixture client theme; e2e runs against the plugin-served site
* retire the parent theme; the plugin is the product
* templates, patterns, tokens and assets ship from the plugin
* blocks and block build move into the plugin
* move the forms engine and settings hub into the plugin
* rename the plugin to Pediment (namespace, slug, asset)
* remove the contact-form stack in favor of the forms engine

### Features

* blocks and block build move into the plugin ([7cfcc4f](https://github.com/Bergert-Digital/pediment/commit/7cfcc4fbc9782239081f0aee8fd0ae073cd87e31))
* fixture client theme; e2e runs against the plugin-served site ([06b7e03](https://github.com/Bergert-Digital/pediment/commit/06b7e03181cbd97b34e4612531e4c832ee8bbbd1))
* move the forms engine and settings hub into the plugin ([09cfec6](https://github.com/Bergert-Digital/pediment/commit/09cfec6d12440e34e4ff67e73f4ae5b301a50aac))
* **release:** one artifact — pediment-plugin.zip, installs as plugins/pediment ([aeae73e](https://github.com/Bergert-Digital/pediment/commit/aeae73e765f195b8a59e44001871102d3fc6e896))
* remove the contact-form stack in favor of the forms engine ([1fdf789](https://github.com/Bergert-Digital/pediment/commit/1fdf7891be73f26499dd8810e1ef8b52b017d1da))
* rename the plugin to Pediment (namespace, slug, asset) ([dfc0044](https://github.com/Bergert-Digital/pediment/commit/dfc00447be60e8adeebb2d7fa79fe0a5bbb66e76))
* retire the parent theme; the plugin is the product ([bf76ffa](https://github.com/Bergert-Digital/pediment/commit/bf76ffabd5a76d858b4dc12aea0b42dfd4448979))
* templates, patterns, tokens and assets ship from the plugin ([a3d74b8](https://github.com/Bergert-Digital/pediment/commit/a3d74b80ff9a9f1149fb66caded55a4d05f9c268))


### Bug Fixes

* **ci:** regenerate the plugin lockfile with npm 10 so npm ci passes on CI ([f5a915b](https://github.com/Bergert-Digital/pediment/commit/f5a915b6757a0457d5200b975f67f5e5be108ccc))
* **editor:** gate the chat composer until the conversation loads; translate quick-action labels ([a42f4b6](https://github.com/Bergert-Digital/pediment/commit/a42f4b6a5d8fea2640e384c68fad426d22a23464))

## [2.4.1](https://github.com/Bergert-Digital/pediment/compare/v2.4.0...v2.4.1) (2026-07-30)


### Bug Fixes

* **plugin:** point at the live monorepo, drop stale changelog from zip ([a1aea24](https://github.com/Bergert-Digital/pediment/commit/a1aea242d51742a7530b16c9ece63e12e111d1c5))

## [2.4.0](https://github.com/Bergert-Digital/pediment/compare/v2.3.0...v2.4.0) (2026-07-30)


### Features

* **ci:** one workflow for theme and plugin ([dca6f38](https://github.com/Bergert-Digital/pediment/commit/dca6f381274d06707dc6621a72965c6a5b01dea2))
* **env:** one wp-env for theme and plugin with pinned mount names ([416c339](https://github.com/Bergert-Digital/pediment/commit/416c339b4249ef19801f32463762cf311c7da266))
* merge pediment-ai into a monorepo with one release line ([#63](https://github.com/Bergert-Digital/pediment/issues/63)) ([92c4460](https://github.com/Bergert-Digital/pediment/commit/92c4460ab7ec7c04e6be160a50bc6392d2b7d933))
* merge pediment-ai into the monorepo as plugin/ ([fe0f706](https://github.com/Bergert-Digital/pediment/commit/fe0f706c2927f7b0a9a5cd2124c28f244aa1bac5))
* **release:** one version line, two artifacts per tag ([64bf1f8](https://github.com/Bergert-Digital/pediment/commit/64bf1f86d0e40704ab6099fb3a8982b821a18028))
* **updates:** plugin updater follows monorepo releases ([7c07a76](https://github.com/Bergert-Digital/pediment/commit/7c07a76fb695485bceefcf5f5a925385348df695))

## [2.3.0](https://github.com/Bergert-Digital/pediment/compare/v2.2.0...v2.3.0) (2026-07-24)


### Features

* **forms:** destination dropdown in the Form block inspector ([359826c](https://github.com/Bergert-Digital/pediment/commit/359826cd8fa808e176fc6578ced848022ce331ce))
* **forms:** destination dropdown in the Form block inspector ([#60](https://github.com/Bergert-Digital/pediment/issues/60)) ([adc721d](https://github.com/Bergert-Digital/pediment/commit/adc721d05e94834f9cf72ce8cc24806e0c579fd3))
* **forms:** destination dropdown in the Form block inspector ([#61](https://github.com/Bergert-Digital/pediment/issues/61)) ([4fbeeb8](https://github.com/Bergert-Digital/pediment/commit/4fbeeb8f88299cc8fccbd9d363b78e9de53a549e))

## [2.2.0](https://github.com/Bergert-Digital/pediment/compare/v2.1.0...v2.2.0) (2026-07-07)


### Features

* **forms:** auto-assign destination ids + keep form input on error ([b6dc47a](https://github.com/Bergert-Digital/pediment/commit/b6dc47a69de8916350b9eadf19e38efa33507964))
* **forms:** auto-assign destination ids + keep form input on error ([6e0ca26](https://github.com/Bergert-Digital/pediment/commit/6e0ca26e1a52ca1cf3e6e42e72f8d4fcb504a00a))
* **forms:** move forms settings to Settings → Pediment Theme tabs ([4da98e2](https://github.com/Bergert-Digital/pediment/commit/4da98e23714f8339254f15b52a74bd74419e2855))
* **forms:** settings tabs, destination editing, form block fixes ([#58](https://github.com/Bergert-Digital/pediment/issues/58)) ([eb6423c](https://github.com/Bergert-Digital/pediment/commit/eb6423ccd31c9c3039bc651431452575e8fbe24c))
* Settings → Pediment Theme tabbed settings with destination editing ([#56](https://github.com/Bergert-Digital/pediment/issues/56)) ([42066a3](https://github.com/Bergert-Digital/pediment/commit/42066a3c786f85d188c3529464697a7110cde961))
* **settings:** reusable tabbed Pediment Theme options page framework ([a9d884e](https://github.com/Bergert-Digital/pediment/commit/a9d884e70e9a26a651f0d6569279df906204984b))


### Bug Fixes

* **forms:** apply block gap between fields, roomier default ([66fb378](https://github.com/Bergert-Digital/pediment/commit/66fb378b65e253b1afbe2e9a0ea5a07ff793d1de))
* **forms:** checkbox inline layout + field block-spacing; add dev commands ([#57](https://github.com/Bergert-Digital/pediment/issues/57)) ([a72f43e](https://github.com/Bergert-Digital/pediment/commit/a72f43e702dcfbc76b6e834b2580abb7780d4d2c))
* **forms:** give form fields room and honor Block Spacing ([c74dbf6](https://github.com/Bergert-Digital/pediment/commit/c74dbf6b6e6b029c5226a4358231d56b20172de2))
* **forms:** keep edit context on destination save error/test redirect ([f191f5e](https://github.com/Bergert-Digital/pediment/commit/f191f5e3c353aa5c22d8d5006af1daa3713f497a))
* **forms:** lay out checkbox field inline with its label ([133db55](https://github.com/Bergert-Digital/pediment/commit/133db55bc7b82bad921fc2c067fc5c51e1229e0f))
* restore in-sync package-lock.json to unblock npm ci in CI ([e38056a](https://github.com/Bergert-Digital/pediment/commit/e38056a164692d315de46f784a16dc12dd37cf45))

## [2.1.0](https://github.com/Bergert-Digital/pediment/compare/v2.0.0...v2.1.0) (2026-07-06)


### Features

* **404:** center page-not-found content vertically ([f9a232d](https://github.com/Bergert-Digital/pediment/commit/f9a232dd870aa8b08a4ef85e4ab3efe937e51edc))
* **404:** center page-not-found content vertically ([#53](https://github.com/Bergert-Digital/pediment/issues/53)) ([99101ab](https://github.com/Bergert-Digital/pediment/commit/99101ab69899bf9f1bbfa7f805d1f3e046889ab2))
* AI-generatable forms — Plan 1 (capture) ([#49](https://github.com/Bergert-Digital/pediment/issues/49)) ([76b4505](https://github.com/Bergert-Digital/pediment/commit/76b4505489f3ab0bca260ff24d7dd7fc4bcfab81))
* AI-generatable forms with delivery, plus Brand Settings removal ([#54](https://github.com/Bergert-Digital/pediment/issues/54)) ([aa1ca41](https://github.com/Bergert-Digital/pediment/commit/aa1ca4113ec1e516d5faf83c2770ee11c13a487f))
* encrypted secret store for form destinations ([575d7b9](https://github.com/Bergert-Digital/pediment/commit/575d7b9960ed1c2bff1050f232b67fcfdd6cb7b8))
* form delivery engine wired to submission storage ([4df3eb4](https://github.com/Bergert-Digital/pediment/commit/4df3eb40e333f396dfdb1dda08d00136ac1481d2))
* form destinations registry and validation gate ([d19d825](https://github.com/Bergert-Digital/pediment/commit/d19d825df6343e20fd121e251408a8fef2c028ea))
* form field-collection and validation helpers ([922daed](https://github.com/Bergert-Digital/pediment/commit/922daed112de7c4e247f6bdbabbfc807bb18ec44))
* form submission delivery — destinations, templated HTTP, secrets (Plan 2) ([#52](https://github.com/Bergert-Digital/pediment/issues/52)) ([0ca0b95](https://github.com/Bergert-Digital/pediment/commit/0ca0b95d36fb33e31a9f968670f5d880159fe20f))
* form submission retention cron ([a649398](https://github.com/Bergert-Digital/pediment/commit/a64939842f1b76ad9a4131d3e043e9e17f7226d4))
* form_submission CPT, persistence, and admin columns ([2d3950b](https://github.com/Bergert-Digital/pediment/commit/2d3950bd2b22c1776f2405400907bc3e5e734608))
* **forms-settings:** send-test dry-run for destinations ([613b032](https://github.com/Bergert-Digital/pediment/commit/613b03255bfa9253377bb3de7ea4c521887ded5c))
* generic form submission REST endpoint with server-authoritative validation ([3c43246](https://github.com/Bergert-Digital/pediment/commit/3c432469b50540708543983945294996275a7be0))
* HTTPS + SSRF guard for form delivery URLs ([0fe96ad](https://github.com/Bergert-Digital/pediment/commit/0fe96ad49b62db2de640766bb60a6061200c7005))
* pediment/form block with server-authoritative submission wiring ([3a9abe9](https://github.com/Bergert-Digital/pediment/commit/3a9abe98a4fb4c7072065874caa9070c5cd01ae5))
* pediment/form-field block ([dceb595](https://github.com/Bergert-Digital/pediment/commit/dceb5952deb4e718883ce7843ae98bf9d6be51a1))
* provider presets for form destinations ([d3b044d](https://github.com/Bergert-Digital/pediment/commit/d3b044ddc926f3a300762a19ad5e69155a01805c))
* retention setting + delivery status column and retry ([df337e0](https://github.com/Bergert-Digital/pediment/commit/df337e028c40de5bf5210f902d4764af9b8874b3))
* Settings → Forms admin UI for destinations and secrets ([2146ff1](https://github.com/Bergert-Digital/pediment/commit/2146ff1f166ae5f73c78e928436641b5a8393872))
* structural token templating for form delivery ([4976391](https://github.com/Bergert-Digital/pediment/commit/497639166823f4019bf014a6aa9cfcbb9574ee4f))


### Bug Fixes

* add novalidate to form so server-side validation is authoritative ([1f5eca7](https://github.com/Bergert-Digital/pediment/commit/1f5eca71e980ad3c6848292a452b6cf4157046b3))
* explicit esc_attr on data-success, harden honeypot hiding, null-guard submit button ([c2c8526](https://github.com/Bergert-Digital/pediment/commit/c2c85267f96521149269d8c5f6d7a2fe63390e07))
* **form-field:** allow editing blank lines in the Options textarea ([4d1e32d](https://github.com/Bergert-Digital/pediment/commit/4d1e32ddf69439fe65b12135015f5f974243aeea))
* **form-field:** harden options handling against malformed AI input ([b0f4420](https://github.com/Bergert-Digital/pediment/commit/b0f4420d43ee2eac73b1e4a94ba2740cd7bbcc29))
* **form:** editor preview structure + field/button theming ([1b56f47](https://github.com/Bergert-Digital/pediment/commit/1b56f4751cd00abdc4f176db440f2757f13cd7ee))
* **forms-destinations:** use json_last_error for body validity (accept JSON null) ([e1b5c22](https://github.com/Bergert-Digital/pediment/commit/e1b5c22e8f257dcff93a535ea66dc28763b5fa71))
* **forms-secrets:** consistent name normalization in set/get + guards ([5c4ce0c](https://github.com/Bergert-Digital/pediment/commit/5c4ce0cd5c8a69796b254306c824a0a4518340c6))
* **forms-template:** single-pass token resolution to block field-value token injection ([129aeef](https://github.com/Bergert-Digital/pediment/commit/129aeeff48710f035641c4929a4865502f09a1a3))
* **form:** serialize inner field blocks on save (InnerBlocks.Content) ([4f6a696](https://github.com/Bergert-Digital/pediment/commit/4f6a6965f2887bbf6c3bcb1421e5f3072ada6f8d))
* **forms:** precise validator error keys, scan header keys, preset+style nits ([72b2353](https://github.com/Bergert-Digital/pediment/commit/72b23531bad53fc44ff45782b000ef766ebf6f94))
* guard retention cron against strtotime false (mass-delete blast radius) ([8fcf47a](https://github.com/Bergert-Digital/pediment/commit/8fcf47a40beec33ad73e7980e3578dc6c3d6faae))
* move form-field help text out of label (a11y) + add name-fallback test ([f840a22](https://github.com/Bergert-Digital/pediment/commit/f840a22f99eb592eb84502496ca8d2673180967f))
* stop forcing pretty permalinks on activation ([#47](https://github.com/Bergert-Digital/pediment/issues/47)) ([a33effd](https://github.com/Bergert-Digital/pediment/commit/a33effda7bc3b3dd84af9ac9b385b48117ae94ed))
* stop forcing pretty permalinks on activation ([#47](https://github.com/Bergert-Digital/pediment/issues/47)) ([#48](https://github.com/Bergert-Digital/pediment/issues/48)) ([e0d99e4](https://github.com/Bergert-Digital/pediment/commit/e0d99e4647b10ddc8a5f26801066d9707e90a140))
* store pending delivery status + sanitize fields at storage time ([5cbc8d8](https://github.com/Bergert-Digital/pediment/commit/5cbc8d8654f49814334c9621f77aba99882e3c4f))
* surface submission field values in form_submission admin list ([bcc2e21](https://github.com/Bergert-Digital/pediment/commit/bcc2e2151bdfb88e37ade817b4ef081a35684146))
* surface submission field values in form_submission admin list (fixes e2e) ([#50](https://github.com/Bergert-Digital/pediment/issues/50)) ([de0b8e7](https://github.com/Bergert-Digital/pediment/commit/de0b8e7124baa97686551698e76541a6ea1462be))


### Refactors

* remove Brand Settings, make social-links block self-contained ([1bfe695](https://github.com/Bergert-Digital/pediment/commit/1bfe695d74e14da0f3564588b0221287bf71fcf3))

## [2.0.0](https://github.com/Bergert-Digital/pediment/compare/v1.0.0...v2.0.0) (2026-06-29)


### ⚠ BREAKING CHANGES

* constrain post-content layout on page & front-page templates ([#45](https://github.com/Bergert-Digital/pediment/issues/45))
* constrain post-content layout on page & front-page templates ([#44](https://github.com/Bergert-Digital/pediment/issues/44))
* Page and front-page content is now width-constrained. Blocks authored as bare full-width content will render at contentSize (720px) and must opt into align:wide / align:full for wider treatment. Child themes can drop manual width-cap wrappers (.wc-wrap or equivalent) for blocks inside page/front-page content.

### Features

* constrain post-content layout on page & front-page templates ([369d141](https://github.com/Bergert-Digital/pediment/commit/369d1418bfcf30a6119a75d17a5a7eac390482d9))
* constrain post-content layout on page & front-page templates ([#44](https://github.com/Bergert-Digital/pediment/issues/44)) ([2668ed0](https://github.com/Bergert-Digital/pediment/commit/2668ed08e6fab5c0f32c4fbddefc7e4743bab8e2))
* constrain post-content layout on page & front-page templates ([#45](https://github.com/Bergert-Digital/pediment/issues/45)) ([504629a](https://github.com/Bergert-Digital/pediment/commit/504629a492d2201f8601ec7554cd09a4ccb8a570))

## [1.0.0](https://github.com/Bergert-Digital/pediment/compare/v0.9.0...v1.0.0) (2026-06-26)


### ⚠ BREAKING CHANGES

* untangle content-seeding into the child theme ([#42](https://github.com/Bergert-Digital/pediment/issues/42))
* the parent no longer seeds a default navigation entity; the child theme owns it.
* `wp pediment seed` is removed. A freshly activated theme now bootstraps itself; demo content is seeded from the child theme.

### Features

* run framework bootstrap on activation (brand defaults, header part, permalinks) ([4057a85](https://github.com/Bergert-Digital/pediment/commit/4057a858ad1e5e32b54cdf4b19ca0ef498728f22))


### Refactors

* remove default-nav seeding (relocates to child theme) ([d949c45](https://github.com/Bergert-Digital/pediment/commit/d949c4545137b412be5d0919d034b4f31be2a2bb))
* remove wp pediment seed command, demo content, and demo tests ([e84f24e](https://github.com/Bergert-Digital/pediment/commit/e84f24ee2741e0b8fb42e74581e7107b757dd77e))
* untangle content-seeding into the child theme ([#42](https://github.com/Bergert-Digital/pediment/issues/42)) ([3e64871](https://github.com/Bergert-Digital/pediment/commit/3e648717cccb18bde71d6fe44dc9437700070899))

## [0.9.0](https://github.com/Bergert-Digital/pediment/compare/v0.8.1...v0.9.0) (2026-06-24)


### Features

* add block translation config ([89241c7](https://github.com/Bergert-Digital/pediment/commit/89241c7c5d41e948e88b2155f02b58a7515f5c6a))


### Bug Fixes

* align lockfile with CI npm ([aeeb3a0](https://github.com/Bergert-Digital/pediment/commit/aeeb3a0f7283178a0edfcdca33e7f33f61be06b8))
* sync package lock ([f0d760b](https://github.com/Bergert-Digital/pediment/commit/f0d760b55a68392e98a293585a50b291791a0215))

## [0.8.1](https://github.com/Bergert-Digital/pediment/compare/v0.8.0...v0.8.1) (2026-06-23)


### Bug Fixes

* **single:** restore single-post masthead spacing & article polish ([#36](https://github.com/Bergert-Digital/pediment/issues/36)) ([c4a6ad4](https://github.com/Bergert-Digital/pediment/commit/c4a6ad43bc89f93f471c4321e99ed24ffac299c7))

## [0.8.0](https://github.com/Bergert-Digital/pediment/compare/v0.7.0...v0.8.0) (2026-06-22)


### Features

* image/content slider block ([#33](https://github.com/Bergert-Digital/pediment/issues/33)) ([88bdb26](https://github.com/Bergert-Digital/pediment/commit/88bdb26790664934d27ecf246e0e6b5b1aa06a6a))

## [0.7.0](https://github.com/Bergert-Digital/Pediment/compare/v0.6.0...v0.7.0) (2026-06-19)


### Features

* single post template + retire manual update-check panel ([#30](https://github.com/Bergert-Digital/Pediment/issues/30)) ([25d6232](https://github.com/Bergert-Digital/Pediment/commit/25d62322c21e94e7db972f2ab4770a6e9b082506))
* **single:** proper single post template with editorial masthead ([#29](https://github.com/Bergert-Digital/Pediment/issues/29)) ([88fa9fd](https://github.com/Bergert-Digital/Pediment/commit/88fa9fd5a9c7bcfeb138ebdc0f99c413288246b5))


### Refactors

* **updates:** retire manual "Check for theme updates" panel ([#28](https://github.com/Bergert-Digital/Pediment/issues/28)) ([c6b6742](https://github.com/Bergert-Digital/Pediment/commit/c6b67423652c3de364dd211197c2ade9c05e7134))

## [0.6.0](https://github.com/Bergert-Digital/Pediment/compare/v0.5.2...v0.6.0) (2026-06-18)


### Features

* **mega-menu:** honor editor color & typography on the trigger ([6906b89](https://github.com/Bergert-Digital/Pediment/commit/6906b89f55ff2985fe486b45786bd5a9c3cc2c1e))
* **mega-menu:** trigger follows Navigation block text color ([#26](https://github.com/Bergert-Digital/Pediment/issues/26)) ([0fe8174](https://github.com/Bergert-Digital/Pediment/commit/0fe817480374f316f334bf9915ab88c65ee2e9ed))


### Bug Fixes

* **mega-menu:** inherit nav text color on trigger ([a44d5db](https://github.com/Bergert-Digital/Pediment/commit/a44d5dbe82515f4762c6a19cacf9c5785c271902))


### Refactors

* **mega-menu:** drop the trigger's own color/typography supports ([c6f1e23](https://github.com/Bergert-Digital/Pediment/commit/c6f1e2343fbcea11bc8980df6fd40e10a4ba45eb))

## [0.5.2](https://github.com/Bergert-Digital/Pediment/compare/v0.5.1...v0.5.2) (2026-06-17)


### Bug Fixes

* make seeded SVG logo render and self-heal in the Site Editor ([#23](https://github.com/Bergert-Digital/Pediment/issues/23)) ([89389c6](https://github.com/Bergert-Digital/Pediment/commit/89389c6150380d901b62aca2d4706c40c279ec1a))
* **seed:** self-heal dimensionless SVG logo on admin_init ([f8ac87e](https://github.com/Bergert-Digital/Pediment/commit/f8ac87e44bd45b44797999ef92c9af3bd7e1c520))
* **seed:** store SVG dimensions on demo logo so it renders in the editor ([5e88862](https://github.com/Bergert-Digital/Pediment/commit/5e88862514793d9a52302640db9ae73485e280a8))

## [0.5.1](https://github.com/Bergert-Digital/Pediment/compare/v0.5.0...v0.5.1) (2026-06-16)


### Bug Fixes

* **release:** bump style.css version header so prod detects updates ([#21](https://github.com/Bergert-Digital/Pediment/issues/21)) ([9c9af20](https://github.com/Bergert-Digital/Pediment/commit/9c9af20cda491a27141018eafefb007a78393d17))
* **release:** let release-please bump the style.css version header ([0195d5f](https://github.com/Bergert-Digital/Pediment/commit/0195d5fe6005b1483c8ac3a5422f17477170fb36))

## [0.5.0](https://github.com/Bergert-Digital/Pediment/compare/v0.4.0...v0.5.0) (2026-06-16)


### Features

* **blocks:** add inserter previews to Pediment blocks ([a65221c](https://github.com/Bergert-Digital/Pediment/commit/a65221ccaa4d8a1f12e13bd07b194273524e381e))
* **blocks:** add pediment/media-text block for copy beside an image ([fce5d9d](https://github.com/Bergert-Digital/Pediment/commit/fce5d9de9e1edb12417ca024de5b05a24e608592))
* **blocks:** media-text block plus editor alignment & inserter UX ([#19](https://github.com/Bergert-Digital/Pediment/issues/19)) ([b16ed88](https://github.com/Bergert-Digital/Pediment/commit/b16ed8886c0b470fdc630d1d578737b06426a6c0))
* **editor:** default Row and Grid group variations to wide alignment ([a55bcbb](https://github.com/Bergert-Digital/Pediment/commit/a55bcbbcd2362ff97cb13223fcc797abb7a2d574))
* **feature-grid:** center orphan rows and lay out 4 items as 2+2 ([dbdbecf](https://github.com/Bergert-Digital/Pediment/commit/dbdbecf1a8fae06b68730615e2be6594687d8368))
* **media-text:** allow buttons inside the text column ([64732f1](https://github.com/Bergert-Digital/Pediment/commit/64732f150996e7ae126ebf36cc92afafb3f286ec))
* **media-text:** show a placeholder slot when no image is set ([0784f68](https://github.com/Bergert-Digital/Pediment/commit/0784f681d487a1311d1ba5faaf06b6581f15c413))
* **section-head:** add optional max-width override ([c0cc339](https://github.com/Bergert-Digital/Pediment/commit/c0cc3393e74d296d2c141c573c8229f63c1f811f))


### Bug Fixes

* **image-caption:** use MediaPlaceholder for empty state ([c96a11c](https://github.com/Bergert-Digital/Pediment/commit/c96a11c8576c19f32cdda40781bef6eda4133f59))
* **media-text:** persist inner blocks so the copy survives save ([bd91108](https://github.com/Bergert-Digital/Pediment/commit/bd911088d0fe4ec9e930cdc59da9d32894d0d06f))
* **section-head:** make width follow block alignment instead of a fixed cap ([e7e4652](https://github.com/Bergert-Digital/Pediment/commit/e7e4652abe7eb3264c55d95d03cb680877ffed89))

## [0.4.0](https://github.com/Bergert-Digital/Pediment/compare/v0.3.0...v0.4.0) (2026-06-12)


### Features

* **theme:** add screenshot.png preview image ([ac7683e](https://github.com/Bergert-Digital/Pediment/commit/ac7683e82bf3ec85224390e5b03e7fb2326d9087))
* **updates:** admin notice reporting manual check results ([5786513](https://github.com/Bergert-Digital/Pediment/commit/57865132b5963f53b70b340d96e94f5ad6e121e0))
* **updates:** admin-post handler for manual update checks ([ca08a78](https://github.com/Bergert-Digital/Pediment/commit/ca08a7865120cb21738b11250856b61b32f0d250))
* **updates:** core logic for manual theme update checks ([873f8ab](https://github.com/Bergert-Digital/Pediment/commit/873f8ab60edf36ce74d12baf9f86d446e53ce50c))
* **updates:** expose parent PUC checker via pediment_update_checkers ([aa19661](https://github.com/Bergert-Digital/Pediment/commit/aa1966181f35546b206859b606c646cece81ae6e))
* **updates:** manual "Check for theme updates" button on Updates screen ([#18](https://github.com/Bergert-Digital/Pediment/issues/18)) ([c314d69](https://github.com/Bergert-Digital/Pediment/commit/c314d6990ef2365d7a600d6f3a779c9465e7d83c))
* **updates:** render check-for-updates section on Updates screen ([408d427](https://github.com/Bergert-Digital/Pediment/commit/408d427cc88f1bb13f7ae5a5002e617c69bafd23))


### Bug Fixes

* **patterns:** stop pattern picker modal on new page creation ([ada6e67](https://github.com/Bergert-Digital/Pediment/commit/ada6e67f8888856642d53fa50861b1f72ce68bbc))
* **updates:** validate checker entry shape in pediment_update_checkers ([23f9178](https://github.com/Bergert-Digital/Pediment/commit/23f91782713b5f8da6b52ac2836ab5a320e17d00))


### Refactors

* **updates:** co-locate notice hook with callback, complete file header ([20bce95](https://github.com/Bergert-Digital/Pediment/commit/20bce95b0ead1246cc1d02d3b000adfc0700a38f))

## [0.3.0](https://github.com/Bergert-Digital/Pediment/compare/v0.2.1...v0.3.0) (2026-06-11)


### Features

* **blocks:** add pediment/testimonial child block ([62327ea](https://github.com/Bergert-Digital/Pediment/commit/62327eac537d2f04a3790f2efca90b03511f0ff9))
* **blocks:** add pediment/testimonial-grid parent block ([6c9e3fe](https://github.com/Bergert-Digital/Pediment/commit/6c9e3fe3c2c6aaecef8c388bef1a003cd2567bb2))
* **blocks:** constrain attribute schemas for AI generation ([35d91bd](https://github.com/Bergert-Digital/Pediment/commit/35d91bd1c262dd0e2c11499d62bc3e296d8fe2c9))
* **feature:** use IconPicker; drop icon enum and text input ([9a6a391](https://github.com/Bergert-Digital/Pediment/commit/9a6a3914051fa3c2ac25cf879d9e8ebfd49b35fe))
* icon picker, testimonial/stat-grid blocks, theme self-updates ([#14](https://github.com/Bergert-Digital/Pediment/issues/14)) ([40b7606](https://github.com/Bergert-Digital/Pediment/commit/40b760613fcccd18d2bf9fd4219feb090b8e441f))
* **icons:** add shared IconPicker editor component ([de58b39](https://github.com/Bergert-Digital/Pediment/commit/de58b39f6c4dcaf4d725ff8d9f93b816c0ddf5fc))
* **icons:** catalog loads {markup, meta, set} bundle; manifest-driven preview ([c51db5e](https://github.com/Bergert-Digital/Pediment/commit/c51db5e8ce124f3841f61dd356e648d8d3b455bf))
* **icons:** category filter + progressive scroll in the IconPicker ([64380a1](https://github.com/Bergert-Digital/Pediment/commit/64380a1a2fe534acfbdb1a3314e38b77f305f28f))
* **icons:** derive category list + display labels from meta ([f0c7054](https://github.com/Bergert-Digital/Pediment/commit/f0c705419df89011be76074ac1e58997fc5fa4ed))
* **icons:** drive svg viewBox/attrs from icon-set manifest (set-agnostic render) ([4b45279](https://github.com/Bergert-Digital/Pediment/commit/4b45279dd63d3c749f5089b500a8240e1c6d1b28))
* **icons:** expose catalog JSON url to the block editor ([3fab6e5](https://github.com/Bergert-Digital/Pediment/commit/3fab6e5b4642bbaf4f6c9ff6ff0e1571cd722b6d))
* **icons:** filter by category and match tags, not just slugs ([c21e90c](https://github.com/Bergert-Digital/Pediment/commit/c21e90c72d157e0818b2fc94200db6ccbe417346))
* **icons:** generate full Phosphor catalog data files ([8c4eac6](https://github.com/Bergert-Digital/Pediment/commit/8c4eac6ce82dbc3b8ca60af758228abce7234c12))
* **icons:** generate icon-meta + icon-set manifest; rename data to icon-markup.* ([c8c6d78](https://github.com/Bergert-Digital/Pediment/commit/c8c6d78fb7d5009d1762384f2f12f9ab61785fe9))
* **icons:** render icons inline from generated map; drop sprite use ([7923bb6](https://github.com/Bergert-Digital/Pediment/commit/7923bb672c3d91a4ad777c5f33145020da604f72))
* **icons:** style the IconPicker editor control ([ebd9f6a](https://github.com/Bergert-Digital/Pediment/commit/ebd9f6a5f0e8dd9bbc2435221d11fb31ee00dfaf))
* **mega-menu:** use IconPicker for column icons ([10472ab](https://github.com/Bergert-Digital/Pediment/commit/10472ab224bbe3fe7ec1c2ddf887f47f304e34c7))
* **patterns:** landing page uses testimonial-grid; update pattern + e2e tests ([413019e](https://github.com/Bergert-Digital/Pediment/commit/413019eaf5d93a521d66c3eae6da5bedeeebde43))
* **stat-grid:** add stat-grid container, lock stat to it ([7b689a3](https://github.com/Bergert-Digital/Pediment/commit/7b689a374b14f13d92c3e746dfec118e88d68e1d))
* **updates:** add ThemeUpdater wiring PUC to GitHub releases ([036d7fc](https://github.com/Bergert-Digital/Pediment/commit/036d7fc5a181064cb8b18c2f31014be7bd847302))
* **updates:** bootstrap ThemeUpdater + add Update URI header ([cdabf09](https://github.com/Bergert-Digital/Pediment/commit/cdabf098493f49b2ac66bb812d75188e3c2af0a7))


### Bug Fixes

* **icons:** cwd-independent lint, CI wiring, shebangs, tidy build script ([07e2c09](https://github.com/Bergert-Digital/Pediment/commit/07e2c0909159055413d60eaae6a1098207642641))
* **icons:** drop JSDoc block from IconPreview to satisfy lint:js ([900dd94](https://github.com/Bergert-Digital/Pediment/commit/900dd946130ba56a860eda38f26d0949e5200505))
* **icons:** route remaining icon output through pediment_icon() ([49ad93a](https://github.com/Bergert-Digital/Pediment/commit/49ad93a0d1d84ec4aec54fbf7c607508988a806e))
* **icons:** scope IconPicker observer to grid; drop dead hint CSS ([cd7821b](https://github.com/Bergert-Digital/Pediment/commit/cd7821b6c4aa9734297f42a03a7982c97f708728))
* **icons:** use WordPress blue for IconPicker hover, keep glyph white ([77705ec](https://github.com/Bergert-Digital/Pediment/commit/77705ec294e010b22ed413b8b2d5dbf5c5834850))
* **section-head:** use padding so header gap survives layout reset ([48d21dd](https://github.com/Bergert-Digital/Pediment/commit/48d21dd3b2bf2bd2f7b4b8d0553de4e68a9f05e9))
* **steps:** lay steps out in a responsive grid ([6cd6b15](https://github.com/Bergert-Digital/Pediment/commit/6cd6b152987e85db94041c1acc779895e8aef3cf))
* **testimonial:** use surface token for initials; align render.php assignments ([823aa7e](https://github.com/Bergert-Digital/Pediment/commit/823aa7ebc2e3c04b0c50a50b4860b95452ec1750))
* **theme:** contain AI-composed sections to theme widths ([e53f644](https://github.com/Bergert-Digital/Pediment/commit/e53f6449a178f0d4f3700dfa1b348328aa1e931e))
* **updates:** register updater unconditionally; add strict_types ([8328aa0](https://github.com/Bergert-Digital/Pediment/commit/8328aa06eb757c97d3555918603a3e54224e2936))


### Refactors

* **icons:** defensive category guard + graceful lint summary for foreign sets ([2250b51](https://github.com/Bergert-Digital/Pediment/commit/2250b51faab9b6a2f3f7449564e3ac40d4bdc43e))
* **icons:** fetch-once effect dep in IconPreview; test retry-after-error ([82f04db](https://github.com/Bergert-Digital/Pediment/commit/82f04db406ddb161850c89586c8f53630a2206aa))
* **icons:** harden svgAttrs guard, de-Phosphor docblocks, add test file docblock ([a5016c9](https://github.com/Bergert-Digital/Pediment/commit/a5016c9f67aba289e2fef60d1bc40245d72f3c9c))
* **pull-quote:** remove testimonial variant; testimonial-grid owns testimonials ([adfd65b](https://github.com/Bergert-Digital/Pediment/commit/adfd65b76cb1eb0f8ec7f02cbf0a4dba3d327fd7))

## [0.2.1](https://github.com/Bergert-Digital/Pediment/compare/v0.2.0...v0.2.1) (2026-05-28)


### Bug Fixes

* **seed:** refresh $wp_rewrite singleton before flushing rules ([3bfa37e](https://github.com/Bergert-Digital/Pediment/commit/3bfa37e911a500af1fd31d120f87a40d830a494a))
* **seed:** refresh $wp_rewrite singleton before flushing rules ([#11](https://github.com/Bergert-Digital/Pediment/issues/11)) ([c532ffe](https://github.com/Bergert-Digital/Pediment/commit/c532ffe4e15ce17dfab939a64e74869ffa1c8f7c))

## [0.2.0](https://github.com/Bergert-Digital/Pediment/compare/v0.1.5...v0.2.0) (2026-05-28)


### Features

* **assets:** add wide Pediment demo SVG for custom-logo seed ([15c88b7](https://github.com/Bergert-Digital/Pediment/commit/15c88b7df90ba3c0d2522aa841990f1c0dcb6f72))
* **block:** scaffold starter/section-head ([ad62dbd](https://github.com/Bergert-Digital/Pediment/commit/ad62dbdbc96071fb438c4e48f08174ab51707b42))
* **blocks:** register is-style-insights-grid block style on core/query ([1dbd895](https://github.com/Bergert-Digital/Pediment/commit/1dbd89598146a0be918385c35994cba3c10b5e39))
* **blocks:** starter/feature child (icon/title/text/link) ([0c4ab4c](https://github.com/Bergert-Digital/Pediment/commit/0c4ab4cdd6fbab430aaa63fed1ab509bf2da99c7))
* **blocks:** starter/feature-grid parent (3-up cards) ([43831ee](https://github.com/Bergert-Digital/Pediment/commit/43831ee774a5b70dee0ecdce59665cd18d8b7cc0))
* **blocks:** starter/logo-cloud (caption + image row) ([6efe5a0](https://github.com/Bergert-Digital/Pediment/commit/6efe5a074eebaf8934cfa992b50b6db726c1b180))
* **blocks:** starter/step child (title/text, CSS-counter number) ([ef38d5e](https://github.com/Bergert-Digital/Pediment/commit/ef38d5ed362817ba61d591d97379946c8230f73d))
* **blocks:** starter/steps parent (numbered, CSS counter) ([771b2b8](https://github.com/Bergert-Digital/Pediment/commit/771b2b8fd4f449afeb5430281d3dc5f7d0435924))
* **blog-index:** editor toggle for the category filter ([c598eaa](https://github.com/Bergert-Digital/Pediment/commit/c598eaaaefda65546697bfb75f5a24668c1ba8ea))
* **blog-index:** Insight card markup, category badge, presentational filter bar ([d59bec1](https://github.com/Bergert-Digital/Pediment/commit/d59bec1dcc86971172cfd6b5bfee5f29ac1f4b54))
* **blog-index:** presentational per-instance category filter view-script ([af7f21e](https://github.com/Bergert-Digital/Pediment/commit/af7f21e03d267f653893de4a0689e79e9e9f6f41))
* **brand:** introduce BrandRegistry for filterable fields and sections ([b5f5314](https://github.com/Bergert-Digital/Pediment/commit/b5f5314d4d7694933294a48a496ef1798dc2b556))
* **editor:** load theme.css in the Site Editor via add_editor_style ([dbb3883](https://github.com/Bergert-Digital/Pediment/commit/dbb3883aa253450fa045a88d63e067b06d22486b))
* **hero:** accent format type for highlighting words in headlines ([c51f90f](https://github.com/Bergert-Digital/Pediment/commit/c51f90f78b7fd41e3c6ba785134d392904c8c253))
* **hero:** block.json stat-card variant + HeroTest (incl. filter) ([d68ed74](https://github.com/Bergert-Digital/Pediment/commit/d68ed74fa0644c5e85a5e23a5de42648bf4e4aa5))
* **hero:** editor variant options from starter_hero_variants + stat-card controls ([c95acd9](https://github.com/Bergert-Digital/Pediment/commit/c95acd90fafe9945b765d74fef00b4bd67684914))
* **hero:** filter-normalized variant + stat-card render branch ([2b5cf5a](https://github.com/Bergert-Digital/Pediment/commit/2b5cf5a5f36b03d00c0975c20a9dd1d52cf27d95))
* **hero:** prepend ph-check-circle icon to each tick row ([497653f](https://github.com/Bergert-Digital/Pediment/commit/497653f6aae3f94c3f1c1ec1a645652532af2467))
* **hero:** starter_hero_variants() filter + editor global ([d042ea7](https://github.com/Bergert-Digital/Pediment/commit/d042ea7f49b14db437a074fa6bbddfc7adfc2f0d))
* **i18n:** wire load_theme_textdomain for self-hosted MO files ([eafb157](https://github.com/Bergert-Digital/Pediment/commit/eafb1573bf1f74e24bebe3bfd65a6f1def8e94f8))
* **icons:** add Phosphor SVG sprite + generator ([06dd704](https://github.com/Bergert-Digital/Pediment/commit/06dd704988af7b57d8a506b66a343cea11c17e67))
* **icons:** starter_icon() helper + print sprite on wp_body_open ([4e6bdfb](https://github.com/Bergert-Digital/Pediment/commit/4e6bdfb502ddeb784f0cb1f0ca664adfa1641348))
* **mega-menu:** editor UX overhaul, icon moves to column ([32dac1f](https://github.com/Bergert-Digital/Pediment/commit/32dac1fe6643b68a3b041cc1d5efac060e698f22))
* **mega-menu:** editor-only CSS hover-reveal preview ([2365081](https://github.com/Bergert-Digital/Pediment/commit/23650819729f8760da17994226b87881ce54cc3e))
* **mega-menu:** gate editing to wp_navigation entity context ([ac6a80d](https://github.com/Bergert-Digital/Pediment/commit/ac6a80d524b9a6e5fd1390043c2edfa4af3ac96f))
* **mega-menu:** Inspector-sidebar form + ServerSideRender preview ([091b307](https://github.com/Bergert-Digital/Pediment/commit/091b307554c92761ad449ef2ac32f05e8dfc7718))
* **mega-menu:** render.php loops columns attribute; add MegaMenuTest ([2275bf7](https://github.com/Bergert-Digital/Pediment/commit/2275bf77eb3820eb66eb8b48f752d133495892cd))
* **nav-seed:** include starter/mega-menu in seeded entity ([844eebc](https://github.com/Bergert-Digital/Pediment/commit/844eebc723a0464bf445e0142b023f89dc257066))
* **parts:** footer bank-icon brand mark ([2648d0f](https://github.com/Bergert-Digital/Pediment/commit/2648d0fbb97cb4a3a5c2bf5e79f576937c8897d3))
* **parts:** header bank-icon brand mark ([ef8f96b](https://github.com/Bergert-Digital/Pediment/commit/ef8f96b746c299b3750998164150c412d371081b))
* **parts:** Pediment 4-column footer + bottom bar ([b2377e4](https://github.com/Bergert-Digital/Pediment/commit/b2377e46d527fdeb44e0db25b25977a272f26e92))
* **parts:** Pediment header (compact nav + pill CTA) ([bf1ad41](https://github.com/Bergert-Digital/Pediment/commit/bf1ad41d44a7552ee4e2535915d581e51f46318f))
* **pattern:** approach band gets section head + image column ([da12e65](https://github.com/Bergert-Digital/Pediment/commit/da12e65baeb74a0f7904dc3cc92a636d1c9bd47d))
* **pattern:** centered section-head above insights grid ([e72165a](https://github.com/Bergert-Digital/Pediment/commit/e72165ad25d51904cb5412ace27784bb73bf5b25))
* **pattern:** Pediment landing — 8-band composition + guard test ([194346d](https://github.com/Bergert-Digital/Pediment/commit/194346d4104ba34a6bfd6530ac64d8eea23e0c30))
* **pattern:** services band uses starter/section-head ([49eb6d9](https://github.com/Bergert-Digital/Pediment/commit/49eb6d94ef06fc77b8e46188780a7d99a577ad97))
* **phase-1:** theme.json design tokens and FSE templates ([bc902c7](https://github.com/Bergert-Digital/Pediment/commit/bc902c74fd2c8a85cc4e2e432d27b50b52c7eef9))
* **phase-2:** block auto-loader and directory lint rule ([481e00a](https://github.com/Bergert-Digital/Pediment/commit/481e00a014456b4543ed2e5039d2a4a407176134))
* **phase-3:** 10 starter blocks (hero, cta, faq, prose, pull-quote, image-caption, stat, blog-index, contact-form) ([667333b](https://github.com/Bergert-Digital/Pediment/commit/667333b4114d8e505d9f16c6730c1abe59c8fc6f))
* **phase-4:** Brand storage class, Settings API admin page, image picker + repeater JS ([7a6a85b](https://github.com/Bergert-Digital/Pediment/commit/7a6a85b0a3ddfda082d17ecfeefc14e8c04570fd))
* **phase-5:** contact form REST endpoint, CPT, email notification, cleanup cron ([46805e0](https://github.com/Bergert-Digital/Pediment/commit/46805e085138432e2d82dfee7d5b8de952d54bf7))
* **phase-6:** three block patterns (hero-cta-faq, prose-article, contact-page) ([42e5c13](https://github.com/Bergert-Digital/Pediment/commit/42e5c13aa8a3215b0601b32daf4c3b064ec07cdf))
* **phase-7:** wp starter-theme seed CLI command ([c3afd15](https://github.com/Bergert-Digital/Pediment/commit/c3afd15d0aeecfdc605abb1beb3f6f620a38e1f4))
* **pull-quote:** editor variant select + testimonial UI ([3f90292](https://github.com/Bergert-Digital/Pediment/commit/3f9029232a8752fc21b26121345f6e4dd1d18e4a))
* **pull-quote:** starter_pull_quote_variants fork-friendly filter + editor reflection ([9d7ac0e](https://github.com/Bergert-Digital/Pediment/commit/9d7ac0e0eac3e630f54b9e88abfb92d28e8363ec))
* **pull-quote:** testimonial render branch; default markup preserved ([c864527](https://github.com/Bergert-Digital/Pediment/commit/c86452714032c14c2cd1a1552e6c307c863909ea))
* **section-head:** editor UI (3 RichText fields + alignment/level controls) ([8436944](https://github.com/Bergert-Digital/Pediment/commit/8436944851d45e6d60c918c0c120d9dba0fcfbe9))
* **section-head:** full render (3 fields, alignment, level, suppression) ([d016bec](https://github.com/Bergert-Digital/Pediment/commit/d016bec96ef35cc3aae267f2313e780ff8397176))
* **section-head:** layout + typography styles ([6994aa8](https://github.com/Bergert-Digital/Pediment/commit/6994aa82c78dc1a00c08ece024981ccbb838631c))
* **seed:** --force flag for re-applying seeded page content ([#5](https://github.com/Bergert-Digital/Pediment/issues/5)) ([14b7443](https://github.com/Bergert-Digital/Pediment/commit/14b744376c2dcfdfd000f4225ec289491398cc04))
* **seed:** add --force to refresh seeded page content ([50baadb](https://github.com/Bergert-Digital/Pediment/commit/50baadbc148dd79d832f36d7ccc45a3774157d9c))
* **seed:** empty Blog page content (home.html renders the listing) ([5cf047a](https://github.com/Bergert-Digital/Pediment/commit/5cf047a155ee7d1c26aabff4dcd61463ebb8a4d9))
* **seed:** Home page from the pediment-landing pattern (safe fallback) ([6624ac9](https://github.com/Bergert-Digital/Pediment/commit/6624ac90ac0ec09da0b3b7d91804e31ef4974185))
* **seed:** idempotent sample categories + posts for the Insights band ([8a9643a](https://github.com/Bergert-Digital/Pediment/commit/8a9643a9a3f468b4547d643d8716e6415ff676d6))
* **seed:** sideload + bake demo image into pediment landing ([edc565a](https://github.com/Bergert-Digital/Pediment/commit/edc565a9e2715202bded7a98ab845657623c7701))
* **seed:** sideload + bake demo wide logo, set as custom_logo theme mod ([e2c7dcf](https://github.com/Bergert-Digital/Pediment/commit/e2c7dcf4050579bb883e56c39e7393589c05ccb6))
* **social-links:** server-rendered block reading Brand::social_links ([b137a20](https://github.com/Bergert-Digital/Pediment/commit/b137a20d67995ae4aa8182b750d13ce90e41164d))
* **templates:** home.html with banded heading + paginated insights grid ([932c6dc](https://github.com/Bergert-Digital/Pediment/commit/932c6dcdb79928096cff74a11855131bcc9c162d))
* **theme:** active-page, focus and submenu nav styling ([785c5ba](https://github.com/Bergert-Digital/Pediment/commit/785c5bae8a740256bbc3de0d6c58be4c51eaa021))
* **theme:** active-state filter for custom-URL nav links ([6c49fe1](https://github.com/Bergert-Digital/Pediment/commit/6c49fe1089530fbc81c345794ae99697b9b48be0))
* **theme:** allow starter/mega-menu as a core navigation item ([0f07174](https://github.com/Bergert-Digital/Pediment/commit/0f07174596c6f54520d2b8098175f49aec54ca97))
* **theme:** always-sticky site header ([eb2fb79](https://github.com/Bergert-Digital/Pediment/commit/eb2fb791f9309574d8d291e489236000bd905cd7))
* **theme:** enqueue global css/js + pre-paint anim gate ([1ccae3e](https://github.com/Bergert-Digital/Pediment/commit/1ccae3e18e135b50fd69c942d6ae3a40785c730d))
* **theme:** global stylesheet (rhythm vars, utilities, band styles, icons) ([34b7d66](https://github.com/Bergert-Digital/Pediment/commit/34b7d668ae3800aa7697969c568adb84cc3cd730))
* **theme:** mega-menu defaults to 1 column + Add column button ([4124bce](https://github.com/Bergert-Digital/Pediment/commit/4124bce4d3c4e9cc8a2a9fad4b5e6bc0c64f0650))
* **theme:** mega-menu Interactivity behavior + e2e + fixture ([673a764](https://github.com/Bergert-Digital/Pediment/commit/673a764cb74cf55a676010f077ffea061210e3d4))
* **theme:** mega-menu mobile accordion + reduced-motion ([bafb896](https://github.com/Bergert-Digital/Pediment/commit/bafb896ff99434e0550c66eea4af7795bb931ea0))
* **theme:** recolor focus shadow to accent (drop legacy indigo) ([102ad46](https://github.com/Bergert-Digital/Pediment/commit/102ad46afb86c579c157551866d7bcf797ca41cd))
* **theme:** reduced-motion-safe entry animations ([0ec3b99](https://github.com/Bergert-Digital/Pediment/commit/0ec3b99ed79f45607cf33f500e327e692c056c78))
* **theme:** register band-surface/band-navy group block styles ([ade433a](https://github.com/Bergert-Digital/Pediment/commit/ade433a8e008a7c0e7264498cb418c6230f6cac6))
* **theme:** register custom-logo theme support with flex dimensions ([2f5a64a](https://github.com/Bergert-Digital/Pediment/commit/2f5a64a82c9d31d7e801f46be7c559e4f7d61021))
* **theme:** retokenize theme.json to Pediment palette/type/shadow ([c22677a](https://github.com/Bergert-Digital/Pediment/commit/c22677a9cf4181fad97a3c1e3f44fd65d9709548))
* **theme:** seed default top-nav items in header part ([1543bfe](https://github.com/Bergert-Digital/Pediment/commit/1543bfe9629b2845e94b5990eaa65a59f58485d9))
* **theme:** space starter-section groups; drop separator hack ([cbd88e8](https://github.com/Bergert-Digital/Pediment/commit/cbd88e87fd352af10f34b003979f501baac81274))
* **theme:** starter/mega-column block ([ad73efa](https://github.com/Bergert-Digital/Pediment/commit/ad73efa68705891d12c784926f87786a736452b6))
* **theme:** starter/mega-link block ([2f003b9](https://github.com/Bergert-Digital/Pediment/commit/2f003b9309905d3a52a3c34502cff7e04cdda782))
* **theme:** starter/mega-menu block markup + trigger/panel ([5608796](https://github.com/Bergert-Digital/Pediment/commit/5608796eaa286509c2583c022256dcb67e2288d7))
* **theme:** style Contact nav item as CTA button ([d64e8ba](https://github.com/Bergert-Digital/Pediment/commit/d64e8baae45e4019862ab2c5f95f8d2b1d709cdb))
* **theme:** style mobile nav overlay surface ([28a9e89](https://github.com/Bergert-Digital/Pediment/commit/28a9e8979210aa7d68638e33036342baf0a440ec))
* wp-starter-theme v0.1.0 — full baseline (phases 0–11 + polish) ([1994422](https://github.com/Bergert-Digital/Pediment/commit/19944225fbfce0e0ccd7f3ad0fc42ab542a329cc))


### Bug Fixes

* **approach:** stretch image to fill right column on desktop ([41cb8c9](https://github.com/Bergert-Digital/Pediment/commit/41cb8c9078ab608467c5c2145fa6fd17cced2cfb))
* **assets:** give demo logo intrinsic width/height so site-logo renders ([18cd2c0](https://github.com/Bergert-Digital/Pediment/commit/18cd2c052035445550c93f836de6005e37fb5e89))
* **assets:** shrink demo logo intrinsic size to 200x48 for header fit ([fa48d21](https://github.com/Bergert-Digital/Pediment/commit/fa48d21907ed1972eacd93fa5da6e031acc05d52))
* **assets:** tighten demo logo viewBox to hug content ([a7f514f](https://github.com/Bergert-Digital/Pediment/commit/a7f514fba8a9ddffc19580515f539f641cb96290))
* **bands:** match mockup palette — most bands white, CTA card not band ([766bd6e](https://github.com/Bergert-Digital/Pediment/commit/766bd6e8720297886189bd57898f9de0252462d7))
* **blocks:** brand mark as server-rendered starter/brand-mark block ([5c70f38](https://github.com/Bergert-Digital/Pediment/commit/5c70f389b55dbe01c0bc810d94093ac8ee287587))
* **ci:** install composer deps in phpunit job so phpunit-polyfills resolves ([9bf0152](https://github.com/Bergert-Digital/Pediment/commit/9bf0152365af644e3ac93fd26eda1368ad1fd25b))
* **ci:** mount checkout at wp-starter-theme to match expected theme slug ([6b8fa2e](https://github.com/Bergert-Digital/Pediment/commit/6b8fa2e10be4638d853b451cd2e7a07ea1b71210))
* **ci:** pin phpunit to vendor/bin/phpunit (9.6) to match WP test harness ([5ad8eec](https://github.com/Bergert-Digital/Pediment/commit/5ad8eececac7652225fe203ede92418da71756ef))
* **ci:** unblock lint, phpunit, and e2e on rebranded theme ([779e352](https://github.com/Bergert-Digital/Pediment/commit/779e35293b51a992f02579d4bed60ddbaedea118))
* **contact-form:** match editor markup to front-end render ([340264c](https://github.com/Bergert-Digital/Pediment/commit/340264c07e55698f5b240abf6823145050d6af11))
* **contact-form:** strip ceremonial REST nonce, document intent ([23e9456](https://github.com/Bergert-Digital/Pediment/commit/23e945633b5d10ab95aeac147789cba4e48c2de2))
* **cta,hero:** button text invisible on hover; CTA fields visually fused ([dbba0bc](https://github.com/Bergert-Digital/Pediment/commit/dbba0bc3b4e2b16c0ff2c921a4c404de85b2ab78))
* **e2e:** dismiss editor welcome guides in global setup ([7c02057](https://github.com/Bergert-Digital/Pediment/commit/7c02057794daf0ac143df88a395d3bd6c55c80ca))
* **e2e:** drop pagination assertion (WP suppresses wrapper on single-page) ([8bcc007](https://github.com/Bergert-Digital/Pediment/commit/8bcc0075a719710b68d4183d2713da6def1157a2))
* **e2e:** make mega-menu-editor tests wait for store hydration ([73dec24](https://github.com/Bergert-Digital/Pediment/commit/73dec24c1a6a9f037e0070c9c453d040a04bf9e8))
* **e2e:** use data-block clientId + force-click for nav-wrapped tests ([830a32d](https://github.com/Bergert-Digital/Pediment/commit/830a32d8d75e8b07e5655813d4a5cfc796624be3))
* **editor:** enqueue block styles in the editor canvas (editorStyle) ([ed5e403](https://github.com/Bergert-Digital/Pediment/commit/ed5e403e892affb164d629c465934ff00467e2c2))
* **editor:** match RichText classNames to render.php DOM ([99efe8b](https://github.com/Bergert-Digital/Pediment/commit/99efe8b21d9508d5ef5d960b8b38da1d730544ec))
* **editor:** show Phosphor icons in the block editor canvas ([1ba8a51](https://github.com/Bergert-Digital/Pediment/commit/1ba8a510a58830e66a67a8bb573e6601de2118f2))
* **faq:** real ph-caret-down icon + 2-column FAQ band intro ([f289db7](https://github.com/Bergert-Digital/Pediment/commit/f289db74970490606a65a3448fedefb992a6f3c6))
* **hero:** cap figure max-height to viewport so it doesn't bleed past fold ([7b9c0b2](https://github.com/Bergert-Digital/Pediment/commit/7b9c0b237230db0af846762cdf80599d9a0ea871))
* **hero:** drop redundant padding-block and stack-center alignment ([78f469c](https://github.com/Bergert-Digital/Pediment/commit/78f469c86be94fe1ff42eff36f8a264117d0c6ec))
* **hero:** editor figure mirrors render.php's image + glass card ([d8566da](https://github.com/Bergert-Digital/Pediment/commit/d8566daa2f7082e33dd8c1ce3b215447b8882fed))
* **hero:** mirror render.php column structure in the editor ([91b363a](https://github.com/Bergert-Digital/Pediment/commit/91b363a82b5e06abc4624d88c644e82ac3c3d6ca))
* **hero:** prevent stat-card figure collapse when no media is set ([3a80e7d](https://github.com/Bergert-Digital/Pediment/commit/3a80e7dea89c9c0a01aa67041e18b480335f86d3))
* **hero:** remove unimplemented "split" variant from enum + UI ([baa4f6c](https://github.com/Bergert-Digital/Pediment/commit/baa4f6c6a3b595fab64e2fa395c66a19165aef61))
* **hero:** right-align figure when max-height shrinks it inside its column ([625b487](https://github.com/Bergert-Digital/Pediment/commit/625b48712c594d9411747df8c0d9cb8bb7d9ca7d))
* **hero:** stat-card figure collapse when no media set ([#4](https://github.com/Bergert-Digital/Pediment/issues/4)) ([6c63b7f](https://github.com/Bergert-Digital/Pediment/commit/6c63b7f206ad58c4d0223b6b1e4f53aa94466e04))
* **home:** add layout classes to band groups for editor block validation ([5c5aa55](https://github.com/Bergert-Digital/Pediment/commit/5c5aa55342e4b4343cd87fb64e7d917e9571f38d))
* **icons:** add ABSPATH direct-access guard for inc/ parity ([9078842](https://github.com/Bergert-Digital/Pediment/commit/9078842d460980f4a01ee3543132cc2e314b1221))
* **icons:** self-deregistering sprite guard for test isolation ([10749af](https://github.com/Bergert-Digital/Pediment/commit/10749af0537da227b939a2c109b268146d3ac0a7))
* **layout:** band padding-inline scales with viewport (mobile gutter) ([82d0d52](https://github.com/Bergert-Digital/Pediment/commit/82d0d522cb8935b6165ab49cc9590e3b5dbe428c))
* **layout:** bands constrain align:wide; site-shell zero block-gap ([b123bed](https://github.com/Bergert-Digital/Pediment/commit/b123bed9ce743829e661683944a29e066630dc2b))
* **layout:** global border-box reset (fixes CTA bounding width) ([4378444](https://github.com/Bergert-Digital/Pediment/commit/4378444361bfa37d9b636249b127b64a72b092dd))
* **lint:** resolve phpcs errors + eslint a11y/unsafe-api issues ([53a237b](https://github.com/Bergert-Digital/Pediment/commit/53a237b406057c481160a96b98ccfb839febf379))
* **mega-menu:** manual wrapper avoids WP_Block_Supports null-attrs crash ([01c0da6](https://github.com/Bergert-Digital/Pediment/commit/01c0da6915da51b55fa7ebd1f6d3862d77127527))
* **mega-menu:** tighten panel width and center under trigger ([5a0dae6](https://github.com/Bergert-Digital/Pediment/commit/5a0dae679f8922cbb2b98c7b40093b37f2f4e26f))
* **nav-seed:** JSON_UNESCAPED_SLASHES so wp_insert_post round-trip is identity ([3be0164](https://github.com/Bergert-Digital/Pediment/commit/3be0164e5b6a2d2862f7db73ee5198cbdd49f08b))
* **nav:** active link uses accent color (out-specify WP6.7 core doubled-class color:inherit) ([33f7a94](https://github.com/Bergert-Digital/Pediment/commit/33f7a94b793e8286cb3577db047247d63ace3f78))
* **nav:** drop nav-cta from seeded Contact (single pill CTA) ([69e4034](https://github.com/Bergert-Digital/Pediment/commit/69e4034547801bc0bd452a28364fc3eba6c3f06b))
* **parts:** brand mark via core/image, not core/html inline SVG ([e4274ee](https://github.com/Bergert-Digital/Pediment/commit/e4274ee0e07128a69fd5c6f4ad98a9a38446788b))
* **parts:** footer brand mark = self-contained inline SVG (sprite &lt;use&gt; is editor-invalid) ([6aea61c](https://github.com/Bergert-Digital/Pediment/commit/6aea61c937cdc401d09558426530398b26cc6143))
* **parts:** header brand mark = self-contained inline SVG (sprite &lt;use&gt; is editor-invalid) ([d41b9e8](https://github.com/Bergert-Digital/Pediment/commit/d41b9e808562b203987c52b5c43c02d2856f4cbf))
* **parts:** header CTA button = canonical core/button save markup ([fd4d586](https://github.com/Bergert-Digital/Pediment/commit/fd4d58606a9b3766f415d685d0a3d9b389079b34))
* **parts:** header CTA needs href so it has role=link ([7ce2423](https://github.com/Bergert-Digital/Pediment/commit/7ce24231bd52b708f7004fe0f1faecea5fe52cee))
* **pattern:** drop band layout:constrained + zero inter-band margin ([b2d4073](https://github.com/Bergert-Digital/Pediment/commit/b2d407352bf8781fd356cd546f9a4eed226102b5))
* **pattern:** plain apostrophe in testimonial quote (HTML entity broke seed round-trip) ([c1b02f3](https://github.com/Bergert-Digital/Pediment/commit/c1b02f3c0b64e41c72b267ca489b9c0b64a1fcab))
* **pull-quote:** cap testimonial variant to 880px centered ([da146dc](https://github.com/Bergert-Digital/Pediment/commit/da146dc52cf4ffc752db9626d22a4e7a9cbacffd))
* **pull-quote:** raise testimonial selector specificity for alignwide ([059b61a](https://github.com/Bergert-Digital/Pediment/commit/059b61a5345a9db2a733a8b04f1a89ad8c612ed4))
* **section-head:** use __experimentalToggleGroupControl alias ([ed5a1f9](https://github.com/Bergert-Digital/Pediment/commit/ed5a1f91af1de8cd05ae15f02f48dcb25ab5cdc1))
* **seed:** move demo assets out of docs/ so they ship in the release ([585566f](https://github.com/Bergert-Digital/Pediment/commit/585566f765fdb346651a1fc5fc2377c37516ad99))
* **services:** match mockup — head block above grid + Explore arrow ([3f86028](https://github.com/Bergert-Digital/Pediment/commit/3f86028b696a2e6d9618d793965c69b4efe2bbd1))
* **social-links:** phpcs — indentation + rename $link to avoid WP global ([460e9d3](https://github.com/Bergert-Digital/Pediment/commit/460e9d3d85c6061763628c436351a63ebc4b0bcd))
* **social-links:** target=_blank, href test, drop dead role=img ([cf5fc0f](https://github.com/Bergert-Digital/Pediment/commit/cf5fc0fdfb03181d44fa88907931a352ddaf43eb))
* **templates:** constrain front-page main; seed home with wide-aligned hero blocks ([1fb97eb](https://github.com/Bergert-Digital/Pediment/commit/1fb97ebd060a5bc8495abb8733ebff35a59251a2))
* **test:** align ThemeJsonTest with foreground palette slug ([6b0b99f](https://github.com/Bergert-Digital/Pediment/commit/6b0b99f71cd9f28c20c08fed77fa6d6972eb5f0c))
* **theme.json:** rename 2xl/3xl/4xl slugs to 2-xl/3-xl/4-xl ([3bf424f](https://github.com/Bergert-Digital/Pediment/commit/3bf424fd7111254198ceb5f500419c2432a4d90f))
* **theme:** airy section rhythm + neutralize separators ([2e329f1](https://github.com/Bergert-Digital/Pediment/commit/2e329f1936c7ee6c611f894722dedc4f6cbfda44))
* **theme:** collapse Spacer height inside nav (was forcing 100px row) ([8e65ab3](https://github.com/Bergert-Digital/Pediment/commit/8e65ab3cdc7c27dfbfecb56dbe5c8afe3c14a5f9))
* **theme:** full-width post-content for full-bleed sections ([b5ba4ad](https://github.com/Bergert-Digital/Pediment/commit/b5ba4ad4ee34c997cfb06f2702826ef3dd516e08))
* **theme:** group blog-index __meta/__title/__excerpt with insights-grid selectors ([1bb6b3d](https://github.com/Bergert-Digital/Pediment/commit/1bb6b3d3116b3fe50fd1d79354878e75e4c76769))
* **theme:** mega-link editor mirrors render DOM; url/icon to inspector ([efea6b6](https://github.com/Bergert-Digital/Pediment/commit/efea6b6bf8b8e9db0286d35494856b3fe7fbd389))
* **theme:** mega-link sprite-independent editor icon cell; safer LinkControl ([829b111](https://github.com/Bergert-Digital/Pediment/commit/829b111b20e1a2e4f71804e20b1a8e3e729ff2df))
* **theme:** mega-link stack label/description in column 2 ([15dd096](https://github.com/Bergert-Digital/Pediment/commit/15dd0969ec85faf9050b5b0a0e933fe3725f6d86))
* **theme:** mega-menu drop dangling aria-controls; name empty-label trigger ([c257020](https://github.com/Bergert-Digital/Pediment/commit/c257020680769131264f22f06b58eb53215d4b36))
* **theme:** mega-menu editor-only stylesheet shows panel expanded ([39563ef](https://github.com/Bergert-Digital/Pediment/commit/39563ef8706fc58038927c16e4720f5f9c704acc))
* **theme:** mega-menu focus-open on trigger only; suppress Escape-refocus reopen ([6904a7d](https://github.com/Bergert-Digital/Pediment/commit/6904a7dd1637494a6f85493d2e904a7d1bb0db7f))
* **theme:** mega-menu interactivity scope/timer/cleanup/hybrid ([4ad3c83](https://github.com/Bergert-Digital/Pediment/commit/4ad3c837de277dedb319ab97fddb44edf77e3f84))
* **theme:** mega-menu progressive-enhance + Escape + isolated e2e fixture ([ee5c6b9](https://github.com/Bergert-Digital/Pediment/commit/ee5c6b976df2e0e15c2417762c621f6eaf571879))
* **theme:** mega-menu touch tap-open + un-stick Escape suppressFocus ([0068ed4](https://github.com/Bergert-Digital/Pediment/commit/0068ed4f4da05ca5d0f3bb099ed307c9e7ac8f57))
* **theme:** nav fills header width, no wrap (editable default) ([2c99311](https://github.com/Bergert-Digital/Pediment/commit/2c993117760b7902177c734cf3f7bae9455fdc32))
* **theme:** restore __readmore typography on curated blog-index block ([f0297ab](https://github.com/Bergert-Digital/Pediment/commit/f0297ab6370c2d4b3bfdd67e7fb700a6c2b1722a))
* **theme:** scope section gap to starter blocks + separators ([227ea79](https://github.com/Bergert-Digital/Pediment/commit/227ea7946c9c4b4b5ed81098572d2a54f0b149a3))
* **theme:** section padding-block rhythm; drop inter-section margin ([0449542](https://github.com/Bergert-Digital/Pediment/commit/0449542a4762ac7cf5c9b811a3ee4d39f3a2c618))
* **theme:** seed wp_navigation entity for default menu (Approach 2) ([d06a750](https://github.com/Bergert-Digital/Pediment/commit/d06a75072e09c86b39c9ec50fa19ce9f3bcab726))
* **theme:** separator-as-boundary section rhythm (v2) ([2d5003c](https://github.com/Bergert-Digital/Pediment/commit/2d5003cfc3b70fab2186551b5187fde8fb63f4b6))
* **theme:** stabilize mega-menu appender callbacks; target .block-list-appender ([2c2a4e8](https://github.com/Bergert-Digital/Pediment/commit/2c2a4e86440d86d2e894540e69bdbfbfc8774313))
* **typography:** h1/h2 to mockup scale; anchor .head to left edge ([d438b90](https://github.com/Bergert-Digital/Pediment/commit/d438b90146f7f1675a212581843f20bc4e05d1c8))
* **wp-env:** bump WP to 6.6 so editor scripts enqueue ([3b290bb](https://github.com/Bergert-Digital/Pediment/commit/3b290bba421fe2feddce42524b4e46e57304e941))


### Performance

* **blocks:** adopt wp_register_block_metadata_collection (WP 6.9) ([e7da73e](https://github.com/Bergert-Digital/Pediment/commit/e7da73e079be8945ed24fa42f193ea1cb1595b67))
* **blocks:** hoist insight-card CSS out of always-loaded theme.css ([d843700](https://github.com/Bergert-Digital/Pediment/commit/d84370066358bc9014e53a208b66f06979a31958))
* **icons:** cache phosphor sprite contents per request ([c323856](https://github.com/Bergert-Digital/Pediment/commit/c323856708c4b3251844dd05fbf961a775e2f54d))


### Refactors

* **blog-index:** move card visuals to theme.css for cross-block reuse ([f17e2f1](https://github.com/Bergert-Digital/Pediment/commit/f17e2f1d98e6eb67bbe8113e20e45e5214ff80ea))
* **brand:** derive defaults from BrandRegistry, support filter-added fields ([547fbfe](https://github.com/Bergert-Digital/Pediment/commit/547fbfebe3a5d07c87662d07c925c4c4232b25e4))
* **brand:** iterate registry in admin_init, support filter-added fields/sections ([fc55844](https://github.com/Bergert-Digital/Pediment/commit/fc558444daa737f1c496d067d38d25d0893b90cc))
* **brand:** registry-driven sanitize with per-field callables ([13db99f](https://github.com/Bergert-Digital/Pediment/commit/13db99f617e71c2a6874bb9be20cf878672b8dce))
* **contact-form:** load view JS via viewScript, drop has_block gate ([b3a1b01](https://github.com/Bergert-Digital/Pediment/commit/b3a1b01ede50e1078a0d2a82f20048676ee8a817))
* **css:** drop dead .brand .mark rules from retired brand-mark block ([f910ac4](https://github.com/Bergert-Digital/Pediment/commit/f910ac457dec85fadff811461999fe0649623ce3))
* **footer:** use core/site-logo in place of brand-mark + site-title ([092da50](https://github.com/Bergert-Digital/Pediment/commit/092da5034f0e032a854bd819e6ccf613f9db1394))
* **header:** use core/site-logo in place of brand-mark + site-title ([f049401](https://github.com/Bergert-Digital/Pediment/commit/f0494017e98fcb6a20cb05a0769dac7468d62d8b))
* **mega-menu:** demo pattern uses single mega-menu block ([8d7076e](https://github.com/Bergert-Digital/Pediment/commit/8d7076e602127d411b1db6321a8bd3737c26324d))
* **mega-menu:** remove mega-column/mega-link blocks + obsolete RenderTest ([09b1e96](https://github.com/Bergert-Digital/Pediment/commit/09b1e96cdbc5aa01b0dd8f2cd6c86ce409dffdf3))
* **mega-menu:** single block shell — columns attr, save null ([98b632c](https://github.com/Bergert-Digital/Pediment/commit/98b632c0679c3159db1502652b469189f996672a))
* **mega-menu:** static preview avoids deselect-on-rerender ([09b75aa](https://github.com/Bergert-Digital/Pediment/commit/09b75aabccbd090d15026288af65b486d709740f))
* **nav-seed:** defensive JSON_HEX_TAG + updated docblock for mega-menu ([359ba5c](https://github.com/Bergert-Digital/Pediment/commit/359ba5cdc3dc2ac62458faaccc4501d665c9bf12))
* **nav:** consolidate nav CSS into theme.json ([2e48897](https://github.com/Bergert-Digital/Pediment/commit/2e4889773d45bb9b849d7f2d9d2ab08d8a4aa24e))
* **nav:** drop mega-menu from default seeded nav ([561dd33](https://github.com/Bergert-Digital/Pediment/commit/561dd33ee6f6c65110e64e776dbf0ecc59ad2da1))
* **pattern:** mega-menu-header uses bound entity, not inline children ([dcf643d](https://github.com/Bergert-Digital/Pediment/commit/dcf643de961da1b3f918a319872ea75201ef734d))
* **pull-quote:** name BEM class on testimonial meta wrapper ([0946e98](https://github.com/Bergert-Digital/Pediment/commit/0946e98bb054fb7378423d1dc932d4bbf4356549))
* **pull-quote:** single text edit surface in editor (drop redundant inspector fields) ([9219ba6](https://github.com/Bergert-Digital/Pediment/commit/9219ba679f61890812a88927f353f6d14dd0b833))
* rename theme Starter → Pediment ([4a52aa6](https://github.com/Bergert-Digital/Pediment/commit/4a52aa64f55ad39fbe041ce892dcc22cba07d060))
* retire starter/brand-mark block (superseded by core/site-logo) ([594cf1c](https://github.com/Bergert-Digital/Pediment/commit/594cf1cd089becbc989e6d5299f6dfe0ba84bd49))
* **seed:** drop dead require, cover theme-mod drift-resync branch ([93479cb](https://github.com/Bergert-Digital/Pediment/commit/93479cb600c2c9c0a397eb0b3f521b9248199b31))
* **seed:** wrap About/Contact in band-hero, fix permalinks ([9beee63](https://github.com/Bergert-Digital/Pediment/commit/9beee63f69ecc23c4d08fcad73edff67793885a1))
* **theme.json:** use var:preset shorthand in structured styles ([5733e71](https://github.com/Bergert-Digital/Pediment/commit/5733e71bdb61d74ea8859848b0e1fdabf0721e49))
* **theme:** polish header layout + nav color scheme ([cdf5b09](https://github.com/Bergert-Digital/Pediment/commit/cdf5b096d5a4f47151c6a5614af1ec8b4184e7f7))
* **theme:** print no-FOUC class via wp_print_inline_script_tag ([08d8041](https://github.com/Bergert-Digital/Pediment/commit/08d80419ee3b3508ac5b9345ba145736a2bce5a1))
* **theme:** rename text palette slug to foreground ([7858f37](https://github.com/Bergert-Digital/Pediment/commit/7858f379cedfadd5ab41d1c1868ff56a8e00d900))
* update sibling refs (starter → pediment) ([8abeea3](https://github.com/Bergert-Digital/Pediment/commit/8abeea3a19acce876e3782e706dd5b8901436294))
