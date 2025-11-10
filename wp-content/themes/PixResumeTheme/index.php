<?php
/**
 * Front page template for FixResume.
 *
 * @package FixResume
 */

defined( 'ABSPATH' ) || exit;

get_header();

$front_can_download = is_user_logged_in() && function_exists( 'rai_user_can_download' ) ? rai_user_can_download( get_current_user_id() ) : false;
?>

<main>
	<section class="hero" id="home">
		<div class="container hero-grid">
			<div class="hero-copy">
				<p class="eyebrow"><?php esc_html_e( 'AI-powered resume tune-up', 'fixresume' ); ?></p>
				<h1><?php esc_html_e( 'Upload your resume, get instant wording suggestions', 'fixresume' ); ?></h1>
				<p class="lead">
					<?php esc_html_e( 'FixResume reviews every section of your resume, highlights weak phrasing, and delivers targeted rewrites that match the roles you want next.', 'fixresume' ); ?>
				</p>
				<div class="hero-actions">
					<a class="button primary" href="#upload"><?php esc_html_e( 'Upload resume', 'fixresume' ); ?></a>
					<a class="button ghost" href="#sample"><?php esc_html_e( 'View sample output', 'fixresume' ); ?></a>
					<a class="button ghost" href="<?php echo esc_url( home_url( '/resume-builder/' ) ); ?>"><?php esc_html_e( 'Create resume', 'fixresume' ); ?></a>
				</div>
				<ul class="trust-points">
					<li><?php esc_html_e( 'Boost clarity, confidence, and measurable impact', 'fixresume' ); ?></li>
					<li><?php esc_html_e( 'Align wording with ATS-friendly keywords in seconds', 'fixresume' ); ?></li>
					<li><?php esc_html_e( 'Get actionable edits instead of vague advice', 'fixresume' ); ?></li>
				</ul>
			</div>
			<aside class="hero-card" aria-label="<?php esc_attr_e( 'What FixResume delivers', 'fixresume' ); ?>">
				<header>
					<p class="hero-card__title"><?php esc_html_e( 'What you’ll receive', 'fixresume' ); ?></p>
				</header>
				<ul>
					<li>
						<span class="pill"><?php esc_html_e( 'Rewrite suggestions', 'fixresume' ); ?></span>
						<p><?php esc_html_e( 'Sharper action verbs, quantified results, and concise statements.', 'fixresume' ); ?></p>
					</li>
					<li>
						<span class="pill"><?php esc_html_e( 'Keyword insights', 'fixresume' ); ?></span>
						<p><?php esc_html_e( 'Role-specific language drawn from current job descriptions.', 'fixresume' ); ?></p>
					</li>
					<li>
						<span class="pill"><?php esc_html_e( 'Formatting checks', 'fixresume' ); ?></span>
						<p><?php esc_html_e( 'Reminders when layout, tense, or punctuation gets in the way.', 'fixresume' ); ?></p>
					</li>
				</ul>
			</aside>
		</div>
	</section>

	<section class="upload" id="upload">
		<div class="container upload-grid">
			<div class="upload-card">
				<h2><?php esc_html_e( 'Upload your resume to start enhancing', 'fixresume' ); ?></h2>
				<p><?php esc_html_e( 'We analyze PDFs and DOCX files, then map each section to the expectations of hiring managers in your target role.', 'fixresume' ); ?></p>
				<form class="upload-form" aria-label="<?php esc_attr_e( 'Resume optimization form', 'fixresume' ); ?>">
					<label class="input-field">
						<span><?php esc_html_e( 'Resume file (PDF or DOCX)', 'fixresume' ); ?></span>
						<input accept=".pdf,.doc,.docx" name="resume" type="file" />
					</label>
					<label class="input-field">
						<span><?php esc_html_e( 'Target job title', 'fixresume' ); ?></span>
						<input name="role" placeholder="<?php esc_attr_e( 'e.g. Product Marketing Manager', 'fixresume' ); ?>" type="text" />
					</label>
					<label class="input-field">
						<span><?php esc_html_e( 'Top priority', 'fixresume' ); ?></span>
						<select name="priority">
							<option value="impact"><?php esc_html_e( 'Emphasize measurable impact', 'fixresume' ); ?></option>
							<option value="story"><?php esc_html_e( 'Balance story and keywords', 'fixresume' ); ?></option>
							<option value="keywords"><?php esc_html_e( 'Maximize ATS keyword alignment', 'fixresume' ); ?></option>
						</select>
					</label>
					<button class="button primary" type="submit"><?php esc_html_e( 'Generate suggestions', 'fixresume' ); ?></button>
				</form>
				<div class="upload-results" hidden></div>
				<?php if ( ! $front_can_download ) : ?>
					<div class="upload-paywall">
						<p><?php esc_html_e( 'Downloads unlock with the AI Resume Optimizer plan.', 'fixresume' ); ?></p>
						<a class="button primary" data-pricing-link href="<?php echo esc_url( home_url( '/pricing/' ) ); ?>"><?php esc_html_e( 'See pricing', 'fixresume' ); ?></a>
					</div>
				<?php endif; ?>
				<p class="disclaimer"><?php esc_html_e( 'We analyze your file securely and return suggestions in seconds. Your resume is deleted immediately after processing.', 'fixresume' ); ?></p>
			</div>
			<div class="upload-notes">
				<h3><?php esc_html_e( 'What happens next?', 'fixresume' ); ?></h3>
				<ol>
					<li><?php esc_html_e( 'FixResume parses each section, keeping your original voice intact.', 'fixresume' ); ?></li>
					<li><?php esc_html_e( 'Weak lines are flagged with rewrite options and rationale.', 'fixresume' ); ?></li>
					<li><?php esc_html_e( 'You accept edits in a clean review panel or export an annotated PDF.', 'fixresume' ); ?></li>
				</ol>
			</div>
		</div>
	</section>

	<section class="features" id="features">
		<div class="container">
			<h2><?php esc_html_e( 'Tailored guidance for every resume section', 'fixresume' ); ?></h2>
			<div class="feature-grid">
				<article class="feature-card">
					<h3><?php esc_html_e( 'Professional summary', 'fixresume' ); ?></h3>
					<p><?php esc_html_e( 'Replace generic statements with a focused value pitch tuned to your desired role and industry.', 'fixresume' ); ?></p>
				</article>
				<article class="feature-card">
					<h3><?php esc_html_e( 'Experience & achievements', 'fixresume' ); ?></h3>
					<p><?php esc_html_e( 'Convert responsibilities into quantified wins that showcase leadership, ownership, and growth.', 'fixresume' ); ?></p>
				</article>
				<article class="feature-card">
					<h3><?php esc_html_e( 'Skills & keywords', 'fixresume' ); ?></h3>
					<p><?php esc_html_e( 'Map in-demand competencies directly from current job descriptions so you pass ATS screens.', 'fixresume' ); ?></p>
				</article>
				<article class="feature-card">
					<h3><?php esc_html_e( 'Layout recommendations', 'fixresume' ); ?></h3>
					<p><?php esc_html_e( 'Keep formatting clean with tense checks, consistent punctuation, and scannable bullet structure.', 'fixresume' ); ?></p>
				</article>
			</div>
		</div>
	</section>

	<section class="sample" id="sample">
		<div class="container">
			<h2><?php esc_html_e( 'See FixResume suggestions in action', 'fixresume' ); ?></h2>
			<div class="sample-grid">
				<article class="sample-card">
					<h3><?php esc_html_e( 'Summary', 'fixresume' ); ?></h3>
					<p class="label"><?php esc_html_e( 'Uploaded wording', 'fixresume' ); ?></p>
					<p class="before"><?php esc_html_e( '“Responsible for managing marketing projects for the SaaS product.”', 'fixresume' ); ?></p>
					<p class="label"><?php esc_html_e( 'FixResume suggestion', 'fixresume' ); ?></p>
					<p class="after"><?php esc_html_e( '“Led cross-functional SaaS campaigns that lifted qualified pipeline 28% in two quarters.”', 'fixresume' ); ?></p>
					<p class="note"><?php esc_html_e( 'Focus on measurable growth and impactful verbs.', 'fixresume' ); ?></p>
				</article>
				<article class="sample-card">
					<h3><?php esc_html_e( 'Experience bullet', 'fixresume' ); ?></h3>
					<p class="label"><?php esc_html_e( 'Uploaded wording', 'fixresume' ); ?></p>
					<p class="before"><?php esc_html_e( '“Worked with sales to improve demo process.”', 'fixresume' ); ?></p>
					<p class="label"><?php esc_html_e( 'FixResume suggestion', 'fixresume' ); ?></p>
					<p class="after"><?php esc_html_e( '“Built a sales enablement flow that cut demo prep time 45% and raised close rate 12%.”', 'fixresume' ); ?></p>
					<p class="note"><?php esc_html_e( 'Clarify what changed, how much, and why it matters.', 'fixresume' ); ?></p>
				</article>
				<article class="sample-card">
					<h3><?php esc_html_e( 'Skills section', 'fixresume' ); ?></h3>
					<p class="label"><?php esc_html_e( 'Uploaded wording', 'fixresume' ); ?></p>
					<p class="before"><?php esc_html_e( '“Skilled in analytics, communication, and leadership.”', 'fixresume' ); ?></p>
					<p class="label"><?php esc_html_e( 'FixResume suggestion', 'fixresume' ); ?></p>
					<p class="after"><?php esc_html_e( '“Analytics (Looker, SQL), stakeholder storytelling, distributed team leadership (20+ reports).”', 'fixresume' ); ?></p>
					<p class="note"><?php esc_html_e( 'Replace vague traits with specific, verifiable capabilities.', 'fixresume' ); ?></p>
				</article>
			</div>
		</div>
	</section>

	<section class="how-it-works" id="how-it-works">
		<div class="container">
			<h2><?php esc_html_e( 'How FixResume delivers smarter edits', 'fixresume' ); ?></h2>
			<div class="steps">
				<article class="step">
					<div class="step-number">1</div>
					<h3><?php esc_html_e( 'Upload & select target role', 'fixresume' ); ?></h3>
					<p><?php esc_html_e( 'Drop in your resume and point us at the job you want. We match language patterns used by recruiters hiring for that role today.', 'fixresume' ); ?></p>
				</article>
				<article class="step">
					<div class="step-number">2</div>
					<h3><?php esc_html_e( 'Review prioritized suggestions', 'fixresume' ); ?></h3>
					<p><?php esc_html_e( 'FixResume sorts edits by impact level so you know exactly which sentences to update first.', 'fixresume' ); ?></p>
				</article>
				<article class="step">
					<div class="step-number">3</div>
					<h3><?php esc_html_e( 'Apply with confidence', 'fixresume' ); ?></h3>
					<p><?php esc_html_e( 'Download a revision-ready doc or copy key suggestions straight into your editor of choice.', 'fixresume' ); ?></p>
				</article>
			</div>
		</div>
	</section>

	<section class="cta">
		<div class="container cta-panel">
			<div>
				<h2><?php esc_html_e( 'Ready to enhance your resume?', 'fixresume' ); ?></h2>
				<p><?php esc_html_e( 'Upload a file, see suggested rewrites in seconds, and apply for your next role with confidence.', 'fixresume' ); ?></p>
			</div>
			<a class="button primary" href="#upload"><?php esc_html_e( 'Upload resume', 'fixresume' ); ?></a>
		</div>
	</section>

	<section class="faq" id="faq">
		<div class="container">
			<h2><?php esc_html_e( 'Frequently asked questions', 'fixresume' ); ?></h2>
			<div class="faq-list">
				<details>
					<summary><?php esc_html_e( 'What file types are supported?', 'fixresume' ); ?></summary>
					<p><?php esc_html_e( 'We currently support PDF, DOC, and DOCX uploads. Additional formats like plain text and Markdown are on the roadmap.', 'fixresume' ); ?></p>
				</details>
				<details>
					<summary><?php esc_html_e( 'Are the suggestions fully automated?', 'fixresume' ); ?></summary>
					<p><?php esc_html_e( 'Yes. FixResume uses AI models trained on successful resumes and live job descriptions. You always review every recommendation before applying it.', 'fixresume' ); ?></p>
				</details>
				<details>
					<summary><?php esc_html_e( 'Will FixResume change my entire resume?', 'fixresume' ); ?></summary>
					<p><?php esc_html_e( 'No. We keep your structure and voice, highlighting only the lines that benefit from stronger wording, quantifiable impact, or better keywords.', 'fixresume' ); ?></p>
				</details>
				<details>
					<summary><?php esc_html_e( 'Is my data secure?', 'fixresume' ); ?></summary>
					<p><?php esc_html_e( 'Resume files are encrypted in transit and automatically deleted after 24 hours. You can remove them instantly through your dashboard.', 'fixresume' ); ?></p>
				</details>
			</div>
		</div>
	</section>
</main>

<?php
get_footer();
