<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Woocommerce Pacenow Payment Gateway Logger
 */
class WC_Pace_Logger {
	
	protected static $logger;
	const WC_LOG_FILENAME = 'woocommerce-gateway-pace';
	const VERSION = '1.1.12';

	public static function log( $message ) {
		if ( ! class_exists( 'WC_Logger' ) ) {
			return;
		}

		if ( empty( self::$logger ) ) {
			self::$logger = wc_get_logger();
		}

		$log_entry  = "\n" . '====Pace Version: ' . self::VERSION . '====' . "\n";
		$log_entry .= '====Start Log====' . "\n" . $message . "\n" . '====End Log====' . "\n\n";

		self::$logger->debug( $log_entry, array( 'source' => self::WC_LOG_FILENAME ) );
	}
}