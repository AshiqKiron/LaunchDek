<?php
/**
 * Plugin bootstrap — defines constants, loads dependencies, registers lifecycle hooks.
 *
 * Purpose: Single entry point. No business logic here; delegates to LAUNCHDEK_Plugin.
 *
 * Plugin Name: LaunchDek
 * Plugin URI: https://asphaltthemes.com/launchdek
 * Description: Build and manage product launch decks in WordPress.
 * Version: 0.1.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: ashiquzzaman
 * Author URI: https://asphaltthemes.com
 * License: GPL-3.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: launchdek
 * Domain Path: /languages
 *
 * @package LaunchDek
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'LAUNCHDEK_VERSION', '0.1.0' );
define( 'LAUNCHDEK_PLUGIN_FILE', __FILE__ );
define( 'LAUNCHDEK_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'LAUNCHDEK_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'LAUNCHDEK_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'LAUNCHDEK_PLUGIN_SLUG', 'launchdek' );
define( 'LAUNCHDEK_TEXT_DOMAIN', 'launchdek' );
define( 'LAUNCHDEK_PLUGIN_DOCS_URL', 'https://asphaltthemes.com/launchdek/docs' );

require_once LAUNCHDEK_PLUGIN_DIR . 'includes/class-launchdek-activator.php';
require_once LAUNCHDEK_PLUGIN_DIR . 'includes/class-launchdek-deactivator.php';
require_once LAUNCHDEK_PLUGIN_DIR . 'includes/class-launchdek-settings.php';
require_once LAUNCHDEK_PLUGIN_DIR . 'includes/class-launchdek-admin.php';
require_once LAUNCHDEK_PLUGIN_DIR . 'includes/class-launchdek-plugin.php';

register_activation_hook( __FILE__, array( 'LAUNCHDEK_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'LAUNCHDEK_Deactivator', 'deactivate' ) );

/**
 * Initialize and run the plugin.
 *
 * @return LAUNCHDEK_Plugin
 */
function launchdek_run_plugin() {
	static $plugin = null;

	if ( null === $plugin ) {
		$plugin = new LAUNCHDEK_Plugin();
		$plugin->run();
	}

	return $plugin;
}

launchdek_run_plugin();
