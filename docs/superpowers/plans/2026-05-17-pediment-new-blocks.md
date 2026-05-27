# Pediment New Section Blocks (Plan 4)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans. Steps use checkbox (`- [ ]`).

**Goal:** Add the three genuinely-new Pediment section blocks — `starter/logo-cloud`, `starter/feature-grid` (+ `starter/feature` child), `starter/steps` (+ `starter/step` child) — so a client marketing page can be built entirely from `starter/*` blocks.

**Architecture:** Mirror the existing parent/child InnerBlocks pattern (`faq`/`faq-item`) and attribute-block pattern (`cta`). `logo-cloud` = caption attribute + InnerBlocks of `core/image` (WP-native; no custom child). `feature-grid`/`steps` = parent (InnerBlocks of a custom child) + child block with attributes (`feature`: icon/title/text/link; `step`: title/text, number via CSS counter). Server render mirrors `faq`: parent echoes pre-rendered `$content`; children render their own markup. Icons via the existing `starter_icon()` helper (sprite already printed on `wp_body_open`). Styling = new `style.scss` per block, mockup CSS ported to `var(--wp--preset--*)` + Plan-1 `--r-*` tokens. Blocks auto-register from `build/blocks/` (no registration code needed).

**Tech Stack:** WordPress FSE block theme, `@wordpress/scripts` (TS/SCSS, no custom webpack), apiVersion 3 blocks, PHP 8.1, PHPUnit (wp-env), Playwright.

**Spec:** `docs/superpowers/specs/2026-05-17-pediment-design-system-design.md` ("Section inventory → block mapping": logo-cloud, feature-grid, steps are the 3 NEW blocks). Visual ref: `docs/design/pediment-mockup.html`. Builds on merged Plans 1–3 (tokens incl. `accent-tint`; `--r-lg`/`--section`/`--band` in `assets/css/theme.css`; `starter_icon()`; band block-styles).

**Scope:** Exactly these 5 blocks (3 section + 2 children) + their PHPUnit BlockRender tests. NOT in scope (→ Plan 5): hero photo+glass, pull-quote→testimonial, blog-index→Insights. No changes to existing blocks, parts, theme.json, or registration code. The mega-menu workstream is unrelated — do not touch `src/blocks/mega-*` or `tests/e2e/mega-menu.spec.ts`.

**Verification constraint:** Worktree NOT mounted in wp-env. Per task: env-independent gates only — `npm run build` (compiles the new block TS/SCSS), `php -l` on render.php, `npx tsc --noEmit --skipLibCheck` on edit/index tsx, valid `block.json` JSON, and a static trace of each PHPUnit test against the render.php you wrote. Author the BlockRender test files (committed deliverables). Full PHPUnit + Playwright run POST-MERGE in the `:8888`/`:8889` checkout. **Definition of done: post-merge PHPUnit green (existing 99 + the new block tests), Playwright unaffected (no e2e added; the 4 unrelated mega-menu failures are out of scope), `npm run build` clean.**

---

## File Structure

Per block dir `src/blocks/<name>/`: `block.json`, `index.tsx`, `edit.tsx`, `render.php`, and (parents + logo-cloud) `style.scss`. Children (`feature`, `step`) have no own `style.scss` — styled by the parent's (like `faq-item`). Tests in `tests/phpunit/BlockRender/`.

| Block | Files |
|---|---|
| `starter/logo-cloud` | block.json, index.tsx, edit.tsx, render.php, style.scss |
| `starter/feature` (child) | block.json, index.tsx, edit.tsx, render.php |
| `starter/feature-grid` (parent) | block.json, index.tsx, edit.tsx, render.php, style.scss |
| `starter/step` (child) | block.json, index.tsx, edit.tsx, render.php |
| `starter/steps` (parent) | block.json, index.tsx, edit.tsx, render.php, style.scss |
| Tests | `LogoCloudTest.php`, `FeatureGridTest.php`, `StepsTest.php` |

Each task commits. Children are created before their parent so the editor template resolves.

---

### Task 1: `starter/logo-cloud`

**Files:** Create `src/blocks/logo-cloud/{block.json,index.tsx,edit.tsx,render.php,style.scss}`; Create `tests/phpunit/BlockRender/LogoCloudTest.php`.

- [ ] **Step 1: Write the failing test** `tests/phpunit/BlockRender/LogoCloudTest.php`:
```php
<?php

class LogoCloudTest extends WP_UnitTestCase {
	public function test_renders_caption_and_inner_images() {
		$html = do_blocks(
			'<!-- wp:starter/logo-cloud {"caption":"Trusted by leaders"} -->' .
			'<!-- wp:image {"className":"x"} --><figure class="wp-block-image x"><img src="/a.png" alt="Acme"/></figure><!-- /wp:image -->' .
			'<!-- /wp:starter/logo-cloud -->'
		);
		$this->assertStringContainsString( 'starter-logo-cloud', $html );
		$this->assertStringContainsString( 'starter-logo-cloud__caption', $html );
		$this->assertStringContainsString( 'Trusted by leaders', $html );
		$this->assertStringContainsString( '/a.png', $html );
	}

	public function test_omits_caption_when_empty() {
		$html = do_blocks( '<!-- wp:starter/logo-cloud --><!-- /wp:starter/logo-cloud -->' );
		$this->assertStringContainsString( 'starter-logo-cloud', $html );
		$this->assertStringNotContainsString( 'starter-logo-cloud__caption', $html );
	}
}
```

- [ ] **Step 2: `block.json`**
```json
{
	"$schema": "https://schemas.wp.org/trunk/block.json",
	"apiVersion": 3,
	"name": "starter/logo-cloud",
	"title": "Logo Cloud",
	"category": "starter",
	"description": "A “trusted by” strip of client or partner logos.",
	"textdomain": "starter",
	"supports": { "html": false, "align": [ "wide", "full" ] },
	"attributes": {
		"caption": { "type": "string", "default": "" }
	},
	"editorScript": "file:./index.js",
	"editorStyle": "file:./style-index.css",
	"style": "file:./style-index.css",
	"render": "file:./render.php"
}
```

- [ ] **Step 3: `index.tsx`**
```tsx
import { registerBlockType } from '@wordpress/blocks';
import { InnerBlocks } from '@wordpress/block-editor';
import metadata from './block.json';
import Edit from './edit';
import './style.scss';

registerBlockType( metadata.name, {
	edit: Edit,
	save: () => <InnerBlocks.Content />,
} );
```

- [ ] **Step 4: `edit.tsx`**
```tsx
import { __ } from '@wordpress/i18n';
import {
	useBlockProps,
	useInnerBlocksProps,
	RichText,
} from '@wordpress/block-editor';

const ALLOWED = [ 'core/image' ];
const TEMPLATE: [ string, Record< string, unknown > ][] = [
	[ 'core/image', {} ],
	[ 'core/image', {} ],
	[ 'core/image', {} ],
];

type Attrs = { caption: string };

export default function Edit( {
	attributes,
	setAttributes,
}: {
	attributes: Attrs;
	setAttributes: ( a: Partial< Attrs > ) => void;
} ) {
	const blockProps = useBlockProps( { className: 'starter-logo-cloud' } );
	const innerProps = useInnerBlocksProps(
		{ className: 'starter-logo-cloud__row' },
		{ allowedBlocks: ALLOWED, template: TEMPLATE, orientation: 'horizontal' }
	);
	return (
		<section { ...blockProps }>
			<RichText
				tagName="p"
				className="starter-logo-cloud__caption"
				value={ attributes.caption }
				onChange={ ( v ) => setAttributes( { caption: v } ) }
				placeholder={ __( 'Trusted by…', 'starter' ) }
			/>
			<div { ...innerProps } />
		</section>
	);
}
```

- [ ] **Step 5: `render.php`**
```php
<?php
/**
 * Server-side render for starter/logo-cloud.
 *
 * @var array  $attributes
 * @var string $content
 */

$caption = isset( $attributes['caption'] ) ? (string) $attributes['caption'] : '';
$wrapper = get_block_wrapper_attributes( array( 'class' => 'starter-logo-cloud' ) );
ob_start();
?>
<section <?php echo $wrapper; // phpcs:ignore WordPress.Security.EscapeOutput ?>>
	<?php if ( '' !== $caption ) : ?>
		<p class="starter-logo-cloud__caption"><?php echo wp_kses_post( $caption ); ?></p>
	<?php endif; ?>
	<div class="starter-logo-cloud__row">
		<?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput -- inner blocks pre-rendered ?>
	</div>
</section>
<?php
echo ob_get_clean();
```

- [ ] **Step 6: `style.scss`**
```scss
.starter-logo-cloud {
  padding-block: var(--band, 96px);

  &__caption {
    text-align: center;
    color: var(--wp--preset--color--text-muted);
    font-size: var(--wp--preset--font-size--sm);
    font-weight: 600;
    letter-spacing: .04em;
    margin: 0 0 var(--wp--preset--spacing--40);
  }

  &__row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: var(--wp--preset--spacing--40);
  }

  &__row .wp-block-image { margin: 0; }
  &__row img {
    max-height: 2rem;
    width: auto;
    opacity: .65;
    filter: grayscale(1);
    transition: opacity .15s ease, filter .15s ease;
  }
  &__row img:hover { opacity: 1; filter: none; }
}
```

- [ ] **Step 7: env-independent verify** — `npm run build` compiles; `php -l src/blocks/logo-cloud/render.php`; `npx tsc --noEmit --skipLibCheck src/blocks/logo-cloud/edit.tsx src/blocks/logo-cloud/index.tsx`; `python3 -c "import json;json.load(open('src/blocks/logo-cloud/block.json'))"`. Static-trace both LogoCloudTest methods against render.php (caption present ⇒ `__caption` p with text + `$content` images; empty caption ⇒ no `__caption`). Confirm only the 6 new files changed.

- [ ] **Step 8: Commit**
```bash
git add src/blocks/logo-cloud tests/phpunit/BlockRender/LogoCloudTest.php
git commit -m "feat(blocks): starter/logo-cloud (caption + image row)"
```

---

### Task 2: `starter/feature` (child block)

**Files:** Create `src/blocks/feature/{block.json,index.tsx,edit.tsx,render.php}` (no style.scss — styled by feature-grid in Task 3).

- [ ] **Step 1: `block.json`**
```json
{
	"$schema": "https://schemas.wp.org/trunk/block.json",
	"apiVersion": 3,
	"name": "starter/feature",
	"title": "Feature",
	"category": "starter",
	"description": "A single icon + title + text + optional link card.",
	"parent": [ "starter/feature-grid" ],
	"textdomain": "starter",
	"supports": { "html": false, "inserter": false },
	"attributes": {
		"icon": { "type": "string", "default": "trend-up" },
		"title": { "type": "string", "default": "" },
		"text": { "type": "string", "default": "" },
		"linkText": { "type": "string", "default": "" },
		"linkUrl": { "type": "string", "default": "" }
	},
	"editorScript": "file:./index.js",
	"render": "file:./render.php"
}
```

- [ ] **Step 2: `index.tsx`**
```tsx
import { registerBlockType } from '@wordpress/blocks';
import metadata from './block.json';
import Edit from './edit';

registerBlockType( metadata.name, { edit: Edit, save: () => null } );
```

- [ ] **Step 3: `edit.tsx`**
```tsx
import { __ } from '@wordpress/i18n';
import {
	useBlockProps,
	RichText,
	InspectorControls,
} from '@wordpress/block-editor';
import { PanelBody, TextControl } from '@wordpress/components';

type Attrs = {
	icon: string;
	title: string;
	text: string;
	linkText: string;
	linkUrl: string;
};

export default function Edit( {
	attributes,
	setAttributes,
}: {
	attributes: Attrs;
	setAttributes: ( a: Partial< Attrs > ) => void;
} ) {
	const blockProps = useBlockProps( { className: 'starter-feature' } );
	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Feature', 'starter' ) }>
					<TextControl
						label={ __( 'Phosphor icon name', 'starter' ) }
						value={ attributes.icon }
						onChange={ ( v ) => setAttributes( { icon: v } ) }
						help={ __( 'e.g. trend-up, gear, stack', 'starter' ) }
					/>
					<TextControl
						label={ __( 'Link URL', 'starter' ) }
						value={ attributes.linkUrl }
						onChange={ ( v ) => setAttributes( { linkUrl: v } ) }
					/>
				</PanelBody>
			</InspectorControls>
			<div { ...blockProps }>
				<div className="starter-feature__ic" aria-hidden="true">
					{ attributes.icon }
				</div>
				<RichText
					tagName="h3"
					className="starter-feature__title"
					value={ attributes.title }
					onChange={ ( v ) => setAttributes( { title: v } ) }
					placeholder={ __( 'Title…', 'starter' ) }
				/>
				<RichText
					tagName="p"
					className="starter-feature__text"
					value={ attributes.text }
					onChange={ ( v ) => setAttributes( { text: v } ) }
					placeholder={ __( 'Description…', 'starter' ) }
				/>
				<RichText
					tagName="span"
					className="starter-feature__more"
					value={ attributes.linkText }
					onChange={ ( v ) => setAttributes( { linkText: v } ) }
					placeholder={ __( 'Link text (optional)…', 'starter' ) }
				/>
			</div>
		</>
	);
}
```

- [ ] **Step 4: `render.php`**
```php
<?php
/**
 * Server-side render for starter/feature.
 *
 * @var array $attributes
 */

$icon      = isset( $attributes['icon'] ) ? (string) $attributes['icon'] : '';
$title     = isset( $attributes['title'] ) ? (string) $attributes['title'] : '';
$text      = isset( $attributes['text'] ) ? (string) $attributes['text'] : '';
$link_text = isset( $attributes['linkText'] ) ? (string) $attributes['linkText'] : '';
$link_url  = isset( $attributes['linkUrl'] ) ? (string) $attributes['linkUrl'] : '';

if ( '' === $title && '' === $text ) {
	return '';
}

$wrapper = get_block_wrapper_attributes( array( 'class' => 'starter-feature' ) );
ob_start();
?>
<div <?php echo $wrapper; // phpcs:ignore WordPress.Security.EscapeOutput ?>>
	<?php if ( '' !== $icon && function_exists( 'starter_icon' ) ) : ?>
		<span class="starter-feature__ic"><?php echo starter_icon( $icon ); // phpcs:ignore WordPress.Security.EscapeOutput -- theme-controlled sprite ?></span>
	<?php endif; ?>
	<?php if ( '' !== $title ) : ?>
		<h3 class="starter-feature__title"><?php echo wp_kses_post( $title ); ?></h3>
	<?php endif; ?>
	<?php if ( '' !== $text ) : ?>
		<p class="starter-feature__text"><?php echo wp_kses_post( $text ); ?></p>
	<?php endif; ?>
	<?php if ( '' !== $link_text && '' !== $link_url ) : ?>
		<a class="starter-feature__more" href="<?php echo esc_url( $link_url ); ?>"><?php echo wp_kses_post( $link_text ); ?></a>
	<?php endif; ?>
</div>
<?php
echo ob_get_clean();
```

- [ ] **Step 5: verify** — `npm run build`; `php -l src/blocks/feature/render.php`; `tsc --noEmit --skipLibCheck` on its tsx; valid block.json. (No phpunit yet — covered by FeatureGridTest in Task 3.) Only the 4 new files changed.

- [ ] **Step 6: Commit**
```bash
git add src/blocks/feature
git commit -m "feat(blocks): starter/feature child (icon/title/text/link)"
```

---

### Task 3: `starter/feature-grid` (parent)

**Files:** Create `src/blocks/feature-grid/{block.json,index.tsx,edit.tsx,render.php,style.scss}`; Create `tests/phpunit/BlockRender/FeatureGridTest.php`.

- [ ] **Step 1: Write the failing test** `tests/phpunit/BlockRender/FeatureGridTest.php`:
```php
<?php

class FeatureGridTest extends WP_UnitTestCase {
	public function test_grid_wraps_features_with_icon_title_link() {
		$html = do_blocks(
			'<!-- wp:starter/feature-grid -->' .
			'<!-- wp:starter/feature {"icon":"gear","title":"Ops","text":"Run it","linkText":"More","linkUrl":"/ops"} /-->' .
			'<!-- wp:starter/feature {"icon":"stack","title":"Digital","text":"Ship it"} /-->' .
			'<!-- /wp:starter/feature-grid -->'
		);
		$this->assertStringContainsString( 'starter-feature-grid', $html );
		$this->assertStringContainsString( 'starter-feature', $html );
		$this->assertStringContainsString( 'Ops', $html );
		$this->assertStringContainsString( 'Digital', $html );
		$this->assertStringContainsString( 'href="/ops"', $html );
		$this->assertStringContainsString( 'href="#ph-gear"', $html );
		$this->assertStringContainsString( 'href="#ph-stack"', $html );
	}

	public function test_feature_omits_link_when_url_missing() {
		$html = do_blocks(
			'<!-- wp:starter/feature {"title":"T","text":"D","linkText":"More","linkUrl":""} /-->'
		);
		$this->assertStringNotContainsString( 'starter-feature__more', $html );
	}
}
```

- [ ] **Step 2: `block.json`**
```json
{
	"$schema": "https://schemas.wp.org/trunk/block.json",
	"apiVersion": 3,
	"name": "starter/feature-grid",
	"title": "Feature Grid",
	"category": "starter",
	"description": "A responsive grid of feature cards. Contains Feature child blocks.",
	"textdomain": "starter",
	"supports": { "html": false, "align": [ "wide", "full" ] },
	"attributes": {},
	"editorScript": "file:./index.js",
	"editorStyle": "file:./style-index.css",
	"style": "file:./style-index.css",
	"render": "file:./render.php"
}
```

- [ ] **Step 3: `index.tsx`**
```tsx
import { registerBlockType } from '@wordpress/blocks';
import { InnerBlocks } from '@wordpress/block-editor';
import metadata from './block.json';
import Edit from './edit';
import './style.scss';

registerBlockType( metadata.name, {
	edit: Edit,
	save: () => <InnerBlocks.Content />,
} );
```

- [ ] **Step 4: `edit.tsx`**
```tsx
import { useBlockProps, useInnerBlocksProps } from '@wordpress/block-editor';

const ALLOWED = [ 'starter/feature' ];
const TEMPLATE: [ string, Record< string, unknown > ][] = [
	[ 'starter/feature', { icon: 'trend-up' } ],
	[ 'starter/feature', { icon: 'gear' } ],
	[ 'starter/feature', { icon: 'stack' } ],
];

export default function Edit() {
	const blockProps = useBlockProps( { className: 'starter-feature-grid' } );
	const innerProps = useInnerBlocksProps( blockProps, {
		allowedBlocks: ALLOWED,
		template: TEMPLATE,
		templateLock: false,
	} );
	return <section { ...innerProps } />;
}
```

- [ ] **Step 5: `render.php`**
```php
<?php
/**
 * Server-side render for starter/feature-grid.
 *
 * @var array  $attributes
 * @var string $content
 */

$wrapper = get_block_wrapper_attributes( array( 'class' => 'starter-feature-grid' ) );
?>
<section <?php echo $wrapper; // phpcs:ignore WordPress.Security.EscapeOutput ?>>
	<?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput -- inner blocks pre-rendered ?>
</section>
```

- [ ] **Step 6: `style.scss`**
```scss
.starter-feature-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 22px;
}

@media (max-width: 781px) {
  .starter-feature-grid { grid-template-columns: 1fr; }
}

.starter-feature {
  background: var(--wp--preset--color--surface);
  border: 1px solid var(--wp--preset--color--border);
  border-radius: var(--r-lg, 20px);
  padding: 32px;
  transition: box-shadow .18s ease, transform .18s ease, border-color .18s ease;

  &:hover {
    box-shadow: var(--wp--preset--shadow--subtle);
    transform: translateY(-3px);
    border-color: var(--wp--preset--color--border-strong);
  }

  &__ic {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 46px;
    height: 46px;
    border-radius: 12px;
    background: var(--wp--preset--color--accent-tint);
    color: var(--wp--preset--color--accent);
    margin-bottom: 20px;
  }
  &__ic .i { width: 22px; height: 22px; }

  &__title {
    margin: 0;
    font-size: 1.15rem;
    font-weight: 700;
    color: var(--wp--preset--color--text);
  }

  &__text {
    color: var(--wp--preset--color--text-muted);
    margin: 10px 0 0;
    font-size: .97rem;
    line-height: 1.55;
  }

  &__more {
    display: inline-block;
    margin-top: 18px;
    color: var(--wp--preset--color--accent);
    font-weight: 700;
    font-size: .92rem;
    text-decoration: none;
  }
  &__more:hover { color: var(--wp--preset--color--accent-hover); }
}
```

- [ ] **Step 7: verify** — `npm run build`; `php -l src/blocks/feature-grid/render.php`; `tsc --noEmit --skipLibCheck`; valid block.json; brace-balanced scss. Static-trace FeatureGridTest against feature/render.php (Task 2) + this parent: parent echoes `$content`; each `starter/feature` renders `starter-feature`, title, `starter_icon('gear')` ⇒ `<use href="#ph-gear">`, link `href="/ops"`; missing linkUrl ⇒ no `starter-feature__more`. Confirm assertions pass. Only the 6 new files changed.

- [ ] **Step 8: Commit**
```bash
git add src/blocks/feature-grid tests/phpunit/BlockRender/FeatureGridTest.php
git commit -m "feat(blocks): starter/feature-grid parent (3-up cards)"
```

---

### Task 4: `starter/step` (child block)

**Files:** Create `src/blocks/step/{block.json,index.tsx,edit.tsx,render.php}` (no style.scss).

- [ ] **Step 1: `block.json`**
```json
{
	"$schema": "https://schemas.wp.org/trunk/block.json",
	"apiVersion": 3,
	"name": "starter/step",
	"title": "Step",
	"category": "starter",
	"description": "A single numbered step (number auto-generated).",
	"parent": [ "starter/steps" ],
	"textdomain": "starter",
	"supports": { "html": false, "inserter": false },
	"attributes": {
		"title": { "type": "string", "default": "" },
		"text": { "type": "string", "default": "" }
	},
	"editorScript": "file:./index.js",
	"render": "file:./render.php"
}
```

- [ ] **Step 2: `index.tsx`**
```tsx
import { registerBlockType } from '@wordpress/blocks';
import metadata from './block.json';
import Edit from './edit';

registerBlockType( metadata.name, { edit: Edit, save: () => null } );
```

- [ ] **Step 3: `edit.tsx`**
```tsx
import { __ } from '@wordpress/i18n';
import { useBlockProps, RichText } from '@wordpress/block-editor';

type Attrs = { title: string; text: string };

export default function Edit( {
	attributes,
	setAttributes,
}: {
	attributes: Attrs;
	setAttributes: ( a: Partial< Attrs > ) => void;
} ) {
	const blockProps = useBlockProps( { className: 'starter-step' } );
	return (
		<div { ...blockProps }>
			<span className="starter-step__num" aria-hidden="true" />
			<div>
				<RichText
					tagName="h3"
					className="starter-step__title"
					value={ attributes.title }
					onChange={ ( v ) => setAttributes( { title: v } ) }
					placeholder={ __( 'Step title…', 'starter' ) }
				/>
				<RichText
					tagName="p"
					className="starter-step__text"
					value={ attributes.text }
					onChange={ ( v ) => setAttributes( { text: v } ) }
					placeholder={ __( 'Step description…', 'starter' ) }
				/>
			</div>
		</div>
	);
}
```

- [ ] **Step 4: `render.php`**
```php
<?php
/**
 * Server-side render for starter/step. Number is generated via CSS counter.
 *
 * @var array $attributes
 */

$title = isset( $attributes['title'] ) ? (string) $attributes['title'] : '';
$text  = isset( $attributes['text'] ) ? (string) $attributes['text'] : '';

if ( '' === $title && '' === $text ) {
	return '';
}

$wrapper = get_block_wrapper_attributes( array( 'class' => 'starter-step' ) );
ob_start();
?>
<div <?php echo $wrapper; // phpcs:ignore WordPress.Security.EscapeOutput ?>>
	<span class="starter-step__num" aria-hidden="true"></span>
	<div class="starter-step__body">
		<?php if ( '' !== $title ) : ?>
			<h3 class="starter-step__title"><?php echo wp_kses_post( $title ); ?></h3>
		<?php endif; ?>
		<?php if ( '' !== $text ) : ?>
			<p class="starter-step__text"><?php echo wp_kses_post( $text ); ?></p>
		<?php endif; ?>
	</div>
</div>
<?php
echo ob_get_clean();
```

- [ ] **Step 5: verify** — `npm run build`; `php -l src/blocks/step/render.php`; `tsc --noEmit --skipLibCheck`; valid block.json. Only the 4 new files changed.

- [ ] **Step 6: Commit**
```bash
git add src/blocks/step
git commit -m "feat(blocks): starter/step child (title/text, CSS-counter number)"
```

---

### Task 5: `starter/steps` (parent)

**Files:** Create `src/blocks/steps/{block.json,index.tsx,edit.tsx,render.php,style.scss}`; Create `tests/phpunit/BlockRender/StepsTest.php`.

- [ ] **Step 1: Write the failing test** `tests/phpunit/BlockRender/StepsTest.php`:
```php
<?php

class StepsTest extends WP_UnitTestCase {
	public function test_steps_wraps_step_children() {
		$html = do_blocks(
			'<!-- wp:starter/steps -->' .
			'<!-- wp:starter/step {"title":"Diagnose","text":"Size it"} /-->' .
			'<!-- wp:starter/step {"title":"Deliver","text":"Ship it"} /-->' .
			'<!-- /wp:starter/steps -->'
		);
		$this->assertStringContainsString( 'starter-steps', $html );
		$this->assertStringContainsString( 'starter-step__num', $html );
		$this->assertStringContainsString( 'Diagnose', $html );
		$this->assertStringContainsString( 'Deliver', $html );
		$this->assertSame( 2, substr_count( $html, 'starter-step__title' ) );
	}

	public function test_step_skips_empty() {
		$html = do_blocks( '<!-- wp:starter/step {"title":"","text":""} /-->' );
		$this->assertStringNotContainsString( 'starter-step__title', $html );
	}
}
```

- [ ] **Step 2: `block.json`**
```json
{
	"$schema": "https://schemas.wp.org/trunk/block.json",
	"apiVersion": 3,
	"name": "starter/steps",
	"title": "Steps",
	"category": "starter",
	"description": "An ordered list of numbered steps. Contains Step child blocks.",
	"textdomain": "starter",
	"supports": { "html": false, "align": [ "wide" ] },
	"attributes": {},
	"editorScript": "file:./index.js",
	"editorStyle": "file:./style-index.css",
	"style": "file:./style-index.css",
	"render": "file:./render.php"
}
```

- [ ] **Step 3: `index.tsx`** (identical pattern to feature-grid)
```tsx
import { registerBlockType } from '@wordpress/blocks';
import { InnerBlocks } from '@wordpress/block-editor';
import metadata from './block.json';
import Edit from './edit';
import './style.scss';

registerBlockType( metadata.name, {
	edit: Edit,
	save: () => <InnerBlocks.Content />,
} );
```

- [ ] **Step 4: `edit.tsx`**
```tsx
import { useBlockProps, useInnerBlocksProps } from '@wordpress/block-editor';

const ALLOWED = [ 'starter/step' ];
const TEMPLATE: [ string, Record< string, unknown > ][] = [
	[ 'starter/step', {} ],
	[ 'starter/step', {} ],
	[ 'starter/step', {} ],
];

export default function Edit() {
	const blockProps = useBlockProps( { className: 'starter-steps' } );
	const innerProps = useInnerBlocksProps( blockProps, {
		allowedBlocks: ALLOWED,
		template: TEMPLATE,
		templateLock: false,
	} );
	return <div { ...innerProps } />;
}
```

- [ ] **Step 5: `render.php`**
```php
<?php
/**
 * Server-side render for starter/steps.
 *
 * @var array  $attributes
 * @var string $content
 */

$wrapper = get_block_wrapper_attributes( array( 'class' => 'starter-steps' ) );
?>
<div <?php echo $wrapper; // phpcs:ignore WordPress.Security.EscapeOutput ?>>
	<?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput -- inner blocks pre-rendered ?>
</div>
```

- [ ] **Step 6: `style.scss`** (CSS counter generates the `01, 02 …` numbers — no PHP index needed)
```scss
.starter-steps {
  counter-reset: starter-step;
  display: flex;
  flex-direction: column;
  gap: 22px;
}

.starter-step {
  display: grid;
  grid-template-columns: auto 1fr;
  gap: 18px;
  counter-increment: starter-step;

  &__num {
    width: 36px;
    height: 36px;
    border: 1px solid var(--wp--preset--color--border);
    background: var(--wp--preset--color--surface);
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 13px;
    font-weight: 800;
    color: var(--wp--preset--color--accent);
  }
  &__num::before {
    content: counter(starter-step, decimal-leading-zero);
  }

  &__title {
    margin: 0;
    font-size: 1.15rem;
    font-weight: 700;
    color: var(--wp--preset--color--text);
  }

  &__text {
    color: var(--wp--preset--color--text-muted);
    font-size: .96rem;
    margin: 5px 0 0;
    line-height: 1.5;
  }
}
```

- [ ] **Step 7: verify** — `npm run build`; `php -l src/blocks/steps/render.php`; `tsc --noEmit --skipLibCheck`; valid block.json; brace-balanced scss. Static-trace StepsTest against step/render.php (Task 4) + this parent: 2 steps ⇒ `starter-steps` once, `starter-step__num` present, two `starter-step__title`; empty step ⇒ none. Confirm. Only the 6 new files changed.

- [ ] **Step 8: Commit**
```bash
git add src/blocks/steps tests/phpunit/BlockRender/StepsTest.php
git commit -m "feat(blocks): starter/steps parent (numbered, CSS counter)"
```

---

### Task 6: Build + cumulative guard

**Files:** none (verification only).

- [ ] **Step 1:** `npm run build` — webpack compiles; confirm `build/blocks/{logo-cloud,feature,feature-grid,step,steps}/` each exist with `block.json` + `index.js` (+ `style-index.css` for the 3 with scss).
- [ ] **Step 2:** `git diff <branch-base>..HEAD --name-only` — only `src/blocks/{logo-cloud,feature,feature-grid,step,steps}/**` and `tests/phpunit/BlockRender/{LogoCloud,FeatureGrid,Steps}Test.php`. NO changes to existing blocks, parts, theme.json, inc/**, or any `mega-*`.
- [ ] **Step 3:** `php -l` on all 5 render.php; every `block.json` valid JSON with `"category":"starter"` and `"name":"starter/<x>"`; `feature`/`step` have `"parent"` + `"inserter":false`. `git status --porcelain` clean (besides pre-existing untracked `docs/images/`).

**Post-merge (main checkout `:8888`/`:8889`, controller — NOT a worktree step):** `npm run build` → `npx wp-env run cli wp theme activate wp-starter-theme` → full `vendor/bin/phpunit` (expect: prior 99 + new LogoCloud/FeatureGrid/Steps tests all green; the auto-registration glob picks up the 5 new `build/blocks/*` dirs). `npx playwright test` — unchanged scope (no e2e added); the 4 unrelated `mega-menu.spec.ts` failures remain out of scope and are NOT a Plan-4 gate.

---

## Self-Review

**Spec coverage:** logo-cloud (Task 1), feature-grid + feature (Tasks 2–3), steps + step (Tasks 4–5) — the exact 3 new section blocks from the spec's section inventory. Build/guard (Task 6). Deferred items (hero/pull-quote/blog structural) are Plan 5 — intentional, not gaps.

**Placeholder scan:** none — every block.json/tsx/php/scss/test is complete and final.

**Type/name consistency:** Block names `starter/logo-cloud|feature|feature-grid|step|steps`; children `parent` arrays reference the exact parent names (`starter/feature-grid`, `starter/steps`); `edit.tsx` `ALLOWED` arrays match the child names; render BEM classes (`starter-feature__ic/__title/__text/__more`, `starter-step__num/__title/__text`, `starter-logo-cloud__caption/__row`) match each `style.scss` exactly and the PHPUnit assertions. `starter_icon($icon)` output `<use href="#ph-<slug>">` matches FeatureGridTest's `href="#ph-gear"`/`href="#ph-stack"` (icons `gear`/`stack` exist in the Plan-1 sprite; default `trend-up` also in sprite). `accent-tint` token exists (Plan 1). `--r-lg`/`--band` referenced with literal fallbacks so they resolve even if Plan-1 `theme.css` load order varies. Children created before parents (Tasks 2→3, 4→5) so editor templates resolve; PHP `do_blocks` order-independent.

**Regression safety:** Only new `src/blocks/*` dirs + 3 new test files; Task 6 Step 2 asserts no existing block/part/theme.json/mega-* touched, so existing PHPUnit/e2e suites are unaffected. New blocks auto-register via the existing `build/blocks` glob — no registration code change. Parents echo pre-rendered `$content` (same safe pattern as `faq`); children `wp_kses_post`/`esc_url`/`starter_icon` (esc_attr-internally) — no new escaping risk.
