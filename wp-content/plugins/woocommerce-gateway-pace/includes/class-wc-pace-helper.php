<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides static methods as helpers.
 *
 * @version 1.0.0
 * @since 1.0.0
 */
class WC_Pace_Helper
{
	/**
	 * The Pacenow's availabel plans based on merchant ID
	 * 
	 * @var array
	 */
	public $available_plans = array();

	function __construct()
	{
		# code...
	}

	/**
	 * Get currency code regarding by country
	 *
	 * @param  string $country country code
	 * @since 1.1.0 
	 * @version 1.0.0
	 * @return string|null     currency code
	 */
	public static function get_currency_by_country( $country ) {

		if ( empty( $country ) ) {
			return get_woocommerce_currency();
		}

		$currency = apply_filters( 'woocommerce_currency_code', array('AF' => 'AFN', 'AL' => 'ALL', 'DZ' => 'DZD', 'AS' => 'USD', 'AD' => 'EUR', 'AO' => 'AOA', 'AI' => 'XCD', 'AQ' => 'XCD', 'AG' => 'XCD', 'AR' => 'ARS', 'AM' => 'AMD', 'AW' => 'AWG', 'AU' => 'AUD', 'AT' => 'EUR', 'AZ' => 'AZN', 'BS' => 'BSD', 'BH' => 'BHD', 'BD' => 'BDT', 'BB' => 'BBD', 'BY' => 'BYR', 'BE' => 'EUR', 'BZ' => 'BZD', 'BJ' => 'XOF', 'BM' => 'BMD', 'BT' => 'BTN', 'BO' => 'BOB', 'BA' => 'BAM', 'BW' => 'BWP', 'BV' => 'NOK', 'BR' => 'BRL', 'IO' => 'USD', 'BN' => 'BND', 'BG' => 'BGN', 'BF' => 'XOF', 'BI' => 'BIF', 'KH' => 'KHR', 'CM' => 'XAF', 'CA' => 'CAD', 'CV' => 'CVE', 'KY' => 'KYD', 'CF' => 'XAF', 'TD' => 'XAF', 'CL' => 'CLP', 'CN' => 'CNY', 'HK' => 'HKD', 'CX' => 'AUD', 'CC' => 'AUD', 'CO' => 'COP', 'KM' => 'KMF', 'CG' => 'XAF', 'CD' => 'CDF', 'CK' => 'NZD', 'CR' => 'CRC', 'HR' => 'HRK', 'CU' => 'CUP', 'CY' => 'EUR', 'CZ' => 'CZK', 'DK' => 'DKK', 'DJ' => 'DJF', 'DM' => 'XCD', 'DO' => 'DOP', 'EC' => 'ECS', 'EG' => 'EGP', 'SV' => 'SVC', 'GQ' => 'XAF', 'ER' => 'ERN', 'EE' => 'EUR', 'ET' => 'ETB', 'FK' => 'FKP', 'FO' => 'DKK', 'FJ' => 'FJD', 'FI' => 'EUR', 'FR' => 'EUR', 'GF' => 'EUR', 'TF' => 'EUR', 'GA' => 'XAF', 'GM' => 'GMD', 'GE' => 'GEL', 'DE' => 'EUR', 'GH' => 'GHS', 'GI' => 'GIP', 'GR' => 'EUR', 'GL' => 'DKK', 'GD' => 'XCD', 'GP' => 'EUR', 'GU' => 'USD', 'GT' => 'QTQ', 'GG' => 'GGP', 'GN' => 'GNF', 'GW' => 'GWP', 'GY' => 'GYD', 'HT' => 'HTG', 'HM' => 'AUD', 'HN' => 'HNL', 'HU' => 'HUF', 'IS' => 'ISK', 'IN' => 'INR', 'ID' => 'IDR', 'IR' => 'IRR', 'IQ' => 'IQD', 'IE' => 'EUR', 'IM' => 'GBP', 'IL' => 'ILS', 'IT' => 'EUR', 'JM' => 'JMD', 'JP' => 'JPY', 'JE' => 'GBP', 'JO' => 'JOD', 'KZ' => 'KZT', 'KE' => 'KES', 'KI' => 'AUD', 'KP' => 'KPW', 'KR' => 'KRW', 'KW' => 'KWD', 'KG' => 'KGS', 'LA' => 'LAK', 'LV' => 'EUR', 'LB' => 'LBP', 'LS' => 'LSL', 'LR' => 'LRD', 'LY' => 'LYD', 'LI' => 'CHF', 'LT' => 'EUR', 'LU' => 'EUR', 'MK' => 'MKD', 'MG' => 'MGF', 'MW' => 'MWK', 'MY' => 'MYR', 'MV' => 'MVR', 'ML' => 'XOF', 'MT' => 'EUR', 'MH' => 'USD', 'MQ' => 'EUR', 'MR' => 'MRO', 'MU' => 'MUR', 'YT' => 'EUR', 'MX' => 'MXN', 'FM' => 'USD', 'MD' => 'MDL', 'MC' => 'EUR', 'MN' => 'MNT', 'ME' => 'EUR', 'MS' => 'XCD', 'MA' => 'MAD', 'MZ' => 'MZN', 'MM' => 'MMK', 'NA' => 'NAD', 'NR' => 'AUD', 'NP' => 'NPR', 'NL' => 'EUR', 'AN' => 'ANG', 'NC' => 'XPF', 'NZ' => 'NZD', 'NI' => 'NIO', 'NE' => 'XOF', 'NG' => 'NGN', 'NU' => 'NZD', 'NF' => 'AUD', 'MP' => 'USD', 'NO' => 'NOK', 'OM' => 'OMR', 'PK' => 'PKR', 'PW' => 'USD', 'PA' => 'PAB', 'PG' => 'PGK', 'PY' => 'PYG', 'PE' => 'PEN', 'PH' => 'PHP', 'PN' => 'NZD', 'PL' => 'PLN', 'PT' => 'EUR', 'PR' => 'USD', 'QA' => 'QAR', 'RE' => 'EUR', 'RO' => 'RON', 'RU' => 'RUB', 'RW' => 'RWF', 'SH' => 'SHP', 'KN' => 'XCD', 'LC' => 'XCD', 'PM' => 'EUR', 'VC' => 'XCD', 'WS' => 'WST', 'SM' => 'EUR', 'ST' => 'STD', 'SA' => 'SAR', 'SN' => 'XOF', 'RS' => 'RSD', 'SC' => 'SCR', 'SL' => 'SLL', 'SG' => 'SGD', 'SK' => 'EUR', 'SI' => 'EUR', 'SB' => 'SBD', 'SO' => 'SOS', 'ZA' => 'ZAR', 'GS' => 'GBP', 'SS' => 'SSP', 'ES' => 'EUR', 'LK' => 'LKR', 'SD' => 'SDG', 'SR' => 'SRD', 'SJ' => 'NOK', 'SZ' => 'SZL', 'SE' => 'SEK', 'CH' => 'CHF', 'SY' => 'SYP', 'TW' => 'TWD', 'TJ' => 'TJS', 'TZ' => 'TZS', 'TH' => 'THB', 'TG' => 'XOF', 'TK' => 'NZD', 'TO' => 'TOP', 'TT' => 'TTD', 'TN' => 'TND', 'TR' => 'TRY', 'TM' => 'TMT', 'TC' => 'USD', 'TV' => 'AUD', 'UG' => 'UGX', 'UA' => 'UAH', 'AE' => 'AED', 'GB' => 'GBP', 'US' => 'USD', 'UM' => 'USD', 'UY' => 'UYU', 'UZ' => 'UZS', 'VU' => 'VUV', 'VE' => 'VEF', 'VN' => 'VND', 'VI' => 'USD', 'WF' => 'XPF', 'EH' => 'MAD', 'YE' => 'YER', 'ZM' => 'ZMW', 'ZW' => 'ZWD', ) );

		return isset( $currency[ $country ] ) ? $currency[ $country ] : null;
	}

	/**
	 * List available instalment plans supported by the merchant.
	 * 
	 * @return array availabel list plans
	 */
	public static function get_merchant_plan() {
		// make a call request to API
		$response = WC_Pace_API::request( array(), 'checkouts/plans', $method = 'GET' );

		if ( ! isset( $response->list ) or empty( $response->list ) ) {
			throw new Exception( __( "Can't validate the merchant plans. Please contact the administrator.", 'woocommerce-pace-gateway' ) );
		}

		// currently, we just only validate the first plan of the list
		return apply_filters( 'woocommerce_pace_validate_merchant_plans', array_shift( $response->list ), $response );
	}

	/**
	 * Blocked the plugins
	 *
	 * @param string $currency the customer currency
	 * @param string $country  the customer country
	 * @since 1.1.0
	 * @version 1.0.0
	 * @return boolean
	 */
	public static function is_block( $currency = '', $country = '' ) {
		try {
			// only applies on fronts page
			if ( is_admin() ) {
				return true;
			}
			
			$clientCurrency = empty( $currency ) ? get_woocommerce_currency() : $currency;
			
			if ( ! $currency || ! $country ) {
				throw new Exception("Not found the currency/country.", 404);
			}

			/**
			 * Add more availabel countries and currencies
			 * @var array
			 * @since 1.1.8
			 */
			$availabelCurrencies = array(
				'SG' => 'SGD',
				'MY' => 'MYR',
				'TH' => 'THB',
				'HK' => 'HKD',
			);

			if ( ! in_array( $clientCurrency, $availabelCurrencies ) ) {
				throw new Exception( "Pace doesn't support the client currency.", 405 );
			}

			if ( ! in_array( $country, array_keys( $availabelCurrencies ) ) ) {
				throw new Exception( "Pace doesn't support the client country.", 405 );
			}

			if ( isset( WC()->cart ) ) {
			 	WC()->cart->calculate_totals();
			 	$getPacePlan = self::get_merchant_plan();
				$getCartTotal = Abstract_WC_Pace_Payment_Gateway::unit_cents( WC()->cart->get_total( $context = 'float' ) );

				if ( $getCartTotal < $getPacePlan->minAmount->value OR $getCartTotal > $getPacePlan->maxAmount->value ) {
					throw new Exception( "The price of the order is out of price range allows", 405 );
				}
			} 

			return true;

		} catch (Exception $e) {
			WC_Pace_Logger::log('Check availabel countries and currencies: ' . $e->getMessage());
			return false;
		}
	}

	/**
	 * Filter woocommerce cancelled uri
	 * 
	 * @param  String 		$uri 
	 * @param  Array | Null $params
	 * @return String      
	 */
	public static function pace_http_build_query( $uri, $params = array() ) {
		$http_query = wp_parse_url( $uri );
		wp_parse_str( $http_query['query'], $http_query_params );
		unset( $http_query_params['_wpnonce'] );

		if ( $params ) {
			array_walk( $params, function( $value, $key ) use ( &$http_query_params ) {
				$http_query_params[ $key ] = $value;
			} );
		}

		return site_url() . $http_query['path'] . '?' . http_build_query( $http_query_params );
	}
}