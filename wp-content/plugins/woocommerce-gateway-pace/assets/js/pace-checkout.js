(function ($) {
	var wc_pace_gateway = {
		timer: false,
		form_checkout: $( document ).find( 'form.checkout' ) || $( document ).find( 'form.woocommerce-checkout' ),
		init: function() {
			// set checkout form element
			if ( $( 'form.woocommerce-checkout' ).length ) {
				this.form = $( 'form.woocommerce-checkout' );
			}

			$( document.body ).on( 'pace_transaction_error', this.onError ); /* handle the request response with error */
			$( 'form.woocommerce-checkout' ).on( 'checkout_place_order_pace', this.transaction );
		},

		/**
		 * Get WC AJAX endpoint URL.
		 *
		 * @param  {String} endpoint Endpoint.
		 * @return {String}
		 */
		getAjaxURL: function( endpoint ) {
			return wc_pace_params.ajaxurl
				.toString()
				.replace( '%%endpoint%%', 'wc_pace_' + endpoint );
		},

		/**
		 * pace API - Create Transaction
		 * 
		 * @return {array}
		 */
		create_transaction: function() {
			// prepare data from the checkout form
			var ajax_data = [],
				method = 'post',
				form_checkout_data = wc_pace_gateway.form.serializeArray();
				
			Object.keys( form_checkout_data ).map( function( i ) {
				ajax_data[ form_checkout_data[i].name ] = $.trim( form_checkout_data[i].value );
			} );
			ajax_data['security'] = wc_pace_params['pace_nonce']; /* setup nonce data */
			// convert to object
			ajax_data = Object.assign( {}, ajax_data );
			ajax_data['payment_method'] = 'pace'
			$.ajax( {
				url:  wc_pace_gateway.getAjaxURL( 'create_transaction' ),
				type: method,
				data: ajax_data,
				success: function( res ) {
					if ( ! res.success || res.result == 'failure' ) {
						$( document.body ).trigger( 'pace_transaction_error', res );
					} else {
						switch ( wc_pace_params['checkout_mode'] ) {
							case 'popup':
								wc_pace_gateway.showPopup( res );
								break;
							case 'redirect':
								window.location.href = res.data.paymentLink;
								break;
							default:
								console.log( 'Awaiting for Pace transaction' );
						}
					}
				}
			} );
		},

		transaction: function() {
			$('#place_order').prop('disabled', true);

			if ( ! wc_pace_gateway.isChosen() ) {
				return true;
			}

			// if a transaction is already in place, just submit form
			if ( wc_pace_gateway.hasTransaction() ) {
				return true;
			}

			// lock elements
			wc_pace_gateway.block();
			wc_pace_gateway.create_transaction();

			return false;
		},

		/**
		 * Show pace transaction confirm popup
		 * 
		 * @param  {Object} res pace API - Create Transaction response
		 */
		showPopup: function( res ) {
			var token = res.data.paymentLink.replace( /.*txn=/gm, '' );
			// loader popup
			pacePay.showProgressModal();
			pacePay.popup( {
				txnToken: token,
				onSuccess: function( data ) {
					wc_pace_gateway.transaction_completed( data );
				},
				onCancel: function() {
					wc_pace_gateway.transaction_cancelled( res );
				},
				onLoad: function() {
					pacePay.hideProgressModal();
				}
			} );
		},

		/**
		 * Append transaction source to checkout form
		 * 
		 * @param  {object} transaction pace's API response
		 */
		preprareSource: function( transaction ) {
			// clear transaction source if it's already exist
			wc_pace_gateway.reset();

			var inputPaceSource = $( '<input type="hidden" />' ).addClass( 'pace-transaction' ).attr( 'name', 'pace_transaction' ).val( JSON.stringify( transaction ) );
			wc_pace_gateway.form.append( inputPaceSource );

			if ( $( 'form#add_payment_method' ).length ) {
				$( wc_pace_gateway.form ).off( 'submit', wc_pace_gateway.form.onSubmit );
			}
			
			wc_pace_gateway.form.submit();
		},

		/**
		 * Handle after transaction was completed
		 * @param {object} data 
		 */
		transaction_completed: function( data ) {
			// add status to response
			data['status'] = 'success';
			// handle transaction response
			wc_pace_gateway.preprareSource( data );
			
			$( document.body ).trigger( 'wc_pace_action_after_create_transaction', [ data ] );
		},

		/**
		 * Handle after transaction was cancelled
		 * @param  {res} token create transaction response
		 */
		transaction_cancelled: function( res ) {
			var method = 'POST';
			$.ajax( {
				url:  wc_pace_gateway.getAjaxURL( 'cancelled_order' ),
				type: method,
				data: res,
				success: function( res ) {
					if ( ! res.success ) {
						$( document.body ).trigger( 'pace_transaction_error', res );
					}

					var redirect = res.data.redirect;
					// show confirm transaction popup
					if ( -1 === redirect.indexOf( 'https://' ) || -1 === redirect.indexOf( 'http://' ) ) {
						window.location = redirect;
					} else {
						window.location = decodeURI( redirect );
					}
				}
			} );
		},

		isChosen: function() {
			return $( '#payment_method_pace' ).is( ':checked' );
		},

		hasTransaction: function() {
			return $( document.body ).find( 'input.pace-transaction' ).length > 0;
		},

		/**
		 * Check whether a mobile device is being used.
		 *
		 * @return {boolean}
		 */
		isMobile: function() {
			if( /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test( navigator.userAgent ) ) {
				return true;
			}

			return false;
		},

		block: function() {
			if ( ! wc_pace_gateway.isMobile() ) {
				wc_pace_gateway.form.block( {
					message: null,
					overlayCSS: {
						background: '#fff',
						opacity: 0.6
					}
				} );
			}
		},

		unBlock: function() {
			wc_pace_gateway.form && wc_pace_gateway.form.unblock();
		},

		/**
		 * Handle DOM element when get error
		 * 
		 * @param {Object} res Response result
		 */
		onError: function( event, res ) {
			var message = res.data ? res.data.message : res.messages;

			if ( message ) {

				var	error_element = $( '<div class="woocommerce-NoticeGroup woocommerce-NoticeGroup-checkout"></div>' ).html( $( message ).addClass( 'woocommerce-error wc-pace-error' ) );
				$( '.woocommerce-NoticeGroup-checkout, .woocommerce-error, .woocommerce-message' ).remove();
				wc_pace_gateway.form.prepend( error_element ); // eslint-disable-line max-len

				if ( $( '.wc-pace-error' ).length ) {
					$( 'html, body' ).animate({
						scrollTop: ( $( '.wc-pace-error' ).offset().top - 200 )
					}, 200 );
				}
			}

			wc_pace_gateway.unBlock();
		},

		// reset transaction source
		reset: function() {
			$( document.body ).find( '.pace-transaction' ).remove();
		},
	};

	wc_pace_gateway.init();
})(jQuery);