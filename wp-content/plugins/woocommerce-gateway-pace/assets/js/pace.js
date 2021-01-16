(function ($) {
	// init pace loader
	function initPayPace () {
		window.pacePayCallback = function( init ) {
		  	var pacePay = init({
				fallbackWidget: params.flag === 'yes' ? true : false, // show fallback widget if price is not within min and max
				debug: true,
				currency: !!params.currency ? params.currency : "SGD" ,
		    	onEvent: function(event) {
		      		var type = event.type,
  						payload = event.payload;

			      	switch (type) {
			        	case "INIT_FAILURE": {
			          		var error = payload.error;
			          		break;
			        	}
			        	case "WIDGET_CLICK_EVENT": {
			          		var widgetEventType = payload.widgetEventType;

			          		if (widgetEventType === "SINGLE_PRODUCT_MORE_INFO_OPEN") {
			            		// Hook up wiht google analytics
					            ga("send", {
					              	hitType: "event",
					              	eventCategory: "PaceWidget",
					              	eventAction: "click",
					              	eventLabel: "Single Product Click"
					            });
			          		}
			         	 	break;
			        	}
			        	default:
			          		break;
			      	}
			    },
			    styles: {
			      	fontFamily: "Roboto",
			      	primaryTextColor: "red",
			      	secondaryTextColor: "#FFFFFF",
			      	secondaryColor: "green"
			    }
			});
		};
	}

	initPayPace();
})(jQuery);