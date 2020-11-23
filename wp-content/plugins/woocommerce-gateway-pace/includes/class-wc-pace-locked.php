<?php
if ( ! defined( 'ABSPATH' ) ) {
	return;
}

/**
 * Woocommerce Pacenow Gateway Locked
 */
class WC_Pace_Locked {
	
	// Pacenow version
	const VERSION = '1.0.0';

	/**
	 * File name
	 * 
	 * @var string
	 */
	protected $file;

	protected $own;

	function __construct() {
		$this->file = fopen( __DIR__ . '/locked/lockfile', 'w+' );
	}

	/**
	 * Locked process until done
	 * @return mixed
	 */
	public function lock() {
		if ( ! flock( $this->file, LOCK_EX | LOCK_NB ) ) {
			WC_Pace_Logger::log( 
				'Unexpected error opening or locking lock file. Perhaps you' . PHP_EOL .
				'don\'t  have permission to write to the lock file or its' . PHP_EOL .
				'containing directory?' 
			);
			return;
		}

		ftruncate( $this->file, 0 );
		fwrite( $this->file, 'Locked' . PHP_EOL );
        fflush( $this->file );

        $this->own = TRUE;

        return TRUE;
	}

	/**
	 * Unlock process
	 * 
	 * @return mixed 
	 */
	public function unlock() {
		if ( $this->own ) {
			if ( ! flock( $this->file, LOCK_UN ) ) {
				WC_Pace_Logger::log( 'Another instance is already running; terminating' );
				return;
			}
			ftruncate( $this->file, 0 );
			fwrite( $this->file, 'Unlocked' . PHP_EOL );
			fflush( $this->file );
			$this->own = FALSE;
		}

		return true;
	}

	function __destruct() {
		if ( $this->own ) {
			$this->unlock();
		}
	}
}