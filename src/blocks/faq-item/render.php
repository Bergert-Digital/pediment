<?php
/**
 * Server-side render for starter/faq-item.
 *
 * @var array $attributes
 */

$question = isset( $attributes['question'] ) ? (string) $attributes['question'] : '';
$answer   = isset( $attributes['answer'] ) ? (string) $attributes['answer'] : '';

if ( '' === $question && '' === $answer ) {
	return '';
}

$wrapper = get_block_wrapper_attributes( array( 'class' => 'starter-faq-item' ) );
ob_start();
?>
<details <?php echo $wrapper; // phpcs:ignore WordPress.Security.EscapeOutput ?>>
	<summary class="starter-faq-item__question">
		<?php echo wp_kses_post( $question ); ?>
		<span class="starter-faq-item__toggle" aria-hidden="true">
			<svg class="i" aria-hidden="true" focusable="false"><use href="#ph-caret-down"></use></svg>
		</span>
	</summary>
	<div class="starter-faq-item__answer"><?php echo wp_kses_post( $answer ); ?></div>
</details>
<?php
echo ob_get_clean();
