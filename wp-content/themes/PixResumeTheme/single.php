<?php
/**
 * Template for single blog posts.
 *
 * @package FixResume
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>

<main class="single-post">
	<?php if ( have_posts() ) : ?>
		<?php
		while ( have_posts() ) :
			the_post();
			$categories   = get_the_category();
			$primary_cat  = ! empty( $categories ) ? $categories[0]->name : esc_html__( 'Updates', 'fixresume' );
			$author_role  = trim( get_the_author_meta( 'description' ) );
			$read_time    = ceil( str_word_count( wp_strip_all_tags( get_the_content() ) ) / 200 );
			?>
			<section class="single-hero">
				<div class="container">
					<p class="pill single-pill"><?php echo esc_html( $primary_cat ); ?></p>
					<h1><?php the_title(); ?></h1>
					<div class="single-meta">
						<time datetime="<?php echo esc_attr( get_the_date( DATE_W3C ) ); ?>">
							<?php echo esc_html( get_the_date( 'M j, Y' ) ); ?>
						</time>
						<span>•</span>
						<span><?php echo esc_html( sprintf( _n( '%d min read', '%d mins read', $read_time, 'fixresume' ), $read_time ) ); ?></span>
					</div>
				</div>
			</section>

			<section class="single-content">
				<div class="container single-layout">
					<article class="single-article">
						<?php if ( has_post_thumbnail() ) : ?>
							<div class="single-cover">
								<?php the_post_thumbnail( 'large', [ 'class' => 'single-cover__img', 'loading' => 'lazy' ] ); ?>
							</div>
						<?php endif; ?>
						<div class="single-body">
							<?php the_content(); ?>
						</div>
						<nav class="single-nav" aria-label="<?php esc_attr_e( 'Post navigation', 'fixresume' ); ?>">
							<div class="single-nav__item">
								<?php previous_post_link( '%link', '&larr; %title' ); ?>
							</div>
							<div class="single-nav__item single-nav__item--next">
								<?php next_post_link( '%link', '%title &rarr;' ); ?>
							</div>
						</nav>
					</article>
					<aside class="single-sidebar">
						<div class="single-author">
							<div class="single-author__avatar">
								<?php
								$avatar_html = get_avatar( get_the_author_meta( 'ID' ), 64, '', get_the_author(), [ 'class' => 'single-author__img' ] );
								if ( $avatar_html ) {
									echo wp_kses_post( $avatar_html );
								} else {
									$initial = function_exists( 'mb_substr' ) ? mb_substr( get_the_author(), 0, 1 ) : substr( get_the_author(), 0, 1 );
									$initial = function_exists( 'mb_strtoupper' ) ? mb_strtoupper( $initial ) : strtoupper( $initial );
									?>
									<span><?php echo esc_html( $initial ); ?></span>
								<?php } ?>
							</div>
							<div>
								<p class="single-author__name"><?php echo esc_html( get_the_author() ); ?></p>
								<p class="single-author__role"><?php echo esc_html( $author_role ? $author_role : __( 'Contributor', 'fixresume' ) ); ?></p>
							</div>
						</div>
						<div class="single-cta">
							<h2><?php esc_html_e( 'Need tailored resume feedback?', 'fixresume' ); ?></h2>
							<p><?php esc_html_e( 'Upload your resume and get recruiter-grade insights in minutes.', 'fixresume' ); ?></p>
							<a class="button primary" href="<?php echo esc_url( home_url( '#upload' ) ); ?>"><?php esc_html_e( 'Upload resume', 'fixresume' ); ?></a>
						</div>
					</aside>
				</div>
			</section>
		<?php endwhile; ?>
	<?php endif; ?>
</main>

<?php
get_footer();
