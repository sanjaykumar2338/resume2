<?php
/**
 * Stripe billing utilities and REST endpoints.
 */

use Stripe\Stripe;
use Stripe\Checkout\Session as CheckoutSession;
use Stripe\BillingPortal\Session as BillingPortalSession;
use Stripe\Webhook as StripeWebhook;
use Stripe\Subscription as StripeSubscription;
use Stripe\Customer as StripeCustomer;
use Stripe\Product as StripeProduct;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Resume_AI_Billing' ) ) {

	class Resume_AI_Billing {

		/**
		 * Cached resolved price IDs.
		 *
		 * @var array
		 */
		private static $resolved_prices = [];

		/**
		 * Bootstrap hooks.
		 */
		public static function init() {
			add_action( 'rest_api_init', [ __CLASS__, 'register_routes' ] );
			add_action( 'template_redirect', [ __CLASS__, 'maybe_handle_thank_you' ] );
		}

		/**
		 * Register REST endpoints for checkout, portal, and webhooks.
		 */
		public static function register_routes() {
			register_rest_route(
				'resume-ai/v1',
				'/checkout',
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'permission_callback' => '__return_true',
					'callback'            => [ __CLASS__, 'create_checkout_session' ],
				]
			);

			register_rest_route(
				'resume-ai/v1',
				'/portal',
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'permission_callback' => function () {
						return is_user_logged_in();
					},
					'callback' => [ __CLASS__, 'create_portal_session' ],
				]
			);

			register_rest_route(
				'resume-ai/v1',
				'/stripe-webhook',
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'permission_callback' => '__return_true',
					'callback'            => [ __CLASS__, 'handle_webhook' ],
				]
			);
		}

		/**
		 * Create a checkout session and return the Stripe URL.
		 */
		public static function create_checkout_session( WP_REST_Request $request ) {
			$email = sanitize_email( $request->get_param( 'email' ) );
			if ( ! $email ) {
				return new WP_Error( 'rai_checkout_email', __( 'Please provide a valid email address.', 'resume-ai-toolkit' ), [ 'status' => 400 ] );
			}

			$secret_key = self::get_config( 'RAI_STRIPE_SECRET' );
			$plan       = sanitize_key( $request->get_param( 'plan' ) ?: 'primary' );
			$success    = self::maybe_format_success_url( self::get_config( 'RAI_SUCCESS_URL', home_url( '/thank-you/' ) ) );
			$cancel     = self::get_config( 'RAI_CANCEL_URL', home_url( '/pricing/' ) );

			if ( ! $secret_key ) {
				return new WP_Error( 'rai_checkout_config', __( 'Stripe checkout is not configured.', 'resume-ai-toolkit' ), [ 'status' => 500 ] );
			}

			self::boot_stripe( $secret_key );

			$price_id = self::resolve_price_id( $plan );
			if ( ! $price_id ) {
				return new WP_Error( 'rai_checkout_config', __( 'Stripe checkout is not configured.', 'resume-ai-toolkit' ), [ 'status' => 500 ] );
			}

			try {
				$session = CheckoutSession::create(
					[
						'mode'                  => 'subscription',
						'customer_email'        => $email,
						'allow_promotion_codes' => true,
						'success_url'           => $success,
						'cancel_url'            => $cancel,
						'line_items'            => [
							[
								'price'    => $price_id,
								'quantity' => 1,
							],
						],
						'subscription_data'     => [
							'trial_period_days' => 7,
						],
					]
				);
			} catch ( Exception $exception ) {
				return new WP_Error( 'rai_checkout_error', $exception->getMessage(), [ 'status' => 500 ] );
			}

			return new WP_REST_Response(
				[
					'id'  => $session->id,
					'url' => $session->url,
				]
			);
		}

		/**
		 * Resolve a usable price ID for Stripe checkout.
		 */
		private static function resolve_price_id( string $plan = 'primary' ) {
			if ( isset( self::$resolved_prices[ $plan ] ) ) {
				return self::$resolved_prices[ $plan ];
			}

			$map = [
				'primary'   => self::get_config( 'RAI_PRICE_QUARTERLY' ),
				'quarterly' => self::get_config( 'RAI_PRICE_QUARTERLY' ),
				'secondary' => self::get_config( 'RAI_PRICE_SECONDARY' ),
				'trial'     => self::get_config( 'RAI_PRICE_SECONDARY' ),
			];

			$candidate = $map[ $plan ] ?? $map['primary'];
			if ( ! $candidate ) {
				return '';
			}

			if ( 0 === strpos( $candidate, 'price_' ) ) {
				self::$resolved_prices[ $plan ] = $candidate;
				return $candidate;
			}

			if ( 0 === strpos( $candidate, 'prod_' ) ) {
				try {
					$product = StripeProduct::retrieve( $candidate );
					if ( isset( $product->default_price ) ) {
						if ( is_string( $product->default_price ) ) {
							$candidate = $product->default_price;
						} elseif ( is_object( $product->default_price ) && ! empty( $product->default_price->id ) ) {
							$candidate = $product->default_price->id;
						}
					}
				} catch ( Exception $exception ) {
					error_log( sprintf( 'Resume AI Billing: unable to resolve product price (%s)', $exception->getMessage() ) );
					$candidate = '';
				}
			}

			self::$resolved_prices[ $plan ] = $candidate;
			return $candidate;
		}

		/**
		 * Create a Billing Portal session for the current user.
		 */
		public static function create_portal_session() {
			$user_id      = get_current_user_id();
			$user         = $user_id ? get_user_by( 'id', $user_id ) : null;
			$customer_id  = $user_id ? get_user_meta( $user_id, 'rai_stripe_customer_id', true ) : '';

			if ( ! $customer_id && $user ) {
				$subscription_id = get_user_meta( $user_id, 'rai_stripe_subscription_id', true );
				if ( $subscription_id ) {
					try {
						$subscription = StripeSubscription::retrieve( $subscription_id );
						if ( isset( $subscription->customer ) ) {
							$customer_id = $subscription->customer;
							update_user_meta( $user_id, 'rai_stripe_customer_id', $customer_id );
						}
					} catch ( Exception $exception ) {
						error_log( sprintf( 'Resume AI Billing: portal subscription lookup failed (%s)', $exception->getMessage() ) );
					}
				}
			}

			if ( ! $customer_id && $user && $user->user_email ) {
				try {
					$list = StripeCustomer::all(
						[
							'email' => $user->user_email,
							'limit' => 1,
						]
					);

					if ( ! empty( $list->data[0]->id ) ) {
						$customer_id = $list->data[0]->id;
						update_user_meta( $user_id, 'rai_stripe_customer_id', $customer_id );
					}
				} catch ( Exception $exception ) {
					error_log( sprintf( 'Resume AI Billing: portal customer lookup failed (%s)', $exception->getMessage() ) );
				}
			}

			if ( ! $customer_id ) {
				return new WP_Error( 'rai_portal_missing_customer', __( 'No Stripe customer found for this account.', 'resume-ai-toolkit' ), [ 'status' => 400 ] );
			}

			$secret_key = self::get_config( 'RAI_STRIPE_SECRET' );
			if ( ! $secret_key ) {
				return new WP_Error( 'rai_portal_config', __( 'Stripe is not configured.', 'resume-ai-toolkit' ), [ 'status' => 500 ] );
			}

			self::boot_stripe( $secret_key );

			try {
				$session = BillingPortalSession::create(
					[
						'customer'   => $customer_id,
						'return_url' => home_url( '/account/' ),
					]
				);
			} catch ( Exception $exception ) {
				return new WP_Error( 'rai_portal_error', $exception->getMessage(), [ 'status' => 500 ] );
			}

			return new WP_REST_Response(
				[
					'url' => $session->url,
				]
			);
		}

		/**
		 * Handle Stripe webhook events and sync user entitlements.
		 */
		public static function handle_webhook( WP_REST_Request $request ) {
			$secret_key     = self::get_config( 'RAI_STRIPE_SECRET' );
			$webhook_secret = self::get_config( 'RAI_WEBHOOK_SECRET' );

			if ( ! $secret_key || ! $webhook_secret ) {
				return new WP_Error( 'rai_webhook_config', __( 'Webhook is not configured.', 'resume-ai-toolkit' ), [ 'status' => 500 ] );
			}

			$payload = $request->get_body();
			$sig     = $request->get_header( 'stripe-signature' );

			self::boot_stripe( $secret_key );

			try {
				$event = StripeWebhook::constructEvent( $payload, $sig, $webhook_secret );
			} catch ( Exception $exception ) {
				return new WP_Error( 'rai_webhook_signature', $exception->getMessage(), [ 'status' => 400 ] );
			}

			self::process_event( $event );

			return new WP_REST_Response( [ 'received' => true ] );
		}

		/**
		 * Auto-login the customer on the thank you page.
		 */
		public static function maybe_handle_thank_you() {
			if ( is_admin() || ! is_page() ) {
				return;
			}

			if ( ! is_page( 'thank-you' ) ) {
				return;
			}

			$session_id = isset( $_GET['session_id'] ) ? sanitize_text_field( wp_unslash( $_GET['session_id'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( ! $session_id ) {
				return;
			}

			$secret_key = self::get_config( 'RAI_STRIPE_SECRET' );
			if ( ! $secret_key ) {
				return;
			}

			self::boot_stripe( $secret_key );

			try {
				$session = CheckoutSession::retrieve(
					$session_id,
					[
						'expand' => [ 'customer_details' ],
					]
				);
			} catch ( Exception $exception ) {
				return;
			}

			$email = $session->customer_details->email ?? '';
			if ( ! $email ) {
				return;
			}

			$user = get_user_by( 'email', $email );
			if ( ! $user ) {
				$user_id = self::create_user_if_needed( $email );
				$user    = $user_id ? get_user_by( 'id', $user_id ) : false;
			}

			$subscription_id = $session->subscription ?? '';
			if ( $subscription_id ) {
				try {
					$subscription = StripeSubscription::retrieve( $subscription_id );
					self::sync_entitlement( $email, $subscription );
					self::store_subscription_post( $email, $subscription );
				} catch ( Exception $exception ) {
					error_log( sprintf( 'Resume AI Billing: subscription sync failed (%s)', $exception->getMessage() ) );
				}
			}

			if ( $user ) {
				wp_set_current_user( $user->ID );
				wp_set_auth_cookie( $user->ID );
			}
		}

		/**
		 * Handle event and sync user meta.
		 */
		private static function process_event( $event ) {
			$type    = $event->type;
			$object  = $event->data->object;
			$email   = '';
			$subscription = null;

			try {
				switch ( $type ) {
					case 'checkout.session.completed':
						$session     = $object;
						$subscription = StripeSubscription::retrieve( $session->subscription );
						$email        = $session->customer_details->email ?? '';
						if ( ! $email ) {
							$email = self::fetch_customer_email( $subscription->customer );
						}
						break;
					case 'invoice.paid':
						$invoice      = $object;
						$subscription = StripeSubscription::retrieve( $invoice->subscription );
						$email        = self::fetch_customer_email( $subscription->customer );
						break;
					case 'invoice.payment_failed':
					case 'customer.subscription.updated':
					case 'customer.subscription.deleted':
						$subscription = $object;
						$email        = self::fetch_customer_email( $subscription->customer );
						break;
					default:
						// Ignore unrelated events.
						return;
				}
			} catch ( Exception $exception ) {
				error_log( sprintf( 'Resume AI Billing: %s', $exception->getMessage() ) );
				return;
			}

			if ( $subscription && $email ) {
				self::sync_entitlement( $email, $subscription );
				self::store_subscription_post( $email, $subscription );
			}
		}

		/**
		 * Sync entitlement data to user meta.
		 */
		private static function sync_entitlement( string $email, $subscription ) {
			$user = get_user_by( 'email', $email );
			if ( ! $user ) {
				$user_id = self::create_user_if_needed( $email );
				$user    = $user_id ? get_user_by( 'id', $user_id ) : false;
			}

			if ( ! $user ) {
				return;
			}

			update_user_meta( $user->ID, 'rai_stripe_customer_id', $subscription->customer );
			update_user_meta( $user->ID, 'rai_stripe_subscription_id', $subscription->id );
			update_user_meta( $user->ID, 'rai_stripe_status', $subscription->status );
			$period_end = self::extract_period_end( $subscription );
			update_user_meta( $user->ID, 'rai_current_period_end', $period_end );
			$plan_slug = self::detect_plan_slug( $subscription );
			if ( $plan_slug ) {
				update_user_meta( $user->ID, 'rai_stripe_plan_slug', $plan_slug );
			}
		}

		/**
		 * Persist subscription data in a custom post for history.
		 */
		private static function store_subscription_post( string $email, $subscription ) {
			if ( empty( $subscription->id ) ) {
				return;
			}

			$user = get_user_by( 'email', $email );

			$post_args = [
				'post_type'   => 'rai_subscription',
				'post_status' => 'publish',
				'post_title'  => $subscription->id,
			];

			$existing = get_posts(
				[
					'post_type'  => 'rai_subscription',
					'title'      => $subscription->id,
					'numberposts'=> 1,
					'fields'     => 'ids',
				]
			);

			if ( $existing ) {
				$post_args['ID'] = $existing[0];
				$post_id = wp_update_post( $post_args );
			} else {
				$post_id = wp_insert_post( $post_args );
			}

			if ( is_wp_error( $post_id ) ) {
				return;
			}

			update_post_meta( $post_id, '_rai_subscription_status', $subscription->status );
			update_post_meta( $post_id, '_rai_subscription_period_end', self::extract_period_end( $subscription ) );
			update_post_meta( $post_id, '_rai_subscription_customer', $subscription->customer );
			update_post_meta( $post_id, '_rai_subscription_email', $email );
			$plan_slug = self::detect_plan_slug( $subscription );
			if ( $plan_slug ) {
				update_post_meta( $post_id, '_rai_subscription_plan', $plan_slug );
			}
			if ( $user ) {
				update_post_meta( $post_id, '_rai_subscription_user_id', $user->ID );
			}
		}

		/**
		 * Ensure a WP user exists for an email address.
		 */
		private static function create_user_if_needed( string $email ) {
			if ( ! is_email( $email ) ) {
				return 0;
			}

			$username = sanitize_user( current( explode( '@', $email ) ), true );
			if ( username_exists( $username ) ) {
				$username .= '_' . wp_generate_password( 4, false );
			}

			$password = wp_generate_password( 20, true, true );
			$user_id  = wp_create_user( $username, $password, $email );

			return is_wp_error( $user_id ) ? 0 : $user_id;
		}

		/**
		 * Fetch the customer email from Stripe.
		 */
		private static function fetch_customer_email( $customer_id ) {
			if ( ! $customer_id ) {
				return '';
			}

			try {
				$customer = StripeCustomer::retrieve( $customer_id );
				return $customer->email ?? '';
			} catch ( Exception $exception ) {
				error_log( sprintf( 'Resume AI Billing: %s', $exception->getMessage() ) );
				return '';
			}
		}

		/**
		 * Ensure thank-you success URL contains session_id placeholder.
		 */
		private static function maybe_format_success_url( string $url ) {
			if ( false === strpos( $url, '{CHECKOUT_SESSION_ID}' ) ) {
				$url = add_query_arg( 'session_id', '{CHECKOUT_SESSION_ID}', trailingslashit( $url ) );
			}
			return $url;
		}

		/**
		 * Determine an end-of-period timestamp for the subscription.
		 */
		private static function extract_period_end( $subscription ) {
			$candidates = [
				isset( $subscription->current_period_end ) ? (int) $subscription->current_period_end : 0,
				isset( $subscription->trial_end ) ? (int) $subscription->trial_end : 0,
			];

			if ( ! empty( $subscription->items->data ) ) {
				$item          = $subscription->items->data[0];
				$candidates[]  = isset( $item->current_period_end ) ? (int) $item->current_period_end : 0;
			}

			foreach ( $candidates as $candidate ) {
				if ( $candidate > 0 ) {
					return $candidate;
				}
			}

			return 0;
		}

		/**
		 * Determine which internal plan matches the subscription items.
		 */
		private static function detect_plan_slug( $subscription ) {
			if ( empty( $subscription->items->data ) ) {
				return '';
			}

			$item        = $subscription->items->data[0] ?? null;
			$price_id    = '';
			$product_id  = '';

			if ( isset( $item->price ) ) {
				$price_id   = $item->price->id ?? '';
				$product_id = $item->price->product ?? '';
			}

			if ( ! $price_id && isset( $item->plan ) ) {
				// Older API versions may only expose plan details.
				$price_id   = $item->plan->id ?? '';
				$product_id = $product_id ?: ( $item->plan->product ?? '' );
			}

			if ( ! $price_id && ! $product_id ) {
				return '';
			}

			$primary = self::resolve_price_id( 'primary' );
			if ( $primary && $price_id === $primary ) {
				return 'primary';
			}

			$primary_product = self::get_config( 'RAI_PRICE_QUARTERLY' );
			if ( $primary_product && $product_id && $product_id === $primary_product ) {
				return 'primary';
			}

			$trial = self::resolve_price_id( 'trial' );
			if ( $trial && $price_id === $trial ) {
				return 'trial';
			}

			$trial_product = self::get_config( 'RAI_PRICE_SECONDARY' );
			if ( $trial_product && $product_id && $product_id === $trial_product ) {
				return 'trial';
			}

			return '';
		}

		private static function get_config( string $constant, $default = '' ) {
			if ( defined( $constant ) && constant( $constant ) ) {
				return constant( $constant );
			}

			$option_key = strtolower( $constant );
			$option     = get_option( $option_key, $default );
			return $option ? $option : $default;
		}

		/**
		 * Initialize the Stripe SDK.
		 */
		private static function boot_stripe( string $secret_key ) {
			$autoload = plugin_dir_path( __FILE__ ) . '../vendor/autoload.php';
			if ( file_exists( $autoload ) ) {
				require_once $autoload;
			}

			Stripe::setApiKey( $secret_key );
			Stripe::setAppInfo( 'Resume AI Toolkit', '1.0.0' );
		}
	}
}

if ( ! function_exists( 'rai_user_can_download' ) ) {
	/**
	 * Determine whether a user can download/export resumes.
	 */
	function rai_user_can_download( $user_id = 0 ) {
		$user_id = $user_id ? $user_id : get_current_user_id();
		if ( ! $user_id ) {
			return false;
		}

		$status = get_user_meta( $user_id, 'rai_stripe_status', true );
		$expiry = (int) get_user_meta( $user_id, 'rai_current_period_end', true );

		if ( ! $status ) {
			return false;
		}

		$allowed_statuses = [ 'trialing', 'active', 'past_due' ];
		if ( ! in_array( $status, $allowed_statuses, true ) ) {
			return false;
		}

		if ( $expiry ) {
			return time() <= $expiry;
		}

		return true;
	}
}
