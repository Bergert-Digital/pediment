# Mega Menu Block Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ship a theme-provided mega menu — three nested blocks (`starter/mega-menu` → `starter/mega-column` → `starter/mega-link`) that slot into the core Navigation block, with hover/focus dropdown on desktop and an accordion in the mobile overlay.

**Architecture:** Mirrors the existing `starter/faq`→`starter/faq-item` InnerBlocks pattern. Editor insertion via `"parent": ["core/navigation"]`; render wrapping via the core `block_core_navigation_listable_blocks` filter (WP 6.5.0). Front-end open/close/Escape/click-outside/accordion via the WordPress Interactivity API (`viewScriptModule`), mirroring core's Navigation-submenu directives.

**Tech Stack:** WordPress block theme (FSE, WP 6.5), `@wordpress/scripts` 28 (`npm run build` → `build/blocks/`), `@wordpress/interactivity`, PHPUnit (wp-env tests container), Playwright (`@playwright/test`, vs :8890).

---

## Constraints (read before starting)

- **Build is required for block tests.** Blocks register from `build/blocks/<name>/` (compiled from `src/blocks/`). `npm run build` runs locally with no WordPress — it works in a worktree.
- **PHPUnit and Playwright need the live env.** They run in / against the child-theme wp-env at `localhost:8890`, which serves the **main checkout** of this theme. **Do NOT run `wp-env start` / `npm run env:start` here** (single-test-env rule). If the plan is executed in a worktree, treat build + lint + `php -l` + JSON-validity as the in-worktree gates and run **PHPUnit + Playwright after merge to `development`** against :8890 (the established pattern in this repo). The test files are deliverables committed with each task regardless.
- **Run PHPUnit** (post-merge): `cd ~/Entwicklung/wp-starter-child-theme && npx wp-env run tests-wordpress --env-cwd=wp-content/themes/wp-starter-theme vendor/bin/phpunit --filter <Name>` (the env must already be running; do not start it).
- **Run Playwright** (post-merge): `PLAYWRIGHT_BASE_URL=http://localhost:8890 npx playwright test tests/e2e/mega-menu.spec.ts` (config already honors `PLAYWRIGHT_BASE_URL`).
- No schema/migration tasks in this plan.

## File Structure

- `src/blocks/mega-link/` — `block.json`, `index.tsx`, `edit.tsx`, `render.php`, `style.scss` (leaf, dynamic)
- `src/blocks/mega-column/` — same set (container of mega-link)
- `src/blocks/mega-menu/` — same set + `view.ts` (Interactivity module; container of mega-column)
- `inc/mega-menu.php` — `block_core_navigation_listable_blocks` filter (new)
- `functions.php` — one `require_once` line
- `patterns/mega-menu-header.php` — demo/fixture pattern (deterministic e2e page)
- `tests/phpunit/MegaMenu/` — `ListableFilterTest.php`, `RenderTest.php`
- `tests/e2e/mega-menu.spec.ts` — interaction / a11y / responsive

Each block folder is self-contained (one responsibility). The Interactivity logic lives only in `mega-menu/view.ts`; columns/links are pure markup.

---

### Task 1: Nav listable-blocks filter

**Files:**
- Create: `inc/mega-menu.php`
- Modify: `functions.php`
- Create: `tests/phpunit/MegaMenu/ListableFilterTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/phpunit/MegaMenu/ListableFilterTest.php`:

```php
<?php

class ListableFilterTest extends WP_UnitTestCase {
	public function test_mega_menu_is_a_listable_navigation_block() {
		$blocks = apply_filters( 'block_core_navigation_listable_blocks', array() );
		$this->assertContains( 'starter/mega-menu', $blocks );
	}

	public function test_filter_preserves_existing_entries() {
		$blocks = apply_filters( 'block_core_navigation_listable_blocks', array( 'core/site-title' ) );
		$this->assertContains( 'core/site-title', $blocks );
		$this->assertContains( 'starter/mega-menu', $blocks );
	}
}
```

- [ ] **Step 2: Run test to verify it fails**

Run (post-merge env): `npx wp-env run tests-wordpress --env-cwd=wp-content/themes/wp-starter-theme vendor/bin/phpunit --filter ListableFilterTest`
Expected: FAIL — `starter/mega-menu` not in the array (filter not registered yet).

- [ ] **Step 3: Create the filter**

Create `inc/mega-menu.php`:

```php
<?php
/**
 * Mega menu: allow starter/mega-menu to render as a core/navigation item.
 *
 * Editor insertion is handled by the block's own "parent": ["core/navigation"]
 * declaration. Render-time, core only wraps known blocks in the nav <li>; we
 * add ours to that set via the core filter (WP 6.5.0+).
 *
 * @package Starter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter(
	'block_core_navigation_listable_blocks',
	function ( $blocks ) {
		$blocks   = is_array( $blocks ) ? $blocks : array();
		$blocks[] = 'starter/mega-menu';
		return array_values( array_unique( $blocks ) );
	}
);
```

- [ ] **Step 4: Wire it into functions.php**

In `functions.php`, the block of nav requires is:

```php
require_once __DIR__ . '/inc/seed.php';
require_once __DIR__ . '/inc/nav-active.php';
require_once __DIR__ . '/inc/nav-seed.php';
```

Add one line immediately after the `nav-seed.php` line:

```php
require_once __DIR__ . '/inc/mega-menu.php';
```

- [ ] **Step 5: In-worktree gates**

Run: `php -l inc/mega-menu.php` and `php -l functions.php`
Expected: "No syntax errors detected" for both.

- [ ] **Step 6: Run test to verify it passes** (post-merge env)

Run: `npx wp-env run tests-wordpress --env-cwd=wp-content/themes/wp-starter-theme vendor/bin/phpunit --filter ListableFilterTest`
Expected: PASS (2 tests).

- [ ] **Step 7: Commit**

```bash
git add inc/mega-menu.php functions.php tests/phpunit/MegaMenu/ListableFilterTest.php
git commit -m "feat(theme): allow starter/mega-menu as a core navigation item"
```

---

### Task 2: `starter/mega-link` block (leaf)

**Files:**
- Create: `src/blocks/mega-link/block.json`, `index.tsx`, `edit.tsx`, `render.php`, `style.scss`
- Create: `tests/phpunit/MegaMenu/RenderTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/phpunit/MegaMenu/RenderTest.php`:

```php
<?php

class RenderTest extends WP_UnitTestCase {
	public function test_mega_link_renders_icon_label_description_href() {
		$html = do_blocks(
			'<!-- wp:starter/mega-link {"label":"Pricing","url":"/pricing","description":"Plans & costs","icon":"tag"} /-->'
		);
		$this->assertStringContainsString( 'href="/pricing"', $html );
		$this->assertStringContainsString( 'Pricing', $html );
		$this->assertStringContainsString( 'Plans &amp; costs', $html );
		$this->assertStringContainsString( '#ph-tag', $html );
		$this->assertStringContainsString( 'starter-mega-link', $html );
	}

	public function test_mega_link_omits_empty_icon_and_description() {
		$html = do_blocks( '<!-- wp:starter/mega-link {"label":"Docs","url":"/docs"} /-->' );
		$this->assertStringContainsString( 'href="/docs"', $html );
		$this->assertStringNotContainsString( '<svg', $html );
		$this->assertStringNotContainsString( 'starter-mega-link__desc', $html );
	}

	public function test_mega_link_renders_nothing_without_label_and_url() {
		$html = do_blocks( '<!-- wp:starter/mega-link /-->' );
		$this->assertStringNotContainsString( 'starter-mega-link', $html );
	}
}
```

- [ ] **Step 2: Run test to verify it fails** (post-merge env, after a build)

Run: `npm run build && (cd ~/Entwicklung/wp-starter-child-theme && npx wp-env run tests-wordpress --env-cwd=wp-content/themes/wp-starter-theme vendor/bin/phpunit --filter RenderTest::test_mega_link)`
Expected: FAIL — block not registered.

- [ ] **Step 3: Create `src/blocks/mega-link/block.json`**

```json
{
	"$schema": "https://schemas.wp.org/trunk/block.json",
	"apiVersion": 3,
	"name": "starter/mega-link",
	"title": "Mega Link",
	"category": "starter",
	"description": "A single icon + label + description link inside a mega-menu column.",
	"parent": [ "starter/mega-column" ],
	"textdomain": "starter",
	"supports": { "html": false, "inserter": false },
	"attributes": {
		"label": { "type": "string", "default": "" },
		"url": { "type": "string", "default": "" },
		"description": { "type": "string", "default": "" },
		"icon": { "type": "string", "default": "" }
	},
	"editorScript": "file:./index.js",
	"editorStyle": "file:./style-index.css",
	"style": "file:./style-index.css",
	"render": "file:./render.php"
}
```

- [ ] **Step 4: Create `src/blocks/mega-link/index.tsx`**

```tsx
import { registerBlockType } from '@wordpress/blocks';
import metadata from './block.json';
import Edit from './edit';
import './style.scss';

registerBlockType( metadata.name, {
	edit: Edit,
	save: () => null,
} );
```

- [ ] **Step 5: Create `src/blocks/mega-link/edit.tsx`**

```tsx
import { __ } from '@wordpress/i18n';
import { useBlockProps, RichText } from '@wordpress/block-editor';
import { TextControl } from '@wordpress/components';

type Attrs = { label: string; url: string; description: string; icon: string };

export default function Edit( {
	attributes,
	setAttributes,
}: {
	attributes: Attrs;
	setAttributes: ( a: Partial< Attrs > ) => void;
} ) {
	const blockProps = useBlockProps( { className: 'starter-mega-link' } );
	return (
		<div { ...blockProps }>
			<TextControl
				label={ __( 'Icon (Phosphor name)', 'starter' ) }
				value={ attributes.icon }
				onChange={ ( v ) => setAttributes( { icon: v } ) }
			/>
			<TextControl
				label={ __( 'URL', 'starter' ) }
				value={ attributes.url }
				onChange={ ( v ) => setAttributes( { url: v } ) }
			/>
			<RichText
				tagName="div"
				className="starter-mega-link__label"
				value={ attributes.label }
				onChange={ ( v ) => setAttributes( { label: v } ) }
				placeholder={ __( 'Link label…', 'starter' ) }
			/>
			<RichText
				tagName="div"
				className="starter-mega-link__desc"
				value={ attributes.description }
				onChange={ ( v ) => setAttributes( { description: v } ) }
				placeholder={ __( 'Short description…', 'starter' ) }
			/>
		</div>
	);
}
```

- [ ] **Step 6: Create `src/blocks/mega-link/render.php`**

```php
<?php
/**
 * Server-side render for starter/mega-link.
 *
 * @var array $attributes
 */

$label = isset( $attributes['label'] ) ? trim( (string) $attributes['label'] ) : '';
$url   = isset( $attributes['url'] ) ? trim( (string) $attributes['url'] ) : '';
$desc  = isset( $attributes['description'] ) ? trim( (string) $attributes['description'] ) : '';
$icon  = isset( $attributes['icon'] ) ? trim( (string) $attributes['icon'] ) : '';

if ( '' === $label && '' === $url ) {
	return '';
}

$wrapper = get_block_wrapper_attributes( array( 'class' => 'starter-mega-link' ) );
ob_start();
?>
<a <?php echo $wrapper; // phpcs:ignore WordPress.Security.EscapeOutput ?> href="<?php echo esc_url( $url ); ?>">
	<?php
	if ( '' !== $icon && function_exists( 'starter_icon' ) ) {
		echo starter_icon( $icon, 'starter-mega-link__icon' ); // phpcs:ignore WordPress.Security.EscapeOutput -- theme-controlled sprite SVG
	}
	?>
	<span class="starter-mega-link__label"><?php echo wp_kses_post( $label ); ?></span>
	<?php if ( '' !== $desc ) : ?>
		<span class="starter-mega-link__desc"><?php echo wp_kses_post( $desc ); ?></span>
	<?php endif; ?>
</a>
<?php
echo ob_get_clean();
```

- [ ] **Step 7: Create `src/blocks/mega-link/style.scss`**

```scss
.starter-mega-link {
  display: grid;
  grid-template-columns: auto 1fr;
  column-gap: var(--wp--preset--spacing--20);
  align-items: start;
  padding: var(--wp--preset--spacing--20);
  border-radius: 0.5rem;
  text-decoration: none;
  color: var(--wp--preset--color--text);

  &:hover { background: var(--wp--preset--color--surface-elevated); color: var(--wp--preset--color--accent); }

  &__icon { grid-row: 1 / span 2; width: 1.25rem; height: 1.25rem; color: var(--wp--preset--color--accent); }
  &__label { font-weight: 600; }
  &__desc { grid-column: 2; color: var(--wp--preset--color--text-muted); font-size: var(--wp--preset--font-size--sm); }
}
```

- [ ] **Step 8: Build + in-worktree gates**

Run: `npm run build && npm run lint:colors && npm run lint:blocks`
Expected: build succeeds; "No color literals…"; block lint passes.

- [ ] **Step 9: Run test to verify it passes** (post-merge env)

Run: `(cd ~/Entwicklung/wp-starter-child-theme && npx wp-env run tests-wordpress --env-cwd=wp-content/themes/wp-starter-theme vendor/bin/phpunit --filter RenderTest::test_mega_link)`
Expected: PASS (the 3 `test_mega_link*` tests).

- [ ] **Step 10: Commit**

```bash
git add src/blocks/mega-link tests/phpunit/MegaMenu/RenderTest.php
git commit -m "feat(theme): starter/mega-link block"
```

---

### Task 3: `starter/mega-column` block

**Files:**
- Create: `src/blocks/mega-column/block.json`, `index.tsx`, `edit.tsx`, `render.php`, `style.scss`
- Modify: `tests/phpunit/MegaMenu/RenderTest.php`

- [ ] **Step 1: Add the failing test**

Append these methods inside the `RenderTest` class in `tests/phpunit/MegaMenu/RenderTest.php` (before the closing `}`):

```php
	public function test_mega_column_renders_heading_and_inner_links() {
		$html = do_blocks(
			'<!-- wp:starter/mega-column {"heading":"Product"} -->' .
			'<!-- wp:starter/mega-link {"label":"Pricing","url":"/pricing"} /-->' .
			'<!-- /wp:starter/mega-column -->'
		);
		$this->assertStringContainsString( 'starter-mega-column', $html );
		$this->assertStringContainsString( 'Product', $html );
		$this->assertStringContainsString( 'href="/pricing"', $html );
	}

	public function test_mega_column_omits_empty_heading() {
		$html = do_blocks(
			'<!-- wp:starter/mega-column -->' .
			'<!-- wp:starter/mega-link {"label":"Docs","url":"/docs"} /-->' .
			'<!-- /wp:starter/mega-column -->'
		);
		$this->assertStringNotContainsString( 'starter-mega-column__heading', $html );
		$this->assertStringContainsString( 'href="/docs"', $html );
	}
```

- [ ] **Step 2: Run test to verify it fails** (post-merge env, after build)

Run: `npm run build && (cd ~/Entwicklung/wp-starter-child-theme && npx wp-env run tests-wordpress --env-cwd=wp-content/themes/wp-starter-theme vendor/bin/phpunit --filter RenderTest::test_mega_column)`
Expected: FAIL — block not registered.

- [ ] **Step 3: Create `src/blocks/mega-column/block.json`**

```json
{
	"$schema": "https://schemas.wp.org/trunk/block.json",
	"apiVersion": 3,
	"name": "starter/mega-column",
	"title": "Mega Column",
	"category": "starter",
	"description": "A column of links inside a mega menu.",
	"parent": [ "starter/mega-menu" ],
	"textdomain": "starter",
	"supports": { "html": false, "inserter": false },
	"attributes": {
		"heading": { "type": "string", "default": "" }
	},
	"editorScript": "file:./index.js",
	"editorStyle": "file:./style-index.css",
	"style": "file:./style-index.css",
	"render": "file:./render.php"
}
```

- [ ] **Step 4: Create `src/blocks/mega-column/index.tsx`**

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

- [ ] **Step 5: Create `src/blocks/mega-column/edit.tsx`**

```tsx
import { __ } from '@wordpress/i18n';
import { useBlockProps, useInnerBlocksProps, RichText } from '@wordpress/block-editor';

const ALLOWED = [ 'starter/mega-link' ];
const TEMPLATE: [ string, Record< string, unknown > ][] = [
	[ 'starter/mega-link', {} ],
	[ 'starter/mega-link', {} ],
];

type Attrs = { heading: string };

export default function Edit( {
	attributes,
	setAttributes,
}: {
	attributes: Attrs;
	setAttributes: ( a: Partial< Attrs > ) => void;
} ) {
	const blockProps = useBlockProps( { className: 'starter-mega-column' } );
	const innerBlocksProps = useInnerBlocksProps(
		{ className: 'starter-mega-column__links' },
		{ allowedBlocks: ALLOWED, template: TEMPLATE, templateLock: false }
	);
	return (
		<div { ...blockProps }>
			<RichText
				tagName="div"
				className="starter-mega-column__heading"
				value={ attributes.heading }
				onChange={ ( v ) => setAttributes( { heading: v } ) }
				placeholder={ __( 'Column heading…', 'starter' ) }
			/>
			<div { ...innerBlocksProps } />
		</div>
	);
}
```

- [ ] **Step 6: Create `src/blocks/mega-column/render.php`**

```php
<?php
/**
 * Server-side render for starter/mega-column.
 *
 * @var array  $attributes
 * @var string $content
 */

$heading = isset( $attributes['heading'] ) ? trim( (string) $attributes['heading'] ) : '';
$wrapper = get_block_wrapper_attributes( array( 'class' => 'starter-mega-column' ) );
ob_start();
?>
<div <?php echo $wrapper; // phpcs:ignore WordPress.Security.EscapeOutput ?>>
	<?php if ( '' !== $heading ) : ?>
		<p class="starter-mega-column__heading"><?php echo wp_kses_post( $heading ); ?></p>
	<?php endif; ?>
	<div class="starter-mega-column__links">
		<?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput -- inner blocks pre-rendered ?>
	</div>
</div>
<?php
echo ob_get_clean();
```

- [ ] **Step 7: Create `src/blocks/mega-column/style.scss`**

```scss
.starter-mega-column {
  display: flex;
  flex-direction: column;
  gap: var(--wp--preset--spacing--10);

  &__heading {
    margin: 0 0 var(--wp--preset--spacing--10);
    font-size: var(--wp--preset--font-size--sm);
    font-weight: 700;
    color: var(--wp--preset--color--text-muted);
    text-transform: uppercase;
    letter-spacing: 0.04em;
  }

  &__links { display: flex; flex-direction: column; }
}
```

- [ ] **Step 8: Build + gates**

Run: `npm run build && npm run lint:colors && npm run lint:blocks`
Expected: success.

- [ ] **Step 9: Run test to verify it passes** (post-merge env)

Run: `(cd ~/Entwicklung/wp-starter-child-theme && npx wp-env run tests-wordpress --env-cwd=wp-content/themes/wp-starter-theme vendor/bin/phpunit --filter RenderTest::test_mega_column)`
Expected: PASS.

- [ ] **Step 10: Commit**

```bash
git add src/blocks/mega-column tests/phpunit/MegaMenu/RenderTest.php
git commit -m "feat(theme): starter/mega-column block"
```

---

### Task 4: `starter/mega-menu` block (markup, no interactivity yet)

**Files:**
- Create: `src/blocks/mega-menu/block.json`, `index.tsx`, `edit.tsx`, `render.php`, `style.scss`
- Modify: `tests/phpunit/MegaMenu/RenderTest.php`

- [ ] **Step 1: Add the failing test**

Append inside `RenderTest` (before the closing `}`):

```php
	public function test_mega_menu_renders_button_trigger_and_panel() {
		$html = do_blocks(
			'<!-- wp:starter/mega-menu {"label":"Products"} -->' .
			'<!-- wp:starter/mega-column {"heading":"Product"} -->' .
			'<!-- wp:starter/mega-link {"label":"Pricing","url":"/pricing"} /-->' .
			'<!-- /wp:starter/mega-column -->' .
			'<!-- /wp:starter/mega-menu -->'
		);
		$this->assertMatchesRegularExpression( '/<button[^>]*aria-expanded="false"/', $html );
		$this->assertMatchesRegularExpression( '/<button[^>]*aria-controls="starter-mega-/', $html );
		$this->assertStringContainsString( 'Products', $html );
		$this->assertStringContainsString( 'starter-mega-menu__panel', $html );
		$this->assertStringContainsString( 'href="/pricing"', $html );
	}

	public function test_mega_menu_without_columns_omits_panel() {
		$html = do_blocks( '<!-- wp:starter/mega-menu {"label":"Empty"} --><!-- /wp:starter/mega-menu -->' );
		$this->assertStringContainsString( 'Empty', $html );
		$this->assertStringNotContainsString( 'starter-mega-menu__panel', $html );
	}
```

- [ ] **Step 2: Run test to verify it fails** (post-merge env, after build)

Run: `npm run build && (cd ~/Entwicklung/wp-starter-child-theme && npx wp-env run tests-wordpress --env-cwd=wp-content/themes/wp-starter-theme vendor/bin/phpunit --filter RenderTest::test_mega_menu)`
Expected: FAIL — block not registered.

- [ ] **Step 3: Create `src/blocks/mega-menu/block.json`**

```json
{
	"$schema": "https://schemas.wp.org/trunk/block.json",
	"apiVersion": 3,
	"name": "starter/mega-menu",
	"title": "Mega Menu",
	"category": "starter",
	"description": "A mega-menu dropdown for the navigation: columns of icon links.",
	"parent": [ "core/navigation" ],
	"textdomain": "starter",
	"supports": { "html": false, "reusable": false },
	"attributes": {
		"label": { "type": "string", "default": "" }
	},
	"editorScript": "file:./index.js",
	"editorStyle": "file:./style-index.css",
	"style": "file:./style-index.css",
	"viewScriptModule": "file:./view.js",
	"render": "file:./render.php"
}
```

- [ ] **Step 4: Create `src/blocks/mega-menu/index.tsx`**

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

- [ ] **Step 5: Create `src/blocks/mega-menu/edit.tsx`**

```tsx
import { __ } from '@wordpress/i18n';
import { useBlockProps, useInnerBlocksProps, RichText } from '@wordpress/block-editor';

const ALLOWED = [ 'starter/mega-column' ];
const TEMPLATE: [ string, Record< string, unknown >, unknown[] ][] = [
	[ 'starter/mega-column', {}, [] ],
	[ 'starter/mega-column', {}, [] ],
	[ 'starter/mega-column', {}, [] ],
];

type Attrs = { label: string };

export default function Edit( {
	attributes,
	setAttributes,
}: {
	attributes: Attrs;
	setAttributes: ( a: Partial< Attrs > ) => void;
} ) {
	const blockProps = useBlockProps( { className: 'starter-mega-menu' } );
	const innerBlocksProps = useInnerBlocksProps(
		{ className: 'starter-mega-menu__panel' },
		{ allowedBlocks: ALLOWED, template: TEMPLATE, templateLock: false }
	);
	return (
		<div { ...blockProps }>
			<RichText
				tagName="span"
				className="starter-mega-menu__trigger"
				value={ attributes.label }
				onChange={ ( v ) => setAttributes( { label: v } ) }
				placeholder={ __( 'Menu label…', 'starter' ) }
			/>
			<div { ...innerBlocksProps } />
		</div>
	);
}
```

- [ ] **Step 6: Create `src/blocks/mega-menu/render.php`**

```php
<?php
/**
 * Server-side render for starter/mega-menu.
 *
 * @var array  $attributes
 * @var string $content
 */

$label   = isset( $attributes['label'] ) ? trim( (string) $attributes['label'] ) : '';
$has_panel = '' !== trim( (string) $content );
$panel_id  = wp_unique_id( 'starter-mega-' );

$wrapper = get_block_wrapper_attributes(
	array(
		'class'                => 'starter-mega-menu',
		'data-wp-interactive'  => 'starter/mega-menu',
		'data-wp-context'      => '{ "isOpen": false }',
		'data-wp-on--keydown'  => 'actions.onKeydown',
		'data-wp-on--focusout' => 'actions.onFocusOut',
		'data-wp-on--mouseenter' => 'actions.onPointerEnter',
		'data-wp-on--mouseleave' => 'actions.onPointerLeave',
	)
);
ob_start();
?>
<div <?php echo $wrapper; // phpcs:ignore WordPress.Security.EscapeOutput ?>>
	<button
		type="button"
		class="starter-mega-menu__trigger"
		aria-expanded="false"
		aria-controls="<?php echo esc_attr( $panel_id ); ?>"
		data-wp-bind--aria-expanded="context.isOpen"
		data-wp-on--click="actions.toggle"
	><?php echo wp_kses_post( $label ); ?></button>
	<?php if ( $has_panel ) : ?>
		<div
			id="<?php echo esc_attr( $panel_id ); ?>"
			class="starter-mega-menu__panel"
			data-wp-bind--hidden="!context.isOpen"
			data-wp-class--is-open="context.isOpen"
		>
			<?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput -- inner blocks pre-rendered ?>
		</div>
	<?php endif; ?>
</div>
<?php
echo ob_get_clean();
```

- [ ] **Step 7: Create `src/blocks/mega-menu/view.ts` (minimal stub for this task)**

```ts
import { store, getContext } from '@wordpress/interactivity';

store( 'starter/mega-menu', {
	actions: {
		toggle() {
			const ctx = getContext< { isOpen: boolean } >();
			ctx.isOpen = ! ctx.isOpen;
		},
		onKeydown() {},
		onFocusOut() {},
		onPointerEnter() {},
		onPointerLeave() {},
	},
} );
```

- [ ] **Step 8: Create `src/blocks/mega-menu/style.scss`**

```scss
.starter-mega-menu {
  position: relative;

  &__trigger {
    background: none;
    border: 0;
    padding: 0;
    font: inherit;
    color: inherit;
    cursor: pointer;
  }

  &__panel {
    position: absolute;
    left: 0;
    top: 100%;
    z-index: 60;
    min-width: min(48rem, 90vw);
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(12rem, 1fr));
    gap: var(--wp--preset--spacing--30);
    padding: var(--wp--preset--spacing--40);
    background: var(--wp--preset--color--surface);
    border: 1px solid var(--wp--preset--color--border);
    border-radius: 0.5rem;
    box-shadow: var(--wp--preset--shadow--lifted);

    &[hidden] { display: none; }
  }
}
```

- [ ] **Step 9: Build + gates**

Run: `npm run build && npm run lint:colors && npm run lint:blocks && npm run lint:js`
Expected: success (no errors).

- [ ] **Step 10: Run test to verify it passes** (post-merge env)

Run: `(cd ~/Entwicklung/wp-starter-child-theme && npx wp-env run tests-wordpress --env-cwd=wp-content/themes/wp-starter-theme vendor/bin/phpunit --filter RenderTest)`
Expected: PASS (all RenderTest methods).

- [ ] **Step 11: Commit**

```bash
git add src/blocks/mega-menu tests/phpunit/MegaMenu/RenderTest.php
git commit -m "feat(theme): starter/mega-menu block markup + trigger/panel"
```

---

### Task 5: Interactivity behavior (hover/focus/escape/outside, sibling-close)

**Files:**
- Modify: `src/blocks/mega-menu/view.ts`
- Create: `tests/e2e/mega-menu.spec.ts`
- Create: `patterns/mega-menu-header.php`

- [ ] **Step 1: Create the deterministic fixture pattern `patterns/mega-menu-header.php`**

```php
<?php
/**
 * Title: Mega Menu Demo Header
 * Slug: starter/mega-menu-header
 * Categories: starter
 * Inserter: true
 */
?>
<!-- wp:group {"tagName":"header","className":"site-header","layout":{"type":"constrained"}} -->
<header class="wp-block-group site-header">
	<!-- wp:navigation {"overlayMenu":"mobile","layout":{"type":"flex","orientation":"horizontal"}} -->
	<!-- wp:starter/mega-menu {"label":"Products"} -->
	<!-- wp:starter/mega-column {"heading":"Product"} -->
	<!-- wp:starter/mega-link {"label":"Pricing","url":"/pricing","description":"Plans","icon":"tag"} /-->
	<!-- wp:starter/mega-link {"label":"Docs","url":"/docs","description":"Guides","icon":"book"} /-->
	<!-- /wp:starter/mega-column -->
	<!-- /wp:starter/mega-menu -->
	<!-- wp:navigation-link {"label":"About","url":"/about","kind":"custom"} /-->
	<!-- /wp:navigation -->
</header>
<!-- /wp:group -->
```

- [ ] **Step 2: Write the failing e2e test**

Create `tests/e2e/mega-menu.spec.ts`:

```ts
import { test, expect } from '@playwright/test';

// Assumes a page at /mega-demo/ built from the "Mega Menu Demo Header" pattern.
test.describe( 'mega menu', () => {
	test( 'opens on hover and closes on Escape, returning focus to trigger', async ( { page } ) => {
		await page.goto( '/mega-demo/' );
		const trigger = page.getByRole( 'button', { name: 'Products' } );
		const panel = page.locator( '.starter-mega-menu__panel' ).first();
		await expect( panel ).toBeHidden();
		await trigger.hover();
		await expect( panel ).toBeVisible();
		await expect( trigger ).toHaveAttribute( 'aria-expanded', 'true' );
		await page.keyboard.press( 'Escape' );
		await expect( panel ).toBeHidden();
		await expect( trigger ).toBeFocused();
	} );

	test( 'opens on keyboard focus and closes on click-outside', async ( { page } ) => {
		await page.goto( '/mega-demo/' );
		const trigger = page.getByRole( 'button', { name: 'Products' } );
		const panel = page.locator( '.starter-mega-menu__panel' ).first();
		await trigger.focus();
		await expect( panel ).toBeVisible();
		await page.mouse.click( 5, 5 );
		await expect( panel ).toBeHidden();
	} );

	test( 'mobile overlay: trigger expands the columns inline (accordion)', async ( { page } ) => {
		await page.setViewportSize( { width: 375, height: 800 } );
		await page.goto( '/mega-demo/' );
		await page.locator( '.wp-block-navigation__responsive-container-open' ).first().click();
		const trigger = page.getByRole( 'button', { name: 'Products' } );
		const panel = page.locator( '.starter-mega-menu__panel' ).first();
		await expect( panel ).toBeHidden();
		await trigger.click();
		await expect( panel ).toBeVisible();
	} );
} );
```

- [ ] **Step 3: Run e2e to verify it fails** (post-merge, after build; create the demo page once)

One-time fixture page (the running :8890 env): create a page at slug `mega-demo` whose content is the pattern body from Step 1. Command:
`cd ~/Entwicklung/wp-starter-child-theme && npx wp-env run cli wp post create --post_type=page --post_status=publish --post_title='Mega Demo' --post_name='mega-demo' --post_content="$(php -r 'echo preg_replace("/^.*\?>\n/s","",file_get_contents("/var/www/html/wp-content/themes/wp-starter-theme/patterns/mega-menu-header.php"));')"`
Then: `npm run build && PLAYWRIGHT_BASE_URL=http://localhost:8890 npx playwright test tests/e2e/mega-menu.spec.ts`
Expected: FAIL — hover/focus/escape/outside not implemented (stub view.ts only toggles on click).

- [ ] **Step 4: Implement `src/blocks/mega-menu/view.ts`**

```ts
import { store, getContext, getElement } from '@wordpress/interactivity';

type Ctx = { isOpen: boolean };
let closeTimer: ReturnType< typeof setTimeout > | undefined;

const closeAllExcept = ( keep: Element | null ) => {
	document
		.querySelectorAll< HTMLElement >( '.starter-mega-menu' )
		.forEach( ( el ) => {
			if ( el !== keep ) {
				el.dispatchEvent( new CustomEvent( 'starter-mega-close' ) );
			}
		} );
};

const { actions } = store( 'starter/mega-menu', {
	actions: {
		open() {
			const ctx = getContext< Ctx >();
			const { ref } = getElement();
			clearTimeout( closeTimer );
			closeAllExcept( ref );
			ctx.isOpen = true;
		},
		close() {
			getContext< Ctx >().isOpen = false;
		},
		toggle() {
			const ctx = getContext< Ctx >();
			if ( ctx.isOpen ) {
				actions.close();
			} else {
				actions.open();
			}
		},
		onPointerEnter() {
			if ( window.matchMedia( '(hover: hover)' ).matches ) {
				actions.open();
			}
		},
		onPointerLeave() {
			if ( window.matchMedia( '(hover: hover)' ).matches ) {
				clearTimeout( closeTimer );
				closeTimer = setTimeout( () => actions.close(), 150 );
			}
		},
		onKeydown( event: KeyboardEvent ) {
			if ( event.key === 'Escape' ) {
				const ctx = getContext< Ctx >();
				if ( ctx.isOpen ) {
					ctx.isOpen = false;
					const { ref } = getElement();
					ref?.querySelector< HTMLButtonElement >(
						'.starter-mega-menu__trigger'
					)?.focus();
				}
			}
		},
		onFocusOut( event: FocusEvent ) {
			const { ref } = getElement();
			const next = event.relatedTarget as Node | null;
			if ( ref && ( ! next || ! ref.contains( next ) ) ) {
				actions.close();
			}
		},
	},
	callbacks: {
		init() {
			const { ref } = getElement();
			ref?.addEventListener( 'starter-mega-close', () => actions.close() );
			const onDocPointer = ( e: Event ) => {
				const ctx = getContext< Ctx >();
				if ( ctx.isOpen && ref && ! ref.contains( e.target as Node ) ) {
					actions.close();
				}
			};
			document.addEventListener( 'pointerdown', onDocPointer );
		},
	},
} );
```

Add `data-wp-watch` is not needed; wire the document-pointer + custom-close via `callbacks.init`. In `render.php` add `data-wp-init="callbacks.init"` to the wrapper attributes array (add `'data-wp-init' => 'callbacks.init',` next to the other `data-wp-*` entries) and add `data-wp-on--focus` / `data-wp-on--keydown` already present. Also add to the wrapper: `'data-wp-on--focusin' => 'actions.open',` so keyboard focus opens.

- [ ] **Step 5: Update `src/blocks/mega-menu/render.php` wrapper attributes**

Replace the `get_block_wrapper_attributes( array( … ) )` array with exactly:

```php
$wrapper = get_block_wrapper_attributes(
	array(
		'class'                  => 'starter-mega-menu',
		'data-wp-interactive'    => 'starter/mega-menu',
		'data-wp-context'        => '{ "isOpen": false }',
		'data-wp-init'           => 'callbacks.init',
		'data-wp-on--keydown'    => 'actions.onKeydown',
		'data-wp-on--focusin'    => 'actions.open',
		'data-wp-on--focusout'   => 'actions.onFocusOut',
		'data-wp-on--mouseenter' => 'actions.onPointerEnter',
		'data-wp-on--mouseleave' => 'actions.onPointerLeave',
	)
);
```

- [ ] **Step 6: Build + gates**

Run: `npm run build && npm run lint:js && npm run lint:blocks`
Expected: success.

- [ ] **Step 7: Run e2e to verify it passes** (post-merge env)

Run: `PLAYWRIGHT_BASE_URL=http://localhost:8890 npx playwright test tests/e2e/mega-menu.spec.ts`
Expected: PASS (3 tests).

- [ ] **Step 8: Commit**

```bash
git add src/blocks/mega-menu patterns/mega-menu-header.php tests/e2e/mega-menu.spec.ts
git commit -m "feat(theme): mega-menu Interactivity behavior + e2e"
```

---

### Task 6: Mobile accordion styling + reduced motion

**Files:**
- Modify: `src/blocks/mega-menu/style.scss`
- Modify: `tests/e2e/mega-menu.spec.ts`

- [ ] **Step 1: Add the failing test**

Append inside the `test.describe` in `tests/e2e/mega-menu.spec.ts`:

```ts
	test( 'desktop panel is an absolutely-positioned dropdown; mobile is inline', async ( { page } ) => {
		await page.goto( '/mega-demo/' );
		const panel = page.locator( '.starter-mega-menu__panel' ).first();
		await page.getByRole( 'button', { name: 'Products' } ).hover();
		await expect( panel ).toHaveCSS( 'position', 'absolute' );

		await page.setViewportSize( { width: 375, height: 800 } );
		await page.goto( '/mega-demo/' );
		await page.locator( '.wp-block-navigation__responsive-container-open' ).first().click();
		await page.getByRole( 'button', { name: 'Products' } ).click();
		await expect( panel ).toHaveCSS( 'position', 'static' );
	} );
```

- [ ] **Step 2: Run e2e to verify it fails** (post-merge env)

Run: `PLAYWRIGHT_BASE_URL=http://localhost:8890 npx playwright test tests/e2e/mega-menu.spec.ts -g "dropdown; mobile is inline"`
Expected: FAIL — no mobile override yet (panel stays `absolute` inside the overlay).

- [ ] **Step 3: Append mobile + reduced-motion rules to `src/blocks/mega-menu/style.scss`**

Add at the end of the file:

```scss
.starter-mega-menu__panel { transition: opacity 160ms ease; }

@media (prefers-reduced-motion: reduce) {
  .starter-mega-menu__panel { transition: none; }
}

/* Inside the core hamburger overlay: accordion, not a floating dropdown. */
.wp-block-navigation__responsive-container.is-menu-open {
  .starter-mega-menu__panel {
    position: static;
    min-width: 0;
    grid-template-columns: 1fr;
    box-shadow: none;
    border: 0;
    padding-inline: var(--wp--preset--spacing--20);
    padding-block: var(--wp--preset--spacing--20);
  }
}
```

- [ ] **Step 4: Build + gates**

Run: `npm run build && npm run lint:colors`
Expected: success; "No color literals…".

- [ ] **Step 5: Run e2e to verify it passes** (post-merge env)

Run: `PLAYWRIGHT_BASE_URL=http://localhost:8890 npx playwright test tests/e2e/mega-menu.spec.ts`
Expected: PASS (all 4 tests).

- [ ] **Step 6: Commit**

```bash
git add src/blocks/mega-menu/style.scss tests/e2e/mega-menu.spec.ts
git commit -m "feat(theme): mega-menu mobile accordion + reduced-motion"
```

---

### Task 7: Full-suite verification

**Files:** none (verification only)

- [ ] **Step 1: Build and static gates (in-worktree OK)**

Run: `npm run build && npm run lint:js && npm run lint:blocks && npm run lint:colors`
Expected: all pass.

- [ ] **Step 2: Full PHPUnit (post-merge env, regression check)**

Run: `cd ~/Entwicklung/wp-starter-child-theme && npx wp-env run tests-wordpress --env-cwd=wp-content/themes/wp-starter-theme vendor/bin/phpunit`
Expected: OK, 0 failures (existing suite + new `MegaMenu` tests).

- [ ] **Step 3: Full mega-menu e2e (post-merge env)**

Run: `PLAYWRIGHT_BASE_URL=http://localhost:8890 npx playwright test tests/e2e/mega-menu.spec.ts`
Expected: all PASS.

- [ ] **Step 4: Editor smoke (manual, post-merge)**

In the Site Editor on :8890, open a Navigation block → confirm "Mega Menu" is offered as an inserter item inside the nav, that columns/links edit inline, and the front end renders it wrapped as a nav `<li>`. (Confirms the `"parent"` insertion + `block_core_navigation_listable_blocks` wrapping from Task 1.)

- [ ] **Step 5: Commit any fixups**

If Steps 1–4 required fixes, commit them:

```bash
git add -A
git commit -m "fix(theme): mega-menu verification follow-ups"
```

---

## Self-Review

**Spec coverage:**
- Three blocks `mega-menu`/`mega-column`/`mega-link`, InnerBlocks trio → Tasks 2,3,4. ✓
- Editor insertion via `"parent":["core/navigation"]` → Task 4 block.json. ✓
- Render wrapping via `block_core_navigation_listable_blocks` → Task 1. ✓
- Trigger = `<button>` toggle-only, `aria-expanded`/`aria-controls` → Task 4. ✓
- Hover+focus open, Escape (focus restore), click-outside, sibling-close, no-hover/touch toggle → Task 5. ✓
- Mobile accordion + reduced-motion → Task 6. ✓
- Icon (Phosphor) + label + description link; safe degradation → Task 2. ✓
- Column optional heading → Task 3. ✓
- Block-scoped SCSS, token-only, no theme.json CSS → Tasks 2,3,4,6 + lint:colors. ✓
- PHPUnit (filter + render) and Playwright (interaction/a11y/responsive) → Tasks 1–6; full run Task 7. ✓
- Demo fixture pattern → Task 5. ✓
- YAGNI exclusions (no promo slot, no link-trigger, no picker UI) honored — not implemented anywhere. ✓

**Placeholder scan:** every code/markup/test step contains complete content; no TBD/TODO; commands have expected output. ✓

**Type/name consistency:** store namespace `starter/mega-menu`; context `{ isOpen }`; CSS classes `starter-mega-menu`/`__trigger`/`__panel`, `starter-mega-column`/`__heading`/`__links`, `starter-mega-link`/`__icon`/`__label`/`__desc`; actions `open/close/toggle/onPointerEnter/onPointerLeave/onKeydown/onFocusOut`, `callbacks.init` — used identically across Tasks 4–6. block.json `parent` chains: mega-menu←core/navigation, mega-column←mega-menu, mega-link←mega-column — consistent with render/test markup. ✓

## Notes / Known limitations (from spec)

- JS-dependent open (Interactivity API), like core submenu.
- Theme-registered blocks orphan content on theme switch — inherent; documented.
- `viewScriptModule` requires WP 6.5+ (target env is 6.5). ✓
