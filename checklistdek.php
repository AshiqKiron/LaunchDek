<?php
/**
 * Plugin bootstrap — defines constants, loads dependencies, registers lifecycle hooks.
 *
 * Plugin Name: ChecklistDek
 * Plugin URI: https://asphaltthemes.com/checklistdek
 * Description: Remote WordPress site orchestration for agencies — checklists, audit, and integrations.
 * Version: 1.0.6
 * Requires at least: 6.2
 * Requires PHP: 7.4
 * Author: ashiquzzaman
 * Author URI: https://asphaltthemes.com
 * License: GPL-3.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: checklistdek
 * Domain Path: /languages
 *
 * @package ChecklistDek
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CHECKLISTDEK_VERSION', '1.0.6' );
define( 'CHECKLISTDEK_PLUGIN_FILE', __FILE__ );
define( 'CHECKLISTDEK_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'CHECKLISTDEK_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'CHECKLISTDEK_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'CHECKLISTDEK_PLUGIN_SLUG', 'checklistdek' );
define( 'CHECKLISTDEK_TEXT_DOMAIN', 'checklistdek' );
define( 'CHECKLISTDEK_PLUGIN_DOCS_URL', 'https://asphaltthemes.com/checklistdek/docs' );
define( 'CHECKLISTDEK_REST_NAMESPACE', 'checklistdek/v1' );

require_once CHECKLISTDEK_PLUGIN_DIR . 'includes/checklistdek-build.php';

if ( ! checklistdek_is_community_build() ) {
	require_once CHECKLISTDEK_PLUGIN_DIR . 'includes/checklistdek-freemius.php';
}

// Core.
require_once CHECKLISTDEK_PLUGIN_DIR . 'includes/class-checklistdek-settings.php';
require_once CHECKLISTDEK_PLUGIN_DIR . 'includes/class-checklistdek-admin-deep-links.php';
require_once CHECKLISTDEK_PLUGIN_DIR . 'includes/class-checklistdek-capabilities.php';
require_once CHECKLISTDEK_PLUGIN_DIR . 'includes/class-checklistdek-credential-vault.php';
require_once CHECKLISTDEK_PLUGIN_DIR . 'includes/class-checklistdek-audit-log.php';
require_once CHECKLISTDEK_PLUGIN_DIR . 'includes/class-checklistdek-integrations.php';
require_once CHECKLISTDEK_PLUGIN_DIR . 'includes/class-checklistdek-telemetry-mapper.php';

// Data layer.
require_once CHECKLISTDEK_PLUGIN_DIR . 'includes/class-checklistdek-site-repository.php';
require_once CHECKLISTDEK_PLUGIN_DIR . 'includes/class-checklistdek-checklist-repository.php';
checklistdek_require_licensing();
require_once CHECKLISTDEK_PLUGIN_DIR . 'includes/class-checklistdek-run-repository.php';
require_once CHECKLISTDEK_PLUGIN_DIR . 'includes/class-checklistdek-installer.php';
require_once CHECKLISTDEK_PLUGIN_DIR . 'includes/class-checklistdek-dashboard-cache.php';

// Remote & engine.
require_once CHECKLISTDEK_PLUGIN_DIR . 'includes/class-checklistdek-remote-client.php';
require_once CHECKLISTDEK_PLUGIN_DIR . 'includes/class-checklistdek-connection-tester.php';
require_once CHECKLISTDEK_PLUGIN_DIR . 'includes/class-checklistdek-payload-mapper.php';
require_once CHECKLISTDEK_PLUGIN_DIR . 'includes/class-checklistdek-step-executor.php';
require_once CHECKLISTDEK_PLUGIN_DIR . 'includes/class-checklistdek-checklist-runner.php';
require_once CHECKLISTDEK_PLUGIN_DIR . 'includes/class-checklistdek-client-push.php';
require_once checklistdek_get_mu_plugin_installer_file();
require_once CHECKLISTDEK_PLUGIN_DIR . 'includes/class-checklistdek-drift-verifier.php';
checklistdek_require_pro_modules();
require_once checklistdek_get_webhook_dispatcher_file();
require_once CHECKLISTDEK_PLUGIN_DIR . 'includes/class-checklistdek-templates.php';
require_once CHECKLISTDEK_PLUGIN_DIR . 'includes/class-checklistdek-integration-sync.php';
require_once checklistdek_get_drift_cron_file();
require_once CHECKLISTDEK_PLUGIN_DIR . 'includes/class-checklistdek-rest-api.php';

// Admin.
require_once CHECKLISTDEK_PLUGIN_DIR . 'includes/class-checklistdek-activator.php';
require_once CHECKLISTDEK_PLUGIN_DIR . 'includes/class-checklistdek-deactivator.php';
require_once CHECKLISTDEK_PLUGIN_DIR . 'includes/class-checklistdek-uninstaller.php';
require_once CHECKLISTDEK_PLUGIN_DIR . 'includes/class-checklistdek-admin.php';
require_once CHECKLISTDEK_PLUGIN_DIR . 'includes/class-checklistdek-admin-ajax.php';
require_once CHECKLISTDEK_PLUGIN_DIR . 'includes/class-checklistdek-plugin.php';

register_activation_hook( __FILE__, array( 'CHECKLISTDEK_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'CHECKLISTDEK_Deactivator', 'deactivate' ) );

if ( checklistdek_is_community_build() ) {
	register_uninstall_hook( __FILE__, array( 'CHECKLISTDEK_Uninstaller', 'run' ) );
} elseif ( function_exists( 'cld_fs' ) ) {
	cld_fs()->add_action( 'after_uninstall', array( 'CHECKLISTDEK_Uninstaller', 'run' ) );
}

/**
 * Initialize and run the plugin.
 *
 * @return CHECKLISTDEK_Plugin
 */
function checklistdek_run_plugin() {
	static $plugin = null;

	if ( null === $plugin ) {
		$plugin = new CHECKLISTDEK_Plugin();
		$plugin->run();
	}

	return $plugin;
}

checklistdek_run_plugin();
