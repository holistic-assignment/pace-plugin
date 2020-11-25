<?php

/**
 * Plugin Name: Pace For WooCommerce
 * Description: Provides Pace as a payment method in WooCommerce.
 * Author: Pace Enterprise Pte Ltd
 * Author URI: https://pace.co/
 * Version: 1.0.0
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

				if ( !$this->is_block() ) {
					return; /* block the plugin when the currency is not allows */
				}
				add_action('admin_enqueue_scripts', array($this, 'loaded_pace_style'));
				add_action('wp_enqueue_scripts', array($this, 'loaded_pace_script')); /* make sure pace's SDK is load early */
				add_filter('woocommerce_payment_gateways', array($this, 'add_gateways'));
				add_filter('woocommerce_get_price_html', array($this, 'filter_woocommerce_get_price_html'), 10, 2); /* include pace's widgets */
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
				$pace_settings = get_option( 'woocommerce_pace_settings' );
				$is_testmode = isset( $pace_settings['sandBox'] ) and 'yes' === $pace_settings['sandBox'];
				$is_enabled  = isset( $pace_settings['enabled'] ) and 'yes' === $pace_settings['enabled'];
				$pace_sdk = $is_testmode ? 'https://pay-playground.pacenow.co/pace-pay.js' : 'https://pay.pacenow.co/pace-pay.js';
				$suffix = $is_testmode ? '' : '.min';

				$fallback_params = array();
				$fallback_params['flag'] = $pace_settings['enable_fallback'];
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
					$pace_params['checkout_mode'] = $pace_settings['checkout_mode'];

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
