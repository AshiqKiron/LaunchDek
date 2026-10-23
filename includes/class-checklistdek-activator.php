<?php
/**
 * Plugin activation handler.
 *
 * @package ChecklistDek
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles plugin activation.
 */
class CHECKLISTDEK_Activator {

	/**
	 * Run activation tasks.
	 *
	 * @return void
	 */
	public static function activate() {
		require_once CHECKLISTDEK_PLUGIN_DIR . 'includes/class-checklistdek-settings.php';
		require_once CHECKLISTDEK_PLUGIN_DIR . 'includes/class-checklistdek-installer.php';
		require_once CHECKLISTDEK_PLUGIN_DIR . 'includes/class-checklistdek-capabilities.php';
		require_once CHECKLISTDEK_PLUGIN_DIR . 'includes/class-checklistdek-site-repository.php';
		require_once CHECKLISTDEK_PLUGIN_DIR . 'includes/class-checklistdek-checklist-repository.php';
		require_once CHECKLISTDEK_PLUGIN_DIR . 'includes/class-checklistdek-run-repository.php';
		require_once CHECKLISTDEK_PLUGIN_DIR . 'includes/class-checklistdek-licensing.php';
		if ( ! function_exists( 'checklistdek_get_drift_cron_file' ) ) {
			require_once CHECKLISTDEK_PLUGIN_DIR . 'includes/checklistdek-build.php';
		}
		require_once checklistdek_get_drift_cron_file();

		$defaults = CHECKLISTDEK_Settings::get_defaults();

		if ( false === get_option( CHECKLISTDEK_Settings::OPTION_NAME, false ) ) {
			add_option( CHECKLISTDEK_Settings::OPTION_NAME, $defaults, '', false );
		}

		CHECKLISTDEK_Installer::install();
		CHECKLISTDEK_Capabilities::register();
		CHECKLISTDEK_Licensing::sync_drift_cron();

		update_option( CHECKLISTDEK_Settings::VERSION_OPTION, CHECKLISTDEK_VERSION, false );
		update_option( CHECKLISTDEK_Installer::DB_VERSION_OPTION, CHECKLISTDEK_Installer::DB_VERSION, false );

		$user_id = get_current_user_id();
		update_option(
			CHECKLISTDEK_Admin::ACTIVATION_REDIRECT_OPTION,
			$user_id > 0 ? $user_id : 1,
			false
		);
	}
}
