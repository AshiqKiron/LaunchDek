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
 * Whether this install is the Community (free) distribution build.
 *
 * @return bool
 */
function checklistdek_is_community_build() {
	return 'community' === CHECKLISTDEK_BUILD;
}

/**
 * Whether Pro-only PHP modules are bundled and may be loaded.
 *
 * @return bool
 */
function checklistdek_includes_pro_package() {
	return ! checklistdek_is_community_build();
}

/**
 * Whether the hub may auto-deploy or sync the client panel (REST, local path, MainWP ZIP).
 *
 * WordPress.org Community builds must use manual mu-plugin upload only.
 *
 * @return bool
 */
function checklistdek_can_auto_deploy_client_panel() {
	return checklistdek_includes_pro_package();
}

/**
 * Licensing class file for full / Pro distributions (not shipped or loaded on Community).
 *
 * @return string
 */
function checklistdek_get_licensing_file() {
	return CHECKLISTDEK_PLUGIN_DIR . 'includes/community/class-checklistdek-licensing.php';
}

/**
 * Load plan / edition licensing helpers when bundled (full build only).
 *
 * @return void
 */
function checklistdek_require_licensing() {
	if ( checklistdek_is_community_build() ) {
		return;
	}

	require_once checklistdek_get_licensing_file();
}

/**
 * Schedule or clear automated drift verification based on build edition.
 *
 * @return void
 */
function checklistdek_sync_drift_cron() {
	if ( ! class_exists( 'CHECKLISTDEK_Drift_Cron', false ) ) {
		return;
	}

	if ( checklistdek_includes_pro_package() ) {
		CHECKLISTDEK_Drift_Cron::activate();
	} else {
		CHECKLISTDEK_Drift_Cron::deactivate();
	}
}

/**
 * Client panel layout catalog for this build edition.
 *
 * Community zips ship `includes/community/client-panel-layouts.php` only; Pro/full zips ship all layouts.
 *
 * @return string
 */
function checklistdek_get_client_panel_layouts_file() {
	if ( checklistdek_is_community_build() ) {
		return CHECKLISTDEK_PLUGIN_DIR . 'includes/community/client-panel-layouts.php';
	}

	return CHECKLISTDEK_PLUGIN_DIR . 'includes/client-panel-layouts.php';
}

/**
 * Mu-plugin installer class file for this build.
 *
 * @return string
 */
function checklistdek_get_mu_plugin_installer_file() {
	if ( checklistdek_can_auto_deploy_client_panel() ) {
		return CHECKLISTDEK_PLUGIN_DIR . 'includes/class-checklistdek-mu-plugin-installer.php';
	}

	return CHECKLISTDEK_PLUGIN_DIR . 'includes/community/class-checklistdek-mu-plugin-installer.php';
}

/**
 * MainWP client panel agent helper for this build.
 *
 * @return string
 */
function checklistdek_get_mainwp_agent_file() {
	if ( checklistdek_can_auto_deploy_client_panel() ) {
		return CHECKLISTDEK_PLUGIN_DIR . 'includes/integrations/class-checklistdek-mainwp-agent.php';
	}

	return CHECKLISTDEK_PLUGIN_DIR . 'includes/community/class-checklistdek-mainwp-agent.php';
}

/**
 * Absolute path to the webhook dispatcher class file for this build.
 *
 * @return string
 */
function checklistdek_get_webhook_dispatcher_file() {
	if ( checklistdek_includes_pro_package() ) {
		return CHECKLISTDEK_PLUGIN_DIR . 'includes/class-checklistdek-webhook-dispatcher.php';
	}

	return CHECKLISTDEK_PLUGIN_DIR . 'includes/community/class-checklistdek-webhook-dispatcher.php';
}

/**
 * Absolute path to the drift cron class file for this build.
 *
 * @return string
 */
function checklistdek_get_drift_cron_file() {
	if ( checklistdek_includes_pro_package() ) {
		return CHECKLISTDEK_PLUGIN_DIR . 'includes/class-checklistdek-drift-cron.php';
	}

	return CHECKLISTDEK_PLUGIN_DIR . 'includes/community/class-checklistdek-drift-cron.php';
}

/**
 * Load Pro-only class files when present in this build.
 *
 * @return void
 */
function checklistdek_require_pro_modules() {
	if ( ! checklistdek_includes_pro_package() ) {
		return;
	}

	require_once CHECKLISTDEK_PLUGIN_DIR . 'includes/class-checklistdek-auto-capture.php';
	require_once CHECKLISTDEK_PLUGIN_DIR . 'includes/class-checklistdek-email-notifier.php';
}
