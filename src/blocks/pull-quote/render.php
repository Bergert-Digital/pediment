<?php
/**
 * Server-side render for starter/pull-quote.
 *
 * @var array $attributes
 */

$quote    = isset( $attributes['quote'] )    ? (string) $attributes['quote']    : '';
$citation = isset( $attributes['citation'] ) ? (string) $attributes['citation'] : '';

if ( '' === $quote ) {
	return '';
}

$wrapper = get_block_wrapper_attributes( array( 'class' => 'starter-pull-quote' ) );
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
