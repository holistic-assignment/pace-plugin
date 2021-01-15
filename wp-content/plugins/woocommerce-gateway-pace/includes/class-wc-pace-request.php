<?php
if ( ! defined( 'ABSPATH' ) ) {
	return;
}

/**
 * WC_Pacenow_Request_Payment class
 */
class WC_Pace_Request_Payment extends WC_Checkout {
	/**
	 * Initialize class actions.
	 */
	public function __construct() {
		$this->init();
	}

	public function init() {
		add_action( 'wc_ajax_wc_pace_create_transaction', array( $this, 'woocommerce_pace_create_transaction' ) );
		add_action( 'wc_ajax_wc_pace_cancelled_order', array( $this, 'woocommerce_pace_cancelled_order_popup' ) );
	}

	/**
	 * Woocommerce checkout create Pacenow transaction
	 * 
	 * @return array Pacenow transaction data
	 */
	public function woocommerce_pace_create_transaction() {
		try {
			$_nonce = wp_unslash( wc_get_var( $_POST['security'] ) ); // phpcs:ignore 
			// check nonce
			if ( ! wp_verify_nonce( $_nonce, '_wc_pace_nonce' ) ) {
				wc_add_notice( __( 'We were unable to process your order, please try again.', 'woocommerce-pace-gateway' ), 'error' );
				
				throw new Exception( wc_print_notices( true ) );
			}

			do_action( 'woocommerce_pace_before_create_transaction' );

			// validate the posted data and create order
			// after created order, make the request call to Pace's API to create transaction
			$errors = new WP_Error();
			$posted_data = $this->get_posted_data();
			
			// Validate posted data and cart items before proceeding.
			$this->validate_checkout( $posted_data, $errors );

			foreach ( $errors->errors as $code => $messages ) {
				$data = $errors->get_error_data( $code );
				foreach ( $messages as $message ) {
					wc_add_notice( $message, 'error', $data );
				}
			}

			if ( 0 !== wc_notice_count( 'error' ) ) {
				throw new Exception( wc_print_notices( $return = true ) );
			}

			// creat hooks to create the transaction
			do_action( 'woocommerce_create_transaction_before_checkout', $posted_data );
		} catch ( Exception $e ) {
			WC_Pace_Logger::log( 'Error: ' . $e->getMessage() );
			wp_send_json_error( array( 'message' => $e->getMessage() ) );
		}
	}

	/**
	 * Canceled the order with Pace checkout popup mode
	 *
	 * @since 1.1.4
	 * @return json
	 */
	public function woocommerce_pace_cancelled_order_popup() {
		try {
			$order_id = wp_unslash( $_POST['data']['referenceID'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

			if ( ! $order_id ) {
				throw new Exception( __( 'Cannot find order Id or transaction Id.', 'woocommerce-pace-gateway' ) );
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

			/**
			 * Update: call API to cancel transaction before update wc order
			 * @since 1.1.1
			 */
			$doCancel = WC_Pace_Gateway_Payment::cancel_transaction( $order );

			if ( isset( $doCancel->error ) ) {
				$error_message = $doCancel->error->message ? $doCancel->error->message : 'There was an error canceling the transaction.';
				$localized_message = __( sprintf( '%s %s', $error_message, $doCancel->correlation_id ), 'woocommerce-pace-gateway' );
				throw new Exception( $localized_message );
			}

			// add hook before creating URI cancel
			do_action( 'woocommerce_cancelled_order', $order->get_id() );

			$redirect_cancel_uri = WC_Pace_Helper::pace_http_build_query( 
				$order->get_cancel_order_url_raw(), 
				array(
					'merchantReferenceId' => wp_unslash( $_POST['data']['referenceID'] ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				) 
			);

			wp_send_json_success( array( 
				'redirect' => $redirect_cancel_uri 
			) );

		} catch ( Exception $e ) {
			WC_Pace_Logger::log( $e->getMessage() );
			wp_send_json_error( array( 'message' => $e->getMessage() ) );
		}
	}
}

new WC_Pace_Request_Payment();