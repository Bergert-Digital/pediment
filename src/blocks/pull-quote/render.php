<?php
/**
 * Server-side render for starter/pull-quote.
 *
 * @var array $attributes
 */

$variant  = isset( $attributes['variant'] ) ? (string) $attributes['variant'] : 'default';
$quote    = isset( $attributes['quote'] ) ? (string) $attributes['quote'] : '';
$citation = isset( $attributes['citation'] ) ? (string) $attributes['citation'] : '';

$allowed = function_exists( 'starter_pull_quote_variants' )
	? starter_pull_quote_variants()
	: array( 'default', 'testimonial' );
if ( ! in_array( $variant, $allowed, true ) ) {
	$variant = 'default';
}

if ( '' === $quote ) {
	return '';
}

$wrapper = get_block_wrapper_attributes(
	array( 'class' => 'starter-pull-quote is-variant-' . sanitize_html_class( $variant ) )
);

if ( 'testimonial' === $variant ) {
	$author_name = isset( $attributes['authorName'] ) ? (string) $attributes['authorName'] : '';
	$author_role = isset( $attributes['authorRole'] ) ? (string) $attributes['authorRole'] : '';
	$avatar_id   = isset( $attributes['avatarId'] ) ? (int) $attributes['avatarId'] : 0;

	ob_start();
	?>
	<figure <?php echo $wrapper; // phpcs:ignore WordPress.Security.EscapeOutput ?>>
		<blockquote class="starter-pull-quote__quote"><?php echo wp_kses_post( $quote ); ?></blockquote>
		<?php if ( $avatar_id || '' !== $author_name || '' !== $author_role ) : ?>
			<figcaption class="starter-pull-quote__by">
				<?php
				if ( $avatar_id ) {
					echo wp_get_attachment_image( $avatar_id, 'thumbnail', false, array( 'class' => 'starter-pull-quote__avatar' ) ); // phpcs:ignore WordPress.Security.EscapeOutput
				}
				?>
				<?php if ( '' !== $author_name || '' !== $author_role ) : ?>
					<div class="starter-pull-quote__meta">
						<?php if ( '' !== $author_name ) : ?>
							<b class="starter-pull-quote__name"><?php echo wp_kses_post( $author_name ); ?></b>
						<?php endif; ?>
						<?php if ( '' !== $author_role ) : ?>
							<span class="starter-pull-quote__role"><?php echo wp_kses_post( $author_role ); ?></span>
						<?php endif; ?>
					</div>
				<?php endif; ?>
			</figcaption>
		<?php endif; ?>
	</figure>
	<?php
	echo ob_get_clean();
	return;
}

ob_start();
?>
<blockquote <?php echo $wrapper; // phpcs:ignore WordPress.Security.EscapeOutput ?>>
	<p class="starter-pull-quote__quote"><?php echo wp_kses_post( $quote ); ?></p>
	<?php if ( '' !== $citation ) : ?>
		<cite class="starter-pull-quote__citation"><?php echo wp_kses_post( $citation ); ?></cite>
	<?php endif; ?>
</blockquote>
<?php
echo ob_get_clean();
