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
 * Dry-run a destination: build a sample context, render the request, send it,
 * and return a human-readable result — without writing any submission or option.
 *
 * @param array<string,mixed> $dest Clean destination record (from pediment_form_sanitize_destination).
 * @return array{ok:bool,message:string}
 */
function pediment_form_test_destination( array $dest ): array {
	// Collect every field token referenced across url, body template, and headers.
	$fields    = array();
	$scan_srcs = array_merge(
		array( (string) ( $dest['url'] ?? '' ), (string) ( $dest['body_template'] ?? '' ) ),
		array_values( (array) ( $dest['headers'] ?? array() ) )
	);
	foreach ( $scan_srcs as $hay ) {
		foreach ( pediment_form_extract_tokens( (string) $hay ) as $tok ) {
			if ( 'field' === $tok['type'] ) {
				$fields[ $tok['name'] ] = 'sample';
			}
		}
	}

	$context = array(
		'fields' => $fields,
		'meta'   => array(
			'post_id'      => '0',
			'page_url'     => home_url( '/' ),
			'submitted_at' => gmdate( 'Y-m-d\TH:i:s\Z' ),
			'destination'  => (string) ( $dest['id'] ?? '' ),
		),
	);

	$url = pediment_form_render_url( (string) ( $dest['url'] ?? '' ), $context );
	if ( ! pediment_form_url_is_safe( $url ) ) {
		return array(
			'ok'      => false,
			// translators: (no placeholders).
			'message' => __( 'Test blocked: the destination URL is not HTTPS or resolves to a private host.', 'pediment' ),
		);
	}

	$content_type = (string) ( $dest['content_type'] ?? 'application/json' );
	$headers      = array( 'Content-Type' => $content_type );
	foreach ( (array) ( $dest['headers'] ?? array() ) as $hk => $hv ) {
		$headers[ (string) $hk ] = pediment_form_render_header_value( (string) $hv, $context );
	}

	$body = pediment_form_render_template( (string) ( $dest['body_template'] ?? '' ), $context, $content_type );

	$response = wp_remote_request(
		$url,
		array(
			'method'  => strtoupper( (string) ( $dest['method'] ?? 'POST' ) ),
			'headers' => $headers,
			'body'    => $body,
			'timeout' => 15,
		)
	);

	if ( is_wp_error( $response ) ) {
		return array(
			'ok'      => false,
			// translators: %s: error message from the HTTP transport.
			'message' => sprintf( __( 'Test request failed: %s', 'pediment' ), $response->get_error_message() ),
		);
	}

	$code    = (int) wp_remote_retrieve_response_code( $response );
	$snippet = substr( (string) wp_remote_retrieve_body( $response ), 0, 200 );
	$ok      = ( $code >= 200 && $code < 300 );
	// translators: 1: HTTP status code, 2: first 200 chars of response body.
	$message = sprintf( __( 'Test response: HTTP %1$d — %2$s', 'pediment' ), $code, $snippet );
	return array(
		'ok'      => $ok,
		'message' => $message,
	);
}

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

	$raw_id = (string) ( $raw['id'] ?? '' );
	$dest   = array(
		'id'            => sanitize_key( trim( (string) preg_replace( '/[^a-z0-9]+/i', '_', $raw_id ), '_' ) ),
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
				<tr>
					<th scope="row"><label for="pf-id"><?php esc_html_e( 'ID', 'pediment' ); ?></label></th>
					<td><input type="text" id="pf-id" name="id" class="regular-text pf-field-id" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="pf-label"><?php esc_html_e( 'Label', 'pediment' ); ?></label></th>
					<td><input type="text" id="pf-label" name="label" class="regular-text" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="pf-method"><?php esc_html_e( 'Method', 'pediment' ); ?></label></th>
					<td>
						<select id="pf-method" name="method" class="pf-field-method">
							<?php foreach ( PEDIMENT_FORM_METHODS as $m ) : ?>
								<option value="<?php echo esc_attr( $m ); ?>"><?php echo esc_html( $m ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="pf-url"><?php esc_html_e( 'URL', 'pediment' ); ?></label></th>
					<td><input type="url" id="pf-url" name="url" class="large-text code pf-field-url" placeholder="https://…" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="pf-ct"><?php esc_html_e( 'Content type', 'pediment' ); ?></label></th>
					<td>
						<select id="pf-ct" name="content_type" class="pf-field-content_type">
							<?php foreach ( PEDIMENT_FORM_CONTENT_TYPES as $ct ) : ?>
								<option value="<?php echo esc_attr( $ct ); ?>"><?php echo esc_html( $ct ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Headers', 'pediment' ); ?></th>
					<td class="pf-headers">
						<div class="pf-headers-rows">
							<div class="pf-header-row">
								<input type="text" name="header_keys[]" placeholder="<?php esc_attr_e( 'Header', 'pediment' ); ?>" />
								<input type="text" name="header_values[]" placeholder="<?php esc_attr_e( 'Value (tokens allowed)', 'pediment' ); ?>" class="code" />
							</div>
						</div>
						<button type="button" class="button pf-add-header"><?php esc_html_e( 'Add header', 'pediment' ); ?></button>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="pf-body"><?php esc_html_e( 'Body template', 'pediment' ); ?></label></th>
					<td>
						<textarea id="pf-body" name="body_template" rows="6" class="large-text code pf-field-body_template"></textarea>
						<p class="description"><?php esc_html_e( 'Tokens: {{ field:NAME }} {{ all_fields }} {{ meta:post_id|page_url|submitted_at|destination }} {{ secret:NAME }}', 'pediment' ); ?></p>
					</td>
				</tr>
			</table>
			<p class="submit">
				<?php submit_button( __( 'Save destination', 'pediment' ), 'primary', 'submit', false ); ?>
				<button type="submit" name="pediment_form_test" value="1" class="button"><?php echo esc_html__( 'Send test', 'pediment' ); ?></button>
			</p>
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
	set_transient(
		'pediment_forms_notice',
		array(
			'type'    => $type,
			'message' => $message,
		),
		30
	);
	wp_safe_redirect(
		add_query_arg(
			array(
				'post_type' => PEDIMENT_FORM_CPT,
				'page'      => PEDIMENT_FORM_SETTINGS_PAGE,
			),
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

/**
 * Handle saving general settings (retention days + default destination).
 */
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

/**
 * Handle saving a secret (add or update).
 */
function pediment_form_handle_save_secret(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Not allowed.', 'pediment' ) );
	}
	check_admin_referer( 'pediment_form_save_secret' );
	$name = isset( $_POST['secret_name'] ) ? sanitize_key( wp_unslash( $_POST['secret_name'] ) ) : '';
	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- secret value is a raw credential; sanitizing would corrupt arbitrary-character API keys.
	$value = isset( $_POST['secret_value'] ) ? (string) wp_unslash( $_POST['secret_value'] ) : '';
	if ( '' === $name || '' === $value ) {
		pediment_form_settings_redirect( 'error', __( 'Secret name and value are required.', 'pediment' ) );
		return;
	}
	pediment_form_secret_set( $name, $value );
	pediment_form_settings_redirect( 'updated', __( 'Secret saved.', 'pediment' ) );
}

/**
 * Handle deleting a secret.
 */
function pediment_form_handle_delete_secret(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Not allowed.', 'pediment' ) );
	}
	$name = isset( $_POST['secret_name'] ) ? sanitize_key( wp_unslash( $_POST['secret_name'] ) ) : '';
	check_admin_referer( 'pediment_form_delete_secret_' . $name );
	pediment_form_secret_set( $name, '' );
	pediment_form_settings_redirect( 'updated', __( 'Secret deleted.', 'pediment' ) );
}

/**
 * Handle saving (creating or updating) a destination.
 */
function pediment_form_handle_save_destination(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Not allowed.', 'pediment' ) );
	}
	check_admin_referer( 'pediment_form_save_destination' );
	$result = pediment_form_sanitize_destination( wp_unslash( $_POST ) );
	if ( ! empty( $result['errors'] ) ) {
		pediment_form_settings_redirect( 'error', implode( ' ', $result['errors'] ) );
	}
	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce already verified by check_admin_referer above.
	if ( isset( $_POST['pediment_form_test'] ) ) {
		$test = pediment_form_test_destination( $result['dest'] );
		pediment_form_settings_redirect( $test['ok'] ? 'updated' : 'error', $test['message'] );
		return;
	}
	pediment_form_save_destination( $result['dest'] );
	pediment_form_settings_redirect( 'updated', __( 'Destination saved.', 'pediment' ) );
}

/**
 * Handle deleting a destination.
 */
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
