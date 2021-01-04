<?php

/**
 * Plugin Name: Pace For WooCommerce
 * Description: Provides Pace as a payment method in WooCommerce.
 * Author: Pace Enterprise Pte Ltd
 * Author URI: https://developers.pacenow.co/#plugins-woocommerce
 * Version: 1.1.5
 * Requires at least: 5.3
 * WC requires at least: 3.0
 * Requires PHP: 7.*
 * Text Domain: woocommerce-gateway-pace
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Required minimums and constants
 */
define('WC_PACE_GATEWAY_VERSION', '1.0.0');
define('WC_PACE_GATEWAY_NAME', 'Pace For WooCommerce');
define('WC_PACE_GATEWAY_MIN_WC_VER', '3.0');
define('WC_PACE_MAIN_FILE', __FILE__);
define('WC_PACE_GATEWAY_PLUGIN_URL', untrailingslashit(plugins_url(basename(plugin_dir_path(__FILE__)), basename(__FILE__))));
define('WC_PACE_GATEWAY_PLUGIN_PATH', untrailingslashit(plugin_dir_path(__FILE__)));

// phpcs:disable WordPress.Files.FileName

/**
 * WooCommerce fallback notice
 *
 * @since 1.0.0 return notice if callback get errors
 * @return string
 */
function pace_notices()
{
	/* translators: 1. URL link. */
	echo '<div class="error"><p><strong>' . sprintf(esc_html__('You need to installed WooCommerce first before install and active this plugins. You can download %s here.', 'woocommerce-gateway-pace'), '<a href="https://woocommerce.com/" target="_blank">WooCommerce</a>') . '</strong></p></div>';
}

/**
 * WooCommerce not supported fallback notice.
 *
 * @since 1.0.0
 * @return string
 */
function woocommerce_pace_gateway_wc_not_supported()
{
	/* translators: $1. Minimum WooCommerce version. $2. Current WooCommerce version. */
	echo '<div class="error"><p><strong>' . sprintf(esc_html__('This plugins requires WooCommerce %1$s or greater to be installed and active. WooCommerce %2$s is no longer supported.', 'woocommerce-gateway-pace'), WC_PACE_GATEWAY_MIN_WC_VER, WC_VERSION) . '</strong></p></div>';
}

/**
 * Active plugins
 *
 * @since 1.0.0 run setting when plugins is active
 */
add_action('plugins_loaded', 'woocommerce_gateway_pace_init');


function compare_transaction()
{
	$params = [
		"from" =>  date('yy-m-01'),
		"to"	=> date('yy-m-d')
	];
	$pace_settings = get_option('woocommerce_pace_settings');
	$fail_status = !!$pace_settings['transaction_failed'] ? "wc-" . $pace_settings['transaction_failed'] : "wc-cancelled";
	$expired_status = !!$pace_settings['transaction_expired'] ? "wc-" . $pace_settings['transaction_expired'] : "wc-failied";
	$list_transaction = WC_Pace_API::request($params, "checkouts/list");
	if ($list_transaction->items) {
		// remove duplicate order
		$orders = [];
		foreach($list_transaction->items as $key => $transaction ) {
			foreach($transaction  as $value) {
				$orders[$value->referenceID] = $value;
			}
		}
	
		foreach($orders as $key => $value) {
			$order = wc_get_order($value->referenceID);
			if ($order) {
				if ($order->get_payment_method() == "pace") {
					switch ($value->status) {
						case 'cancelled':
							if ($order->get_status() != "cancelled") {
								WC_Pace_Logger::log("Convert " . $order->get_id() . " from " . $order->get_status()   . " wc-cancelled");
								$order->set_status($fail_status);
								$order->save();
							}
							break;
						case 'pending_confirmation':
							if ($order->get_status() != "pending") {
								WC_Pace_Logger::log("Convert " . $order->get_id() . " from " . $order->get_status()   . " wc-pending");
								$order->set_status("wc-pending");
								$order->save();
							}
							break;
						case 'approved':
							if ($order->get_status() != "completed") {
								WC_Pace_Logger::log("Convert " . $order->get_id() . " from " . $order->get_status()  . "wc-approved");
								$order->set_status("wc-completed");
								$order->save();
							}
							break;

						case 'expired':
							if ($order->get_status() != "failed") {
								WC_Pace_Logger::log("Convert " . $order->get_id() . " from " . $order->get_status() . "wc-failied");
								$order->set_status($expired_status);
								$order->save();
							}
							break;
					}
				}
			}
		}
		
	}
}

add_action('hook_compare_transaction', 'compare_transaction');
add_action('check_cron_exist', 'handle_add_cron');
function handle_add_cron()
{
	$pace_settings = get_option('woocommerce_pace_settings');
	$time =  isset($pace_settings['interval_cron']) && is_numeric($pace_settings['interval_cron']) ? (int)$pace_settings['interval_cron'] : 300;
	if (!check_hook_cron_exist(["hook_compare_transaction", "complete", "failed"])) {
		as_schedule_single_action(time() + $time, 'hook_compare_transaction');
	}
}

function check_hook_cron_exist($args)
{
	global $wpdb;
	$query = "SELECT a.action_id FROM wp_actionscheduler_actions a";
	$query  .= " WHERE a.hook=%s";

	$query  .= " AND a.status<> %s and a.status <> %s   order by action_id desc LIMIT 1";
	$query = $wpdb->prepare($query, $args);

	$id = $wpdb->get_var($query);
	return $id;
}

// add the filter 

function woocommerce_gateway_pace_init()
{
	// load i10n
	load_plugin_textdomain('woocommerce-gateway-pace', false, plugin_basename(dirname(__FILE__)) . '/languages');

	// check WooCommerce has been installed
	if (!class_exists('WooCommerce')) {
		add_action('admin_notices', 'pace_notices');
		return;
	}

	// compare WooCommerce version
	if (version_compare(WC_VERSION, WC_PACE_GATEWAY_MIN_WC_VER, '<')) {
		add_action('admin_notices', 'woocommerce_pace_gateway_wc_not_supported');
		return;
	}

	if (!class_exists('WC_PACE_GATEWAY')) {

		class WC_PACE_GATEWAY
		{
			/**
			 * Pace settings
			 * @var array
			 */
			private $settings;

			/**
			 * @var Singleton The reference the *Singleton* instance of this class
			 */
			private static $instance;

			/**
			 * Returns the *Singleton* instance of this class.
			 *
			 * @return Singleton The *Singleton* instance.
			 */
			public static function get_instance()
			{
				if (null === self::$instance) {
					self::$instance = new self();
				}
				return self::$instance;
			}

			/**
			 * Private clone method to prevent cloning of the instance of the
			 * *Singleton* instance.
			 *
			 * @return void
			 */
			private function __clone()
			{
			}

			/**
			 * Private unserialize method to prevent unserializing of the *Singleton*
			 * instance.
			 *
			 * @return void
			 */
			private function __wakeup()
			{
			}

			/**
			 * Protected constructor to prevent creating a new instance of the
			 * *Singleton* via the `new` operator from outside of this class.
			 */
			private function __construct()
			{
				add_action('admin_init', array($this, 'install'));

				$this->settings = get_option( 'woocommerce_pace_settings' );
				$this->init();
			}

			/**
			 * Init the plugin after plugins_loaded so environment variables are set.
			 *
			 * @since 1.0.0
			 * @version 1.0.0
			 */
			public function init()
			{	
				require_once dirname(__FILE__) . '/includes/abstracts/abstract-wc-pace-payment-gateway.php';
				require_once dirname(__FILE__) . '/includes/class-wc-pace-logger.php';
				require_once dirname(__FILE__) . '/includes/class-wc-pace-api.php';
				require_once dirname(__FILE__) . '/includes/class-wc-pace-helper.php';
				require_once dirname(__FILE__) . '/includes/class-wc-pace-locked.php';
				require_once dirname(__FILE__) . '/includes/class-wc-pace-gateway.php';
				require_once dirname(__FILE__) . '/includes/class-wc-pace-request.php'; /* handle payment request */

				if ( !$this->is_block() )
					return; /* block the plugin when the currency is not allows */

				add_action('admin_enqueue_scripts', array($this, 'loaded_pace_style'));
				add_action('wp_enqueue_scripts', array($this, 'loaded_pace_script')); /* make sure pace's SDK is load early */
				add_action('woocommerce_order_status_changed', array($this, 'cancel_payment'), 10, 4);
				add_action('woocommerce_before_thankyou', array($this, 'pace_validate_before_success_redirect'));
				add_action('wp_loaded', array($this, 'pace_canceled_redirect_uri'), 99); /* update order status based on merchant setting on dashboard */

				add_filter('woocommerce_payment_gateways', array($this, 'add_gateways'));
				add_filter('woocommerce_get_price_html', array($this, 'filter_woocommerce_get_price_html'), 10, 2); /* include pace's widgets */
				add_filter('plugin_action_links_' . plugin_basename( __FILE__ ), array($this, 'plugin_action_links'));
				add_filter('woocommerce_update_cart_action_cart_updated', array($this, 'pace_unset_order_session_when_updated_cart'), 20);

				do_action('check_cron_exist');
			}

			/**
			 * Unset pre order when updated cart
			 * 
			 * @param Boolean $is_updated 
			 * @since 1.1.5
			 */
			public function pace_unset_order_session_when_updated_cart( $is_updated ) {
				if ( $is_updated ) {
					unset( WC()->session->order_awaiting_payment );
				}
			}

			/**
			 * Add plugin action links
			 * 	
			 * @param  array $links  WP default plugin action links
			 * @return array 	     Plugin action link after filter
			 */
			public function plugin_action_links($links) {
				$setting_url = admin_url( 'admin.php?page=wc-settings&tab=checkout&section=pace' );
				$customize_links = apply_filters( 'pace_customizer_action_links', 
					array(
						sprintf( '<a href="%s">%s</a>', $setting_url, __( 'Settings', 'woocommerce-pace-gateway' ) ),
					), 
					$links 
				);
				
				return array_merge( $customize_links, $links );
			}

			/**
			 * Handles upgrade routines.
			 *
			 * @since 1.0.0
			 * @version 1.0.0
			 */
			public function install()
			{
				if (!is_plugin_active(plugin_basename(__FILE__))) {
					return;
				}
			}

			/**
			 * Blocked the plugins
			 * WC_pace_Helper::is_block()
			 * 
			 * @since 1.1.0
			 * @version 1.0.0
			 * @return boolean
			 */
			public function is_block() {
				return WC_Pace_Helper::is_block();
			}

			/**
			 * Loaded style
			 */
			public function loaded_pace_style() {
				wp_register_style( 'admin_style', WC_PACE_GATEWAY_PLUGIN_URL . '/assets/css/admin-settings.css', null, '1.0.0' );
				wp_enqueue_style( 'admin_style' );
			}

			/**
			 * Initialize pace's SDK
			 */
			public function loaded_pace_script() {
				$is_testmode = isset( $this->settings['sandBox'] ) && 'yes' === $this->settings['sandBox'];
				$is_enabled  = isset( $this->settings['enabled'] ) && 'yes' === $this->settings['enabled'];
				$pace_sdk = $is_testmode ? 'https://pay-playground.pacenow.co/pace-pay.js' : 'https://pay.pacenow.co/pace-pay.js';
				$suffix = $is_testmode ? '' : '.min';

				$fallback_params = array();
				$fallback_params['flag'] = $this->settings['enable_fallback'];
				wp_register_script('woocommerce_pace_init', plugins_url('assets/js/pace' . $suffix . '.js', WC_PACE_MAIN_FILE), null, null, true);
				wp_localize_script('woocommerce_pace_init', 'fallback_params', $fallback_params);
				wp_register_script('woocommerce_pace_widget', plugins_url('assets/js/pace-widget' . $suffix . '.js', WC_PACE_MAIN_FILE), null, null, true);
				wp_enqueue_script('woocommerce_pace_init');
				wp_enqueue_script('woocommerce_pace_widget');

				// Outputs scripts used for customizer payment
				if ( is_checkout() and $is_enabled ) {
					$pace_params = array();
					$pace_params['ajaxurl'] = WC_AJAX::get_endpoint('%%endpoint%%');
					$pace_params['pace_nonce'] = wp_create_nonce('_wc_pace_nonce');
					$pace_params['checkout_mode'] = $this->settings['checkout_mode'];

					wp_register_script('woocommerce_pace_checkout', plugins_url('assets/js/pace-checkout' . $suffix . '.js', WC_PACE_MAIN_FILE), null, null, true);
					wp_localize_script('woocommerce_pace_checkout', 'wc_pace_params', $pace_params);
					wp_enqueue_script('woocommerce_pace_checkout');
				}
				
				wp_enqueue_script('pace', $pace_sdk, null, null, true);
			}

			/**
			 * Handle cancel Pace transaction in dashboard
			 * 
			 * @param int 		$order_id  		WC_Order::id
			 * @param string 	$from old order status
			 * @param string 	$to new order 	status
			 * @param WC_Order 	$instance 		WC_Order:instance
			 * 
			 * @since 1.0.1
			 * @version 1.0.0
			 */
			public function cancel_payment($order_id, $from, $to, $instance)
			{
				try {
					$order = wc_get_order( $order_id );

					if ( is_wp_error( $order ) ) {
						throw new Exception( __( $order->get_error_messages(), 'woocommerce-pace-gateway' ) );
					}

					if ( 'cancelled' === $to AND 'pace' === $order->get_payment_method() ) {
						// cancelled Pace transaction
						$response = WC_Pace_Gateway_Payment::cancel_transaction($order);

						if ( isset( $response->error ) ) {
							$localized_message = __( "There is a problem canceling the order. {$response->error->message}", 'woocommerce-pace-gateway' );
							
							add_filter( 'woocommerce_order_cancelled_notice', function() use ( $localized_message ) {
								return $localized_message;
							} );

							throw new Exception( $localized_message );
						}

						// do nothing
					}
				} catch (Exception $e) {
					WC_Pace_Logger::log( 'Error: ' . $e->getMessage() );

					$order->add_order_note( $e->getMessage() );

					// back to the previous status
					$order->set_status(wc_clean($from), '', true);
					$order->save();
				}
			}

			/**
			 * Validate Pace transaction before render success page
			 * 
			 * @param WC_Order $order_id 
			 * @since 1.1.4 
			 */
			public function pace_validate_before_success_redirect( $order_id ) {
				$order = wc_get_order( $order_id );

				if ( ! $order->get_transaction_id() && 'pace' !== $order->get_payment_method() ) {
					return;
				}

				$_transaction = WC_Pace_API::request( 
					array(),
					sprintf( 'checkouts/%s', $order->get_transaction_id() ),
					$method = 'GET'
				);

				try {
					$statuses = '';

					if ( isset( $_transaction->error ) ) {
						$statuses = 'failed';

						throw new Exception( __( 'Your order is not valid.', 'woocommerce-pace-gateway' ) );
					}

					if ( 'approved' === $_transaction->status ) {
						$order->update_status( 'completed' );
						return;
					}

					throw new Exception( 'order failed' );
				} catch (Exception $e) {
					$redirect_cancel_uri = WC_Pace_Helper::pace_http_build_query( 
						$order->get_cancel_order_url_raw(), 
						array(
							'merchantReferenceId' => $order_id
						) 
					);

					wp_safe_redirect( $redirect_cancel_uri );
					exit();
				}
			}

			/**
			 * Woocommerce pace Gateway - include widgets
			 * @param  html   $price    WC_Product::get_price_html
			 * @param  object $instance WC_Product::instance
			 * @return html           
			 */
			public function filter_woocommerce_get_price_html($price, $instance)
			{
				$options 	= get_option('woocommerce_pace_settings');
				$_product 	= get_queried_object();
				$product_id = $instance->get_id();

				// show product price widget by types
				if (
					( isset($_product->post_type) and $_product->post_type == 'product' )
					and $product_id == $_product->ID
				) {
					if ('yes' === $options['enable_single_widget']) {
						$price = $price .
							apply_filters(
								'woocommerce_pace_customize_single_widget',
								sprintf(
									'<div style="%s" data-theme-color="%s" data-single-primary-color="%s" data-single-second-color="%s" data-fontsize="%s" data-price="%s" id="single-widget"> </div>',
									$options['single_widget_style'],
									$options['single_theme_config_color'],
									$options['single_text_primary_color'],
									$options['single_text_second_color'],
									$options['single_fontsize'],
									esc_attr($instance->get_price())
								),
								$options,
								$instance
							);
					}
				} else {
					if ('yes' === $options['enable_multiple_widget']) {
						$price = $price .
							apply_filters(
								'woocommerce_pace_customize_multiple_widget',
								sprintf(
									'</span> <div style="%s" data-theme-color="%s" data-text-color="%s" data-fontsize="%s" data-price="%s" id="multiple-widget"> </div> <span>',
									$options['multiple_widget_style'],
									$options['multiple_theme_config_color'],
									$options['multiple_text_color'],
									$options['multiple_fontsize'],
									esc_attr($instance->get_price())
								),
								$options,
								$instance
							);
					}
				}

				return $price;
			}	

			/**
			 * Update order status when transaction cancelled/failed based on Merchant settings
			 * 
			 * @param WC_Order $order
			 */
			public function update_order_status_supports( $order ) {
				try {
					// clear order session first
					unset( WC()->session->order_awaiting_payment );

					// validate Pace transaction before update order
					$_transaction = WC_Pace_API::request( 
						array(),
						sprintf( 'checkouts/%s', $order->get_transaction_id() ),
						$method = 'GET'
					);

					if ( isset( $_transaction->error ) ) {
						throw new Exception( __( 'Your order is not valid.', 'woocommerce-pace-gateway' ) );
					}

					if ( 
						$order->has_status( $this->settings['transaction_failed'] ) ||
						!in_array( $_transaction->status, array( 'cancelled', 'expired' ) )
					) {
						throw new Exception( __( "Your order can no longer be cancelled. Please contact us if you need assistance.", 'woocommerce-pace-gateway' ) );
					}

					$statuses = '';
					switch ( $_transaction->status ) {
						case 'cancelled':
							$statuses = $this->settings['transaction_failed'];
							break;
						case 'expired':
							$statuses = $this->settings['transaction_expired'];
							break;
						default:
							# do nothing
							break;
					}

					$order->update_status( $statuses, __( "Order has been {$statuses} by customer.", 'woocommerce' ) );
					wc_add_notice( 
						apply_filters( 
							'woocommerce_order_cancelled_notice', 
							__( "Your order has been {$statuses}.", 'woocommerce' ), 
						),
						'notice'
					);
				} catch (Exception $e) {
					wc_add_notice( $e->getMessage(), 'error' );
				}
			}

			/**
			 * Update order status when the transaction has been canceled that based on merchant setting
			 * Note: override cancel order function
			 * 
			 * @since 1.1.4
			 */
			public function pace_canceled_redirect_uri() {
				if ( 
					isset( $_GET['cancel_order'] ) &&
					isset( $_GET['order'] ) &&
					isset( $_GET['order_id'] ) && 
					isset( $_GET['merchantReferenceId'] )
				) {
					$order_id = (int) wp_unslash( $_GET['order_id'] ); // phpcs:ignore
					$order = wc_get_order( $order_id );
					$user_can_cancel = current_user_can( 'cancel_order', $order_id );

					if ( ! $user_can_cancel ) {
						wc_add_notice( 
							__( 'You are not allowed to cancel this order', 'woocommerce-pace-gateway' ), 
							'error' 
						);
					} else {
						$this->update_order_status_supports( $order );	
					}
				}
			}

			/**
			 * Add the gateways to WooCommerce.
			 * 
			 * @param array $methods WooCommerce payment gateway
			 * @since 1.0.0 
			 * @version 1.1.0 
			 */
			public function add_gateways($methods)
			{	
				$methods[] = 'WC_Pace_Gateway_Payment';

				return $methods;
			}
		}

		WC_PACE_GATEWAY::get_instance();
	}
}
