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
		require_once LAUNCHDEK_PLUGIN_DIR . 'includes/class-launchdek-drift-cron.php';

		delete_transient( 'launchdek_activation_redirect' );
		LAUNCHDEK_Drift_Cron::deactivate();
	}
}
