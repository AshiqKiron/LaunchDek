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
