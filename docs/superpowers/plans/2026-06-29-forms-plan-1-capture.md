# Forms — Plan 1: Capture Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ship an AI-generatable `pediment/form` + `pediment/form-field` block pair whose submissions are validated server-side and stored in a `form_submission` CPT — the form works end-to-end with no delivery yet.

**Architecture:** Two dynamic blocks (PHP `render.php`, no `save`). The post content *is* the form definition: the submission REST endpoint re-parses the post, re-derives the authoritative field list, and validates against it. A shared field-collection helper guarantees the front-end render, the form-key, and server validation all agree. Submissions fire a `pediment_form_submitted` action that a storage module persists to a CPT (the same action is where Plan 2 will hook delivery).

**Tech Stack:** WordPress block theme (PHP 8.1+, `@wordpress/scripts`/TypeScript blocks), PHPUnit (wp-env tests harness), Playwright e2e.

This is the first of four sequential plans (see `docs/superpowers/specs/2026-06-29-ai-generatable-forms-design.md`). Plan 1 leaves the `destination` attribute present but inert; Plans 2–4 add delivery, the AI destination builder, and contact-form migration.

## Global Constraints

- PHP `>= 8.1`; match existing WPCS style (Yoda conditions, full escaping, `/* translators */` comments). `composer run lint` (phpcs) must pass with **zero warnings** — CI fails on warnings.
- `npm run lint:colors` must pass — **no color literals** in SCSS; use `var(--wp--preset--…)` / inherit only.
- Text domain is `pediment`; block category is `pediment`.
- Blocks build from `src/blocks/<name>/` → `build/blocks/` via `npm run build` (also regenerates `build/blocks-manifest.php`). Registration is automatic via `inc/register-blocks.php`; do **not** hand-edit `build/`.
- Both blocks are **dynamic**: `index.tsx` registers with `{ edit }` only (no `save`), output comes from `render.php`.
- New `inc/*.php` files must be `require_once`d from `functions.php` and start with the `if ( ! defined( 'ABSPATH' ) ) { exit; }` guard.
- Anti-spam reuses the contact pattern: honeypot field `hp_field` + time-trap hidden `_t`.

---

### Task 1: Field-collection & validation helpers

Pure functions that both `render.php` and the REST handler depend on. No WordPress hooks yet.

**Files:**
- Create: `inc/forms.php`
- Modify: `functions.php` (add `require_once`)
- Test: `tests/phpunit/Forms/FieldsTest.php`

**Interfaces:**
- Produces:
  - `pediment_form_slug( string $label ): string` — normalized machine name (`a-z0-9_`, never empty; falls back to `'field'`).
  - `pediment_form_collect_fields( array $blocks ): array` — given a list of parsed blocks, returns an ordered list of field rows `['name'=>string,'type'=>string,'label'=>string,'required'=>bool,'options'=>string[]]` for each direct `pediment/form-field` child.
  - `pediment_form_form_key( array $fields ): string` — 12-char stable hash of the field names.
  - `pediment_form_find_forms( array $blocks ): array` — recursively returns every parsed `pediment/form` block.
  - `pediment_form_validate( array $fields, array $values ): array` — returns a `name => message` error map (empty when valid).

- [ ] **Step 1: Write the failing test**

```php
// tests/phpunit/Forms/FieldsTest.php
<?php

class FieldsTest extends WP_UnitTestCase {
	private function markup(): string {
		return '<!-- wp:pediment/form -->'
			. '<!-- wp:pediment/form-field {"label":"Full Name","fieldName":"name","required":true} /-->'
			. '<!-- wp:pediment/form-field {"fieldType":"email","label":"Email","fieldName":"email","required":true} /-->'
			. '<!-- wp:pediment/form-field {"fieldType":"select","label":"Plan","fieldName":"plan","options":[{"label":"Basic","value":"basic"},{"label":"Pro","value":"pro"}]} /-->'
			. '<!-- /wp:pediment/form -->';
	}

	public function test_slug_normalizes_and_falls_back() {
		$this->assertSame( 'full_name', pediment_form_slug( 'Full Name!' ) );
		$this->assertSame( 'field', pediment_form_slug( '—' ) );
	}

	public function test_collect_fields_reads_children() {
		$blocks = parse_blocks( $this->markup() );
		$inner  = $blocks[0]['innerBlocks'];
		$fields = pediment_form_collect_fields( $inner );

		$this->assertCount( 3, $fields );
		$this->assertSame( 'name', $fields[0]['name'] );
		$this->assertTrue( $fields[0]['required'] );
		$this->assertSame( 'email', $fields[1]['type'] );
		$this->assertSame( array( 'basic', 'pro' ), $fields[2]['options'] );
	}

	public function test_form_key_is_stable_and_name_derived() {
		$inner  = parse_blocks( $this->markup() )[0]['innerBlocks'];
		$key1   = pediment_form_form_key( pediment_form_collect_fields( $inner ) );
		$key2   = pediment_form_form_key( pediment_form_collect_fields( $inner ) );
		$this->assertSame( $key1, $key2 );
		$this->assertSame( 12, strlen( $key1 ) );
	}

	public function test_find_forms_recurses_into_groups() {
		$wrapped = '<!-- wp:group --><div class="wp-block-group">' . $this->markup() . '</div><!-- /wp:group -->';
		$forms   = pediment_form_find_forms( parse_blocks( $wrapped ) );
		$this->assertCount( 1, $forms );
		$this->assertSame( 'pediment/form', $forms[0]['blockName'] );
	}

	public function test_validate_reports_required_and_type_errors() {
		$fields = pediment_form_collect_fields( parse_blocks( $this->markup() )[0]['innerBlocks'] );

		$errors = pediment_form_validate( $fields, array( 'name' => '', 'email' => 'nope', 'plan' => 'gold' ) );
		$this->assertArrayHasKey( 'name', $errors );  // required
		$this->assertArrayHasKey( 'email', $errors ); // bad email
		$this->assertArrayHasKey( 'plan', $errors );  // not an allowed option

		$ok = pediment_form_validate( $fields, array( 'name' => 'A', 'email' => 'a@b.com', 'plan' => 'pro' ) );
		$this->assertSame( array(), $ok );
	}
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `npm run build && wp-env run tests-cli --env-cwd=wp-content/themes/pediment vendor/bin/phpunit --filter FieldsTest`
Expected: FAIL — `Call to undefined function pediment_form_slug()`.

- [ ] **Step 3: Write the helpers**

```php
// inc/forms.php
<?php
/**
 * Generic form submission endpoint, field derivation, and validation.
 *
 * @package Pediment
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const PEDIMENT_FORM_NAMESPACE = 'pediment/v1';
const PEDIMENT_FORM_ROUTE     = '/forms';
const PEDIMENT_FORM_CPT       = 'form_submission';
const PEDIMENT_FORM_MIN_AGE   = 3;
const PEDIMENT_FORM_CRON_HOOK = 'pediment_form_cleanup';

/**
 * Normalize a label into a stable machine field name.
 */
function pediment_form_slug( string $label ): string {
	$slug = str_replace( '-', '_', sanitize_title( $label ) );
	return '' === $slug ? 'field' : $slug;
}

/**
 * Build the ordered field list from a form's direct child blocks.
 *
 * @param array<int,array<string,mixed>> $blocks Parsed inner blocks.
 * @return array<int,array<string,mixed>>
 */
function pediment_form_collect_fields( array $blocks ): array {
	$fields = array();
	foreach ( $blocks as $block ) {
		if ( ! is_array( $block ) || ( $block['blockName'] ?? '' ) !== 'pediment/form-field' ) {
			continue;
		}
		$attrs = isset( $block['attrs'] ) && is_array( $block['attrs'] ) ? $block['attrs'] : array();
		$label = isset( $attrs['label'] ) ? (string) $attrs['label'] : '';
		$name  = isset( $attrs['fieldName'] ) && '' !== $attrs['fieldName']
			? pediment_form_slug( (string) $attrs['fieldName'] )
			: pediment_form_slug( $label );

		$options = array();
		if ( isset( $attrs['options'] ) && is_array( $attrs['options'] ) ) {
			foreach ( $attrs['options'] as $opt ) {
				if ( is_array( $opt ) && isset( $opt['value'] ) ) {
					$options[] = (string) $opt['value'];
				}
			}
		}

		$fields[] = array(
			'name'     => $name,
			'type'     => isset( $attrs['fieldType'] ) ? (string) $attrs['fieldType'] : 'text',
			'label'    => '' !== $label ? $label : $name,
			'required' => ! empty( $attrs['required'] ),
			'options'  => $options,
		);
	}
	return $fields;
}

/**
 * Stable 12-char key identifying a form by its field-name set.
 *
 * @param array<int,array<string,mixed>> $fields
 */
function pediment_form_form_key( array $fields ): string {
	$names = wp_list_pluck( $fields, 'name' );
	return substr( md5( (string) wp_json_encode( $names ) ), 0, 12 );
}

/**
 * Recursively collect every pediment/form parsed block.
 *
 * @param array<int,array<string,mixed>> $blocks
 * @return array<int,array<string,mixed>>
 */
function pediment_form_find_forms( array $blocks ): array {
	$found = array();
	foreach ( $blocks as $block ) {
		if ( ! is_array( $block ) ) {
			continue;
		}
		if ( ( $block['blockName'] ?? '' ) === 'pediment/form' ) {
			$found[] = $block;
		}
		if ( ! empty( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
			$found = array_merge( $found, pediment_form_find_forms( $block['innerBlocks'] ) );
		}
	}
	return $found;
}

/**
 * Validate submitted values against the derived field list.
 *
 * @param array<int,array<string,mixed>> $fields
 * @param array<string,mixed>            $values
 * @return array<string,string> name => error message
 */
function pediment_form_validate( array $fields, array $values ): array {
	$errors = array();
	foreach ( $fields as $field ) {
		$name  = (string) $field['name'];
		$value = isset( $values[ $name ] ) ? trim( (string) $values[ $name ] ) : '';

		if ( $field['required'] && '' === $value ) {
			/* translators: %s: field label */
			$errors[ $name ] = sprintf( __( '%s is required.', 'pediment' ), $field['label'] );
			continue;
		}
		if ( '' === $value ) {
			continue;
		}

		switch ( $field['type'] ) {
			case 'email':
				if ( ! is_email( $value ) ) {
					$errors[ $name ] = __( 'Enter a valid email address.', 'pediment' );
				}
				break;
			case 'number':
				if ( ! is_numeric( $value ) ) {
					$errors[ $name ] = __( 'Enter a number.', 'pediment' );
				}
				break;
			case 'date':
				$d = DateTime::createFromFormat( 'Y-m-d', $value );
				if ( ! $d || $d->format( 'Y-m-d' ) !== $value ) {
					$errors[ $name ] = __( 'Enter a valid date.', 'pediment' );
				}
				break;
			case 'select':
			case 'radio':
				if ( ! empty( $field['options'] ) && ! in_array( $value, $field['options'], true ) ) {
					$errors[ $name ] = __( 'Choose a valid option.', 'pediment' );
				}
				break;
		}
	}
	return $errors;
}
```

Add the require to `functions.php` immediately after the `contact-form.php` line:

```php
require_once __DIR__ . '/inc/contact-form.php';
require_once __DIR__ . '/inc/forms.php';
require_once __DIR__ . '/inc/forms-storage.php';
```

(`forms-storage.php` is created in Task 3; create an empty guarded stub now so the require does not fatal:)

```php
// inc/forms-storage.php
<?php
/**
 * Form submission storage CPT, persistence, admin columns, and retention.
 *
 * @package Pediment
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `wp-env run tests-cli --env-cwd=wp-content/themes/pediment vendor/bin/phpunit --filter FieldsTest`
Expected: PASS (5 tests).

- [ ] **Step 5: Commit**

```bash
git add inc/forms.php inc/forms-storage.php functions.php tests/phpunit/Forms/FieldsTest.php
git commit -m "feat: form field-collection and validation helpers"
```

---

### Task 2: Submission REST endpoint

**Files:**
- Modify: `inc/forms.php` (append route + handler)
- Test: `tests/phpunit/Forms/SubmissionTest.php`

**Interfaces:**
- Consumes: `pediment_form_locate()`, `pediment_form_validate()`, `pediment_form_collect_fields()`, `pediment_form_form_key()` (Task 1).
- Produces:
  - `pediment_form_locate( int $post_id, string $form_key ): ?array` — returns `['fields'=>array,'destination'=>string]` for the matching form in that post, or `null`.
  - `POST pediment/v1/forms` → `{ ok: true }` on success; `WP_Error` (status 400) on spam / unknown form / unknown field / validation failure.
  - Action `do_action( 'pediment_form_submitted', array $submission, WP_REST_Request $request )` where `$submission = ['post_id'=>int,'form_key'=>string,'destination'=>string,'fields'=>array<string,array{label:string,value:string}>]`.

- [ ] **Step 1: Write the failing test**

```php
// tests/phpunit/Forms/SubmissionTest.php
<?php

class SubmissionTest extends WP_UnitTestCase {
	private $server;
	private int $post_id;
	private string $form_key;

	public function set_up(): void {
		parent::set_up();
		global $wp_rest_server;
		$wp_rest_server = new WP_REST_Server();
		$this->server   = $wp_rest_server;
		do_action( 'rest_api_init' );

		$markup = '<!-- wp:pediment/form {"destination":"sales"} -->'
			. '<!-- wp:pediment/form-field {"label":"Name","fieldName":"name","required":true} /-->'
			. '<!-- wp:pediment/form-field {"fieldType":"email","label":"Email","fieldName":"email","required":true} /-->'
			. '<!-- /wp:pediment/form -->';
		$this->post_id  = (int) self::factory()->post->create( array( 'post_content' => $markup ) );
		$inner          = parse_blocks( $markup )[0]['innerBlocks'];
		$this->form_key = pediment_form_form_key( pediment_form_collect_fields( $inner ) );
	}

	private function submit( array $body ): WP_REST_Response {
		$request = new WP_REST_Request( 'POST', '/pediment/v1/forms' );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( (string) wp_json_encode( $body ) );
		return $this->server->dispatch( $request );
	}

	private function base( array $fields ): array {
		return array(
			'post_id'  => $this->post_id,
			'form_key' => $this->form_key,
			'hp_field' => '',
			'_t'       => time() - 10,
			'fields'   => $fields,
		);
	}

	public function test_valid_submission_returns_200_and_fires_action() {
		$captured = null;
		add_action( 'pediment_form_submitted', function ( $s ) use ( &$captured ) { $captured = $s; }, 5 );

		$r = $this->submit( $this->base( array( 'name' => 'Alice', 'email' => 'alice@example.com' ) ) );

		$this->assertSame( 200, $r->get_status() );
		$this->assertTrue( $r->get_data()['ok'] );
		$this->assertSame( 'sales', $captured['destination'] );
		$this->assertSame( 'Alice', $captured['fields']['name']['value'] );
	}

	public function test_missing_required_returns_400() {
		$r = $this->submit( $this->base( array( 'name' => '', 'email' => 'a@b.com' ) ) );
		$this->assertSame( 400, $r->get_status() );
	}

	public function test_unknown_field_returns_400() {
		$r = $this->submit( $this->base( array( 'name' => 'A', 'email' => 'a@b.com', 'evil' => 'x' ) ) );
		$this->assertSame( 400, $r->get_status() );
	}

	public function test_honeypot_returns_400() {
		$body             = $this->base( array( 'name' => 'A', 'email' => 'a@b.com' ) );
		$body['hp_field'] = 'bot';
		$this->assertSame( 400, $this->submit( $body )->get_status() );
	}

	public function test_unknown_form_key_returns_400() {
		$body             = $this->base( array( 'name' => 'A', 'email' => 'a@b.com' ) );
		$body['form_key'] = 'deadbeef0000';
		$this->assertSame( 400, $this->submit( $body )->get_status() );
	}
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `wp-env run tests-cli --env-cwd=wp-content/themes/pediment vendor/bin/phpunit --filter SubmissionTest`
Expected: FAIL — route `/pediment/v1/forms` returns 404 (no route registered).

- [ ] **Step 3: Implement the route, `pediment_form_locate`, and handler**

Append to `inc/forms.php`:

```php
/**
 * Find the form in a post that matches the submitted key.
 *
 * @return array{fields:array<int,array<string,mixed>>,destination:string}|null
 */
function pediment_form_locate( int $post_id, string $form_key ) {
	$post = get_post( $post_id );
	if ( ! $post instanceof WP_Post ) {
		return null;
	}
	foreach ( pediment_form_find_forms( parse_blocks( (string) $post->post_content ) ) as $form ) {
		$inner  = isset( $form['innerBlocks'] ) && is_array( $form['innerBlocks'] ) ? $form['innerBlocks'] : array();
		$fields = pediment_form_collect_fields( $inner );
		if ( pediment_form_form_key( $fields ) === $form_key ) {
			$attrs = isset( $form['attrs'] ) && is_array( $form['attrs'] ) ? $form['attrs'] : array();
			return array(
				'fields'      => $fields,
				'destination' => isset( $attrs['destination'] ) ? (string) $attrs['destination'] : '',
			);
		}
	}
	return null;
}

add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			PEDIMENT_FORM_NAMESPACE,
			PEDIMENT_FORM_ROUTE,
			array(
				'methods'             => 'POST',
				// Public by design (anonymous form). Anti-spam = honeypot + time-trap.
				'permission_callback' => '__return_true',
				'callback'            => 'pediment_form_handle_submission',
			)
		);
	}
);

function pediment_form_handle_submission( WP_REST_Request $request ) {
	$hp_field = (string) $request->get_param( 'hp_field' );
	$t_raw    = $request->get_param( '_t' );
	$t        = is_numeric( $t_raw ) ? (int) $t_raw : 0;
	$post_id  = (int) $request->get_param( 'post_id' );
	$form_key = (string) $request->get_param( 'form_key' );
	$values   = $request->get_param( 'fields' );
	$values   = is_array( $values ) ? $values : array();

	if ( '' !== $hp_field ) {
		return new WP_Error( 'pediment_spam', __( 'Submission rejected.', 'pediment' ), array( 'status' => 400 ) );
	}
	$now = time();
	if ( $t <= 0 || $t > $now || ( $now - $t ) < PEDIMENT_FORM_MIN_AGE ) {
		return new WP_Error( 'pediment_spam', __( 'Submission rejected.', 'pediment' ), array( 'status' => 400 ) );
	}

	$form = $post_id > 0 ? pediment_form_locate( $post_id, $form_key ) : null;
	if ( null === $form ) {
		return new WP_Error( 'pediment_unknown_form', __( 'Form not found.', 'pediment' ), array( 'status' => 400 ) );
	}

	$allowed = wp_list_pluck( $form['fields'], 'name' );
	foreach ( array_keys( $values ) as $key ) {
		if ( ! in_array( $key, $allowed, true ) ) {
			return new WP_Error( 'pediment_unknown_field', __( 'Unknown field.', 'pediment' ), array( 'status' => 400 ) );
		}
	}

	$errors = pediment_form_validate( $form['fields'], $values );
	if ( ! empty( $errors ) ) {
		return new WP_Error(
			'pediment_validation',
			__( 'Validation failed.', 'pediment' ),
			array(
				'status' => 400,
				'errors' => $errors,
			)
		);
	}

	$collected = array();
	foreach ( $form['fields'] as $field ) {
		$name               = (string) $field['name'];
		$collected[ $name ] = array(
			'label' => (string) $field['label'],
			'value' => isset( $values[ $name ] ) ? sanitize_textarea_field( (string) $values[ $name ] ) : '',
		);
	}

	$submission = array(
		'post_id'     => $post_id,
		'form_key'    => $form_key,
		'destination' => $form['destination'],
		'fields'      => $collected,
	);

	do_action( 'pediment_form_submitted', $submission, $request );

	return new WP_REST_Response( array( 'ok' => true ), 200 );
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `wp-env run tests-cli --env-cwd=wp-content/themes/pediment vendor/bin/phpunit --filter SubmissionTest`
Expected: PASS (5 tests).

- [ ] **Step 5: Commit**

```bash
git add inc/forms.php tests/phpunit/Forms/SubmissionTest.php
git commit -m "feat: generic form submission REST endpoint with server-authoritative validation"
```

---

### Task 3: Submission storage CPT

**Files:**
- Modify: `inc/forms-storage.php`
- Test: `tests/phpunit/Forms/StorageTest.php`

**Interfaces:**
- Consumes: `PEDIMENT_FORM_CPT` and the `pediment_form_submitted` action (Tasks 1–2).
- Produces: a registered `form_submission` CPT; `pediment_form_persist_submission( array $submission, $request ): void` hooked at priority 10; admin columns `from` / `destination` / `date`.

- [ ] **Step 1: Write the failing test**

```php
// tests/phpunit/Forms/StorageTest.php
<?php

class StorageTest extends WP_UnitTestCase {
	public function test_cpt_registered() {
		do_action( 'init' );
		$this->assertTrue( post_type_exists( PEDIMENT_FORM_CPT ) );
		$pt = get_post_type_object( PEDIMENT_FORM_CPT );
		$this->assertFalse( $pt->public );
		$this->assertTrue( $pt->show_ui );
	}

	public function test_submission_persists_row_with_meta() {
		do_action( 'init' );

		$submission = array(
			'post_id'     => 0,
			'form_key'    => 'abc123abc123',
			'destination' => 'sales',
			'fields'      => array(
				'name'  => array( 'label' => 'Name', 'value' => 'Alice' ),
				'email' => array( 'label' => 'Email', 'value' => 'alice@example.com' ),
			),
		);
		do_action( 'pediment_form_submitted', $submission, null );

		$posts = get_posts( array( 'post_type' => PEDIMENT_FORM_CPT, 'numberposts' => -1, 'post_status' => 'any' ) );
		$this->assertCount( 1, $posts );

		$id = $posts[0]->ID;
		$this->assertSame( 'sales', get_post_meta( $id, '_destination', true ) );
		$this->assertStringContainsString( 'alice@example.com', $posts[0]->post_content );

		$stored = json_decode( (string) get_post_meta( $id, '_fields', true ), true );
		$this->assertSame( 'Alice', $stored['name']['value'] );

		wp_delete_post( $id, true );
	}
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `wp-env run tests-cli --env-cwd=wp-content/themes/pediment vendor/bin/phpunit --filter StorageTest`
Expected: FAIL — `post_type_exists` returns false.

- [ ] **Step 3: Implement the CPT, persistence, and columns**

Append to `inc/forms-storage.php`:

```php
add_action(
	'init',
	function () {
		if ( post_type_exists( PEDIMENT_FORM_CPT ) ) {
			return;
		}
		register_post_type(
			PEDIMENT_FORM_CPT,
			array(
				'label'               => __( 'Form submissions', 'pediment' ),
				'labels'              => array(
					'name'          => __( 'Form submissions', 'pediment' ),
					'singular_name' => __( 'Form submission', 'pediment' ),
					'menu_name'     => __( 'Form submissions', 'pediment' ),
				),
				'public'              => false,
				'exclude_from_search' => true,
				'publicly_queryable'  => false,
				'show_ui'             => true,
				'show_in_menu'        => true,
				'show_in_rest'        => false,
				'menu_icon'           => 'dashicons-feedback',
				'capability_type'     => 'page',
				'capabilities'        => array( 'create_posts' => 'do_not_allow' ),
				'map_meta_cap'        => true,
				'supports'            => array( 'title' ),
				'has_archive'         => false,
				'rewrite'             => false,
			)
		);
	}
);

add_action( 'pediment_form_submitted', 'pediment_form_persist_submission', 10, 2 );

function pediment_form_persist_submission( array $submission, $request ): void {
	$post_id     = (int) ( $submission['post_id'] ?? 0 );
	$destination = (string) ( $submission['destination'] ?? '' );
	$fields      = isset( $submission['fields'] ) && is_array( $submission['fields'] ) ? $submission['fields'] : array();

	$source_title = $post_id > 0 ? get_the_title( $post_id ) : '';
	$title        = sprintf(
		/* translators: 1: source page title, 2: submission date */
		__( '%1$s — %2$s', 'pediment' ),
		'' !== $source_title ? $source_title : __( 'Form', 'pediment' ),
		wp_date( 'Y-m-d H:i' )
	);

	$lines = array();
	foreach ( $fields as $data ) {
		$lines[] = sprintf( '%s: %s', (string) ( $data['label'] ?? '' ), (string) ( $data['value'] ?? '' ) );
	}

	$new_id = wp_insert_post(
		array(
			'post_type'    => PEDIMENT_FORM_CPT,
			'post_status'  => 'publish',
			'post_title'   => $title,
			'post_content' => implode( "\n", $lines ),
		),
		true
	);
	if ( is_wp_error( $new_id ) || ! $new_id ) {
		return;
	}

	update_post_meta( $new_id, '_fields', wp_json_encode( $fields ) );
	update_post_meta( $new_id, '_source_post_id', $post_id );
	update_post_meta( $new_id, '_destination', sanitize_text_field( $destination ) );
}

add_filter(
	'manage_' . PEDIMENT_FORM_CPT . '_posts_columns',
	function ( array $cols ) {
		return array(
			'cb'          => $cols['cb'] ?? '',
			'title'       => __( 'Submission', 'pediment' ),
			'destination' => __( 'Destination', 'pediment' ),
			'date'        => __( 'Submitted', 'pediment' ),
		);
	}
);

add_action(
	'manage_' . PEDIMENT_FORM_CPT . '_posts_custom_column',
	function ( $col, $post_id ) {
		if ( 'destination' === $col ) {
			$dest = (string) get_post_meta( $post_id, '_destination', true );
			echo esc_html( '' !== $dest ? $dest : __( '(default)', 'pediment' ) );
		}
	},
	10,
	2
);
```

- [ ] **Step 4: Run test to verify it passes**

Run: `wp-env run tests-cli --env-cwd=wp-content/themes/pediment vendor/bin/phpunit --filter StorageTest`
Expected: PASS (2 tests).

- [ ] **Step 5: Commit**

```bash
git add inc/forms-storage.php tests/phpunit/Forms/StorageTest.php
git commit -m "feat: form_submission CPT, persistence, and admin columns"
```

---

### Task 4: Retention cron

**Files:**
- Modify: `inc/forms-storage.php` (append), `functions.php` (schedule hooks)
- Test: `tests/phpunit/Forms/RetentionTest.php`

**Interfaces:**
- Produces:
  - `pediment_form_cleanup(): void` — deletes submissions older than `apply_filters( 'pediment_form_retention_days', 90 )` days (a value `<= 0` disables purging).
  - `pediment_form_schedule_cleanup(): void` / `pediment_form_unschedule_cleanup(): void` — daily cron wiring.

- [ ] **Step 1: Write the failing test**

```php
// tests/phpunit/Forms/RetentionTest.php
<?php

class RetentionTest extends WP_UnitTestCase {
	public function test_cleanup_deletes_old_keeps_recent() {
		do_action( 'init' );

		$old = self::factory()->post->create(
			array(
				'post_type'     => PEDIMENT_FORM_CPT,
				'post_date_gmt' => gmdate( 'Y-m-d H:i:s', strtotime( '-200 days' ) ),
				'post_date'     => gmdate( 'Y-m-d H:i:s', strtotime( '-200 days' ) ),
			)
		);
		$new = self::factory()->post->create( array( 'post_type' => PEDIMENT_FORM_CPT ) );

		pediment_form_cleanup();

		$this->assertNull( get_post( $old ) );
		$this->assertNotNull( get_post( $new ) );
		wp_delete_post( $new, true );
	}

	public function test_retention_filter_zero_disables_purge() {
		do_action( 'init' );
		$old = self::factory()->post->create(
			array(
				'post_type'     => PEDIMENT_FORM_CPT,
				'post_date_gmt' => gmdate( 'Y-m-d H:i:s', strtotime( '-200 days' ) ),
				'post_date'     => gmdate( 'Y-m-d H:i:s', strtotime( '-200 days' ) ),
			)
		);
		add_filter( 'pediment_form_retention_days', '__return_zero' );

		pediment_form_cleanup();

		$this->assertNotNull( get_post( $old ) );
		remove_filter( 'pediment_form_retention_days', '__return_zero' );
		wp_delete_post( $old, true );
	}
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `wp-env run tests-cli --env-cwd=wp-content/themes/pediment vendor/bin/phpunit --filter RetentionTest`
Expected: FAIL — `Call to undefined function pediment_form_cleanup()`.

- [ ] **Step 3: Implement the cron**

Append to `inc/forms-storage.php`:

```php
add_action( PEDIMENT_FORM_CRON_HOOK, 'pediment_form_cleanup' );

function pediment_form_cleanup(): void {
	$days = (int) apply_filters( 'pediment_form_retention_days', 90 );
	if ( $days <= 0 ) {
		return;
	}
	$cutoff = gmdate( 'Y-m-d H:i:s', strtotime( '-' . $days . ' days' ) );

	$stale = get_posts(
		array(
			'post_type'      => PEDIMENT_FORM_CPT,
			'post_status'    => 'any',
			'posts_per_page' => 200,
			'fields'         => 'ids',
			'date_query'     => array(
				array(
					'before'    => $cutoff,
					'column'    => 'post_date_gmt',
					'inclusive' => true,
				),
			),
		)
	);
	foreach ( $stale as $post_id ) {
		wp_delete_post( $post_id, true );
	}
}

function pediment_form_schedule_cleanup(): void {
	if ( ! wp_next_scheduled( PEDIMENT_FORM_CRON_HOOK ) ) {
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', PEDIMENT_FORM_CRON_HOOK );
	}
}

function pediment_form_unschedule_cleanup(): void {
	wp_clear_scheduled_hook( PEDIMENT_FORM_CRON_HOOK );
}
```

Add to `functions.php` next to the existing contact cron wiring at the bottom:

```php
add_action( 'after_switch_theme', 'pediment_form_schedule_cleanup' );
add_action( 'switch_theme', 'pediment_form_unschedule_cleanup' );
```

- [ ] **Step 4: Run test to verify it passes**

Run: `wp-env run tests-cli --env-cwd=wp-content/themes/pediment vendor/bin/phpunit --filter RetentionTest`
Expected: PASS (2 tests).

- [ ] **Step 5: Commit**

```bash
git add inc/forms-storage.php functions.php tests/phpunit/Forms/RetentionTest.php
git commit -m "feat: form submission retention cron"
```

---

### Task 5: `pediment/form-field` block

**Files:**
- Create: `src/blocks/form-field/block.json`, `index.tsx`, `edit.tsx`, `render.php`, `style.scss`
- Test: `tests/phpunit/BlockRender/FormFieldTest.php`

**Interfaces:**
- Consumes: `pediment_form_slug()` (Task 1) inside `render.php`.
- Produces: a dynamic `pediment/form-field` block whose front-end render emits one labelled control carrying `data-pediment-field` and the resolved `name`.

- [ ] **Step 1: Write the block source**

`src/blocks/form-field/block.json`:

```json
{
	"$schema": "https://schemas.wp.org/trunk/block.json",
	"apiVersion": 3,
	"name": "pediment/form-field",
	"title": "Form Field",
	"category": "pediment",
	"parent": [ "pediment/form" ],
	"description": "A single field inside a Pediment form. Choose its type (text, email, phone, long text, dropdown, checkbox, radio, number, date), label, and whether it is required.",
	"textdomain": "pediment",
	"supports": { "html": false, "reusable": false },
	"attributes": {
		"fieldType": { "type": "string", "default": "text" },
		"label": { "type": "string", "default": "" },
		"fieldName": { "type": "string", "default": "" },
		"required": { "type": "boolean", "default": false },
		"placeholder": { "type": "string", "default": "" },
		"helpText": { "type": "string", "default": "" },
		"options": { "type": "array", "default": [] }
	},
	"editorScript": "file:./index.js",
	"editorStyle": "file:./style-index.css",
	"style": "file:./style-index.css",
	"render": "file:./render.php"
}
```

`src/blocks/form-field/index.tsx`:

```tsx
import { registerBlockType } from '@wordpress/blocks';
import metadata from './block.json';
import Edit from './edit';
import './style.scss';

registerBlockType( metadata.name, { edit: Edit } );
```

`src/blocks/form-field/edit.tsx`:

```tsx
import { __ } from '@wordpress/i18n';
import {
	useBlockProps,
	InspectorControls,
	store as blockEditorStore,
} from '@wordpress/block-editor';
import {
	PanelBody,
	SelectControl,
	TextControl,
	ToggleControl,
	TextareaControl,
} from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { useEffect } from '@wordpress/element';

type Option = { label: string; value: string };
type Attrs = {
	fieldType: string;
	label: string;
	fieldName: string;
	required: boolean;
	placeholder: string;
	helpText: string;
	options: Option[];
};

const TYPES = [
	{ label: __( 'Text', 'pediment' ), value: 'text' },
	{ label: __( 'Email', 'pediment' ), value: 'email' },
	{ label: __( 'Phone', 'pediment' ), value: 'tel' },
	{ label: __( 'Long text', 'pediment' ), value: 'textarea' },
	{ label: __( 'Dropdown', 'pediment' ), value: 'select' },
	{ label: __( 'Checkbox', 'pediment' ), value: 'checkbox' },
	{ label: __( 'Radio', 'pediment' ), value: 'radio' },
	{ label: __( 'Number', 'pediment' ), value: 'number' },
	{ label: __( 'Date', 'pediment' ), value: 'date' },
];

function slug( s: string ): string {
	return (
		s
			.toLowerCase()
			.trim()
			.replace( /[^a-z0-9]+/g, '_' )
			.replace( /^_+|_+$/g, '' ) || 'field'
	);
}

export default function Edit( {
	clientId,
	attributes,
	setAttributes,
}: {
	clientId: string;
	attributes: Attrs;
	setAttributes: ( a: Partial< Attrs > ) => void;
} ) {
	const blockProps = useBlockProps( { className: 'pediment-form__field' } );
	const { fieldType, label, fieldName, required, placeholder, helpText, options } =
		attributes;

	const siblingNames = useSelect(
		( select: any ) => {
			const { getBlockRootClientId, getBlocks } = select( blockEditorStore );
			const root = getBlockRootClientId( clientId );
			return getBlocks( root )
				.filter(
					( b: any ) =>
						b.clientId !== clientId && b.name === 'pediment/form-field'
				)
				.map( ( b: any ) => b.attributes.fieldName );
		},
		[ clientId ]
	) as string[];

	useEffect( () => {
		if ( fieldName !== '' || label === '' ) {
			return;
		}
		const base = slug( label );
		let candidate = base;
		let n = 2;
		while ( siblingNames.includes( candidate ) ) {
			candidate = base + '_' + n;
			n++;
		}
		setAttributes( { fieldName: candidate } );
	}, [ label ] ); // eslint-disable-line react-hooks/exhaustive-deps

	const needsOptions = fieldType === 'select' || fieldType === 'radio';
	const optionsText = options
		.map( ( o ) => ( o.label === o.value ? o.label : `${ o.label }|${ o.value }` ) )
		.join( '\n' );
	const parseOptions = ( text: string ): Option[] =>
		text
			.split( '\n' )
			.map( ( l ) => l.trim() )
			.filter( Boolean )
			.map( ( l ) => {
				const [ lab, val ] = l.split( '|' );
				return { label: lab.trim(), value: ( val ?? lab ).trim() };
			} );

	return (
		<div { ...blockProps }>
			<InspectorControls>
				<PanelBody title={ __( 'Field', 'pediment' ) }>
					<SelectControl
						label={ __( 'Type', 'pediment' ) }
						value={ fieldType }
						options={ TYPES }
						onChange={ ( v ) => setAttributes( { fieldType: v } ) }
					/>
					<TextControl
						label={ __( 'Label', 'pediment' ) }
						value={ label }
						onChange={ ( v ) => setAttributes( { label: v } ) }
					/>
					<TextControl
						label={ __( 'Field name', 'pediment' ) }
						help={ __( 'Data key. Auto-filled from the label.', 'pediment' ) }
						value={ fieldName }
						onChange={ ( v ) => setAttributes( { fieldName: slug( v ) } ) }
					/>
					<ToggleControl
						label={ __( 'Required', 'pediment' ) }
						checked={ required }
						onChange={ ( v ) => setAttributes( { required: v } ) }
					/>
					<TextControl
						label={ __( 'Placeholder', 'pediment' ) }
						value={ placeholder }
						onChange={ ( v ) => setAttributes( { placeholder: v } ) }
					/>
					<TextControl
						label={ __( 'Help text', 'pediment' ) }
						value={ helpText }
						onChange={ ( v ) => setAttributes( { helpText: v } ) }
					/>
					{ needsOptions && (
						<TextareaControl
							label={ __(
								'Options (one per line, "Label|value" optional)',
								'pediment'
							) }
							value={ optionsText }
							onChange={ ( v ) =>
								setAttributes( { options: parseOptions( v ) } )
							}
						/>
					) }
				</PanelBody>
			</InspectorControls>
			<label className="pediment-form__label">
				<span>
					{ label || __( 'Untitled field', 'pediment' ) }
					{ required ? ' *' : '' }
				</span>
				{ renderPreview( fieldType, placeholder, options ) }
			</label>
			{ helpText && <small className="pediment-form__help">{ helpText }</small> }
		</div>
	);
}

function renderPreview( type: string, placeholder: string, options: Option[] ) {
	if ( type === 'textarea' ) {
		return <textarea rows={ 4 } placeholder={ placeholder } readOnly />;
	}
	if ( type === 'select' ) {
		return (
			<select disabled>
				{ options.map( ( o ) => (
					<option key={ o.value }>{ o.label }</option>
				) ) }
			</select>
		);
	}
	if ( type === 'checkbox' ) {
		return <input type="checkbox" disabled />;
	}
	if ( type === 'radio' ) {
		return (
			<span>
				{ options.map( ( o ) => (
					<label key={ o.value }>
						<input type="radio" disabled /> { o.label }
					</label>
				) ) }
			</span>
		);
	}
	return <input type={ type } placeholder={ placeholder } readOnly />;
}
```

`src/blocks/form-field/render.php`:

```php
<?php
/**
 * Server-side render for pediment/form-field.
 *
 * @var array $attributes
 */

$pediment_type  = isset( $attributes['fieldType'] ) ? (string) $attributes['fieldType'] : 'text';
$pediment_label = isset( $attributes['label'] ) ? (string) $attributes['label'] : '';
$pediment_name  = isset( $attributes['fieldName'] ) && '' !== $attributes['fieldName']
	? pediment_form_slug( (string) $attributes['fieldName'] )
	: pediment_form_slug( $pediment_label );
$pediment_req   = ! empty( $attributes['required'] );
$pediment_ph    = isset( $attributes['placeholder'] ) ? (string) $attributes['placeholder'] : '';
$pediment_help  = isset( $attributes['helpText'] ) ? (string) $attributes['helpText'] : '';
$pediment_opts  = isset( $attributes['options'] ) && is_array( $attributes['options'] ) ? $attributes['options'] : array();

$pediment_req_attr = $pediment_req ? ' required' : '';
$pediment_ph_attr  = '' !== $pediment_ph ? ' placeholder="' . esc_attr( $pediment_ph ) . '"' : '';

ob_start();
?>
<label class="pediment-form__field">
	<span class="pediment-form__label"><?php echo esc_html( '' !== $pediment_label ? $pediment_label : $pediment_name ); ?><?php echo $pediment_req ? ' <span aria-hidden="true">*</span>' : ''; // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
	<?php
	switch ( $pediment_type ) :
		case 'textarea':
			?>
			<textarea data-pediment-field name="<?php echo esc_attr( $pediment_name ); ?>" rows="5"<?php echo $pediment_req_attr . $pediment_ph_attr; // phpcs:ignore WordPress.Security.EscapeOutput ?>></textarea>
			<?php
			break;
		case 'select':
			?>
			<select data-pediment-field name="<?php echo esc_attr( $pediment_name ); ?>"<?php echo $pediment_req_attr; // phpcs:ignore WordPress.Security.EscapeOutput ?>>
				<option value=""><?php esc_html_e( 'Choose…', 'pediment' ); ?></option>
				<?php foreach ( $pediment_opts as $pediment_opt ) : ?>
					<option value="<?php echo esc_attr( (string) ( $pediment_opt['value'] ?? '' ) ); ?>"><?php echo esc_html( (string) ( $pediment_opt['label'] ?? '' ) ); ?></option>
				<?php endforeach; ?>
			</select>
			<?php
			break;
		case 'checkbox':
			?>
			<input data-pediment-field type="checkbox" name="<?php echo esc_attr( $pediment_name ); ?>" value="1"<?php echo $pediment_req_attr; // phpcs:ignore WordPress.Security.EscapeOutput ?> />
			<?php
			break;
		case 'radio':
			foreach ( $pediment_opts as $pediment_opt ) :
				?>
				<label class="pediment-form__radio"><input data-pediment-field type="radio" name="<?php echo esc_attr( $pediment_name ); ?>" value="<?php echo esc_attr( (string) ( $pediment_opt['value'] ?? '' ) ); ?>"<?php echo $pediment_req_attr; // phpcs:ignore WordPress.Security.EscapeOutput ?> /> <?php echo esc_html( (string) ( $pediment_opt['label'] ?? '' ) ); ?></label>
				<?php
			endforeach;
			break;
		default:
			?>
			<input data-pediment-field type="<?php echo esc_attr( $pediment_type ); ?>" name="<?php echo esc_attr( $pediment_name ); ?>"<?php echo $pediment_req_attr . $pediment_ph_attr; // phpcs:ignore WordPress.Security.EscapeOutput ?> />
			<?php
	endswitch;
	?>
	<?php if ( '' !== $pediment_help ) : ?>
		<small class="pediment-form__help"><?php echo esc_html( $pediment_help ); ?></small>
	<?php endif; ?>
</label>
<?php
echo ob_get_clean();
```

`src/blocks/form-field/style.scss`:

```scss
.pediment-form__field {
	display: grid;
	gap: var( --wp--preset--spacing--20, 0.5rem );
}

.pediment-form__field-error {
	font-weight: 600;
}
```

- [ ] **Step 2: Write the failing render test**

```php
// tests/phpunit/BlockRender/FormFieldTest.php
<?php

class FormFieldTest extends WP_UnitTestCase {
	public function test_text_field_renders_named_input() {
		$html = do_blocks( '<!-- wp:pediment/form-field {"label":"Full Name","fieldName":"name","required":true} /-->' );
		$this->assertStringContainsString( 'name="name"', $html );
		$this->assertStringContainsString( 'data-pediment-field', $html );
		$this->assertStringContainsString( 'required', $html );
		$this->assertStringContainsString( 'Full Name', $html );
	}

	public function test_select_renders_options() {
		$html = do_blocks( '<!-- wp:pediment/form-field {"fieldType":"select","label":"Plan","fieldName":"plan","options":[{"label":"Pro","value":"pro"}]} /-->' );
		$this->assertStringContainsString( '<select', $html );
		$this->assertStringContainsString( 'value="pro"', $html );
		$this->assertStringContainsString( 'Pro', $html );
	}
}
```

- [ ] **Step 3: Build and run the test (verify fail → pass)**

Run: `npm run build && wp-env run tests-cli --env-cwd=wp-content/themes/pediment vendor/bin/phpunit --filter FormFieldTest`
Expected: PASS (2 tests). (Before `npm run build` the block is unregistered and the test FAILs — build registers it.)

- [ ] **Step 4: Lint**

Run: `npm run lint:colors && composer run lint`
Expected: both pass with no errors/warnings.

- [ ] **Step 5: Commit**

```bash
git add src/blocks/form-field tests/phpunit/BlockRender/FormFieldTest.php
git commit -m "feat: pediment/form-field block"
```

---

### Task 6: `pediment/form` block

**Files:**
- Create: `src/blocks/form/block.json`, `index.tsx`, `edit.tsx`, `render.php`, `view.js`, `style.scss`
- Test: `tests/phpunit/BlockRender/FormTest.php`

**Interfaces:**
- Consumes: `pediment_form_collect_fields()`, `pediment_form_form_key()`, `PEDIMENT_FORM_NAMESPACE`, `PEDIMENT_FORM_ROUTE` (Tasks 1–2).
- Produces: a dynamic `pediment/form` block rendering a `<form class="pediment-form">` that wraps its field children, emits the honeypot + `_t` + a `data-form-key`/`data-post-id`/`data-rest-url`, and a `view.js` that posts the submission.

- [ ] **Step 1: Write the block source**

`src/blocks/form/block.json`:

```json
{
	"$schema": "https://schemas.wp.org/trunk/block.json",
	"apiVersion": 3,
	"name": "pediment/form",
	"title": "Form",
	"category": "pediment",
	"description": "A form that collects visitor input and delivers it to a configured destination. Add Form Field child blocks for each input. Use for contact, booking, registration, feedback, or signup forms.",
	"textdomain": "pediment",
	"supports": { "html": false, "align": [ "wide" ] },
	"attributes": {
		"destination": { "type": "string", "default": "" },
		"successMessage": { "type": "string", "default": "Thanks — we'll be in touch shortly." },
		"submitLabel": { "type": "string", "default": "Send" }
	},
	"example": {
		"attributes": { "successMessage": "Thanks — we'll be in touch shortly." },
		"innerBlocks": [
			{ "name": "pediment/form-field", "attributes": { "label": "Name", "fieldName": "name", "required": true } },
			{ "name": "pediment/form-field", "attributes": { "fieldType": "email", "label": "Email", "fieldName": "email", "required": true } }
		]
	},
	"editorScript": "file:./index.js",
	"editorStyle": "file:./style-index.css",
	"style": "file:./style-index.css",
	"viewScript": "file:./view.js",
	"render": "file:./render.php"
}
```

`src/blocks/form/index.tsx`:

```tsx
import { registerBlockType } from '@wordpress/blocks';
import metadata from './block.json';
import Edit from './edit';
import './style.scss';

registerBlockType( metadata.name, { edit: Edit } );
```

`src/blocks/form/edit.tsx`:

```tsx
import { __ } from '@wordpress/i18n';
import {
	useBlockProps,
	InnerBlocks,
	InspectorControls,
} from '@wordpress/block-editor';
import { PanelBody, TextControl, TextareaControl } from '@wordpress/components';

const ALLOWED = [ 'pediment/form-field' ];
const TEMPLATE: Array< [ string, Record< string, unknown > ] > = [
	[ 'pediment/form-field', { label: 'Name', fieldName: 'name', required: true } ],
	[
		'pediment/form-field',
		{ fieldType: 'email', label: 'Email', fieldName: 'email', required: true },
	],
	[ 'pediment/form-field', { fieldType: 'textarea', label: 'Message', fieldName: 'message' } ],
];

type Attrs = { destination: string; successMessage: string; submitLabel: string };

export default function Edit( {
	attributes,
	setAttributes,
}: {
	attributes: Attrs;
	setAttributes: ( a: Partial< Attrs > ) => void;
} ) {
	const blockProps = useBlockProps( { className: 'pediment-form' } );
	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Form settings', 'pediment' ) }>
					<TextControl
						label={ __( 'Destination id', 'pediment' ) }
						help={ __(
							'Configured in Settings → Forms. Leave empty for the default.',
							'pediment'
						) }
						value={ attributes.destination }
						onChange={ ( v ) => setAttributes( { destination: v } ) }
					/>
					<TextControl
						label={ __( 'Submit button label', 'pediment' ) }
						value={ attributes.submitLabel }
						onChange={ ( v ) => setAttributes( { submitLabel: v } ) }
					/>
					<TextareaControl
						label={ __( 'Success message', 'pediment' ) }
						value={ attributes.successMessage }
						onChange={ ( v ) => setAttributes( { successMessage: v } ) }
					/>
				</PanelBody>
			</InspectorControls>
			<form { ...blockProps } onSubmit={ ( e ) => e.preventDefault() }>
				<InnerBlocks allowedBlocks={ ALLOWED } template={ TEMPLATE } />
				<button type="button" className="pediment-form__submit">
					{ attributes.submitLabel || __( 'Send', 'pediment' ) }
				</button>
			</form>
		</>
	);
}
```

`src/blocks/form/render.php`:

```php
<?php
/**
 * Server-side render for pediment/form.
 *
 * @var array    $attributes
 * @var string   $content
 * @var WP_Block $block
 */

$pediment_success = isset( $attributes['successMessage'] ) ? (string) $attributes['successMessage'] : '';
$pediment_submit  = isset( $attributes['submitLabel'] ) && '' !== $attributes['submitLabel']
	? (string) $attributes['submitLabel']
	: __( 'Send', 'pediment' );

$pediment_inner = isset( $block->parsed_block['innerBlocks'] ) && is_array( $block->parsed_block['innerBlocks'] )
	? $block->parsed_block['innerBlocks']
	: array();
$pediment_fields   = pediment_form_collect_fields( $pediment_inner );
$pediment_form_key = pediment_form_form_key( $pediment_fields );
$pediment_post_id  = (int) get_the_ID();

$pediment_wrapper = get_block_wrapper_attributes(
	array(
		'class'         => 'pediment-form',
		'data-success'  => $pediment_success,
		'data-rest-url' => esc_url_raw( rest_url( PEDIMENT_FORM_NAMESPACE . PEDIMENT_FORM_ROUTE ) ),
		'data-post-id'  => (string) $pediment_post_id,
		'data-form-key' => $pediment_form_key,
	)
);

$pediment_timestamp = time();

ob_start();
?>
<form <?php echo $pediment_wrapper; // phpcs:ignore WordPress.Security.EscapeOutput ?>>
	<?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput -- inner field blocks escape their own output. ?>

	<div class="pediment-form__hp" aria-hidden="true">
		<label>Leave this empty <input type="text" name="hp_field" tabindex="-1" autocomplete="off" /></label>
	</div>
	<input type="hidden" name="_t" value="<?php echo esc_attr( (string) $pediment_timestamp ); ?>" />

	<button type="submit" class="pediment-form__submit"><?php echo esc_html( $pediment_submit ); ?></button>

	<p class="pediment-form__status" role="status" hidden></p>
</form>
<?php
echo ob_get_clean();
```

`src/blocks/form/view.js`:

```js
( function () {
	'use strict';

	function init() {
		document.querySelectorAll( 'form.pediment-form' ).forEach( bindForm );
	}

	function bindForm( form ) {
		var restUrl = form.getAttribute( 'data-rest-url' );
		var postId = form.getAttribute( 'data-post-id' );
		var formKey = form.getAttribute( 'data-form-key' );
		var successMsg =
			form.getAttribute( 'data-success' ) || 'Thanks — we will be in touch.';
		var statusEl = form.querySelector( '.pediment-form__status' );
		var submitBtn = form.querySelector( '.pediment-form__submit' );

		form.addEventListener( 'submit', function ( e ) {
			e.preventDefault();
			if ( ! restUrl ) {
				return;
			}

			var payload = {
				post_id: postId ? parseInt( postId, 10 ) : 0,
				form_key: formKey || '',
				hp_field: valueOf( form, 'hp_field' ),
				_t: valueOf( form, '_t' ),
				fields: collectFields( form ),
			};

			submitBtn.disabled = true;
			clearErrors( form );
			showStatus( statusEl, '', null );

			fetch( restUrl, {
				method: 'POST',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify( payload ),
			} )
				.then( function ( res ) {
					return res.json().then( function ( body ) {
						return { res: res, body: body };
					} );
				} )
				.then( function ( out ) {
					submitBtn.disabled = false;
					if ( out.res.ok && out.body && out.body.ok ) {
						form.querySelectorAll( 'input,textarea,select,button' ).forEach(
							function ( el ) {
								el.disabled = true;
							}
						);
						showStatus( statusEl, successMsg, 'success' );
						return;
					}
					var data = out.body && out.body.data ? out.body.data : null;
					if ( data && data.errors ) {
						applyErrors( form, data.errors );
					}
					var msg =
						out.body && out.body.message
							? out.body.message
							: 'Something went wrong. Please try again.';
					showStatus( statusEl, msg, 'error' );
				} )
				.catch( function () {
					submitBtn.disabled = false;
					showStatus( statusEl, 'Network error. Please try again.', 'error' );
				} );
		} );
	}

	function collectFields( form ) {
		var out = {};
		form.querySelectorAll( '[data-pediment-field]' ).forEach( function ( el ) {
			var name = el.getAttribute( 'name' );
			if ( ! name ) {
				return;
			}
			if ( el.type === 'checkbox' ) {
				out[ name ] = el.checked ? el.value || '1' : '';
				return;
			}
			if ( el.type === 'radio' ) {
				if ( el.checked ) {
					out[ name ] = el.value;
				} else if ( ! ( name in out ) ) {
					out[ name ] = '';
				}
				return;
			}
			out[ name ] = el.value;
		} );
		return out;
	}

	function valueOf( form, name ) {
		var el = form.querySelector( '[name="' + name + '"]' );
		return el ? el.value : '';
	}

	function clearErrors( form ) {
		form.querySelectorAll( '.pediment-form__field-error' ).forEach( function ( el ) {
			el.remove();
		} );
	}

	function applyErrors( form, errors ) {
		Object.keys( errors ).forEach( function ( name ) {
			var field = form.querySelector( '[name="' + name + '"]' );
			if ( ! field ) {
				return;
			}
			var wrap = field.closest( '.pediment-form__field' ) || field.parentNode;
			var err = document.createElement( 'small' );
			err.className = 'pediment-form__field-error';
			err.textContent = errors[ name ];
			wrap.appendChild( err );
		} );
	}

	function showStatus( el, msg, state ) {
		if ( ! el ) {
			return;
		}
		if ( ! msg ) {
			el.hidden = true;
			el.textContent = '';
			el.removeAttribute( 'data-state' );
			return;
		}
		el.hidden = false;
		el.textContent = msg;
		if ( state ) {
			el.setAttribute( 'data-state', state );
		} else {
			el.removeAttribute( 'data-state' );
		}
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
```

`src/blocks/form/style.scss`:

```scss
.pediment-form {
	display: grid;
	gap: var( --wp--preset--spacing--30, 1rem );
}

.pediment-form__hp {
	position: absolute;
	left: -9999px;
}

.pediment-form__status[ data-state='error' ] {
	font-weight: 600;
}
```

- [ ] **Step 2: Write the failing render test**

```php
// tests/phpunit/BlockRender/FormTest.php
<?php

class FormTest extends WP_UnitTestCase {
	private function markup(): string {
		return '<!-- wp:pediment/form {"successMessage":"Thanks!"} -->'
			. '<!-- wp:pediment/form-field {"label":"Name","fieldName":"name","required":true} /-->'
			. '<!-- wp:pediment/form-field {"fieldType":"email","label":"Email","fieldName":"email","required":true} /-->'
			. '<!-- /wp:pediment/form -->';
	}

	public function test_form_wraps_fields_and_emits_metadata() {
		$html = do_blocks( $this->markup() );

		$this->assertStringContainsString( 'wp-block-pediment-form', $html );
		$this->assertStringContainsString( 'pediment-form', $html );
		$this->assertStringContainsString( 'data-form-key="', $html );
		$this->assertStringContainsString( 'data-rest-url="', $html );
		$this->assertStringContainsString( 'name="name"', $html );
		$this->assertStringContainsString( 'name="email"', $html );
		$this->assertStringContainsString( 'name="hp_field"', $html );
		$this->assertStringContainsString( 'name="_t"', $html );
	}

	public function test_form_key_matches_server_derivation() {
		$inner = parse_blocks( $this->markup() )[0]['innerBlocks'];
		$key   = pediment_form_form_key( pediment_form_collect_fields( $inner ) );
		$this->assertStringContainsString( 'data-form-key="' . $key . '"', do_blocks( $this->markup() ) );
	}
}
```

- [ ] **Step 3: Build and run (verify pass)**

Run: `npm run build && wp-env run tests-cli --env-cwd=wp-content/themes/pediment vendor/bin/phpunit --filter FormTest`
Expected: PASS (2 tests).

- [ ] **Step 4: Lint**

Run: `npm run lint:colors && npm run lint:js && composer run lint`
Expected: all pass.

- [ ] **Step 5: Commit**

```bash
git add src/blocks/form tests/phpunit/BlockRender/FormTest.php
git commit -m "feat: pediment/form block with server-authoritative submission wiring"
```

---

### Task 7: End-to-end submission flow

**Files:**
- Create: `tests/e2e/forms.spec.ts`

**Interfaces:**
- Consumes: `createPageWithContent`, `deletePageBySlug`, `login` from `tests/e2e/utils.ts`; the live `pediment/form` block + REST endpoint + storage from Tasks 1–6.

- [ ] **Step 1: Write the e2e spec**

```ts
// tests/e2e/forms.spec.ts
import { test, expect } from '@playwright/test';
import { login, createPageWithContent, deletePageBySlug } from './utils';

const SLUG = 'e2e-form';

const FORM = `<!-- wp:pediment/form {"successMessage":"Thanks, got it."} -->
<!-- wp:pediment/form-field {"label":"Name","fieldName":"name","required":true} /-->
<!-- wp:pediment/form-field {"fieldType":"email","label":"Email","fieldName":"email","required":true} /-->
<!-- wp:pediment/form-field {"fieldType":"textarea","label":"Message","fieldName":"message"} /-->
<!-- /wp:pediment/form -->`;

test( 'generic form submits, shows success, and stores a submission', async ( { page } ) => {
	test.slow();
	deletePageBySlug( SLUG );
	const url = createPageWithContent( SLUG, 'Form test', FORM );

	await page.goto( url );
	await page.fill( 'input[name="name"]', 'Alice E2E' );
	await page.fill( 'input[name="email"]', 'alice-form-e2e@example.com' );
	await page.fill( 'textarea[name="message"]', 'Hello from Playwright.' );

	// Beat the time-trap (PEDIMENT_FORM_MIN_AGE seconds).
	await page.waitForTimeout( 4000 );
	await page.click( 'button.pediment-form__submit' );

	await expect( page.locator( '.pediment-form__status' ) ).toContainText( /thanks/i, {
		timeout: 10_000,
	} );

	await login( page );
	await page.goto( '/wp-admin/edit.php?post_type=form_submission' );
	await expect(
		page.locator( 'text=alice-form-e2e@example.com' ).first()
	).toBeVisible();

	deletePageBySlug( SLUG );
} );

test( 'required validation blocks an empty submit', async ( { page } ) => {
	test.slow();
	const slug = 'e2e-form-required';
	deletePageBySlug( slug );
	const url = createPageWithContent( slug, 'Form required test', FORM );

	await page.goto( url );
	await page.fill( 'input[name="name"]', 'Bob' );
	// Leave required email empty.
	await page.waitForTimeout( 4000 );
	await page.click( 'button.pediment-form__submit' );

	await expect( page.locator( '.pediment-form__status' ) ).toContainText(
		/validation failed|required/i
	);

	deletePageBySlug( slug );
} );
```

- [ ] **Step 2: Run the e2e (verify pass)**

Run: `npm run build && npm run e2e -- forms.spec.ts`
Expected: PASS (2 tests). (Requires wp-env running: `npm run env:start`.)

- [ ] **Step 3: Full suite + lint gate**

Run: `composer run lint && npm run lint:colors && npm run lint:js && wp-env run tests-cli --env-cwd=wp-content/themes/pediment vendor/bin/phpunit`
Expected: all green.

- [ ] **Step 4: Commit**

```bash
git add tests/e2e/forms.spec.ts
git commit -m "test: e2e generic form submission and validation"
```

---

## Plan 1 → Plan 2 handoff

At the end of Plan 1 the `destination` attribute is captured and stored but does nothing. Plan 2 hooks `pediment_form_submitted` for delivery, adds the destination registry + secret store + templating + the **Settings → Forms** admin UI, and wires the retention-days setting into the `pediment_form_retention_days` filter.
