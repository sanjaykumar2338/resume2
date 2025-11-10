<?php
/**
 * Template Name: Enhance Resume Wizard
 *
 * @package FixResume
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

$can_download = is_user_logged_in() && function_exists( 'rai_user_can_download' ) ? rai_user_can_download( get_current_user_id() ) : false;
?>

<main id="enhance-wizard" class="wizard-page container">
	<header class="wizard-hero">
		<p class="eyebrow"><?php esc_html_e( 'Polish what you already have', 'fixresume' ); ?></p>
		<h1><?php esc_html_e( 'Upload, pick goals, and review AI suggestions for free.', 'fixresume' ); ?></h1>
		<p class="lead">
			<?php esc_html_e( 'Download-ready exports stay behind the paywall, but every analysis is free while you experiment.', 'fixresume' ); ?>
		</p>
	</header>

	<ol class="stepper" aria-label="<?php esc_attr_e( 'Enhance steps', 'fixresume' ); ?>">
		<li data-step-pill="1" class="active"><?php esc_html_e( 'Upload', 'fixresume' ); ?></li>
		<li data-step-pill="2"><?php esc_html_e( 'Goals', 'fixresume' ); ?></li>
		<li data-step-pill="3"><?php esc_html_e( 'Review', 'fixresume' ); ?></li>
	</ol>

	<section class="step-card" data-step="1">
		<h2><?php esc_html_e( 'Upload your current resume', 'fixresume' ); ?></h2>
		<p><?php esc_html_e( 'We accept PDF, DOC, or DOCX files up to 5MB.', 'fixresume' ); ?></p>
		<label class="input-field file-field">
			<input type="file" id="enhance-file" accept=".pdf,.doc,.docx" />
			<span><?php esc_html_e( 'Choose file', 'fixresume' ); ?></span>
		</label>
		<p class="file-meta" id="enhance-file-meta"><?php esc_html_e( 'No file selected yet.', 'fixresume' ); ?></p>
		<div class="actions">
			<button type="button" class="button primary" data-next-step="2"><?php esc_html_e( 'Next: Goals', 'fixresume' ); ?></button>
		</div>
	</section>

	<section class="step-card" data-step="2" hidden>
		<h2><?php esc_html_e( 'Tell us what to improve', 'fixresume' ); ?></h2>
		<div class="goal-grid">
			<label class="goal-pill">
				<input type="checkbox" value="grammar" />
				<span><?php esc_html_e( 'Grammar & clarity', 'fixresume' ); ?></span>
			</label>
			<label class="goal-pill">
				<input type="checkbox" value="keywords" />
				<span><?php esc_html_e( 'Keywords (ATS)', 'fixresume' ); ?></span>
			</label>
			<label class="goal-pill">
				<input type="checkbox" value="formatting" />
				<span><?php esc_html_e( 'Formatting', 'fixresume' ); ?></span>
			</label>
			<label class="goal-pill">
				<input type="checkbox" value="tone" />
				<span><?php esc_html_e( 'Tone & voice', 'fixresume' ); ?></span>
			</label>
		</div>
		<div class="wizard-grid">
			<label class="input-field">
				<span><?php esc_html_e( 'Target job title (optional)', 'fixresume' ); ?></span>
				<input type="text" id="enhance-role" />
			</label>
			<label class="input-field">
				<span><?php esc_html_e( 'Industry', 'fixresume' ); ?></span>
				<input type="text" id="enhance-industry" />
			</label>
			<label class="input-field">
				<span><?php esc_html_e( 'Years of experience', 'fixresume' ); ?></span>
				<input type="number" min="0" max="40" id="enhance-years" />
			</label>
		</div>
		<div class="actions">
			<button type="button" class="button ghost" data-prev-step="1"><?php esc_html_e( 'Back', 'fixresume' ); ?></button>
			<button type="button" class="button primary" id="enhance-analyze"><?php esc_html_e( 'Analyze with AI', 'fixresume' ); ?></button>
		</div>
	</section>

	<section class="step-card" data-step="3" hidden>
		<div class="review-header">
			<div>
				<p class="eyebrow"><?php esc_html_e( 'AI Insights', 'fixresume' ); ?></p>
				<h2><?php esc_html_e( 'Review before you download', 'fixresume' ); ?></h2>
			</div>
			<div class="score-badge" id="enhance-score">--</div>
		</div>

		<div class="review-layout">
			<div class="review-cards" id="enhance-cards">
				<p class="wizard-placeholder"><?php esc_html_e( 'Run an analysis to see suggestions.', 'fixresume' ); ?></p>
			</div>
			<div class="review-preview">
				<h3><?php esc_html_e( 'Preview', 'fixresume' ); ?></h3>
				<div class="preview-pane" id="enhance-preview">
					<p class="wizard-placeholder"><?php esc_html_e( 'Your optimized resume preview will appear here.', 'fixresume' ); ?></p>
				</div>
			</div>
		</div>

		<div class="actions review-actions">
			<button type="button" class="button ghost" id="enhance-copy" disabled><?php esc_html_e( 'Copy suggestions', 'fixresume' ); ?></button>
			<button type="button" class="button primary" id="enhance-download-pdf" data-format="pdf" data-can="<?php echo esc_attr( $can_download ? '1' : '0' ); ?>">
				<?php esc_html_e( 'Download PDF', 'fixresume' ); ?>
			</button>
			<button type="button" class="button primary" id="enhance-download-docx" data-format="docx" data-can="<?php echo esc_attr( $can_download ? '1' : '0' ); ?>">
				<?php esc_html_e( 'Download DOCX', 'fixresume' ); ?>
			</button>
		</div>
	</section>
</main>

<?php get_template_part( 'template-parts/modal', 'unlock' ); ?>

<?php
get_footer();
