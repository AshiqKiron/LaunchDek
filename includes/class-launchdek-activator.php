<?php
/**
 * Plugin activation handler.
 *
 * Purpose: Seed default options on first install and record plugin version.
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

		$defaults = LAUNCHDEK_Settings::get_defaults();

		if ( false === get_option( LAUNCHDEK_Settings::OPTION_NAME, false ) ) {
			add_option( LAUNCHDEK_Settings::OPTION_NAME, $defaults, '', false );
		}

		update_option( LAUNCHDEK_Settings::VERSION_OPTION, LAUNCHDEK_VERSION, false );

		set_transient( 'launchdek_activation_redirect', 1, 30 );
	}
}
