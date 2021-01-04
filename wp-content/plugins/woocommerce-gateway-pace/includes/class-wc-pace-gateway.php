<?php
if (!defined('ABSPATH')) {
	return;
}

// phpcs:disable WordPress.Files.FileName

/**
 * WooCoommerce Pacenow Gateway Payment
 *
 * @extends WC_Payment_Gateway
 */
class WC_Pace_Gateway_Payment extends Abstract_WC_Pace_Payment_Gateway
{
	/**
	 * The delay between retries.
	 *
	 * @var int
	 */
	public $retry_interval;

	/**
	 * Should we store the users credit cards?
	 *
	 * @var bool
	 */
	public $saved_cards;

	/**
	 * Pre Orders Object
	 *
	 * @var object
	 */
	public $pre_orders;

	/**
	 * Is testmode active?
	 * 
	 * @var bool
	 */
	public $testmode;

	/**
	 * Constructor
	 */
	public function __construct()
	{	
		$this->id = 'pace';
		$this->has_fields = true;
		$this->method_title = __('Pace', 'woocommerce-pace-gateway');
		$this->method_description =  __('Spread your purchases into interest-free instalments.', 'woocommerce-pace-gateway');
		$this->supports = array(
			'products',
			'refunds',
			'tokenization',
			'add_payment_method',
			'subscriptions',
			'subscription_cancellation',
			'subscription_suspension',
			'subscription_reactivation',
			'subscription_amount_changes',
			'subscription_date_changes',
			'subscription_payment_method_change',
			'subscription_payment_method_change_customer',
			'subscription_payment_method_change_admin',
			'multiple_subscriptions',
			'pre-orders',
		);

		// Load the form fields.
		$this->init_form_fields();
		// Load the settings.
		$this->init_settings();

		// Get setting values.
		$this->title         = $this->get_option('title');
		$this->enabled       = $this->get_option('enabled');
		$this->testmode	     = 'yes' === $this->get_option('sandBox');
		$this->description   = $this->get_description();
		$this->checkout_mode = $this->get_option('checkout_mode');
		$this->order_status_failed = $this->get_option('transaction_failed'); /* update Order status when transaction is cancelled follow merchant setting */
		$this->order_status_expire = $this->get_option('transaction_expired');

		$this->client_id   	 = $this->testmode ? $this->get_option('sandbox_client_id') : $this->get_option('client_id');
		$this->client_secret = $this->testmode ? $this->get_option('sandbox_client_secret') : $this->get_option('client_secret');
		$this->options       = get_option('woocommerce_pace_settings');
		// hooks
		$this->initHooks();
	}

	/**
	 * Displays the admin settings payment sub-title
	 * 
	 * @return mixed
	 * @since 1.0.0
	 */
	public function get_title() {
		global $pagenow;
		/* translators: 1) webhook url */
		$pace_title = wp_kses_post( $this->title );
		
		if ( 
			( is_admin() and in_array( $pagenow, array( 'post.php' ) ) )
			or ( isset( $_REQUEST['wc-ajax'] ) and 'checkout' === $_REQUEST['wc-ajax'] )
			or ( isset( $_REQUEST['wc-ajax'] ) and 'wc_pace_create_transaction' === $_REQUEST['wc-ajax'] )
		) {
			$pace_title = 'Pace';
		} elseif ( 
			( isset( $_REQUEST['wc-ajax'] ) and 'update_order_review' === $_REQUEST['wc-ajax'] ) // shows on checkout page - wc-ajax:update_order_review
		   	or !is_admin() 
		) {
			/**
			 * Update checkout payment logo
			 *
			 * @since 1.0.3
			 */
			$logo = file_get_contents( wc_clean( WC_PACE_GATEWAY_PLUGIN_PATH . '/assets/image/logo.svg' ) );
			$pace_title = "<span style='display: inline-flex; align-items: center;'>Pay with $logo in 3 instalments</span>";
		}

		return __( $pace_title, 'woocommerce-pace-gateway' );
	}

	/**
	 * Displays the plugin description
	 * 
	 * @return string default description
	 */
	public function get_description() {
		return __( "Spread your purchases into 3 interest-free instalments.<br/><br/>Reminders and notifications will be sent to you each time a payment is due or has been charged.", 'woocommerce-pace-gateway' );
	}

	/**
	 * Overried the description for admin screens.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function get_method_description() {
		$description = __( 'Manage Pace’s API connectivity and widgets settings', 'woocommerce-pace-gateway' );
		return apply_filters( 'woocommerce_gateway_method_description', $description, $this );
	}

	/**
	 * Override output the gateway settings screen.
	 * 
	 * @since 1.0.0
	 */
	public function admin_options() {
		printf( '<img id="%s" src="%s">', 'pace-settings-logo', WC_PACE_GATEWAY_PLUGIN_URL . '/assets/image/logo.png' );
		parent::admin_options();
	}

	/**
	 * Displays the admin settings payment methods note.
	 *
	 * @return mixed
	 */
	public function display_admin_settings_payment_methods_note()
	{
		/* translators: 1) webhook url */
		return __('You must be a Pace registered merchant to get these credentials. Please contact merchant-integration@pacenow.co if you need help retrieving these details.', 'woocommerce-pace-gateway');
	}

	/**
	 * Check available payment methods
	 * 
	 * @return boolean
	 */
	public function is_available()
	{	
		/**
		 * checkout - update order rivew
		 * since 1.1.0
		 */ 
		$country = isset( WC()->customer ) ? WC()->customer->get_billing_country() : '';
		$currency = WC_Pace_Helper::get_currency_by_country( $country );
		
		if ( 
			'yes' == $this->enabled and 
			! empty( $this->client_id ) and 
			! empty( $this->client_secret ) and 
			WC_Pace_Helper::is_block( $currency )
		) {
			return true;
		}

		return;
	}

	public function initHooks()
	{	
		// actions
		add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options')); /* customizer gateway payment save change */
		add_action('woocommerce_thankyou_' . $this->id, array($this, 'pace_redirect_payment_update_order_status' )); /* update order status when redirect checkout */

		/** 
		 * Checkout process with validation posted data
		 * @since 1.1.0
		 */ 
		add_action('woocommerce_create_transaction_before_checkout', array($this, 'woocommerce_create_transaction_before_checkout_hooks'), 10, 2);
	}

	/**
	 * Initialise Gateway Settings Form Fields
	 */
	public function init_form_fields()
	{
		$this->form_fields = require(dirname(__FILE__) . '/admin/pace-gateway-settings.php');
	}

	/**
	 * Payment form on checkout page
	 */
	public function payment_fields()
	{
		global $wp, $woocommerce;
		$options = $this->options;

		do_action('woocommerce_checkout_before_order_review');

		$display_tokenization = $this->supports('tokenization') && is_checkout();
		$user 				  = wp_get_current_user();
		$user_email 		  = '';
		$firstname  	      = '';
		$lastname   		  = '';
		$total 				  = $woocommerce->cart->total;

		// If paying from order, we need to get total from order not cart.
		if (isset($_GET['pay_for_order']) && !empty($_GET['key'])) { // wpcs: csrf ok.
			$order      = wc_get_order(wc_clean($wp->query_vars['order-pay'])); // wpcs: csrf ok, sanitization ok.
			$user_email = $order->get_billing_email();
		} else {
			if ($user->ID) {
				$user_email = get_user_meta($user->ID, 'billing_email', true);
				$user_email = $user_email ? $user_email : $user->user_email;
			}
		}

		if (is_add_payment_method_page()) {
			$firstname = $user->user_firstname;
			$lastname  = $user->user_lastname;
		}

		ob_start();

		printf(
			'<div
			id="pace-payment-data"
			data-email="%s"
			data-full-name="%s"
			data-currency="%s">',
			esc_attr($user_email),
			esc_attr($firstname . ' ' . $lastname),
			esc_attr(strtolower(get_woocommerce_currency()))
		);

		echo '<div class="pace-pay-description" style="margin-bottom: 20px;">'. $this->description .'</div>';

		echo "<div id='pace-pay-widget-container' 
				data-price=$total
				data-enabled='$options[enable_checkout_widget]'
				data-checkout-primary-color='$options[checkout_text_primary_color]'
				data-timeline-color='$options[checkout_text_timeline_color]'
				data-checkout-second-color='$options[checkout_text_second_color]'
				data-fontsize='$options[checkout_fontsize]'
				data-checkout-background-color='$options[checkout_background_color]'
				data-checkout-foreground-color='$options[checkout_foreground_color]'
			 </div>";
		// load csrs
		if ($display_tokenization) {
			$this->tokenization_script();
			$this->saved_payment_methods();
		}

		$this->elements_form();

		// $this->save_payment_method_checkbox();

		echo '</div>';

		ob_end_flush();
	}

	/**
	 * Renders the payment gateway elements form.
	 *
	 * @since 1.0.0
	 * @version 1.0.0
	 */
	public function elements_form()
	{
		// printf( 'Test' );
	}

	/**
	 * Completed free order
	 * 
	 * @param  WC_Order $order The order completed
	 * @return array           Redirection data for `process_payment`.
	 */
	public function completed_free_order($order)
	{
		// Remove cart.
		WC()->cart->empty_cart();

		$order->payment_complete();

		// Return thank you page redirect.
		return array(
			'result'   => 'success',
			'redirect' => $this->get_return_url($order),
		);
	}

	/**
	 * Pacenow create the transaction by checkout Order
	 * 
	 * @param  WC_Order $order Woocommerce order
	 * @return array           Pacenow API response
	 */
	public function make_request_create_transaction($order)
	{
		$pacenow_source = apply_filters('woocommerce_pace_prepare_source', $this->prepare_order_source($order));
		// check source before send the request
		if (isset($pacenow_source) and !empty($pacenow_source)) {
			$response = WC_Pace_API::request($pacenow_source, 'checkouts');
		}

		return $response;
	}

	/**
	 * Customizer Gateway process payment
	 * 	
	 * @param  WC_Order 	$order_id 	The order id has just been created
	 * @throws Exception  				If payment will not be accepted.
	 * @return array|void
	 */
	public function process_payment($order_id)
	{
		try {
			$order = wc_get_order($order_id);

			// lock the process until the checkout is completed
			$lock = new WC_Pace_Locked();

			if (!$lock->lock()) {
				WC_Pace_Logger::log(__('Process is locked.', 'woocommerce-pace-gateway'));
			}

			// if order have no price, completed order without payment
			if (0 >= $order->get_total()) {
				return $this->completed_free_order($order);
			}

			// process order after transaction is created
			$transaction = isset($_POST['pace_transaction']) ? stripslashes_deep($_POST['pace_transaction']) : null;

			if (is_null($transaction)) {
				throw new Exception(__('Missing the transaction source, please create a transaction first.', 'woocommerce-pace-gateway'));
			}

			// convert transaction to array
			$transaction = json_decode($transaction, true);

			$this->process_response($transaction, $order);

			// make cart is empty
			if (isset(WC()->cart)) {
				WC()->cart->empty_cart();
			}

			// unlock the process
			$lock->unlock();

			return array(
				'result'      => 'success',
				'redirect'    => isset($transaction['redirect']) ? esc_url($transaction['redirect']) : $this->get_return_url($order),
				'transaction' => $transaction
			);
		} catch (Exception $e) {
			wc_add_notice($e->getMessage(), 'error');
			WC_Pace_Logger::log('Error: ' . $e->getMessage());

			do_action('wc_gateway_pace_process_payment_error', $e, $order);
			/* translators: error message */
			$order->update_status('failed');

			return array(
				'result'   => 'fail',
				'redirect' => '',
			);
		}
	}

	/**
	 * Checks whether new keys are being entered when saving options.
	 */
	public function process_admin_options()
	{
		parent::process_admin_options();
	}

	/**
	 * Pacenow Gateway - create transaction
	 * 
	 * @param  array $posted_data  checkout's validated post data
	 * @since  1.1.0
	 * @return array|object transaction response
	 */
	public function woocommerce_create_transaction_before_checkout_hooks( $posted_data ) {
		try {
			// update cart total
			WC()->cart->calculate_totals();

			$order_id = WC()->checkout->create_order( $posted_data );
				
			if ( is_wp_error( $order_id ) ) {
				throw new Exception( __( $order_id->get_error_messages(), 'woocommerce-pace-gateway' ) );
			}
			
			$order = wc_get_order( $order_id );

			if ( is_wp_error( $order ) ) {
				$localized_message = apply_filters( 
					'woocommerce_api_cannot_create_order',
					sprintf( 
						__( 'Cannot create order: %s', 'woocommerce-pace-gateway' ),
						implode( ', ', $order->get_error_messages() )
					)
				);
				
				throw new Exception( $localized_message );
			}

			// store the pre-order id for later use
			WC()->session->set( 'order_awaiting_payment', $order_id );

			// check the order has Pace transaction id
			// if not, send the request to Pacenow API to create transaction
			$transaction = $order->get_transaction_id() 
				? $this->retrieve_order_transaction( $order )
				: $this->make_request_create_transaction( $order );

			if ( isset( $transaction->error ) ) {
				$localized_message = __( 
					sprintf( 'Your transaction could not be created on Pace. Please contact our team at support@pacenow.co. %s', $transaction->correlation_id ), 
					'woocommerce-pace-gateway' 
				);

				throw new Exception( $localized_message );
			}
			
			/**
			 * Update the order status when transaction is cancelled or expired
			 * based on merchant's setting on Dashboard
			 * 
			 * @throws String
			 */
			if ( 
				isset( $transaction->status ) && 
				( $transaction->status === 'cancelled' || $transaction->status === 'expired' ) 
			) {
				$order_status = $transaction->status === 'cancelled' ? $this->order_status_failed : $this->order_status_expire;
				$order->update_status( $order_status, $note = __( 'Having trouble creating a Pace transactions.', 'woocommerce-pace-gateway' ) );
				unset( WC()->session->order_awaiting_payment );

				throw new Exception( __( 'There is a problem paying with Pace. Please try checking again later or try another payment source.', 'woocommerce-pace-gateway' ) );
			}

			$order->set_transaction_id( $transaction->transactionID );
			$order->add_meta_data( 'pace_transaction', json_encode( $transaction ), $unique = true );
			$order->save();
			
			wp_send_json_success( $transaction );
		} catch ( Exception $e ) {
			WC_Pace_Logger::log( $e->getMessage() );
			wc_add_notice( $e->getMessage(), 'error' );
			wp_send_json_error( array( 'message' => wc_print_notices( true ) ) );
		}
	}

	/**
	 * Pacenow create the transaction by checkout Order
	 * 
	 * @param  WC_Order $order Woocommerce order
	 * @return array           Pacenow API response
	 */
	public static function cancel_transaction($order)
	{
		$transaction_id = apply_filters( 'woocommerce_pace_cancelled_payment_order_transaction', $order->get_transaction_id(), $order );
		$api = sprintf( 'checkouts/%s/cancel', $transaction_id );
		$response = WC_Pace_API::request(array(), $api);
		
		return $response;
	}

	/**
	 * Pace update order status when redirect payment
	 * 		
	 * @param  WC_Order $order_id
	 * @since 1.0.0
	 */
	public function pace_redirect_payment_update_order_status( $order_id ) {
		$order = wc_get_order( $order_id );

		if ( is_wp_error( $order ) ) {
			return;
		}

		if ( 'redirect' === $this->checkout_mode and 'pending' === $order->get_status() ) {
			// process order after rediect payment
			$transaction = array(
				'status' => 'success',
				'transactionId' => '' /* already set transaction ID */
			);

			$this->process_response( $transaction, $order );
		}
	}
}
