<?php
/**
 * Server-side render for pediment/testimonial.
 *
 * @var array $attributes
 */

$quote       = isset( $attributes['quote'] ) ? (string) $attributes['quote'] : '';
$author_name = isset( $attributes['authorName'] ) ? (string) $attributes['authorName'] : '';
$author_role = isset( $attributes['authorRole'] ) ? (string) $attributes['authorRole'] : '';
$avatar_id   = isset( $attributes['avatarId'] ) ? (int) $attributes['avatarId'] : 0;

if ( '' === $quote ) {
	return '';
}

/**
 * Build up-to-two-letter initials from the author name (first letter of the
 * first two words). Returns '' when no name is set.
 */
$initials = '';
if ( '' !== $author_name ) {
	$words = preg_split( '/\s+/', trim( wp_strip_all_tags( $author_name ) ) );
	foreach ( array_slice( $words, 0, 2 ) as $word ) {
		$first     = function_exists( 'mb_substr' ) ? mb_substr( $word, 0, 1 ) : substr( $word, 0, 1 );
		$initials .= function_exists( 'mb_strtoupper' ) ? mb_strtoupper( $first ) : strtoupper( $first );
	}
}

$has_byline = '' !== $author_name || '' !== $author_role;
$wrapper    = get_block_wrapper_attributes( array( 'class' => 'starter-testimonial' ) );

ob_start();
?>
<figure <?php echo $wrapper; // phpcs:ignore WordPress.Security.EscapeOutput ?>>
	<span class="starter-testimonial__mark" aria-hidden="true">&ldquo;</span>
	<blockquote class="starter-testimonial__quote"><?php echo wp_kses_post( $quote ); ?></blockquote>
	<?php if ( $has_byline ) : ?>
		<figcaption class="starter-testimonial__by">
			<?php if ( $avatar_id ) : ?>
				<?php echo wp_get_attachment_image( $avatar_id, 'thumbnail', false, array( 'class' => 'starter-testimonial__avatar' ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
			<?php elseif ( '' !== $initials ) : ?>
				<span class="starter-testimonial__initials" aria-hidden="true"><?php echo esc_html( $initials ); ?></span>
			<?php endif; ?>
			<div class="starter-testimonial__meta">
				<?php if ( '' !== $author_name ) : ?>
					<b class="starter-testimonial__name"><?php echo wp_kses_post( $author_name ); ?></b>
				<?php endif; ?>
				<?php if ( '' !== $author_role ) : ?>
					<span class="starter-testimonial__role"><?php echo wp_kses_post( $author_role ); ?></span>
				<?php endif; ?>
			</div>
		</figcaption>
	<?php endif; ?>
</figure>
<?php
echo ob_get_clean();
