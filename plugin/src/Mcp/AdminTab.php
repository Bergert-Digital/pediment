<?php
/**
 * "AI agents" tab in Settings → Pediment: checks what the MCP server
 * needs and connects an agent with one click.
 *
 * @package Pediment
 */

declare(strict_types=1);

namespace Pediment\Mcp;

use WP_Application_Passwords;
use WP_Error;
use WP_User;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin UI for the MCP server.
 */
final class AdminTab {

	public const ACTION = 'pediment_mcp_connect';

	private const ADAPTER_CLASS = 'WP\MCP\Core\McpAdapter';

	/**
	 * Registers the tab.
	 */
	public static function register(): void {
		add_action(
			'admin_menu',
			static function () {
				pediment_settings_register_tab( 'mcp', __( 'AI agents', 'pediment' ), array( self::class, 'render' ), 50 );
			}
		);
	}

	/**
	 * Whether the MCP Adapter plugin is loaded. Without it the endpoint does not exist.
	 */
	public static function adapterActive(): bool {
		return class_exists( self::ADAPTER_CLASS );
	}

	/**
	 * The server's endpoint. rest_url() gives the `?rest_route=` form on plain permalinks.
	 */
	public static function endpoint(): string {
		return rest_url( Server::REST_NAMESPACE . '/' . Server::ROUTE );
	}

	/**
	 * Creates an application password when the connect form was posted.
	 *
	 * The password is only rendered into this response, never stored: WordPress
	 * keeps a hash, so the page that creates it is the only place it can be shown.
	 *
	 * @param WP_User $user The user the agent will act as.
	 * @return array{password:string,name:string}|WP_Error|null Null when the form was not posted.
	 */
	public static function handlePost( WP_User $user ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified below, before anything is written.
		$action = isset( $_POST['pediment_mcp_action'] ) ? sanitize_key( wp_unslash( $_POST['pediment_mcp_action'] ) ) : '';
		if ( 'connect' !== $action ) {
			return null;
		}
		$nonce = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, self::ACTION ) ) {
			return new WP_Error( 'pediment_mcp_nonce', __( 'That form had expired, so no password was created. Please try again.', 'pediment' ) );
		}
		if ( ! current_user_can( 'create_app_password', $user->ID ) || ! wp_is_application_passwords_available_for_user( $user ) ) {
			return new WP_Error( 'pediment_mcp_forbidden', __( 'You cannot create an application password on this site.', 'pediment' ) );
		}
		$name = isset( $_POST['pediment_mcp_name'] ) ? sanitize_text_field( wp_unslash( $_POST['pediment_mcp_name'] ) ) : '';
		if ( '' === $name ) {
			$name = self::defaultName();
		}
		$created = WP_Application_Passwords::create_new_application_password( $user->ID, array( 'name' => $name ) );
		if ( is_wp_error( $created ) ) {
			return $created;
		}
		return array(
			'password' => $created[0],
			'name'     => $name,
		);
	}

	/**
	 * Builds the client configurations for one user and password.
	 *
	 * @param WP_User $user     The password's owner.
	 * @param string  $password The plain application password.
	 * @return array{server:string,url:string,header:string,command:string,codex:string}
	 */
	public static function connection( WP_User $user, string $password ): array {
		$host   = (string) wp_parse_url( home_url(), PHP_URL_HOST );
		$server = 'pediment-' . sanitize_title( str_replace( '.', '-', $host ) );
		$url    = self::endpoint();
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- HTTP Basic auth.
		$basic = base64_encode( $user->user_login . ':' . $password );

		return array(
			'server'  => $server,
			'url'     => $url,
			'header'  => 'Authorization: Basic ' . $basic,
			'command' => sprintf( 'claude mcp add --transport http --scope user %s "%s" --header "Authorization: Basic %s"', $server, $url, $basic ),
			// `codex mcp add` only sends Bearer tokens, so Basic auth goes into config.toml.
			'codex'   => sprintf( "[mcp_servers.%s]\nurl = \"%s\"\nhttp_headers = { \"Authorization\" = \"Basic %s\" }", $server, $url, $basic ),
		);
	}

	/**
	 * Renders the tab body.
	 */
	public static function render(): void {
		$user      = wp_get_current_user();
		$result    = self::handlePost( $user );
		$adapter   = self::adapterActive();
		$passwords = wp_is_application_passwords_available_for_user( $user );
		$ready     = $adapter && $passwords && current_user_can( 'create_app_password', $user->ID );

		if ( is_wp_error( $result ) ) {
			echo '<div class="notice notice-error"><p>' . esc_html( $result->get_error_message() ) . '</p></div>';
		}
		?>
		<p><?php esc_html_e( 'AI agents such as Claude Code or Codex build and change pages and site styles through Pediment’s MCP server. Pediment checks every change against the content and design rules before it writes.', 'pediment' ); ?></p>

		<h2><?php esc_html_e( 'Requirements', 'pediment' ); ?></h2>
		<ul>
			<li><?php self::check( $adapter, __( 'MCP Adapter plugin is active.', 'pediment' ), self::adapterHint() ); ?></li>
			<li><?php self::check( $passwords, __( 'Application passwords are available.', 'pediment' ), self::passwordHint() ); ?></li>
		</ul>
		<p><?php esc_html_e( 'Endpoint:', 'pediment' ); ?> <code><?php echo esc_html( self::endpoint() ); ?></code></p>

		<?php
		if ( is_array( $result ) ) {
			self::renderConnection( $user, $result );
		}
		?>

		<?php
		if ( is_array( $result ) ) {
			// The setup above is what the user acts on now; a second form in
			// full view reads like a step they still have to take.
			echo '<details class="pediment-mcp-another" style="margin-top:2em;"><summary style="cursor:pointer;font-weight:600;">' . esc_html__( 'Connect another agent', 'pediment' ) . '</summary>';
			self::renderConnectForm( $user, $ready );
			echo '</details>';
		} else {
			echo '<h2>' . esc_html__( 'Connect an agent', 'pediment' ) . '</h2>';
			self::renderConnectForm( $user, $ready );
		}
	}

	/**
	 * Renders the form that creates an application password.
	 *
	 * @param WP_User $user  The signed-in user.
	 * @param bool    $ready Whether every requirement is met.
	 */
	private static function renderConnectForm( WP_User $user, bool $ready ): void {
		?>
		<p>
			<?php
			echo esc_html(
				sprintf(
					/* translators: %s: user login */
					__( 'This creates an application password for your account (%s) and shows the setup for your agent. The agent acts as you: it may do what your role may do. To limit it, give the agent its own Editor account and create the password on that account’s profile instead; the style tools then refuse.', 'pediment' ),
					$user->user_login
				)
			);
			?>
		</p>
		<form method="post">
			<?php wp_nonce_field( self::ACTION ); ?>
			<input type="hidden" name="pediment_mcp_action" value="connect" />
			<p>
				<label for="pediment-mcp-name"><?php esc_html_e( 'Password name', 'pediment' ); ?></label><br />
				<input type="text" id="pediment-mcp-name" name="pediment_mcp_name" class="regular-text" value="<?php echo esc_attr( self::defaultName() ); ?>" <?php disabled( ! $ready ); ?> />
			</p>
			<?php submit_button( __( 'Create password and show setup', 'pediment' ), 'primary', 'submit', true, $ready ? array() : array( 'disabled' => 'disabled' ) ); ?>
		</form>
		<p>
			<?php esc_html_e( 'To disconnect an agent, revoke its password:', 'pediment' ); ?>
			<a href="<?php echo esc_url( admin_url( 'profile.php#application-passwords-section' ) ); ?>"><?php esc_html_e( 'Users → Profile → Application Passwords', 'pediment' ); ?></a>
		</p>
		<?php
	}

	/**
	 * Renders the one-time setup after a password was created.
	 *
	 * @param WP_User                             $user    The password's owner.
	 * @param array{password:string,name:string} $created The new password.
	 */
	private static function renderConnection( WP_User $user, array $created ): void {
		$c = self::connection( $user, $created['password'] );

		wp_enqueue_script( 'clipboard' );
		wp_add_inline_script(
			'clipboard',
			'new ClipboardJS(".pediment-mcp-copy").on("success",function(e){e.clearSelection();e.trigger.textContent=' . wp_json_encode( __( 'Copied', 'pediment' ) ) . ';});'
			// Without JS every panel stays visible, one under the other.
			. 'document.querySelectorAll(".pediment-mcp-clients").forEach(function(box){'
			. 'var tabs=box.querySelectorAll("[role=tab]");'
			. 'function show(tab){tabs.forEach(function(t){var on=t===tab;t.classList.toggle("nav-tab-active",on);t.setAttribute("aria-selected",on);box.querySelector(t.getAttribute("href")).hidden=!on;});}'
			. 'tabs.forEach(function(t){t.addEventListener("click",function(e){e.preventDefault();show(t);});});'
			. 'show(tabs[0]);});'
		);
		?>
		<div class="notice notice-success inline">
			<p>
				<strong>
				<?php
				echo esc_html(
					sprintf(
						/* translators: %s: application password name */
						__( 'Created the application password “%s”. Copy the setup now: WordPress shows the password only once.', 'pediment' ),
						$created['name']
					)
				);
				?>
				</strong>
			</p>
		</div>

		<div class="pediment-mcp-clients">
			<nav class="nav-tab-wrapper" role="tablist">
				<a href="#pediment-mcp-claude" class="nav-tab nav-tab-active" role="tab"><?php esc_html_e( 'Claude Code', 'pediment' ); ?></a>
				<a href="#pediment-mcp-codex" class="nav-tab" role="tab"><?php esc_html_e( 'ChatGPT / Codex', 'pediment' ); ?></a>
				<a href="#pediment-mcp-other" class="nav-tab" role="tab"><?php esc_html_e( 'Other clients', 'pediment' ); ?></a>
			</nav>

			<div id="pediment-mcp-claude" role="tabpanel">
				<p><?php esc_html_e( 'Run this once in a terminal. The server is then available in every folder. Check it with “claude mcp list”.', 'pediment' ); ?></p>
				<?php self::field( 'command', $c['command'], 3 ); ?>
			</div>

			<div id="pediment-mcp-codex" role="tabpanel">
				<p><?php esc_html_e( 'Add this to ~/.codex/config.toml. The Codex CLI, the Codex app and the IDE extension read it. Check it with “codex mcp list”.', 'pediment' ); ?></p>
				<?php self::field( 'codex', $c['codex'], 3 ); ?>
				<p class="description"><?php esc_html_e( 'ChatGPT in the browser and the ChatGPT desktop app connect custom MCP servers only with OAuth or without authentication, so they cannot use an application password. Use Codex.', 'pediment' ); ?></p>
			</div>

			<div id="pediment-mcp-other" role="tabpanel">
				<p><?php esc_html_e( 'Use the HTTP transport with this URL and header.', 'pediment' ); ?></p>
				<?php
				self::field( 'url', $c['url'], 1 );
				self::field( 'header', $c['header'], 1 );
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * Renders a read-only field with a copy button.
	 *
	 * @param string $id    Field id suffix.
	 * @param string $value Field value.
	 * @param int    $rows  Visible rows.
	 */
	private static function field( string $id, string $value, int $rows ): void {
		$field_id = 'pediment-mcp-' . $id;
		?>
		<p>
			<textarea id="<?php echo esc_attr( $field_id ); ?>" class="large-text code" rows="<?php echo esc_attr( (string) $rows ); ?>" readonly><?php echo esc_textarea( $value ); ?></textarea>
			<button type="button" class="button pediment-mcp-copy" data-clipboard-target="#<?php echo esc_attr( $field_id ); ?>"><?php esc_html_e( 'Copy', 'pediment' ); ?></button>
		</p>
		<?php
	}

	/**
	 * Renders one requirement line.
	 *
	 * @param bool   $ok    Whether the requirement is met.
	 * @param string $label What is required.
	 * @param string $hint  What to do when it is not met (may hold a link).
	 */
	private static function check( bool $ok, string $label, string $hint ): void {
		printf(
			'<span class="dashicons %1$s" aria-hidden="true"></span> %2$s',
			esc_attr( $ok ? 'dashicons-yes-alt' : 'dashicons-warning' ),
			esc_html( $label )
		);
		if ( ! $ok ) {
			echo ' ' . wp_kses( $hint, array( 'a' => array( 'href' => array() ) ) );
		}
	}

	/**
	 * What to do when the adapter is missing.
	 */
	private static function adapterHint(): string {
		$text = __( 'Install and activate it; without it the endpoint does not exist.', 'pediment' );
		if ( ! current_user_can( 'install_plugins' ) ) {
			return esc_html( $text );
		}
		$url = admin_url( 'plugin-install.php?tab=search&type=term&s=' . rawurlencode( 'MCP Adapter' ) );
		return esc_html( $text ) . ' <a href="' . esc_url( $url ) . '">' . esc_html__( 'Find the MCP Adapter', 'pediment' ) . '</a>';
	}

	/**
	 * What to do when application passwords are unavailable.
	 */
	private static function passwordHint(): string {
		if ( ! wp_is_application_passwords_supported() ) {
			return esc_html__( 'WordPress offers them only over HTTPS (or on a local environment). Serve the site over HTTPS.', 'pediment' );
		}
		return esc_html__( 'A plugin, the host or your role turns them off for your account.', 'pediment' );
	}

	/**
	 * A password name that is unique per second. WordPress refuses a duplicate
	 * name, and the collapsed form offers a second password right after the first.
	 */
	private static function defaultName(): string {
		return sprintf(
			/* translators: %s: date and time */
			__( 'Pediment MCP %s', 'pediment' ),
			wp_date( 'Y-m-d H:i:s' )
		);
	}
}
