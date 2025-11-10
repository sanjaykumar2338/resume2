<?php
/**
 * Plugin Name: Resume AI Toolkit
 * Description: Adds the resume optimizer REST endpoint and frontend shortcode for the MVP flow.
 * Version: 0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$autoload = plugin_dir_path( __FILE__ ) . 'vendor/autoload.php';
if ( file_exists( $autoload ) ) {
	require_once $autoload;
}

require_once plugin_dir_path( __FILE__ ) . 'includes/class-resume-ai-endpoints.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-resume-ai-data-store.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-rai-billing.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-rai-user-subscriptions.php';
if ( is_admin() ) {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-resume-ai-admin.php';
}

if ( class_exists( 'Resume_AI_Data_Store' ) ) {
	Resume_AI_Data_Store::init();
	register_activation_hook( __FILE__, [ 'Resume_AI_Data_Store', 'activate' ] );
}

if ( class_exists( 'Resume_AI_Billing' ) ) {
	Resume_AI_Billing::init();
}

if ( class_exists( 'Resume_AI_User_Subscriptions' ) ) {
	Resume_AI_User_Subscriptions::init();
}

if ( class_exists( 'Resume_AI_Admin_Page' ) ) {
	Resume_AI_Admin_Page::init();
}

if ( ! class_exists( 'Resume_AI_Toolkit' ) ) {

	class Resume_AI_Toolkit {

		const REST_NAMESPACE = Resume_AI_EndPoints::REST_NAMESPACE;

		/**
		 * Bootstrap hooks.
		 */
		public function __construct() {
			add_shortcode( 'resume_optimizer_form', [ $this, 'render_form_shortcode' ] );
			add_shortcode( 'resume_ai_dashboard', [ $this, 'render_dashboard_shortcode' ] );
			add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
		}

		/**
		 * Expose REST endpoint information to the frontend.
		 */
		public function enqueue_assets() {
			if ( ! is_singular() ) {
				return;
			}

			global $post;

			if ( ! $post instanceof \WP_Post ) {
				return;
			}

			$post_contains_shortcode = false;

			if ( has_shortcode( $post->post_content, 'resume_optimizer_form' ) ) {
				$post_contains_shortcode = true;
			} else {
				$elementor_data = get_post_meta( $post->ID, '_elementor_data', true );
				if ( is_string( $elementor_data ) && false !== strpos( $elementor_data, '[resume_optimizer_form]' ) ) {
					$post_contains_shortcode = true;
				}
			}

			if ( ! $post_contains_shortcode ) {
				return;
			}

				$handle = 'resume-ai-toolkit';
				$js_path = plugin_dir_url( __FILE__ ) . 'assets/js/resume-ai-toolkit.js';
				$js_file = plugin_dir_path( __FILE__ ) . 'assets/js/resume-ai-toolkit.js';
				$version = file_exists( $js_file ) ? filemtime( $js_file ) : '0.1.0';
			$endpoint = rest_url( self::REST_NAMESPACE . '/optimize' );
			$alternate = add_query_arg(
				[
					'rest_route' => '/' . self::REST_NAMESPACE . '/optimize',
				],
				site_url( 'index.php' )
			);

				wp_enqueue_script( 'sweetalert2', 'https://cdn.jsdelivr.net/npm/sweetalert2@11', [], null, true );
				wp_enqueue_script( $handle, $js_path, [ 'sweetalert2' ], $version, true );
				wp_add_inline_script(
					$handle,
					sprintf(
						'window.resumeAiToolkit = %s;',
						wp_json_encode(
							[
								'endpoint' => esc_url_raw( $endpoint ),
								'alt'      => esc_url_raw( $alternate ),
								'loadingText' => esc_html__( 'Analyzing…', 'resume-ai-toolkit' ),
								'successText' => esc_html__( 'Resume optimized!', 'resume-ai-toolkit' ),
								'errorTitle'  => esc_html__( 'Something went wrong', 'resume-ai-toolkit' ),
								'warningTitle' => esc_html__( 'Heads up', 'resume-ai-toolkit' ),
								'fileMissing' => esc_html__( 'Please attach your resume before submitting.', 'resume-ai-toolkit' ),
							]
						)
					),
					'before'
				);
		}


		/**
		 * Render the resume optimizer form shortcode.
		 *
		 * @return string
		 */
		public function render_form_shortcode() {
			$hint_text    = esc_html__( 'PDF / DOC / DOCX up to 5MB.', 'resume-ai-toolkit' );
			$target_placeholder = esc_attr__( 'Add keywords or the role you are targeting.', 'resume-ai-toolkit' );
			$email_placeholder  = esc_attr__( 'We can email a copy of the suggestions.', 'resume-ai-toolkit' );
			$button_label  = esc_html__( 'Optimize Now', 'resume-ai-toolkit' );
			$resume_label  = esc_html__( 'Resume File', 'resume-ai-toolkit' );
			$target_label  = esc_html__( 'Target Role or Keywords (optional)', 'resume-ai-toolkit' );
			$email_label   = esc_html__( 'Email (optional)', 'resume-ai-toolkit' );

			$email_value = '';
			if ( is_user_logged_in() ) {
				$email_value = wp_get_current_user()->user_email;
			}

			$html  = '<div class="resume-optimizer-wrapper">';
			$html .= '<form id="resume-optimizer-form" class="resume-optimizer-form" enctype="multipart/form-data" novalidate>';
			$html .= '<div class="resume-optimizer-field">';
			$html .= '<label for="resume_file">' . $resume_label . '</label>';
			$html .= '<input type="file" id="resume_file" name="resume_file" accept=".pdf,.doc,.docx" required />';
			$html .= '<p class="resume-optimizer-hint">' . $hint_text . '</p>';
			$html .= '</div>';
			$html .= '<div class="resume-optimizer-field">';
			$html .= '<label for="target_role">' . $target_label . '</label>';
			$html .= '<textarea id="target_role" name="target_role" rows="4" placeholder="' . $target_placeholder . '"></textarea>';
			$html .= '</div>';
			$html .= '<div class="resume-optimizer-field">';
			$html .= '<label for="user_email">' . $email_label . '</label>';
			$html .= '<input type="email" id="user_email" name="user_email" placeholder="' . $email_placeholder . '" value="' . esc_attr( $email_value ) . '" />';
			$html .= '</div>';
			$html .= '<button type="submit" class="resume-optimizer-submit">' . $button_label . '</button>';
			$html .= '</form>';
			$html .= '<div id="ai-results" class="ai-results" aria-live="polite" aria-atomic="true"></div>';
			$html .= '</div>';

			return $html;
		}

		/**
		 * Render the logged-in dashboard.
		 */
		public function render_dashboard_shortcode() {
			if ( ! is_user_logged_in() ) {
				$login_url = wp_login_url( get_permalink() );
				return sprintf(
					'<p>%s</p>',
					sprintf(
						/* translators: %s - login url */
						esc_html__( 'Please %s to view your saved resumes.', 'resume-ai-toolkit' ),
						'<a href="' . esc_url( $login_url ) . '">' . esc_html__( 'sign in', 'resume-ai-toolkit' ) . '</a>'
					)
				);
			}

			$user    = wp_get_current_user();
			$email   = $user->user_email;
			$user_id = $user->ID;

			$uploads = class_exists( 'Resume_AI_Data_Store' ) ? Resume_AI_Data_Store::get_user_submissions( $email, 'enhance', 50, $user_id ) : [];
			$builders = class_exists( 'Resume_AI_Data_Store' ) ? Resume_AI_Data_Store::get_user_submissions( $email, 'builder', 50, $user_id ) : [];

			ob_start();
			static $styles_printed = false;

			if ( ! $styles_printed ) {
				?>
				<style>
					.rai-dashboard__section { margin-bottom: 2rem; }
					.rai-dashboard__table { width: 100%; border-collapse: collapse; background: #fff; box-shadow: 0 10px 25px rgba(15,23,42,.07); border-radius: 12px; overflow: hidden; }
					.rai-dashboard__table th,
					.rai-dashboard__table td { padding: 0.85rem 1rem; border-bottom: 1px solid #e5e7eb; text-align: left; }
					.rai-dashboard__table th { background: #f9fafb; font-weight: 600; text-transform: uppercase; font-size: 0.8rem; color: #6b7280; }
					.rai-dashboard__table details { font-size: 0.9rem; }
					.rai-dashboard__table pre { white-space: pre-wrap; max-height: 200px; overflow: auto; background: #f3f4f6; padding: 0.5rem; border-radius: 8px; }
				</style>
				<?php
				$styles_printed = true;
			}

			?>
			<div class="rai-dashboard">
				<section class="rai-dashboard__section">
					<h2><?php esc_html_e( 'Processed uploads', 'resume-ai-toolkit' ); ?></h2>
					<?php echo $this->render_table_for_entries( $uploads, 'enhance' ); ?>
				</section>
				<section class="rai-dashboard__section">
					<h2><?php esc_html_e( 'New resumes created', 'resume-ai-toolkit' ); ?></h2>
					<?php echo $this->render_table_for_entries( $builders, 'builder' ); ?>
				</section>
			</div>
			<?php
			return ob_get_clean();
		}

		private function render_table_for_entries( array $entries, string $context ) {
			if ( empty( $entries ) ) {
				return '<p>' . esc_html__( 'No entries recorded yet.', 'resume-ai-toolkit' ) . '</p>';
			}

			ob_start();
			?>
			<table class="rai-dashboard__table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Date', 'resume-ai-toolkit' ); ?></th>
						<th><?php esc_html_e( 'Target / Role', 'resume-ai-toolkit' ); ?></th>
						<th><?php esc_html_e( 'Score', 'resume-ai-toolkit' ); ?></th>
						<th><?php esc_html_e( 'Snapshot', 'resume-ai-toolkit' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $entries as $entry ) : ?>
						<tr>
							<td><?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $entry['created_at'] ) ) ); ?></td>
							<td><?php echo esc_html( $entry['target_role'] ?: __( '—', 'resume-ai-toolkit' ) ); ?></td>
							<td><?php echo isset( $entry['score'] ) ? esc_html( $entry['score'] ) : '—'; ?></td>
							<td>
								<?php if ( ! empty( $entry['document'] ) ) : ?>
									<details>
										<summary><?php esc_html_e( 'View snapshot', 'resume-ai-toolkit' ); ?></summary>
										<pre><?php echo esc_html( mb_substr( $entry['document'], 0, 600 ) ); ?></pre>
									</details>
								<?php else : ?>
									—
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<?php
			return ob_get_clean();
		}
	}

	new Resume_AI_EndPoints();
	new Resume_AI_Toolkit();
}
