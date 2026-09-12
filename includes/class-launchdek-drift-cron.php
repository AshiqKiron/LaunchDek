<?php
/**
 * Scheduled drift verification cron.
 *
 * @package LaunchDek
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Drift cron handler.
 */
class LAUNCHDEK_Drift_Cron {

	const HOOK = 'launchdek_drift_verify_cron';

	/**
	 * Register cron schedule.
	 *
	 * @return void
	 */
	public static function register() {
		add_filter( 'cron_schedules', array( __CLASS__, 'add_schedule' ) );
		add_action( self::HOOK, array( __CLASS__, 'run' ) );
	}

	/**
	 * Schedule cron on activation.
	 *
	 * @return void
	 */
	public static function activate() {
		if ( ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_event( time(), 'twicedaily', self::HOOK );
		}
	}

	/**
	 * Clear cron on deactivation.
	 *
	 * @return void
	 */
	public static function deactivate() {
		wp_clear_scheduled_hook( self::HOOK );
	}

	/**
	 * Add custom schedule.
	 *
	 * @param array $schedules Schedules.
	 * @return array
	 */
	public static function add_schedule( $schedules ) {
		return $schedules;
	}

	/**
	 * Run drift verification for all sites.
	 *
	 * @return void
	 */
	public static function run() {
		$settings = LAUNCHDEK_Settings::get();

		if ( empty( $settings['enabled'] ) || empty( $settings['drift_verification_enabled'] ) ) {
			return;
		}

		LAUNCHDEK_Drift_Verifier::verify_all();
	}
}
