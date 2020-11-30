<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Woocommerce Pacenow Gateway
 */
class WC_Pace_API {
	
	/**
	 * Endpoint API
	 */
	private static $endpoint = 'https://api-playground.pacenow.co';
	const API_VER = '/v1/';
	const VERSION  = '1.0.0';

	/**
	 * Pacenow Gateway settings
	 * 
	 * @var array
	 */
	private static $options = array();

	/**
	 * Pacenow Client ID
	 * 
	 * @var string
	 */
	private static $client_id = '';

	/**
	 * Pacenow Client Secret
	 * 
	 * @var string
	 */
	private static $client_secret = '';

	/**
	 * Set Client ID
	 */
	private static function set_client_id( $client_id ) {
		self::$client_id = $client_id;
	}

	/**
	 * Set Client Secret
	 */
	private static function set_client_secret( $client_secret ) {
		self::$client_secret = $client_secret;
	}

	/**
	 * Get Client ID
	 * 
	 * @return string Pacenow's client Id
	 */
	private static function get_client_id() {
		$options = self::$options;

		$client_id = ! self::$client_id
			? 'yes' === $options['sandBox'] ? $options['sandbox_client_id'] : $options['client_id']
			: self::$client_id;

		self::set_client_id( $client_id );

		return self::$client_id;
	}

	/**
	 * Get Client Secret
	 * 
	 * @return string Pacenow's client secret
	 */
	private static function get_client_secret() {
		$options = self::$options;

		$client_secret = ! self::$client_secret
			? 'yes' === $options['sandBox'] ? $options['sandbox_client_secret'] : $options['client_secret']
			: self::$client_secret;

		self::set_client_secret( $client_secret );

		return self::$client_secret;
	}

	/**
	 * Get Pacenow API request
	 * 
	 * @param  self:$endpoint default: https://api-playground.pacenow.co
	 * @return string api request
	 */
	private static function get_api_request() {
		$options = self::$options;

		self::$endpoint = 'yes' === $options['sandBox'] ? 'https://api-playground.pacenow.co' : 'https://api.pacenow.co';

		return self::$endpoint;
	}

	/**
	 * Generates the user agent we use to pass to API request so
	 * 
	 * @return array user agent
	 */
	public static function get_user_agent() {
		$agent = array(
			'version' => self::VERSION,
			'name'    => 'Woocommerce Pace Gateway',
			'url' 	  => 'https://pacenow.co/'
		);

		return array(
			'lang'         => 'php',
			'uname'        => php_uname(),
			'publisher'    => 'woocommerce',
			'application'  => $agent,
			'lang_version' => phpversion(),
		);
	}

	/**
	 * Send the request to Pacenow API
	 * 
	 * @param  array|object   $request
	 * @param  string 		  $api     API endpoint
	 * @param  string 		  $method  
	 * @return stdClass|array
	 * @throws Exception 
	 */
	public static function request( $request, $api, $method = 'POST' ) {
		// set options to config api
		self::$options = get_option( 'woocommerce_pace_settings' );
		$curl = curl_init();
		curl_setopt_array( $curl, array(
		  	CURLOPT_URL => self::get_api_request() . self::API_VER . $api,
		  	CURLOPT_RETURNTRANSFER => true,
		  	CURLOPT_ENCODING => '',
		  	CURLOPT_MAXREDIRS => 10,
		  	CURLOPT_TIMEOUT => 30,
		  	CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
		  	CURLOPT_CUSTOMREQUEST => $method,
		  	CURLOPT_POSTFIELDS => json_encode( $request ),
		  	CURLOPT_HTTPHEADER => array(
		  		'user-agent: ' . $_SERVER['HTTP_USER_AGENT'],
		  		'content-type: application/json',
		    	'authorization: Basic ' . base64_encode( self::get_client_id() . ':' . self::get_client_secret() ),
		    	'cache-control: no-cache',
		    	'pace-version: ' . self::VERSION,
		    	'x-pace-platformversion: ' . sprintf( '%s-%s, %s, %s', WC_PACE_GATEWAY_NAME, 'WooCommerce', WC_PACE_GATEWAY_VERSION, WC_VERSION )
		  	),
		));

		$response = curl_exec( $curl );
		$err = curl_error( $curl );
		
		curl_close( $curl );

		if ( $err ) {
			WC_Pace_Logger::log(
				'Error Response: ' . print_r( $err, true ) . PHP_EOL . PHP_EOL . 'Failed request: ' . print_r(
					array(
						'api'     => $api,
						'request' => json_encode( $request ),
					),
					true
				)
			);

			throw new Exception( __( 'There was a problem connecting to the Pace API', 'woocommerce-pace-gateway' ) );
		}

		return json_decode( apply_filters( 'woocommerce_pace_api_response', $response, $request ) );
	}
}