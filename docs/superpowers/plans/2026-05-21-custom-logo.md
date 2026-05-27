# Custom Logo Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the `starter/brand-mark + core/site-title` pair in the header and footer with the WordPress-native `core/site-logo` block, backed by `add_theme_support('custom-logo')` with `flex-width`. Seed a wide demo SVG so dev installs show a logo immediately. Retire the now-unused `starter/brand-mark` block.

**Architecture:** Theme support + template-part edits + a single new seed function (mirroring the existing `starter_seed_demo_image()` pattern) + asset deletion. No new infrastructure; uses core WP primitives end-to-end. Tests live alongside existing PHPUnit suites in `tests/phpunit/`.

**Tech Stack:** WordPress block themes, `core/site-logo` block, `add_theme_support('custom-logo')`, `set_theme_mod`, PHPUnit (via wp-env tests container), wp-scripts (webpack) for block builds.

**Worktree policy:** This plan has multiple tasks, so per the user's git policy execute it in a short-lived worktree off `development`. There are no schema/migration tasks, so the worktree is safe for the whole plan. The final manual smoke verification (Task 7) happens on the working branch **after merge**, not inside the worktree.

---

## File Structure

**Modify:**
- `functions.php` — add `add_theme_support('custom-logo', …)` inside the existing `after_setup_theme` callback.
- `parts/header.html` — swap brand markup.
- `parts/footer.html` — swap brand markup.
- `inc/seed.php` — add `starter_seed_demo_logo()` and call it from `starter_seed_run()`.

**Create:**
- `docs/images/logo-demo.svg` — wide SVG used by the seed.
- `tests/phpunit/Seed/SeedDemoLogoTest.php` — PHPUnit coverage for the seed function.
- `tests/phpunit/ThemeSupportTest.php` — PHPUnit coverage for the `custom-logo` theme support.

**Delete:**
- `src/blocks/brand-mark/` (block.json, edit.tsx, index.tsx, render.php).
- Locally only (gitignored, per-developer cleanup step): `build/blocks/brand-mark/`.

---

## Task 1: Register `custom-logo` theme support

**Files:**
- Create: `tests/phpunit/ThemeSupportTest.php`
- Modify: `functions.php:82-87` (the existing `after_setup_theme` callback that registers `add_editor_style`)

- [ ] **Step 1: Write the failing test**

Create `tests/phpunit/ThemeSupportTest.php`:

```php
<?php

class ThemeSupportTest extends WP_UnitTestCase {
	public function test_custom_logo_is_registered_with_flex_dimensions() {
		$this->assertTrue(
			current_theme_supports( 'custom-logo' ),
			'Theme must declare support for custom-logo.'
		);

		$args = get_theme_support( 'custom-logo' );
		$this->assertIsArray( $args );
		$this->assertIsArray( $args[0] );
		$this->assertTrue( $args[0]['flex-width'] ?? false, 'custom-logo must allow flex width.' );
		$this->assertTrue( $args[0]['flex-height'] ?? false, 'custom-logo must allow flex height.' );
	}
}
```

- [ ] **Step 2: Run test to verify it fails**

Run (inside the wp-env tests container — see existing `composer test` config):

```
composer test -- --filter ThemeSupportTest
```

Expected: FAIL — `current_theme_supports('custom-logo')` returns false.

- [ ] **Step 3: Add the theme support**

Edit `functions.php`. Replace:

```php
add_action(
	'after_setup_theme',
	function () {
		add_editor_style( 'assets/css/theme.css' );
	}
);
```

with:

```php
add_action(
	'after_setup_theme',
	function () {
		add_editor_style( 'assets/css/theme.css' );
		add_theme_support(
			'custom-logo',
			array(
				'flex-width'  => true,
				'flex-height' => true,
				'header-text' => array( 'site-title', 'site-description' ),
			)
		);
	}
);
```

- [ ] **Step 4: Run test to verify it passes**

```
composer test -- --filter ThemeSupportTest
```

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add functions.php tests/phpunit/ThemeSupportTest.php
git commit -m "feat(theme): register custom-logo theme support with flex dimensions"
```

---

## Task 2: Header — replace `brand-mark + site-title` with `core/site-logo`

**Files:**
- Modify: `parts/header.html:5-10`

There is no meaningful unit test for static template-part markup. Verification is a string-presence check on the file plus the manual smoke in Task 7.

- [ ] **Step 1: Replace the `.brand` group**

Edit `parts/header.html`. Replace:

```html
    <!-- wp:group {"className":"brand","layout":{"type":"flex","flexWrap":"nowrap"}} -->
    <div class="wp-block-group brand">
      <!-- wp:starter/brand-mark /-->
      <!-- wp:site-title {"level":0,"style":{"typography":{"fontWeight":"800","textDecoration":"none","fontSize":"1.2rem","letterSpacing":"-0.02em"}}} /-->
    </div>
    <!-- /wp:group -->
```

with:

```html
    <!-- wp:group {"className":"brand","layout":{"type":"flex","flexWrap":"nowrap"}} -->
    <div class="wp-block-group brand">
      <!-- wp:site-logo {"width":200} /-->
    </div>
    <!-- /wp:group -->
```

- [ ] **Step 2: Verify the file no longer references brand-mark**

```
grep -n "brand-mark\|wp:site-title" parts/header.html
```

Expected: no matches.

- [ ] **Step 3: Verify the site-logo block is present**

```
grep -n "wp:site-logo" parts/header.html
```

Expected: one match on the `.brand` group.

- [ ] **Step 4: Commit**

```bash
git add parts/header.html
git commit -m "refactor(header): use core/site-logo in place of brand-mark + site-title"
```

---

## Task 3: Footer — replace `brand-mark + site-title` with `core/site-logo`

**Files:**
- Modify: `parts/footer.html:7-12`

- [ ] **Step 1: Replace the `.brand` group**

Edit `parts/footer.html`. Replace:

```html
      <!-- wp:group {"className":"brand","layout":{"type":"flex","flexWrap":"nowrap"}} -->
      <div class="wp-block-group brand">
        <!-- wp:starter/brand-mark /-->
        <!-- wp:site-title {"level":0,"style":{"typography":{"fontWeight":"800","textDecoration":"none","fontSize":"1.2rem"}}} /-->
      </div>
      <!-- /wp:group -->
```

with:

```html
      <!-- wp:group {"className":"brand","layout":{"type":"flex","flexWrap":"nowrap"}} -->
      <div class="wp-block-group brand">
        <!-- wp:site-logo {"width":180} /-->
      </div>
      <!-- /wp:group -->
```

Leave the surrounding `tagline` paragraph and `wp:starter/social-links` group below the `.brand` group untouched.

- [ ] **Step 2: Verify the file no longer references brand-mark**

```
grep -n "brand-mark\|wp:site-title" parts/footer.html
```

Expected: no matches.

- [ ] **Step 3: Commit**

```bash
git add parts/footer.html
git commit -m "refactor(footer): use core/site-logo in place of brand-mark + site-title"
```

---

## Task 4: Author the demo wide SVG logo

**Files:**
- Create: `docs/images/logo-demo.svg`

The SVG reuses the Phosphor "bank" path that currently lives in `src/blocks/brand-mark/render.php` so the seeded logo is visually consistent with the icon being retired. It uses a baked-in fill color (not `currentColor`) because the logo is loaded as an `<img>` by the site-logo block — `currentColor` would not inherit through.

- [ ] **Step 1: Create the SVG file**

Create `docs/images/logo-demo.svg`:

```xml
<?xml version="1.0" encoding="UTF-8"?>
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 400 96" role="img" aria-label="Pediment">
  <g fill="#0f172a" transform="translate(8 8) scale(0.3125)">
    <path d="M24,104H48v64H32a8,8,0,0,0,0,16H224a8,8,0,0,0,0-16H208V104h24a8,8,0,0,0,4.19-14.81l-104-64a8,8,0,0,0-8.38,0l-104,64A8,8,0,0,0,24,104Zm40,0H96v64H64Zm80,0v64H112V104Zm48,64H160V104h32ZM128,41.39,203.74,88H52.26ZM248,208a8,8,0,0,1-8,8H16a8,8,0,0,1,0-16H240A8,8,0,0,1,248,208Z"/>
  </g>
  <text x="108" y="62" font-family="-apple-system, BlinkMacSystemFont, 'Segoe UI', system-ui, sans-serif" font-weight="800" font-size="44" letter-spacing="-1" fill="#0f172a">Pediment</text>
</svg>
```

- [ ] **Step 2: Verify it parses as valid XML**

```
xmllint --noout docs/images/logo-demo.svg
```

Expected: no output (success). If `xmllint` is unavailable on the host, skip and rely on the seed function actually loading it in the next task's tests.

- [ ] **Step 3: Commit**

```bash
git add docs/images/logo-demo.svg
git commit -m "feat(assets): add wide Pediment demo SVG for custom-logo seed"
```

---

## Task 5: Seed the demo logo and wire it into `starter_seed_run()`

**Files:**
- Create: `tests/phpunit/Seed/SeedDemoLogoTest.php`
- Modify: `inc/seed.php` (add `starter_seed_demo_logo()`, call it from `starter_seed_run()` after `starter_seed_demo_image()`).

- [ ] **Step 1: Write the failing test**

Create `tests/phpunit/Seed/SeedDemoLogoTest.php`:

```php
<?php

class SeedDemoLogoTest extends WP_UnitTestCase {
	public function set_up(): void {
		parent::set_up();
		remove_theme_mod( 'custom_logo' );
		$existing = get_posts(
			array(
				'post_type'   => 'attachment',
				'post_status' => 'inherit',
				'numberposts' => -1,
				'fields'      => 'ids',
				'meta_key'    => '_starter_seed_demo_logo',
				'meta_value'  => '1',
			)
		);
		foreach ( $existing as $id ) {
			wp_delete_attachment( (int) $id, true );
		}
	}

	public function test_seed_sideloads_demo_logo_and_sets_custom_logo_theme_mod() {
		$id = starter_seed_demo_logo();

		$this->assertGreaterThan( 0, $id, 'Seed must return a positive attachment ID.' );
		$attachment = get_post( $id );
		$this->assertInstanceOf( WP_Post::class, $attachment );
		$this->assertSame( 'image/svg+xml', $attachment->post_mime_type );
		$this->assertSame( '1', get_post_meta( $id, '_starter_seed_demo_logo', true ) );
		$this->assertSame( $id, (int) get_theme_mod( 'custom_logo', 0 ) );
	}

	public function test_seed_demo_logo_is_idempotent() {
		$first  = starter_seed_demo_logo();
		$second = starter_seed_demo_logo();

		$this->assertSame( $first, $second, 'Second call must return the same attachment.' );

		$attachments = get_posts(
			array(
				'post_type'   => 'attachment',
				'post_status' => 'inherit',
				'numberposts' => -1,
				'fields'      => 'ids',
				'meta_key'    => '_starter_seed_demo_logo',
				'meta_value'  => '1',
			)
		);
		$this->assertCount( 1, $attachments, 'Idempotent seed must not create a duplicate attachment.' );
	}

	public function test_seed_run_invokes_demo_logo_seed() {
		starter_seed_run();
		$this->assertGreaterThan( 0, (int) get_theme_mod( 'custom_logo', 0 ) );
	}
}
```

- [ ] **Step 2: Run tests to verify they fail**

```
composer test -- --filter SeedDemoLogoTest
```

Expected: FAIL — `starter_seed_demo_logo` undefined.

- [ ] **Step 3: Implement `starter_seed_demo_logo()`**

Edit `inc/seed.php`. Add the following function immediately after `starter_seed_demo_image()` (which currently ends around line 187):

```php
/**
 * Idempotently sideload the wide demo logo and set it as the site's
 * Custom Logo. Mirrors starter_seed_demo_image().
 *
 * The marker meta `_starter_seed_demo_logo` makes removal trivial:
 *   wp post list --post_type=attachment --meta_key=_starter_seed_demo_logo --field=ID
 *   | xargs -I{} wp post delete {} --force
 *
 * @return int Attachment ID, or 0 on failure.
 */
function starter_seed_demo_logo(): int {
	$existing = get_posts(
		array(
			'post_type'   => 'attachment',
			'post_status' => 'inherit',
			'numberposts' => 1,
			'fields'      => 'ids',
			'meta_key'    => '_starter_seed_demo_logo',
			'meta_value'  => '1',
		)
	);
	if ( ! empty( $existing ) ) {
		$id = (int) $existing[0];
		if ( (int) get_theme_mod( 'custom_logo', 0 ) !== $id ) {
			set_theme_mod( 'custom_logo', $id );
		}
		return $id;
	}

	$src = get_template_directory() . '/docs/images/logo-demo.svg';
	if ( ! file_exists( $src ) ) {
		return 0;
	}

	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';

	$uploads = wp_upload_dir();
	if ( ! empty( $uploads['error'] ) ) {
		return 0;
	}
	$filename = wp_unique_filename( $uploads['path'], basename( $src ) );
	$dest     = trailingslashit( $uploads['path'] ) . $filename;
	if ( ! @copy( $src, $dest ) ) {
		return 0;
	}

	$attach_id = wp_insert_attachment(
		array(
			'post_mime_type' => 'image/svg+xml',
			'post_title'     => 'Demo logo (Pediment)',
			'post_content'   => '',
			'post_status'    => 'inherit',
		),
		$dest
	);
	if ( is_wp_error( $attach_id ) || ! $attach_id ) {
		@unlink( $dest );
		return 0;
	}

	update_post_meta( (int) $attach_id, '_starter_seed_demo_logo', '1' );
	set_theme_mod( 'custom_logo', (int) $attach_id );

	return (int) $attach_id;
}
```

`wp_generate_attachment_metadata()` is intentionally omitted — SVG attachments do not produce sub-sizes, and calling it on an SVG can emit warnings.

- [ ] **Step 4: Call it from `starter_seed_run()`**

In `inc/seed.php`, find the line:

```php
	starter_seed_demo_image();
```

(around line 36, inside `starter_seed_run()`). Add this line immediately after it:

```php
	starter_seed_demo_logo();
```

- [ ] **Step 5: Run tests to verify they pass**

```
composer test -- --filter SeedDemoLogoTest
```

Expected: PASS (all three tests).

- [ ] **Step 6: Run the broader seed test suite to confirm no regression**

```
composer test -- --filter SeedCommandTest
```

Expected: PASS — existing seed tests still green.

- [ ] **Step 7: Commit**

```bash
git add inc/seed.php tests/phpunit/Seed/SeedDemoLogoTest.php
git commit -m "feat(seed): sideload + bake demo wide logo, set as custom_logo theme mod"
```

---

## Task 6: Delete the `starter/brand-mark` block

The header and footer no longer reference the block (Tasks 2 and 3). The only other repository hits for the string `brand-mark` are CSS class usage in `IconsTest.php` and `starter_icon( …, 'brand-mark' )` — both treat `brand-mark` as a CSS class name on a generic icon and are unrelated to the block. No code change needed there.

**Files:**
- Delete: `src/blocks/brand-mark/block.json`
- Delete: `src/blocks/brand-mark/edit.tsx`
- Delete: `src/blocks/brand-mark/index.tsx`
- Delete: `src/blocks/brand-mark/render.php`

- [ ] **Step 1: Verify no remaining references exist in production code**

```
grep -rn "starter/brand-mark\|wp:starter/brand-mark" --include="*.php" --include="*.html" --include="*.json" --include="*.tsx" --include="*.ts" .
```

Expected: no matches.

If anything turns up, fix it before continuing.

- [ ] **Step 2: Delete the block source directory**

```bash
git rm -r src/blocks/brand-mark
```

- [ ] **Step 3: Rebuild the block bundle**

```
pnpm build
```

Expected: build succeeds, no errors. The remaining blocks still emit normally.

- [ ] **Step 4: Locally remove the stale build artifact**

`wp-scripts build` only emits files for current entry points; it does not clean stale output. `build/` is gitignored, so commit isn't needed — this is a per-developer cleanup so the dev environment doesn't keep registering the removed block.

```bash
rm -rf build/blocks/brand-mark
```

- [ ] **Step 5: Run the full PHPUnit suite to make sure nothing depended on the block**

```
composer test
```

Expected: PASS — all tests green.

- [ ] **Step 6: Commit**

```bash
git add -A src/blocks
git commit -m "refactor: retire starter/brand-mark block (superseded by core/site-logo)"
```

---

## Task 7: Manual smoke verification (after merge to development)

Per the user's "Worktree tests = verify-after-merge" memory, wp-env mounts the main checkout, not the worktree. Run this verification **after merging the plan's worktree branch back into `development`** and reloading the site at `http://localhost:8890`.

- [ ] **Step 1: Re-run the seed inside wp-env**

```
pnpm wp-env run cli wp starter-theme seed
```

Expected: "Starter theme seeded." (or similar). The demo logo attachment is created and `custom_logo` theme mod is set.

- [ ] **Step 2: Visit the front-end home page**

Open `http://localhost:8890`. Confirm:
- Header shows the wide Pediment logo (bank glyph + wordmark) instead of the previous icon-plus-text pair.
- Footer shows the same logo (smaller width per template-part attribute).
- Browser console has no errors related to missing block types.

- [ ] **Step 3: Visit the Site Editor**

Open `http://localhost:8890/wp-admin/site-editor.php`. Confirm:
- Editing the header template part, the site-logo block is present, selectable, and the "Replace" media picker accepts a non-square upload (no forced crop).
- Editing the footer template part, same behavior.

- [ ] **Step 4: Test the fallback**

Delete the seeded logo attachment (Media Library → demo logo → delete permanently) and reload the front-end. The header and footer should fall back to rendering the site title as plain text — the existing core behavior of `core/site-logo`. Then re-run `wp starter-theme seed` to restore.

- [ ] **Step 5: (No commit — verification only)**

If any step fails, file an issue / amend the plan and follow up. If all pass, the feature is shipped.

---

## Self-Review

**Spec coverage** — every section of `docs/superpowers/specs/2026-05-21-custom-logo-design.md` is addressed:

| Spec section | Task |
| --- | --- |
| Theme support | Task 1 |
| Header markup | Task 2 |
| Footer markup | Task 3 |
| Demo seed asset | Task 4 |
| Seed function | Task 5 |
| Deleting the brand-mark block | Task 6 |
| Verification | Task 7 |
| Risk / rollback | Implicit — git revert reverses the diff; SVG mime failure mode covered by the seed returning 0 and the site-logo block's built-in title fallback. |

**Placeholder scan** — no TBD/TODO; every code step shows the actual code; every command shows the actual command and expected output.

**Type / identifier consistency** — function name `starter_seed_demo_logo` is consistent across the seed function, the call from `starter_seed_run()`, and the test file. Theme-mod key `custom_logo` matches WordPress core's expectation. Meta key `_starter_seed_demo_logo` is consistent across the function, the test set-up cleanup, and the documented cleanup command. Block name `starter/brand-mark` matches the grep pattern in Task 6 Step 1.
