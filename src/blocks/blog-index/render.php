<?php
/**
 * Server-side render for starter/blog-index.
 *
 * @var array $attributes
 */

$count    = isset( $attributes['count'] )        ? max( 1, (int) $attributes['count'] ) : 6;
$cat_slug = isset( $attributes['categorySlug'] ) ? (string) $attributes['categorySlug'] : '';

$query_args = array(
	'post_type'      => 'post',
	'post_status'    => 'publish',
	'posts_per_page' => $count,
);
if ( '' !== $cat_slug ) {
	$query_args['category_name'] = $cat_slug;
}

$query = new WP_Query( $query_args );

$wrapper = get_block_wrapper_attributes( array( 'class' => 'starter-blog-index' ) );

ob_start();
?>
<section <?php echo $wrapper; // phpcs:ignore WordPress.Security.EscapeOutput ?>>
	<?php if ( ! $query->have_posts() ) : ?>
		<p class="starter-blog-index__empty"><?php esc_html_e( 'No posts yet.', 'starter' ); ?></p>
	<?php else : ?>
		<ul class="starter-blog-index__list">
			<?php
			while ( $query->have_posts() ) :
				$query->the_post();
				?>
				<li class="starter-blog-index__item">
					<a class="starter-blog-index__link" href="<?php the_permalink(); ?>">
						<h3 class="starter-blog-index__title"><?php the_title(); ?></h3>
					</a>
					<time class="starter-blog-index__date" datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>"><?php echo esc_html( get_the_date() ); ?></time>
					<p class="starter-blog-index__excerpt"><?php echo esc_html( get_the_excerpt() ); ?></p>
				</li>
				<?php
			endwhile;
			?>
		</ul>
		<?php
	endif;
	wp_reset_postdata();
	?>
</section>
<?php
echo ob_get_clean();
