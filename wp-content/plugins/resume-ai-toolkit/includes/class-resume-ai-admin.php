<?php
/**
 * Admin UI for displaying resume submissions.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

if ( ! class_exists( 'Resume_AI_Admin_Page' ) ) {

	class Resume_AI_Admin_Page {

		const MENU_SLUG        = 'resume-ai-activity';
		const OPTION_API_KEY   = 'resume_ai_api_key';
		const OPTION_LIVE_MODE = 'resume_ai_live_mode';

		/**
		 * Register hooks for the admin UI.
		 */
		public static function init() {
			if ( ! is_admin() ) {
				return;
			}

			add_action( 'admin_menu', [ __CLASS__, 'register_menu' ] );
			add_action( 'admin_post_resume_ai_download', [ __CLASS__, 'handle_download' ] );
			add_action( 'admin_post_resume_ai_save_settings', [ __CLASS__, 'handle_settings_save' ] );
		}

		/**
		 * Add the dashboard menu entry.
		 */
		public static function register_menu() {
			add_menu_page(
				__( 'Resume Activity', 'resume-ai-toolkit' ),
				__( 'Resume Activity', 'resume-ai-toolkit' ),
				'manage_options',
				self::MENU_SLUG,
				[ __CLASS__, 'render_page' ],
				'dashicons-media-document',
				58
			);
		}

		/**
		 * Render the list table page.
		 */
		public static function render_page() {
			$active_type = isset( $_GET['resume_type'] ) ? sanitize_key( wp_unslash( $_GET['resume_type'] ) ) : 'all'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( ! in_array( $active_type, [ 'all', 'enhance', 'builder', 'settings' ], true ) ) {
				$active_type = 'all';
			}

			$table = new Resume_AI_Submissions_Table( $active_type );
			if ( 'settings' !== $active_type ) {
				$table->prepare_items();
			}

			?>
			<div class="wrap">
				<style>
					.resume-ai-badge {
						display: inline-block;
						padding: 0 10px;
						line-height: 1.8;
						border-radius: 999px;
						font-size: 12px;
						font-weight: 600;
						text-transform: uppercase;
						background: #eef2ff;
						color: #1e1e1e;
					}
					.resume-ai-badge.type-builder {
						background: #ecfdf3;
					}
					.resume-ai-badge.type-enhance {
						background: #fef3c7;
					}
				</style>
				<h1><?php esc_html_e( 'Resume Activity', 'resume-ai-toolkit' ); ?></h1>
				<p><?php esc_html_e( 'Review every enhance upload and new resume builder run in a single place.', 'resume-ai-toolkit' ); ?></p>
				<?php self::render_tabs( $active_type ); ?>
				<?php if ( 'settings' !== $active_type && ! Resume_AI_Data_Store::table_exists() ) : ?>
					<div class="notice notice-warning inline">
						<p><?php esc_html_e( 'The submissions table has not been created yet. Reactivate the plugin or reload this page to retry.', 'resume-ai-toolkit' ); ?></p>
					</div>
				<?php endif; ?>
				<?php
				if ( 'settings' === $active_type ) {
					self::render_settings_panel();
				} else {
					?>
					<form method="get">
						<input type="hidden" name="page" value="<?php echo esc_attr( self::MENU_SLUG ); ?>" />
						<input type="hidden" name="resume_type" value="<?php echo esc_attr( $active_type ); ?>" />
						<?php
						$table->search_box( __( 'Search submissions', 'resume-ai-toolkit' ), 'resume-ai-search' );
						$table->display();
						?>
					</form>
					<?php
				}
				?>
			</div>
			<?php
		}

		/**
		 * Render the tab navigation for flow filters.
		 */
		private static function render_tabs( string $active_type ) {
			$tabs = [
				'all'       => __( 'All entries', 'resume-ai-toolkit' ),
				'enhance'   => __( 'Enhance uploads', 'resume-ai-toolkit' ),
				'builder'   => __( 'New resume builder', 'resume-ai-toolkit' ),
				'settings'  => __( 'Settings', 'resume-ai-toolkit' ),
			];

			echo '<h2 class="nav-tab-wrapper">';
			foreach ( $tabs as $type => $label ) {
				$url   = add_query_arg(
					[
						'page'        => self::MENU_SLUG,
						'resume_type' => $type,
					],
					admin_url( 'admin.php' )
				);
				$class = 'nav-tab' . ( $active_type === $type ? ' nav-tab-active' : '' );
				printf(
					'<a href="%s" class="%s">%s</a>',
					esc_url( $url ),
					esc_attr( $class ),
					esc_html( $label )
				);
			}
				echo '</h2>';
		}

		/**
		 * Settings panel markup.
		 */
		private static function render_settings_panel() {
			$stored_key   = get_option( self::OPTION_API_KEY, '' );
			$live_mode    = (bool) get_option( self::OPTION_LIVE_MODE, 0 );
			?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="resume-ai-settings">
				<?php wp_nonce_field( 'resume_ai_save_settings' ); ?>
				<input type="hidden" name="action" value="resume_ai_save_settings" />
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">
							<label for="resume_ai_api_key"><?php esc_html_e( 'OpenAI API key', 'resume-ai-toolkit' ); ?></label>
						</th>
						<td>
							<input type="text" id="resume_ai_api_key" name="resume_ai_api_key" class="regular-text" value="<?php echo esc_attr( $stored_key ); ?>" autocomplete="off" />
							<p class="description">
								<?php esc_html_e( 'Leave blank to fall back to the constant defined in wp-config.php.', 'resume-ai-toolkit' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<?php esc_html_e( 'Live processing', 'resume-ai-toolkit' ); ?>
						</th>
						<td>
							<label for="resume_ai_live_mode">
								<input type="checkbox" id="resume_ai_live_mode" name="resume_ai_live_mode" value="1" <?php checked( $live_mode ); ?> />
								<?php esc_html_e( 'Call OpenAI instead of returning dry-run responses.', 'resume-ai-toolkit' ); ?>
							</label>
							<p class="description">
								<?php esc_html_e( 'Disable this when you need to demo or test without consuming tokens.', 'resume-ai-toolkit' ); ?>
							</p>
						</td>
					</tr>
				</table>
				<?php submit_button( __( 'Save settings', 'resume-ai-toolkit' ) ); ?>
			</form>
			<?php
		}

		/**
		 * Handle resume download requests.
		 */
		public static function handle_download() {
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( esc_html__( 'Sorry, you are not allowed to export resumes.', 'resume-ai-toolkit' ) );
			}

			$submission_id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( ! $submission_id ) {
				wp_die( esc_html__( 'Invalid submission.', 'resume-ai-toolkit' ) );
			}

			check_admin_referer( 'resume_ai_download_' . $submission_id );

			$record = Resume_AI_Data_Store::get_submission( $submission_id );
			if ( ! $record ) {
				wp_die( esc_html__( 'Submission not found.', 'resume-ai-toolkit' ) );
			}

			$document = isset( $record['document'] ) ? trim( (string) $record['document'] ) : '';
			if ( '' === $document && ! empty( $record['response'] ) ) {
				$response = json_decode( $record['response'], true );
				if ( is_array( $response ) && ! empty( $response['resume_document'] ) ) {
					$document = $response['resume_document'];
				}
			}

			if ( '' === $document ) {
				wp_die( esc_html__( 'This entry does not contain downloadable content yet.', 'resume-ai-toolkit' ) );
			}

			$suggested_name = ! empty( $record['file_name'] ) ? $record['file_name'] : '';
			if ( ! $suggested_name && ! empty( $record['first_name'] ) ) {
				$suggested_name = sanitize_file_name( trim( $record['first_name'] . '-' . ( $record['last_name'] ?? '' ) ) );
			}

			$filename = $suggested_name ? preg_replace( '/\.[^.]+$/', '', $suggested_name ) : 'resume-' . $submission_id;
			$filename = sanitize_file_name( $filename . '.txt' );

			nocache_headers();
			header( 'Content-Type: text/plain; charset=utf-8' );
			header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
			echo $document; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- sending raw file contents.
			exit;
		}

		/**
		 * Persist configuration settings.
		 */
		public static function handle_settings_save() {
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( esc_html__( 'Sorry, you are not allowed to update these settings.', 'resume-ai-toolkit' ) );
			}

			check_admin_referer( 'resume_ai_save_settings' );

			$api_key = isset( $_POST['resume_ai_api_key'] ) ? sanitize_text_field( wp_unslash( $_POST['resume_ai_api_key'] ) ) : '';
			update_option( self::OPTION_API_KEY, $api_key );

			$live_mode = isset( $_POST['resume_ai_live_mode'] ) ? 1 : 0;
			update_option( self::OPTION_LIVE_MODE, $live_mode );

			$redirect = add_query_arg(
				[
					'page'        => self::MENU_SLUG,
					'resume_type' => 'settings',
					'updated'     => 'true',
				],
				admin_url( 'admin.php' )
			);

			wp_safe_redirect( $redirect );
			exit;
		}
	}
}

if ( ! class_exists( 'Resume_AI_Submissions_Table' ) ) {
	class Resume_AI_Submissions_Table extends WP_List_Table {

		/**
		 * Current flow filter.
		 *
		 * @var string
		 */
		private $type;

		/**
		 * Constructor.
		 */
		public function __construct( string $type = 'all' ) {
			$this->type = $type ?: 'all';

			parent::__construct(
				[
					'singular' => 'resume_submission',
					'plural'   => 'resume_submissions',
					'ajax'     => false,
				]
			);
		}

		/**
		 * Define table columns.
		 */
			public function get_columns() {
				return [
					'type'     => __( 'Flow', 'resume-ai-toolkit' ),
					'name'     => __( 'Name', 'resume-ai-toolkit' ),
					'email'    => __( 'Email', 'resume-ai-toolkit' ),
					'score'    => __( 'Score', 'resume-ai-toolkit' ),
					'source'   => __( 'Source', 'resume-ai-toolkit' ),
					'target'   => __( 'Target / Role', 'resume-ai-toolkit' ),
					'created'  => __( 'Submitted', 'resume-ai-toolkit' ),
					'document' => __( 'Snapshot', 'resume-ai-toolkit' ),
					'actions'  => __( 'Actions', 'resume-ai-toolkit' ),
				];
			}

		/**
		 * Prepare rows & pagination.
		 */
		public function prepare_items() {
			$per_page     = 20;
			$current_page = $this->get_pagenum();
			$search       = isset( $_REQUEST['s'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

			$query = Resume_AI_Data_Store::query_submissions(
				[
					'type'     => $this->type,
					'search'   => $search,
					'paged'    => $current_page,
					'per_page' => $per_page,
				]
			);

			$items = $query['items'] ?? [];
			$this->items = array_map( [ $this, 'format_item' ], $items );

			$this->_column_headers = [ $this->get_columns(), [], [] ];
			$this->set_pagination_args(
				[
					'total_items' => $query['total'] ?? 0,
					'per_page'    => $per_page,
				]
			);
		}

		/**
		 * Default column output fallback.
		 */
		public function column_default( $item, $column_name ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			switch ( $column_name ) {
				case 'type':
					return $this->format_type_badge( $item['submission_type'] ?? '' );
				case 'name':
					return $item['display_name'] ?? '—';
				case 'email':
					return $item['email'] ? '<a href="mailto:' . esc_attr( $item['email'] ) . '">' . esc_html( $item['email'] ) . '</a>' : '—';
				case 'score':
					return isset( $item['score'] ) ? absint( $item['score'] ) : '—';
				case 'source':
					return $this->format_source( $item );
				case 'target':
					return $item['target_role'] ? esc_html( $item['target_role'] ) : '—';
				case 'created':
					return esc_html( $item['human_created'] ?? '—' );
				case 'document':
					return $this->format_document_column( $item['document'] ?? '' );
				case 'actions':
					return $this->format_actions_column( $item );
				default:
					return isset( $item[ $column_name ] ) ? esc_html( (string) $item[ $column_name ] ) : '';
			}
		}

		/**
		 * Display message when no data exists.
		 */
		public function no_items() {
			echo esc_html__( 'No resume submissions recorded yet.', 'resume-ai-toolkit' );
		}

		/**
		 * Prepare friendly row data.
		 */
		private function format_item( array $item ) {
			$item['display_name'] = $this->build_display_name( $item );
			$item['human_created'] = $this->format_datetime( $item['created_at'] ?? '' );
			return $item;
		}

		private function build_display_name( array $item ) {
			$name = trim( sprintf( '%s %s', $item['first_name'] ?? '', $item['last_name'] ?? '' ) );
			if ( ! $name && ! empty( $item['email'] ) ) {
				return esc_html( $item['email'] );
			}
			return $name ?: '—';
		}

		private function format_type_badge( string $type ) {
			$labels = [
				'enhance' => __( 'Enhance upload', 'resume-ai-toolkit' ),
				'builder' => __( 'New resume', 'resume-ai-toolkit' ),
			];

			$label = $labels[ $type ] ?? __( 'Unknown', 'resume-ai-toolkit' );
			return sprintf( '<span class="resume-ai-badge type-%s">%s</span>', esc_attr( $type ?: 'unknown' ), esc_html( $label ) );
		}

		private function format_source( array $item ) {
			if ( ! empty( $item['file_name'] ) ) {
				$priority = '';
				if ( ! empty( $item['priority'] ) ) {
					$priority = sprintf( ' · %s', esc_html( ucfirst( $item['priority'] ) ) );
				}

				return sprintf( '<code>%s</code>%s', esc_html( $item['file_name'] ), $priority ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- composed of escaped fragments.
			}

			return esc_html__( 'Builder submission', 'resume-ai-toolkit' );
		}

		private function format_datetime( string $datetime ) {
			if ( ! $datetime ) {
				return '';
			}

			$format = sprintf( '%s %s', get_option( 'date_format' ), get_option( 'time_format' ) );
			return get_date_from_gmt( $datetime, $format );
		}

		private function format_document_column( string $document ) {
			if ( ! $document ) {
				return '—';
			}

			$limit   = 800;
			$snippet = function_exists( 'mb_substr' ) ? mb_substr( $document, 0, $limit ) : substr( $document, 0, $limit );
			if ( strlen( $document ) > $limit ) {
				$snippet .= '…';
			}

			return sprintf(
				'<details><summary>%s</summary><pre style="white-space:pre-wrap;max-height:260px;overflow:auto;">%s</pre></details>',
				esc_html__( 'View snapshot', 'resume-ai-toolkit' ),
				esc_html( $snippet )
			);
		}

		private function format_actions_column( array $item ) {
			$id = isset( $item['id'] ) ? (int) $item['id'] : 0;
			if ( $id <= 0 ) {
				return '—';
			}

			$url = wp_nonce_url(
				add_query_arg(
					[
						'action' => 'resume_ai_download',
						'id'     => $id,
					],
					admin_url( 'admin-post.php' )
				),
				'resume_ai_download_' . $id
			);

			return sprintf(
				'<a class="button button-small" href="%s">%s</a>',
				esc_url( $url ),
				esc_html__( 'Download', 'resume-ai-toolkit' )
			);
		}
	}
}
