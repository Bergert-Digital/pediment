<?php
/**
 * "Design" tab in Settings → Pediment: lists the vocabulary and offers
 * the theme-file download.
 *
 * @package Pediment
 */

declare(strict_types=1);

namespace Pediment\Design;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin UI for the design layer.
 */
final class AdminTab {

	private const ACTION = 'pediment_design_export';

	/**
	 * Registers the tab and the download handler.
	 */
	public static function register(): void {
		add_action(
			'admin_menu',
			static function () {
				pediment_settings_register_tab( 'design', __( 'Design', 'pediment' ), array( self::class, 'render' ), 40 );
			}
		);
		add_action( 'admin_post_' . self::ACTION, array( self::class, 'download' ) );
	}

	/**
	 * Renders the tab body.
	 */
	public static function render(): void {
		$validator = new StyleValidator();
		$store     = new SiteStyles( $validator );
		$styles    = ( new Vocabulary( $validator, $store ) )->styles();
		$active    = count( $store->all( SiteStyles::STATE_ACTIVE ) );
		?>
		<table class="widefat striped">
			<thead><tr>
				<th><?php esc_html_e( 'Style', 'pediment' ); ?></th>
				<th><?php esc_html_e( 'Blocks', 'pediment' ); ?></th>
				<th><?php esc_html_e( 'Layer', 'pediment' ); ?></th>
				<th><?php esc_html_e( 'Warnings', 'pediment' ); ?></th>
			</tr></thead>
			<tbody>
			<?php foreach ( $styles as $style ) : ?>
				<tr>
					<td><strong><?php echo esc_html( $style['label'] ); ?></strong> <code><?php echo esc_html( $style['slug'] ); ?></code></td>
					<td><?php echo esc_html( implode( ', ', $style['blockTypes'] ) ); ?></td>
					<td><?php echo esc_html( $style['layer'] . ( null !== $style['overrides'] ? ' → ' . $style['overrides'] : '' ) ); ?></td>
					<td><?php echo esc_html( implode( ' · ', $style['warnings'] ) ); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>" />
			<?php wp_nonce_field( self::ACTION ); ?>
			<p>
				<?php submit_button( __( 'Download site styles as theme files', 'pediment' ), 'secondary', 'submit', false, 0 === $active ? array( 'disabled' => 'disabled' ) : array() ); ?>
				<span class="description"><?php echo esc_html( sprintf( /* translators: %d: number of styles */ _n( '%d active site style.', '%d active site styles.', $active, 'pediment' ), $active ) ); ?></span>
			</p>
		</form>
		<?php
	}

	/**
	 * Streams the zip.
	 */
	public static function download(): void {
		if ( ! current_user_can( 'edit_theme_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to export styles.', 'pediment' ), 403 );
		}
		check_admin_referer( self::ACTION );
		$zip = ( new Exporter( new SiteStyles( new StyleValidator() ) ) )->zip();
		if ( is_wp_error( $zip ) ) {
			wp_die( esc_html( $zip->get_error_message() ) );
		}
		nocache_headers();
		header( 'Content-Type: application/zip' );
		header( 'Content-Disposition: attachment; filename="pediment-styles.zip"' );
		header( 'Content-Length: ' . (string) filesize( $zip ) );
		readfile( $zip ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
		wp_delete_file( $zip );
		exit;
	}
}
