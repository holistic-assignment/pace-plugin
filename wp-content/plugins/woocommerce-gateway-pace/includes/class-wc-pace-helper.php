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
			return get_option( 'woocommerce_currency' );
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
		$request = 'checkouts/plans';
		// make a call request to API
		$response = WC_Pace_API::request( array(), $request, $method = 'GET' );

		if ( ! isset( $response->list ) or empty( $response->list ) ) {
			throw new Exception( __( 'Cannot validate the merchant\'s plans. Please contact to the administrator.', 'woocommerce-pace-gateway' ) );
		}

		// currently, we just only validate the first plan of the list
		return apply_filters( 'woocommerce_pace_validate_merchant_plans', array_shift( $response->list ), $response );
	}

	/**
	 * Validate order before create Pacenow's transaction
	 * @param  WC_Order $order checkout's order
	 * @throws string 		   errors message
	 */
	public static function validate_create_transaction( $order ) {
		$plan = self::get_merchant_plan();
		// check the amount range that the plan allows
		$order_amount = Abstract_WC_Pace_Payment_Gateway::unit_cents( $order->get_total() );

		if ( $order_amount < $plan->minAmount->value or $order_amount > $plan->maxAmount->value ) {
			$range_amount = $plan->currencyCode .' $'. $plan->minAmount->actualValue . ' - ' . $plan->currencyCode .' $'. $plan->maxAmount->actualValue;
			$localized_message = sprintf( '%s: %s', __( 'The price of the order is out of price range allows', 'woocommerce-pace-gateway' ), wp_kses_post( $range_amount ) );
			wc_add_notice( $localized_message, 'error' );
			throw new Exception( wc_print_notices( true ) );
		}

		// check valid currency
		if ( strtoupper( $order->get_currency() ) !== $plan->currencyCode ) {
			$localized_message = sprintf( '%s (%s).', __( 'Currently, Pace do not support your currency', 'woocommerce-pace-gateway' ), strtoupper( $order->get_currency() ) );
			wc_add_notice( $localized_message, 'error' );
			throw new Exception( wc_print_notices( true ) );
		}
	}

	/**
	 * Blocked the plugins
	 *
	 * @param string $currency is currency need checked
	 * @since 1.1.0
	 * @version 1.0.0
	 * @return boolean
	 */
	public static function is_block( $currency = '' ) {
		if ( is_admin() ) {
			return true; /* only pages on front */
		}

		if ( !$currency ) {
			$plugin_allows_currency = self::get_merchant_plan();
			$currency = isset( $plugin_allows_currency ) ? $plugin_allows_currency->currencyCode : null;
		}
		
		if ( is_null( $currency ) ) {
			return;
		}

		return strtoupper( get_option('woocommerce_currency') ) === $currency;
	}
}