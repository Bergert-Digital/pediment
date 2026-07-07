# Pediment Theme Settings Tabs Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Move the forms settings UI from the Form Submissions CPT submenu to a tabbed **Settings → Pediment Theme** page (General / Form Destinations / Secrets), backed by a reusable tab framework.

**Architecture:** A new `inc/settings-page.php` owns a generic tab registry and the `add_options_page()` shell (heading, `nav-tab-wrapper`, active-tab resolution). `inc/forms-settings.php` stops registering its own submenu and instead registers three tabs, splitting its one render function into three body callbacks. All sanitize/save/handler logic is unchanged; only the page shell, redirect target, and enqueue condition move.

**Tech Stack:** WordPress theme PHP (procedural, `manage_options`), PHPUnit (`WP_UnitTestCase`), phpcs (WordPress standard).

## Global Constraints

- No color literals in CSS/SCSS (CI `lint:colors`) — N/A here, no styles added.
- phpcs must pass with **zero warnings** (CI `phpcs`) — every echo escaped, every superglobal read sanitized + unslashed, docblocks on all functions, `translators:` comments on all `sprintf`/placeholder strings.
- Text domain is `pediment` for all i18n strings.
- Capability gate is `manage_options` everywhere.
- New page slug: `pediment-theme`. New constant: `PEDIMENT_SETTINGS_PAGE = 'pediment-theme'`.
- Tabs and order: `general` (General, priority 10), `destinations` (Form Destinations, priority 20), `secrets` (Secrets, priority 30). Default tab is `general`.

---

### Task 1: Tab framework (`inc/settings-page.php`)

**Files:**
- Create: `inc/settings-page.php`
- Modify: `functions.php` (add `require_once` before `inc/forms-settings.php`)
- Test: `tests/phpunit/Settings/TabsTest.php`

**Interfaces:**
- Produces:
  - `const PEDIMENT_SETTINGS_PAGE = 'pediment-theme';`
  - `pediment_settings_register_tab( string $id, string $label, callable $render, int $priority = 10 ): void`
  - `pediment_settings_get_tabs(): array` — tabs keyed by id, sorted by priority then registration order; each is `array{id:string,label:string,render:callable,priority:int}`.
  - `pediment_settings_resolve_active_tab( string $requested, array $tabs ): string` — returns `$requested` when it is a non-empty key of `$tabs`, else the first key, else `''`.
  - `pediment_settings_page_url( string $tab = '' ): string` — `options-general.php?page=pediment-theme[&tab=…]`.
  - `pediment_settings_render_page(): void` — the options-page shell callback.

- [ ] **Step 1: Write the failing test**

Create `tests/phpunit/Settings/TabsTest.php`:

```php
<?php

class TabsTest extends WP_UnitTestCase {
	public function set_up(): void {
		parent::set_up();
		$GLOBALS['pediment_settings_tabs'] = array();
	}

	public function tear_down(): void {
		unset( $GLOBALS['pediment_settings_tabs'] );
		parent::tear_down();
	}

	public function test_get_tabs_sorts_by_priority() {
		pediment_settings_register_tab( 'b', 'B', '__return_null', 30 );
		pediment_settings_register_tab( 'a', 'A', '__return_null', 10 );
		pediment_settings_register_tab( 'c', 'C', '__return_null', 20 );
		$this->assertSame( array( 'a', 'c', 'b' ), array_keys( pediment_settings_get_tabs() ) );
	}

	public function test_register_same_id_overwrites() {
		pediment_settings_register_tab( 'x', 'First', '__return_null', 10 );
		pediment_settings_register_tab( 'x', 'Second', '__return_null', 10 );
		$tabs = pediment_settings_get_tabs();
		$this->assertCount( 1, $tabs );
		$this->assertSame( 'Second', $tabs['x']['label'] );
	}

	public function test_resolve_active_tab_prefers_requested_when_known() {
		$tabs = array( 'a' => array(), 'b' => array() );
		$this->assertSame( 'b', pediment_settings_resolve_active_tab( 'b', $tabs ) );
	}

	public function test_resolve_active_tab_falls_back_to_first_when_unknown() {
		$tabs = array( 'a' => array(), 'b' => array() );
		$this->assertSame( 'a', pediment_settings_resolve_active_tab( 'nope', $tabs ) );
		$this->assertSame( 'a', pediment_settings_resolve_active_tab( '', $tabs ) );
	}

	public function test_page_url_includes_page_and_optional_tab() {
		$this->assertStringContainsString( 'page=' . PEDIMENT_SETTINGS_PAGE, pediment_settings_page_url() );
		$url = pediment_settings_page_url( 'secrets' );
		$this->assertStringContainsString( 'page=' . PEDIMENT_SETTINGS_PAGE, $url );
		$this->assertStringContainsString( 'tab=secrets', $url );
	}
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `npx wp-env run tests-wordpress --env-cwd=wp-content/themes/pediment vendor/bin/phpunit --filter TabsTest`
Expected: FAIL — `Error: Call to undefined function pediment_settings_register_tab()` (and `PEDIMENT_SETTINGS_PAGE` undefined).

- [ ] **Step 3: Create the framework file**

Create `inc/settings-page.php`:

```php
<?php
/**
 * Pediment Theme settings: a tabbed options page under Settings.
 *
 * Register a tab with pediment_settings_register_tab(); the page shell (heading,
 * nav tabs, notices) is owned here and each tab supplies only its own body markup.
 *
 * @package Pediment
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const PEDIMENT_SETTINGS_PAGE = 'pediment-theme';

/**
 * Register a settings tab.
 *
 * @param string   $id       Unique tab id (used in the URL and as the array key).
 * @param string   $label    Human-readable tab label.
 * @param callable $render   Callback that echoes the tab body.
 * @param int      $priority Sort order; lower renders first. Default 10.
 */
function pediment_settings_register_tab( string $id, string $label, callable $render, int $priority = 10 ): void {
	if ( ! isset( $GLOBALS['pediment_settings_tabs'] ) || ! is_array( $GLOBALS['pediment_settings_tabs'] ) ) {
		$GLOBALS['pediment_settings_tabs'] = array();
	}
	$GLOBALS['pediment_settings_tabs'][ $id ] = array(
		'id'       => $id,
		'label'    => $label,
		'render'   => $render,
		'priority' => $priority,
	);
}

/**
 * All registered tabs, sorted by priority then registration order.
 *
 * @return array<string,array{id:string,label:string,render:callable,priority:int}>
 */
function pediment_settings_get_tabs(): array {
	$tabs = isset( $GLOBALS['pediment_settings_tabs'] ) && is_array( $GLOBALS['pediment_settings_tabs'] )
		? $GLOBALS['pediment_settings_tabs']
		: array();
	uasort(
		$tabs,
		static function ( array $a, array $b ): int {
			return $a['priority'] <=> $b['priority'];
		}
	);
	return $tabs;
}

/**
 * Resolve which tab is active for a request.
 *
 * @param string              $requested Requested tab id (e.g. from $_GET['tab']).
 * @param array<string,mixed> $tabs      Registered tabs, keyed by id (assumed pre-sorted).
 * @return string Active tab id; the first registered tab when $requested is unknown or empty.
 */
function pediment_settings_resolve_active_tab( string $requested, array $tabs ): string {
	if ( '' !== $requested && isset( $tabs[ $requested ] ) ) {
		return $requested;
	}
	$ids = array_keys( $tabs );
	return $ids[0] ?? '';
}

/**
 * Admin URL for the settings page, optionally deep-linked to a tab.
 *
 * @param string $tab Tab id, or '' for the default tab.
 * @return string
 */
function pediment_settings_page_url( string $tab = '' ): string {
	$args = array( 'page' => PEDIMENT_SETTINGS_PAGE );
	if ( '' !== $tab ) {
		$args['tab'] = $tab;
	}
	return add_query_arg( $args, admin_url( 'options-general.php' ) );
}

add_action(
	'admin_menu',
	function () {
		add_options_page(
			__( 'Pediment Theme', 'pediment' ),
			__( 'Pediment Theme', 'pediment' ),
			'manage_options',
			PEDIMENT_SETTINGS_PAGE,
			'pediment_settings_render_page'
		);
	}
);

/**
 * Render the tabbed settings page shell and delegate to the active tab body.
 */
function pediment_settings_render_page(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$tabs = pediment_settings_get_tabs();
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only tab switch, changes no state.
	$requested = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '';
	$active    = pediment_settings_resolve_active_tab( $requested, $tabs );
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Pediment Theme', 'pediment' ); ?></h1>
		<?php settings_errors(); ?>
		<h2 class="nav-tab-wrapper">
			<?php
			foreach ( $tabs as $id => $tab ) :
				$classes = 'nav-tab' . ( $id === $active ? ' nav-tab-active' : '' );
				?>
				<a href="<?php echo esc_url( pediment_settings_page_url( $id ) ); ?>" class="<?php echo esc_attr( $classes ); ?>"><?php echo esc_html( (string) $tab['label'] ); ?></a>
			<?php endforeach; ?>
		</h2>
		<?php
		if ( isset( $tabs[ $active ] ) && is_callable( $tabs[ $active ]['render'] ) ) {
			call_user_func( $tabs[ $active ]['render'] );
		}
		?>
	</div>
	<?php
}
```

- [ ] **Step 4: Wire the include**

In `functions.php`, add the require immediately before the `forms-settings.php` line (currently line 30):

```php
require_once __DIR__ . '/inc/forms-delivery.php';
require_once __DIR__ . '/inc/settings-page.php';
require_once __DIR__ . '/inc/forms-settings.php';
```

- [ ] **Step 5: Run test to verify it passes**

Run: `npx wp-env run tests-wordpress --env-cwd=wp-content/themes/pediment vendor/bin/phpunit --filter TabsTest`
Expected: PASS (5 tests).

- [ ] **Step 6: Lint**

Run: `vendor/bin/phpcs inc/settings-page.php` (CI runs `composer lint` = `phpcs` over the whole tree)
Expected: no errors, no warnings.

- [ ] **Step 7: Commit**

```bash
git add inc/settings-page.php functions.php tests/phpunit/Settings/TabsTest.php
git commit -m "feat(settings): reusable tabbed Pediment Theme options page framework"
```

---

### Task 2: Move forms settings onto tabs (`inc/forms-settings.php`)

**Files:**
- Modify: `inc/forms-settings.php`
- Test: `tests/phpunit/Settings/FormsTabsTest.php`

**Interfaces:**
- Consumes: `pediment_settings_register_tab()`, `pediment_settings_page_url()`, `PEDIMENT_SETTINGS_PAGE` (Task 1).
- Produces:
  - `pediment_form_render_general_tab(): void`
  - `pediment_form_render_destinations_tab(): void`
  - `pediment_form_render_secrets_tab(): void`
  - `pediment_form_destination_form_values( string $edit_id ): array` — field defaults for the add/edit form. Keys: `id`, `label`, `method`, `url`, `content_type`, `headers` (assoc), `body_template`, `is_edit` (bool). Empty/unknown `$edit_id` → blank add-mode defaults with `is_edit === false`.
  - `pediment_form_settings_redirect( string $type, string $message, string $tab = 'general' ): void` (adds `$tab`).
- Removes: `const PEDIMENT_FORM_SETTINGS_PAGE`, the `add_submenu_page()` registration, and `pediment_form_render_settings_page()`.

- [ ] **Step 1: Write the failing test**

Create `tests/phpunit/Settings/FormsTabsTest.php`:

```php
<?php

class FormsTabsTest extends WP_UnitTestCase {
	public function set_up(): void {
		parent::set_up();
		$GLOBALS['pediment_settings_tabs'] = array();
		do_action( 'admin_menu' );
	}

	public function tear_down(): void {
		unset( $GLOBALS['pediment_settings_tabs'] );
		parent::tear_down();
	}

	public function test_forms_registers_three_tabs_in_order() {
		$tabs = pediment_settings_get_tabs();
		$this->assertSame( array( 'general', 'destinations', 'secrets' ), array_keys( $tabs ) );
	}

	public function test_forms_tab_labels() {
		$tabs = pediment_settings_get_tabs();
		$this->assertSame( 'General', $tabs['general']['label'] );
		$this->assertSame( 'Form Destinations', $tabs['destinations']['label'] );
		$this->assertSame( 'Secrets', $tabs['secrets']['label'] );
	}

	public function test_each_tab_render_is_callable() {
		$tabs = pediment_settings_get_tabs();
		foreach ( array( 'general', 'destinations', 'secrets' ) as $id ) {
			$this->assertTrue( is_callable( $tabs[ $id ]['render'] ), "$id render not callable" );
		}
	}

	public function test_form_values_blank_for_empty_id() {
		$v = pediment_form_destination_form_values( '' );
		$this->assertFalse( $v['is_edit'] );
		$this->assertSame( '', $v['id'] );
		$this->assertSame( 'POST', $v['method'] );
		$this->assertSame( 'application/json', $v['content_type'] );
		$this->assertSame( array(), $v['headers'] );
	}

	public function test_form_values_blank_for_unknown_id() {
		$v = pediment_form_destination_form_values( 'does_not_exist' );
		$this->assertFalse( $v['is_edit'] );
		$this->assertSame( '', $v['id'] );
	}

	public function test_form_values_prefills_stored_destination() {
		update_option(
			PEDIMENT_FORM_DESTINATIONS_OPTION,
			array(
				array(
					'id'            => 'brevo',
					'label'         => 'Brevo main',
					'method'        => 'POST',
					'url'           => 'https://api.brevo.com/v3/smtp/email',
					'content_type'  => 'application/json',
					'headers'       => array( 'api-key' => '{{ secret:brevo_api_key }}' ),
					'body_template' => '{"x":"{{ all_fields }}"}',
					'secret_refs'   => array( 'brevo_api_key' ),
				),
			)
		);
		$v = pediment_form_destination_form_values( 'brevo' );
		$this->assertTrue( $v['is_edit'] );
		$this->assertSame( 'brevo', $v['id'] );
		$this->assertSame( 'Brevo main', $v['label'] );
		$this->assertSame( 'https://api.brevo.com/v3/smtp/email', $v['url'] );
		$this->assertSame( array( 'api-key' => '{{ secret:brevo_api_key }}' ), $v['headers'] );
		delete_option( PEDIMENT_FORM_DESTINATIONS_OPTION );
	}
}
```

Note: `pediment_form_destinations()` returns destinations keyed by id, so the lookup in the helper is `$destinations[ $edit_id ]`.

- [ ] **Step 2: Run test to verify it fails**

Run: `npx wp-env run tests-wordpress --env-cwd=wp-content/themes/pediment vendor/bin/phpunit --filter FormsTabsTest`
Expected: FAIL — `pediment_settings_get_tabs()` returns an empty array (forms still registers a submenu, not tabs), so `array_keys` assertion fails.

- [ ] **Step 3: Replace the page registration with tab registration**

In `inc/forms-settings.php`, delete the constant on line 12:

```php
const PEDIMENT_FORM_SETTINGS_PAGE = 'pediment-forms';
```

and delete the entire `add_action( 'admin_menu', function () { add_submenu_page( … ); } );` block (lines 184–196). Replace both with a single tab-registration block placed where the `admin_menu` block was:

```php
add_action(
	'admin_menu',
	function () {
		pediment_settings_register_tab( 'general', __( 'General', 'pediment' ), 'pediment_form_render_general_tab', 10 );
		pediment_settings_register_tab( 'destinations', __( 'Form Destinations', 'pediment' ), 'pediment_form_render_destinations_tab', 20 );
		pediment_settings_register_tab( 'secrets', __( 'Secrets', 'pediment' ), 'pediment_form_render_secrets_tab', 30 );
	}
);
```

- [ ] **Step 4: Split the render function into three tab bodies**

Replace `pediment_form_render_settings_page()` (lines 198–371) with three functions. Each keeps its existing inner markup verbatim, minus the shared `<div class="wrap">`, `<h1>`, and `settings_errors()` (now owned by the framework), and minus the `<hr />` separators between sections.

```php
/**
 * General tab: retention days + default destination.
 */
function pediment_form_render_general_tab(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$destinations = pediment_form_destinations();
	$retention    = (int) get_option( PEDIMENT_FORM_RETENTION_OPTION, 90 );
	$default_dest = (string) get_option( PEDIMENT_FORM_DEFAULT_DEST_OPTION, '' );
	?>
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
	<?php
}

/**
 * Secrets tab: encrypted credential list + add form.
 */
function pediment_form_render_secrets_tab(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$secrets = pediment_form_secret_names();
	?>
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
	<?php
}

/**
 * Field defaults for the add/edit destination form.
 *
 * @param string $edit_id Destination id to edit, or '' for add mode.
 * @return array{id:string,label:string,method:string,url:string,content_type:string,headers:array<string,string>,body_template:string,is_edit:bool}
 */
function pediment_form_destination_form_values( string $edit_id ): array {
	$blank = array(
		'id'            => '',
		'label'         => '',
		'method'        => 'POST',
		'url'           => '',
		'content_type'  => 'application/json',
		'headers'       => array(),
		'body_template' => '',
		'is_edit'       => false,
	);
	if ( '' === $edit_id ) {
		return $blank;
	}
	$destinations = pediment_form_destinations();
	if ( ! isset( $destinations[ $edit_id ] ) ) {
		return $blank;
	}
	$d       = $destinations[ $edit_id ];
	$headers = ( isset( $d['headers'] ) && is_array( $d['headers'] ) ) ? $d['headers'] : array();
	return array(
		'id'            => (string) ( $d['id'] ?? $edit_id ),
		'label'         => (string) ( $d['label'] ?? '' ),
		'method'        => (string) ( $d['method'] ?? 'POST' ),
		'url'           => (string) ( $d['url'] ?? '' ),
		'content_type'  => (string) ( $d['content_type'] ?? 'application/json' ),
		'headers'       => $headers,
		'body_template' => (string) ( $d['body_template'] ?? '' ),
		'is_edit'       => true,
	);
}

/**
 * Form Destinations tab: destinations table + add/edit editor.
 */
function pediment_form_render_destinations_tab(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$destinations = pediment_form_destinations();
	$presets      = pediment_form_presets();
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only pre-fill of the edit form, changes no state.
	$edit_id  = isset( $_GET['edit'] ) ? sanitize_key( wp_unslash( $_GET['edit'] ) ) : '';
	$values   = pediment_form_destination_form_values( $edit_id );
	$readonly = $values['is_edit'] ? 'readonly' : '';
	$rows     = ! empty( $values['headers'] ) ? $values['headers'] : array( '' => '' );
	?>
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
						<a href="<?php echo esc_url( add_query_arg( 'edit', $id, pediment_settings_page_url( 'destinations' ) ) ); ?>" class="button-link"><?php esc_html_e( 'Edit', 'pediment' ); ?></a>
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

	<h3>
		<?php
		if ( $values['is_edit'] ) {
			/* translators: %s: destination id being edited. */
			printf( esc_html__( 'Edit destination: %s', 'pediment' ), esc_html( $values['id'] ) );
		} else {
			esc_html_e( 'Add / edit destination', 'pediment' );
		}
		?>
	</h3>
	<?php if ( $values['is_edit'] ) : ?>
		<p><a href="<?php echo esc_url( pediment_settings_page_url( 'destinations' ) ); ?>"><?php esc_html_e( 'Cancel / add new', 'pediment' ); ?></a></p>
	<?php endif; ?>
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
			<tr>
				<th scope="row"><label for="pf-id"><?php esc_html_e( 'ID', 'pediment' ); ?></label></th>
				<td><input type="text" id="pf-id" name="id" class="regular-text pf-field-id" value="<?php echo esc_attr( $values['id'] ); ?>" <?php echo esc_attr( $readonly ); ?> /></td>
			</tr>
			<tr>
				<th scope="row"><label for="pf-label"><?php esc_html_e( 'Label', 'pediment' ); ?></label></th>
				<td><input type="text" id="pf-label" name="label" class="regular-text" value="<?php echo esc_attr( $values['label'] ); ?>" /></td>
			</tr>
			<tr>
				<th scope="row"><label for="pf-method"><?php esc_html_e( 'Method', 'pediment' ); ?></label></th>
				<td>
					<select id="pf-method" name="method" class="pf-field-method">
						<?php foreach ( PEDIMENT_FORM_METHODS as $m ) : ?>
							<option value="<?php echo esc_attr( $m ); ?>" <?php selected( $values['method'], $m ); ?>><?php echo esc_html( $m ); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="pf-url"><?php esc_html_e( 'URL', 'pediment' ); ?></label></th>
				<td><input type="url" id="pf-url" name="url" class="large-text code pf-field-url" placeholder="https://…" value="<?php echo esc_attr( $values['url'] ); ?>" /></td>
			</tr>
			<tr>
				<th scope="row"><label for="pf-ct"><?php esc_html_e( 'Content type', 'pediment' ); ?></label></th>
				<td>
					<select id="pf-ct" name="content_type" class="pf-field-content_type">
						<?php foreach ( PEDIMENT_FORM_CONTENT_TYPES as $ct ) : ?>
							<option value="<?php echo esc_attr( $ct ); ?>" <?php selected( $values['content_type'], $ct ); ?>><?php echo esc_html( $ct ); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Headers', 'pediment' ); ?></th>
				<td class="pf-headers">
					<div class="pf-headers-rows">
						<?php foreach ( $rows as $hk => $hv ) : ?>
							<div class="pf-header-row">
								<input type="text" name="header_keys[]" placeholder="<?php esc_attr_e( 'Header', 'pediment' ); ?>" value="<?php echo esc_attr( (string) $hk ); ?>" />
								<input type="text" name="header_values[]" placeholder="<?php esc_attr_e( 'Value (tokens allowed)', 'pediment' ); ?>" class="code" value="<?php echo esc_attr( (string) $hv ); ?>" />
							</div>
						<?php endforeach; ?>
					</div>
					<button type="button" class="button pf-add-header"><?php esc_html_e( 'Add header', 'pediment' ); ?></button>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="pf-body"><?php esc_html_e( 'Body template', 'pediment' ); ?></label></th>
				<td>
					<textarea id="pf-body" name="body_template" rows="6" class="large-text code pf-field-body_template"><?php echo esc_textarea( $values['body_template'] ); ?></textarea>
					<p class="description"><?php esc_html_e( 'Tokens: {{ field:NAME }} {{ all_fields }} {{ meta:post_id|page_url|submitted_at|destination }} {{ secret:NAME }}', 'pediment' ); ?></p>
				</td>
			</tr>
		</table>
		<p class="submit">
			<?php submit_button( $values['is_edit'] ? __( 'Update destination', 'pediment' ) : __( 'Save destination', 'pediment' ), 'primary', 'submit', false ); ?>
			<button type="submit" name="pediment_form_test" value="1" class="button"><?php echo esc_html__( 'Send test', 'pediment' ); ?></button>
		</p>
	</form>
	<?php
}
```

**Edit-flow notes for the implementer:**
- The `readonly` id on edit is intentional — the save handler upserts by id, so an editable id would create a new record (a "rename" makes a copy) rather than update. Do not change the save/sanitize logic.
- The preset JS (`assets/js/admin-forms-settings.js`) is unchanged: `fillFromPreset` only runs on the preset `<select>` **change** event and never touches the id/label fields, so it does not clobber the server pre-filled values on page load. No JS edit is required.
- Secret tokens (`{{ secret:NAME }}`) in url/headers/body are references, not values — rendering them back into the form (`esc_attr` / `esc_textarea`) leaks nothing.

- [ ] **Step 5: Run test to verify it passes**

Run: `npx wp-env run tests-wordpress --env-cwd=wp-content/themes/pediment vendor/bin/phpunit --filter FormsTabsTest`
Expected: PASS (3 tests).

- [ ] **Step 6: Point the redirect at the new page and per-tab**

Replace the body of `pediment_form_settings_redirect()` (lines 385–404) — add a `$tab` param and use the framework URL:

```php
/**
 * Redirect back to the settings page with a notice.
 *
 * @param string $type    'updated' or 'error'.
 * @param string $message Notice text.
 * @param string $tab     Tab to return to. Default 'general'.
 */
function pediment_form_settings_redirect( string $type, string $message, string $tab = 'general' ): void {
	set_transient(
		'pediment_forms_notice',
		array(
			'type'    => $type,
			'message' => $message,
		),
		30
	);
	wp_safe_redirect( pediment_settings_page_url( $tab ) );
	exit;
}
```

- [ ] **Step 7: Pass the correct tab from each handler**

Update every `pediment_form_settings_redirect( … )` call to pass its tab:

- In `pediment_form_handle_save_general()` (line 433): add `, 'general'`
  `pediment_form_settings_redirect( 'updated', __( 'Settings saved.', 'pediment' ), 'general' );`
- In `pediment_form_handle_save_secret()` (lines 448, 452) and `pediment_form_handle_delete_secret()` (line 465): add `, 'secrets'` to each call.
- In `pediment_form_handle_save_destination()` (lines 478, 484, 488) and `pediment_form_handle_delete_destination()` (line 501): add `, 'destinations'` to each call.

- [ ] **Step 8: Fix the enqueue hook-suffix condition**

Replace the enqueue guard (lines 504–519). The options-page hook suffix is `settings_page_pediment-theme`:

```php
add_action(
	'admin_enqueue_scripts',
	function ( $hook_suffix ) {
		if ( 'settings_page_' . PEDIMENT_SETTINGS_PAGE !== $hook_suffix ) {
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

- [ ] **Step 9: Update the file docblock**

Change the header comment on line 3 from `Settings → Forms:` to reflect the new home:

```php
 * Forms settings tabs (General, Destinations, Secrets) for the Pediment Theme settings page.
```

- [ ] **Step 10: Run the full forms + settings suites**

Run: `npx wp-env run tests-wordpress --env-cwd=wp-content/themes/pediment vendor/bin/phpunit --filter 'Forms|Settings|ContactForm'`
Expected: PASS — no regressions in existing suites; TabsTest + FormsTabsTest green.

- [ ] **Step 11: Lint**

Run: `vendor/bin/phpcs inc/forms-settings.php`
Expected: no errors, no warnings.

- [ ] **Step 12: Commit**

```bash
git add inc/forms-settings.php tests/phpunit/Settings/FormsTabsTest.php
git commit -m "feat(forms): move forms settings to Settings → Pediment Theme tabs"
```

---

### Task 3: Manual verification

**Files:** none (verification only).

- [ ] **Step 1: Build and boot the dev env, then verify in Chrome**

Confirm all of the following in **Settings → Pediment Theme**:
- The page appears under **Settings** (not under Form Submissions — that submenu is gone).
- Three tabs render in order: **General**, **Form Destinations**, **Secrets**.
- Saving on **General** (retention/default) round-trips and shows the success notice on the General tab.
- Adding/deleting a **Secret** round-trips and shows its notice on the Secrets tab.
- Adding a **Destination**, choosing a preset, adding a header row, and **Send test** all work and land back on the Form Destinations tab with the right notice.
- **Editing a destination:** clicking **Edit** on a table row pre-fills the form (id shown **readonly**, label/url/method/content-type/headers/body populated); the heading reads "Edit destination: `<id>`" and the button "Update destination". Saving updates that record in place (no duplicate row). **Cancel / add new** returns to a blank form. Editing then choosing a preset still repopulates url/method/content-type/headers/body (the id stays as typed).
- Deep link `options-general.php?page=pediment-theme&tab=secrets` opens with Secrets active; an unknown `&tab=bogus` falls back to General; `&tab=destinations&edit=bogus` shows the blank add form.

- [ ] **Step 2: Run the e2e suite** (guards against unrelated regressions)

Run: `npm run e2e`
Expected: existing form specs pass (they do not depend on the settings page location).

---

## Self-Review

**Spec coverage:**
- New location (Settings → Pediment Theme, `add_options_page`, slug `pediment-theme`) → Task 1 Step 3.
- Tab framework (`register`, `get`, `resolve`, `page_url`, shell) → Task 1.
- Three tabs General/Destinations/Secrets with order → Task 2 Steps 3–4, tested Steps 1/5.
- Editing destinations (per-row Edit link, server pre-fill, readonly id, Update button, Cancel link) → Task 2 Step 4 (`pediment_form_destination_form_values` + edit-aware render), tested Step 1 (`test_form_values_*`).
- Redirect preserves tab → Task 2 Steps 6–7.
- Enqueue hook-suffix update → Task 2 Step 8.
- Handlers/logic unchanged → confirmed (only redirect calls gain a tab arg).
- Constant removed/repointed → Task 2 Step 3 (removes `PEDIMENT_FORM_SETTINGS_PAGE`); new `PEDIMENT_SETTINGS_PAGE` in Task 1.
- Include order (framework before forms) → Task 1 Step 4.
- Testing (existing suites keep passing + new tab tests + manual) → Tasks 1, 2, 3.

**Placeholder scan:** none — every step has concrete code/commands.

**Type consistency:** `pediment_settings_register_tab`, `pediment_settings_get_tabs`, `pediment_settings_resolve_active_tab`, `pediment_settings_page_url`, `PEDIMENT_SETTINGS_PAGE`, and the three `pediment_form_render_*_tab` names are used identically across tasks. Redirect signature `(string, string, string='general')` matches all updated call sites.

**Real command reference (verified):** PHPUnit runs through wp-env — `npx wp-env run tests-wordpress --env-cwd=wp-content/themes/pediment vendor/bin/phpunit [--filter X]`. phpcs is `composer lint` (whole tree) or `vendor/bin/phpcs <file>` for one file. e2e is `npm run e2e` (Playwright). CI is the source of truth (jobs: `phpcs`, `phpunit`, `e2e`, `lint-blocks`).

**wp-env theme-slug caveat:** the wp-env/e2e setup hardcodes the theme slug `pediment` and `--env-cwd=wp-content/themes/pediment`, but this workspace directory is `providence`. Local wp-env commands may not resolve here. If they fail, the executor should either run against the pediment-named env / parent env, or rely on CI (push the branch and let the `phpunit`/`phpcs`/`e2e` jobs verify). Do **not** conclude the code is broken solely because a wp-env command fails to start in this workspace — probe first.
