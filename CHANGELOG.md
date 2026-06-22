# Changelog

## [0.8.0](https://github.com/Bergert-Digital/pediment/compare/v0.7.0...v0.8.0) (2026-06-22)


### Features

* image/content slider block ([#33](https://github.com/Bergert-Digital/pediment/issues/33)) ([3d1c8ea](https://github.com/Bergert-Digital/pediment/commit/3d1c8eaa05b0fe59bb6f2ceb70a8df8ad4d6a572))

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
