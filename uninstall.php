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

require_once plugin_dir_path( __FILE__ ) . 'includes/class-launchdek-dashboard-cache.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-launchdek-templates.php';

delete_option( LAUNCHDEK_Settings::OPTION_NAME );
delete_option( LAUNCHDEK_Settings::VERSION_OPTION );
delete_option( 'launchdek_templates_seeded' );
delete_option( LAUNCHDEK_Templates::CATALOG_OPTION );
delete_option( 'launchdek_drift_status' );
delete_option( LAUNCHDEK_Installer::DB_VERSION_OPTION );
LAUNCHDEK_Dashboard_Cache::clear();

LAUNCHDEK_Installer::uninstall();
LAUNCHDEK_Capabilities::unregister();
