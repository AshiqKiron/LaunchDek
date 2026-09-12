<?php
/**
 * Uninstall cleanup.
 *
 * Purpose: Remove all plugin options when the plugin is deleted (not deactivated).
 *
 * @package LaunchDek
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

require_once plugin_dir_path( __FILE__ ) . 'includes/class-launchdek-settings.php';

delete_option( LAUNCHDEK_Settings::OPTION_NAME );
delete_option( LAUNCHDEK_Settings::VERSION_OPTION );
