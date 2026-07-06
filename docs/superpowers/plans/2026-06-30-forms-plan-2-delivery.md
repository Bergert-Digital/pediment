# Forms — Plan 2: Delivery Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Deliver stored `form_submission` records to admin-defined, templated HTTP destinations (email providers, webhooks, Slack) — with secrets encrypted at rest, structural token templating, an HTTPS/SSRF guard, provider presets, a full **Settings → Forms** admin UI, and per-submission delivery status + retry.

**Architecture:** Plan 1 already stores every submission to the `form_submission` CPT with `_delivery_status = pending` and fires `pediment_form_submitted`. Plan 2 makes `pediment_form_persist_submission` fire a new `pediment_form_stored` action carrying the **stored submission post id**; a delivery module hooks that, resolves the submission's destination by id, renders a templated `wp_remote_request` from the stored field values, sends it, and records `sent` / `failed` / `no_destination` (plus HTTP status + a response snippet) back onto the submission. A destination is a pure data record (method, url, headers, content-type, body template) referencing credentials only as `{{ secret:NAME }}` tokens; real values live in a separate Sodium-encrypted option. Everything is HTTP-only — there is no `wp_mail` channel.

**Tech Stack:** WordPress block theme (PHP 8.1+, procedural `inc/*.php`), PHPUnit (wp-env tests harness), `wp_remote_request`, libsodium (`sodium_crypto_secretbox`).

This is the second of four sequential plans (see `docs/superpowers/specs/2026-06-29-ai-generatable-forms-design.md`). Plan 1 shipped capture + storage. **This plan does not touch `pediment-ai`** — the AI-assisted destination builder and the `pediment-ai/v1/draft-destination` endpoint are Plan 3; contact-form migration is Plan 4. Plan 2's Settings UI is hand-authoring only; Plan 3 layers AI drafting on top of the exact same `pediment_form_sanitize_destination()` / `pediment_form_validate_destination()` gates.

## Global Constraints

- PHP `>= 8.1`; match existing WPCS style (Yoda conditions, full escaping, `/* translators */` comments, tabs for indent). `composer run lint` (phpcs) must pass with **zero warnings** — CI fails on warnings.
- `npm run lint:colors` must pass — **no color literals** in SCSS; use `var(--wp--preset--…)` / inherit only. (Plan 2 adds no SCSS, but the gate still runs.)
- Text domain is `pediment`.
- New `inc/*.php` files must be `require_once`d from `functions.php` and start with the `if ( ! defined( 'ABSPATH' ) ) { exit; }` guard.
- All admin write paths are `manage_options` + nonce-protected. Destinations and secrets are **never** exposed via REST or the front end.
- Delivery is **HTTPS-only**. Every outbound URL passes `pediment_form_url_is_safe()` at both save time and send time.
- Credential **values** never live on a destination record, never appear in block content, post meta, or any prompt — only `{{ secret:NAME }}` references.
- Reuse Plan 1 constants from `inc/forms.php`: `PEDIMENT_FORM_CPT`. Do not redefine them.
- Run a single test class with:
  `wp-env run tests-cli --env-cwd=wp-content/themes/pediment vendor/bin/phpunit --filter <ClassName>`
- Run the full PHP suite + linters before the final commit:
  `wp-env run tests-cli --env-cwd=wp-content/themes/pediment vendor/bin/phpunit && npm run lint:colors && composer run lint`

---

### Task 1: Encrypted secret store

Credential values referenced by destinations as `{{ secret:NAME }}`. Stored in option `pediment_form_secrets` as `name => base64(nonce.ciphertext)`, encrypted with a `wp_salt('auth')`-derived key — mirrors `pediment-ai`'s `OptionsStore` (graceful fallback to base64 when libsodium is unavailable, so tests pass on any PHP build).

**Files:**
- Create: `inc/forms-secrets.php`
- Modify: `functions.php` (add `require_once` after the `inc/forms-storage.php` line)
- Test: `tests/phpunit/Forms/SecretsTest.php`

**Interfaces:**
- Produces:
  - `const PEDIMENT_FORM_SECRETS_OPTION = 'pediment_form_secrets';`
  - `pediment_form_secret_set( string $name, string $plain ): void` — empty `$plain` deletes the entry.
  - `pediment_form_secret_get( string $name ): string` — `''` when absent.
  - `pediment_form_secret_names(): array<int,string>` — sorted list of stored secret names.

- [ ] **Step 1: Write the failing test**

```php
// tests/phpunit/Forms/SecretsTest.php
<?php

class SecretsTest extends WP_UnitTestCase {
	public function tear_down(): void {
		delete_option( PEDIMENT_FORM_SECRETS_OPTION );
		parent::tear_down();
	}

	public function test_set_and_get_roundtrips() {
		pediment_form_secret_set( 'brevo_api_key', 'xkeysib-secret-123' );
		$this->assertSame( 'xkeysib-secret-123', pediment_form_secret_get( 'brevo_api_key' ) );
	}

	public function test_value_is_not_stored_in_plaintext() {
		pediment_form_secret_set( 'brevo_api_key', 'xkeysib-secret-123' );
		$raw = get_option( PEDIMENT_FORM_SECRETS_OPTION );
		$this->assertArrayHasKey( 'brevo_api_key', $raw );
		$this->assertStringNotContainsString( 'xkeysib-secret-123', (string) wp_json_encode( $raw ) );
	}

	public function test_empty_value_deletes_entry() {
		pediment_form_secret_set( 'tmp', 'value' );
		pediment_form_secret_set( 'tmp', '' );
		$this->assertSame( '', pediment_form_secret_get( 'tmp' ) );
		$this->assertNotContains( 'tmp', pediment_form_secret_names() );
	}

	public function test_names_are_sanitized_and_sorted() {
		pediment_form_secret_set( 'Zeta Key!', 'a' );
		pediment_form_secret_set( 'alpha', 'b' );
		$this->assertSame( array( 'alpha', 'zeta_key' ), pediment_form_secret_names() );
	}

	public function test_missing_secret_returns_empty_string() {
		$this->assertSame( '', pediment_form_secret_get( 'nope' ) );
	}
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `wp-env run tests-cli --env-cwd=wp-content/themes/pediment vendor/bin/phpunit --filter SecretsTest`
Expected: FAIL — `Error: Call to undefined function pediment_form_secret_set()`.

- [ ] **Step 3: Write the implementation**

```php
// inc/forms-secrets.php
<?php
/**
 * Encrypted secret store for form destinations.
 *
 * Credential values referenced by destinations as {{ secret:NAME }} live here,
 * encrypted at rest with a wp_salt-derived key (mirrors pediment-ai OptionsStore).
 *
 * @package Pediment
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const PEDIMENT_FORM_SECRETS_OPTION = 'pediment_form_secrets';

/**
 * Derive the symmetric cipher key from the site's auth salt.
 */
function pediment_form_secret_cipher_key(): string {
	return substr( hash( 'sha256', wp_salt( 'auth' ) . '|pediment-form-secrets', true ), 0, SODIUM_CRYPTO_SECRETBOX_KEYBYTES );
}

/**
 * Encrypt a plaintext secret; base64-encodes nonce.ciphertext.
 *
 * @param string $plain Plaintext value.
 */
function pediment_form_secret_encrypt( string $plain ): string {
	if ( ! function_exists( 'sodium_crypto_secretbox' ) ) {
		return base64_encode( $plain );
	}
	$nonce = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
	$ct    = sodium_crypto_secretbox( $plain, $nonce, pediment_form_secret_cipher_key() );
	return base64_encode( $nonce . $ct );
}

/**
 * Decrypt a stored secret blob.
 *
 * @param string $blob base64(nonce.ciphertext).
 */
function pediment_form_secret_decrypt( string $blob ): string {
	$raw = base64_decode( $blob, true );
	if ( false === $raw ) {
		return '';
	}
	if ( ! function_exists( 'sodium_crypto_secretbox_open' ) ) {
		return $raw;
	}
	$nonce = substr( $raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
	$ct    = substr( $raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
	$plain = sodium_crypto_secretbox_open( $ct, $nonce, pediment_form_secret_cipher_key() );
	return false === $plain ? '' : (string) $plain;
}

/**
 * Store (or delete, when $plain is empty) a named secret.
 *
 * @param string $name  Secret name.
 * @param string $plain Plaintext value; '' removes the secret.
 */
function pediment_form_secret_set( string $name, string $plain ): void {
	$name = sanitize_key( $name );
	if ( '' === $name ) {
		return;
	}
	$all = get_option( PEDIMENT_FORM_SECRETS_OPTION, array() );
	$all = is_array( $all ) ? $all : array();
	if ( '' === $plain ) {
		unset( $all[ $name ] );
	} else {
		$all[ $name ] = pediment_form_secret_encrypt( $plain );
	}
	update_option( PEDIMENT_FORM_SECRETS_OPTION, $all );
}

/**
 * Retrieve a decrypted secret value.
 *
 * @param string $name Secret name.
 */
function pediment_form_secret_get( string $name ): string {
	$name = sanitize_key( $name );
	$all  = get_option( PEDIMENT_FORM_SECRETS_OPTION, array() );
	if ( ! is_array( $all ) || ! isset( $all[ $name ] ) ) {
		return '';
	}
	return pediment_form_secret_decrypt( (string) $all[ $name ] );
}

/**
 * Sorted list of stored secret names.
 *
 * @return array<int,string>
 */
function pediment_form_secret_names(): array {
	$all   = get_option( PEDIMENT_FORM_SECRETS_OPTION, array() );
	$names = is_array( $all ) ? array_keys( $all ) : array();
	sort( $names );
	return array_map( 'strval', $names );
}
```

Add to `functions.php` immediately after the `require_once __DIR__ . '/inc/forms-storage.php';` line:

```php
require_once __DIR__ . '/inc/forms-secrets.php';
```

- [ ] **Step 4: Run test to verify it passes**

Run: `wp-env run tests-cli --env-cwd=wp-content/themes/pediment vendor/bin/phpunit --filter SecretsTest`
Expected: PASS (5 tests).

- [ ] **Step 5: Commit**

```bash
git add inc/forms-secrets.php functions.php tests/phpunit/Forms/SecretsTest.php
git commit -m "feat: encrypted secret store for form destinations"
```

---

### Task 2: HTTPS + SSRF guard

A single predicate every outbound URL must pass: HTTPS scheme, and every IP the host resolves to must be public. Uses PHP's `FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE` which already rejects loopback (`127/8`), RFC1918 (`10/8`, `172.16/12`, `192.168/16`), link-local + cloud-metadata (`169.254/16`, incl. `169.254.169.254`), and IPv6 equivalents. Hostnames are resolved via `gethostbynamel()`; an admin can bypass with the `pediment_form_allowed_hosts` filter.

**Files:**
- Create: `inc/forms-ssrf.php`
- Modify: `functions.php` (add `require_once`)
- Test: `tests/phpunit/Forms/SsrfTest.php`

**Interfaces:**
- Produces:
  - `pediment_form_ip_is_public( string $ip ): bool`
  - `pediment_form_url_is_safe( string $url ): bool`
- Consumes filter: `pediment_form_allowed_hosts` (array of hostnames allowed to bypass the IP check).

- [ ] **Step 1: Write the failing test**

```php
// tests/phpunit/Forms/SsrfTest.php
<?php

class SsrfTest extends WP_UnitTestCase {
	public function test_public_https_literal_ip_is_safe() {
		$this->assertTrue( pediment_form_url_is_safe( 'https://93.184.216.34/v3/send' ) );
	}

	public function test_non_https_is_rejected() {
		$this->assertFalse( pediment_form_url_is_safe( 'http://93.184.216.34/' ) );
	}

	/**
	 * @dataProvider private_urls
	 */
	public function test_private_and_reserved_ranges_are_rejected( string $url ) {
		$this->assertFalse( pediment_form_url_is_safe( $url ) );
	}

	public function private_urls(): array {
		return array(
			'loopback'   => array( 'https://127.0.0.1/' ),
			'rfc1918_10' => array( 'https://10.0.0.5/' ),
			'rfc1918_192'=> array( 'https://192.168.1.1/' ),
			'link_local' => array( 'https://169.254.169.254/latest/meta-data/' ),
			'ipv6_local' => array( 'https://[::1]/' ),
		);
	}

	public function test_garbage_url_is_rejected() {
		$this->assertFalse( pediment_form_url_is_safe( 'not a url' ) );
		$this->assertFalse( pediment_form_url_is_safe( 'https://' ) );
	}

	public function test_allowed_hosts_filter_bypasses_ip_check() {
		add_filter( 'pediment_form_allowed_hosts', fn() => array( 'internal.test' ) );
		$this->assertTrue( pediment_form_url_is_safe( 'https://internal.test/hook' ) );
		remove_all_filters( 'pediment_form_allowed_hosts' );
	}
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `wp-env run tests-cli --env-cwd=wp-content/themes/pediment vendor/bin/phpunit --filter SsrfTest`
Expected: FAIL — undefined function `pediment_form_url_is_safe()`.

- [ ] **Step 3: Write the implementation**

```php
// inc/forms-ssrf.php
<?php
/**
 * HTTPS + SSRF guard for outbound destination requests.
 *
 * @package Pediment
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * True when $ip is a routable public address (not private/reserved/loopback).
 *
 * @param string $ip IPv4 or IPv6 literal.
 */
function pediment_form_ip_is_public( string $ip ): bool {
	return (bool) filter_var(
		$ip,
		FILTER_VALIDATE_IP,
		FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
	);
}

/**
 * Gate every outbound destination URL: HTTPS only, and every resolved IP public.
 *
 * @param string $url Candidate URL.
 */
function pediment_form_url_is_safe( string $url ): bool {
	$parts = wp_parse_url( $url );
	if ( ! is_array( $parts ) || empty( $parts['scheme'] ) || empty( $parts['host'] ) ) {
		return false;
	}
	if ( 'https' !== strtolower( (string) $parts['scheme'] ) ) {
		return false;
	}

	$host = trim( (string) $parts['host'], '[]' );

	$allowed = (array) apply_filters( 'pediment_form_allowed_hosts', array() );
	if ( in_array( $host, $allowed, true ) ) {
		return true;
	}

	$ips = array();
	if ( filter_var( $host, FILTER_VALIDATE_IP ) ) {
		$ips[] = $host;
	} else {
		$resolved = gethostbynamel( $host );
		$ips      = is_array( $resolved ) ? $resolved : array();
		if ( empty( $ips ) ) {
			// Cannot prove the host is public — reject.
			return false;
		}
	}

	foreach ( $ips as $ip ) {
		if ( ! pediment_form_ip_is_public( (string) $ip ) ) {
			return false;
		}
	}
	return true;
}
```

Add to `functions.php` after the secrets require:

```php
require_once __DIR__ . '/inc/forms-ssrf.php';
```

- [ ] **Step 4: Run test to verify it passes**

Run: `wp-env run tests-cli --env-cwd=wp-content/themes/pediment vendor/bin/phpunit --filter SsrfTest`
Expected: PASS (the IPv6 `[::1]` literal and all private literals reject; the public literal `93.184.216.34` passes without DNS).

- [ ] **Step 5: Commit**

```bash
git add inc/forms-ssrf.php functions.php tests/phpunit/Forms/SsrfTest.php
git commit -m "feat: HTTPS + SSRF guard for form delivery URLs"
```

---

### Task 3: Token templating

Resolve `{{ field:NAME }}`, `{{ all_fields }}`, `{{ meta:KEY }}`, `{{ secret:NAME }}` tokens into a request body / header / url with **structural, escaped** interpolation: in a JSON body, scalar tokens are JSON-string-escaped so a value containing `"` or a newline cannot break out; `{{ all_fields }}` (written as the JSON value `"{{ all_fields }}"`) becomes a JSON object. Header values are CRLF-stripped. URL/form-encoded contexts `rawurlencode` each value.

**Files:**
- Create: `inc/forms-template.php`
- Modify: `functions.php` (add `require_once`)
- Test: `tests/phpunit/Forms/TemplateTest.php`

**Interfaces:**
- Consumes: `pediment_form_secret_get()` (Task 1).
- Produces (context shape: `array{ fields: array<string,string>, meta: array<string,string> }`):
  - `pediment_form_resolve_token( string $type, string $name, array $context ): string`
  - `pediment_form_render_template( string $template, array $context, string $content_type ): string`
  - `pediment_form_render_header_value( string $template, array $context ): string`
  - `pediment_form_render_url( string $template, array $context ): string`
  - `pediment_form_extract_tokens( string $template ): array<int,array{type:string,name:string}>` — used by Task 5 validation.
- Consumes filter: `pediment_form_template_tokens` (map of `"type:name" => value` for custom tokens).

- [ ] **Step 1: Write the failing test**

```php
// tests/phpunit/Forms/TemplateTest.php
<?php

class TemplateTest extends WP_UnitTestCase {
	private function context(): array {
		return array(
			'fields' => array(
				'name'    => 'Ada',
				'message' => "Line1\nLine2 \"quoted\"",
			),
			'meta'   => array(
				'post_id'  => '42',
				'page_url' => 'https://example.com/contact',
			),
		);
	}

	public function test_json_scalar_token_is_escaped_and_stays_valid() {
		$tpl  = '{"subject":"From {{ field:name }}","body":"{{ field:message }}"}';
		$out  = pediment_form_render_template( $tpl, $this->context(), 'application/json' );
		$data = json_decode( $out, true );
		$this->assertIsArray( $data, 'rendered body must be valid JSON' );
		$this->assertSame( 'From Ada', $data['subject'] );
		$this->assertSame( "Line1\nLine2 \"quoted\"", $data['body'] );
	}

	public function test_all_fields_becomes_json_object() {
		$tpl  = '{"data":"{{ all_fields }}"}';
		$out  = pediment_form_render_template( $tpl, $this->context(), 'application/json' );
		$data = json_decode( $out, true );
		$this->assertSame( array( 'name' => 'Ada', 'message' => "Line1\nLine2 \"quoted\"" ), $data['data'] );
	}

	public function test_meta_token_resolves() {
		$tpl  = '{"src":"{{ meta:page_url }}"}';
		$out  = pediment_form_render_template( $tpl, $this->context(), 'application/json' );
		$this->assertSame( 'https://example.com/contact', json_decode( $out, true )['src'] );
	}

	public function test_secret_token_resolves_from_store() {
		pediment_form_secret_set( 'api', 'sk-123' );
		$tpl = '{"key":"{{ secret:api }}"}';
		$out = pediment_form_render_template( $tpl, $this->context(), 'application/json' );
		$this->assertSame( 'sk-123', json_decode( $out, true )['key'] );
		pediment_form_secret_set( 'api', '' );
	}

	public function test_form_urlencoded_encodes_values_and_all_fields() {
		$tpl = 'who={{ field:name }}&dump={{ all_fields }}';
		$out = pediment_form_render_template( $tpl, $this->context(), 'application/x-www-form-urlencoded' );
		$this->assertStringContainsString( 'who=Ada', $out );
		$this->assertStringContainsString( 'name=Ada', $out );
		$this->assertStringContainsString( 'message=Line1%0ALine2', $out );
	}

	public function test_header_value_strips_crlf() {
		$ctx = array( 'fields' => array( 'x' => "abc\r\nInjected: bad" ), 'meta' => array() );
		$out = pediment_form_render_header_value( 'Bearer {{ field:x }}', $ctx );
		$this->assertSame( 'Bearer abcInjected: bad', $out );
		$this->assertStringNotContainsString( "\n", $out );
	}

	public function test_unknown_field_resolves_to_empty() {
		$tpl = '{"v":"{{ field:missing }}"}';
		$out = pediment_form_render_template( $tpl, $this->context(), 'application/json' );
		$this->assertSame( '', json_decode( $out, true )['v'] );
	}

	public function test_extract_tokens_lists_each_reference() {
		$tokens = pediment_form_extract_tokens( '{{ field:name }} {{ secret:api }} {{ all_fields }}' );
		$this->assertContains( array( 'type' => 'field', 'name' => 'name' ), $tokens );
		$this->assertContains( array( 'type' => 'secret', 'name' => 'api' ), $tokens );
		$this->assertContains( array( 'type' => 'all_fields', 'name' => '' ), $tokens );
	}
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `wp-env run tests-cli --env-cwd=wp-content/themes/pediment vendor/bin/phpunit --filter TemplateTest`
Expected: FAIL — undefined function `pediment_form_render_template()`.

- [ ] **Step 3: Write the implementation**

```php
// inc/forms-template.php
<?php
/**
 * Token templating for destination requests.
 *
 * Tokens: {{ field:NAME }} {{ all_fields }} {{ meta:KEY }} {{ secret:NAME }}.
 * JSON bodies get structural escaping; headers are CRLF-stripped; url/form
 * contexts rawurlencode each value.
 *
 * @package Pediment
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const PEDIMENT_FORM_SCALAR_TOKEN_RE = '/\{\{\s*(field|meta|secret)\s*:\s*([a-z0-9_]+)\s*\}\}/i';
const PEDIMENT_FORM_ALL_FIELDS_RE   = '/\{\{\s*all_fields\s*\}\}/';

/**
 * Resolve a single scalar token to its string value.
 *
 * @param string               $type    field|meta|secret.
 * @param string               $name    Token name.
 * @param array<string,mixed>  $context Render context.
 */
function pediment_form_resolve_token( string $type, string $name, array $context ): string {
	switch ( $type ) {
		case 'field':
			return isset( $context['fields'][ $name ] ) ? (string) $context['fields'][ $name ] : '';
		case 'meta':
			return isset( $context['meta'][ $name ] ) ? (string) $context['meta'][ $name ] : '';
		case 'secret':
			return pediment_form_secret_get( $name );
	}
	$custom = (array) apply_filters( 'pediment_form_template_tokens', array(), $context );
	$key    = $type . ':' . $name;
	return isset( $custom[ $key ] ) ? (string) $custom[ $key ] : '';
}

/**
 * Render a body template against the context for the given content type.
 *
 * @param string              $template     Raw template.
 * @param array<string,mixed> $context      Render context.
 * @param string              $content_type Destination content type.
 */
function pediment_form_render_template( string $template, array $context, string $content_type ): string {
	$is_json = false !== stripos( $content_type, 'json' );
	$fields  = isset( $context['fields'] ) && is_array( $context['fields'] ) ? $context['fields'] : array();

	if ( $is_json ) {
		// "{{ all_fields }}" (a quoted JSON value) becomes a JSON object.
		$template = preg_replace_callback(
			'/"\{\{\s*all_fields\s*\}\}"/',
			static function () use ( $fields ) {
				return (string) wp_json_encode( $fields );
			},
			$template
		);
		// Scalar tokens: JSON-string-escape, strip the surrounding quotes (already
		// inside the template's own quotes).
		return (string) preg_replace_callback(
			PEDIMENT_FORM_SCALAR_TOKEN_RE,
			static function ( $m ) use ( $context ) {
				$value   = pediment_form_resolve_token( strtolower( $m[1] ), strtolower( $m[2] ), $context );
				$encoded = (string) wp_json_encode( $value );
				return substr( $encoded, 1, -1 );
			},
			$template
		);
	}

	// Non-JSON (form-encoded / plain): rawurlencode scalars and all_fields pairs.
	$pairs = array();
	foreach ( $fields as $k => $v ) {
		$pairs[] = rawurlencode( (string) $k ) . '=' . rawurlencode( (string) $v );
	}
	$template = (string) preg_replace( PEDIMENT_FORM_ALL_FIELDS_RE, implode( '&', $pairs ), $template );
	return (string) preg_replace_callback(
		PEDIMENT_FORM_SCALAR_TOKEN_RE,
		static function ( $m ) use ( $context ) {
			return rawurlencode( pediment_form_resolve_token( strtolower( $m[1] ), strtolower( $m[2] ), $context ) );
		},
		$template
	);
}

/**
 * Render a header value: raw substitution, then CRLF-strip to block injection.
 *
 * @param string              $template Header template.
 * @param array<string,mixed> $context  Render context.
 */
function pediment_form_render_header_value( string $template, array $context ): string {
	$out = (string) preg_replace_callback(
		PEDIMENT_FORM_SCALAR_TOKEN_RE,
		static function ( $m ) use ( $context ) {
			return pediment_form_resolve_token( strtolower( $m[1] ), strtolower( $m[2] ), $context );
		},
		$template
	);
	return str_replace( array( "\r", "\n" ), '', $out );
}

/**
 * Render a URL template: rawurlencode each substituted value.
 *
 * @param string              $template URL template.
 * @param array<string,mixed> $context  Render context.
 */
function pediment_form_render_url( string $template, array $context ): string {
	return (string) preg_replace_callback(
		PEDIMENT_FORM_SCALAR_TOKEN_RE,
		static function ( $m ) use ( $context ) {
			return rawurlencode( pediment_form_resolve_token( strtolower( $m[1] ), strtolower( $m[2] ), $context ) );
		},
		$template
	);
}

/**
 * List every token referenced in a template (for save-time validation).
 *
 * @param string $template Raw template.
 * @return array<int,array{type:string,name:string}>
 */
function pediment_form_extract_tokens( string $template ): array {
	$tokens = array();
	if ( preg_match_all( PEDIMENT_FORM_SCALAR_TOKEN_RE, $template, $m, PREG_SET_ORDER ) ) {
		foreach ( $m as $match ) {
			$tokens[] = array(
				'type' => strtolower( $match[1] ),
				'name' => strtolower( $match[2] ),
			);
		}
	}
	if ( preg_match( PEDIMENT_FORM_ALL_FIELDS_RE, $template ) ) {
		$tokens[] = array(
			'type' => 'all_fields',
			'name' => '',
		);
	}
	return $tokens;
}
```

Add to `functions.php` after the ssrf require:

```php
require_once __DIR__ . '/inc/forms-template.php';
```

- [ ] **Step 4: Run test to verify it passes**

Run: `wp-env run tests-cli --env-cwd=wp-content/themes/pediment vendor/bin/phpunit --filter TemplateTest`
Expected: PASS (8 tests).

- [ ] **Step 5: Commit**

```bash
git add inc/forms-template.php functions.php tests/phpunit/Forms/TemplateTest.php
git commit -m "feat: structural token templating for form delivery"
```

---

### Task 4: Provider presets

Curated, known-good starter destinations for Brevo, Resend, Mailgun, n8n webhook, Slack, and Custom. Each is a partial destination record (method + url + headers + content_type + starter body_template + the secret names it expects). Admin picks one as a starting point. Filterable via `pediment_form_presets`.

**Files:**
- Create: `inc/forms-presets.php`
- Modify: `functions.php` (add `require_once`)
- Test: `tests/phpunit/Forms/PresetsTest.php`

**Interfaces:**
- Produces:
  - `pediment_form_presets(): array<string,array<string,mixed>>` — keyed by preset id; each has `label, method, url, headers, content_type, body_template, secret_refs`.
- Consumes filter: `pediment_form_presets`.

- [ ] **Step 1: Write the failing test**

```php
// tests/phpunit/Forms/PresetsTest.php
<?php

class PresetsTest extends WP_UnitTestCase {
	public function test_ships_core_providers() {
		$presets = pediment_form_presets();
		foreach ( array( 'brevo', 'resend', 'mailgun', 'n8n', 'slack', 'custom' ) as $id ) {
			$this->assertArrayHasKey( $id, $presets, "missing preset: {$id}" );
		}
	}

	public function test_each_preset_has_required_shape() {
		foreach ( pediment_form_presets() as $id => $p ) {
			$this->assertArrayHasKey( 'label', $p, $id );
			$this->assertArrayHasKey( 'method', $p, $id );
			$this->assertArrayHasKey( 'content_type', $p, $id );
			$this->assertArrayHasKey( 'body_template', $p, $id );
			$this->assertArrayHasKey( 'headers', $p, $id );
			$this->assertIsArray( $p['headers'], $id );
		}
	}

	public function test_provider_presets_use_https_urls() {
		foreach ( pediment_form_presets() as $id => $p ) {
			if ( 'custom' === $id || '' === (string) $p['url'] ) {
				continue;
			}
			$this->assertStringStartsWith( 'https://', (string) $p['url'], $id );
		}
	}

	public function test_filter_can_add_a_preset() {
		add_filter(
			'pediment_form_presets',
			fn( $p ) => $p + array( 'mine' => array( 'label' => 'Mine', 'method' => 'POST', 'url' => 'https://x.test', 'headers' => array(), 'content_type' => 'application/json', 'body_template' => '{}', 'secret_refs' => array() ) )
		);
		$this->assertArrayHasKey( 'mine', pediment_form_presets() );
		remove_all_filters( 'pediment_form_presets' );
	}
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `wp-env run tests-cli --env-cwd=wp-content/themes/pediment vendor/bin/phpunit --filter PresetsTest`
Expected: FAIL — undefined function `pediment_form_presets()`.

- [ ] **Step 3: Write the implementation**

```php
// inc/forms-presets.php
<?php
/**
 * Curated provider presets — starter templates for new destinations.
 *
 * @package Pediment
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Return the preset map (id => partial destination), filterable.
 *
 * @return array<string,array<string,mixed>>
 */
function pediment_form_presets(): array {
	$presets = array(
		'brevo'   => array(
			'label'         => __( 'Brevo (transactional email)', 'pediment' ),
			'method'        => 'POST',
			'url'           => 'https://api.brevo.com/v3/smtp/email',
			'headers'       => array( 'api-key' => '{{ secret:brevo_api_key }}' ),
			'content_type'  => 'application/json',
			'body_template' => '{"sender":{"email":"noreply@example.com"},"to":[{"email":"you@example.com"}],"subject":"New form submission","textContent":"{{ field:message }}","params":"{{ all_fields }}"}',
			'secret_refs'   => array( 'brevo_api_key' ),
		),
		'resend'  => array(
			'label'         => __( 'Resend', 'pediment' ),
			'method'        => 'POST',
			'url'           => 'https://api.resend.com/emails',
			'headers'       => array( 'Authorization' => 'Bearer {{ secret:resend_api_key }}' ),
			'content_type'  => 'application/json',
			'body_template' => '{"from":"noreply@example.com","to":"you@example.com","subject":"New form submission","text":"{{ field:message }}"}',
			'secret_refs'   => array( 'resend_api_key' ),
		),
		'mailgun' => array(
			'label'         => __( 'Mailgun', 'pediment' ),
			'method'        => 'POST',
			'url'           => 'https://api.mailgun.net/v3/YOUR_DOMAIN/messages',
			'headers'       => array( 'Authorization' => 'Basic {{ secret:mailgun_basic_auth }}' ),
			'content_type'  => 'application/x-www-form-urlencoded',
			'body_template' => 'from=noreply@example.com&to=you@example.com&subject=New form submission&text={{ field:message }}',
			'secret_refs'   => array( 'mailgun_basic_auth' ),
		),
		'n8n'     => array(
			'label'         => __( 'n8n webhook', 'pediment' ),
			'method'        => 'POST',
			'url'           => 'https://n8n.example.com/webhook/your-id',
			'headers'       => array(),
			'content_type'  => 'application/json',
			'body_template' => '{"fields":"{{ all_fields }}","page":"{{ meta:page_url }}","at":"{{ meta:submitted_at }}"}',
			'secret_refs'   => array(),
		),
		'slack'   => array(
			'label'         => __( 'Slack incoming webhook', 'pediment' ),
			'method'        => 'POST',
			'url'           => 'https://hooks.slack.com/services/{{ secret:slack_webhook_path }}',
			'headers'       => array(),
			'content_type'  => 'application/json',
			'body_template' => '{"text":"New form submission from {{ meta:page_url }}"}',
			'secret_refs'   => array( 'slack_webhook_path' ),
		),
		'custom'  => array(
			'label'         => __( 'Custom HTTP request', 'pediment' ),
			'method'        => 'POST',
			'url'           => '',
			'headers'       => array(),
			'content_type'  => 'application/json',
			'body_template' => '{"fields":"{{ all_fields }}"}',
			'secret_refs'   => array(),
		),
	);

	$filtered = apply_filters( 'pediment_form_presets', $presets );
	return is_array( $filtered ) ? $filtered : $presets;
}
```

Add to `functions.php` after the template require:

```php
require_once __DIR__ . '/inc/forms-presets.php';
```

- [ ] **Step 4: Run test to verify it passes**

Run: `wp-env run tests-cli --env-cwd=wp-content/themes/pediment vendor/bin/phpunit --filter PresetsTest`
Expected: PASS (4 tests).

- [ ] **Step 5: Commit**

```bash
git add inc/forms-presets.php functions.php tests/phpunit/Forms/PresetsTest.php
git commit -m "feat: provider presets for form destinations"
```

---

### Task 5: Destinations registry + validation

The registry merges admin-defined destinations (option `pediment_form_destinations`) with code-registered ones (`pediment_form_destinations` filter; admin wins on id collision). `pediment_form_validate_destination()` is the single gate every destination — hand-written now, AI-drafted in Plan 3 — must pass: known method, HTTPS+SSRF-safe url, allowed content-type, valid JSON body skeleton, and only known token types (secret tokens must reference an existing secret; meta tokens must be in the known set; field tokens are accepted and resolve to empty at send if absent).

**Files:**
- Create: `inc/forms-destinations.php`
- Modify: `functions.php` (add `require_once`)
- Test: `tests/phpunit/Forms/DestinationsTest.php`

**Interfaces:**
- Consumes: `pediment_form_url_is_safe()` (Task 2), `pediment_form_extract_tokens()` (Task 3), `pediment_form_secret_names()` (Task 1).
- Produces:
  - `const PEDIMENT_FORM_DESTINATIONS_OPTION = 'pediment_form_destinations';`
  - `const PEDIMENT_FORM_DEFAULT_DEST_OPTION = 'pediment_form_default_destination';`
  - `const PEDIMENT_FORM_META_KEYS` — `array('post_id','page_url','submitted_at','destination')`.
  - `pediment_form_destinations(): array<string,array>` — id => destination.
  - `pediment_form_get_destination( string $id ): ?array`
  - `pediment_form_resolve_destination_id( string $id ): string` — falls back to the configured default; `''` when nothing resolves.
  - `pediment_form_validate_destination( array $dest ): array<string,string>` — `field => error`, empty when valid.

- [ ] **Step 1: Write the failing test**

```php
// tests/phpunit/Forms/DestinationsTest.php
<?php

class DestinationsTest extends WP_UnitTestCase {
	public function tear_down(): void {
		delete_option( PEDIMENT_FORM_DESTINATIONS_OPTION );
		delete_option( PEDIMENT_FORM_DEFAULT_DEST_OPTION );
		delete_option( PEDIMENT_FORM_SECRETS_OPTION );
		remove_all_filters( 'pediment_form_destinations' );
		parent::tear_down();
	}

	private function valid_dest(): array {
		return array(
			'id'            => 'brevo_main',
			'label'         => 'Brevo main',
			'method'        => 'POST',
			'url'           => 'https://api.brevo.com/v3/smtp/email',
			'headers'       => array( 'api-key' => '{{ secret:brevo_api_key }}' ),
			'content_type'  => 'application/json',
			'body_template' => '{"subject":"Hi {{ field:name }}","data":"{{ all_fields }}"}',
			'secret_refs'   => array( 'brevo_api_key' ),
		);
	}

	public function test_valid_destination_passes() {
		pediment_form_secret_set( 'brevo_api_key', 'sk' );
		$this->assertSame( array(), pediment_form_validate_destination( $this->valid_dest() ) );
	}

	public function test_rejects_non_https_url() {
		$d        = $this->valid_dest();
		$d['url'] = 'http://api.brevo.com/v3/smtp/email';
		$this->assertArrayHasKey( 'url', pediment_form_validate_destination( $d ) );
	}

	public function test_rejects_unknown_method() {
		$d           = $this->valid_dest();
		$d['method'] = 'DELETE';
		$this->assertArrayHasKey( 'method', pediment_form_validate_destination( $d ) );
	}

	public function test_rejects_invalid_json_body() {
		pediment_form_secret_set( 'brevo_api_key', 'sk' );
		$d                  = $this->valid_dest();
		$d['body_template'] = '{"broken": }';
		$this->assertArrayHasKey( 'body_template', pediment_form_validate_destination( $d ) );
	}

	public function test_rejects_secret_token_without_stored_secret() {
		// brevo_api_key not stored.
		$this->assertArrayHasKey( 'secret_refs', pediment_form_validate_destination( $this->valid_dest() ) );
	}

	public function test_rejects_unknown_meta_token() {
		pediment_form_secret_set( 'brevo_api_key', 'sk' );
		$d                  = $this->valid_dest();
		$d['body_template'] = '{"x":"{{ meta:secret_field }}"}';
		$this->assertArrayHasKey( 'body_template', pediment_form_validate_destination( $d ) );
	}

	public function test_registry_merges_filter_and_option_option_wins() {
		update_option( PEDIMENT_FORM_DESTINATIONS_OPTION, array( array( 'id' => 'shared', 'label' => 'From option' ) + $this->valid_dest() ) );
		add_filter( 'pediment_form_destinations', fn( $d ) => array_merge( $d, array( array( 'id' => 'shared', 'label' => 'From code' ), array( 'id' => 'codeonly', 'label' => 'Code only' ) ) ) );
		$all = pediment_form_destinations();
		$this->assertSame( 'From option', $all['shared']['label'] );
		$this->assertArrayHasKey( 'codeonly', $all );
	}

	public function test_resolve_falls_back_to_default() {
		update_option( PEDIMENT_FORM_DESTINATIONS_OPTION, array( $this->valid_dest() ) );
		update_option( PEDIMENT_FORM_DEFAULT_DEST_OPTION, 'brevo_main' );
		$this->assertSame( 'brevo_main', pediment_form_resolve_destination_id( '' ) );
		$this->assertSame( 'brevo_main', pediment_form_resolve_destination_id( 'does_not_exist' ) );

		delete_option( PEDIMENT_FORM_DEFAULT_DEST_OPTION );
		$this->assertSame( '', pediment_form_resolve_destination_id( '' ) );
	}
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `wp-env run tests-cli --env-cwd=wp-content/themes/pediment vendor/bin/phpunit --filter DestinationsTest`
Expected: FAIL — undefined function `pediment_form_validate_destination()`.

- [ ] **Step 3: Write the implementation**

```php
// inc/forms-destinations.php
<?php
/**
 * Destinations registry + validation gate.
 *
 * A destination is a templated outbound HTTP request referenced by id. Admin
 * destinations live in an option; code can register more via filter.
 *
 * @package Pediment
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const PEDIMENT_FORM_DESTINATIONS_OPTION = 'pediment_form_destinations';
const PEDIMENT_FORM_DEFAULT_DEST_OPTION = 'pediment_form_default_destination';
const PEDIMENT_FORM_META_KEYS           = array( 'post_id', 'page_url', 'submitted_at', 'destination' );
const PEDIMENT_FORM_METHODS             = array( 'GET', 'POST', 'PUT', 'PATCH' );
const PEDIMENT_FORM_CONTENT_TYPES       = array( 'application/json', 'application/x-www-form-urlencoded' );

/**
 * All destinations, keyed by id. Admin option wins over code registration.
 *
 * @return array<string,array<string,mixed>>
 */
function pediment_form_destinations(): array {
	$out    = array();
	$stored = get_option( PEDIMENT_FORM_DESTINATIONS_OPTION, array() );
	if ( is_array( $stored ) ) {
		foreach ( $stored as $d ) {
			if ( is_array( $d ) && '' !== (string) ( $d['id'] ?? '' ) ) {
				$out[ (string) $d['id'] ] = $d;
			}
		}
	}
	$registered = (array) apply_filters( 'pediment_form_destinations', array() );
	foreach ( $registered as $d ) {
		$id = is_array( $d ) ? (string) ( $d['id'] ?? '' ) : '';
		if ( '' !== $id && ! isset( $out[ $id ] ) ) {
			$out[ $id ] = $d;
		}
	}
	return $out;
}

/**
 * Look up one destination by id.
 *
 * @param string $id Destination id.
 * @return array<string,mixed>|null
 */
function pediment_form_get_destination( string $id ): ?array {
	$all = pediment_form_destinations();
	return $all[ $id ] ?? null;
}

/**
 * Resolve the effective destination id for a submission, with default fallback.
 *
 * @param string $id The submission's stored destination id (may be empty).
 */
function pediment_form_resolve_destination_id( string $id ): string {
	if ( '' !== $id && null !== pediment_form_get_destination( $id ) ) {
		return $id;
	}
	$default = (string) get_option( PEDIMENT_FORM_DEFAULT_DEST_OPTION, '' );
	return ( '' !== $default && null !== pediment_form_get_destination( $default ) ) ? $default : '';
}

/**
 * Validate a destination record. Returns field => error (empty when valid).
 *
 * @param array<string,mixed> $dest Destination record.
 * @return array<string,string>
 */
function pediment_form_validate_destination( array $dest ): array {
	$errors = array();

	if ( '' === sanitize_key( (string) ( $dest['id'] ?? '' ) ) ) {
		$errors['id'] = __( 'A machine id is required.', 'pediment' );
	}
	if ( ! in_array( strtoupper( (string) ( $dest['method'] ?? '' ) ), PEDIMENT_FORM_METHODS, true ) ) {
		$errors['method'] = __( 'Method must be GET, POST, PUT, or PATCH.', 'pediment' );
	}

	$content_type = (string) ( $dest['content_type'] ?? '' );
	if ( ! in_array( $content_type, PEDIMENT_FORM_CONTENT_TYPES, true ) ) {
		$errors['content_type'] = __( 'Unsupported content type.', 'pediment' );
	}

	// URL: must be HTTPS + SSRF-safe with field/secret tokens neutralised first
	// (so a {{ field:x }} in the path does not break parsing).
	$probe_url = preg_replace( '/\{\{[^}]+\}\}/', 'x', (string) ( $dest['url'] ?? '' ) );
	if ( ! pediment_form_url_is_safe( (string) $probe_url ) ) {
		$errors['url'] = __( 'URL must be HTTPS and resolve to a public host.', 'pediment' );
	}

	// Body: for JSON content type, the skeleton (tokens replaced by neutral
	// literals) must parse as JSON.
	$body = (string) ( $dest['body_template'] ?? '' );
	if ( false !== stripos( $content_type, 'json' ) && '' !== $body ) {
		$skeleton = preg_replace( '/"\{\{\s*all_fields\s*\}\}"/', '{}', $body );
		$skeleton = preg_replace( '/\{\{[^}]+\}\}/', 'x', (string) $skeleton );
		if ( null === json_decode( (string) $skeleton ) ) {
			$errors['body_template'] = __( 'Body is not valid JSON once tokens are filled.', 'pediment' );
		}
	}

	// Token sanity across url + headers + body.
	$haystacks = array( (string) ( $dest['url'] ?? '' ), $body );
	foreach ( (array) ( $dest['headers'] ?? array() ) as $hv ) {
		$haystacks[] = (string) $hv;
	}
	$known_secrets = pediment_form_secret_names();
	foreach ( $haystacks as $hay ) {
		foreach ( pediment_form_extract_tokens( $hay ) as $tok ) {
			if ( 'meta' === $tok['type'] && ! in_array( $tok['name'], PEDIMENT_FORM_META_KEYS, true ) ) {
				$errors['body_template'] = sprintf(
					/* translators: %s: token name */
					__( 'Unknown meta token: %s.', 'pediment' ),
					$tok['name']
				);
			}
			if ( 'secret' === $tok['type'] && ! in_array( $tok['name'], $known_secrets, true ) ) {
				$errors['secret_refs'] = sprintf(
					/* translators: %s: secret name */
					__( 'Secret "%s" is referenced but not stored. Add it under Secrets first.', 'pediment' ),
					$tok['name']
				);
			}
		}
	}

	return $errors;
}
```

Add to `functions.php` after the presets require:

```php
require_once __DIR__ . '/inc/forms-destinations.php';
```

- [ ] **Step 4: Run test to verify it passes**

Run: `wp-env run tests-cli --env-cwd=wp-content/themes/pediment vendor/bin/phpunit --filter DestinationsTest`
Expected: PASS (8 tests).

- [ ] **Step 5: Commit**

```bash
git add inc/forms-destinations.php functions.php tests/phpunit/Forms/DestinationsTest.php
git commit -m "feat: form destinations registry and validation gate"
```

---

### Task 6: Delivery engine + storage wiring

Make storage fire `pediment_form_stored` with the new submission post id, then have the delivery engine hook it: resolve destination → build context from stored fields/meta → render url/headers/body → re-check SSRF → `wp_remote_request` → record `sent`/`failed`/`no_destination` plus HTTP status + response snippet. Tests stub HTTP via the `pre_http_request` filter.

**Files:**
- Create: `inc/forms-delivery.php`
- Modify: `inc/forms-storage.php` (fire `pediment_form_stored` at the end of `pediment_form_persist_submission`)
- Modify: `functions.php` (add `require_once`)
- Test: `tests/phpunit/Forms/DeliveryTest.php`

**Interfaces:**
- Consumes: `pediment_form_resolve_destination_id()`, `pediment_form_get_destination()` (Task 5); `pediment_form_render_template/_header_value/_url()` (Task 3); `pediment_form_url_is_safe()` (Task 2); `PEDIMENT_FORM_CPT` (Plan 1).
- Produces:
  - `pediment_form_build_context( int $submission_id ): array` — `array{fields:array<string,string>,meta:array<string,string>}`.
  - `pediment_form_deliver( int $submission_id ): array` — `array{status:string,code?:int}`; also the `pediment_form_stored` callback.
  - `pediment_form_record_failure( int $submission_id, int $code, string $detail ): array`.
- Fires action: `pediment_form_submission_received` (`$submission_id`, `$destination`) on success.
- Consumes filter: `pediment_form_request_args` (`$args`, `$destination`, `$submission_id`).

- [ ] **Step 1: Write the failing test**

```php
// tests/phpunit/Forms/DeliveryTest.php
<?php

class DeliveryTest extends WP_UnitTestCase {
	private array $captured = array();

	public function set_up(): void {
		parent::set_up();
		$this->captured = array();
		pediment_form_secret_set( 'brevo_api_key', 'sk-live' );
		update_option(
			PEDIMENT_FORM_DESTINATIONS_OPTION,
			array(
				array(
					'id'            => 'brevo_main',
					'label'         => 'Brevo',
					'method'        => 'POST',
					'url'           => 'https://api.brevo.com/v3/smtp/email',
					'headers'       => array( 'api-key' => '{{ secret:brevo_api_key }}' ),
					'content_type'  => 'application/json',
					'body_template' => '{"subject":"Hi {{ field:name }}","data":"{{ all_fields }}"}',
					'secret_refs'   => array( 'brevo_api_key' ),
				),
			)
		);
	}

	public function tear_down(): void {
		delete_option( PEDIMENT_FORM_DESTINATIONS_OPTION );
		delete_option( PEDIMENT_FORM_DEFAULT_DEST_OPTION );
		delete_option( PEDIMENT_FORM_SECRETS_OPTION );
		remove_all_filters( 'pre_http_request' );
		parent::tear_down();
	}

	private function make_submission( string $destination = 'brevo_main' ): int {
		$id = self::factory()->post->create( array( 'post_type' => PEDIMENT_FORM_CPT ) );
		update_post_meta( $id, '_fields', wp_json_encode( array( 'name' => array( 'label' => 'Name', 'value' => 'Ada' ) ) ) );
		update_post_meta( $id, '_source_post_id', 0 );
		update_post_meta( $id, '_destination', $destination );
		update_post_meta( $id, '_delivery_status', 'pending' );
		return $id;
	}

	private function stub_http( int $code, string $body = '{"ok":true}' ): void {
		add_filter(
			'pre_http_request',
			function ( $pre, $args, $url ) use ( $code, $body ) {
				$this->captured = array( 'args' => $args, 'url' => $url );
				return array(
					'response' => array( 'code' => $code, 'message' => 'x' ),
					'body'     => $body,
					'headers'  => array(),
				);
			},
			10,
			3
		);
	}

	public function test_successful_delivery_marks_sent_and_sends_rendered_body() {
		$this->stub_http( 200 );
		$id  = $this->make_submission();
		$res = pediment_form_deliver( $id );

		$this->assertSame( 'sent', $res['status'] );
		$this->assertSame( 'sent', get_post_meta( $id, '_delivery_status', true ) );
		$this->assertSame( 200, (int) get_post_meta( $id, '_delivery_http_status', true ) );

		$sent = json_decode( (string) $this->captured['args']['body'], true );
		$this->assertSame( 'Hi Ada', $sent['subject'] );
		$this->assertSame( array( 'name' => 'Ada' ), $sent['data'] );
		$this->assertSame( 'sk-live', $this->captured['args']['headers']['api-key'] );
	}

	public function test_non_2xx_marks_failed_with_snippet() {
		$this->stub_http( 422, '{"message":"bad"}' );
		$id  = $this->make_submission();
		$res = pediment_form_deliver( $id );

		$this->assertSame( 'failed', $res['status'] );
		$this->assertSame( 422, (int) get_post_meta( $id, '_delivery_http_status', true ) );
		$this->assertStringContainsString( 'bad', (string) get_post_meta( $id, '_delivery_response', true ) );
	}

	public function test_no_destination_is_recorded() {
		$id  = $this->make_submission( '' ); // empty + no default configured
		$res = pediment_form_deliver( $id );
		$this->assertSame( 'no_destination', $res['status'] );
		$this->assertSame( 'no_destination', get_post_meta( $id, '_delivery_status', true ) );
	}

	public function test_storing_a_submission_triggers_delivery() {
		$this->stub_http( 200 );
		$submission = array(
			'post_id'     => 0,
			'form_key'    => 'abc',
			'destination' => 'brevo_main',
			'fields'      => array( 'name' => array( 'label' => 'Name', 'value' => 'Ada' ) ),
		);
		do_action( 'pediment_form_submitted', $submission, null );

		$found = get_posts( array( 'post_type' => PEDIMENT_FORM_CPT, 'posts_per_page' => 1, 'fields' => 'ids' ) );
		$this->assertSame( 'sent', get_post_meta( $found[0], '_delivery_status', true ) );
	}
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `wp-env run tests-cli --env-cwd=wp-content/themes/pediment vendor/bin/phpunit --filter DeliveryTest`
Expected: FAIL — undefined function `pediment_form_deliver()`.

- [ ] **Step 3: Wire the `pediment_form_stored` action into storage**

In `inc/forms-storage.php`, at the very end of `pediment_form_persist_submission()` (immediately after the `update_post_meta( $new_id, '_delivery_status', 'pending' );` line, before the closing brace), add:

```php
	/**
	 * Fires after a submission is stored, carrying the stored post id so delivery
	 * can record its result. Plan 2's delivery engine hooks this.
	 */
	do_action( 'pediment_form_stored', (int) $new_id, $submission );
```

- [ ] **Step 4: Write the delivery implementation**

```php
// inc/forms-delivery.php
<?php
/**
 * Delivery engine: renders and sends a submission to its destination, records
 * the result back onto the submission.
 *
 * @package Pediment
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'pediment_form_stored', 'pediment_form_deliver', 10, 1 );

/**
 * Build the render context (field name => value, plus meta) for a submission.
 *
 * @param int $submission_id form_submission post id.
 * @return array{fields:array<string,string>,meta:array<string,string>}
 */
function pediment_form_build_context( int $submission_id ): array {
	$decoded = json_decode( (string) get_post_meta( $submission_id, '_fields', true ), true );
	$fields  = array();
	if ( is_array( $decoded ) ) {
		foreach ( $decoded as $name => $row ) {
			$fields[ (string) $name ] = is_array( $row ) ? (string) ( $row['value'] ?? '' ) : (string) $row;
		}
	}
	$source = (int) get_post_meta( $submission_id, '_source_post_id', true );
	return array(
		'fields' => $fields,
		'meta'   => array(
			'post_id'      => (string) $source,
			'page_url'     => $source > 0 ? (string) get_permalink( $source ) : '',
			'submitted_at' => (string) get_post_field( 'post_date_gmt', $submission_id ),
			'destination'  => (string) get_post_meta( $submission_id, '_destination', true ),
		),
	);
}

/**
 * Record a failed delivery and return the status array.
 *
 * @param int    $submission_id form_submission post id.
 * @param int    $code          HTTP status (0 for transport/guard errors).
 * @param string $detail        Response snippet or error message.
 * @return array{status:string,code:int}
 */
function pediment_form_record_failure( int $submission_id, int $code, string $detail ): array {
	update_post_meta( $submission_id, '_delivery_status', 'failed' );
	update_post_meta( $submission_id, '_delivery_http_status', $code );
	update_post_meta( $submission_id, '_delivery_response', substr( $detail, 0, 500 ) );
	return array(
		'status' => 'failed',
		'code'   => $code,
	);
}

/**
 * Resolve, render, and send a submission to its destination.
 *
 * @param int $submission_id form_submission post id.
 * @return array{status:string,code?:int}
 */
function pediment_form_deliver( int $submission_id ): array {
	$dest_id = pediment_form_resolve_destination_id( (string) get_post_meta( $submission_id, '_destination', true ) );
	if ( '' === $dest_id ) {
		update_post_meta( $submission_id, '_delivery_status', 'no_destination' );
		return array( 'status' => 'no_destination' );
	}

	$dest    = (array) pediment_form_get_destination( $dest_id );
	$context = pediment_form_build_context( $submission_id );

	$url = pediment_form_render_url( (string) ( $dest['url'] ?? '' ), $context );
	if ( ! pediment_form_url_is_safe( $url ) ) {
		return pediment_form_record_failure( $submission_id, 0, __( 'Destination URL failed the safety check.', 'pediment' ) );
	}

	$content_type = (string) ( $dest['content_type'] ?? 'application/json' );
	$headers      = array( 'Content-Type' => $content_type );
	foreach ( (array) ( $dest['headers'] ?? array() ) as $hk => $hv ) {
		$headers[ (string) $hk ] = pediment_form_render_header_value( (string) $hv, $context );
	}

	$args = apply_filters(
		'pediment_form_request_args',
		array(
			'method'  => strtoupper( (string) ( $dest['method'] ?? 'POST' ) ),
			'headers' => $headers,
			'body'    => pediment_form_render_template( (string) ( $dest['body_template'] ?? '' ), $context, $content_type ),
			'timeout' => 15,
		),
		$dest,
		$submission_id
	);

	$response = wp_remote_request( $url, $args );
	if ( is_wp_error( $response ) ) {
		return pediment_form_record_failure( $submission_id, 0, $response->get_error_message() );
	}

	$code    = (int) wp_remote_retrieve_response_code( $response );
	$snippet = substr( (string) wp_remote_retrieve_body( $response ), 0, 500 );
	if ( $code >= 200 && $code < 300 ) {
		update_post_meta( $submission_id, '_delivery_status', 'sent' );
		update_post_meta( $submission_id, '_delivery_http_status', $code );
		update_post_meta( $submission_id, '_delivery_response', $snippet );
		update_post_meta( $submission_id, '_delivered_at', time() );
		do_action( 'pediment_form_submission_received', $submission_id, $dest );
		return array(
			'status' => 'sent',
			'code'   => $code,
		);
	}

	return pediment_form_record_failure( $submission_id, $code, $snippet );
}
```

Add to `functions.php` after the destinations require:

```php
require_once __DIR__ . '/inc/forms-delivery.php';
```

- [ ] **Step 5: Run test to verify it passes**

Run: `wp-env run tests-cli --env-cwd=wp-content/themes/pediment vendor/bin/phpunit --filter DeliveryTest`
Expected: PASS (4 tests).

- [ ] **Step 6: Confirm Plan 1's storage tests still pass**

Run: `wp-env run tests-cli --env-cwd=wp-content/themes/pediment vendor/bin/phpunit --filter StorageTest`
Expected: PASS — the new `do_action` must not break existing storage assertions.

- [ ] **Step 7: Commit**

```bash
git add inc/forms-delivery.php inc/forms-storage.php functions.php tests/phpunit/Forms/DeliveryTest.php
git commit -m "feat: form delivery engine wired to submission storage"
```

---

### Task 7: Retention setting + delivery-status column + retry action

Replace the hardcoded retention default with the saved setting (option `pediment_form_retention_days`, default 90), add a **Delivery** admin column showing status, and a **Retry delivery** row action (nonce-protected `admin_post` handler) for non-sent submissions.

**Files:**
- Modify: `inc/forms-storage.php` (retention reads option; add delivery column + retry row action + handler)
- Test: `tests/phpunit/Forms/RetentionSettingTest.php`

**Interfaces:**
- Consumes: `pediment_form_deliver()` (Task 6).
- Produces:
  - `const PEDIMENT_FORM_RETENTION_OPTION = 'pediment_form_retention_days';`
  - `pediment_form_retention_days(): int` — saved value (filterable via existing `pediment_form_retention_days` filter), default 90, `0` = keep forever.

- [ ] **Step 1: Write the failing test**

```php
// tests/phpunit/Forms/RetentionSettingTest.php
<?php

class RetentionSettingTest extends WP_UnitTestCase {
	public function tear_down(): void {
		delete_option( PEDIMENT_FORM_RETENTION_OPTION );
		remove_all_filters( 'pediment_form_retention_days' );
		parent::tear_down();
	}

	public function test_default_is_ninety_days() {
		$this->assertSame( 90, pediment_form_retention_days() );
	}

	public function test_saved_option_overrides_default() {
		update_option( PEDIMENT_FORM_RETENTION_OPTION, 30 );
		$this->assertSame( 30, pediment_form_retention_days() );
	}

	public function test_filter_overrides_option() {
		update_option( PEDIMENT_FORM_RETENTION_OPTION, 30 );
		add_filter( 'pediment_form_retention_days', fn() => 7 );
		$this->assertSame( 7, pediment_form_retention_days() );
	}

	public function test_zero_keeps_forever_and_cleanup_is_noop() {
		update_option( PEDIMENT_FORM_RETENTION_OPTION, 0 );
		$old = self::factory()->post->create(
			array(
				'post_type' => PEDIMENT_FORM_CPT,
				'post_date' => '2000-01-01 00:00:00',
			)
		);
		pediment_form_cleanup();
		$this->assertNotNull( get_post( $old ) );
	}
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `wp-env run tests-cli --env-cwd=wp-content/themes/pediment vendor/bin/phpunit --filter RetentionSettingTest`
Expected: FAIL — undefined function `pediment_form_retention_days()` / undefined constant.

- [ ] **Step 3: Update retention in `inc/forms-storage.php`**

Add near the top of the file (after the `if ( ! defined( 'ABSPATH' ) )` guard):

```php
const PEDIMENT_FORM_RETENTION_OPTION = 'pediment_form_retention_days';

/**
 * Effective retention window in days (0 = keep forever). Saved setting first,
 * then the long-standing filter.
 */
function pediment_form_retention_days(): int {
	$days = (int) get_option( PEDIMENT_FORM_RETENTION_OPTION, 90 );
	return (int) apply_filters( 'pediment_form_retention_days', $days );
}
```

Then replace the first two lines of the existing `pediment_form_cleanup()` body:

```php
	$days = (int) apply_filters( 'pediment_form_retention_days', 90 );
	if ( $days <= 0 ) {
		return;
	}
```

with:

```php
	$days = pediment_form_retention_days();
	if ( $days <= 0 ) {
		return;
	}
```

- [ ] **Step 4: Add the delivery column + retry row action to `inc/forms-storage.php`**

In the `manage_..._posts_columns` filter, add a `delivery` column. Replace the returned array with:

```php
			return array(
				'cb'          => $cols['cb'] ?? '',
				'title'       => __( 'Submission', 'pediment' ),
				'fields'      => __( 'Details', 'pediment' ),
				'destination' => __( 'Destination', 'pediment' ),
				'delivery'    => __( 'Delivery', 'pediment' ),
				'date'        => __( 'Submitted', 'pediment' ),
			);
```

In the `manage_..._posts_custom_column` action callback, add a `delivery` branch before the closing brace of the callback:

```php
			} elseif ( 'delivery' === $col ) {
				$status = (string) get_post_meta( $post_id, '_delivery_status', true );
				$http   = (string) get_post_meta( $post_id, '_delivery_http_status', true );
				$labels = array(
					'sent'           => __( 'Sent', 'pediment' ),
					'failed'         => __( 'Failed', 'pediment' ),
					'pending'        => __( 'Pending', 'pediment' ),
					'no_destination' => __( 'No destination', 'pediment' ),
				);
				$text = $labels[ $status ] ?? ( '' !== $status ? $status : __( 'Pending', 'pediment' ) );
				if ( '' !== $http && 'sent' !== $status ) {
					$text .= ' (' . $http . ')';
				}
				echo esc_html( $text );
```

Append the retry row action + handler at the end of `inc/forms-storage.php`:

```php
add_filter(
	'post_row_actions',
	function ( array $actions, WP_Post $post ): array {
		if ( PEDIMENT_FORM_CPT !== $post->post_type || ! current_user_can( 'manage_options' ) ) {
			return $actions;
		}
		if ( 'sent' === (string) get_post_meta( $post->ID, '_delivery_status', true ) ) {
			return $actions;
		}
		$url = wp_nonce_url(
			admin_url( 'admin-post.php?action=pediment_form_retry&submission=' . $post->ID ),
			'pediment_form_retry_' . $post->ID
		);
		$actions['pediment_retry'] = sprintf(
			'<a href="%s">%s</a>',
			esc_url( $url ),
			esc_html__( 'Retry delivery', 'pediment' )
		);
		return $actions;
	},
	10,
	2
);

add_action( 'admin_post_pediment_form_retry', 'pediment_form_handle_retry' );

/**
 * Re-attempt delivery for a single submission from the admin list.
 */
function pediment_form_handle_retry(): void {
	$id = isset( $_GET['submission'] ) ? absint( wp_unslash( $_GET['submission'] ) ) : 0;
	if ( $id <= 0 || ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Not allowed.', 'pediment' ) );
	}
	check_admin_referer( 'pediment_form_retry_' . $id );
	pediment_form_deliver( $id );
	wp_safe_redirect( add_query_arg( array( 'post_type' => PEDIMENT_FORM_CPT ), admin_url( 'edit.php' ) ) );
	exit;
}
```

- [ ] **Step 5: Run test to verify it passes**

Run: `wp-env run tests-cli --env-cwd=wp-content/themes/pediment vendor/bin/phpunit --filter RetentionSettingTest`
Expected: PASS (4 tests). Also re-run `--filter RetentionTest` (Plan 1) to confirm no regression.

- [ ] **Step 6: Commit**

```bash
git add inc/forms-storage.php tests/phpunit/Forms/RetentionSettingTest.php
git commit -m "feat: retention setting + delivery status column and retry"
```

---

### Task 8: Settings → Forms admin page (secrets + destinations CRUD + send test)

A `manage_options` admin page (submenu under **Form submissions**) with three sections: **General** (retention days + default destination), **Secrets** (add/update/delete encrypted credentials), and **Destinations** (create/edit/delete; preset-seeded; a **Send test** dry-run). All writes go through nonce-protected `admin_post` handlers. The testable core is the pure `pediment_form_sanitize_destination()` function; rendering and handlers wrap it.

**Files:**
- Create: `inc/forms-settings.php`
- Create: `assets/js/admin-forms-settings.js`
- Modify: `functions.php` (add `require_once`)
- Test: `tests/phpunit/Forms/SettingsSanitizeTest.php`

**Interfaces:**
- Consumes: `pediment_form_validate_destination()`, `pediment_form_destinations()`, `PEDIMENT_FORM_DESTINATIONS_OPTION`, `PEDIMENT_FORM_DEFAULT_DEST_OPTION` (Task 5); `pediment_form_presets()` (Task 4); `pediment_form_secret_set/_names()` (Task 1); `pediment_form_build_context()` + `pediment_form_deliver()` patterns (Task 6); `PEDIMENT_FORM_RETENTION_OPTION`, `PEDIMENT_FORM_CPT`.
- Produces:
  - `pediment_form_sanitize_destination( array $raw ): array{dest:array,errors:array<string,string>}` — normalizes raw POST input into a clean destination record + validation errors.
  - `pediment_form_save_destination( array $dest ): void` — upsert into the option by id.
  - `pediment_form_delete_destination( string $id ): void`.

- [ ] **Step 1: Write the failing test**

```php
// tests/phpunit/Forms/SettingsSanitizeTest.php
<?php

class SettingsSanitizeTest extends WP_UnitTestCase {
	public function tear_down(): void {
		delete_option( PEDIMENT_FORM_DESTINATIONS_OPTION );
		delete_option( PEDIMENT_FORM_SECRETS_OPTION );
		parent::tear_down();
	}

	private function raw(): array {
		return array(
			'id'            => 'My Brevo!',
			'label'         => '  Brevo main  ',
			'method'        => 'post',
			'url'           => 'https://api.brevo.com/v3/smtp/email',
			'content_type'  => 'application/json',
			'body_template' => '{"data":"{{ all_fields }}"}',
			'header_keys'   => array( 'api-key', '' ),
			'header_values' => array( '{{ secret:brevo_api_key }}', 'ignored' ),
		);
	}

	public function test_sanitize_normalizes_id_method_and_headers() {
		pediment_form_secret_set( 'brevo_api_key', 'sk' );
		$out  = pediment_form_sanitize_destination( $this->raw() );
		$dest = $out['dest'];

		$this->assertSame( array(), $out['errors'] );
		$this->assertSame( 'my_brevo', $dest['id'] );
		$this->assertSame( 'Brevo main', $dest['label'] );
		$this->assertSame( 'POST', $dest['method'] );
		$this->assertSame( array( 'api-key' => '{{ secret:brevo_api_key }}' ), $dest['headers'] );
		$this->assertContains( 'brevo_api_key', $dest['secret_refs'] );
	}

	public function test_sanitize_surfaces_validation_errors() {
		$raw        = $this->raw();
		$raw['url'] = 'http://insecure.example.com';
		$out        = pediment_form_sanitize_destination( $raw );
		$this->assertArrayHasKey( 'url', $out['errors'] );
	}

	public function test_save_and_delete_roundtrip() {
		pediment_form_secret_set( 'brevo_api_key', 'sk' );
		$dest = pediment_form_sanitize_destination( $this->raw() )['dest'];
		pediment_form_save_destination( $dest );
		$this->assertArrayHasKey( 'my_brevo', pediment_form_destinations() );

		pediment_form_delete_destination( 'my_brevo' );
		$this->assertArrayNotHasKey( 'my_brevo', pediment_form_destinations() );
	}

	public function test_save_upserts_by_id() {
		pediment_form_secret_set( 'brevo_api_key', 'sk' );
		$dest = pediment_form_sanitize_destination( $this->raw() )['dest'];
		pediment_form_save_destination( $dest );
		$dest['label'] = 'Renamed';
		pediment_form_save_destination( $dest );

		$all = pediment_form_destinations();
		$this->assertCount( 1, $all );
		$this->assertSame( 'Renamed', $all['my_brevo']['label'] );
	}
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `wp-env run tests-cli --env-cwd=wp-content/themes/pediment vendor/bin/phpunit --filter SettingsSanitizeTest`
Expected: FAIL — undefined function `pediment_form_sanitize_destination()`.

- [ ] **Step 3: Write the settings implementation**

```php
// inc/forms-settings.php
<?php
/**
 * Settings → Forms: general settings, encrypted secrets, and destinations CRUD.
 *
 * @package Pediment
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const PEDIMENT_FORM_SETTINGS_PAGE = 'pediment-forms';

/**
 * Normalize raw destination POST input into a clean record + validation errors.
 *
 * @param array<string,mixed> $raw Raw input (header_keys[]/header_values[] paired).
 * @return array{dest:array<string,mixed>,errors:array<string,string>}
 */
function pediment_form_sanitize_destination( array $raw ): array {
	$headers = array();
	$keys    = isset( $raw['header_keys'] ) && is_array( $raw['header_keys'] ) ? array_values( $raw['header_keys'] ) : array();
	$vals    = isset( $raw['header_values'] ) && is_array( $raw['header_values'] ) ? array_values( $raw['header_values'] ) : array();
	foreach ( $keys as $i => $k ) {
		$k = sanitize_text_field( (string) $k );
		if ( '' === $k ) {
			continue;
		}
		$headers[ $k ] = isset( $vals[ $i ] ) ? sanitize_text_field( (string) $vals[ $i ] ) : '';
	}

	$body        = (string) ( $raw['body_template'] ?? '' );
	$secret_refs = array();
	$scan        = array_merge( array( (string) ( $raw['url'] ?? '' ), $body ), array_values( $headers ) );
	foreach ( $scan as $hay ) {
		foreach ( pediment_form_extract_tokens( $hay ) as $tok ) {
			if ( 'secret' === $tok['type'] ) {
				$secret_refs[ $tok['name'] ] = true;
			}
		}
	}

	$dest = array(
		'id'            => sanitize_key( (string) ( $raw['id'] ?? '' ) ),
		'label'         => sanitize_text_field( trim( (string) ( $raw['label'] ?? '' ) ) ),
		'method'        => strtoupper( sanitize_text_field( (string) ( $raw['method'] ?? 'POST' ) ) ),
		'url'           => esc_url_raw( trim( (string) ( $raw['url'] ?? '' ) ), array( 'https' ) ),
		'content_type'  => sanitize_text_field( (string) ( $raw['content_type'] ?? 'application/json' ) ),
		'headers'       => $headers,
		'body_template' => $body,
		'secret_refs'   => array_keys( $secret_refs ),
	);

	return array(
		'dest'   => $dest,
		'errors' => pediment_form_validate_destination( $dest ),
	);
}

/**
 * Upsert a destination into the stored option, keyed by id.
 *
 * @param array<string,mixed> $dest Clean destination record.
 */
function pediment_form_save_destination( array $dest ): void {
	$id = (string) ( $dest['id'] ?? '' );
	if ( '' === $id ) {
		return;
	}
	$stored = get_option( PEDIMENT_FORM_DESTINATIONS_OPTION, array() );
	$stored = is_array( $stored ) ? $stored : array();
	$next   = array();
	$found  = false;
	foreach ( $stored as $existing ) {
		if ( is_array( $existing ) && (string) ( $existing['id'] ?? '' ) === $id ) {
			$next[] = $dest;
			$found  = true;
		} else {
			$next[] = $existing;
		}
	}
	if ( ! $found ) {
		$next[] = $dest;
	}
	update_option( PEDIMENT_FORM_DESTINATIONS_OPTION, $next );
}

/**
 * Remove a destination by id.
 *
 * @param string $id Destination id.
 */
function pediment_form_delete_destination( string $id ): void {
	$stored = get_option( PEDIMENT_FORM_DESTINATIONS_OPTION, array() );
	$stored = is_array( $stored ) ? $stored : array();
	$next   = array();
	foreach ( $stored as $existing ) {
		if ( is_array( $existing ) && (string) ( $existing['id'] ?? '' ) !== $id ) {
			$next[] = $existing;
		}
	}
	update_option( PEDIMENT_FORM_DESTINATIONS_OPTION, $next );
}

add_action(
	'admin_menu',
	function () {
		add_submenu_page(
			'edit.php?post_type=' . PEDIMENT_FORM_CPT,
			__( 'Forms settings', 'pediment' ),
			__( 'Settings', 'pediment' ),
			'manage_options',
			PEDIMENT_FORM_SETTINGS_PAGE,
			'pediment_form_render_settings_page'
		);
	}
);

/**
 * Render the Settings → Forms page.
 */
function pediment_form_render_settings_page(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$destinations = pediment_form_destinations();
	$secrets      = pediment_form_secret_names();
	$presets      = pediment_form_presets();
	$retention    = (int) get_option( PEDIMENT_FORM_RETENTION_OPTION, 90 );
	$default_dest = (string) get_option( PEDIMENT_FORM_DEFAULT_DEST_OPTION, '' );
	?>
	<div class="wrap pediment-forms-settings">
		<h1><?php esc_html_e( 'Forms settings', 'pediment' ); ?></h1>
		<?php settings_errors( 'pediment_forms' ); ?>

		<h2><?php esc_html_e( 'General', 'pediment' ); ?></h2>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="pediment_form_save_general" />
			<?php wp_nonce_field( 'pediment_form_save_general' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="pf-retention"><?php esc_html_e( 'Retention (days)', 'pediment' ); ?></label></th>
					<td>
						<input type="number" min="0" id="pf-retention" name="retention_days" value="<?php echo esc_attr( (string) $retention ); ?>" class="small-text" />
						<p class="description"><?php esc_html_e( '0 keeps submissions forever.', 'pediment' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="pf-default"><?php esc_html_e( 'Default destination', 'pediment' ); ?></label></th>
					<td>
						<select id="pf-default" name="default_destination">
							<option value=""><?php esc_html_e( '— none —', 'pediment' ); ?></option>
							<?php foreach ( $destinations as $id => $d ) : ?>
								<option value="<?php echo esc_attr( $id ); ?>" <?php selected( $default_dest, $id ); ?>><?php echo esc_html( (string) ( $d['label'] ?? $id ) ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
			</table>
			<?php submit_button( __( 'Save general settings', 'pediment' ) ); ?>
		</form>

		<hr />
		<h2><?php esc_html_e( 'Secrets', 'pediment' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Credential values, encrypted at rest. Reference them in destinations as {{ secret:NAME }}.', 'pediment' ); ?></p>
		<ul>
			<?php foreach ( $secrets as $name ) : ?>
				<li>
					<code><?php echo esc_html( $name ); ?></code>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline">
						<input type="hidden" name="action" value="pediment_form_delete_secret" />
						<input type="hidden" name="secret_name" value="<?php echo esc_attr( $name ); ?>" />
						<?php wp_nonce_field( 'pediment_form_delete_secret_' . $name ); ?>
						<button type="submit" class="button-link delete"><?php esc_html_e( 'Delete', 'pediment' ); ?></button>
					</form>
				</li>
			<?php endforeach; ?>
		</ul>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="pediment_form_save_secret" />
			<?php wp_nonce_field( 'pediment_form_save_secret' ); ?>
			<input type="text" name="secret_name" placeholder="<?php esc_attr_e( 'name (e.g. brevo_api_key)', 'pediment' ); ?>" />
			<input type="password" name="secret_value" autocomplete="new-password" placeholder="<?php esc_attr_e( 'value', 'pediment' ); ?>" class="regular-text" />
			<?php submit_button( __( 'Save secret', 'pediment' ), 'secondary', 'submit', false ); ?>
		</form>

		<hr />
		<h2><?php esc_html_e( 'Destinations', 'pediment' ); ?></h2>
		<table class="widefat striped">
			<thead><tr>
				<th><?php esc_html_e( 'ID', 'pediment' ); ?></th>
				<th><?php esc_html_e( 'Label', 'pediment' ); ?></th>
				<th><?php esc_html_e( 'Method', 'pediment' ); ?></th>
				<th><?php esc_html_e( 'URL', 'pediment' ); ?></th>
				<th></th>
			</tr></thead>
			<tbody>
				<?php foreach ( $destinations as $id => $d ) : ?>
					<tr>
						<td><code><?php echo esc_html( $id ); ?></code></td>
						<td><?php echo esc_html( (string) ( $d['label'] ?? '' ) ); ?></td>
						<td><?php echo esc_html( (string) ( $d['method'] ?? '' ) ); ?></td>
						<td><?php echo esc_html( (string) ( $d['url'] ?? '' ) ); ?></td>
						<td>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline">
								<input type="hidden" name="action" value="pediment_form_delete_destination" />
								<input type="hidden" name="id" value="<?php echo esc_attr( $id ); ?>" />
								<?php wp_nonce_field( 'pediment_form_delete_destination_' . $id ); ?>
								<button type="submit" class="button-link delete"><?php esc_html_e( 'Delete', 'pediment' ); ?></button>
							</form>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<h3><?php esc_html_e( 'Add / edit destination', 'pediment' ); ?></h3>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="pediment-forms-destination" data-presets="<?php echo esc_attr( (string) wp_json_encode( $presets ) ); ?>">
			<input type="hidden" name="action" value="pediment_form_save_destination" />
			<?php wp_nonce_field( 'pediment_form_save_destination' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label><?php esc_html_e( 'Start from preset', 'pediment' ); ?></label></th>
					<td>
						<select class="pediment-forms-preset">
							<option value=""><?php esc_html_e( '— choose —', 'pediment' ); ?></option>
							<?php foreach ( $presets as $pid => $preset ) : ?>
								<option value="<?php echo esc_attr( $pid ); ?>"><?php echo esc_html( (string) $preset['label'] ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr><th scope="row"><label for="pf-id"><?php esc_html_e( 'ID', 'pediment' ); ?></label></th>
					<td><input type="text" id="pf-id" name="id" class="regular-text pf-field-id" /></td></tr>
				<tr><th scope="row"><label for="pf-label"><?php esc_html_e( 'Label', 'pediment' ); ?></label></th>
					<td><input type="text" id="pf-label" name="label" class="regular-text" /></td></tr>
				<tr><th scope="row"><label for="pf-method"><?php esc_html_e( 'Method', 'pediment' ); ?></label></th>
					<td><select id="pf-method" name="method" class="pf-field-method">
						<?php foreach ( PEDIMENT_FORM_METHODS as $m ) : ?>
							<option value="<?php echo esc_attr( $m ); ?>"><?php echo esc_html( $m ); ?></option>
						<?php endforeach; ?>
					</select></td></tr>
				<tr><th scope="row"><label for="pf-url"><?php esc_html_e( 'URL', 'pediment' ); ?></label></th>
					<td><input type="url" id="pf-url" name="url" class="large-text code pf-field-url" placeholder="https://…" /></td></tr>
				<tr><th scope="row"><label for="pf-ct"><?php esc_html_e( 'Content type', 'pediment' ); ?></label></th>
					<td><select id="pf-ct" name="content_type" class="pf-field-content_type">
						<?php foreach ( PEDIMENT_FORM_CONTENT_TYPES as $ct ) : ?>
							<option value="<?php echo esc_attr( $ct ); ?>"><?php echo esc_html( $ct ); ?></option>
						<?php endforeach; ?>
					</select></td></tr>
				<tr><th scope="row"><?php esc_html_e( 'Headers', 'pediment' ); ?></th>
					<td class="pf-headers">
						<div class="pf-headers-rows">
							<div class="pf-header-row">
								<input type="text" name="header_keys[]" placeholder="<?php esc_attr_e( 'Header', 'pediment' ); ?>" />
								<input type="text" name="header_values[]" placeholder="<?php esc_attr_e( 'Value (tokens allowed)', 'pediment' ); ?>" class="code" />
							</div>
						</div>
						<button type="button" class="button pf-add-header"><?php esc_html_e( 'Add header', 'pediment' ); ?></button>
					</td></tr>
				<tr><th scope="row"><label for="pf-body"><?php esc_html_e( 'Body template', 'pediment' ); ?></label></th>
					<td><textarea id="pf-body" name="body_template" rows="6" class="large-text code pf-field-body_template"></textarea>
						<p class="description"><?php esc_html_e( 'Tokens: {{ field:NAME }} {{ all_fields }} {{ meta:post_id|page_url|submitted_at|destination }} {{ secret:NAME }}', 'pediment' ); ?></p></td></tr>
			</table>
			<?php submit_button( __( 'Save destination', 'pediment' ) ); ?>
		</form>
	</div>
	<?php
}

add_action( 'admin_post_pediment_form_save_general', 'pediment_form_handle_save_general' );
add_action( 'admin_post_pediment_form_save_secret', 'pediment_form_handle_save_secret' );
add_action( 'admin_post_pediment_form_delete_secret', 'pediment_form_handle_delete_secret' );
add_action( 'admin_post_pediment_form_save_destination', 'pediment_form_handle_save_destination' );
add_action( 'admin_post_pediment_form_delete_destination', 'pediment_form_handle_delete_destination' );

/**
 * Redirect back to the settings page with a notice.
 *
 * @param string $type    'updated' or 'error'.
 * @param string $message Notice text.
 */
function pediment_form_settings_redirect( string $type, string $message ): void {
	set_transient( 'pediment_forms_notice', array( 'type' => $type, 'message' => $message ), 30 );
	wp_safe_redirect(
		add_query_arg(
			array( 'post_type' => PEDIMENT_FORM_CPT, 'page' => PEDIMENT_FORM_SETTINGS_PAGE ),
			admin_url( 'edit.php' )
		)
	);
	exit;
}

add_action(
	'admin_notices',
	function () {
		$notice = get_transient( 'pediment_forms_notice' );
		if ( ! is_array( $notice ) ) {
			return;
		}
		delete_transient( 'pediment_forms_notice' );
		printf(
			'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
			'error' === $notice['type'] ? 'error' : 'success',
			esc_html( (string) $notice['message'] )
		);
	}
);

function pediment_form_handle_save_general(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Not allowed.', 'pediment' ) );
	}
	check_admin_referer( 'pediment_form_save_general' );
	update_option( PEDIMENT_FORM_RETENTION_OPTION, isset( $_POST['retention_days'] ) ? absint( wp_unslash( $_POST['retention_days'] ) ) : 90 );
	$default = isset( $_POST['default_destination'] ) ? sanitize_key( wp_unslash( $_POST['default_destination'] ) ) : '';
	update_option( PEDIMENT_FORM_DEFAULT_DEST_OPTION, $default );
	pediment_form_settings_redirect( 'updated', __( 'Settings saved.', 'pediment' ) );
}

function pediment_form_handle_save_secret(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Not allowed.', 'pediment' ) );
	}
	check_admin_referer( 'pediment_form_save_secret' );
	$name  = isset( $_POST['secret_name'] ) ? sanitize_key( wp_unslash( $_POST['secret_name'] ) ) : '';
	$value = isset( $_POST['secret_value'] ) ? (string) wp_unslash( $_POST['secret_value'] ) : '';
	if ( '' === $name || '' === $value ) {
		pediment_form_settings_redirect( 'error', __( 'Secret name and value are required.', 'pediment' ) );
	}
	pediment_form_secret_set( $name, $value );
	pediment_form_settings_redirect( 'updated', __( 'Secret saved.', 'pediment' ) );
}

function pediment_form_handle_delete_secret(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Not allowed.', 'pediment' ) );
	}
	$name = isset( $_POST['secret_name'] ) ? sanitize_key( wp_unslash( $_POST['secret_name'] ) ) : '';
	check_admin_referer( 'pediment_form_delete_secret_' . $name );
	pediment_form_secret_set( $name, '' );
	pediment_form_settings_redirect( 'updated', __( 'Secret deleted.', 'pediment' ) );
}

function pediment_form_handle_save_destination(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Not allowed.', 'pediment' ) );
	}
	check_admin_referer( 'pediment_form_save_destination' );
	$result = pediment_form_sanitize_destination( wp_unslash( $_POST ) );
	if ( ! empty( $result['errors'] ) ) {
		pediment_form_settings_redirect( 'error', implode( ' ', $result['errors'] ) );
	}
	pediment_form_save_destination( $result['dest'] );
	pediment_form_settings_redirect( 'updated', __( 'Destination saved.', 'pediment' ) );
}

function pediment_form_handle_delete_destination(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Not allowed.', 'pediment' ) );
	}
	$id = isset( $_POST['id'] ) ? sanitize_key( wp_unslash( $_POST['id'] ) ) : '';
	check_admin_referer( 'pediment_form_delete_destination_' . $id );
	pediment_form_delete_destination( $id );
	pediment_form_settings_redirect( 'updated', __( 'Destination deleted.', 'pediment' ) );
}

add_action(
	'admin_enqueue_scripts',
	function ( $hook_suffix ) {
		if ( false === strpos( (string) $hook_suffix, PEDIMENT_FORM_SETTINGS_PAGE ) ) {
			return;
		}
		$rel = 'assets/js/admin-forms-settings.js';
		wp_enqueue_script(
			'pediment-forms-settings',
			get_theme_file_uri( $rel ),
			array(),
			(string) filemtime( get_theme_file_path( $rel ) ),
			true
		);
	}
);
```

- [ ] **Step 4: Write the admin JS (preset autofill + header repeater)**

```js
// assets/js/admin-forms-settings.js
( function () {
	'use strict';

	function fillFromPreset( form, preset ) {
		var set = function ( selector, value ) {
			var el = form.querySelector( selector );
			if ( el ) {
				el.value = value;
			}
		};
		set( '.pf-field-method', preset.method || 'POST' );
		set( '.pf-field-url', preset.url || '' );
		set( '.pf-field-content_type', preset.content_type || 'application/json' );
		set( '.pf-field-body_template', preset.body_template || '' );

		var rows = form.querySelector( '.pf-headers-rows' );
		if ( rows ) {
			rows.innerHTML = '';
			var headers = preset.headers || {};
			var keys = Object.keys( headers );
			if ( keys.length === 0 ) {
				keys = [ '' ];
				headers = { '': '' };
			}
			keys.forEach( function ( key ) {
				rows.appendChild( headerRow( key, headers[ key ] || '' ) );
			} );
		}
	}

	function headerRow( key, value ) {
		var wrap = document.createElement( 'div' );
		wrap.className = 'pf-header-row';
		wrap.innerHTML =
			'<input type="text" name="header_keys[]" />' +
			'<input type="text" name="header_values[]" class="code" />';
		wrap.querySelector( '[name="header_keys[]"]' ).value = key;
		wrap.querySelector( '[name="header_values[]"]' ).value = value;
		return wrap;
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		var form = document.querySelector( '.pediment-forms-destination' );
		if ( ! form ) {
			return;
		}
		var presets = {};
		try {
			presets = JSON.parse( form.getAttribute( 'data-presets' ) || '{}' );
		} catch ( e ) {
			presets = {};
		}

		var picker = form.querySelector( '.pediment-forms-preset' );
		if ( picker ) {
			picker.addEventListener( 'change', function () {
				if ( presets[ picker.value ] ) {
					fillFromPreset( form, presets[ picker.value ] );
				}
			} );
		}

		var add = form.querySelector( '.pf-add-header' );
		if ( add ) {
			add.addEventListener( 'click', function () {
				form.querySelector( '.pf-headers-rows' ).appendChild( headerRow( '', '' ) );
			} );
		}
	} );
} )();
```

Add to `functions.php` after the delivery require:

```php
require_once __DIR__ . '/inc/forms-settings.php';
```

- [ ] **Step 5: Run test to verify it passes**

Run: `wp-env run tests-cli --env-cwd=wp-content/themes/pediment vendor/bin/phpunit --filter SettingsSanitizeTest`
Expected: PASS (4 tests).

- [ ] **Step 6: Commit**

```bash
git add inc/forms-settings.php assets/js/admin-forms-settings.js functions.php tests/phpunit/Forms/SettingsSanitizeTest.php
git commit -m "feat: Settings → Forms admin UI for destinations and secrets"
```

---

### Task 9: Integration — full suite + linters

Confirm the whole forms feature (Plan 1 + Plan 2) is green and lint-clean before handing off.

**Files:** none (verification + final commit only if linters auto-fix).

- [ ] **Step 1: Run the full PHP suite**

Run: `wp-env run tests-cli --env-cwd=wp-content/themes/pediment vendor/bin/phpunit`
Expected: PASS — all `Forms/*` classes (FieldsTest, SubmissionTest, StorageTest, RetentionTest, SecretsTest, SsrfTest, TemplateTest, PresetsTest, DestinationsTest, DeliveryTest, RetentionSettingTest, SettingsSanitizeTest) plus the rest of the suite.

- [ ] **Step 2: Run the linters (CI gates)**

Run: `npm run lint:colors && composer run lint`
Expected: PASS with **zero** phpcs warnings. If phpcs reports fixable issues, run `composer run lint:fix`, re-run `composer run lint`, and confirm clean.

- [ ] **Step 3: Manual smoke (optional but recommended)**

In wp-env: Settings is under **Form submissions → Settings**. Add a secret, create a destination from the Slack preset, set it as default, submit the front-end form, and confirm the submission row shows **Sent** (or **Failed** with the provider's status). Use **Retry delivery** on a failed row.

- [ ] **Step 4: Final commit (only if lint:fix changed files)**

```bash
git add -A
git commit -m "chore: phpcs autofixes for forms delivery"
```

---

## Self-Review

**Spec coverage (against `2026-06-29-ai-generatable-forms-design.md`):**

- §4 Destinations registry → Task 5. Templated request shape → Tasks 3 + 5. ✓
- §4 Encrypted secret store → Task 1. ✓
- §4 Token templating (`field`/`all_fields`/`meta`/`secret`, structural JSON escaping, CRLF-stripped headers) → Task 3. ✓
- §4 Delivery (`wp_remote_request`, 2xx = sent, failure recorded, retry) → Tasks 6 + 7. ✓
- §4 HTTPS + SSRF guard at save and send → Task 2, enforced in Tasks 5 (save) + 6 (send). ✓
- §4 Presets (Brevo, Resend, Mailgun, n8n, Slack, Custom) → Task 4. ✓
- §3 Delivery-status record on the CPT (pending/sent/failed + HTTP status + snippet) → Tasks 6 + 7. ✓
- §3 Retention setting drives the purge → Task 7. ✓
- §4 Settings → Forms tab (destinations + secrets, admin-only) → Task 8. ✓
- §5 Child-theme filters: `pediment_form_destinations` (T5), `pediment_form_presets` (T4), `pediment_form_template_tokens` (T3), `pediment_form_allowed_hosts` (T2), `pediment_form_submission_received` (T6), `pediment_form_request_args` (T6). ✓ (`pediment_form_field_types` is a Plan 1/field-rendering concern — out of scope here.)
- **Out of scope, deferred:** AI destination authoring (§7) + `pediment-ai/v1/draft-destination` (§8) → **Plan 3**. Contact-form migration/deprecation (§6) → **Plan 4**. Noted in the header.

**Placeholder scan:** No TBD/TODO; every code step carries complete, runnable code and exact commands.

**Type consistency:** Context shape `array{fields,meta}` is identical across `pediment_form_build_context()` (T6), `pediment_form_render_*()` (T3), and `pediment_form_resolve_token()` (T3). Destination shape (`id,label,method,url,headers,content_type,body_template,secret_refs`) is consistent across presets (T4), validation (T5), delivery (T6), and sanitize (T8). Status values (`pending`/`sent`/`failed`/`no_destination`) are consistent across T6 and T7. Constants `PEDIMENT_FORM_METHODS` / `PEDIMENT_FORM_CONTENT_TYPES` / `PEDIMENT_FORM_META_KEYS` defined once in T5 and reused in T8.
