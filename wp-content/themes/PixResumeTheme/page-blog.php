<?php
/**
 * Template Name: Blog
 *
 * Blog index template that lists published posts in a card grid.
 *
 * @package FixResume
 */

defined( 'ABSPATH' ) || exit;

get_header();

$page_title = get_the_title() ? get_the_title() : __( 'Blog', 'fixresume' );
$page_intro = '';
if ( have_posts() ) {
	the_post();
	$page_intro = trim( get_the_content() );
}

$paged = get_query_var( 'paged' ) ? absint( get_query_var( 'paged' ) ) : 1;
$paged = $paged ? $paged : 1;

$blog_query = new WP_Query(
	[
		'post_type'      => 'post',
		'post_status'    => 'publish',
		'posts_per_page' => 9,
		'paged'          => $paged,
	]
);
?>

<main class="blog">
	<section class="blog-hero">
		<div class="container">
			<p class="eyebrow"><?php esc_html_e( 'Insights & wins', 'fixresume' ); ?></p>
			<h1><?php echo esc_html( $page_title ); ?></h1>
			<?php if ( ! empty( $page_intro ) ) : ?>
				<div class="blog-hero__intro">
					<?php echo apply_filters( 'the_content', $page_intro ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</div>
			<?php else : ?>
				<p class="lead">
					<?php esc_html_e( 'Fresh guidance from recruiters, writers, and hiring managers. Every story is built from native WordPress posts, so your team can publish whenever inspiration strikes.', 'fixresume' ); ?>
				</p>
			<?php endif; ?>
		</div>
	</section>

	<section class="blog-list">
		<div class="container">
			<?php if ( $blog_query->have_posts() ) : ?>
				<div class="blog-grid">
					<?php
					while ( $blog_query->have_posts() ) :
						$blog_query->the_post();
						$categories  = get_the_category();
						$primary_cat = ! empty( $categories ) ? $categories[0]->name : esc_html__( 'Updates', 'fixresume' );
						$author_role = trim( get_the_author_meta( 'description' ) );
						$author_name = get_the_author();
						?>
						<article <?php post_class( 'blog-card' ); ?>>
							<a class="blog-card__media" href="<?php the_permalink(); ?>">
								<?php if ( has_post_thumbnail() ) : ?>
									<?php the_post_thumbnail( 'large', [ 'class' => 'blog-card__image', 'loading' => 'lazy' ] ); ?>
								<?php else : ?>
									<div class="blog-card__image blog-card__image--placeholder">
										<span><?php esc_html_e( 'Feature image coming soon', 'fixresume' ); ?></span>
									</div>
								<?php endif; ?>
							</a>
							<div class="blog-card__body">
								<div class="blog-card__meta">
									<time datetime="<?php echo esc_attr( get_the_date( DATE_W3C ) ); ?>" class="blog-card__date">
										<?php echo esc_html( get_the_date( 'M j, Y' ) ); ?>
									</time>
									<span class="blog-card__pill"><?php echo esc_html( $primary_cat ); ?></span>
								</div>
								<h2 class="blog-card__title">
									<a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
								</h2>
								<p class="blog-card__excerpt">
									<?php echo esc_html( wp_strip_all_tags( has_excerpt() ? get_the_excerpt() : wp_trim_words( get_the_content(), 40 ) ) ); ?>
								</p>
								<div class="blog-card__author">
									<div class="blog-card__avatar">
										<?php
										$avatar_html = get_avatar( get_the_author_meta( 'ID' ), 48, '', $author_name, [ 'class' => 'blog-card__avatar-img' ] );
										if ( $avatar_html ) {
											echo wp_kses_post( $avatar_html );
										} else {
											$initial = function_exists( 'mb_substr' ) ? mb_substr( $author_name, 0, 1 ) : substr( $author_name, 0, 1 );
											$initial = function_exists( 'mb_strtoupper' ) ? mb_strtoupper( $initial ) : strtoupper( $initial );
											?>
											<span class="blog-card__avatar-initial"><?php echo esc_html( $initial ); ?></span>
										<?php } ?>
									</div>
									<div class="blog-card__author-info">
										<span class="blog-card__author-name"><?php echo esc_html( $author_name ); ?></span>
										<span class="blog-card__author-role">
											<?php echo esc_html( $author_role ? $author_role : __( 'Contributor', 'fixresume' ) ); ?>
										</span>
									</div>
								</div>
							</div>
						</article>
					<?php endwhile; ?>
				</div>

				<?php
					$pagination = paginate_links(
						[
							'total'   => $blog_query->max_num_pages,
							'current' => $paged,
							'type'    => 'list',
							'prev_text' => __( 'Previous', 'fixresume' ),
							'next_text' => __( 'Next', 'fixresume' ),
						]
					);
					if ( $pagination ) :
						?>
						<nav class="blog-pagination" aria-label="<?php esc_attr_e( 'Blog navigation', 'fixresume' ); ?>">
							<?php echo wp_kses_post( $pagination ); ?>
						</nav>
					<?php endif; ?>
			<?php else : ?>
				<p class="blog-empty">
					<?php esc_html_e( 'No articles yet. Publish your first post from the WordPress dashboard to see it appear here automatically.', 'fixresume' ); ?>
				</p>
			<?php endif; ?>
		</div>
	</section>
</main>

<?php
wp_reset_postdata();
get_footer();
