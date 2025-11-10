<?php
/**
 * Backend UI for managing user subscriptions.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

if ( ! class_exists( 'Resume_AI_User_Subscriptions' ) ) {

	class Resume_AI_User_Subscriptions {

		public static function init() {
			add_action( 'init', [ __CLASS__, 'register_post_type' ] );
			add_action( 'admin_menu', [ __CLASS__, 'register_menu_items' ] );
			add_action( 'admin_post_rai_toggle_subscription', [ __CLASS__, 'handle_toggle' ] );
		}

		public static function register_post_type() {
			register_post_type(
				'rai_subscription',
				[
					'labels' => [
						'name'          => __( 'Subscriptions', 'resume-ai-toolkit' ),
						'singular_name' => __( 'Subscription', 'resume-ai-toolkit' ),
					],
					'public'       => false,
					'show_ui'      => false,
					'show_in_menu' => false,
					'supports'     => [ 'title' ],
				]
			);
		}

		public static function register_menu_items() {
			add_users_page(
				__( 'Subscriptions', 'resume-ai-toolkit' ),
				__( 'Subscriptions', 'resume-ai-toolkit' ),
				'list_users',
				'rai-user-subscriptions',
				[ __CLASS__, 'render_page' ]
			);

			add_action( 'show_user_profile', [ __CLASS__, 'render_user_section' ] );
			add_action( 'edit_user_profile', [ __CLASS__, 'render_user_section' ] );
		}

		public static function render_page() {
			if ( ! class_exists( 'WP_List_Table' ) ) {
				require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
			}

			$table = new Resume_AI_Subscription_Table();
			$table->prepare_items();

			?>
			<div class="wrap">
				<h1><?php esc_html_e( 'User Subscriptions', 'resume-ai-toolkit' ); ?></h1>
				<p><?php esc_html_e( 'Reference Stripe entitlements for every user and deactivate access when needed.', 'resume-ai-toolkit' ); ?></p>
				<form method="get">
					<input type="hidden" name="page" value="rai-user-subscriptions" />
					<?php
					$table->search_box( __( 'Search by email or subscription ID', 'resume-ai-toolkit' ), 'rai-user-search' );
					$table->display();
					?>
				</form>
			</div>
			<?php
		}

		public static function handle_toggle() {
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( esc_html__( 'You cannot modify this subscription.', 'resume-ai-toolkit' ) );
			}

			$user_id = isset( $_GET['user'] ) ? absint( $_GET['user'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			check_admin_referer( 'rai_toggle_subscription_' . $user_id );

			if ( ! $user_id ) {
				wp_safe_redirect( admin_url( 'users.php?page=rai-user-subscriptions' ) );
				exit;
			}

			delete_user_meta( $user_id, 'rai_stripe_customer_id' );
			delete_user_meta( $user_id, 'rai_stripe_subscription_id' );
			delete_user_meta( $user_id, 'rai_stripe_status' );
			delete_user_meta( $user_id, 'rai_current_period_end' );

			wp_safe_redirect( admin_url( 'users.php?page=rai-user-subscriptions&updated=1' ) );
			exit;
		}

		public static function render_user_section( $user ) {
			$entries = class_exists( 'Resume_AI_Data_Store' ) ? Resume_AI_Data_Store::get_user_submissions( $user->user_email, 'all', 20, $user->ID ) : [];

			echo '<h2>' . esc_html__( 'Resume Activity', 'resume-ai-toolkit' ) . '</h2>';

			if ( empty( $entries ) ) {
				echo '<p>' . esc_html__( 'No processed resumes recorded yet.', 'resume-ai-toolkit' ) . '</p>';
				return;
			}

			echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Type', 'resume-ai-toolkit' ) . '</th><th>' . esc_html__( 'Target / Role', 'resume-ai-toolkit' ) . '</th><th>' . esc_html__( 'Score', 'resume-ai-toolkit' ) . '</th><th>' . esc_html__( 'Date', 'resume-ai-toolkit' ) . '</th></tr></thead><tbody>';
			foreach ( $entries as $entry ) {
				echo '<tr>';
				echo '<td>' . esc_html( ucfirst( $entry['submission_type'] ) ) . '</td>';
				echo '<td>' . esc_html( $entry['target_role'] ?: '—' ) . '</td>';
				echo '<td>' . ( isset( $entry['score'] ) ? esc_html( $entry['score'] ) : '—' ) . '</td>';
				echo '<td>' . esc_html( date_i18n( get_option( 'date_format' ), strtotime( $entry['created_at'] ) ) ) . '</td>';
				echo '</tr>';
			}
			echo '</tbody></table>';
		}
	}
}

if ( ! class_exists( 'Resume_AI_Subscription_Table' ) ) {

	class Resume_AI_Subscription_Table extends WP_List_Table {

		public function __construct() {
			parent::__construct(
				[
					'singular' => 'rai_subscription',
					'plural'   => 'rai_subscriptions',
					'ajax'     => false,
				]
			);
		}

		public function get_columns() {
			return [
				'user'        => __( 'User', 'resume-ai-toolkit' ),
				'email'       => __( 'Email', 'resume-ai-toolkit' ),
				'subscription'=> __( 'Subscription ID', 'resume-ai-toolkit' ),
				'status'      => __( 'Status', 'resume-ai-toolkit' ),
				'expires'     => __( 'Period End', 'resume-ai-toolkit' ),
				'actions'     => __( 'Actions', 'resume-ai-toolkit' ),
			];
		}

		public function prepare_items() {
			$per_page     = 20;
			$current_page = $this->get_pagenum();
			$search       = isset( $_REQUEST['s'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

			$args = [
				'post_type'      => 'rai_subscription',
				'posts_per_page' => $per_page,
				'paged'          => $current_page,
				'orderby'        => 'date',
				'order'          => 'DESC',
			];

			if ( $search ) {
				$args['s'] = $search;
			}

			$query = new WP_Query( $args );
			$this->items = array_map( [ $this, 'format_item' ], $query->posts );

			$this->set_pagination_args(
				[
					'total_items' => $query->found_posts,
					'per_page'    => $per_page,
				]
			);
		}

		private function format_item( WP_Post $post ) {
			$user_id = (int) get_post_meta( $post->ID, '_rai_subscription_user_id', true );
			$user    = $user_id ? get_user_by( 'id', $user_id ) : null;

			return [
				'user'         => $user,
				'email'        => get_post_meta( $post->ID, '_rai_subscription_email', true ),
				'subscription' => $post->post_title,
				'status'       => get_post_meta( $post->ID, '_rai_subscription_status', true ),
				'expires'      => (int) get_post_meta( $post->ID, '_rai_subscription_period_end', true ),
				'customer'     => get_post_meta( $post->ID, '_rai_subscription_customer', true ),
			];
		}

		public function column_user( $item ) {
			if ( ! $item['user'] ) {
				return '—';
			}

			$url = get_edit_user_link( $item['user']->ID );
			return sprintf( '<a href="%s">%s</a>', esc_url( $url ), esc_html( $item['user']->display_name ?: $item['user']->user_login ) );
		}

		public function column_email( $item ) {
			return sprintf( '<a href="mailto:%1$s">%1$s</a>', esc_html( $item['email'] ) );
		}

		public function column_subscription( $item ) {
			return $item['subscription'] ? esc_html( $item['subscription'] ) : '—';
		}

		public function column_status( $item ) {
			return $item['status'] ? esc_html( ucfirst( $item['status'] ) ) : '—';
		}

		public function column_expires( $item ) {
			if ( ! $item['expires'] ) {
				return '—';
			}
			return esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $item['expires'] ) );
		}

		public function column_actions( $item ) {
			if ( empty( $item['subscription'] ) || empty( $item['user']->ID ) ) {
				return '—';
			}

			$url = wp_nonce_url(
				add_query_arg(
					[
						'action' => 'rai_toggle_subscription',
						'user'   => $item['user']->ID,
					],
					admin_url( 'admin-post.php' )
				),
				'rai_toggle_subscription_' . $item['user']->ID
			);

			return sprintf(
				'<a class="button button-small" href="%s">%s</a>',
				esc_url( $url ),
				esc_html__( 'Deactivate', 'resume-ai-toolkit' )
			);
		}
	}
}
