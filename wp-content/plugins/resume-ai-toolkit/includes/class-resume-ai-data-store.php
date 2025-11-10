<?php
/**
 * Data persistence helpers for Resume AI Toolkit.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Resume_AI_Data_Store' ) ) {

		class Resume_AI_Data_Store {

			const TABLE   = 'resume_ai_submissions';
			const VERSION = '1.1.0';

		/**
		 * Hook into WordPress lifecycle events.
		 */
		public static function init() {
			add_action( 'plugins_loaded', [ __CLASS__, 'maybe_upgrade_table' ] );
		}

		/**
		 * Handle plugin activation.
		 */
		public static function activate() {
			self::create_table();
		}

		/**
		 * Return the fully qualified table name.
		 */
		public static function table_name() {
			global $wpdb;
			return $wpdb->prefix . self::TABLE;
		}

		/**
		 * Ensure the table exists and is up to date.
		 */
		public static function maybe_upgrade_table() {
			$installed = get_option( 'resume_ai_table_version' );
			if ( self::VERSION !== $installed ) {
				self::create_table();
			}
		}

		/**
		 * Create (or upgrade) the submissions table.
		 */
		private static function create_table() {
			global $wpdb;

			$table            = self::table_name();
			$charset_collate  = $wpdb->get_charset_collate();
			$timestamp_column = "created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP";

			require_once ABSPATH . 'wp-admin/includes/upgrade.php';

			$sql = "CREATE TABLE {$table} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				user_id bigint(20) unsigned NOT NULL DEFAULT 0,
				submission_type varchar(20) NOT NULL,
				first_name varchar(190) NOT NULL DEFAULT '',
				last_name varchar(190) NOT NULL DEFAULT '',
				email varchar(190) NOT NULL DEFAULT '',
				score smallint unsigned NULL,
				file_name varchar(255) NOT NULL DEFAULT '',
				target_role text NULL,
				priority varchar(60) NOT NULL DEFAULT '',
				payload longtext NULL,
				response longtext NULL,
				document longtext NULL,
				{$timestamp_column},
				PRIMARY KEY  (id),
				KEY user_id (user_id),
				KEY submission_type (submission_type),
				KEY created_at (created_at)
			) {$charset_collate};";

			dbDelta( $sql );

			update_option( 'resume_ai_table_version', self::VERSION );
		}

		/**
		 * Whether the submissions table exists.
		 */
		public static function table_exists() {
			global $wpdb;
			$table  = self::table_name();
			$search = $wpdb->esc_like( $table );
			$sql    = $wpdb->prepare( 'SHOW TABLES LIKE %s', $search );
			return (string) $wpdb->get_var( $sql ) === $table;
		}

		/**
		 * Persist a submission row.
		 */
		public static function log_submission( array $args ) {
			global $wpdb;

			if ( ! self::table_exists() ) {
				self::maybe_upgrade_table();
				if ( ! self::table_exists() ) {
					return false;
				}
			}

			$defaults = [
				'user_id'         => 0,
				'submission_type' => 'enhance',
				'first_name'      => '',
				'last_name'       => '',
				'email'           => '',
				'score'           => null,
				'file_name'       => '',
				'target_role'     => '',
				'priority'        => '',
				'payload'         => null,
				'response'        => null,
				'document'        => null,
				'created_at'      => current_time( 'mysql', true ),
			];

			$data = wp_parse_args( $args, $defaults );

			$payload  = is_array( $data['payload'] ) ? wp_json_encode( $data['payload'] ) : $data['payload'];
			$response = is_array( $data['response'] ) ? wp_json_encode( $data['response'] ) : $data['response'];
			$document = is_array( $data['document'] ) ? wp_json_encode( $data['document'] ) : $data['document'];

			$insert_data = [
				'user_id'         => absint( $data['user_id'] ),
				'submission_type' => sanitize_key( $data['submission_type'] ),
				'first_name'      => sanitize_text_field( $data['first_name'] ),
				'last_name'       => sanitize_text_field( $data['last_name'] ),
				'email'           => sanitize_email( $data['email'] ),
				'score'           => is_numeric( $data['score'] ) ? (int) $data['score'] : null,
				'file_name'       => sanitize_file_name( $data['file_name'] ),
				'target_role'     => sanitize_textarea_field( $data['target_role'] ),
				'priority'        => sanitize_key( $data['priority'] ),
				'payload'         => $payload,
				'response'        => $response,
				'document'        => $document,
				'created_at'      => $data['created_at'],
			];

			$formats = [
				'submission_type' => '%s',
				'first_name'      => '%s',
				'last_name'       => '%s',
				'email'           => '%s',
				'score'           => '%d',
				'file_name'       => '%s',
				'target_role'     => '%s',
				'priority'        => '%s',
				'payload'         => '%s',
				'response'        => '%s',
				'document'        => '%s',
				'created_at'      => '%s',
			];

			foreach ( $insert_data as $key => $value ) {
				if ( null === $value ) {
					unset( $insert_data[ $key ], $formats[ $key ] );
				}
			}

			$result = $wpdb->insert( self::table_name(), $insert_data, array_values( $formats ) );

			return $result ? (int) $wpdb->insert_id : false;
		}

		/**
		 * Query submissions for the admin table.
		 */
		public static function query_submissions( array $args = [] ) {
			global $wpdb;

			if ( ! self::table_exists() ) {
				return [
					'items' => [],
					'total' => 0,
				];
			}

			$args = wp_parse_args(
				$args,
				[
					'type'     => 'all',
					'search'   => '',
					'paged'    => 1,
					'per_page' => 20,
				]
			);

			$type     = sanitize_key( $args['type'] );
			$search   = sanitize_text_field( $args['search'] );
			$per_page = max( 1, (int) $args['per_page'] );
			$page     = max( 1, (int) $args['paged'] );
			$offset   = ( $page - 1 ) * $per_page;

			$table = self::table_name();
			$where = 'WHERE 1=1';
			$params = [];

			if ( $type && 'all' !== $type ) {
				$where   .= ' AND submission_type = %s';
				$params[] = $type;
			}

			if ( $search ) {
				$like     = '%' . $wpdb->esc_like( $search ) . '%';
				$where   .= ' AND (first_name LIKE %s OR last_name LIKE %s OR email LIKE %s OR target_role LIKE %s)';
				$params[] = $like;
				$params[] = $like;
				$params[] = $like;
				$params[] = $like;
			}

			$count_sql  = "SELECT COUNT(*) FROM {$table} {$where}";
			$count_query = $params ? $wpdb->prepare( $count_sql, $params ) : $count_sql;
			$total       = (int) $wpdb->get_var( $count_query );

			$data_sql = "SELECT * FROM {$table} {$where} ORDER BY created_at DESC LIMIT %d OFFSET %d";
			$data_params = $params;
			$data_params[] = $per_page;
			$data_params[] = $offset;
			$data_query = $wpdb->prepare( $data_sql, $data_params );

			$items = $wpdb->get_results( $data_query, ARRAY_A );

			return [
				'items' => $items,
				'total' => $total,
			];
		}

		/**
		 * Fetch a single submission row.
		 */
			public static function get_submission( int $id ) {
			global $wpdb;

			if ( $id <= 0 || ! self::table_exists() ) {
				return null;
			}

			$table = self::table_name();
			$sql   = $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d LIMIT 1", $id );

			return $wpdb->get_row( $sql, ARRAY_A );
		}

		/**
		 * Fetch submissions for a specific user email.
		 */
		public static function get_user_submissions( string $email, string $type = 'all', int $limit = 20, int $user_id = 0 ) {
			global $wpdb;

			if ( ! self::table_exists() ) {
				return [];
			}

			$table      = self::table_name();
			$where      = 'WHERE 1=1';
			$params     = [];
			$email      = sanitize_email( $email );
			$has_user   = $user_id > 0;
			$has_email  = ! empty( $email );

			if ( ! $has_user && ! $has_email ) {
				return [];
			}

			if ( $has_user && $has_email ) {
				$where   .= ' AND (user_id = %d OR email = %s)';
				$params[] = $user_id;
				$params[] = $email;
			} elseif ( $has_user ) {
				$where   .= ' AND user_id = %d';
				$params[] = $user_id;
			} elseif ( $has_email ) {
				$where   .= ' AND email = %s';
				$params[] = $email;
			}

			if ( 'all' !== $type ) {
				$where    .= ' AND submission_type = %s';
				$params[] = sanitize_key( $type );
			}

			$sql = "SELECT * FROM {$table} {$where} ORDER BY created_at DESC LIMIT %d";
			$params[] = max( 1, $limit );

			$query = $wpdb->prepare( $sql, $params );

			return $wpdb->get_results( $query, ARRAY_A );
		}
	}
}
