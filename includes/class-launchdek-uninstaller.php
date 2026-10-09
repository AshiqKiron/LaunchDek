<?php
/**
 * Plugin uninstall cleanup.
 *
 * @package LaunchDek
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Removes LaunchDek options, tables, and capabilities on uninstall.
 */
class LAUNCHDEK_Uninstaller {

	/**
	 * Run full uninstall cleanup.
	 *
	 * @return void
	 */
	public static function run() {
		delete_option( LAUNCHDEK_Settings::OPTION_NAME );
		delete_option( LAUNCHDEK_Licensing::OPTION_NAME );
		delete_option( LAUNCHDEK_Settings::VERSION_OPTION );
		delete_option( 'launchdek_templates_seeded' );
		delete_option( LAUNCHDEK_Templates::CATALOG_OPTION );
		delete_option( 'launchdek_drift_status' );
		delete_option( LAUNCHDEK_Installer::DB_VERSION_OPTION );
		delete_option( 'launchdek_activation_redirect' );
		delete_transient( 'launchdek_activation_redirect' );
		LAUNCHDEK_Dashboard_Cache::clear();

		LAUNCHDEK_Installer::uninstall();
		LAUNCHDEK_Capabilities::unregister();
	}
}
