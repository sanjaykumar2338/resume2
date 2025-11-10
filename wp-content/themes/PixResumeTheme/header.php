<?php
$theme_uri = get_template_directory_uri();
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>"/>
	<meta name="viewport" content="width=device-width, initial-scale=1"/>
	<meta name="description" content="Upload your resume and FixResume will generate tailored wording suggestions that boost clarity, impact, and keyword alignment in minutes."/>
	<link rel="icon" href="<?php echo esc_url( $theme_uri . '/assets/images/PixResume_favicon.ico' ); ?>"/>
	<link rel="apple-touch-icon" href="<?php echo esc_url( $theme_uri . '/assets/images/PixResume_apple-touch-icon.png' ); ?>"/>
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<header class="site-header" id="top">
	<div class="container header-layout">
		<a class="brand" href="<?php echo esc_url( home_url( '#home' ) ); ?>">
			<?php bloginfo( 'name' ); ?>
		</a>
	<nav class="primary-nav" aria-label="<?php esc_attr_e( 'Primary navigation', 'fixresume' ); ?>">
		<ul>
			<li><a href="<?php echo esc_url( home_url( '#how-it-works' ) ); ?>"><?php esc_html_e( 'How it works', 'fixresume' ); ?></a></li>
			<li><a href="<?php echo esc_url( home_url( '#sample' ) ); ?>"><?php esc_html_e( 'Sample suggestions', 'fixresume' ); ?></a></li>
			<li><a href="<?php echo esc_url( home_url( '#faq' ) ); ?>"><?php esc_html_e( 'FAQ', 'fixresume' ); ?></a></li>
			<li><a class="cta-link" href="<?php echo esc_url( home_url( '#upload' ) ); ?>"><?php esc_html_e( 'Upload resume', 'fixresume' ); ?></a></li>
			<?php if ( is_user_logged_in() ) : ?>
				<li><a href="<?php echo esc_url( home_url( '/dashboard/' ) ); ?>"><?php esc_html_e( 'Dashboard', 'fixresume' ); ?></a></li>
				<li><a href="<?php echo esc_url( wp_logout_url( home_url() ) ); ?>"><?php esc_html_e( 'Sign out', 'fixresume' ); ?></a></li>
			<?php else : ?>
				<li><a href="<?php echo esc_url( wp_login_url( home_url( '/dashboard/' ) ) ); ?>"><?php esc_html_e( 'Sign in', 'fixresume' ); ?></a></li>
				<li><a href="<?php echo esc_url( wp_registration_url() ); ?>"><?php esc_html_e( 'Sign up', 'fixresume' ); ?></a></li>
			<?php endif; ?>
		</ul>
	</nav>
	</div>
</header>
