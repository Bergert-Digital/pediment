<?php
/**
 * Server-side render for starter/blog-index (Insights cards).
 *
 * The post query is unchanged (count + optional categorySlug). The filter
 * bar is purely presentational — view.js toggles card visibility client-side.
 *
 * @var array $attributes
 */

$count       = isset( $attributes['count'] ) ? max( 1, (int) $attributes['count'] ) : 6;
$cat_slug    = isset( $attributes['categorySlug'] ) ? (string) $attributes['categorySlug'] : '';
$show_filter = ! isset( $attributes['showFilter'] ) || (bool) $attributes['showFilter'];

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
		<?php
	else :
		$cards   = array();
		$filters = array(); // slug => name, in first-appearance order.
		while ( $query->have_posts() ) :
			$query->the_post();
			$post_id = get_the_ID();
			// Primary category = first term. Uncategorised posts get an empty
			// slug: no badge, excluded from the filter list, and shown only
			// under "All" (they are not in any specific category by design).
			$terms   = get_the_category( $post_id );
			$primary = ! empty( $terms ) ? $terms[0] : null;
			$slug    = $primary ? (string) $primary->slug : '';
			$name    = $primary ? (string) $primary->name : '';
			if ( '' !== $slug && ! isset( $filters[ $slug ] ) ) {
				$filters[ $slug ] = $name;
			}
			$cards[] = array(
				'slug'      => $slug,
				'cat_name'  => $name,
				'permalink' => get_permalink( $post_id ),
				'title'     => get_the_title( $post_id ),
				'date'      => get_the_date( '', $post_id ),
				'datetime'  => get_the_date( 'c', $post_id ),
				'excerpt'   => get_the_excerpt( $post_id ),
				'thumb'     => has_post_thumbnail( $post_id )
					? get_the_post_thumbnail(
						$post_id,
						'large',
						array(
							'class' => 'starter-blog-index__img',
							'alt'   => '',
						)
					)
					: '',
			);
		endwhile;
		wp_reset_postdata();

		$render_filter = $show_filter && count( $filters ) > 1;
		?>
		<?php if ( $render_filter ) : ?>
			<div class="starter-blog-index__filter">
				<button type="button" class="is-active" data-filter="all"><?php esc_html_e( 'All', 'starter' ); ?></button>
				<?php foreach ( $filters as $f_slug => $f_name ) : ?>
					<button type="button" data-filter="<?php echo esc_attr( $f_slug ); ?>"><?php echo esc_html( $f_name ); ?></button>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
		<ul class="starter-blog-index__list">
			<?php foreach ( $cards as $card ) : ?>
				<li class="starter-blog-index__item" data-cat="<?php echo esc_attr( $card['slug'] ); ?>">
					<div class="starter-blog-index__media">
						<?php echo $card['thumb']; // phpcs:ignore WordPress.Security.EscapeOutput -- get_the_post_thumbnail() output is escaped. ?>
						<?php if ( '' !== $card['cat_name'] ) : ?>
							<span class="starter-blog-index__badge starter-blog-index__badge--<?php echo esc_attr( $card['slug'] ); ?>"><?php echo esc_html( $card['cat_name'] ); ?></span>
						<?php endif; ?>
					</div>
					<div class="starter-blog-index__body">
						<div class="starter-blog-index__meta">
							<time class="starter-blog-index__date" datetime="<?php echo esc_attr( $card['datetime'] ); ?>"><?php echo esc_html( $card['date'] ); ?></time>
						</div>
						<a class="starter-blog-index__link" href="<?php echo esc_url( $card['permalink'] ); ?>">
							<h3 class="starter-blog-index__title"><?php echo esc_html( $card['title'] ); ?></h3>
						</a>
						<p class="starter-blog-index__excerpt"><?php echo esc_html( $card['excerpt'] ); ?></p>
						<a class="starter-blog-index__readmore" href="<?php echo esc_url( $card['permalink'] ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: post title */ __( 'Read more: %s', 'starter' ), $card['title'] ) ); ?>">
							<?php esc_html_e( 'Read more', 'starter' ); ?>
							<svg class="i" aria-hidden="true" focusable="false"><use href="#ph-arrow-right"></use></svg>
						</a>
					</div>
				</li>
			<?php endforeach; ?>
		</ul>
		<?php
	endif;
	?>
</section>
<?php
echo ob_get_clean();
