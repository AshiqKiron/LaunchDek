<?php
/**
 * Plugin activation handler.
 *
 * @package LaunchDek
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles plugin activation.
 */
class LAUNCHDEK_Activator {

	/**
	 * Run activation tasks.
	 *
	 * @return void
	 */
	public static function activate() {
		require_once LAUNCHDEK_PLUGIN_DIR . 'includes/class-launchdek-settings.php';
		require_once LAUNCHDEK_PLUGIN_DIR . 'includes/class-launchdek-installer.php';
		require_once LAUNCHDEK_PLUGIN_DIR . 'includes/class-launchdek-capabilities.php';
		require_once LAUNCHDEK_PLUGIN_DIR . 'includes/class-launchdek-site-repository.php';
		require_once LAUNCHDEK_PLUGIN_DIR . 'includes/class-launchdek-checklist-repository.php';
		require_once LAUNCHDEK_PLUGIN_DIR . 'includes/class-launchdek-run-repository.php';
		require_once LAUNCHDEK_PLUGIN_DIR . 'includes/class-launchdek-licensing.php';
		require_once LAUNCHDEK_PLUGIN_DIR . 'includes/class-launchdek-drift-cron.php';

		$defaults = LAUNCHDEK_Settings::get_defaults();

		if ( false === get_option( LAUNCHDEK_Settings::OPTION_NAME, false ) ) {
			add_option( LAUNCHDEK_Settings::OPTION_NAME, $defaults, '', false );
		}

		LAUNCHDEK_Installer::install();
		LAUNCHDEK_Capabilities::register();
		LAUNCHDEK_Licensing::sync_drift_cron();

		update_option( LAUNCHDEK_Settings::VERSION_OPTION, LAUNCHDEK_VERSION, false );
		update_option( LAUNCHDEK_Installer::DB_VERSION_OPTION, LAUNCHDEK_Installer::DB_VERSION, false );

		$user_id = get_current_user_id();
		update_option(
			LAUNCHDEK_Admin::ACTIVATION_REDIRECT_OPTION,
			$user_id > 0 ? $user_id : 1,
			false
		);
	}
}
