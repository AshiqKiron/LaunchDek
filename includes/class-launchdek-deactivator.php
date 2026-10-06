<?php
/**
 * Plugin deactivation handler.
 *
 * @package LaunchDek
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles plugin deactivation.
 */
class LAUNCHDEK_Deactivator {

	/**
	 * Run deactivation tasks.
	 *
	 * @return void
	 */
	public static function deactivate() {
		if ( ! function_exists( 'launchdek_get_drift_cron_file' ) ) {
			require_once LAUNCHDEK_PLUGIN_DIR . 'includes/launchdek-build.php';
		}
		require_once launchdek_get_drift_cron_file();

		delete_option( LAUNCHDEK_Admin::ACTIVATION_REDIRECT_OPTION );
		delete_transient( LAUNCHDEK_Admin::ACTIVATION_REDIRECT_OPTION );
		LAUNCHDEK_Drift_Cron::deactivate();
	}
}
