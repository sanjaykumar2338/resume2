<?php
/**
 * Template Name: Pricing
 *
 * @package FixResume
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

$plan_cards = [
	[
		'slug'       => 'primary',
		'title'      => __( 'Main Subscription', 'fixresume' ),
		'price'      => '$63.80',
		'subtext'    => __( 'Billed every 3 months', 'fixresume' ),
		'features'   => [
			__( 'Unlimited resume downloads (PDF & DOCX)', 'fixresume' ),
			__( 'AI rewrite suggestions for every section', 'fixresume' ),
			__( 'ATS keyword guidance & formatting checklist', 'fixresume' ),
			__( 'Live chat support with resume specialists', 'fixresume' ),
			__( '7-day trial before billing begins', 'fixresume' ),
		],
		'button'     => 'primary',
	],
	[
		'slug'       => 'trial',
		'title'      => __( 'Trial Plan', 'fixresume' ),
		'price'      => '$0.99',
		'subtext'    => __( 'Per week · full access', 'fixresume' ),
		'features'   => [
			__( 'Unlock downloads for 7 days', 'fixresume' ),
			__( 'Perfect for polishing one resume quickly', 'fixresume' ),
			__( 'Upgrade or cancel anytime inside the portal', 'fixresume' ),
		],
		'button'     => 'ghost',
	],
];

$faq_items = [
	[
		'q' => __( 'Can I cancel anytime?', 'fixresume' ),
		'a' => __( 'Yes. Visit your billing portal from Settings → Billing and cancel before the next renewal. Downloads remain unlocked until the current period ends.', 'fixresume' ),
	],
	[
		'q' => __( 'Do you offer refunds?', 'fixresume' ),
		'a' => __( 'If something goes sideways or you forgot to cancel, contact support within 7 days of the charge and we’ll help you out.', 'fixresume' ),
	],
	[
		'q' => __( 'What payment methods do you accept?', 'fixresume' ),
		'a' => __( 'Stripe handles all payments. We accept major cards, Apple Pay, Google Pay, and most regional wallets.', 'fixresume' ),
	],
];

$user_status = '';
$has_active_plan = false;
$active_plan_slug = '';
if ( is_user_logged_in() && function_exists( 'rai_user_can_download' ) ) {
	$user_status     = get_user_meta( get_current_user_id(), 'rai_stripe_status', true );
	$has_active_plan = rai_user_can_download( get_current_user_id() );
	$active_plan_slug = get_user_meta( get_current_user_id(), 'rai_stripe_plan_slug', true );
}
?>

<main class="pricing">
	<section class="hero pricing-hero">
		<div class="container hero-grid">
			<div class="hero-copy">
				<p class="eyebrow"><?php esc_html_e( 'Transparent billing', 'fixresume' ); ?></p>
				<h1><?php esc_html_e( 'Pick a plan that keeps downloads unlocked.', 'fixresume' ); ?></h1>
				<p class="lead">
					<?php esc_html_e( 'Every plan keeps AI suggestions free while funding the export engine and customer support team. Unlock download-ready resumes, templates, and fast responses whenever you need help.', 'fixresume' ); ?>
				</p>
			</div>
			<aside class="hero-card pricing-card">
				<header>
					<p class="hero-card__title"><?php esc_html_e( 'Everything includes', 'fixresume' ); ?></p>
				</header>
				<ul class="pricing-pill-list">
					<li><span class="pill"><?php esc_html_e( 'Unlimited downloads', 'fixresume' ); ?></span></li>
					<li><span class="pill"><?php esc_html_e( 'Live chat support', 'fixresume' ); ?></span></li>
					<li><span class="pill"><?php esc_html_e( 'ATS-ready layouts', 'fixresume' ); ?></span></li>
				</ul>
			</aside>
		</div>
	</section>

	<section class="pricing-plans">
		<div class="container plan-grid">
			<?php foreach ( $plan_cards as $plan ) : ?>
				<article class="plan-card <?php echo 'ghost' === $plan['button'] ? 'plan-card--secondary' : 'plan-card--primary'; ?>">
					<p class="eyebrow"><?php echo esc_html( $plan['title'] ); ?></p>
					<h2><?php echo esc_html( $plan['price'] ); ?></h2>
					<p class="plan-card__subtext"><?php echo esc_html( $plan['subtext'] ); ?></p>
					<ul>
						<?php foreach ( $plan['features'] as $feature ) : ?>
							<li><?php echo esc_html( $feature ); ?></li>
						<?php endforeach; ?>
					</ul>
					<?php if ( $has_active_plan && $active_plan_slug === $plan['slug'] ) : ?>
						<div class="plan-status">
							<span class="pill"><?php esc_html_e( 'Already purchased', 'fixresume' ); ?></span>
							<button type="button" class="button ghost" data-portal-trigger>
								<?php esc_html_e( 'Manage / cancel', 'fixresume' ); ?>
							</button>
						</div>
					<?php else : ?>
						<button type="button" class="button <?php echo 'ghost' === $plan['button'] ? 'ghost' : 'primary'; ?> ra-trial-button" data-plan="<?php echo esc_attr( $plan['slug'] ); ?>">
							<?php esc_html_e( 'Get started', 'fixresume' ); ?>
						</button>
					<?php endif; ?>
				</article>
			<?php endforeach; ?>
		</div>
	</section>

	<section class="faq-section">
		<div class="container">
			<h2><?php esc_html_e( 'Questions we hear often', 'fixresume' ); ?></h2>
			<div class="faq-grid">
				<?php foreach ( $faq_items as $faq ) : ?>
					<article class="faq-card">
						<h3><?php echo esc_html( $faq['q'] ); ?></h3>
						<p><?php echo esc_html( $faq['a'] ); ?></p>
					</article>
				<?php endforeach; ?>
			</div>
		</div>
	</section>

	<section class="cta pricing-cta">
		<div class="container cta-panel">
			<div>
				<h2><?php esc_html_e( 'Keep your downloads unlocked and ready.', 'fixresume' ); ?></h2>
				<p><?php esc_html_e( 'Take action in minutes—export polished resumes, share templates, and stay interview ready.', 'fixresume' ); ?></p>
			</div>
			<button type="button" class="button primary ra-trial-button" data-plan="primary"><?php esc_html_e( 'Get started', 'fixresume' ); ?></button>
		</div>
	</section>
</main>

<?php
get_footer();
