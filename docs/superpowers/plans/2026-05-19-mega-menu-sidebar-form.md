# Mega Menu Sidebar-Form Editor Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the `starter/mega-menu` InnerBlocks trio with a single self-rendered block holding a structured `columns` attribute, edited through an Inspector-sidebar form, previewed in-canvas via ServerSideRender.

**Architecture:** `starter/mega-menu` becomes a dynamic block (`save: () => null`, `render.php`) with attributes `label` (string) and `columns` (array of `{ heading, links: [{ label, url, description, icon }] }`). `edit.tsx` renders an `InspectorControls` form that mutates `columns` immutably and a `ServerSideRender` canvas preview. `render.php` loops `columns` and emits the **existing, unchanged** front-end DOM/classes/Interactivity wrapper. The `starter/mega-column` and `starter/mega-link` blocks are deleted. Our pattern and fixtures are rewritten; no legacy auto-migration.

**Tech Stack:** WordPress block API v3, `@wordpress/scripts` build (`src/blocks` → `build/blocks`), `@wordpress/block-editor`/`components`/`server-side-render`/`i18n`, PHP `render.php`, PHPUnit `WP_UnitTestCase` BlockRender tests, Playwright e2e.

**Spec:** `docs/superpowers/specs/2026-05-19-mega-menu-sidebar-form-design.md`

---

## Environment & Verification Model (read first)

- **Branch/worktree:** This is plan-driven multi-task work → execute in a short-lived worktree at `.worktrees/mega-menu-sidebar-form` off `development` (the using-git-worktrees skill handles creation). The spec's "migration" is **content/asset only** — there is **no DB schema/migration task**, so no schema-lane serialization concerns.
- **What runs in the worktree:** `npm run build`, `npm run lint:js`, `npm run lint:blocks`, `npm run lint:colors`, `php -l` syntax checks.
- **What is deferred to post-merge (Task 8):** PHPUnit and Playwright. The single test wp-env (child-theme env, `localhost:8890`) **mounts the main checkout, not the worktree** (see memory `worktree_test_verification` / `single_test_env`). So PHPUnit `MegaMenuTest` and the Playwright specs are written in their tasks but **executed only after merge to `development`**, in Task 8, against `:8890`.
- **wp-cli/e2e env pin:** `tests/e2e/utils.ts` already pins wp-cli to the child-theme env via `WP_ENV_CWD`; do not change that.
- **Build artifacts:** `build/` is gitignored. Commit source only; `npm run build` is run locally/CI. Deleting a block means also `rm -rf build/blocks/<name>` so the stale dynamic block does not stay registered locally.

---

## File Structure

| File | Responsibility | Action |
|------|----------------|--------|
| `src/blocks/mega-menu/block.json` | Block metadata; `label`+`columns` attributes | Modify |
| `src/blocks/mega-menu/index.tsx` | Register block: `edit` + `save: () => null` | Modify |
| `src/blocks/mega-menu/edit.tsx` | Inspector form + ServerSideRender preview | Rewrite |
| `src/blocks/mega-menu/render.php` | Loop `columns` → existing front-end DOM | Rewrite |
| `src/blocks/mega-menu/editor.scss` | Editor-only CSS hover-reveal (display-only) | Rewrite |
| `src/blocks/mega-menu/style.scss` | Front-end styles | **Unchanged** |
| `src/blocks/mega-menu/view.ts` | Front-end Interactivity | **Unchanged** |
| `src/blocks/mega-column/` | (removed) | Delete dir + `build/blocks/mega-column` |
| `src/blocks/mega-link/` | (removed) | Delete dir + `build/blocks/mega-link` |
| `inc/mega-menu.php` | listable-blocks filter | **Unchanged** (already only `starter/mega-menu`) |
| `patterns/mega-menu-header.php` | Demo pattern | Rewrite to single block |
| `tests/phpunit/BlockRender/MegaMenuTest.php` | render.php coverage | Create |
| `tests/e2e/mega-menu-editor.spec.ts` | Editor form e2e | Rewrite |
| `tests/e2e/mega-menu.spec.ts` | Front-end e2e | **Unchanged** (regression guard) |

---

## Task 1: Convert the block to a single self-rendered block

**Files:**
- Modify: `src/blocks/mega-menu/block.json`
- Modify: `src/blocks/mega-menu/index.tsx`

- [ ] **Step 1: Replace the attributes in `block.json`**

Replace the `"attributes"` object (currently `{ "label": { "type": "string", "default": "" } }`) so the full file reads:

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
		"label": { "type": "string", "default": "" },
		"columns": { "type": "array", "default": [] }
	},
	"editorScript": "file:./index.js",
	"editorStyle": "file:./index.css",
	"style": "file:./style-index.css",
	"viewScriptModule": "file:./view.js",
	"render": "file:./render.php"
}
```

- [ ] **Step 2: Rewrite `index.tsx` to drop InnerBlocks and save null**

Full new contents (keep both style imports so `style-index.css` and `index.css` keep emitting exactly as before):

```tsx
import { registerBlockType } from '@wordpress/blocks';
import metadata from './block.json';
import Edit from './edit';
import './style.scss';
import './editor.scss';

registerBlockType( metadata.name, { edit: Edit, save: () => null } );
```

- [ ] **Step 3: Build (verifies block.json + index.tsx compile; edit.tsx is rewritten in Task 3 — temporarily it still imports the old InnerBlocks edit, so do NOT build yet if edit.tsx is unchanged).**

Skip building here; Task 3 rewrites `edit.tsx` and Task 1+3 build together. Proceed to Task 2 (PHP, independent of JS build).

- [ ] **Step 4: Commit**

```bash
git add src/blocks/mega-menu/block.json src/blocks/mega-menu/index.tsx
git commit -m "refactor(mega-menu): single block shell — columns attr, save null"
```

---

## Task 2: Rewrite `render.php` (TDD via PHPUnit)

**Files:**
- Create: `tests/phpunit/BlockRender/MegaMenuTest.php`
- Rewrite: `src/blocks/mega-menu/render.php`

PHPUnit runs against `:8890` post-merge (Task 8). Write the test now; it is the executable spec for `render.php`.

- [ ] **Step 1: Write the failing test**

Create `tests/phpunit/BlockRender/MegaMenuTest.php`:

```php
<?php

class MegaMenuTest extends WP_UnitTestCase {
	private function render( string $attrs ): string {
		return do_blocks( '<!-- wp:starter/mega-menu ' . $attrs . ' /-->' );
	}

	public function test_no_panel_when_no_columns() {
		$html = $this->render( '{"label":"Products","columns":[]}' );
		$this->assertStringContainsString( 'starter-mega-menu__trigger', $html );
		$this->assertStringNotContainsString( 'starter-mega-menu__panel', $html );
	}

	public function test_renders_columns_and_links() {
		$attrs = '{"label":"Products","columns":[{"heading":"Product","links":[{"label":"Pricing","url":"/pricing","description":"Plans","icon":"tag"},{"label":"Docs","url":"/docs","description":"","icon":""}]}]}';
		$html  = $this->render( $attrs );
		$this->assertStringContainsString( 'starter-mega-menu__panel', $html );
		$this->assertStringContainsString( '<p class="starter-mega-column__heading">Product</p>', $html );
		$this->assertSame( 2, substr_count( $html, 'class="starter-mega-link"' ) );
		$this->assertStringContainsString( 'href="/pricing"', $html );
		$this->assertStringContainsString( '<span class="starter-mega-link__desc">Plans</span>', $html );
	}

	public function test_link_with_icon_emits_icon_span() {
		$attrs = '{"label":"X","columns":[{"heading":"","links":[{"label":"Pricing","url":"/pricing","description":"","icon":"tag"}]}]}';
		$html  = $this->render( $attrs );
		$this->assertStringContainsString( 'starter-mega-link__icon', $html );
	}

	public function test_skips_links_without_label_or_url_and_empty_columns() {
		$attrs = '{"label":"X","columns":[{"heading":"Empty","links":[{"label":"","url":"","description":"","icon":""}]},{"heading":"Real","links":[{"label":"Docs","url":"/docs","description":"","icon":""}]}]}';
		$html  = $this->render( $attrs );
		$this->assertStringNotContainsString( 'Empty', $html );
		$this->assertStringContainsString( 'Real', $html );
		$this->assertSame( 1, substr_count( $html, 'class="starter-mega-link"' ) );
	}

	public function test_trigger_aria_label_when_label_empty() {
		$html = $this->render( '{"label":"","columns":[]}' );
		$this->assertStringContainsString( 'aria-label="Menu"', $html );
	}
}
```

- [ ] **Step 2: Note expected failure**

Until `render.php` loops `columns`, `test_renders_columns_and_links` / `test_skips_*` fail (old render emits nothing from the attribute). This is verified in Task 8 Step 2. Proceed.

- [ ] **Step 3: Rewrite `render.php`**

Full new contents:

```php
<?php
/**
 * Server-side render for starter/mega-menu.
 *
 * @var array $attributes
 */

$label   = isset( $attributes['label'] ) ? trim( (string) $attributes['label'] ) : '';
$columns = isset( $attributes['columns'] ) && is_array( $attributes['columns'] )
	? $attributes['columns']
	: array();

// Panel renders only if at least one link has a label or url.
$has_panel = false;
foreach ( $columns as $col ) {
	$links = isset( $col['links'] ) && is_array( $col['links'] ) ? $col['links'] : array();
	foreach ( $links as $lnk ) {
		$l = isset( $lnk['label'] ) ? trim( (string) $lnk['label'] ) : '';
		$u = isset( $lnk['url'] ) ? trim( (string) $lnk['url'] ) : '';
		if ( '' !== $l || '' !== $u ) {
			$has_panel = true;
			break 2;
		}
	}
}

$panel_id = wp_unique_id( 'starter-mega-' );

$wrapper = get_block_wrapper_attributes(
	array(
		'class'                  => 'starter-mega-menu',
		'data-wp-interactive'    => 'starter/mega-menu',
		'data-wp-context'        => '{ "isOpen": false }',
		'data-wp-init'           => 'callbacks.init',
		'data-wp-on--focusout'   => 'actions.onFocusOut',
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
		<?php if ( $has_panel ) : ?>aria-controls="<?php echo esc_attr( $panel_id ); ?>"<?php endif; ?>
		<?php if ( '' === $label ) : ?>aria-label="<?php echo esc_attr__( 'Menu', 'starter' ); ?>"<?php endif; ?>
		data-wp-bind--aria-expanded="context.isOpen"
		data-wp-on--focus="actions.onTriggerFocus"
		data-wp-on--click="actions.toggle"
	><?php echo wp_kses_post( $label ); ?></button>
	<?php if ( $has_panel ) : ?>
		<div
			id="<?php echo esc_attr( $panel_id ); ?>"
			class="starter-mega-menu__panel"
			hidden
			data-wp-bind--hidden="!context.isOpen"
			data-wp-class--is-open="context.isOpen"
		>
			<?php
			foreach ( $columns as $col ) :
				$heading = isset( $col['heading'] ) ? trim( (string) $col['heading'] ) : '';
				$links   = isset( $col['links'] ) && is_array( $col['links'] ) ? $col['links'] : array();

				$renderable = array();
				foreach ( $links as $lnk ) {
					$l = isset( $lnk['label'] ) ? trim( (string) $lnk['label'] ) : '';
					$u = isset( $lnk['url'] ) ? trim( (string) $lnk['url'] ) : '';
					if ( '' !== $l || '' !== $u ) {
						$renderable[] = $lnk;
					}
				}
				if ( empty( $renderable ) ) {
					continue;
				}
				?>
				<div class="starter-mega-column">
					<?php if ( '' !== $heading ) : ?>
						<p class="starter-mega-column__heading"><?php echo wp_kses_post( $heading ); ?></p>
					<?php endif; ?>
					<div class="starter-mega-column__links">
						<?php
						foreach ( $renderable as $lnk ) :
							$l_label = isset( $lnk['label'] ) ? trim( (string) $lnk['label'] ) : '';
							$l_url   = isset( $lnk['url'] ) ? trim( (string) $lnk['url'] ) : '';
							$l_desc  = isset( $lnk['description'] ) ? trim( (string) $lnk['description'] ) : '';
							$l_icon  = isset( $lnk['icon'] ) ? trim( (string) $lnk['icon'] ) : '';
							?>
							<a class="starter-mega-link" href="<?php echo esc_url( $l_url ); ?>">
								<?php
								if ( '' !== $l_icon && function_exists( 'starter_icon' ) ) {
									echo starter_icon( $l_icon, 'starter-mega-link__icon' ); // phpcs:ignore WordPress.Security.EscapeOutput -- theme-controlled sprite SVG
								}
								?>
								<span class="starter-mega-link__label"><?php echo wp_kses_post( $l_label ); ?></span>
								<?php if ( '' !== $l_desc ) : ?>
									<span class="starter-mega-link__desc"><?php echo wp_kses_post( $l_desc ); ?></span>
								<?php endif; ?>
							</a>
						<?php endforeach; ?>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
</div>
<?php
echo ob_get_clean();
```

- [ ] **Step 4: Syntax check**

Run: `php -l src/blocks/mega-menu/render.php`
Expected: `No syntax errors detected`

- [ ] **Step 5: Commit**

```bash
git add tests/phpunit/BlockRender/MegaMenuTest.php src/blocks/mega-menu/render.php
git commit -m "feat(mega-menu): render.php loops columns attribute; add MegaMenuTest"
```

---

## Task 3: Inspector-sidebar form + ServerSideRender canvas (`edit.tsx`)

**Files:**
- Rewrite: `src/blocks/mega-menu/edit.tsx`

- [ ] **Step 1: Rewrite `edit.tsx`**

Full new contents:

```tsx
import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, TextControl, Button } from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';

type Link = { label: string; url: string; description: string; icon: string };
type Column = { heading: string; links: Link[] };
type Attrs = { label: string; columns: Column[] };

const emptyLink = (): Link => ( {
	label: '',
	url: '',
	description: '',
	icon: '',
} );
const emptyColumn = (): Column => ( { heading: '', links: [ emptyLink() ] } );

function move< T >( arr: T[], from: number, to: number ): T[] {
	if ( to < 0 || to >= arr.length ) {
		return arr;
	}
	const copy = arr.slice();
	const [ item ] = copy.splice( from, 1 );
	copy.splice( to, 0, item );
	return copy;
}

export default function Edit( {
	attributes,
	setAttributes,
}: {
	attributes: Attrs;
	setAttributes: ( a: Partial< Attrs > ) => void;
} ) {
	const blockProps = useBlockProps( { className: 'starter-mega-menu' } );
	const columns = attributes.columns ?? [];
	const commit = ( next: Column[] ) => setAttributes( { columns: next } );
	const updateColumn = ( ci: number, patch: Partial< Column > ) =>
		commit(
			columns.map( ( c, i ) => ( i === ci ? { ...c, ...patch } : c ) )
		);
	const updateLink = ( ci: number, li: number, patch: Partial< Link > ) =>
		updateColumn( ci, {
			links: columns[ ci ].links.map( ( l, i ) =>
				i === li ? { ...l, ...patch } : l
			),
		} );

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Mega Menu', 'starter' ) }>
					<TextControl
						label={ __( 'Menu label', 'starter' ) }
						value={ attributes.label }
						onChange={ ( label ) => setAttributes( { label } ) }
					/>
				</PanelBody>
				{ columns.map( ( column, ci ) => (
					<PanelBody
						key={ ci }
						title={ `${ __( 'Column', 'starter' ) } ${ ci + 1 }` }
						initialOpen={ false }
					>
						<TextControl
							label={ __( 'Heading', 'starter' ) }
							value={ column.heading }
							onChange={ ( heading ) =>
								updateColumn( ci, { heading } )
							}
						/>
						{ column.links.map( ( link, li ) => (
							<div
								key={ li }
								className="starter-mega-form__link"
							>
								<TextControl
									label={ __( 'Label', 'starter' ) }
									value={ link.label }
									onChange={ ( v ) =>
										updateLink( ci, li, { label: v } )
									}
								/>
								<TextControl
									label={ __( 'URL', 'starter' ) }
									type="url"
									value={ link.url }
									onChange={ ( v ) =>
										updateLink( ci, li, { url: v } )
									}
								/>
								<TextControl
									label={ __(
										'Description',
										'starter'
									) }
									value={ link.description }
									onChange={ ( v ) =>
										updateLink( ci, li, {
											description: v,
										} )
									}
								/>
								<TextControl
									label={ __(
										'Icon (Phosphor name)',
										'starter'
									) }
									help={ __(
										'e.g. gear, bank, article',
										'starter'
									) }
									value={ link.icon }
									onChange={ ( v ) =>
										updateLink( ci, li, { icon: v } )
									}
								/>
								<div className="starter-mega-form__row">
									<Button
										variant="tertiary"
										onClick={ () =>
											updateColumn( ci, {
												links: move(
													column.links,
													li,
													li - 1
												),
											} )
										}
										disabled={ li === 0 }
									>
										{ __( 'Up', 'starter' ) }
									</Button>
									<Button
										variant="tertiary"
										onClick={ () =>
											updateColumn( ci, {
												links: move(
													column.links,
													li,
													li + 1
												),
											} )
										}
										disabled={
											li ===
											column.links.length - 1
										}
									>
										{ __( 'Down', 'starter' ) }
									</Button>
									<Button
										isDestructive
										variant="tertiary"
										onClick={ () =>
											updateColumn( ci, {
												links: column.links.filter(
													( _, i ) => i !== li
												),
											} )
										}
									>
										{ __(
											'Remove link',
											'starter'
										) }
									</Button>
								</div>
							</div>
						) ) }
						<Button
							variant="secondary"
							onClick={ () =>
								updateColumn( ci, {
									links: [
										...column.links,
										emptyLink(),
									],
								} )
							}
						>
							{ __( 'Add link', 'starter' ) }
						</Button>
						<div className="starter-mega-form__row">
							<Button
								variant="tertiary"
								onClick={ () =>
									commit( move( columns, ci, ci - 1 ) )
								}
								disabled={ ci === 0 }
							>
								{ __( 'Move column up', 'starter' ) }
							</Button>
							<Button
								variant="tertiary"
								onClick={ () =>
									commit( move( columns, ci, ci + 1 ) )
								}
								disabled={ ci === columns.length - 1 }
							>
								{ __(
									'Move column down',
									'starter'
								) }
							</Button>
							<Button
								isDestructive
								variant="tertiary"
								onClick={ () =>
									commit(
										columns.filter(
											( _, i ) => i !== ci
										)
									)
								}
							>
								{ __( 'Remove column', 'starter' ) }
							</Button>
						</div>
					</PanelBody>
				) ) }
				<PanelBody>
					<Button
						variant="primary"
						onClick={ () =>
							commit( [ ...columns, emptyColumn() ] )
						}
					>
						{ __( 'Add column', 'starter' ) }
					</Button>
				</PanelBody>
			</InspectorControls>
			<div { ...blockProps }>
				<ServerSideRender
					block="starter/mega-menu"
					attributes={ attributes }
				/>
			</div>
		</>
	);
}
```

- [ ] **Step 2: Build**

Run: `npm run build`
Expected: `webpack ... compiled successfully`; `build/blocks/mega-menu/index.js` regenerated; no TypeScript errors.

- [ ] **Step 3: Lint JS**

Run: `npm run lint:js`
Expected: passes for `src/blocks/mega-menu/edit.tsx` (no errors).

- [ ] **Step 4: Commit**

```bash
git add src/blocks/mega-menu/edit.tsx
git commit -m "feat(mega-menu): Inspector-sidebar form + ServerSideRender preview"
```

---

## Task 4: Editor-only hover-reveal CSS (`editor.scss`)

**Files:**
- Rewrite: `src/blocks/mega-menu/editor.scss`

- [ ] **Step 1: Rewrite `editor.scss`**

Full new contents (replaces the A1 expanded-static rules; removes the now-irrelevant `.block-list-appender` rule):

```scss
/* Editor-only. The block is display-only in the canvas (ServerSideRender of
   render.php); all editing happens in the Inspector sidebar form. The
   front-end panel is position:absolute + [hidden] and is revealed by the
   Interactivity runtime, which does NOT execute in the editor — so reveal it
   with CSS :hover/:focus for a look-only preview. Accepts the documented
   absolute-overlay clipping / hover-flicker caveat. */
.starter-mega-menu__panel,
.starter-mega-menu__panel[hidden] {
  display: none;
}

.starter-mega-menu:hover .starter-mega-menu__panel,
.starter-mega-menu__trigger:hover + .starter-mega-menu__panel,
.starter-mega-menu__trigger:focus + .starter-mega-menu__panel {
  display: grid;
}

.starter-mega-menu__trigger {
  font-weight: 600;
  cursor: default;
}
```

- [ ] **Step 2: Build**

Run: `npm run build`
Expected: compiles; `build/blocks/mega-menu/index.css` regenerated.

- [ ] **Step 3: Lint colors (palette-only policy)**

Run: `npm run lint:colors`
Expected: passes (no raw colors introduced in `src/blocks/`).

- [ ] **Step 4: Commit**

```bash
git add src/blocks/mega-menu/editor.scss
git commit -m "feat(mega-menu): editor-only CSS hover-reveal preview"
```

---

## Task 5: Delete the `mega-column` and `mega-link` blocks

**Files:**
- Delete: `src/blocks/mega-column/` (dir), `src/blocks/mega-link/` (dir)
- Delete (local, gitignored): `build/blocks/mega-column/`, `build/blocks/mega-link/`

- [ ] **Step 1: Confirm no remaining code references (docs/specs history is fine)**

Run:
```bash
grep -rn "mega-column\|mega-link\|starter/mega-column\|starter/mega-link" src inc tests/e2e tests/phpunit patterns --include='*.ts' --include='*.tsx' --include='*.php' --include='*.json' | grep -v 'tests/e2e/mega-menu-editor.spec.ts'
```
Expected: no output. (The only expected match, `tests/e2e/mega-menu-editor.spec.ts`, is rewritten in Task 7 and excluded here. `inc/mega-menu.php` references only `starter/mega-menu` and must NOT match.)

If any other file matches, stop and resolve it before deleting (it indicates an un-migrated dependency).

- [ ] **Step 2: Delete source dirs**

```bash
git rm -r src/blocks/mega-column src/blocks/mega-link
```

- [ ] **Step 3: Delete stale build output (gitignored — prevents the deleted dynamic blocks staying registered locally)**

```bash
rm -rf build/blocks/mega-column build/blocks/mega-link
```

- [ ] **Step 4: Rebuild and confirm they are gone**

Run: `npm run build && ls build/blocks | grep -E 'mega-(column|link)' || echo "removed"`
Expected: prints `removed` (no `mega-column`/`mega-link` build dirs).

- [ ] **Step 5: Commit**

```bash
git add -A src/blocks
git commit -m "refactor(mega-menu): remove mega-column and mega-link blocks"
```

---

## Task 6: Rewrite the demo pattern

**Files:**
- Rewrite: `patterns/mega-menu-header.php`

- [ ] **Step 1: Rewrite `patterns/mega-menu-header.php`**

Full new contents (single self-closing block reproducing the original demo: "Products" → "Product" column with Pricing/Docs):

```php
<?php
/**
 * Title: Mega Menu Demo Header
 * Slug: starter/mega-menu-header
 * Categories: starter
 * Inserter: true
 */
?>
<!-- wp:group {"className":"mega-demo","layout":{"type":"constrained"}} -->
<div class="wp-block-group mega-demo">
	<!-- wp:navigation {"overlayMenu":"mobile","layout":{"type":"flex","orientation":"horizontal"}} -->
	<!-- wp:starter/mega-menu {"label":"Products","columns":[{"heading":"Product","links":[{"label":"Pricing","url":"/pricing","description":"Plans","icon":"tag"},{"label":"Docs","url":"/docs","description":"Guides","icon":"book"}]}]} /-->
	<!-- wp:navigation-link {"label":"About","url":"/about","kind":"custom"} /-->
	<!-- /wp:navigation -->
</div>
<!-- /wp:group -->
```

- [ ] **Step 2: Syntax check**

Run: `php -l patterns/mega-menu-header.php`
Expected: `No syntax errors detected`

- [ ] **Step 3: Commit**

```bash
git add patterns/mega-menu-header.php
git commit -m "refactor(mega-menu): demo pattern uses single mega-menu block"
```

---

## Task 7: Rework the editor e2e spec

**Files:**
- Rewrite: `tests/e2e/mega-menu-editor.spec.ts`

These run post-merge on `:8890` (Task 8). `createPageWithContent`/`deletePageBySlug`/`login` from `./utils` are already env-pinned — do not modify utils.

- [ ] **Step 1: Rewrite `tests/e2e/mega-menu-editor.spec.ts`**

Full new contents:

```ts
import { test, expect } from '@playwright/test';
import { login, createPageWithContent, deletePageBySlug } from './utils';

const SEED =
	'<!-- wp:navigation {"overlayMenu":"never"} -->' +
	'<!-- wp:starter/mega-menu {"label":"Products","columns":[{"heading":"Product","links":[{"label":"Pricing","url":"/pricing","description":"Plans","icon":"tag"}]}]} /-->' +
	'<!-- /wp:navigation -->';

test.describe( 'mega menu editor (sidebar form)', () => {
	test( 'sidebar form exposes label/columns/links and preview reflects data', async ( {
		page,
	} ) => {
		const slug = 'mega-form-fixture';
		deletePageBySlug( slug );
		const url = createPageWithContent(
			slug,
			'Mega Form Fixture',
			SEED
		);
		const id = url.replace( /[^0-9]/g, '' );
		await login( page );
		await page.goto(
			`/wp-admin/post.php?post=${ id }&action=edit`
		);
		const canvas = page.frameLocator(
			'iframe[name="editor-canvas"]'
		);

		// Select the block (its ServerSideRender preview) to load the sidebar.
		await canvas.locator( '.starter-mega-menu' ).first().click();

		// Sidebar form reflects seeded attributes.
		await expect( page.getByLabel( 'Menu label' ) ).toHaveValue(
			'Products'
		);

		// Preview renders the seeded link via render.php.
		await expect(
			canvas
				.locator( '.starter-mega-link__label', {
					hasText: 'Pricing',
				} )
				.first()
		).toBeVisible();

		// Adding a column via the form is possible.
		await page
			.getByRole( 'button', { name: 'Add column' } )
			.click();
		await expect(
			page.getByRole( 'button', { name: /^Column 2/ } )
		).toBeVisible();

		deletePageBySlug( slug );
	} );

	test( 'editor CSS reveals the panel on trigger hover', async ( {
		page,
	} ) => {
		const slug = 'mega-hover-fixture';
		deletePageBySlug( slug );
		const url = createPageWithContent(
			slug,
			'Mega Hover Fixture',
			SEED
		);
		const id = url.replace( /[^0-9]/g, '' );
		await login( page );
		await page.goto(
			`/wp-admin/post.php?post=${ id }&action=edit`
		);
		const canvas = page.frameLocator(
			'iframe[name="editor-canvas"]'
		);
		const panel = canvas
			.locator( '.starter-mega-menu__panel' )
			.first();
		await expect( panel ).toBeHidden();
		await canvas
			.locator( '.starter-mega-menu__trigger' )
			.first()
			.hover();
		await expect( panel ).toBeVisible();
		deletePageBySlug( slug );
	} );
} );
```

- [ ] **Step 2: Commit**

```bash
git add tests/e2e/mega-menu-editor.spec.ts
git commit -m "test(mega-menu): rework editor e2e for the sidebar-form model"
```

---

## Task 8: Post-merge verification on `:8890`

This task runs **after the worktree is merged back to `development`** (the single test wp-env mounts the main checkout, not the worktree — see memory `worktree_test_verification`). Do not run these in the worktree.

- [ ] **Step 1: Merge worktree → `development` and rebuild on the main checkout**

Handled by `superpowers:finishing-a-development-branch` (Option 1, merge locally). After merge, in the main checkout:

Run: `npm run build`
Expected: compiles; `build/blocks/mega-menu` present; no `build/blocks/mega-column|mega-link`.

- [ ] **Step 2: Run the PHPUnit block test on the child-theme env**

Run:
```bash
cd ~/Entwicklung/wp-starter-child-theme && npx wp-env run tests-wordpress --env-cwd=wp-content/themes/wp-starter-theme vendor/bin/phpunit --filter MegaMenuTest
```
Expected: `OK` — all 5 `MegaMenuTest` tests pass.

- [ ] **Step 3: Run full PHPUnit (regression — render/markup behaviourally unchanged)**

Run:
```bash
cd ~/Entwicklung/wp-starter-child-theme && npx wp-env run tests-wordpress --env-cwd=wp-content/themes/wp-starter-theme vendor/bin/phpunit
```
Expected: full suite green (no regressions; `MegaMenuTest` added).

- [ ] **Step 4: Recreate the `/mega-demo/` fixture from the new pattern**

The fixture page must carry the new single-block body. From the theme checkout:
```bash
cd ~/Entwicklung/wp-starter-child-theme
BODY='<!-- wp:group {"className":"mega-demo","layout":{"type":"constrained"}} --><div class="wp-block-group mega-demo"><!-- wp:navigation {"overlayMenu":"mobile","layout":{"type":"flex","orientation":"horizontal"}} --><!-- wp:starter/mega-menu {"label":"Products","columns":[{"heading":"Product","links":[{"label":"Pricing","url":"/pricing","description":"Plans","icon":"tag"},{"label":"Docs","url":"/docs","description":"Guides","icon":"book"}]}]} /--><!-- wp:navigation-link {"label":"About","url":"/about","kind":"custom"} /--><!-- /wp:navigation --></div><!-- /wp:group -->'
ID=$(npx wp-env run cli wp post list --post_type=page --name=mega-demo --field=ID --format=ids 2>/dev/null | grep -E '^[0-9]+$' | head -n1)
if [ -n "$ID" ]; then npx wp-env run cli wp post update "$ID" --post_content="$BODY"; else npx wp-env run cli wp post create --post_type=page --post_status=publish --post_title='Mega Demo' --post_name=mega-demo --post_content="$BODY"; fi
```
Expected: page updated/created; `curl -s http://localhost:8890/mega-demo/ | grep -c starter-mega-menu__trigger` returns `1`.

- [ ] **Step 5: Run the front-end regression e2e (must stay green)**

Run: `npx playwright test tests/e2e/mega-menu.spec.ts`
Expected: 4/4 pass (hover/Escape/focus/mobile-accordion — front-end behaviour unchanged). A first cold-start timeout may be re-run once (see memory `single_test_env`); a real break fails the same test twice.

- [ ] **Step 6: Run the reworked editor e2e**

Run: `npx playwright test tests/e2e/mega-menu-editor.spec.ts`
Expected: 2/2 pass (sidebar form + hover-reveal).

- [ ] **Step 7: Front-end smoke (env-independent)**

Run: `curl -s http://localhost:8890/mega-demo/ | grep -o 'starter-mega-link__label">Pricing\|starter-mega-link__label">Docs' | sort -u`
Expected: both `Pricing` and `Docs` label spans present — confirms attribute-driven render on the real front-end.

---

## Self-Review

**Spec coverage:**
- Single block + `columns` attribute → Task 1. ✓
- Delete mega-column/mega-link → Task 5. ✓
- Sidebar form (`InspectorControls`) → Task 3. ✓
- ServerSideRender canvas + editor CSS hover-reveal → Task 3 + Task 4. ✓
- render.php loops attribute, front-end DOM/Interactivity unchanged → Task 2. ✓
- Icon as Phosphor-name text field → Task 3 (`TextControl` "Icon (Phosphor name)"). ✓
- No hard column/link limits → no limit code anywhere. ✓
- Migration: rewrite pattern + fixture + e2e, no deprecation/shim → Task 6 + Task 8 Step 4 + Task 7. ✓
- `inc/mega-menu.php` unchanged → File Structure + Task 5 Step 1 guard. ✓
- Testing: MegaMenuTest + reworked editor spec + front-end regression → Task 2 + Task 7 + Task 8. ✓
- Build/lint gates → Task 3 Step 2-3, Task 4 Step 2-3. ✓

**Placeholder scan:** No TBD/TODO/"handle edge cases"/"similar to Task N". Every code step contains complete file contents.

**Type consistency:** `Link`/`Column`/`Attrs` shapes in `edit.tsx` (Task 3) match the JSON attribute names in `block.json` (Task 1: `label`, `columns`) and the keys read by `render.php` (Task 2: `heading`, `links`, `label`, `url`, `description`, `icon`). `move<T>` defined once and reused. Class names (`.starter-mega-menu__panel`, `.starter-mega-link`, `.starter-mega-column__heading`) are identical across `render.php`, `editor.scss`, the unchanged `style.scss`, and the e2e selectors. Consistent.
