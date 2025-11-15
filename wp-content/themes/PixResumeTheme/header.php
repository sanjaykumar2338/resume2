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
		<?php if ( has_nav_menu( 'primary' ) ) : ?>
			<?php
			wp_nav_menu(
				[
					'theme_location' => 'primary',
					'container'      => false,
					'menu_class'     => 'primary-nav__list',
					'fallback_cb'    => false,
				]
			);
			?>
		<?php elseif ( current_user_can( 'edit_theme_options' ) ) : ?>
			<ul class="primary-nav__list primary-nav__list--empty">
				<li class="primary-nav__notice"><?php esc_html_e( 'Assign a menu to the Primary location in Appearance → Menus.', 'fixresume' ); ?></li>
			</ul>
		<?php endif; ?>
	</nav>
	</div>
</header>
