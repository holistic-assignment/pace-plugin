<?php
if ( ! defined( 'ABSPATH' ) ) {
	return;
}

/**
 * WC_Pacenow_Request_Payment class
 */
class WC_Pace_Request_Payment {
	/**
	 * Initialize class actions.
	 */
	public function __construct() {
		$this->init();
	}

	public function init() {
		add_action( 'wc_ajax_wc_pace_create_transaction', array( $this, 'woocommerce_pace_create_transaction' ) );
		add_action( 'wc_ajax_wc_pace_cancelled_order', array( $this, 'woocommerce_pace_cancelled_order' ) );
	}

	/**
	 * Woocommerce checkout create Pacenow transaction
	 * 
	 * @return array Pacenow transaction data
	 */
	public function woocommerce_pace_create_transaction() {
		try {
			$_nonce = wc_get_var( $_POST['security'] );
			// check nonce
			if ( ! wp_verify_nonce( $_nonce, '_wc_pace_nonce' ) ) {
				wc_add_notice( __( 'We were unable to process your order, please try again.', 'woocommerce-pace-gateway' ), 'error' );
				throw new Exception( wc_print_notices( true ) );
			}

			// create order from the posted data
			do_action( 'woocommerce_pace_before_create_transaction' );

			// trigger process_checkout but just only validation the posted data
			WC()->checkout->process_checkout();

		} catch ( Exception $e ) {
			WC_Pace_Logger::log( 'Error: ' . $e->getMessage() );
			wp_send_json_error( array( 'message' => $e->getMessage() ) );
		}
	}

	/**
	 * Woocommerce cancelled order
	 * 
	 * @return array|url
	 */
	public function woocommerce_pace_cancelled_order() {
		try {
			$order_id = WC()->session->get( 'order_awaiting_payment' );
			if ( ! $order_id ) {
				throw new Exception( __( 'Cannot find order Id or transaction Id.', 'woocommerce-pace-gateway' ) );
			}

			$order = wc_get_order( $order_id );

			if ( is_wp_error( $order ) ) {
				throw new Exception( 'woocommerce_api_cannot_create_order', sprintf( __( 'Cannot create order: %s', 'woocommerce-pace-gateway' ), implode( ', ', $order->get_error_messages() ) ), 400 );
			}

			$cancelled_url = apply_filters( 'woocommerce_pace_cancelled_order_redirect', $order->get_cancel_order_url_raw(), $order );
			
			// WC()->session->set( 'order_awaiting_payment', false );

			do_action( 'woocommerce_cancelled_order', $order->get_id() );

			wp_send_json_success( array( 'redirect' => $cancelled_url ) );

		} catch ( Exception $e ) {
			WC_Pace_Logger::log( $e->getMessage() );
			wp_send_json_error( array( 'message' => $e->getMessage() ) );
		}
	}
}

new WC_Pace_Request_Payment();