<?php
if ( ! defined( 'ABSPATH' ) ) {
	return;
}

// phpcs:disable WordPress.Files.FileName

/**
 * Abstract class that will be inherited by all payment methods.
 *
 * @extends WC_Payment_Gateway_CC
 *
 * @since 1.0.0
 */
abstract class Abstract_WC_Pace_Payment_Gateway extends WC_Payment_Gateway_CC {
	/**
	 * Convert price to unit cents
	 * 
	 * @param  float|int|string $price Product price
	 * @return int        			   Product price (cents)
	 */
	public static function unit_cents( $price ) {
		$clean_price = wp_kses_post( $price );
		$cents = absint( bcmul( $price, 100 ) );

		return apply_filters( 'woocommerce_pace_convert_unitcents', $cents );
	}

	/**
	 * All payment icons that work with Customizer. Some icons references
	 * WC core icons.
	 *
	 * @since 1.0.0
	 * @since 1.0.0 Changed to using img with svg (colored) instead of fonts.
	 * @return array
	 */
	public function payment_icons() {
		return apply_filters( 'woocommerce_pace_payment_icons', array() );
	}

	/**
	 * Check payment methods is availabel before use
	 * 
	 * @return boolean
	 */
	public function is_available() {
		$this->is_available();
	}

	/**
	 * Get order items soruce - Pacenow API: Create Transaction
	 * 
	 * @param  WC_Order::get_items $items list item assign by order
	 * @return array 		   			  list source items
	 */
	public function get_source_order_items( $items , $order ) {
		$source = array();
		array_walk( $items , function( $item, $id ) use ( &$source, $order ) {
			// get WC_Product item by ID
			$product = $item->get_product();
			$source_item = array(
				'itemID' 		 => wp_kses_post( absint( $id ) ),
				'itemType'		 => wp_kses_post( wp_strip_all_tags( $product->get_categories(), true ) ), /* remove html tags, just only get categories name */
				'reference' 	 => wp_kses_post( absint( $id ) ),
				'name' 			 => wp_kses_post( apply_filters( 'woocommerce_order_item_name', $item->get_name(), $item, false ) ),
				'productUrl' 	 => wp_kses_post( apply_filters( 'woocommerce_order_item_permalink', $product->get_permalink(), $item, $order ) ),
				'imageUrl' 		 => wp_kses_post( apply_filters( 'woocommerce_order_item_thumbnail', get_the_post_thumbnail_url( $product->get_id(), 'large' ) ) ),
				'quantity' 		 => apply_filters( 'woocommerce_order_item_quantity', $item->get_quantity(), $item ),
				'tags'			 => apply_filters( 'woocommerce_pace_transaction_items_tag', explode( ', ' , wp_strip_all_tags( $product->get_tags() ) ) ), /* remove html tags, get only tags and explode to array */
				'unitPriceCents' => self::unit_cents( $item->get_total() )
			);
			array_push( $source, apply_filters( 'woocommerce_pace_prepare_item', $source_item ) );
		} );

		return $source;
	}

	/**
	 * Prepare data for API
	 * 
	 * @param  WC_Order $order order instance
	 * @return array           API data source
	 */
	public function prepare_order_source( $order = null ) {
		if ( ! $order ) {
			throw new Exception( __( "Missing the checkout's order.", 'woocommerce-pace-gateway' ) );
		}
		
		// success url
		$success_url = wc_get_endpoint_url( 'order-received', $order->get_id(), wc_get_checkout_url() );
		$success_url = add_query_arg( 'key', $order->get_order_key(), $success_url );

		return array(
			'items'		   => $this->get_source_order_items( $order->get_items(), $order ),
			'amount'	   => self::unit_cents( $order->get_total() ),
			'currency'     => $order->get_currency(),
			'referenceID'  => wp_kses_post( $order->get_id() ),
			'redirectUrls' => array(
				'success' => apply_filters( 'woocommerce_get_checkout_order_received_url', $success_url ),
				'failed'  => esc_url_raw( WC_Pace_Helper::pace_http_build_query( $order->get_cancel_order_url_raw() ) )
			)
		);
	}

	/**
	 * Store order extra meta by from a transaction
	 * 
	 * @param  Array 	$transaction Pacenow API response
	 * @param  WC_Order $order       Checkout's order
	 */
	public function process_response( $transaction, $order ) {
		$order_id = $order->get_id();

		if ( ! isset( $transaction['status'] ) ) {
			throw new Exception( __( 'Unknow transaction source status.', 'woocommerce-pace-gateway' ) );
		}

		$transaction_id = empty( $transaction['transactionId'] ) ? $order->get_transaction_id() : $transaction['transactionId'];

		/**
		 * Update the order status based on Pace transaction status
		 * Default status is pending_confirmation
		 * 
		 * @since 1.0.5
		 */
		switch ( $transaction['status'] ) {
			case 'pending_confirmation':
				$order_stock_reduced = $order->get_meta( '_order_stock_reduced', true );

				if ( ! $order_stock_reduced ) {
					wc_reduce_stock_levels( $order_id );
				}

				$order->update_status( 'on-hold', sprintf( __( "Pace's transaction awaiting payment: %s.", 'woocommerce-pace-gateway' ), $transaction_id ) );
				break;
			case 'approved':
				$message = sprintf( __( 'Pace payment is completed (Reference ID: %s)', 'woocommerce-pace-gateway' ), $transaction_id );
				$order->payment_complete();
				$order->add_order_note( $message );
				break;
			case 'cancelled':
			case 'expired':
				$localized_message = __( "The transaction has {$transaction['status']}. Please try your payment again or contact the admin.", 'woocommerce-pace-gateway' );
				$order->add_order_note( $localized_message );
				throw new Exception( $localized_message );
				break;
			default:
				# code...
				break;
		}

		// clear order id
		unset( WC()->session->order_awaiting_payment );

		if ( is_callable( array( $order, 'save' ) ) ) {
			$order->save();
		}

		do_action( 'woocommerce_pace_after_process_response', $transaction, $order );

		return $transaction;
	}
}