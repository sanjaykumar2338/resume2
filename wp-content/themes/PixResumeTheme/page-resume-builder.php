<?php
/**
 * Template Name: Resume Builder
 *
 * @package FixResume
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

$builder_can_download = is_user_logged_in() && function_exists( 'rai_user_can_download' ) ? rai_user_can_download( get_current_user_id() ) : false;
$welcome_message = '';
if ( isset( $_GET['welcome'] ) && (int) $_GET['welcome'] === 1 && $builder_can_download ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$welcome_message = __( 'Welcome back! Downloads are unlocked—export your resume when you’re ready.', 'fixresume' );
}
?>

<section class="hero builder-hero">
	<div class="container hero-grid">
		<div class="hero-copy">
			<p class="eyebrow"><?php esc_html_e( 'Create from scratch', 'fixresume' ); ?></p>
			<h1><?php esc_html_e( 'Design your resume, section by section.', 'fixresume' ); ?></h1>
			<p class="lead">
				<?php esc_html_e( 'Use the same toolkit the homepage promotes—wizard on the left, preview on the right, and instant AI help whenever you need it.', 'fixresume' ); ?>
			</p>
			<div class="hero-actions">
				<a class="button primary" href="#builder-main"><?php esc_html_e( 'Start building', 'fixresume' ); ?></a>
				<a class="button ghost" href="<?php echo esc_url( home_url( '#sample' ) ); ?>"><?php esc_html_e( 'View sample output', 'fixresume' ); ?></a>
			</div>
		</div>
		<aside class="hero-card">
			<header>
				<p class="hero-card__title"><?php esc_html_e( 'How it works', 'fixresume' ); ?></p>
			</header>
			<ul>
				<li>
					<span class="pill"><?php esc_html_e( 'Fill sections', 'fixresume' ); ?></span>
					<p><?php esc_html_e( 'Personal details, summary, experience, education, and skills—all in one place.', 'fixresume' ); ?></p>
				</li>
				<li>
					<span class="pill"><?php esc_html_e( 'Preview instantly', 'fixresume' ); ?></span>
					<p><?php esc_html_e( 'Every keystroke updates the preview panel so you see the polished layout immediately.', 'fixresume' ); ?></p>
				</li>
				<li>
					<span class="pill"><?php esc_html_e( 'Download & copy', 'fixresume' ); ?></span>
					<p><?php esc_html_e( 'Export your resume or copy everything to send into your favorite editor.', 'fixresume' ); ?></p>
				</li>
			</ul>
		</aside>
	</div>
</section>

<main id="builder-wizard" class="wizard-page container">
	<div class="builder-panel__header">
		<div>
			<p class="eyebrow"><?php esc_html_e( 'Interactive builder', 'fixresume' ); ?></p>
			<h1><?php esc_html_e( 'Work through each step, then export when you’re ready.', 'fixresume' ); ?></h1>
		</div>
		<div class="builder-score">
			<span class="builder-score__label"><?php esc_html_e( 'AI score', 'fixresume' ); ?></span>
			<strong class="builder-score__value" id="builder-score">--</strong>
		</div>
	</div>

	<?php if ( $welcome_message ) : ?>
		<div class="builder-alert success">
			<p><?php echo esc_html( $welcome_message ); ?></p>
		</div>
	<?php endif; ?>

	<ol class="stepper" aria-label="<?php esc_attr_e( 'Builder steps', 'fixresume' ); ?>">
		<li data-step-pill="1" class="active"><?php esc_html_e( 'Basics', 'fixresume' ); ?></li>
		<li data-step-pill="2"><?php esc_html_e( 'Summary', 'fixresume' ); ?></li>
		<li data-step-pill="3"><?php esc_html_e( 'Experience', 'fixresume' ); ?></li>
		<li data-step-pill="4"><?php esc_html_e( 'Education & Skills', 'fixresume' ); ?></li>
		<li data-step-pill="5"><?php esc_html_e( 'Review', 'fixresume' ); ?></li>
		<li data-step-pill="6"><?php esc_html_e( 'Download', 'fixresume' ); ?></li>
	</ol>

	<form id="resume-builder-form" class="builder-form wizard-flow" novalidate>
		<section class="step-card" data-step="1">
			<h2><?php esc_html_e( 'Basics', 'fixresume' ); ?></h2>
			<div class="builder-grid">
				<label class="input-field">
					<span><?php esc_html_e( 'Desired role', 'fixresume' ); ?></span>
					<input type="text" name="job_title" placeholder="<?php esc_attr_e( 'Senior Product Manager', 'fixresume' ); ?>" />
				</label>
				<label class="input-field">
					<span><?php esc_html_e( 'Location', 'fixresume' ); ?></span>
					<input type="text" name="location" placeholder="<?php esc_attr_e( 'City, Country', 'fixresume' ); ?>" />
				</label>
			</div>
			<div class="builder-grid">
				<label class="input-field">
					<span><?php esc_html_e( 'First name', 'fixresume' ); ?></span>
					<input type="text" name="first_name" />
				</label>
				<label class="input-field">
					<span><?php esc_html_e( 'Last name', 'fixresume' ); ?></span>
					<input type="text" name="last_name" />
				</label>
			</div>
			<div class="builder-grid">
				<label class="input-field">
					<span><?php esc_html_e( 'Email', 'fixresume' ); ?></span>
					<input type="email" name="email" />
				</label>
				<label class="input-field">
					<span><?php esc_html_e( 'Phone', 'fixresume' ); ?></span>
					<input type="text" name="phone" />
				</label>
			</div>
			<div class="actions">
				<button type="button" class="button primary" data-next-step="2"><?php esc_html_e( 'Next: Summary', 'fixresume' ); ?></button>
			</div>
		</section>

		<section class="step-card" data-step="2" hidden>
			<h2><?php esc_html_e( 'Professional summary', 'fixresume' ); ?></h2>
			<label class="input-field">
				<textarea name="summary" rows="5" placeholder="<?php esc_attr_e( 'Two to three energetic sentences covering scope, metrics, and toolkit.', 'fixresume' ); ?>"></textarea>
			</label>
			<div class="actions">
				<button type="button" class="button ghost" data-prev-step="1"><?php esc_html_e( 'Back', 'fixresume' ); ?></button>
				<button type="button" class="button soft" id="builder-summary-ai"><?php esc_html_e( 'Improve with AI', 'fixresume' ); ?></button>
				<button type="button" class="button primary" data-next-step="3"><?php esc_html_e( 'Next: Experience', 'fixresume' ); ?></button>
			</div>
		</section>

		<section class="step-card" data-step="3" hidden>
			<div class="builder-block__header">
				<div>
					<h2><?php esc_html_e( 'Experience', 'fixresume' ); ?></h2>
					<p class="muted"><?php esc_html_e( 'Add roles with bullet summaries. AI can rewrite each bullet as you go.', 'fixresume' ); ?></p>
				</div>
				<button type="button" class="builder-add" data-add="employment"><?php esc_html_e( 'Add role', 'fixresume' ); ?></button>
			</div>
			<div class="builder-repeatable" id="employment-list"></div>
			<div class="actions">
				<button type="button" class="button ghost" data-prev-step="2"><?php esc_html_e( 'Back', 'fixresume' ); ?></button>
				<button type="button" class="button primary" data-next-step="4"><?php esc_html_e( 'Next: Education', 'fixresume' ); ?></button>
			</div>
		</section>

		<section class="step-card" data-step="4" hidden>
			<div class="builder-block__header">
				<div>
					<h2><?php esc_html_e( 'Education & Skills', 'fixresume' ); ?></h2>
					<p class="muted"><?php esc_html_e( 'Highlight degrees plus a comma-separated skills list.', 'fixresume' ); ?></p>
				</div>
				<button type="button" class="builder-add" data-add="education"><?php esc_html_e( 'Add education', 'fixresume' ); ?></button>
			</div>
			<div class="builder-repeatable" id="education-list"></div>
			<label class="input-field">
				<span><?php esc_html_e( 'Skills (comma separated)', 'fixresume' ); ?></span>
				<textarea name="skills" rows="3"></textarea>
			</label>
			<div class="actions">
				<button type="button" class="button ghost" data-prev-step="3"><?php esc_html_e( 'Back', 'fixresume' ); ?></button>
				<button type="button" class="button primary" data-next-step="5"><?php esc_html_e( 'Next: Review', 'fixresume' ); ?></button>
			</div>
		</section>

		<section class="step-card" data-step="5" hidden>
			<div class="review-header">
				<div>
					<p class="eyebrow"><?php esc_html_e( 'Preview', 'fixresume' ); ?></p>
					<h2><?php esc_html_e( 'Review before generating', 'fixresume' ); ?></h2>
				</div>
			</div>
			<div class="review-preview">
				<div class="preview-pane builder-preview" id="builder-preview">
					<p class="builder-preview__placeholder"><?php esc_html_e( 'Fill the form to see your resume preview.', 'fixresume' ); ?></p>
				</div>
			</div>
			<div class="actions">
				<button type="button" class="button ghost" data-prev-step="4"><?php esc_html_e( 'Back', 'fixresume' ); ?></button>
				<button type="submit" class="button primary" id="builder-generate"><?php esc_html_e( 'Generate with AI', 'fixresume' ); ?></button>
				<button type="button" class="button soft" id="builder-reset"><?php esc_html_e( 'Clear form', 'fixresume' ); ?></button>
				<button type="button" class="button primary" data-next-step="6"><?php esc_html_e( 'Next: Download', 'fixresume' ); ?></button>
			</div>
		</section>

		<section class="step-card" data-step="6" hidden>
			<h2><?php esc_html_e( 'Download & share', 'fixresume' ); ?></h2>
			<p class="muted"><?php esc_html_e( 'Copy everything for free. PDF & DOCX exports require an active plan.', 'fixresume' ); ?></p>
			<div class="actions review-actions">
				<button type="button" class="button ghost" id="builder-copy"><?php esc_html_e( 'Copy resume text', 'fixresume' ); ?></button>
				<button type="button" class="button primary" id="builder-download-pdf" data-download-format="pdf" data-can="<?php echo esc_attr( $builder_can_download ? '1' : '0' ); ?>">
					<?php esc_html_e( 'Download PDF', 'fixresume' ); ?>
				</button>
				<button type="button" class="button primary" id="builder-download-docx" data-download-format="docx" data-can="<?php echo esc_attr( $builder_can_download ? '1' : '0' ); ?>">
					<?php esc_html_e( 'Download DOCX', 'fixresume' ); ?>
				</button>
			</div>
			<?php if ( ! $builder_can_download ) : ?>
				<p class="builder-paywall__note"><?php esc_html_e( 'Need DOCX exports or unlimited PDFs? Unlock downloads to continue.', 'fixresume' ); ?></p>
			<?php endif; ?>
		</section>
	</form>
</main>

<?php get_template_part( 'template-parts/modal', 'unlock' ); ?>

<?php
get_footer();
