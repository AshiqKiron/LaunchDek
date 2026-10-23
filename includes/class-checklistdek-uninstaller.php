<?php
/**
 * Plugin uninstall cleanup.
 *
 * @package ChecklistDek
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Removes ChecklistDek options, tables, and capabilities on uninstall.
 */
class CHECKLISTDEK_Uninstaller {

	/**
	 * Run full uninstall cleanup.
	 *
	 * @return void
	 */
	public static function run() {
		delete_option( CHECKLISTDEK_Settings::OPTION_NAME );
		delete_option( CHECKLISTDEK_Licensing::OPTION_NAME );
		delete_option( CHECKLISTDEK_Settings::VERSION_OPTION );
		delete_option( 'checklistdek_templates_seeded' );
		delete_option( CHECKLISTDEK_Templates::CATALOG_OPTION );
		delete_option( 'checklistdek_drift_status' );
		delete_option( CHECKLISTDEK_Installer::DB_VERSION_OPTION );
		delete_option( 'checklistdek_activation_redirect' );
		delete_transient( 'checklistdek_activation_redirect' );
		delete_transient( 'launchdek_activation_redirect' );
		CHECKLISTDEK_Dashboard_Cache::clear();

		CHECKLISTDEK_Installer::uninstall();
		CHECKLISTDEK_Capabilities::unregister();
	}
}
