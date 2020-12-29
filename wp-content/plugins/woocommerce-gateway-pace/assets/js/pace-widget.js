(function ($) {
	var pace_widget = {
		init: function() {
			if ( $( '#single-widget' ).length ) {
				setTimeout( this.show_single_widget , 2000 );
			}

			if ( $( '#multiple-widget').length ) {
				setTimeout( this.show_multiple_widget , 2000 );
			}

			// woocommerce checkout update order trigger
			$( document.body ).on( 'updated_checkout', this.show_checkout_widget );
			
			// pace show checkout widgets
			if ( $( document.body ).find( '#pace-pay-widget-container' ).length ) {
				// onload checkout page
				if ( pace_widget.isChosen() ) {
					// waiting pace initialization
					setTimeout( this.show_checkout_widget , 3000 );
				} else {
					$( document.body ).find( '#payment_method_pace' ).on( 'change', this.show_checkout_widget );
				}
			}
		},
		/**
		 * Show pace single widget
		 *
		 * @version 1.0.0
		 */
		show_single_widget: function() {
			var data = $("#single-widget").data();      
			pacePay.loadWidgets({
				containerSelector: "#single-widget",
				type: "single-product",
				styles: {
					logoTheme: data.themeColor ? data.themeColor :  "light",
					textPrimaryColor: data.singlePrimaryColor ? data.singlePrimaryColor : "black",
					textSecondaryColor: data.singleSecondColor ? data.singleSecondColor : "#74705e",
					fontSize: ( data.fontsize ? data.fontsize : 13 ) + 'px'
				}
			});
		},

		/**
		 * Show pace multiple widget
		 *
		 * @version 1.0.0
		 */
		show_multiple_widget: function() {
			var data = $("#multiple-widget").data();
			pacePay.loadWidgets({
				containerSelector: "#multiple-widget",
				type: "multi-products",
				styles: {
					logoTheme: data.themeColor ? data.themeColor :  "light",
					textColor: data.textColor ? data.textColor : "",
					fontSize: ( data.fontsize ? data.fontsize : 13 ) + 'px'
				}
			});
		},		

		/**
		 * Show pace checkout widget
		 *
		 * @version 1.0.0
		 */
		show_checkout_widget: function() {
			var data = $('#pace-pay-widget-container').data();

			if ( data === undefined ) {
				return;
			}

      		if( data.enabled === "yes" ) {
				pacePay.loadWidgets( {
					containerSelector: '#pace-pay-widget-container',
					type: 'checkout',
					styles: {
						textPrimaryColor: data.checkoutPrimaryColor,
						textSecondaryColor: data.checkoutSecondColor,
						timelineColor: data.timelineColor,
						backgroundColor: data.checkoutBackgroundColor,
						foregroundColor: data.checkoutForegroundColor,
						fontSize: (data.fontsize ? data.fontsize : 13) + 'px'
					}
				} );
			}
		},

		isChosen: function() {
			return $( document.body ).find( '#payment_method_pace' ).is( ':checked' );
		},
	};
	pace_widget.init();
})(jQuery);