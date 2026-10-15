<?php
/**
 * "Convert" tab in Settings → Pediment Theme: preview, then convert legacy blocks.
 *
 * @package Pediment
 */

declare(strict_types=1);

namespace Pediment\Convert;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The production route: hosts without WP-CLI convert here. The tab shows a
 * dry run of every post that holds a legacy block; one confirmed form post
 * converts the previewed posts.
 */
final class AdminTab {

	public const ACTION = 'pediment_convert_legacy';

	/**
	 * Posts per preview and per run. Shared hosting limits request time
	 * (about 30 s) and form fields (max_input_vars); filter with
	 * `pediment_convert_batch_size`.
	 */
	public const BATCH = 25;

	private const REPORT = 'pediment_convert_report_';

	/**
	 * The docs section on the order of a site conversion.
	 */
	public const DOCS_URL = 'https://github.com/Bergert-Digital/pediment/blob/main/docs/convert.md#before-you-convert-production';

	/**
	 * Registers the tab and the form handler.
	 */
	public static function register(): void {
		add_action(
			'admin_menu',
			static function () {
				pediment_settings_register_tab( 'convert', __( 'Convert legacy blocks', 'pediment' ), array( self::class, 'render' ), 50 );
			}
		);
		add_action( 'admin_post_' . self::ACTION, array( self::class, 'handle' ) );
	}

	/**
	 * Who may convert here: the settings page needs manage_options, and the
	 * run touches templates, which need edit_theme_options.
	 */
	public static function allowed(): bool {
		return current_user_can( 'manage_options' ) && current_user_can( 'edit_theme_options' );
	}

	/**
	 * Posts per batch.
	 */
	private static function batchSize(): int {
		return max( 1, (int) apply_filters( 'pediment_convert_batch_size', self::BATCH ) );
	}

	/**
	 * The `after` query arg: the preview starts after this post ID.
	 */
	private static function cursor(): int {
		return absint( wp_unslash( $_GET['after'] ?? 0 ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- read-only position; absint.
	}

	/**
	 * The nonce action for a set of previewed posts. It binds the confirm to
	 * the preview: a changed id list fails the nonce check.
	 *
	 * @param list<int> $ids Post IDs of the batch.
	 */
	public static function nonceAction( array $ids ): string {
		$ids = array_values( array_unique( array_map( 'intval', $ids ) ) );
		sort( $ids );
		return self::ACTION . ':' . md5( implode( ',', $ids ) );
	}

	/**
	 * Renders the tab body: the last run's result (once), then the preview.
	 */
	public static function render(): void {
		if ( ! self::allowed() ) {
			echo '<p>' . esc_html__( 'You are not allowed to convert legacy blocks.', 'pediment' ) . '</p>';
			return;
		}
		$user = get_current_user_id();
		$last = get_transient( self::REPORT . $user );
		if ( is_array( $last ) ) {
			delete_transient( self::REPORT . $user );
			echo '<h2>' . esc_html__( 'Result', 'pediment' ) . '</h2>';
			self::table( $last );
		}

		$service = PostConverter::create();
		$after   = self::cursor();
		$all     = $service->candidates();
		$rest    = array_values( array_filter( $all, static fn( int $id ): bool => $id > $after ) );
		$batch   = array_slice( $rest, 0, self::batchSize() );
		$preview = $service->runMany( $batch, true, array( self::class, 'canEdit' ) );
		$start   = $after > 0 ? ' <a href="' . esc_url( pediment_settings_page_url( 'convert' ) ) . '">' . esc_html__( 'Start from the beginning', 'pediment' ) . '</a>' : '';
		$ready   = array_values( array_filter( $preview['posts'], static fn( array $post ): bool => 'preview' === $post['status'] ) );
		echo '<h2>' . esc_html__( 'Preview', 'pediment' ) . '</h2>';
		echo '<div class="notice notice-warning inline"><p>' . esc_html__( 'Convert production only after your client theme has styles for the looks your theme gave the legacy blocks; check the converted pages in a dev copy first.', 'pediment' ) . ' <a href="' . esc_url( self::DOCS_URL ) . '">' . esc_html__( 'Before you convert production', 'pediment' ) . '</a></p></div>';
		echo '<p>' . esc_html__( 'Posts, pages, synced patterns and templates in every language that still use legacy Pediment blocks. Nothing changes until you press the button. Every converted post keeps a revision of its old content.', 'pediment' ) . '</p>';
		if ( array() === $preview['posts'] ) {
			if ( $after > 0 && array() !== $all ) {
				echo '<p><strong>' . esc_html__( 'No more posts with legacy blocks after this point.', 'pediment' ) . '</strong>' . $start . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $start is built from escaped parts.
				return;
			}
			echo '<p><strong>' . esc_html__( 'No legacy blocks found. Nothing to convert.', 'pediment' ) . '</strong></p>';
			return;
		}
		self::table( $preview );
		$more = count( $rest ) - count( $batch );
		echo '<p>' . esc_html( sprintf( /* translators: %d: number of posts */ _n( '%d post with legacy blocks in total.', '%d posts with legacy blocks in total.', count( $all ), 'pediment' ), count( $all ) ) );
		if ( $more > 0 ) {
			echo ' ' . esc_html( sprintf( /* translators: %d: number of posts */ _n( '%d more post with legacy blocks after this batch.', '%d more posts with legacy blocks after this batch.', $more, 'pediment' ), $more ) );
			echo ' <a href="' . esc_url( add_query_arg( 'after', (string) end( $batch ), pediment_settings_page_url( 'convert' ) ) ) . '">' . esc_html__( 'Next batch', 'pediment' ) . '</a>';
		}
		echo $start . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $start is built from escaped parts.
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>" />
			<input type="hidden" name="after" value="<?php echo esc_attr( (string) $after ); ?>" />
			<?php wp_nonce_field( self::nonceAction( array_column( $ready, 'id' ) ) ); ?>
			<?php foreach ( $ready as $post ) : ?>
				<input type="hidden" name="ids[]" value="<?php echo esc_attr( (string) $post['id'] ); ?>" />
			<?php endforeach; ?>
			<?php
			/* translators: %d: number of posts */
			submit_button( sprintf( _n( 'Convert %d post', 'Convert %d posts', count( $ready ), 'pediment' ), count( $ready ) ), 'primary', 'submit', true, array() === $ready ? array( 'disabled' => 'disabled' ) : array() );
			?>
		</form>
		<?php
	}

	/**
	 * Form handler: checks rights and nonce, converts, shows the result.
	 */
	public static function handle(): void {
		if ( ! self::allowed() ) {
			wp_die( esc_html__( 'You are not allowed to convert legacy blocks.', 'pediment' ), 403 );
		}
		$ids = array_values( array_filter( array_map( 'absint', (array) wp_unslash( $_POST['ids'] ?? array() ) ) ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- absint on every item.
		check_admin_referer( self::nonceAction( $ids ) );
		self::process( $ids );
		$after = absint( wp_unslash( $_POST['after'] ?? 0 ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- the nonce was checked above; absint.
		wp_safe_redirect( add_query_arg( 'after', $after > 0 ? $after : false, pediment_settings_page_url( 'convert' ) ) );
		exit;
	}

	/**
	 * Converts the posts (at most one batch) and keeps the result for the next page load.
	 *
	 * @param list<int> $ids Post IDs from the confirmed preview.
	 * @return array{dryRun:bool,posts:list<array>,summary:array<string,int>}
	 */
	public static function process( array $ids ): array {
		$report = PostConverter::create()->runMany( array_slice( array_values( $ids ), 0, self::batchSize() ), false, array( self::class, 'canEdit' ) );
		set_transient( self::REPORT . get_current_user_id(), $report, 10 * MINUTE_IN_SECONDS );
		return $report;
	}

	/**
	 * Whether the current user may convert this post.
	 *
	 * @param int $id Post ID.
	 */
	public static function canEdit( int $id ): bool {
		return current_user_can( 'edit_post', $id );
	}

	/**
	 * One table of post reports. A post the user may not edit shows no title;
	 * a converted post links to the revision that restores its old content.
	 *
	 * @param array $report runMany() result.
	 */
	private static function table( array $report ): void {
		?>
		<table class="widefat striped">
			<thead><tr>
				<th><?php esc_html_e( 'Post', 'pediment' ); ?></th>
				<th><?php esc_html_e( 'Type', 'pediment' ); ?></th>
				<th><?php esc_html_e( 'Language', 'pediment' ); ?></th>
				<th><?php esc_html_e( 'Status', 'pediment' ); ?></th>
				<th><?php esc_html_e( 'Legacy blocks', 'pediment' ); ?></th>
				<th><?php esc_html_e( 'Notes', 'pediment' ); ?></th>
			</tr></thead>
			<tbody>
			<?php foreach ( $report['posts'] as $post ) : ?>
				<tr>
					<?php if ( self::canEdit( $post['id'] ) ) : ?>
						<td><a href="<?php echo esc_url( (string) get_edit_post_link( $post['id'] ) ); ?>"><?php echo esc_html( '' !== $post['title'] ? $post['title'] : '#' . $post['id'] ); ?></a></td>
					<?php else : ?>
						<td><?php esc_html_e( '(no access)', 'pediment' ); ?></td>
					<?php endif; ?>
					<td><?php echo esc_html( $post['type'] ); ?></td>
					<td><?php echo esc_html( $post['language'] ); ?></td>
					<?php if ( 'converted' === $post['status'] && $post['restoreRevision'] > 0 ) : ?>
						<td><?php echo esc_html( $post['status'] ); ?> · <a href="<?php echo esc_url( admin_url( 'revision.php?revision=' . (int) $post['restoreRevision'] ) ); ?>"><?php esc_html_e( 'Restore', 'pediment' ); ?></a></td>
					<?php else : ?>
						<td><?php echo esc_html( $post['status'] ); ?></td>
					<?php endif; ?>
					<td><?php echo esc_html( implode( ', ', array_merge( $post['converted'], $post['unmapped'] ) ) ); ?></td>
					<td>
						<?php echo esc_html( implode( ' · ', array_merge( $post['errors'], $post['notes'] ) ) ); ?>
						<?php if ( '' !== $post['diff'] ) : ?>
							<details><summary><?php esc_html_e( 'Show changes', 'pediment' ); ?></summary><pre style="white-space:pre-wrap;max-height:30em;overflow:auto"><?php echo esc_html( $post['diff'] ); ?></pre></details>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<p>
		<?php
		$summary = $report['summary'];
		/* translators: 1: posts, 2: legacy blocks, 3: converted, 4: preview, 5: failed, 6: skipped, 7: unchanged */
		echo esc_html( sprintf( __( '%1$d posts, %2$d legacy blocks · converted %3$d · ready %4$d · failed %5$d · skipped %6$d · unchanged %7$d', 'pediment' ), $summary['posts'], $summary['blocks'], $summary['converted'], $summary['preview'], $summary['failed'], $summary['skipped'], $summary['unchanged'] ) );
		?>
		</p>
		<?php
	}
}
