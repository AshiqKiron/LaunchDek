<?php
/**
 * Plugin bootstrap — defines constants, loads dependencies, registers lifecycle hooks.
 *
 * Plugin Name: LaunchDek
 * Plugin URI: https://asphaltthemes.com/launchdek
 * Description: Remote WordPress site orchestration for agencies — checklists, audit, and integrations.
 * Version: 1.0.0
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

define( 'LAUNCHDEK_VERSION', '1.0.0' );
define( 'LAUNCHDEK_PLUGIN_FILE', __FILE__ );
define( 'LAUNCHDEK_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'LAUNCHDEK_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'LAUNCHDEK_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'LAUNCHDEK_PLUGIN_SLUG', 'launchdek' );
define( 'LAUNCHDEK_TEXT_DOMAIN', 'launchdek' );
define( 'LAUNCHDEK_PLUGIN_DOCS_URL', 'https://asphaltthemes.com/launchdek/docs' );
define( 'LAUNCHDEK_REST_NAMESPACE', 'launchdek/v1' );

// Core.
require_once LAUNCHDEK_PLUGIN_DIR . 'includes/class-launchdek-settings.php';
require_once LAUNCHDEK_PLUGIN_DIR . 'includes/class-launchdek-installer.php';
require_once LAUNCHDEK_PLUGIN_DIR . 'includes/class-launchdek-capabilities.php';
require_once LAUNCHDEK_PLUGIN_DIR . 'includes/class-launchdek-credential-vault.php';
require_once LAUNCHDEK_PLUGIN_DIR . 'includes/class-launchdek-audit-log.php';

// Data layer.
require_once LAUNCHDEK_PLUGIN_DIR . 'includes/class-launchdek-site-repository.php';
require_once LAUNCHDEK_PLUGIN_DIR . 'includes/class-launchdek-checklist-repository.php';
require_once LAUNCHDEK_PLUGIN_DIR . 'includes/class-launchdek-run-repository.php';

// Remote & engine.
require_once LAUNCHDEK_PLUGIN_DIR . 'includes/class-launchdek-remote-client.php';
require_once LAUNCHDEK_PLUGIN_DIR . 'includes/class-launchdek-connection-tester.php';
require_once LAUNCHDEK_PLUGIN_DIR . 'includes/class-launchdek-payload-mapper.php';
require_once LAUNCHDEK_PLUGIN_DIR . 'includes/class-launchdek-step-executor.php';
require_once LAUNCHDEK_PLUGIN_DIR . 'includes/class-launchdek-checklist-runner.php';
require_once LAUNCHDEK_PLUGIN_DIR . 'includes/class-launchdek-client-push.php';
require_once LAUNCHDEK_PLUGIN_DIR . 'includes/class-launchdek-mu-plugin-installer.php';
require_once LAUNCHDEK_PLUGIN_DIR . 'includes/class-launchdek-auto-capture.php';
require_once LAUNCHDEK_PLUGIN_DIR . 'includes/class-launchdek-drift-verifier.php';
require_once LAUNCHDEK_PLUGIN_DIR . 'includes/class-launchdek-webhook-dispatcher.php';
require_once LAUNCHDEK_PLUGIN_DIR . 'includes/class-launchdek-templates.php';
require_once LAUNCHDEK_PLUGIN_DIR . 'includes/class-launchdek-integrations.php';
require_once LAUNCHDEK_PLUGIN_DIR . 'includes/class-launchdek-drift-cron.php';
require_once LAUNCHDEK_PLUGIN_DIR . 'includes/class-launchdek-rest-api.php';

// Admin.
require_once LAUNCHDEK_PLUGIN_DIR . 'includes/class-launchdek-activator.php';
require_once LAUNCHDEK_PLUGIN_DIR . 'includes/class-launchdek-deactivator.php';
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
