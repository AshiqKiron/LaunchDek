<?php
/**
 * Uninstall cleanup.
 *
 * @package LaunchDek
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

require_once plugin_dir_path( __FILE__ ) . 'includes/class-launchdek-settings.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-launchdek-installer.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-launchdek-capabilities.php';

delete_option( LAUNCHDEK_Settings::OPTION_NAME );
delete_option( LAUNCHDEK_Settings::VERSION_OPTION );
delete_option( 'launchdek_templates_seeded' );
delete_option( 'launchdek_drift_status' );
delete_option( LAUNCHDEK_Installer::DB_VERSION_OPTION );

LAUNCHDEK_Installer::uninstall();
LAUNCHDEK_Capabilities::unregister();
