<?php
/**
 * Pediment settings: a tabbed options page under Settings.
 *
 * Register a tab with pediment_settings_register_tab(); the page shell (heading,
 * nav tabs, notices) is owned here and each tab supplies only its own body markup.
 *
 * @package Pediment
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const PEDIMENT_SETTINGS_PAGE = 'pediment';

/**
 * The page's slug before it was renamed from "Pediment Theme".
 */
const PEDIMENT_SETTINGS_LEGACY_PAGE = 'pediment-theme';

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

/**
 * Where a request for the old page slug belongs, or null for any other page.
 *
 * @param string $page Requested page slug.
 * @param string $tab  Requested tab id.
 * @return string|null
 */
function pediment_settings_legacy_redirect_url( string $page, string $tab ): ?string {
	return PEDIMENT_SETTINGS_LEGACY_PAGE === $page ? pediment_settings_page_url( $tab ) : null;
}

// Old bookmarks and links name the old slug, which WordPress refuses as an unknown page.
add_action(
	'admin_page_access_denied',
	function () {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only redirect, changes no state.
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		$tab  = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		$url = pediment_settings_legacy_redirect_url( $page, $tab );
		if ( null !== $url && current_user_can( 'manage_options' ) ) {
			wp_safe_redirect( $url );
			exit;
		}
	}
);

add_action(
	'admin_menu',
	function () {
		add_options_page(
			__( 'Pediment', 'pediment' ),
			__( 'Pediment', 'pediment' ),
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
		<h1><?php esc_html_e( 'Pediment', 'pediment' ); ?></h1>
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
