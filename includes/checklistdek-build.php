<?php
/**
 * Build edition helpers (full plugin vs Community / WordPress.org zip).
 *
 * @package ChecklistDek
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'CHECKLISTDEK_BUILD' ) ) {
	define( 'CHECKLISTDEK_BUILD', 'full' );
}

if ( ! defined( 'CHECKLISTDEK_LEGACY_BILLING_OPTION' ) ) {
	define( 'CHECKLISTDEK_LEGACY_BILLING_OPTION', 'checklistdek_billing' );
}

/**
 * Load Freemius when bundled (Pro / premium zip — omitted from WordPress.org Community zip).
 *
 * @return void
 */
function checklistdek_load_freemius() {
	static $loaded = false;

	if ( $loaded ) {
		return;
	}

	$file = CHECKLISTDEK_PLUGIN_DIR . 'includes/checklistdek-freemius.php';
	if ( ! is_readable( $file ) ) {
		return;
	}

	require_once $file;
	$loaded = true;
}

/**
 * Load Pro licensing when bundled (WordPress.org free zip omits all licensing PHP).
 *
 * @return void
 */
function checklistdek_load_licensing() {
	static $loaded = false;

	if ( $loaded ) {
		return;
	}

	$file = CHECKLISTDEK_PLUGIN_DIR . 'includes/class-checklistdek-licensing.php';
	if ( is_readable( $file ) ) {
		require_once $file;
	}

	$loaded = true;
}

/**
 * Whether this install ships the Pro module bundle (Pro zip only — omitted from WordPress.org free zip).
 *
 * @return bool
 */
function checklistdek_is_pro_distribution() {
	return checklistdek_install_bundles_premium_modules();
}

/**
 * Whether the admin sidebar should include the external Upgrade item (Pro zip without an active license only).
 *
 * @return bool
 */
function checklistdek_should_show_upgrade_submenu() {
	if ( checklistdek_is_pro_distribution() ) {
		if ( class_exists( 'CHECKLISTDEK_Licensing', false ) ) {
			return CHECKLISTDEK_Licensing::should_show_upgrade_submenu();
		}

		return false;
	}

	// WordPress.org package: external pricing link only (no in-plugin licensing UI).
	return true;
}

/**
 * Whether the hub may auto-deploy or sync the client panel (REST, local path, MainWP ZIP).
 *
 * @return bool
 */
function checklistdek_can_auto_deploy_client_panel() {
	if ( ! checklistdek_is_pro_distribution() ) {
		return false;
	}

	if ( class_exists( 'CHECKLISTDEK_Licensing', false ) ) {
		return CHECKLISTDEK_Licensing::can_auto_deploy_client_panel();
	}

	return is_readable( CHECKLISTDEK_PLUGIN_DIR . 'includes/class-checklistdek-mu-plugin-installer.php' );
}

/**
 * Whether this install bundles Pro-only premium modules (WordPress.org zip omits the loader file).
 *
 * @return bool
 */
function checklistdek_install_bundles_premium_modules() {
	return is_readable( CHECKLISTDEK_PLUGIN_DIR . 'includes/checklistdek-premium-modules.php' );
}

/**
 * Load Pro-only modules when the premium loader is bundled.
 *
 * @return void
 */
function checklistdek_load_premium_modules() {
	static $loaded = false;

	if ( $loaded ) {
		return;
	}

	$file = CHECKLISTDEK_PLUGIN_DIR . 'includes/checklistdek-premium-modules.php';
	if ( is_readable( $file ) ) {
		require_once $file;
	}

	$loaded = true;
}

/**
 * @return bool
 */
function checklistdek_can_use_auto_capture() {
	if ( ! checklistdek_is_pro_distribution() ) {
		return false;
	}

	if ( class_exists( 'CHECKLISTDEK_Licensing', false ) ) {
		return CHECKLISTDEK_Licensing::can_use_auto_capture();
	}

	return false;
}

/**
 * @return bool
 */
function checklistdek_can_use_notifications() {
	if ( ! checklistdek_is_pro_distribution() ) {
		return false;
	}

	if ( class_exists( 'CHECKLISTDEK_Licensing', false ) ) {
		return CHECKLISTDEK_Licensing::can_use_notifications();
	}

	return false;
}

/**
 * @return bool
 */
function checklistdek_can_use_scheduled_drift() {
	if ( ! checklistdek_is_pro_distribution() ) {
		return false;
	}

	if ( class_exists( 'CHECKLISTDEK_Licensing', false ) ) {
		return CHECKLISTDEK_Licensing::can_use_scheduled_drift();
	}

	return false;
}

/**
 * Panel layout choices for Settings UI.
 *
 * @return array<string, array{label: string, description: string}>
 */
function checklistdek_get_panel_layouts_for_plan() {
	if ( class_exists( 'CHECKLISTDEK_Licensing', false ) ) {
		return CHECKLISTDEK_Licensing::get_panel_layouts_for_plan();
	}

	return CHECKLISTDEK_Settings::get_client_panel_layouts();
}

/**
 * @param array $templates Template definitions.
 * @return array
 */
function checklistdek_filter_builtin_templates( $templates ) {
	if ( class_exists( 'CHECKLISTDEK_Licensing', false ) ) {
		return CHECKLISTDEK_Licensing::filter_builtin_templates( $templates );
	}

	if ( ! is_array( $templates ) ) {
		return array();
	}

	return array_values( $templates );
}

/**
 * Schedule or clear automated drift verification based on plan and bundled modules.
 *
 * @return void
 */
function checklistdek_sync_drift_cron() {
	if ( ! class_exists( 'CHECKLISTDEK_Drift_Cron', false ) ) {
		return;
	}

	if ( checklistdek_can_use_scheduled_drift() ) {
		CHECKLISTDEK_Drift_Cron::activate();
	} else {
		CHECKLISTDEK_Drift_Cron::deactivate();
	}
}

/**
 * Client panel layout catalog for this install (full catalog when bundled, else Community file).
 *
 * @return string
 */
function checklistdek_get_client_panel_layouts_file() {
	$full = CHECKLISTDEK_PLUGIN_DIR . 'includes/client-panel-layouts.php';
	if ( is_readable( $full ) ) {
		return $full;
	}

	return CHECKLISTDEK_PLUGIN_DIR . 'includes/community/client-panel-layouts.php';
}

/**
 * Mu-plugin installer class file for this build.
 *
 * @return string
 */
function checklistdek_get_mu_plugin_installer_file() {
	$full = CHECKLISTDEK_PLUGIN_DIR . 'includes/class-checklistdek-mu-plugin-installer.php';
	if ( is_readable( $full ) ) {
		return $full;
	}

	return CHECKLISTDEK_PLUGIN_DIR . 'includes/community/class-checklistdek-mu-plugin-installer.php';
}

/**
 * MainWP client panel agent helper for this build.
 *
 * @return string
 */
function checklistdek_get_mainwp_agent_file() {
	$full = CHECKLISTDEK_PLUGIN_DIR . 'includes/integrations/class-checklistdek-mainwp-agent.php';
	if ( is_readable( $full ) ) {
		return $full;
	}

	return CHECKLISTDEK_PLUGIN_DIR . 'includes/community/class-checklistdek-mainwp-agent.php';
}

/**
 * Absolute path to the webhook dispatcher class file for this build.
 *
 * @return string
 */
function checklistdek_get_webhook_dispatcher_file() {
	$full = CHECKLISTDEK_PLUGIN_DIR . 'includes/class-checklistdek-webhook-dispatcher.php';
	if ( is_readable( $full ) ) {
		return $full;
	}

	return CHECKLISTDEK_PLUGIN_DIR . 'includes/community/class-checklistdek-webhook-dispatcher.php';
}

/**
 * Absolute path to the drift cron class file for this build.
 *
 * @return string
 */
function checklistdek_get_drift_cron_file() {
	$full = CHECKLISTDEK_PLUGIN_DIR . 'includes/class-checklistdek-drift-cron.php';
	if ( is_readable( $full ) ) {
		return $full;
	}

	return CHECKLISTDEK_PLUGIN_DIR . 'includes/community/class-checklistdek-drift-cron.php';
}

/**
 * Load Pro-only class files when present in this install.
 *
 * @return void
 */
function checklistdek_require_pro_modules() {
	$base = CHECKLISTDEK_PLUGIN_DIR . 'includes/';

	$auto_capture = $base . 'class-checklistdek-auto-capture.php';
	if ( is_readable( $auto_capture ) ) {
		require_once $auto_capture;
	}

	$email_notifier = $base . 'class-checklistdek-email-notifier.php';
	if ( is_readable( $email_notifier ) ) {
		require_once $email_notifier;
	}
}
