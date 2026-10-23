<?php
/**
 * Plugin deactivation handler.
 *
 * @package ChecklistDek
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles plugin deactivation.
 */
class CHECKLISTDEK_Deactivator {

	/**
	 * Run deactivation tasks.
	 *
	 * @return void
	 */
	public static function deactivate() {
		if ( ! function_exists( 'checklistdek_get_drift_cron_file' ) ) {
			require_once CHECKLISTDEK_PLUGIN_DIR . 'includes/checklistdek-build.php';
		}
		require_once checklistdek_get_drift_cron_file();
		require_once CHECKLISTDEK_PLUGIN_DIR . 'includes/class-checklistdek-admin.php';

		delete_option( CHECKLISTDEK_Admin::ACTIVATION_REDIRECT_OPTION );
		delete_transient( CHECKLISTDEK_Admin::ACTIVATION_REDIRECT_OPTION );
		delete_transient( 'launchdek_activation_redirect' );
		CHECKLISTDEK_Drift_Cron::deactivate();
	}
}
