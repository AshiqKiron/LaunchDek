<?php
/**
 * Community build stub — scheduled drift cron is not bundled in the free distribution.
 *
 * @package LaunchDek
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Drift cron stub (manual drift verify on Batch Run remains available).
 */
class LAUNCHDEK_Drift_Cron {

	const HOOK = 'launchdek_drift_verify_cron';

	/**
	 * @return void
	 */
	public static function register() {
	}

	/**
	 * @return void
	 */
	public static function activate() {
	}

	/**
	 * @return void
	 */
	public static function deactivate() {
		wp_clear_scheduled_hook( self::HOOK );
	}
}
