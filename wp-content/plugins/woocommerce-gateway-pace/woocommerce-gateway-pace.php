<?php

/**
 * Plugin Name: Pace For WooCommerce
 * Description: Provides Pace as a payment method in WooCommerce.
 * Author: Pace Enterprise Pte Ltd
 * Author URI: https://developers.pacenow.co/#plugins-woocommerce
 * Version: 1.1.11
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
define('WC_PACE_GATEWAY_VERSION', '1.1.11');
define('WC_PACE_GATEWAY_NAME', 'Pace For WooCommerce');
define('WC_PACE_GATEWAY_MIN_WC_VER', '3.0');
define('WC_PACE_MAIN_FILE', __FILE__);
define('WC_PACE_PRIORITY', 9999);
define('WC_PACE_GATEWAY_PLUGIN_URL', untrailingslashit(plugins_url(basename(plugin_dir_path(__FILE__)), basename(__FILE__))));
define('WC_PACE_GATEWAY_PLUGIN_PATH', untrailingslashit(plugin_dir_path(__FILE__)));

// phpcs:disable WordPress.Files.FileName

/**
 * WooCommerce fallback notice
 *
 * @since 1.0.0 return notice if callback get errors
 * @return string
 */
if ( ! function_exists( 'pace_notices' ) ) {
	function pace_notices() {
		/* translators: 1. URL link. */
		echo '<div class="error"><p><strong>' . sprintf(esc_html__('You need to installed WooCommerce first before install and active this plugins. You can download %s here.', 'woocommerce-gateway-pace'), '<a href="https://woocommerce.com/" target="_blank">WooCommerce</a>') . '</strong></p></div>';
	}
}

/**
 * WooCommerce not supported fallback notice.
 *
 * @since 1.0.0
 * @return string
 */
if ( ! function_exists( 'woocommerce_pace_gateway_wc_not_supported' ) ) {
	function woocommerce_pace_gateway_wc_not_supported() {
		/* translators: $1. Minimum WooCommerce version. $2. Current WooCommerce version. */
		echo '<div class="error"><p><strong>' . sprintf(esc_html__('This plugins requires WooCommerce %1$s or greater to be installed and active. WooCommerce %2$s is no longer supported.', 'woocommerce-gateway-pace'), WC_PACE_GATEWAY_MIN_WC_VER, WC_VERSION) . '</strong></p></div>';
	}
}

function filter_woocommerce_available_payment_gateways($available_payment )
{
	global $woocommerce;
	
	$result =WC_Pace_Helper::get_merchant_plan();
	if ( !isset($result->maxAmount->actualValue) ) {
		throw new Exception( "Can not get maxAmount from pace", 405 );
	}

	$total = $woocommerce->cart->total;
	// unset($available_payment['pace']);
	$max_amount = $result->maxAmount->actualValue;
	if ((double) $total > (double) $max_amount){
		unset($available_payment['pace']);
	}
	return  $available_payment;
	
}

/**
 * Active plugins
 *
 * @since 1.0.0 run setting when plugins is active
 */
add_action('plugins_loaded', 'woocommerce_gateway_pace_init');

if ( ! function_exists( 'woocommerce_gateway_pace_init' ) ) {
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
				 * @var string
				 */
				private $error_message;

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

					$this->settings = get_option('woocommerce_pace_settings');
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
					require_once dirname(__FILE__) . '/includes/class-wc-pace-cron.php';

					if ( ! $this->is_block() ) {
						return; /* block the plugin when the currency is not allows */
					}

					add_action('admin_notices', array( $this, 'pace_show_admin_notices' ) );
					add_action('admin_enqueue_scripts', array($this, 'loaded_pace_style'));
					// ensure the Pace SDK is loaded before page load
					add_action('wp_enqueue_scripts', array($this, 'loaded_pace_script'));
					// validate Pace transaction before the client access the cancel page
					add_action('wp_loaded', array($this, 'pace_canceled_redirect_uri'), 99);
					add_filter( 'woocommerce_available_payment_gateways', 'filter_woocommerce_available_payment_gateways', 99, 1 );

					add_filter('woocommerce_payment_gateways', array($this, 'add_gateways'));

					// Pace add price widgets
					add_filter('woocommerce_get_price_html', array($this, 'filter_woocommerce_get_price_html'), PHP_INT_MAX * WC_PACE_PRIORITY, 2);
					add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'plugin_action_links'));
					add_filter('woocommerce_update_cart_action_cart_updated', array($this, 'pace_unset_order_session_when_updated_cart'), 20);
				
					WC_Pace_Cron::setup();
				}

				public function get_status_when_transaction_cancelled() {
					return $this->settings['transaction_failed'] ? $this->settings['transaction_failed'] : 'cancelled';
				}

				public function get_status_when_transaction_expired() {
					return $this->settings['transaction_expired'] ? $this->settings['transaction_expired'] : 'failed';
				}

				public function pace_show_admin_notices() {
					$error = get_option('pace_error_notices');

					if ( $error ) {
						printf('
							<div class="notice notice-error is-dismissible"> 
								<p>%s</p>
								<button type="button" class="notice-dismiss">
									<span class="screen-reader-text">Dismiss this notice.</span>
								</button>
							</div>
						',
						$error
						);

						// reset pace errors message
						delete_option( 'pace_error_notices' );
					}
				}

				/**
				 * Unset pre order when updated cart
				 * 
				 * @param Boolean $is_updated 
				 * @since 1.1.9
				 */
				public function pace_unset_order_session_when_updated_cart($is_updated)
				{
					if ($is_updated) {
						unset(WC()->session->order_awaiting_payment);
					}
				}

				/**
				 * Add plugin action links
				 * 	
				 * @param  array $links  WP default plugin action links
				 * @return array 	     Plugin action link after filter
				 */
				public function plugin_action_links($links)
				{
					$setting_url = admin_url('admin.php?page=wc-settings&tab=checkout&section=pace');
					$customize_links = apply_filters(
						'pace_customizer_action_links',
						array(
							sprintf('<a href="%s">%s</a>', $setting_url, __('Settings', 'woocommerce-pace-gateway')),
						),
						$links
					);

					return array_merge($customize_links, $links);
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
				public function is_block()
				{
					$getWCCountriesInstance = new WC_Countries();
					$getBaseCurrency = get_woocommerce_currency();
					$getBaseCountry = $getWCCountriesInstance->get_base_country();

					return WC_Pace_Helper::is_block( $getBaseCurrency, $getBaseCountry );
				}

				/**
				 * Loaded style
				 */
				public function loaded_pace_style()
				{
					wp_register_style('admin_style', WC_PACE_GATEWAY_PLUGIN_URL . '/assets/css/admin-settings.css', null, '1.0.0');
					wp_enqueue_style('admin_style');
				}

				/**
				 * Initialize pace's SDK
				 */
				public function loaded_pace_script()
				{
					$is_testmode = isset($this->settings['sandBox']) && 'yes' === $this->settings['sandBox'];
					$is_enabled  = isset($this->settings['enabled']) && 'yes' === $this->settings['enabled'];
					$pace_sdk = $is_testmode ? 'https://pay-playground.pacenow.co/pace-pay.js' : 'https://pay.pacenow.co/pace-pay.js';
					$suffix = $is_testmode ? '' : '.min';

					$params = array();
					$currency = get_woocommerce_currency();
					$getPacePlan = WC_Pace_Helper::get_merchant_plan();
					$params['flag'] = $this->settings['enable_fallback'];
					$params['minPrice'] = (float) $getPacePlan->minAmount->actualValue; 
					$params['maxPrice'] = (float) $getPacePlan->maxAmount->actualValue;
					$params['currency'] = $currency;

					wp_register_script('woocommerce_pace_init', plugins_url('assets/js/pace' . $suffix . '.js', WC_PACE_MAIN_FILE), null, null, true);
					wp_localize_script('woocommerce_pace_init', 'params', $params);
					wp_register_script('woocommerce_pace_widget', plugins_url('assets/js/pace-widget' . $suffix . '.js', WC_PACE_MAIN_FILE), null, null, true);
					wp_enqueue_script('woocommerce_pace_init');
					wp_enqueue_script('woocommerce_pace_widget');

					// Outputs scripts used for customizer payment
					if (is_checkout() and $is_enabled) {
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
				 * Woocommerce pace Gateway - include widgets
				 * @param  html   $price    WC_Product::get_price_html
				 * @param  object $instance WC_Product::instance
				 * @return html           
				 */
				public function filter_woocommerce_get_price_html($price, $instance)
				{
				
					$product 	= get_queried_object();
					$product_id = $instance->get_id();

					// get product price
				
					if(
						!function_exists('wc_format_sale_price')   || 
						!function_exists('wc_get_price_thousand_separator')   || 
						!function_exists('wc_get_price_decimal_separator')   || 
						!method_exists('WC_Product', 'is_on_sale') || 
						!method_exists('WC_Product', 'get_price')  ||
						!method_exists('WC_Product', 'get_sale_price') ||
						!method_exists('WC_Product', 'get_regular_price')) {
						return $price;
					}
					
					$low_price = null;
					$dom = new DOMDocument; 
					libxml_use_internal_errors(true);
					$dom->loadHTML(str_replace ('”', '"', $price));  
					$xpath = new DOMXPath($dom);
					$domList = $xpath->query('//span[contains(@class, "woocommerce-Price-amount amount")]');
                 
					foreach($domList as $value){
					   
						preg_match('/[\d|\.|\,]+/', $value->nodeValue, $matches);
						if(!isset($matches[0])) {
							continue;
						}	
					 
					   $format_price = str_replace(wc_get_price_thousand_separator(), '', $matches[0] );
					   $format_price = str_replace( wc_get_price_decimal_separator(), '.', $format_price);
					  
					  if(is_numeric($format_price)){
					      if(!$low_price) {
					          $low_price = $format_price;
					          continue;
					      }
					      
					      if($low_price > $format_price ){
					          $low_price = $format_price;
					      }
					  }
					 
					}

					if(!$low_price) {
						$low_price = $instance->get_price();
					}


					// show product price widget by types
					if (
						( isset( $product->post_type ) && $product->post_type == 'product' )
						&& $product_id == $product->ID
					) {
						if ('yes' === $this->settings['enable_single_widget']) {
							$price = $price .
							apply_filters(
								'woocommerce_pace_customize_single_widget',
								sprintf(
									'<div style="%s" data-theme-color="%s" data-single-primary-color="%s" data-single-second-color="%s" data-fontsize="%s" data-price="%s" id="single-widget"> </div>',
									$this->settings['single_widget_style'],
									$this->settings['single_theme_config_color'],
									$this->settings['single_text_primary_color'],
									$this->settings['single_text_second_color'],
									$this->settings['single_fontsize'],
									esc_attr( $low_price )
								),
								$this->settings,
								$instance
							);
						}
					} else {
						if ('yes' === $this->settings['enable_multiple_widget']) {
							$price = $price .
							apply_filters(
								'woocommerce_pace_customize_multiple_widget',
								sprintf(
									'</span> <div style="%s" data-theme-color="%s" data-text-color="%s" data-fontsize="%s" data-price="%s" id="multiple-widget"> </div> <span>',
									$this->settings['multiple_widget_style'],
									$this->settings['multiple_theme_config_color'],
									$this->settings['multiple_text_color'],
									$this->settings['multiple_fontsize'],
									esc_attr( $low_price )
								),
								$this->settings,
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
				public function update_order_status_supports($order)
				{
					try {
						// clear order session first
						WC()->session->set( 'order_awaiting_payment', false );

						// retrieve Pace transaction
						$getTransaction = WC_Pace_API::request(array(), 'checkouts/' . $order->get_transaction_id(), $method = 'GET');
						
						if (isset($getTransaction->error)) {
							throw new Exception(__('Pace transaction cannot be retrieved, therefore your order is invalid.' . $getTransaction->correlation_id, 'woocommerce-pace-gateway'));
						}

						$isUpdateStatus = WC_Pace_Cron::check_order_manually_update( $order->get_id() );

						if ( ! $isUpdateStatus ) {
							$noticeMessage = __( 'Pace transaction has been cancelled.', 'woocommerce-gateway-pace' );
						} else {
							if (
								$order->has_status($this->settings['transaction_failed']) ||
								!in_array($getTransaction->status, array('cancelled', 'expired'))
							) {
								throw new Exception(__('Your order can no longer be cancelled. Please contact us if you need assistance.', 'woocommerce-pace-gateway'));
							}

							switch ($getTransaction->status) {
								case 'cancelled':
									$statuses = $this->get_status_when_transaction_cancelled();
									break;
								case 'expired':
									$statuses = $this->get_status_when_transaction_expired();
									break;
								default:
									$statuses = '';
									break;
							}

							$noticeMessage = __("Your order has been {$statuses}.", 'woocommerce-gateway-pace');
							$order->update_status($statuses, $noticeMessage);
						}

						wc_add_notice( apply_filters( 'woocommerce_order_cancelled_notice', $noticeMessage ), 'notice');
					} catch (Exception $e) {
						wc_add_notice($e->getMessage(), 'error');
					}
				}

				/**
				 * Update order status when the transaction has been canceled that based on merchant setting
				 * Note: override cancel order function
				 * 
				 * @since 1.1.4
				 */
				public function pace_canceled_redirect_uri()
				{
					if (
						isset($_GET['cancel_order']) &&
						isset($_GET['order']) &&
						isset($_GET['order_id']) &&
						isset($_GET['merchantReferenceId'])
					) {
						$order_id = (int) wp_unslash($_GET['order_id']); // phpcs:ignore
						$order = wc_get_order($order_id);
						$user_can_cancel = current_user_can('cancel_order', $order_id);

						if (!$user_can_cancel) {
							wc_add_notice(
								__('You are not allowed to cancel this order', 'woocommerce-pace-gateway'),
								'error'
							);
						} else {
							$this->update_order_status_supports($order);
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
}