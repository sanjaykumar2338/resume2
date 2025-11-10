<?php
/**
 * Template Name: Account Dashboard
 *
 * @package FixResume
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! is_user_logged_in() ) {
	wp_safe_redirect( wp_login_url( get_permalink() ) );
	exit;
}

$current_user  = wp_get_current_user();
$status        = get_user_meta( $current_user->ID, 'rai_stripe_status', true );
$end           = (int) get_user_meta( $current_user->ID, 'rai_current_period_end', true );
$plan          = get_user_meta( $current_user->ID, 'rai_stripe_plan_slug', true );
$can_download  = function_exists( 'rai_user_can_download' ) ? rai_user_can_download( $current_user->ID ) : false;
$renewal_label = $end ? date_i18n( get_option( 'date_format' ), $end ) : __( '—', 'fixresume' );
$note_message  = $can_download
	? __( 'Downloads are unlocked. Head to the builder to export anytime.', 'fixresume' )
	: __( 'Downloads are locked. Upgrade to Quarterly to unlock exports.', 'fixresume' );
$note_state    = $can_download ? 'status-note--active' : 'status-note--locked';
$uploads       = class_exists( 'Resume_AI_Data_Store' ) ? Resume_AI_Data_Store::get_user_submissions( $current_user->user_email, 'enhance', 5, $current_user->ID ) : [];
$builders      = class_exists( 'Resume_AI_Data_Store' ) ? Resume_AI_Data_Store::get_user_submissions( $current_user->user_email, 'builder', 5, $current_user->ID ) : [];

$render_entries = function ( array $entries, string $context = 'enhance' ) {
	if ( empty( $entries ) ) {
		return '<p class="dashboard-empty">' . esc_html__( 'No entries recorded yet.', 'fixresume' ) . '</p>';
	}

	ob_start();
	?>
	<ul class="dashboard-list">
		<?php foreach ( $entries as $entry ) : ?>
			<li class="dashboard-list__item">
				<div>
					<p class="dashboard-list__title">
						<?php
						$headline = $entry['target_role'] ?: ( $entry['file_name'] ?: __( 'Resume update', 'fixresume' ) );
						echo esc_html( $headline );
						?>
					</p>
					<span class="dashboard-list__meta">
						<?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $entry['created_at'] ) ) ); ?>
					</span>
					</div>
					<span class="dashboard-list__score">
						<?php echo isset( $entry['score'] ) && '' !== $entry['score'] ? esc_html( $entry['score'] . '/100' ) : '—'; ?>
					</span>
					<?php
					$file_label = $entry['file_name'] ? sprintf( /* translators: %s file name */ __( 'File: %s', 'fixresume' ), $entry['file_name'] ) : '';
					$context_label = ( 'builder' === $context && $entry['target_role'] ) ? sprintf( /* translators: %s job title */ __( 'Role: %s', 'fixresume' ), $entry['target_role'] ) : '';
					$snippet = '';
					if ( ! empty( $entry['document'] ) ) {
						$clean_document = wp_strip_all_tags( $entry['document'] );
						$snippet        = wp_trim_words( $clean_document, 40, '…' );
					}
					?>
					<div class="dashboard-list__details">
						<?php if ( $file_label ) : ?>
							<p><?php echo esc_html( $file_label ); ?></p>
						<?php endif; ?>
						<?php if ( $context_label ) : ?>
							<p><?php echo esc_html( $context_label ); ?></p>
						<?php endif; ?>
						<?php if ( $snippet ) : ?>
							<details>
								<summary><?php esc_html_e( 'View AI suggestions', 'fixresume' ); ?></summary>
								<p><?php echo esc_html( $snippet ); ?></p>
							</details>
						<?php endif; ?>
					</div>
				</li>
			<?php endforeach; ?>
		</ul>
		<?php
	return ob_get_clean();
};

get_header();
?>

<main class="account-dashboard container">
	<header class="dashboard-header">
		<p class="eyebrow"><?php esc_html_e( 'Welcome back', 'fixresume' ); ?></p>
		<h1><?php echo esc_html( $current_user->display_name ?: $current_user->user_login ); ?></h1>
		<p class="lead"><?php esc_html_e( 'Track your subscription, review AI runs, and jump back into the builder without leaving this page.', 'fixresume' ); ?></p>
	</header>

	<div class="dashboard-grid">
		<section class="dashboard-card dashboard-card--subscription">
			<div class="dashboard-card__header">
				<p class="eyebrow"><?php esc_html_e( 'Subscription status', 'fixresume' ); ?></p>
				<h2><?php echo $status ? esc_html( ucfirst( $status ) ) : esc_html__( 'Not active', 'fixresume' ); ?></h2>
			</div>
			<dl class="subscription-meta">
				<div>
					<dt><?php esc_html_e( 'User login', 'fixresume' ); ?></dt>
					<dd><?php echo esc_html( $current_user->user_login ); ?></dd>
				</div>
				<div>
					<dt><?php esc_html_e( 'Status', 'fixresume' ); ?></dt>
					<dd><?php echo $status ? esc_html( ucfirst( $status ) ) : esc_html__( 'Not active', 'fixresume' ); ?></dd>
				</div>
				<div>
					<dt><?php esc_html_e( 'Renews', 'fixresume' ); ?></dt>
					<dd><?php echo esc_html( $renewal_label ); ?></dd>
				</div>
			</dl>
			<div class="dashboard-actions">
				<button type="button" class="button soft" data-portal-trigger id="account-portal">
					<?php esc_html_e( 'Manage billing', 'fixresume' ); ?>
				</button>
				<?php if ( 'primary' === $plan ) : ?>
					<button type="button" class="button ghost" disabled>
						<?php esc_html_e( 'Quarterly active', 'fixresume' ); ?>
					</button>
				<?php else : ?>
					<button type="button" class="button primary ra-trial-button" data-plan="primary">
						<?php esc_html_e( 'Upgrade to Quarterly', 'fixresume' ); ?>
					</button>
				<?php endif; ?>
			</div>
			<p class="status-note <?php echo esc_attr( $note_state ); ?>">
				<?php echo esc_html( $note_message ); ?>
			</p>
		</section>

		<div class="dashboard-card-stack">
			<section class="dashboard-card dashboard-card--list">
				<div class="dashboard-card__header">
					<p class="eyebrow"><?php esc_html_e( 'Enhance flow', 'fixresume' ); ?></p>
					<h2><?php esc_html_e( 'Processed uploads', 'fixresume' ); ?></h2>
				</div>
				<?php echo $render_entries( $uploads, 'enhance' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</section>

			<section class="dashboard-card dashboard-card--list">
				<div class="dashboard-card__header">
					<p class="eyebrow"><?php esc_html_e( 'Builder flow', 'fixresume' ); ?></p>
					<h2><?php esc_html_e( 'New resumes created', 'fixresume' ); ?></h2>
				</div>
				<?php echo $render_entries( $builders, 'builder' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</section>

			<a class="button ghost dashboard-create" href="<?php echo esc_url( home_url( '/resume-builder/' ) ); ?>">
				<?php esc_html_e( 'Create new resume', 'fixresume' ); ?>
			</a>
		</div>
	</div>
</main>

<style>
	.account-dashboard {
		padding: clamp(2rem, 4vw, 3.5rem) 0 4rem;
	}
	.account-dashboard .dashboard-header {
		margin-bottom: 2rem;
		text-align: left;
	}
	.dashboard-grid {
		display: grid;
		gap: 24px;
	}
	@media (min-width: 900px) {
		.dashboard-grid {
			grid-template-columns: 1fr 1.3fr;
			align-items: start;
		}
	}
	.dashboard-card,
	.dashboard-card-stack > .dashboard-card {
		background: #fff;
		border-radius: 16px;
		padding: 20px;
		box-shadow: 0 20px 40px rgba(15, 23, 42, 0.08);
	}
	.dashboard-card__header h2 {
		margin: 0;
	}
	.subscription-meta {
		display: grid;
		grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
		gap: 12px;
		margin: 1.5rem 0;
	}
	.subscription-meta dt {
		font-size: 0.85rem;
		text-transform: uppercase;
		letter-spacing: 0.04em;
		color: #6b7280;
		margin-bottom: 0.25rem;
	}
	.subscription-meta dd {
		margin: 0;
		font-size: 1.1rem;
		font-weight: 600;
		color: #111827;
	}
	.dashboard-actions {
		display: flex;
		flex-wrap: wrap;
		gap: 12px;
		margin-bottom: 1rem;
	}
	.button.soft {
		background: #ecfdf5;
		color: #047857;
		border: none;
	}
	.status-note {
		padding: 0.9rem 1.1rem;
		border-radius: 12px;
		font-size: 0.95rem;
	}
	.status-note--active {
		background: #ecfdf5;
		color: #047857;
	}
	.status-note--locked {
		background: #fff7ed;
		color: #c2410c;
	}
	.dashboard-card--list {
		min-height: 250px;
	}
	.dashboard-list {
		list-style: none;
		padding: 0;
		margin: 0;
	}
	.dashboard-list__item {
		display: flex;
		justify-content: space-between;
		gap: 12px;
		padding: 12px 0;
		flex-wrap: wrap;
		border-bottom: 1px solid rgba(107, 114, 128, 0.15);
	}
	.dashboard-list__item:last-child {
		border-bottom: none;
	}
	.dashboard-list__title {
		margin: 0 0 4px;
		font-weight: 600;
		color: #111827;
	}
	.dashboard-list__meta {
		font-size: 0.85rem;
		color: #6b7280;
	}
	.dashboard-list__score {
		font-weight: 700;
		color: #10b981;
		white-space: nowrap;
	}
	.dashboard-list__details {
		flex-basis: 100%;
		font-size: 0.9rem;
		color: #4b5563;
	}
	.dashboard-list__details p {
		margin: 6px 0;
	}
	.dashboard-list__details details {
		margin-top: 4px;
		border: 1px solid rgba(107, 114, 128, 0.2);
		border-radius: 10px;
		padding: 8px 12px;
		background: #f9fafb;
	}
	.dashboard-list__details summary {
		cursor: pointer;
		font-weight: 600;
		color: #0f172a;
	}
	.dashboard-empty {
		color: #6b7280;
	}
	.dashboard-card-stack {
		display: flex;
		flex-direction: column;
		gap: 20px;
	}
	.dashboard-create {
		align-self: flex-start;
	}
	@media (max-width: 899px) {
		.dashboard-create {
			align-self: stretch;
			text-align: center;
		}
	}
</style>

<?php
get_footer();
